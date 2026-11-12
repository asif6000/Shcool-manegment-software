<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\MarkSubmission;
use App\Models\Notification;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class MarkSubmissionTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private const TODAY = '2026-10-29 10:00:00';

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_teacher_hands_over_a_completed_sheet_and_the_office_is_told(): void
    {
        [$teacher, $principal] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);

        $this->assertSame(0, MarkSubmission::query()->count());

        $this->actingAs($teacher)
            ->post(route('exams.marks.submit', $exam), $this->sheetPayload($paper))
            ->assertRedirect(route('exams.marks.index', [
                'exam' => $exam->id,
                'class_id' => $this->class->id,
                'paper_id' => $paper->id,
                'section_id' => $this->section->id,
            ]))
            ->assertSessionHasNoErrors();

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();

        $this->assertSame(MarkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(3, $submission->enrolled_count);
        $this->assertSame(3, $submission->entered_count);
        $this->assertSame(0, $submission->absent_count);
        $this->assertSame(0, $submission->unmarked_count);
        $this->assertSame($teacher->id, $submission->submitted_by);
        $this->assertSame(0, $submission->revision_count);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'marks.submission.submitted',
            'auditable_type' => MarkSubmission::class,
        ]);

        // The reviewers hear about it; the teacher does not notify themselves.
        $this->assertSame(1, Notification::query()->where('notifiable_id', $principal->id)->count());
        $this->assertSame(0, Notification::query()->where('notifiable_id', $teacher->id)->count());
    }

    public function test_the_register_shows_what_happened_to_a_handed_over_sheet(): void
    {
        [$teacher, $principal] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);
        $this->handOver($teacher, $exam, $paper);

        $register = route('exams.marks.index', [
            'exam' => $exam->id,
            'class_id' => $this->class->id,
            'paper_id' => $paper->id,
            'section_id' => $this->section->id,
        ]);

        $mine = $this->actingAs($teacher)->get($register)->assertOk();
        $mine->assertSee('Awaiting review');
        // Handed over, so the only move left is another hand-over.
        $mine->assertSee('Resubmit for review');
        $mine->assertSee('correction_reason', false);

        $this->actingAs($principal)->get($register)->assertOk()->assertSee('Review queue');
    }

    public function test_an_incomplete_sheet_cannot_be_handed_over(): void    {
        [$teacher] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30]);

        $this->actingAs($teacher)
            ->post(route('exams.marks.submit', $exam), $this->sheetPayload($paper))
            ->assertSessionHasErrors('paper');

        $this->assertSame(0, MarkSubmission::query()->count());
    }

    public function test_a_teacher_cannot_submit_a_paper_that_is_not_on_their_timetable(): void
    {
        [$teacher] = $this->school();

        $this->useSchoolContext($this->class->school_id);

        $foreignClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);
        $foreign = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Fourth Term',
            'exam_type' => 'term',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-26',
            'status' => 'draft',
        ]);
        $paper = $foreign->papers()->create([
            'class_id' => $foreignClass->id,
            'subject_id' => $this->subject->id,
            'full_marks' => 50,
            'pass_marks' => 25,
        ]);

        $this->actingAs($teacher)
            ->post(route('exams.marks.submit', $foreign), $this->sheetPayload($paper))
            ->assertForbidden();

        $this->assertSame(0, MarkSubmission::query()->count());
    }

    public function test_an_unchanged_resave_leaves_a_handed_over_sheet_alone(): void
    {
        [$teacher] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $marks = [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12];
        $this->enter($teacher, $exam, $paper, $marks);
        $this->handOver($teacher, $exam, $paper);

        // Posting the same scores again is what the browser does when nothing
        // was edited, so it must not reopen the sheet or ask for a reason.
        $this->enter($teacher, $exam, $paper, $marks);

        $submission = MarkSubmission::query()->sole();
        $this->assertSame(MarkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(0, $submission->revision_count);
    }

    public function test_editing_a_handed_over_sheet_needs_a_reason_and_goes_back_on_the_queue(): void
    {
        [$teacher] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);
        $this->handOver($teacher, $exam, $paper);

        $this->actingAs($teacher)
            ->post(route('exams.marks.store', $exam), $this->marksPayload($paper, [
                $students[0]->id => 45,
                $students[1]->id => 31,
            ]))
            ->assertSessionHasErrors('correction_reason');

        $this->assertSame(0, (int) ExamMark::query()->where('student_id', $students[1]->id)->where('marks_obtained', 31)->count());
        $this->assertSame(30.0, (float) ExamMark::query()->where('student_id', $students[1]->id)->value('marks_obtained'));

        $this->actingAs($teacher)
            ->post(route('exams.marks.store', $exam), $this->marksPayload($paper, [
                $students[0]->id => 45,
                $students[1]->id => 31,
                $students[2]->id => 12,
            ], 'Transcript correction from the second paper.'))
            ->assertSessionHasNoErrors();

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();

        $this->assertSame(1, $submission->revision_count);
        $this->assertSame(MarkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame('Transcript correction from the second paper.', $submission->last_correction_reason);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $teacher->id,
            'event' => 'marks.submission.corrected',
        ]);
    }

    public function test_the_office_approves_a_sheet_and_the_teacher_is_told(): void
    {
        [$teacher, $principal] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);
        $this->handOver($teacher, $exam, $paper);

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();

        $this->actingAs($teacher)
            ->post(route('marks.submissions.decide', [$submission, 'approve']))
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('marks.submissions.decide', [$submission, 'approve']), ['note' => 'Matches the answer key.'])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame(MarkSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($principal->id, $submission->reviewed_by);
        $this->assertSame('Matches the answer key.', $submission->review_note);

        $this->assertSame(1, Notification::query()->where('notifiable_id', $teacher->id)->count());

        // A decided sheet cannot be decided twice.
        $this->actingAs($principal)
            ->post(route('marks.submissions.decide', [$submission, 'approve']))
            ->assertSessionHasErrors('submission');
    }

    public function test_a_sheet_sent_back_needs_a_note_and_returns_to_the_teacher(): void
    {
        [$teacher, $principal] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);
        $this->handOver($teacher, $exam, $paper);

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();

        $this->actingAs($principal)
            ->post(route('marks.submissions.decide', [$submission, 'reject']), ['note' => 'fix'])
            ->assertSessionHasErrors('note');

        $this->actingAs($principal)
            ->post(route('marks.submissions.decide', [$submission, 'reject']), ['note' => 'Roll 2 is short by ten marks.'])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame(MarkSubmission::STATUS_REJECTED, $submission->status);
        $this->assertSame('Roll 2 is short by ten marks.', $submission->review_note);

        // A rejected sheet is already the teacher's to fix, so editing it needs
        // no reason and leaves the decision where the office put it.
        $this->enter($teacher, $exam, $paper, [$students[1]->id => 40]);

        $submission->refresh();
        $this->assertSame(MarkSubmission::STATUS_REJECTED, $submission->status);
        $this->assertSame(3, $submission->entered_count);

        $this->handOver($teacher, $exam, $paper);

        $submission->refresh();
        $this->assertSame(MarkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertNull($submission->review_note);
        $this->assertSame(1, $submission->revision_count);
    }

    public function test_an_approved_sheet_the_teacher_moves_goes_back_to_the_queue_with_the_drift(): void
    {
        [$teacher, $principal] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 'absent']);
        $this->handOver($teacher, $exam, $paper);

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();
        $this->assertSame(1, $submission->absent_count);
        $this->actingAs($principal)->post(route('marks.submissions.decide', [$submission, 'approve']))->assertRedirect();

        $before = Notification::query()->where('notifiable_id', $principal->id)->count();

        // Roll 3 sat the paper, it was absent: the absent count has to move.
        $this->actingAs($teacher)
            ->post(route('exams.marks.store', $exam), $this->marksPayload($paper, [
                $students[0]->id => 45,
                $students[1]->id => 30,
                $students[2]->id => 12,
            ], 'Roll 3 was present after all.'))
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(MarkSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(0, $submission->absent_count);
        $this->assertNull($submission->reviewed_by);
        $this->assertNull($submission->reviewed_at);
        $this->assertGreaterThan($before, Notification::query()->where('notifiable_id', $principal->id)->count());
    }

    public function test_a_teacher_sees_only_their_own_submissions_and_the_office_sees_every_one(): void
    {
        [$teacher, , $colleague] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);

        $this->useSchoolContext($this->class->school_id);
        $otherClass = SchoolClass::query()->create(['name' => 'Class Seven', 'level' => 7, 'is_active' => true]);
        $otherSection = Section::query()->create([
            'class_id' => $otherClass->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $this->routineFor($colleague, $otherClass, $otherSection);
        $otherPaper = $exam->papers()->create([
            'class_id' => $otherClass->id,
            'subject_id' => $this->subject->id,
            'full_marks' => 50,
            'pass_marks' => 25,
        ]);
        $outsider = Student::factory()->create();
        Enrollment::query()->create([
            'student_id' => $outsider->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
        $this->enter($colleague, $exam, $otherPaper, [$outsider->id => 41]);
        $this->handOver($colleague, $exam, $otherPaper, $otherSection->id);

        $this->handOver($teacher, $exam, $paper);

        $mine = $this->actingAs($teacher)->get(route('marks.submissions.index'))->assertOk();
        $this->assertCount(1, $mine->viewData('submissions'));
        $this->assertFalse($mine->viewData('reviews'));

        $every = $this->actingAs($this->makeUser(School::query()->findOrFail($this->class->school_id), 'school_admin'))
            ->get(route('marks.submissions.index'))
            ->assertOk();
        $this->assertCount(2, $every->viewData('submissions'));
        $this->assertTrue($every->viewData('reviews'));
    }

    public function test_a_submission_from_another_school_cannot_be_reached_or_decided(): void
    {
        [$teacher] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);
        $this->handOver($teacher, $exam, $paper);

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();

        $rivalSchool = $this->makeSchool();
        $rival = $this->makeUser($rivalSchool, 'school_admin');

        $page = $this->actingAs($rival)->get(route('marks.submissions.index'))->assertOk();
        $page->assertSee('Nothing here yet');

        $this->actingAs($rival)
            ->post(route('marks.submissions.decide', [$submission, 'approve']))
            ->assertNotFound();
    }

    public function test_a_second_submission_for_the_same_sheet_updates_the_row_instead_of_duplicating_it(): void
    {
        [$teacher, , $colleague] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);
        $this->handOver($teacher, $exam, $paper);

        // The colleague teaches the same subject in the same section, so the
        // sheet is one record and the second hand-over simply re-files it.
        $this->handOver($colleague, $exam, $paper);

        /** @var MarkSubmission $submission */
        $submission = MarkSubmission::query()->sole();
        $this->assertSame($colleague->id, $submission->submitted_by);
        $this->assertSame(1, $submission->revision_count);
    }

    public function test_the_dashboards_count_the_work_the_workflow_created(): void
    {
        [$teacher, $principal] = $this->school();
        [$exam, $paper] = $this->exam();

        $students = $this->enrolThree();
        $this->enter($teacher, $exam, $paper, [$students[0]->id => 45, $students[1]->id => 30, $students[2]->id => 12]);

        // Entered in full but not handed over: the teacher owes a submission.
        $owed = $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $owed->viewData('marks')['to_submit']);

        $this->handOver($teacher, $exam, $paper);

        $done = $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $done->viewData('marks')['to_submit']);

        $office = $this->actingAs($principal)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $office->viewData('marks_approvals'));
        $office->assertSee('awaiting review');
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
        // takes the next one and still teaches the same paper.
        $this->routineFor($colleague, $this->class, $this->section, '10:00', '10:45');

        return [$teacherUser, $principal, $colleague];
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

        $this->subject = Subject::query()->firstOrCreate(
            ['name' => 'Mathematics'],
            ['type' => 'theory', 'is_active' => true],
        );
    }

    /**
     * @return array{0: Exam, 1: ExamSubject}
     */
    private function exam(): array
    {
        $this->useSchoolContext($this->class->school_id);

        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Third Term',
            'exam_type' => 'term',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-26',
            'status' => 'draft',
        ]);

        $paper = $exam->papers()->create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'full_marks' => 50,
            'pass_marks' => 25,
            'exam_date' => '2026-10-22',
        ]);

        return [$exam, $paper];
    }

    private function routineFor(User $user, SchoolClass $class, Section $section, string $start = '09:00', string $end = '09:45'): void
    {
        $profile = Teacher::query()->where('user_id', $user->id)->first()
            ?? Teacher::factory()->create(['user_id' => $user->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $this->subject->id,
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
     * @param  array<int, int|string>  $marks  student id => marks obtained, or 'absent'
     * @return array<string, mixed>
     */
    private function marksPayload(ExamSubject $paper, array $marks, ?string $reason = null): array
    {
        return array_filter([
            'paper_id' => $paper->id,
            'section_id' => $this->section->id,
            'marks' => collect($marks)
                ->map(fn (int|string $value): array => $value === 'absent'
                    ? ['marks_obtained' => null, 'is_absent' => true]
                    : ['marks_obtained' => $value, 'is_absent' => false])
                ->all(),
            'correction_reason' => $reason,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<int, int|string>  $marks
     */
    private function enter(User $actor, Exam $exam, ExamSubject $paper, array $marks): void
    {
        $this->actingAs($actor)
            ->post(route('exams.marks.store', $exam), $this->marksPayload($paper, $marks))
            ->assertSessionHasNoErrors();
    }

    private function handOver(User $actor, Exam $exam, ExamSubject $paper, ?int $sectionId = null): void
    {
        $this->actingAs($actor)
            ->post(route('exams.marks.submit', $exam), [
                'paper_id' => $paper->id,
                'section_id' => $sectionId ?? $this->section->id,
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetPayload(ExamSubject $paper): array
    {
        return [
            'paper_id' => $paper->id,
            'section_id' => $this->section->id,
        ];
    }
}
