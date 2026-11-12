<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushNotification;
use App\Models\Notification;
use App\Models\PushDeliveryLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\SchoolEventNotification;
use App\Services\NotificationService;
use App\Services\WebPushSender;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;
use Throwable;

class PushNotificationJobTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_queued_push_is_sent_and_logged_for_an_active_subscription(): void
    {
        [$user, $notification, $subscription] = $this->fixtures();
        $sender = $this->fakeSender();

        $this->runJob($notification, $user);

        $log = PushDeliveryLog::query()->firstOrFail();

        $this->assertSame(PushDeliveryLog::STATUS_SENT, $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertSame(201, $log->response_code);
        $this->assertNotNull($log->sent_at);
        $this->assertNotNull($subscription->fresh()->last_used_at);

        $this->assertCount(1, $sender->payloads);
        $this->assertSame('Lock-screen title', $sender->payloads[0]['title']);
        $this->assertSame('Lock-screen body', $sender->payloads[0]['body']);
        $this->assertSame($notification->id, $sender->payloads[0]['tag']);
    }

    public function test_nothing_is_sent_when_web_push_is_disabled(): void
    {
        [$user, $notification, $subscription] = $this->fixtures();

        config(['webpush.enabled' => false]);

        $sender = $this->fakeSender();

        $this->runJob($notification, $user);

        $this->assertSame(0, PushDeliveryLog::query()->count());
        $this->assertNull($subscription->fresh()->last_used_at);
        $this->assertCount(0, $sender->payloads);
    }

    public function test_a_disabled_category_preference_stops_delivery(): void
    {
        [$user, $notification] = $this->fixtures(category: 'fees');

        $user->notificationPreferences()->create([
            'category' => 'fees',
            'channel' => 'webpush',
            'enabled' => false,
        ]);

        $sender = $this->fakeSender();

        $this->runJob($notification, $user);

        $this->assertSame(0, PushDeliveryLog::query()->count());
        $this->assertCount(0, $sender->payloads);
    }

    public function test_an_expired_subscription_is_deactivated_and_marked_invalid(): void
    {
        [$user, $notification, $subscription] = $this->fixtures();

        $this->fakeSender([
            'skipped' => false,
            'success' => false,
            'expired' => true,
            'status' => 410,
            'reason' => 'Gone',
        ]);

        $this->runJob($notification, $user);

        $log = PushDeliveryLog::query()->firstOrFail();
        $this->assertSame(PushDeliveryLog::STATUS_INVALID, $log->status);
        $this->assertSame('subscription_expired', $log->error_code);
        $this->assertFalse($subscription->fresh()->is_active);
    }

    public function test_an_already_delivered_notification_is_not_sent_again(): void
    {
        [$user, $notification, $subscription] = $this->fixtures();

        PushDeliveryLog::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'push_subscription_id' => $subscription->id,
            'channel' => 'webpush',
            'status' => PushDeliveryLog::STATUS_SENT,
            'attempts' => 1,
            'sent_at' => now()->subMinute(),
        ]);

        $sender = $this->fakeSender(exception: new RuntimeException('should not be called'));

        $this->runJob($notification, $user);

        $log = PushDeliveryLog::query()->firstOrFail();
        $this->assertSame(PushDeliveryLog::STATUS_SENT, $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertCount(0, $sender->payloads);
    }

    public function test_a_transport_error_is_logged_without_leaking_the_endpoint_and_is_rethrown(): void
    {
        [$user, $notification] = $this->fixtures();

        $this->fakeSender(
            exception: new RuntimeException('cURL error for https://push.example.test/secret-endpoint'),
        );

        try {
            $this->runJob($notification, $user);
            $this->fail('The job should rethrow transport failures so the queue retries.');
        } catch (RuntimeException) {
            // Expected: the queue worker is responsible for retrying.
        }

        $log = PushDeliveryLog::query()->firstOrFail();

        $this->assertSame(PushDeliveryLog::STATUS_FAILED, $log->status);
        $this->assertSame('transport_error', $log->error_code);
        $this->assertSame('cURL error for [url]', $log->error_message);
        $this->assertStringNotContainsString('push.example.test', (string) $log->error_message);
    }

    public function test_a_transient_rejection_is_recorded_and_retried(): void
    {
        [$user, $notification, $subscription] = $this->fixtures();

        $this->fakeSender([
            'skipped' => false,
            'success' => false,
            'expired' => false,
            'status' => 503,
            'reason' => 'Service Unavailable',
        ]);

        try {
            $this->runJob($notification, $user);
            $this->fail('A transient rejection should be retried by the queue.');
        } catch (RuntimeException) {
            // Expected.
        }

        $log = PushDeliveryLog::query()->firstOrFail();

        $this->assertSame(PushDeliveryLog::STATUS_FAILED, $log->status);
        $this->assertSame('push_rejected', $log->error_code);
        $this->assertSame(1, $subscription->fresh()->failure_count);
    }

    public function test_a_permanent_rejection_is_logged_without_retrying(): void
    {
        [$user, $notification, $subscription] = $this->fixtures();

        $this->fakeSender([
            'skipped' => false,
            'success' => false,
            'expired' => false,
            'status' => 400,
            'reason' => 'Bad Request',
        ]);

        $this->runJob($notification, $user);

        $log = PushDeliveryLog::query()->firstOrFail();
        $this->assertSame(PushDeliveryLog::STATUS_FAILED, $log->status);
        $this->assertSame(1, $subscription->fresh()->failure_count);
    }

    public function test_a_job_for_another_users_notification_is_ignored(): void
    {
        [$user, $notification] = $this->fixtures();
        $other = $this->makeUser($user->school, 'teacher');

        $sender = $this->fakeSender();

        $this->runJob($notification, $other);

        $this->assertSame(0, PushDeliveryLog::query()->count());
        $this->assertCount(0, $sender->payloads);
    }

    public function test_a_deleted_notification_is_ignored(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'school_admin');
        $sender = $this->fakeSender();

        $job = new SendWebPushNotification((string) Str::uuid(), $user->id);
        $job->handle(app(NotificationService::class), app(WebPushSender::class));

        $this->assertSame(0, PushDeliveryLog::query()->count());
        $this->assertCount(0, $sender->payloads);
    }

    /**
     * @return array{0: User, 1: Notification, 2: PushSubscription}
     */
    private function fixtures(string $category = 'attendance'): array
    {
        config([
            'webpush.enabled' => true,
            'webpush.public_key' => 'test-public-key',
            'webpush.private_key' => 'test-private-key',
            'webpush.subject' => 'mailto:notifications@example.test',
            'webpush.queue' => 'sync',
        ]);

        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'school_admin');

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => SchoolEventNotification::class,
            'data' => [
                'title' => 'Inbox title',
                'message' => 'Inbox message',
                'icon' => 'bi-bell',
                'url' => route('notifications.index'),
                'category' => $category,
                'push_title' => 'Lock-screen title',
                'push_body' => 'Lock-screen body',
            ],
            'category' => $category,
        ]);

        $endpoint = 'https://push.example.test/send/job-test';

        $subscription = PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::endpointHash($endpoint),
            'public_key' => str_repeat('B', 20),
            'auth_token' => str_repeat('a', 16),
            'content_encoding' => 'aes128gcm',
            'is_active' => true,
        ]);

        return [$user, $notification, $subscription];
    }

    private function runJob(Notification $notification, User $user): void
    {
        $job = new SendWebPushNotification($notification->id, $user->id);
        $job->handle(app(NotificationService::class), app(WebPushSender::class));
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function fakeSender(?array $result = null, ?Throwable $exception = null): WebPushSender
    {
        $fake = new class($result, $exception) extends WebPushSender
        {
            /** @var list<array<string, mixed>> */
            public array $payloads = [];

            /**
             * @param  array<string, mixed>|null  $result
             */
            public function __construct(
                private readonly ?array $result,
                private readonly ?Throwable $exception,
            ) {}

            public function enabled(): bool
            {
                return true;
            }

            public function send(PushSubscription $subscription, array $payload): array
            {
                if ($this->exception !== null) {
                    throw $this->exception;
                }

                $this->payloads[] = $payload;

                return $this->result ?? [
                    'skipped' => false,
                    'success' => true,
                    'expired' => false,
                    'status' => 201,
                    'reason' => 'Created',
                ];
            }
        };

        $this->app->instance(WebPushSender::class, $fake);

        return $fake;
    }
}
