<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class TargetedNotificationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);
        $this->admin = $this->makeUser($this->school, 'school_admin');
    }

    public function test_only_an_authorized_administrator_can_open_the_compose_screen(): void
    {
        $teacher = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $teacher->id]);

        $this->actingAs($this->admin)
            ->get(route('notifications.compose'))
            ->assertOk()
            ->assertSee('Send a targeted notification');

        $this->actingAs($teacher)
            ->get(route('notifications.compose'))
            ->assertForbidden();
    }

    public function test_a_targeted_message_reaches_active_students_once_and_never_uses_the_message_on_the_lock_screen(): void
    {
        [, $activeStudent] = $this->studentAccount();
        [, $inactiveStudent] = $this->studentAccount(['is_active' => false]);
        $teacher = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $teacher->id]);

        $campaign = (string) Str::uuid();
        $payload = [
            'campaign_id' => $campaign,
            'audience' => 'students',
            'recipient_scope' => 'all',
            'title' => 'Winter uniform from Sunday',
            'message' => 'Please send the winter uniform with your child from Sunday.',
        ];

        $this->actingAs($this->admin)
            ->post(route('notifications.compose.store'), $payload)
            ->assertRedirect(route('notifications.compose'))
            ->assertSessionHasNoErrors();

        $notification = $activeStudent->notifications()->firstOrFail();

        $this->assertSame('general', $notification->category);
        $this->assertSame(route('notifications.index'), $notification->data['url']);
        $this->assertSame('Winter uniform from Sunday', $notification->data['title']);
        $this->assertStringNotContainsString('Winter uniform', (string) $notification->data['push_title']);
        $this->assertStringNotContainsString('winter uniform', (string) $notification->data['push_body']);
        $this->assertSame('targeted:'.$campaign.':'.$activeStudent->id, $notification->dedupe_key);

        $this->assertSame(0, $inactiveStudent->notifications()->count());
        $this->assertSame(0, $teacher->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());

        $this->actingAs($this->admin)
            ->post(route('notifications.compose.store'), $payload)
            ->assertRedirect(route('notifications.compose'));

        $this->assertSame(1, $activeStudent->notifications()->count());
    }

    public function test_selected_teachers_are_the_only_people_notified_in_a_teacher_campaign(): void
    {
        $first = $this->makeUser($this->school, 'teacher');
        $second = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $first->id]);
        Teacher::factory()->create(['user_id' => $second->id]);
        [, $student] = $this->studentAccount();

        $this->actingAs($this->admin)
            ->post(route('notifications.compose.store'), [
                'campaign_id' => (string) Str::uuid(),
                'audience' => 'teachers',
                'recipient_scope' => 'selected',
                'recipient_ids' => [$first->id, $student->id],
                'title' => 'Department meeting',
                'message' => 'The department meeting starts at 2 PM in the staff room.',
            ])
            ->assertRedirect(route('notifications.compose'));

        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(0, $second->notifications()->count());
        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_staff_broadcasts_include_the_authorized_workspace_roles_only(): void
    {
        $principal = $this->makeUser($this->school, 'principal');
        $accountant = $this->makeUser($this->school, 'accountant');
        $teacher = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $teacher->id]);
        [, $student] = $this->studentAccount();

        $this->actingAs($this->admin)
            ->post(route('notifications.compose.store'), [
                'campaign_id' => (string) Str::uuid(),
                'audience' => 'staff',
                'recipient_scope' => 'all',
                'title' => 'Staff meeting',
                'message' => 'The staff meeting has moved to Thursday.',
            ])
            ->assertRedirect(route('notifications.compose'));

        $this->assertSame(1, $principal->notifications()->count());
        $this->assertSame(1, $accountant->notifications()->count());
        $this->assertSame(1, $teacher->notifications()->count());
        $this->assertSame(0, $student->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_a_recipient_from_another_school_cannot_be_selected(): void
    {
        $otherSchool = $this->makeSchool();
        $outsider = $this->makeUser($otherSchool, 'teacher');
        Teacher::factory()->create(['user_id' => $outsider->id]);

        $this->actingAs($this->admin)
            ->post(route('notifications.compose.store'), [
                'campaign_id' => (string) Str::uuid(),
                'audience' => 'teachers',
                'recipient_scope' => 'selected',
                'recipient_ids' => [$outsider->id],
                'title' => 'Private message',
                'message' => 'This should never reach another school.',
            ])
            ->assertSessionHasErrors('recipient_ids.0');

        $this->assertSame(0, $outsider->notifications()->count());
    }

    /**
     * @return array{0: Student, 1: User}
     */
    private function studentAccount(array $overrides = []): array
    {
        $student = Student::factory()->create();
        $user = $this->makeUser($this->school, 'student', $overrides);

        $student->forceFill(['user_id' => $user->id])->save();

        return [$student, $user];
    }
}
