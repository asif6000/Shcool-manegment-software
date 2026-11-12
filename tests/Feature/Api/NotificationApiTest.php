<?php

namespace Tests\Feature\Api;

use App\Models\DevicePushToken;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\SchoolEventNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_notification_endpoints_require_a_valid_token(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/devices', [])->assertUnauthorized();
    }

    public function test_the_api_lists_only_the_authenticated_users_notifications(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $token = $this->tokenFor($admin);

        $this->notify($admin, ['title' => 'Admin only']);
        $this->notify($teacher, ['title' => 'Teacher only']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.title', 'Admin only')
            ->assertJsonStructure([
                'data' => [['id', 'category', 'title', 'message', 'icon', 'url', 'is_read', 'read_at', 'created_at']],
            ]);
    }

    public function test_the_api_filters_by_unread_state_and_category(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $token = $this->tokenFor($admin);

        $this->notify($admin, ['title' => 'Unread fee', 'category' => 'fees']);
        $this->notify($admin, ['title' => 'Unread exam', 'category' => 'exams']);
        $this->notify($admin, ['title' => 'Read fee', 'category' => 'fees'])->markAsRead();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonMissing(['title' => 'Read fee']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?category=fees')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonMissing(['title' => 'Unread exam']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?per_page=0')
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?category=unknown')
            ->assertStatus(422);
    }

    public function test_the_unread_count_reflects_only_the_authenticated_user(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $token = $this->tokenFor($admin);

        $read = $this->notify($admin);
        $this->notify($admin);
        $read->markAsRead();
        $this->notify($teacher);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_marking_a_notification_read_is_scoped_to_the_owner(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $adminToken = $this->tokenFor($admin);

        $own = $this->notify($admin);
        $foreign = $this->notify($teacher);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/v1/notifications/'.$own->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($own->fresh()->read_at);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/v1/notifications/'.$foreign->id.'/read')
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_mark_all_read_only_updates_the_authenticated_users_inbox(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $token = $this->tokenFor($admin);

        $this->notify($admin);
        $this->notify($admin);
        $foreign = $this->notify($teacher);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 2)
            ->assertJsonPath('data.count', 0);

        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_api_preferences_default_to_enabled_and_can_be_updated(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $token = $this->tokenFor($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.preferences.fees.webpush', true)
            ->assertJsonPath('data.preferences.general.expo', true)
            ->assertJsonStructure(['data' => ['categories', 'channels', 'preferences' => ['fees' => ['webpush', 'expo']]]]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    ['category' => 'fees', 'channel' => 'webpush', 'enabled' => false],
                    ['category' => 'attendance', 'channel' => 'expo', 'enabled' => false],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.preferences.fees.webpush', false)
            ->assertJsonPath('data.preferences.attendance.expo', false);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $admin->id,
            'category' => 'fees',
            'channel' => 'webpush',
            'enabled' => false,
        ]);
    }

    public function test_api_preferences_validate_categories_and_channels(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $token = $this->tokenFor($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    ['category' => 'unknown', 'channel' => 'sms', 'enabled' => 'yes'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'preferences.0.category',
                'preferences.0.channel',
                'preferences.0.enabled',
            ]);
    }

    public function test_an_expo_device_token_can_be_registered_and_revoked(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $token = $this->tokenFor($admin);
        $expoToken = 'ExponentPushToken[androidDevice_1234]';

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/notifications/devices', [
                'platform' => 'android',
                'token' => $expoToken,
                'device_name' => 'Pixel 8',
                'app_version' => '1.0.0',
            ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.is_active', true);

        $deviceId = (int) $response->json('data.id');
        $device = DevicePushToken::query()->findOrFail($deviceId);

        $this->assertSame($admin->id, $device->user_id);
        $this->assertSame(DevicePushToken::hashToken($expoToken), $device->token_hash);
        $this->assertSame($expoToken, $device->token);

        $raw = DB::table('device_push_tokens')->where('id', $deviceId)->first();
        $this->assertNotSame($expoToken, $raw->token);

        $this->assertArrayNotHasKey('token', $response->json('data'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/notifications/devices', [
                'platform' => 'android',
                'token' => $expoToken,
                'device_name' => 'Pixel 8 Pro',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $deviceId);

        $this->assertSame(1, DevicePushToken::query()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/notifications/devices/'.$deviceId)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($device->fresh()->is_active);
        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_device_token_validation_and_ownership_are_enforced(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $adminToken = $this->tokenFor($admin);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/v1/notifications/devices', [
                'platform' => 'windows',
                'token' => 'not-an-expo-token',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'token']);

        $device = DevicePushToken::query()->create([
            'user_id' => $admin->id,
            'platform' => 'android',
            'token' => 'ExponentPushToken[ownedByAdmin1234]',
            'token_hash' => DevicePushToken::hashToken('ExponentPushToken[ownedByAdmin1234]'),
            'is_active' => true,
        ]);

        $teacherToken = $this->tokenFor($teacher);

        $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->deleteJson('/api/v1/notifications/devices/'.$device->id)
            ->assertNotFound();

        $this->assertTrue($device->fresh()->is_active);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function notify(User $user, array $overrides = []): Notification
    {
        return $user->notifications()->create([
            'id' => (string) Str::orderedUuid(),
            'type' => SchoolEventNotification::class,
            'data' => array_merge([
                'title' => 'Test notification',
                'message' => 'A test message.',
                'icon' => 'bi-bell',
                'url' => route('notifications.index'),
                'category' => 'general',
            ], $overrides),
            'category' => $overrides['category'] ?? 'general',
        ]);
    }

    private function tokenFor(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();

        // Each HTTP call in a test shares the app container, and the Sanctum
        // request guard caches the first resolved user. Forget the guards so
        // the next request authenticates as the newly signed-in account.
        $this->app['auth']->forgetGuards();

        return (string) $response->json('data.token');
    }
}
