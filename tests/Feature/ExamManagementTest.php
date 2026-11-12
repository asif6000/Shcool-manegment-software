<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\GradeScaleItem;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ExamService;
use App\Services\GradeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $bangla;

    private Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_exam_workspace_is_permission_gated(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $principal = $this->makeUser($school, 'principal');
        $accountant = $this->makeUser($school, 'accountant');
        $this->academicFixtures($school->id);

        $this->actingAs($admin)->get(route('exams.index'))->assertOk();
        $this->actingAs($principal)->get(route('exams.index'))->assertOk();
        $this->actingAs($admin)->get(route('exams.grade-scale.edit'))->assertOk();

        $this->actingAs($accountant)->get(route('exams.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('exams.grade-scale.edit'))->assertForbidden();
    }

    public function test_admin_can_create_an_exam_and_schedule_a_paper(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $this->actingAs($admin)
            ->get(route('exams.create'))
            ->assertOk()
            ->assertSee('First Term');

        $this->actingAs($admin)
            ->post(route('exams.store'), [
                'academic_session_id' => $this->session->id,
                'name' => 'First Term Examination',
                'exam_type' => 'term',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-10',
                'description' => 'Scheduled from the admin UI test.',
            ])
            ->assertSessionHasNoErrors();

        $exam = Exam::query()->firstOrFail();
        $this->assertSame('draft', $exam->status);
        $this->assertSame($admin->id, $exam->created_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'exam.created']);

        $this->actingAs($admin)
            ->post(route('exams.papers.store', $exam), [
                'class_id' => $this->class->id,
                'subject_id' => $this->bangla->id,
                'exam_date' => '2026-06-02',
                'start_time' => '10:00',
                'end_time' => '13:00',
                'full_marks' => 100,
                'pass_marks' => 33,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_subjects', [
            'exam_id' => $exam->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->bangla->id,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        $this->actingAs($admin)
            ->post(route('exams.papers.store', $exam), [
                'class_id' => $this->class->id,
                'subject_id' => $this->bangla->id,
                'full_marks' => 100,
                'pass_marks' => 33,
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, ExamSubject::query()->where('exam_id', $exam->id)->count());
    }

    public function test_teacher_can_enter_marks_but_cannot_manage_exams(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $student = $this->enrolledStudent(1);

        $this->assignPeriod($teacher, $this->bangla);

        // The exam list belongs to the office: a teacher reaches their papers
        // through the results panel of the dashboard, never through exams.index.
        $this->actingAs($teacher)->get(route('exams.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('exams.create'))->assertForbidden();
        $this->actingAs($teacher)->post(route('exams.store'), [
            'academic_session_id' => $this->session->id,
            'name' => 'Teacher Exam',
            'exam_type' => 'term',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ])->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('exams.marks.index', ['exam' => $exam, 'paper_id' => $paper->id]))
            ->assertOk()
            ->assertSee($student->full_name);

        $this->actingAs($teacher)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $paper->id,
                'marks' => [
                    $student->id => ['marks_obtained' => 80, 'is_absent' => 0],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exam_marks', [
            'exam_subject_id' => $paper->id,
            'student_id' => $student->id,
            'marks_obtained' => 80,
            'is_absent' => 0,
            'grade_letter' => 'A+',
            'grade_point' => 5,
            'entered_by' => $teacher->id,
        ]);

        // A subject that is not on the timetable is not reachable at all.
        $englishPaper = $this->makePaper($admin, $exam, $this->english);

        $this->actingAs($teacher)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $englishPaper->id,
                'marks' => [
                    $student->id => ['marks_obtained' => 10],
                ],
            ])
            ->assertForbidden();

        $marksPage = $this->actingAs($teacher)
            ->get(route('exams.marks.index', ['exam' => $exam]))
            ->assertOk();

        $this->assertSame([$paper->id], $marksPage->viewData('papers')->pluck('id')->all());
        $this->assertSame([$paper->id], $marksPage->viewData('classPapers')->pluck('id')->all());

        $this->assertSame(0, ExamMark::query()->where('exam_subject_id', $englishPaper->id)->count());
    }

    public function test_marks_outside_the_paper_total_are_rejected(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $student = $this->enrolledStudent(1);

        $this->actingAs($admin)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $paper->id,
                'marks' => [
                    $student->id => ['marks_obtained' => 120],
                ],
            ])
            ->assertSessionHasErrors('marks');

        $this->assertSame(0, ExamMark::query()->count());
    }

    public function test_a_student_who_is_not_enrolled_cannot_receive_marks(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $outsider = Student::factory()->create();

        $this->expectException(ValidationException::class);

        app(ExamService::class)->saveMarks($admin, $exam, $paper, [
            'marks' => [
                $outsider->id => ['marks_obtained' => 70],
            ],
        ]);
    }

    public function test_results_cannot_be_published_with_missing_marks(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $first = $this->enrolledStudent(1);
        $this->enrolledStudent(2);

        $this->saveMarks($admin, $exam, $paper, [$first->id => 80]);

        $this->actingAs($admin)
            ->from(route('exams.results.index', $exam))
            ->post(route('exams.results.publish', $exam))
            ->assertSessionHasErrors('results');

        $this->assertSame('draft', $exam->fresh()->status);
        $this->assertSame(0, StudentResult::query()->count());
    }

    public function test_publishing_snapshots_results_with_tie_aware_positions_and_locks_marks(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $bangla = $this->makePaper($admin, $exam, $this->bangla);
        $english = $this->makePaper($admin, $exam, $this->english);

        $first = $this->enrolledStudent(1);
        $second = $this->enrolledStudent(2);
        $third = $this->enrolledStudent(3);

        $this->saveMarks($teacher, $exam, $bangla, [
            $first->id => 80,
            $second->id => 80,
            $third->id => 60,
        ]);
        $this->saveMarks($teacher, $exam, $english, [
            $first->id => 90,
            $second->id => 90,
            $third->id => 60,
        ]);

        $this->actingAs($admin)
            ->post(route('exams.results.publish', $exam))
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $exam->fresh()->status);
        $this->assertNotNull($exam->fresh()->published_at);

        $results = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->get()
            ->keyBy('student_id');

        $this->assertCount(3, $results);
        $this->assertSame('published', $results[$first->id]->status);
        $this->assertSame(1, (int) $results[$first->id]->position);
        $this->assertSame(1, (int) $results[$second->id]->position);
        $this->assertSame(3, (int) $results[$third->id]->position);
        $this->assertSame('A+', $results[$first->id]->grade_letter);
        $this->assertEquals(5, (float) $results[$first->id]->gpa);

        $snapshot = $results[$first->id]->snapshot;
        $this->assertCount(2, $snapshot['subjects']);
        $this->assertSame(170.0, (float) $snapshot['total_obtained']);
        $this->assertSame('pass', $snapshot['outcome']);
        $this->assertSame($first->full_name, $snapshot['student']['name']);

        $this->assertDatabaseHas('audit_logs', ['event' => 'exam.results_published']);
        $log = AuditLog::query()->where('event', 'exam.results_published')->firstOrFail();
        $this->assertStringContainsString($exam->name, $log->description);
        $this->assertStringNotContainsString(':exam', $log->description);

        $this->actingAs($admin)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $bangla->id,
                'marks' => [
                    $first->id => ['marks_obtained' => 10],
                ],
            ])
            ->assertSessionHasErrors('exam');

        $this->assertEquals(80, (float) ExamMark::query()
            ->where('exam_subject_id', $bangla->id)
            ->where('student_id', $first->id)
            ->value('marks_obtained'));
    }

    public function test_failing_one_subject_zeroes_the_overall_gpa(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $bangla = $this->makePaper($admin, $exam, $this->bangla);
        $english = $this->makePaper($admin, $exam, $this->english);
        $student = $this->enrolledStudent(1);

        $this->saveMarks($admin, $exam, $bangla, [$student->id => 90]);
        $this->saveMarks($admin, $exam, $english, [$student->id => 20]);

        $this->actingAs($admin)
            ->post(route('exams.results.publish', $exam))
            ->assertSessionHasNoErrors();

        $result = StudentResult::query()->where('exam_id', $exam->id)->firstOrFail();
        $this->assertEquals(0, (float) $result->gpa);
        $this->assertSame('F', $result->grade_letter);
        $this->assertSame('fail', $result->snapshot['outcome']);
    }

    public function test_an_absent_student_is_recorded_as_failing(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $student = $this->enrolledStudent(1);

        $this->actingAs($admin)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $paper->id,
                'marks' => [
                    $student->id => ['is_absent' => 1],
                ],
            ])
            ->assertSessionHasNoErrors();

        $mark = ExamMark::query()->firstOrFail();
        $this->assertTrue($mark->is_absent);
        $this->assertNull($mark->marks_obtained);
        $this->assertSame('F', $mark->grade_letter);
    }

    public function test_unpublishing_restores_drafting_and_allows_mark_edits(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $student = $this->enrolledStudent(1);

        $this->assignPeriod($teacher, $this->bangla);

        $this->saveMarks($admin, $exam, $paper, [$student->id => 80]);
        $this->actingAs($admin)->post(route('exams.results.publish', $exam))->assertSessionHasNoErrors();
        $this->assertSame('published', $exam->fresh()->status);

        $this->actingAs($admin)
            ->post(route('exams.results.unpublish', $exam))
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', $exam->fresh()->status);
        $this->assertNull($exam->fresh()->published_at);
        $this->assertSame('draft', StudentResult::query()->firstOrFail()->status);

        $unpublishLog = AuditLog::query()->where('event', 'exam.results_unpublished')->firstOrFail();
        $this->assertStringContainsString($exam->name, $unpublishLog->description);
        $this->assertStringNotContainsString(':exam', $unpublishLog->description);

        $this->actingAs($teacher)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $paper->id,
                'marks' => [
                    $student->id => ['marks_obtained' => 45],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(45, (float) ExamMark::query()->firstOrFail()->marks_obtained);
    }

    public function test_report_cards_and_the_student_portal_only_expose_published_results(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $accountant = $this->makeUser($school, 'accountant');
        $this->academicFixtures($school->id);

        $studentUser = $this->makeUser($school, 'student', ['email' => 'result.student@example.test']);
        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $student = $this->enrolledStudent(1, $studentUser);
        $this->saveMarks($admin, $exam, $paper, [$student->id => 80]);

        // Without a timetable assignment the marks sheet opens with no paper at
        // all — the teacher is not denied the page, but none of it is theirs.
        $this->actingAs($teacher)
            ->get(route('exams.marks.index', ['exam' => $exam, 'paper_id' => $paper->id]))
            ->assertOk()
            ->assertDontSee($student->full_name);

        $this->assignPeriod($teacher, $this->bangla);

        $this->actingAs($teacher)
            ->get(route('exams.marks.index', ['exam' => $exam, 'paper_id' => $paper->id]))
            ->assertOk()
            ->assertSee($student->full_name);

        $this->actingAs($admin)
            ->get(route('exams.results.show', [$exam, $student]))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Bangla');

        // Reading a report card is the office's job; a teacher only fills in the
        // marks sheet for the papers on their own timetable.
        $this->actingAs($teacher)
            ->get(route('exams.results.show', [$exam, $student]))
            ->assertForbidden();

        $this->actingAs($accountant)
            ->get(route('exams.results.index', $exam))
            ->assertForbidden();

        $this->actingAs($studentUser)->get(route('student.results.index'))->assertOk();
        $this->actingAs($studentUser)
            ->get(route('student.results.show', $exam))
            ->assertNotFound();

        $this->actingAs($admin)->post(route('exams.results.publish', $exam))->assertSessionHasNoErrors();

        $this->actingAs($studentUser)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee($exam->name);

        $this->actingAs($studentUser)
            ->get(route('student.results.show', $exam))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Bangla');

        $this->actingAs($studentUser)
            ->get(route('student.results.show', ['exam' => $exam->id + 999]))
            ->assertNotFound();
    }

    public function test_exams_are_scoped_to_the_school(): void
    {
        $schoolA = $this->makeSchool();
        $adminA = $this->makeUser($schoolA, 'school_admin');
        $this->academicFixtures($schoolA->id);

        $schoolB = $this->makeSchool();
        $adminB = $this->makeUser($schoolB, 'school_admin');
        $this->useSchoolContext($schoolB->id);

        $sessionB = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $classB = SchoolClass::query()->create([
            'name' => 'Class Seven',
            'level' => 7,
            'is_active' => true,
        ]);
        $subjectB = Subject::query()->create([
            'name' => 'Science',
            'code' => 'SCI',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $examB = app(ExamService::class)->create($adminB, [
            'academic_session_id' => $sessionB->id,
            'name' => 'School B Exam',
            'exam_type' => 'term',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);
        $paperB = app(ExamService::class)->addPaper($adminB, $examB, [
            'class_id' => $classB->id,
            'subject_id' => $subjectB->id,
            'exam_date' => '2026-06-02',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        $this->useSchoolContext($schoolA->id);

        $this->actingAs($adminA)
            ->get(route('exams.show', $examB))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('exams.marks.store', $examB), [
                'paper_id' => $paperB->id,
                'marks' => [$this->enrolledStudent(1)->id => ['marks_obtained' => 60]],
            ])
            ->assertNotFound();

        $this->assertSame(0, ExamMark::query()->withoutGlobalScopes()->count());
    }

    public function test_result_export_is_permission_gated_and_formula_safe(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $accountant = $this->makeUser($school, 'accountant');
        $this->academicFixtures($school->id);

        $exam = $this->makeExam($admin);
        $paper = $this->makePaper($admin, $exam, $this->bangla);
        $student = $this->enrolledStudent(1, null, [
            'first_name' => '=SUM(A1)',
            'last_name' => 'Probe',
        ]);

        $this->saveMarks($admin, $exam, $paper, [$student->id => 80]);
        $this->actingAs($admin)->post(route('exams.results.publish', $exam))->assertSessionHasNoErrors();

        $this->actingAs($accountant)
            ->get(route('exams.results.export', $exam))
            ->assertForbidden();

        $response = $this->actingAs($admin)
            ->get(route('exams.results.export', $exam))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString(__('Position'), $csv);
        $this->assertStringContainsString("'=SUM(A1) Probe", $csv);
        $this->assertStringNotContainsString(',=SUM(A1) Probe', $csv);
    }

    public function test_grade_scale_updates_must_stay_continuous(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $this->actingAs($admin)
            ->get(route('exams.grade-scale.edit'))
            ->assertOk()
            ->assertSee('A+');

        $this->actingAs($admin)
            ->put(route('exams.grade-scale.update'), [
                'items' => [
                    ['grade_letter' => 'F', 'min_percentage' => 0, 'max_percentage' => 40, 'grade_point' => 0],
                    ['grade_letter' => 'A+', 'min_percentage' => 80, 'max_percentage' => 100, 'grade_point' => 5],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(7, GradeScaleItem::query()->count());

        $this->actingAs($admin)
            ->put(route('exams.grade-scale.update'), [
                'items' => [
                    ['grade_letter' => 'f', 'min_percentage' => 0, 'max_percentage' => 49.99, 'grade_point' => 0, 'remarks' => 'Fail'],
                    ['grade_letter' => 'a+', 'min_percentage' => 50, 'max_percentage' => 100, 'grade_point' => 5, 'remarks' => 'Outstanding'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, GradeScaleItem::query()->count());
        $this->assertDatabaseHas('grade_scale_items', [
            'grade_letter' => 'A+',
            'min_percentage' => 50,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'grade_scale.updated']);
    }

    public function test_the_overall_grade_and_pass_fail_follow_the_configured_scale(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $grades = app(GradeService::class);

        // The shipped GPA-5 scale keeps the letters the reports already show.
        $this->assertSame('A+', $grades->overallLetter(5.0));
        $this->assertSame('A-', $grades->overallLetter(3.6));
        $this->assertTrue($grades->isFailing('F'));
        $this->assertFalse($grades->isFailing('B'));

        // A school that names its bands differently gets its own letters and its
        // own fail rule; nothing in the code decides that 'F' spells failure.
        $this->actingAs($admin)
            ->put(route('exams.grade-scale.update'), [
                'items' => [
                    ['grade_letter' => 'NG', 'min_percentage' => 0, 'max_percentage' => 39.99, 'grade_point' => 0],
                    ['grade_letter' => 'PASS', 'min_percentage' => 40, 'max_percentage' => 100, 'grade_point' => 2],
                ],
            ])
            ->assertSessionHasNoErrors();

        $grades = app(GradeService::class);

        $this->assertSame('PASS', $grades->overallLetter(2.0));
        $this->assertSame('NG', $grades->overallLetter(0.0));
        $this->assertTrue($grades->isFailing('NG'));
        $this->assertFalse($grades->isFailing('PASS'));
    }

    /**
     * Put a subject on a teacher's timetable. The routine is what gives a teacher
     * the right to read the roll, enter marks and open a report card, so a test
     * that expects access has to assign the period first.
     */
    private function assignPeriod(User $teacher, Subject $subject): Teacher
    {
        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $profile->id,
            'day_of_week' => 3,
            'start_time' => '21:00',
            'end_time' => '21:40',
        ]);

        return $profile;
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

        $this->bangla = Subject::query()->create([
            'name' => 'Bangla',
            'code' => 'BAN',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->english = Subject::query()->create([
            'name' => 'English',
            'code' => 'ENG',
            'type' => 'theory',
            'is_active' => true,
        ]);
    }

    private function makeExam(User $actor, string $name = 'First Term Examination'): Exam
    {
        return app(ExamService::class)->create($actor, [
            'academic_session_id' => $this->session->id,
            'name' => $name,
            'exam_type' => 'term',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'description' => null,
        ]);
    }

    private function makePaper(User $actor, Exam $exam, Subject $subject): ExamSubject
    {
        return app(ExamService::class)->addPaper($actor, $exam, [
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'exam_date' => '2026-06-02',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
    }

    private function enrolledStudent(int $roll, ?User $user = null, array $overrides = []): Student
    {
        $student = Student::factory()->create($overrides);

        if ($user !== null) {
            $student->forceFill(['user_id' => $user->id])->save();
        }

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return $student;
    }

    /**
     * @param  array<int, int|array<string, mixed>>  $scores
     */
    private function saveMarks(User $actor, Exam $exam, ExamSubject $paper, array $scores): void
    {
        $marks = [];

        foreach ($scores as $studentId => $value) {
            $marks[$studentId] = is_array($value) ? $value : ['marks_obtained' => $value];
        }

        app(ExamService::class)->saveMarks($actor, $exam, $paper, ['marks' => $marks]);
    }
}
