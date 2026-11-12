<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class AttendanceSubmissionTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private const TODAY = '2026-10-29 10:00:00';

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_teacher_hands_over_a_completed_register_and_the_office_is_told(): void
    {
        [$teacher, $principal] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'absent', $students[2]->id => 'late']);

        $this->assertSame(0, AttendanceSubmission::query()->count());

        $this->actingAs($teacher)
            ->post(route('attendance.submissions.store'), $this->registerPayload())
            ->assertRedirect(route('attendance.students.index', [
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'date' => '2026-10-28',
            ]))
            ->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();

        $this->assertSame(AttendanceSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(3, $submission->student_count);
        $this->assertSame(1, $submission->present_count);
        $this->assertSame(1, $submission->absent_count);
        $this->assertSame(1, $submission->late_count);
        $this->assertSame(0, $submission->unmarked_count);
        $this->assertSame($teacher->id, $submission->submitted_by);
        $this->assertSame(0, $submission->revision_count);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'attendance.submission.submitted',
            'auditable_type' => AttendanceSubmission::class,
        ]);

        // The reviewers hear about it; the teacher does not notify themselves.
        $this->assertSame(1, Notification::query()->where('notifiable_id', $principal->id)->count());
        $this->assertSame(0, Notification::query()->where('notifiable_id', $teacher->id)->count());
    }

    public function test_an_incomplete_register_cannot_be_handed_over(): void
    {
        [$teacher] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'absent']);

        $this->actingAs($teacher)
            ->post(route('attendance.submissions.store'), $this->registerPayload())
            ->assertSessionHasErrors('attendance_date');

        $this->assertSame(0, AttendanceSubmission::query()->count());
    }

    public function test_a_teacher_cannot_submit_a_class_that_is_not_on_their_timetable(): void
    {
        [$teacher] = $this->school();

        $this->useSchoolContext($this->class->school_id);

        $foreignClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        $foreignSection = Section::query()->create([
            'class_id' => $foreignClass->id,
            'name' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $outsider = Student::factory()->create();
        Enrollment::query()->create([
            'student_id' => $outsider->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $foreignClass->id,
            'section_id' => $foreignSection->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->post(route('attendance.submissions.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $foreignClass->id,
                'section_id' => $foreignSection->id,
                'attendance_date' => '2026-10-28',
            ])
            ->assertForbidden();

        $this->assertSame(0, AttendanceSubmission::query()->count());
    }

    public function test_editing_a_handed_over_register_needs_a_reason_and_goes_back_on_the_queue(): void
    {
        [$teacher] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        // Same numbers, no reason: nothing actually changed, so the save passes.
        $this->actingAs($teacher)->post(route('attendance.students.store'), $this->markPayload([
            $students[0]->id => 'present',
            $students[1]->id => 'present',
            $students[2]->id => 'present',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, (int) AttendanceSubmission::query()->sole()->revision_count);

        $this->actingAs($teacher)->post(route('attendance.students.store'), $this->markPayload([
            $students[0]->id => 'absent',
            $students[1]->id => 'present',
            $students[2]->id => 'present',
        ]))->assertSessionHasErrors('correction_reason');

        $this->assertSame(3, StudentAttendance::query()->where('status', 'present')->count());

        $this->actingAs($teacher)->post(route('attendance.students.store'), $this->markPayload([
            $students[0]->id => 'absent',
            $students[1]->id => 'present',
            $students[2]->id => 'present',
        ], 'Child was sent home ill'))->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();

        $this->assertSame(AttendanceSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(1, $submission->revision_count);
        $this->assertSame(2, $submission->present_count);
        $this->assertSame(1, $submission->absent_count);
        $this->assertSame('Child was sent home ill', $submission->last_correction_reason);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'attendance.submission.corrected',
        ]);
    }

    public function test_the_office_approves_a_register_and_the_teacher_is_told(): void
    {
        [$teacher, $principal] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();

        $this->actingAs($teacher)
            ->post(route('attendance.submissions.decide', [$submission, 'approve']))
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('attendance.submissions.decide', [$submission, 'approve']), ['note' => 'Looks right'])
            ->assertSessionHasNoErrors();

        $submission->refresh();

        $this->assertSame(AttendanceSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($principal->id, $submission->reviewed_by);
        $this->assertSame('Looks right', $submission->review_note);
        $this->assertSame(1, Notification::query()->where('notifiable_id', $teacher->id)->count());

        // A decided day cannot be decided twice.
        $this->actingAs($principal)
            ->post(route('attendance.submissions.decide', [$submission, 'approve']))
            ->assertSessionHasErrors('submission');
    }

    public function test_a_register_sent_back_needs_a_note_and_returns_to_the_teacher(): void
    {
        [$teacher, $principal] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();

        $this->actingAs($principal)
            ->post(route('attendance.submissions.decide', [$submission, 'reject']), ['note' => 'fix'])
            ->assertSessionHasErrors('note');

        $this->actingAs($principal)
            ->post(route('attendance.submissions.decide', [$submission, 'reject']), [
                'note' => 'Roll 3 was in the infirmary, not absent.',
            ])
            ->assertSessionHasNoErrors();

        $submission->refresh();

        $this->assertSame(AttendanceSubmission::STATUS_REJECTED, $submission->status);
        $this->assertSame('Roll 3 was in the infirmary, not absent.', $submission->review_note);

        // A sent-back day belongs to the teacher again: they may fix it without a
        // reason, and it only rejoins the queue when they hand it back over.
        $this->actingAs($teacher)->post(route('attendance.students.store'), $this->markPayload([
            $students[0]->id => 'present',
            $students[1]->id => 'present',
            $students[2]->id => 'late',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(AttendanceSubmission::STATUS_REJECTED, $submission->refresh()->status);
        $this->assertSame(3, $submission->present_count);

        $this->actingAs($teacher)
            ->post(route('attendance.submissions.store'), $this->registerPayload())
            ->assertSessionHasNoErrors();

        $submission->refresh();

        $this->assertSame(AttendanceSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(2, $submission->present_count);
        $this->assertSame(1, $submission->late_count);
        $this->assertNull($submission->review_note);
        $this->assertSame(1, $submission->revision_count);
    }

    public function test_an_approved_day_the_teacher_moves_goes_back_to_the_queue_with_the_drift(): void
    {
        [$teacher, $principal] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();
        $this->actingAs($principal)->post(route('attendance.submissions.decide', [$submission, 'approve']))->assertSessionHasNoErrors();
        $this->assertSame(AttendanceSubmission::STATUS_APPROVED, $submission->refresh()->status);

        $before = Notification::query()->where('notifiable_id', $principal->id)->count();

        $this->actingAs($teacher)->post(route('attendance.students.store'), $this->markPayload([
            $students[0]->id => 'absent',
            $students[1]->id => 'present',
            $students[2]->id => 'present',
        ], 'Attendance was taken from the wrong register'))->assertSessionHasNoErrors();

        $submission->refresh();

        $this->assertSame(AttendanceSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(2, $submission->present_count);
        $this->assertSame(1, $submission->absent_count);
        $this->assertNull($submission->reviewed_by);
        $this->assertGreaterThan($before, Notification::query()->where('notifiable_id', $principal->id)->count());
    }

    public function test_a_teacher_sees_only_their_own_submissions_and_the_office_sees_every_one(): void
    {
        [$teacher, $principal, $colleague] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        $otherClass = SchoolClass::query()->create(['name' => 'Class Ten', 'level' => 10, 'is_active' => true]);
        $otherSection = Section::query()->create([
            'class_id' => $otherClass->id,
            'name' => 'B',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $this->routineFor($colleague, $otherClass, $otherSection);
        $foreign = $this->enrol($otherClass, $otherSection, Student::factory()->create(), 1);

        StudentAttendance::query()->create([
            'school_id' => $this->class->school_id,
            'student_id' => $foreign->student_id,
            'enrollment_id' => $foreign->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'attendance_date' => '2026-10-28',
            'status' => 'present',
            'source' => 'manual',
            'marked_by' => $colleague->id,
        ]);

        $this->actingAs($colleague)->post(route('attendance.submissions.store'), [
            'academic_session_id' => $this->session->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'attendance_date' => '2026-10-28',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, AttendanceSubmission::query()->count());

        $mine = $this->actingAs($teacher)->get(route('attendance.submissions.index'))->assertOk();
        $this->assertCount(1, $mine->viewData('submissions'));
        $this->assertFalse($mine->viewData('reviews'));

        $all = $this->actingAs($principal)->get(route('attendance.submissions.index'))->assertOk();
        $this->assertCount(2, $all->viewData('submissions'));
        $this->assertTrue($all->viewData('reviews'));
    }

    public function test_a_submission_from_another_school_cannot_be_reached_or_decided(): void
    {
        [$teacher, $principal] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();

        $rival = $this->makeSchool(['name' => 'Rival Academy']);
        $this->useSchoolContext($rival->id);
        $rivalAdmin = $this->makeUser($rival, 'school_admin');
        $this->useSchoolContext($this->class->school_id);

        $this->actingAs($rivalAdmin)
            ->get(route('attendance.submissions.index'))
            ->assertOk()
            ->assertSee('Nothing here yet');

        $this->actingAs($rivalAdmin)
            ->post(route('attendance.submissions.decide', [$submission, 'approve']))
            ->assertNotFound();

        $this->assertSame(AttendanceSubmission::STATUS_SUBMITTED, $submission->refresh()->status);
        $this->assertNotNull($principal);
    }

    public function test_a_second_submission_for_the_same_register_updates_the_row_instead_of_duplicating_it(): void
    {
        [$teacher] = $this->school();

        $students = $this->enrolThree();
        $this->mark($teacher, [$students[0]->id => 'present', $students[1]->id => 'present', $students[2]->id => 'present']);

        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();
        $this->actingAs($teacher)->post(route('attendance.submissions.store'), $this->registerPayload())->assertSessionHasNoErrors();

        /** @var AttendanceSubmission $submission */
        $submission = AttendanceSubmission::query()->sole();

        $this->assertSame(1, AttendanceSubmission::query()->count());
        $this->assertSame(1, $submission->revision_count);
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function school(): array
    {
        $school = $this->makeSchool();
        $teacherUser = $this->makeUser($school, 'teacher');
        $principal = $this->makeUser($school, 'principal');
        $colleague = $this->makeUser($school, 'teacher');

        $this->academicFixtures($school->id);

        $this->routineFor($teacherUser, $this->class, $this->section);
        // A section cannot hold two periods in the same slot, so the colleague
        // takes the next one and still teaches the same register.
        $this->routineFor($colleague, $this->class, $this->section, '10:00', '10:45');

        return [$teacherUser, $principal, $colleague];
    }

    public function test_the_dashboards_count_the_work_the_workflow_created(): void
    {
        [$teacher, $principal] = $this->school();

        $students = $this->enrolThree();
        $today = $this->markPayload([
            $students[0]->id => 'present',
            $students[1]->id => 'present',
            $students[2]->id => 'present',
        ]);
        $today['attendance_date'] = Carbon::now()->toDateString();

        $this->actingAs($teacher)->post(route('attendance.students.store'), $today)->assertSessionHasNoErrors();

        // Marked but not handed over: the teacher owes a submission.
        $owed = $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $owed->viewData('attendance')['to_submit']);

        $this->actingAs($teacher)->post(route('attendance.submissions.store'), [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => Carbon::now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $done = $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $done->viewData('attendance')['to_submit']);

        $office = $this->actingAs($principal)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $office->viewData('attendance_approvals'));
        $office->assertSee('awaiting review');
    }

    private function academicFixtures(int $schoolId): void
    {
        $this->useSchoolContext($schoolId);

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create([
            'name' => 'Class Six',
            'level' => 6,
            'is_active' => true,
        ]);

        $this->section = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
    }

    private function routineFor(User $user, SchoolClass $class, Section $section, string $start = '09:00', string $end = '09:45'): void
    {
        $profile = Teacher::query()->where('user_id', $user->id)->first()
            ?? Teacher::factory()->create(['user_id' => $user->id]);

        $subject = Subject::query()->firstOrCreate(
            ['name' => 'Mathematics'],
            ['type' => 'theory', 'is_active' => true],
        );

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $profile->id,
            'day_of_week' => 2,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * @return array<int, Student>
     */
    private function enrolThree(): array
    {
        return Student::factory()->count(3)->create()->values()
            ->map(fn (Student $student, int $index): Student => $this->enrol($this->class, $this->section, $student, $index + 1)->student)
            ->all();
    }

    private function enrol(SchoolClass $class, Section $section, Student $student, int $roll): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array<string, mixed>
     */
    private function markPayload(array $statuses, ?string $reason = null): array
    {
        return array_filter([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-10-28',
            'statuses' => $statuses,
            'correction_reason' => $reason,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function mark(User $actor, array $statuses): void
    {
        $this->actingAs($actor)
            ->post(route('attendance.students.store'), $this->markPayload($statuses))
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function registerPayload(): array
    {
        return [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-10-28',
        ];
    }
}
