<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Holiday;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Notice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeave;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\HomeworkService;
use App\Services\StudentLeaveService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudentPortalAcademicTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private Subject $bangla;

    private Teacher $teacher;

    private User $ayesha;

    private Student $ayeshaStudent;

    private Enrollment $ayeshaEnrollment;

    private User $bilal;

    private Student $bilalStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->buildAcademy();
    }

    public function test_the_routine_page_lists_periods_for_the_students_own_section(): void
    {
        $today = ClassRoutine::dayIndexOf(now());

        $this->routineSlot($this->sectionA, $this->bangla, $today, '09:00', 'Room 3');
        $this->routineSlot($this->sectionB, $this->subject('Chemistry'), $today, '09:00', 'Lab 1');

        $this->actingAs($this->ayesha)
            ->get(route('student.routine'))
            ->assertOk()
            ->assertSee('Bangla')
            ->assertSee('Room 3')
            ->assertDontSee('Chemistry')
            ->assertDontSee('Lab 1');
    }

    public function test_the_routine_page_explains_why_it_is_empty(): void
    {
        // A class with no published timetable still gets a usable page.
        $this->actingAs($this->ayesha)
            ->get(route('student.routine'))
            ->assertOk()
            ->assertSee('No classes scheduled');

        // An admitted student whose class and section are not recorded yet.
        [$orphanUser] = $this->makeStudent('Nadia', 'Islam', null, null);

        $this->actingAs($orphanUser)
            ->get(route('student.routine'))
            ->assertOk()
            ->assertSee('No routine yet');
    }

    public function test_the_calendar_shows_holidays_exams_and_homework_for_one_month(): void
    {
        $month = now()->startOfMonth();

        Holiday::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Founder Day',
            'start_date' => $month->copy()->addDays(3)->toDateString(),
            'end_date' => $month->copy()->addDays(4)->toDateString(),
            'type' => 'public',
        ]);

        Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Term Test One',
            'exam_type' => 'test',
            'start_date' => $month->copy()->addDays(10)->toDateString(),
            'end_date' => $month->copy()->addDays(12)->toDateString(),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'created_by' => $this->admin->id,
        ]);

        Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Hidden Mock Exam',
            'exam_type' => 'test',
            'start_date' => $month->copy()->addDays(20)->toDateString(),
            'end_date' => $month->copy()->addDays(21)->toDateString(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->homework('Geometry exercise', $this->sectionA, dueDate: $month->copy()->addDays(6));

        $this->actingAs($this->ayesha)
            ->get(route('student.calendar', ['month' => $month->format('Y-m')]))
            ->assertOk()
            ->assertSee('Founder Day')
            ->assertSee('Term Test One')
            ->assertSee('Geometry exercise')
            ->assertDontSee('Hidden Mock Exam');
    }

    public function test_another_sections_homework_is_absent_from_the_calendar(): void
    {
        $month = now()->startOfMonth();

        $this->homework('Physics lab', $this->sectionB, dueDate: $month->copy()->addDays(6));

        $this->actingAs($this->ayesha)
            ->get(route('student.calendar', ['month' => $month->format('Y-m')]))
            ->assertOk()
            ->assertDontSee('Physics lab');
    }

    public function test_a_student_sees_only_published_notices_for_their_audience(): void
    {
        $studentNotice = $this->notice('Annual sports day', 'students');
        $publicNotice = $this->notice('Gate closure notice', 'all');
        $staffNotice = $this->notice('Staff roster change', 'teachers');
        $draftNotice = $this->notice('Half term memo', 'students', draft: true);

        $this->actingAs($this->ayesha)
            ->get(route('student.notices.index'))
            ->assertOk()
            ->assertSee('Annual sports day')
            ->assertSee('Gate closure notice')
            ->assertDontSee('Staff roster change')
            ->assertDontSee('Half term memo');

        $this->actingAs($this->ayesha)->get(route('student.notices.show', $studentNotice))->assertOk();
        $this->actingAs($this->ayesha)->get(route('student.notices.show', $publicNotice))->assertOk();

        // Known ids still refuse: neither a draft nor a staff memo resolves here.
        $this->actingAs($this->ayesha)->get(route('student.notices.show', $staffNotice))->assertNotFound();
        $this->actingAs($this->ayesha)->get(route('student.notices.show', $draftNotice))->assertNotFound();
    }

    public function test_the_notice_list_can_be_searched_without_leaking_other_audiences(): void
    {
        $this->notice('Sports day venue change', 'students');
        $this->notice('Sports equipment purchase', 'guardians');

        $this->actingAs($this->ayesha)
            ->get(route('student.notices.index', ['q' => 'Sports']))
            ->assertOk()
            ->assertSee('Sports day venue change')
            ->assertDontSee('Sports equipment purchase');

        // A quoted payload is bound, so it matches nothing instead of everything.
        $this->actingAs($this->ayesha)
            ->get(route('student.notices.index', ['q' => "anything' or 1 = 1 --"]))
            ->assertOk()
            ->assertDontSee('Sports day venue change');
    }

    public function test_homework_is_limited_to_the_students_class_and_section(): void
    {
        $own = $this->homework('Algebra worksheet', $this->sectionA);
        $whole = $this->homework('Library reading log', null);
        $foreign = $this->homework('Physics lab', $this->sectionB);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Algebra worksheet')
            ->assertSee('Library reading log')
            ->assertDontSee('Physics lab');

        $this->actingAs($this->ayesha)->get(route('student.homework.show', $own))->assertOk();
        $this->actingAs($this->ayesha)->get(route('student.homework.show', $whole))->assertOk();

        // Changing the id in the URL must not open another section's work.
        $this->actingAs($this->ayesha)->get(route('student.homework.show', $foreign))->assertNotFound();
        $this->actingAs($this->bilal)->get(route('student.homework.show', $own))->assertNotFound();

        // Whole-class homework is shared, and the other student can still reach it.
        $this->actingAs($this->bilal)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Library reading log')
            ->assertDontSee('Algebra worksheet');
    }

    public function test_the_homework_page_splits_homework_and_class_work_into_tabs(): void
    {
        $this->homework('Algebra worksheet', $this->sectionA);
        $this->homework('Today: noun groups', $this->sectionA, category: Homework::CATEGORY_CLASSWORK);
        $this->homework('Section B dictation', $this->sectionB, category: Homework::CATEGORY_CLASSWORK);

        // Both kinds of published work land on the unfiltered list.
        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Algebra worksheet')
            ->assertSee('Today: noun groups')
            ->assertDontSee('Section B dictation');

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index', ['category' => 'classwork']))
            ->assertOk()
            ->assertSee('Today: noun groups')
            ->assertDontSee('Algebra worksheet');

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index', ['category' => 'homework']))
            ->assertOk()
            ->assertSee('Algebra worksheet')
            ->assertDontSee('Today: noun groups');

        // A made-up category cannot widen the list past the student's section.
        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index', ['category' => 'materials']))
            ->assertOk()
            ->assertSee('Algebra worksheet')
            ->assertDontSee('Section B dictation');
    }

    public function test_unpublished_work_stays_out_of_the_student_portal(): void
    {
        $draftHomework = $this->homework('Draft spelling list', $this->sectionA, status: Homework::DRAFT);
        $draftClasswork = $this->homework(
            'Draft class notes',
            $this->sectionA,
            category: Homework::CATEGORY_CLASSWORK,
            status: Homework::DRAFT,
        );
        $this->homework('Published reading log', $this->sectionA);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Published reading log')
            ->assertDontSee('Draft spelling list')
            ->assertDontSee('Draft class notes');

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index', ['category' => 'classwork']))
            ->assertOk()
            ->assertDontSee('Draft class notes');

        // A known id from a draft does not open, and cannot be answered.
        $this->actingAs($this->ayesha)->get(route('student.homework.show', $draftHomework))->assertNotFound();
        $this->actingAs($this->ayesha)->get(route('student.homework.show', $draftClasswork))->assertNotFound();
        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $draftHomework), ['text_answer' => 'Sneaking an answer in.'])
            ->assertNotFound();

        $this->assertDatabaseMissing('homework_submissions', ['homework_id' => $draftHomework->id]);
    }

    public function test_a_student_can_answer_class_work_and_sees_the_marks_back(): void
    {
        $classwork = $this->homework('Copy the paragraph', $this->sectionA, category: Homework::CATEGORY_CLASSWORK);

        $this->assertNull($classwork->due_date);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.show', $classwork))
            ->assertOk()
            ->assertSee('Classwork')
            ->assertSee('No deadline');

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $classwork), [
                'text_answer' => 'I copied the paragraph into my notebook.',
            ])
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()
            ->where('homework_id', $classwork->id)
            ->where('student_id', $this->ayeshaStudent->id)
            ->firstOrFail();

        $this->assertSame('submitted', $submission->status);

        app(HomeworkService::class)->review($this->admin, $submission, ['marks' => '9', 'feedback' => 'Neat copying.']);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.show', $classwork))
            ->assertOk()
            ->assertSee('Reviewed')
            ->assertSee('Neat copying.')
            ->assertSee('9');
    }

    public function test_a_student_submits_and_then_updates_an_answer_before_the_deadline(): void
    {
        Storage::fake('school_uploads');

        $homework = $this->homework('Essay on rainforest', $this->sectionA);

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => 'Rainforests receive heavy rain all year.',
            ])
            ->assertRedirect(route('student.homework.show', $homework))
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()->where('homework_id', $homework->id)->firstOrFail();

        $this->assertSame($this->ayeshaStudent->id, $submission->student_id);
        $this->assertSame($this->ayeshaEnrollment->id, $submission->enrollment_id);
        $this->assertSame('submitted', $submission->status);
        $this->assertSame('Rainforests receive heavy rain all year.', $submission->text_answer);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.submitted', 'user_id' => $this->ayesha->id]);

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => 'Rainforests receive heavy rain and hold great biodiversity.',
                'attachment' => $this->textUpload('draft.txt', 'see the attached notes'),
            ])
            ->assertSessionHasNoErrors();

        $submission->refresh();

        $this->assertSame('submitted', $submission->status);
        $this->assertNotNull($submission->resubmitted_at);
        $this->assertNotNull($submission->attachment_path);
        $this->assertSame('draft.txt', $submission->attachment_name);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.show', $homework))
            ->assertOk()
            ->assertSee('Submitted')
            ->assertSee('hold great biodiversity');

        // The student downloads their own file; nobody else can.
        $this->actingAs($this->ayesha)
            ->get(route('student.homework.submission.file', $submission))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->bilal)
            ->get(route('student.homework.submission.file', $submission))
            ->assertNotFound();
    }

    public function test_an_answer_after_the_deadline_is_stored_as_late_and_frozen(): void
    {
        $homework = $this->homework('Map work', $this->sectionA, dueDate: now()->subDay());

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), ['text_answer' => 'River systems of Bangladesh.'])
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $this->ayeshaStudent->id)
            ->firstOrFail();

        $this->assertSame('late', $submission->status);

        // A late answer is accepted once, then locked against further edits.
        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), ['text_answer' => 'Changed my mind entirely.'])
            ->assertSessionHasErrors('text_answer');

        $this->assertSame('River systems of Bangladesh.', $submission->refresh()->text_answer);
    }

    public function test_a_reviewed_submission_cannot_be_replaced_by_the_student(): void
    {
        $homework = $this->homework('Book review', $this->sectionA);

        app(HomeworkService::class)->submit(
            $this->ayeshaStudent,
            $this->ayesha,
            $homework,
            ['text_answer' => 'The story taught me about honesty.']
        );

        $submission = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $this->ayeshaStudent->id)
            ->firstOrFail();

        app(HomeworkService::class)->review($this->admin, $submission, ['marks' => '18', 'feedback' => 'Well argued.']);

        $this->assertSame('reviewed', $submission->refresh()->status);

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), ['text_answer' => 'A different answer now.'])
            ->assertSessionHasErrors('text_answer');

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.show', $homework))
            ->assertOk()
            ->assertSee('Reviewed')
            ->assertSee('Well argued.')
            ->assertSee('Teacher feedback')
            ->assertDontSee('Submit your answer');
    }

    public function test_a_student_cannot_answer_homework_for_another_section(): void
    {
        $foreign = $this->homework('Physics lab', $this->sectionB);

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $foreign), ['text_answer' => 'Trying another class.'])
            ->assertNotFound();

        $this->assertDatabaseMissing('homework_submissions', [
            'homework_id' => $foreign->id,
            'student_id' => $this->ayeshaStudent->id,
        ]);
    }

    public function test_a_homework_attachment_is_served_only_inside_its_own_class(): void
    {
        Storage::fake('school_uploads');

        $homework = $this->homework('Reading sheet', $this->sectionA);
        $homework->forceFill(['attachment_path' => 'homework/'.$this->school->id.'/sheet.txt'])->save();
        Storage::disk('school_uploads')->put($homework->attachment_path, 'worksheet');

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.attachment', $homework))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->bilal)
            ->get(route('student.homework.attachment', $homework))
            ->assertNotFound();
    }

    public function test_an_uploaded_answer_is_stored_with_the_extension_of_its_real_bytes(): void
    {
        Storage::fake('school_uploads');

        $homework = $this->homework('Coding notes', $this->sectionA);

        // The filename claims .txt and the bytes are a script, so the stored
        // extension can only ever be the one the sniffer found.
        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => 'My notes are in the file.',
                'attachment' => $this->textUpload('notes.txt', "<?php echo 'hi'; ?>"),
            ])
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $this->ayeshaStudent->id)
            ->firstOrFail();

        $this->assertStringEndsWith('.txt', $submission->attachment_path);
        $this->assertStringNotContainsString('php', $submission->attachment_path);
        $this->assertTrue(Storage::disk('school_uploads')->exists($submission->attachment_path));
    }

    public function test_an_oversized_or_unlisted_file_is_refused(): void
    {
        Storage::fake('school_uploads');

        $homework = $this->homework('Field trip notes', $this->sectionA);

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => 'Notes attached separately.',
                'attachment' => UploadedFile::fake()->create('scan.exe', 200),
            ])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, HomeworkSubmission::query()->where('homework_id', $homework->id)->count());
    }

    public function test_a_student_applies_for_leave_and_can_withdraw_it(): void
    {
        $payload = [
            'from_date' => now()->addDays(2)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'leave_type' => 'sick',
            'reason' => 'Fever and cough, the doctor advised rest.',
            'contact_phone' => '01711223344',
        ];

        $this->actingAs($this->ayesha)
            ->post(route('student.leave.store'), $payload)
            ->assertRedirect(route('student.leave.index'))
            ->assertSessionHasNoErrors();

        $leave = StudentLeave::query()->where('student_id', $this->ayeshaStudent->id)->firstOrFail();

        $this->assertSame('pending', $leave->status);
        $this->assertSame($this->session->id, $leave->academic_session_id);
        $this->assertSame(3, $leave->daysRequested());
        $this->assertDatabaseHas('audit_logs', ['event' => 'student_leave.applied']);

        // The office is told there is something to review.
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame('attendance', $this->admin->notifications()->firstOrFail()->category);

        $this->actingAs($this->ayesha)
            ->get(route('student.leave.index'))
            ->assertOk()
            ->assertSee('Fever and cough')
            ->assertSee('Awaiting decision');

        // The same dates cannot be requested twice.
        $this->actingAs($this->ayesha)
            ->post(route('student.leave.store'), $payload)
            ->assertSessionHasErrors('from_date');

        $this->assertSame(1, StudentLeave::query()->where('student_id', $this->ayeshaStudent->id)->count());

        $this->actingAs($this->ayesha)
            ->delete(route('student.leave.cancel', $leave))
            ->assertRedirect(route('student.leave.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $leave->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student_leave.cancelled']);
    }

    public function test_leave_requests_are_rejected_before_they_are_stored(): void
    {
        $this->actingAs($this->ayesha)
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(45)->toDateString(),
                'to_date' => now()->addDays(47)->toDateString(),
                'leave_type' => 'casual',
                'reason' => 'A family wedding out of district next month.',
            ])
            ->assertSessionHasErrors('from_date');

        $this->actingAs($this->ayesha)
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(2)->toDateString(),
                'to_date' => now()->addDays(1)->toDateString(),
                'leave_type' => 'casual',
                'reason' => 'A short trip with the family.',
            ])
            ->assertSessionHasErrors('to_date');

        $this->actingAs($this->ayesha)
            ->post(route('student.leave.store'), [
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
                'leave_type' => 'not-a-type',
                'reason' => 'Too short',
            ])
            ->assertSessionHasErrors(['leave_type', 'reason']);

        $this->assertSame(0, StudentLeave::query()->count());
    }

    public function test_a_student_cannot_withdraw_another_students_leave(): void
    {
        $leave = StudentLeave::query()->create([
            'student_id' => $this->ayeshaStudent->id,
            'academic_session_id' => $this->session->id,
            'from_date' => now()->addDays(2)->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'leave_type' => 'family',
            'reason' => 'A sibling is unwell at home.',
            'status' => 'pending',
            'created_by' => $this->ayesha->id,
        ]);

        $this->actingAs($this->bilal)
            ->delete(route('student.leave.cancel', $leave))
            ->assertNotFound();

        $this->assertSame('pending', $leave->refresh()->status);

        // A decided request is history and cannot be withdrawn.
        app(StudentLeaveService::class)->decide($this->admin, $leave, 'approved', ['notes' => 'Noted.']);

        $this->actingAs($this->ayesha)
            ->delete(route('student.leave.cancel', $leave))
            ->assertSessionHasErrors('leave');

        $this->assertSame('approved', $leave->refresh()->status);
    }

    public function test_the_attendance_page_renders_a_month_grid_of_the_own_register(): void
    {
        $this->markAttendance($this->ayeshaStudent, $this->ayeshaEnrollment, 'present');
        $this->markAttendance($this->bilalStudent, $this->bilalStudent->currentEnrollment(), 'absent');

        Holiday::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Warm up break',
            'start_date' => now()->startOfMonth()->addDays(1)->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(1)->toDateString(),
            'type' => 'school',
        ]);

        $this->actingAs($this->ayesha)
            ->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('My Attendance')
            ->assertSee('Daily Register')
            ->assertSee('Holiday')
            ->assertSee('1 entries');

        // The register of the other student never reaches this page.
        $this->actingAs($this->bilal)
            ->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('1 entries');
    }

    /**
     * School, two sections and one enrolled student per section.
     */
    private function buildAcademy(): void
    {
        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin');

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

        $this->sectionA = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->sectionB = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'B',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->bangla = $this->subject('Bangla');

        $this->teacher = Teacher::factory()->create();

        [$this->ayesha, $this->ayeshaStudent, $this->ayeshaEnrollment] = $this->makeStudent('Ayesha', 'Rahman', $this->sectionA, 1);
        [, $this->bilalStudent] = $this->makeStudent('Bilal', 'Ahmed', $this->sectionB, 2);
        $this->bilal = $this->bilalStudent->user;
    }

    /**
     * @return array{0: User, 1: Student, 2: ?Enrollment}
     */
    private function makeStudent(string $first, string $last, ?Section $section, ?int $roll): array
    {
        $user = $this->makeUser($this->school, 'student', [
            'email' => Str::slug($first.'-'.$last).'@example.test',
        ]);

        $student = Student::factory()->create([
            'first_name' => $first,
            'last_name' => $last,
        ]);

        $student->forceFill(['user_id' => $user->id])->save();
        $user->setRelation('student', $student);

        $enrollment = null;

        if ($section !== null && $roll !== null) {
            $enrollment = Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $section->id,
                'roll_no' => $roll,
                'enrolled_on' => '2026-01-02',
                'status' => 'active',
            ]);
        }

        return [$user, $student, $enrollment];
    }

    private function markAttendance(Student $student, Enrollment $enrollment, string $status): StudentAttendance
    {
        return StudentAttendance::query()->create([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $enrollment->section_id,
            'attendance_date' => now()->toDateString(),
            'status' => $status,
            'marked_by' => $this->admin->id,
        ]);
    }

    private function subject(string $name): Subject
    {
        return Subject::query()->create([
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 6)),
            'type' => 'theory',
            'is_active' => true,
        ]);
    }

    private function routineSlot(Section $section, Subject $subject, int $day, string $start, string $room): ClassRoutine
    {
        return ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => Carbon::parse($start)->addMinutes(40)->format('H:i'),
            'room' => $room,
        ]);
    }

    private function homework(
        string $title,
        ?Section $section,
        ?Carbon $dueDate = null,
        string $category = Homework::CATEGORY_HOMEWORK,
        string $status = Homework::PUBLISHED,
    ): Homework {
        return Homework::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section?->id,
            'subject_id' => $this->bangla->id,
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'category' => $category,
            'description' => 'Complete every question in the exercise book.',
            'assigned_date' => now()->toDateString(),
            // Class work records what happened in the lesson, so it is only
            // dated when the test asks for a deadline.
            'due_date' => $category === Homework::CATEGORY_CLASSWORK
                ? $dueDate?->toDateString()
                : ($dueDate ?? now()->addDays(3))->toDateString(),
            'status' => $status,
            'published_at' => $status === Homework::PUBLISHED ? now() : null,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_portal_lists_classwork_and_materials_for_the_enrolled_class_only(): void
    {
        Storage::fake('school_uploads');

        $notes = StudyMaterial::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->bangla->id,
            'kind' => StudyMaterial::CLASSWORK,
            'work_date' => now()->toDateString(),
            'title' => 'Today: noun groups',
            'description' => 'Underline every noun group in the passage.',
            'created_by' => $this->admin->id,
        ]);

        $paper = StudyMaterial::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => null,
            'subject_id' => $this->bangla->id,
            'title' => 'Grammar suggestion paper',
            'created_by' => $this->admin->id,
        ]);
        $paper->forceFill([
            'file_path' => 'materials/secret.pdf',
            'file_name' => 'suggestion.pdf',
        ])->save();
        Storage::disk('school_uploads')->put('materials/secret.pdf', 'questions');

        // Parallel section material must not leak into this student's list.
        $other = StudyMaterial::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => $this->bangla->id,
            'kind' => StudyMaterial::CLASSWORK,
            'title' => 'Section B only dictation',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->ayesha)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk()
            ->assertSee('Today: noun groups')
            ->assertDontSee('Section B only dictation')
            ->assertDontSee('Grammar suggestion paper');

        $this->actingAs($this->ayesha)
            ->get(route('student.materials.index'))
            ->assertOk()
            ->assertSee('Grammar suggestion paper');

        $download = $this->actingAs($this->ayesha)->get(route('student.materials.file', $paper));
        $download->assertOk();

        // A classwork entry the student is not in cannot be downloaded.
        $this->actingAs($this->bilal)
            ->get(route('student.materials.file', $notes))
            ->assertNotFound();
        $this->actingAs($this->bilal)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk()
            ->assertDontSee('Today: noun groups');
    }

    private function notice(string $title, string $audience, bool $draft = false): Notice
    {
        return Notice::query()->create([
            'title' => $title,
            'body' => 'Please read the details on the notice board.',
            'audience' => $audience,
            'published_at' => $draft ? null : now()->subHour(),
            'created_by' => $this->admin->id,
        ]);
    }

    private function textUpload(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
