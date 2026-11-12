<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\StaffLeave;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * A teacher asking the office for leave: the request is written against the
 * staff profile behind the login, and the decision comes back from the office
 * register at staff/leaves.
 */
class TeacherLeaveTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29 09:00:00');
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_teacher_applies_for_their_own_leave_and_the_office_is_told(): void
    {
        [$teacher, $colleague, $office] = $this->school();
        $type = $this->leaveType();

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-02', '2026-11-04'))
            ->assertRedirect(route('teacher.leave.index'))
            ->assertSessionHasNoErrors();

        /** @var StaffLeave $leave */
        $leave = StaffLeave::query()->sole();
        $this->assertSame($this->profileOf($teacher)->id, $leave->teacher_id);
        $this->assertNull($leave->employee_id);
        $this->assertSame('pending', $leave->status);
        $this->assertSame('3.0', (string) $leave->total_days);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'staff_leave.requested',
            'user_id' => $teacher->id,
            'auditable_type' => StaffLeave::class,
            'auditable_id' => $leave->id,
        ]);

        // The register is where the decision happens, so the office is pointed there.
        $this->actingAs($office)->get(route('staff.leaves.index'))->assertOk()->assertSee($leave->staffName());

        $this->assertGreaterThan(0, Notification::query()
            ->where('notifiable_id', $office->id)
            ->where('data->url', route('staff.leaves.index'))
            ->count());
    }

    public function test_a_posted_applicant_id_cannot_move_the_request_onto_a_colleague(): void
    {
        [$teacher, $colleague, $office] = $this->school();
        $type = $this->leaveType();

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-02', '2026-11-02') + [
                'teacher_id' => $colleague->id,
                'employee_id' => 999,
            ])
            ->assertSessionHasNoErrors();

        /** @var StaffLeave $leave */
        $leave = StaffLeave::query()->sole();
        $this->assertSame($this->profileOf($teacher)->id, $leave->teacher_id);
        $this->assertNull($leave->employee_id);

        // The colleague's own list is still empty.
        $this->assertSame(0, (int) $this->actingAs($colleague)
            ->get(route('teacher.leave.index'))
            ->viewData('rows')
            ->total());
    }

    public function test_dates_that_overlap_a_waiting_request_are_refused(): void
    {
        [$teacher] = $this->school();
        $type = $this->leaveType();

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-02', '2026-11-04'))
            ->assertSessionHasNoErrors();

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-04', '2026-11-06'))
            ->assertSessionHasErrors('start_date');

        $this->assertSame(1, StaffLeave::query()->count());
    }

    public function test_the_dates_and_the_reason_have_to_make_sense(): void
    {
        [$teacher] = $this->school();
        $type = $this->leaveType();

        // Three months ago is a correction for the office, not an application.
        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-07-01', '2026-07-03'))
            ->assertSessionHasErrors('start_date');

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-05', '2026-11-02'))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), [
                'leave_type_id' => $type->id,
                'start_date' => '2026-11-02',
                'end_date' => '2026-11-02',
                'reason' => 'At home.',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, StaffLeave::query()->count());
    }

    public function test_a_leave_type_of_another_school_cannot_be_used(): void
    {
        [$teacher] = $this->school();
        $type = $this->leaveType();

        $otherSchool = $this->makeSchool();
        $this->useSchoolContext($otherSchool->id);
        $otherType = $this->leaveType(['name' => 'Sabbatical', 'code' => 'SAB']);
        $this->useSchoolContext($type->school_id);

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($otherType, '2026-11-02', '2026-11-02'))
            ->assertSessionHasErrors('leave_type_id');

        $this->assertSame(0, StaffLeave::query()->count());
    }

    public function test_a_login_without_a_staff_profile_is_told_the_office_must_record_it(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($teacher)->get(route('teacher.leave.index'))
            ->assertOk()
            ->assertSee('not linked to a staff profile');

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($this->leaveType(), '2026-11-02', '2026-11-02'))
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, StaffLeave::query()->count());
    }

    public function test_a_teacher_sees_only_their_own_requests_and_their_own_numbers(): void
    {
        [$teacher, $colleague, $office] = $this->school();
        $type = $this->leaveType();

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-02', '2026-11-04'))
            ->assertSessionHasNoErrors();

        // A request the office recorded for the colleague on the same dates.
        $this->actingAs($office)
            ->post(route('staff.leaves.store'), $this->payload($type, '2026-11-02', '2026-11-11') + [
                'teacher_id' => $this->profileOf($colleague)->id,
            ])
            ->assertSessionHasNoErrors();

        $mine = $this->actingAs($teacher)->get(route('teacher.leave.index'))->assertOk();
        $rows = $mine->viewData('rows');
        $this->assertSame(1, (int) $rows->total());
        $this->assertSame(1, (int) $mine->viewData('pending'));
        $this->assertSame(0.0, (float) $mine->viewData('daysThisYear'));
        $this->assertSame(
            [$teacher->id],
            StaffLeave::query()->whereIn('id', $rows->pluck('id'))->pluck('teacher_id')
                ->map(fn (int $id): int => Teacher::query()->findOrFail($id)->user_id)->all(),
        );
    }

    public function test_the_office_decision_comes_back_to_the_teacher(): void
    {
        [$teacher, $colleague, $office] = $this->school();
        $type = $this->leaveType();

        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-02', '2026-11-04'))
            ->assertSessionHasNoErrors();

        /** @var StaffLeave $leave */
        $leave = StaffLeave::query()->sole();

        $this->actingAs($office)
            ->post(route('staff.leaves.reject', $leave), ['reason' => 'The exam week needs you.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($teacher)->get(route('teacher.leave.index'))
            ->assertOk()
            ->assertSee('Rejected')
            ->assertSee('The exam week needs you.');

        // Approved days are what the year total counts.
        $approved = $this->leaveType(['name' => 'Casual', 'code' => 'CS']);
        $this->actingAs($teacher)
            ->post(route('teacher.leave.store'), $this->payload($approved, '2026-12-01', '2026-12-02'))
            ->assertSessionHasNoErrors();

        /** @var StaffLeave $second */
        $second = StaffLeave::query()->where('leave_type_id', $approved->id)->sole();
        $this->actingAs($office)->post(route('staff.leaves.approve', $second))->assertSessionHasNoErrors();

        $page = $this->actingAs($teacher)->get(route('teacher.leave.index'))->assertOk();
        $this->assertSame(2.0, (float) $page->viewData('daysThisYear'));
        $this->assertSame(0, (int) $page->viewData('pending'));

        // The status filter is what the teacher uses to find the answered ones.
        $this->actingAs($teacher)->get(route('teacher.leave.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('2.0')
            ->assertDontSee('The exam week needs you.');
    }

    public function test_a_role_without_the_leave_permission_is_turned_away(): void
    {
        [$teacher] = $this->school();
        $type = $this->leaveType();

        // A student is not allowed in the staff area at all.
        $student = $this->makeUser($teacher->school, 'student');
        $this->actingAs($student)->get(route('teacher.leave.index'))->assertRedirect();

        // The accountant is a staff login, so the permission gate is what stops them.
        $accountant = $this->makeUser($teacher->school, 'accountant');
        $this->actingAs($accountant)->get(route('teacher.leave.index'))->assertForbidden();

        $this->actingAs($accountant)
            ->post(route('teacher.leave.store'), $this->payload($type, '2026-11-02', '2026-11-02'))
            ->assertForbidden();

        $this->assertSame(0, StaffLeave::query()->count());
    }

    public function test_an_unauthenticated_visit_is_sent_to_the_login_page(): void
    {
        $this->get(route('teacher.leave.index'))->assertRedirect(route('login'));
    }

    /**
     * @return array{User, User, User} teacher, colleague teacher, office admin
     */
    private function school(): array
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $teacher = $this->makeUser($school, 'teacher');
        $colleague = $this->makeUser($school, 'teacher');
        $office = $this->makeUser($school, 'school_admin');

        Teacher::factory()->create(['user_id' => $teacher->id, 'first_name' => 'Mira', 'last_name' => 'Das']);
        Teacher::factory()->create(['user_id' => $colleague->id, 'first_name' => 'Arif', 'last_name' => 'Khan']);

        return [$teacher, $colleague, $office];
    }

    private function profileOf(User $user): Teacher
    {
        return Teacher::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function leaveType(array $overrides = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'name' => 'Medical',
            'code' => 'MED',
            'max_days_per_year' => 12,
            'is_paid' => true,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function payload(LeaveType $type, string $start, string $end): array
    {
        return [
            'leave_type_id' => (string) $type->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Family function in my village, with two periods to hand over.',
        ];
    }
}
