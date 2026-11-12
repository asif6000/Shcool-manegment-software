<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\SchoolEventNotification;
use App\Services\NotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class NotificationWebTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    public function test_a_staff_member_sees_only_their_own_notifications(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');

        $this->notifyUser($admin, ['title' => 'Fee reminder for you']);
        $this->notifyUser($teacher, ['title' => 'Teacher-only notice']);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Fee reminder for you')
            ->assertDontSee('Teacher-only notice')
            ->assertSee('Mark all as read');
    }

    public function test_a_student_opens_the_inbox_inside_the_student_portal_layout(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser($school, 'student');
        $this->notifyUser($student, ['title' => 'Result available']);

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Result available')
            ->assertSee('class="student-shell', false)
            ->assertDontSee('app-sidebar', false)
            ->assertDontSee('data-sidebar-toggle', false);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $notification = $this->notifyUser($teacher, ['title' => 'Private']);

        $this->actingAs($admin)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marking_read_and_marking_all_read_only_affects_the_authenticated_user(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');

        $first = $this->notifyUser($admin);
        $second = $this->notifyUser($admin);
        $other = $this->notifyUser($teacher);

        $this->actingAs($admin)
            ->post(route('notifications.read', $first->id))
            ->assertRedirect();

        $this->assertNotNull($first->fresh()->read_at);

        $this->actingAs($admin)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_the_feed_returns_the_latest_ten_items_and_the_unread_count(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        for ($i = 1; $i <= 12; $i++) {
            $this->notifyUser($admin, ['title' => 'Notification '.$i]);
        }

        $this->actingAs($admin)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 12)
            ->assertJsonCount(10, 'items')
            ->assertJsonPath('items.0.title', 'Notification 12');
    }

    public function test_marking_read_from_the_dropdown_returns_the_new_unread_count(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $notification = $this->notifyUser($admin);

        $this->actingAs($admin)
            ->postJson(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_web_push_preferences_are_saved_per_category(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->put(route('notifications.preferences.update'), [
                'preferences' => [
                    'attendance' => ['webpush' => '1'],
                    'fees' => ['webpush' => '0'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $admin->id,
            'category' => 'attendance',
            'channel' => 'webpush',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $admin->id,
            'category' => 'fees',
            'channel' => 'webpush',
            'enabled' => false,
        ]);

        $matrix = app(NotificationService::class)->preferencesFor($admin->fresh());
        $this->assertFalse($matrix['fees']['webpush']);
        $this->assertTrue($matrix['exams']['webpush']);
    }

    private function notifyUser(User $user, array $overrides = []): Notification
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
}
