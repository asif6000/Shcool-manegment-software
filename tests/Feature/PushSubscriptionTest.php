<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_signed_in_user_can_register_a_browser_push_subscription(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $endpoint = 'https://push.example.test/send/browser-one';

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload($endpoint))
            ->assertCreated()
            ->assertJsonPath('subscribed', true);

        $stored = PushSubscription::query()->firstOrFail();

        $this->assertSame($admin->id, $stored->user_id);
        $this->assertSame($endpoint, $stored->endpoint);
        $this->assertSame(hash('sha256', $endpoint), $stored->endpoint_hash);
        $this->assertTrue($stored->is_active);
        $this->assertSame('aes128gcm', $stored->content_encoding);
    }

    public function test_the_auth_secret_is_encrypted_at_rest(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload())
            ->assertCreated();

        $raw = DB::table('push_subscriptions')->first();
        $auth = str_repeat('a', 16);

        $this->assertIsString($raw->auth_token);
        $this->assertNotSame($auth, $raw->auth_token);
        $this->assertSame($auth, PushSubscription::query()->firstOrFail()->auth_token);
    }

    public function test_registering_the_same_endpoint_twice_updates_instead_of_duplicating(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $first = $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload())
            ->assertCreated();

        $second = $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload())
            ->assertOk()
            ->assertJsonPath('id', $first->json('id'));

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertNotSame(null, $second->json('id'));
    }

    public function test_a_subscription_follows_the_device_to_the_newly_signed_in_user(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload())
            ->assertCreated();

        $this->actingAs($teacher)
            ->postJson(route('notifications.push.subscribe'), $this->payload())
            ->assertOk();

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame($teacher->id, PushSubscription::query()->firstOrFail()->user_id);
    }

    public function test_unsubscribing_deactivates_only_the_authenticated_users_subscription(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $endpoint = 'https://push.example.test/send/shared-browser';

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload($endpoint))
            ->assertCreated();

        $this->actingAs($teacher)
            ->deleteJson(route('notifications.push.unsubscribe'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJsonPath('subscribed', false);

        $this->assertTrue(PushSubscription::query()->firstOrFail()->is_active);

        $this->actingAs($admin)
            ->deleteJson(route('notifications.push.unsubscribe'), ['endpoint' => $endpoint])
            ->assertOk();

        $this->assertFalse(PushSubscription::query()->firstOrFail()->is_active);
    }

    public function test_a_user_cannot_exceed_the_configured_subscription_limit(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        config(['webpush.max_subscriptions_per_user' => 2]);

        foreach ([1, 2] as $index) {
            $this->actingAs($admin)
                ->postJson(route('notifications.push.subscribe'), $this->payload('https://push.example.test/send/'.$index))
                ->assertCreated();
        }

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload('https://push.example.test/send/3'))
            ->assertStatus(422);

        $this->assertSame(2, PushSubscription::query()->count());
    }

    public function test_push_subscriptions_require_https_and_valid_keys(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), $this->payload('ftp://push.example.test/send/x'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->actingAs($admin)
            ->postJson(route('notifications.push.subscribe'), [
                'endpoint' => 'https://push.example.test/send/x',
                'keys' => ['p256dh' => '', 'auth' => 'not valid!'],
                'content_encoding' => 'gzip',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth', 'content_encoding']);

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_guests_cannot_manage_push_subscriptions(): void
    {
        $this->postJson(route('notifications.push.subscribe'), $this->payload())
            ->assertUnauthorized();

        $this->deleteJson(route('notifications.push.unsubscribe'), ['endpoint' => 'https://push.example.test/send/x'])
            ->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $endpoint = 'https://push.example.test/send/default'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => str_repeat('B', 20),
                'auth' => str_repeat('a', 16),
            ],
            'content_encoding' => 'aes128gcm',
        ];
    }
}
