<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The staff side of the portal modules: whatever the office enters here has to
 * arrive in the student workspace, and nothing may cross a role boundary.
 */
class AcademicOperationsTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $principal;

    private User $teacherUser;

    private User $accountant;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private Subject $bangla;

    private Teacher $teacher;

    private User $ayesha;

    private Student $ayeshaStudent;

    private User $bilal;

    private Student $bilalStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->buildAcademy();
    }

    public function test_a_viewing_role_cannot_edit_the_grid(): void
    {
        // Teachers may read the routine but not rewrite it.
        $this->actingAs($this->teacherUser)
            ->get(route('timetable.index'))
            ->assertOk();

        $this->actingAs($this->teacherUser)
            ->post(route('timetable.store'), $this->slotPayload())
            ->assertForbidden();

        // The bursary has no business in the timetable at all.
        $this->actingAs($this->accountant)
            ->get(route('timetable.index'))
            ->assertForbidden();

        $this->assertSame(0, ClassRoutine::query()->count());
    }

    public function test_a_student_is_sent_back_to_their_own_workspace(): void
    {
        $this->actingAs($this->ayesha)
            ->get(route('timetable.index'))
            ->assertRedirect(route('student.dashboard'));

        $this->actingAs($this->ayesha)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Cheat attempt', $this->sectionA))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_the_office_builds_a_week_and_the_student_sees_it(): void
    {
        $day = ClassRoutine::dayIndexOf(now());

        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $this->slotPayload([
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '09:40',
                'room' => 'Room 3',
            ]))
            ->assertRedirect(route('timetable.index', [
                'session' => $this->session->id,
                'class' => $this->class->id,
                'section' => $this->sectionA->id,
            ]))
            ->assertSessionHasNoErrors();

        $slot = ClassRoutine::query()->firstOrFail();

        $this->assertSame($this->sectionA->id, $slot->section_id);
        $this->assertSame($this->bangla->id, $slot->subject_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'timetable.slot.created', 'user_id' => $this->admin->id]);

        // Only the school administrator assigns subjects; the principal cannot.
        $this->actingAs($this->principal)
            ->post(route('timetable.store'), $this->slotPayload([
                'section_id' => $this->sectionB->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '09:40',
            ]))
            ->assertForbidden();

        $this->assertSame(1, ClassRoutine::query()->count());

        // The same afternoon slot in the other section is a separate class.
        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $this->slotPayload([
                'section_id' => $this->sectionB->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '09:40',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClassRoutine::query()->count());

        // Module P: the period entered by staff is now on the student screen.
        $this->actingAs($this->ayesha)
            ->get(route('student.routine'))
            ->assertOk()
            ->assertSee('Bangla')
            ->assertSee('Room 3');

        // The grid lists what was saved, with the period count.
        $this->actingAs($this->admin)
            ->get(route('timetable.index', [
                'session' => $this->session->id,
                'class' => $this->class->id,
                'section' => $this->sectionA->id,
            ]))
            ->assertOk()
            ->assertSee('Bangla')
            ->assertSee('1 period');
    }

    public function test_the_grid_refuses_an_impossible_period(): void
    {
        $day = ClassRoutine::dayIndexOf(now());

        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $this->slotPayload([
                'day_of_week' => $day,
                'start_time' => '10:00',
                'end_time' => '09:00',
            ]))
            ->assertSessionHasErrors('end_time');

        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $this->slotPayload(['day_of_week' => 9]))
            ->assertSessionHasErrors('day_of_week');

        // A section must belong to the class the period is written for.
        $otherClass = SchoolClass::query()->create(['name' => 'Class Nine', 'level' => 9, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $this->slotPayload([
                'class_id' => $otherClass->id,
                'section_id' => $this->sectionA->id,
            ]))
            ->assertSessionHasErrors('section_id');

        $this->assertSame(0, ClassRoutine::query()->count());
    }

    public function test_a_duplicate_slot_is_reported_as_a_field_error(): void
    {
        $payload = $this->slotPayload(['day_of_week' => 1, 'start_time' => '11:00', 'end_time' => '11:40']);

        $this->actingAs($this->admin)->post(route('timetable.store'), $payload)->assertSessionHasNoErrors();

        // The unique slot index is translated into a readable refusal, not an exception.
        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $payload)
            ->assertSessionHasErrors('start_time');

        $this->assertSame(1, ClassRoutine::query()->count());
    }

    public function test_a_period_can_be_moved_and_removed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('timetable.store'), $this->slotPayload(['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '08:40']))
            ->assertSessionHasNoErrors();

        $slot = ClassRoutine::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('timetable.update', $slot), $this->slotPayload([
                'day_of_week' => 3,
                'start_time' => '08:00',
                'end_time' => '08:50',
                'room' => 'Hall',
            ]))
            ->assertSessionHasNoErrors();

        $slot->refresh();

        $this->assertSame(3, $slot->day_of_week);
        $this->assertSame('Hall', $slot->room);
        $this->assertDatabaseHas('audit_logs', ['event' => 'timetable.slot.updated']);

        $this->actingAs($this->admin)
            ->delete(route('timetable.destroy', $slot))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ClassRoutine::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'class_routine.permanently_deleted']);
    }

    public function test_assigning_homework_reaches_only_the_target_class(): void
    {
        $this->giveTeacherSections($this->sectionA);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Algebra worksheet', $this->sectionA))
            ->assertSessionHasNoErrors();

        $homework = Homework::query()->firstOrFail();

        $this->assertSame($this->teacherUser->id, $homework->created_by);
        $this->assertSame($this->teacher->id, $homework->teacher_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.created', 'user_id' => $this->teacherUser->id]);

        // The student of that section is told; the parallel class is not.
        $this->assertSame(1, $this->ayesha->notifications()->count());
        $this->assertSame('homework', $this->ayesha->notifications()->firstOrFail()->category);
        $this->assertSame(0, $this->bilal->notifications()->count());

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Library reading log', null))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->ayesha->notifications()->count());
        $this->assertSame(1, $this->bilal->notifications()->count());
    }

    public function test_homework_validation_and_the_bursary_boundary(): void
    {
        Storage::fake('school_uploads');
        $this->giveTeacherSections($this->sectionA);

        $this->actingAs($this->accountant)
            ->get(route('teacher.homework.index'))
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Ledger entry', $this->sectionA))
            ->assertForbidden();

        $this->assertSame(0, Homework::query()->count());

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Ti', $this->sectionA))
            ->assertSessionHasErrors('title');

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Past due', $this->sectionA, [
                'assigned_date' => now()->toDateString(),
                'due_date' => now()->subDays(2)->toDateString(),
            ]))
            ->assertSessionHasErrors('due_date');

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Wrong paper', $this->sectionA, [
                'attachment' => UploadedFile::fake()->create('big.pdf', 6000),
            ]))
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, Homework::query()->count());

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Poetry dictation', $this->sectionA, [
                'attachment' => UploadedFile::fake()->createWithContent('poem.pdf', "%PDF-1.4\n1 0 obj\nendobj\ntrailer\n%%EOF"),
            ]))
            ->assertSessionHasNoErrors();

        $homework = Homework::query()->firstOrFail();

        $this->assertNotNull($homework->attachment_path);
        $this->assertStringStartsWith('homework/'.$this->school->id.'/', $homework->attachment_path);

        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $homework), $this->homeworkPayload('Poetry dictation (revised)', $this->sectionA))
            ->assertSessionHasNoErrors();

        $this->assertSame('Poetry dictation (revised)', $homework->refresh()->title);
        $this->assertNotNull($homework->attachment_path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.updated']);

        $this->actingAs($this->teacherUser)
            ->delete(route('teacher.homework.destroy', $homework))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('homework', ['id' => $homework->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.permanently_deleted']);
    }

    public function test_a_teacher_marks_an_answer_and_the_portal_shows_it(): void
    {
        Storage::fake('school_uploads');
        $this->giveTeacherSections($this->sectionA);

        $homework = $this->makeHomework('Science diary', $this->sectionA);

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => 'I grew a bean seed in a glass of water.',
                'attachment' => UploadedFile::fake()->createWithContent('diary.txt', 'day one: sprout'),
            ])
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()->firstOrFail();

        // The review screen is staff-only, and the answer is readable there.
        $this->actingAs($this->ayesha)
            ->get(route('teacher.homework.show', $homework))
            ->assertRedirect(route('student.dashboard'));

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.show', $homework))
            ->assertOk()
            ->assertSee('I grew a bean seed in a glass of water.')
            ->assertSee('Answers received');

        // The teacher downloads the student file.
        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.submissions.file', $submission))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->teacherUser)
            ->from(route('teacher.homework.show', $homework))
            ->post(route('teacher.homework.submissions.review', $submission), [
                'marks' => '19',
                'feedback' => 'Lovely observations, add the dates next time.',
            ])
            ->assertRedirect(route('teacher.homework.show', $homework))
            ->assertSessionHasNoErrors();

        $submission->refresh();

        $this->assertSame('reviewed', $submission->status);
        $this->assertSame(19.0, (float) $submission->marks);
        $this->assertSame($this->teacherUser->id, $submission->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.reviewed', 'user_id' => $this->teacherUser->id]);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.show', $homework))
            ->assertOk()
            ->assertSee('add the dates next time');

        // An answer without a file has nothing to download.
        $textOnly = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $this->bilalStudent->id,
            'status' => 'submitted',
            'text_answer' => 'My own diary entry.',
        ]);

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.submissions.file', $textOnly))
            ->assertNotFound();
    }

    public function test_the_homework_index_filters_by_class_and_deadline(): void
    {
        $this->giveTeacherSections($this->sectionA, $this->sectionB);

        $open = $this->makeHomework('Open task', $this->sectionA, dueDate: now()->addDays(4));
        $closed = $this->makeHomework('Closed task', $this->sectionB, dueDate: now()->subDays(2));

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index'))
            ->assertOk()
            ->assertSee('Open task')
            ->assertSee('Closed task');

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index', ['class' => $this->class->id, 'section' => $this->sectionA->id]))
            ->assertOk()
            ->assertSee('Open task')
            ->assertDontSee('Closed task');

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index', ['due' => 'past']))
            ->assertOk()
            ->assertSee('Closed task')
            ->assertDontSee('Open task');

        // The coverage figures count the roll, not the answers.
        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.show', $open))
            ->assertOk()
            ->assertSee('0 / 1')
            ->assertSee('Nothing sent yet')
            ->assertSee('No answers yet');

        $this->assertNotNull($closed->id);
    }

    public function test_the_office_decides_a_leave_request_and_the_student_is_told(): void
    {
        $this->actingAs($this->ayesha)
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(2)->toDateString(),
                'to_date' => now()->addDays(3)->toDateString(),
                'leave_type' => 'sick',
                'reason' => 'A fever the office has already seen.',
            ])
            ->assertSessionHasNoErrors();

        $leave = StudentLeave::query()->firstOrFail();

        // Teachers are not in the leave queue; the office is.
        $this->actingAs($this->teacherUser)
            ->get(route('student_leaves.index'))
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->post(route('student_leaves.decide', $leave), ['decision' => 'approved'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('student_leaves.index'))
            ->assertOk()
            ->assertSee('A fever the office has already seen')
            ->assertSee('Awaiting decision');

        $this->actingAs($this->principal)
            ->from(route('student_leaves.index'))
            ->post(route('student_leaves.decide', $leave), [
                'decision' => 'approved',
                'notes' => 'Get well soon. Bring a certificate.',
            ])
            ->assertRedirect(route('student_leaves.index'))
            ->assertSessionHasNoErrors();

        $leave->refresh();

        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->principal->id, $leave->decided_by);
        $this->assertSame('Get well soon. Bring a certificate.', $leave->decision_notes);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student_leave.approved', 'user_id' => $this->principal->id]);

        // The student reads the outcome in the portal, not in the push text.
        $this->assertSame(1, $this->ayesha->notifications()->count());
        $this->assertSame('attendance', $this->ayesha->notifications()->firstOrFail()->category);
        $this->assertSame(1, $this->admin->notifications()->count());

        $this->actingAs($this->admin)
            ->get(route('student_leaves.index', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee('A fever the office has already seen');

        // Only the two decisions the queue understands are accepted.
        $this->actingAs($this->admin)
            ->post(route('student_leaves.decide', $leave), ['decision' => 'cancelled'])
            ->assertSessionHasErrors('decision');
    }

    public function test_a_teacher_cannot_reach_a_parallel_section(): void
    {
        $this->giveTeacherSections($this->sectionA);

        $own = $this->makeHomework('Own section task', $this->sectionA);
        $foreign = $this->makeHomework('Parallel section task', $this->sectionB);
        $shared = $this->makeHomework('Whole class reading', null);

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index'))
            ->assertOk()
            ->assertSee('Own section task')
            ->assertSee('Whole class reading')
            ->assertDontSee('Parallel section task');

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.show', $foreign))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.attachment', $foreign))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $foreign), $this->homeworkPayload('Rewritten by someone else', $this->sectionB))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->delete(route('teacher.homework.destroy', $foreign))
            ->assertForbidden();

        // A homework row that belongs to nobody's section is a class-wide task.
        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.show', $shared))
            ->assertOk();

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.show', $own))
            ->assertOk();

        // The student register is office-only in this build; a teacher's roster
        // is served inside the teaching modules, narrowed by the timetable.
        $this->actingAs($this->teacherUser)
            ->get(route('students.show', $this->bilalStudent))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->get(route('students.show', $this->ayeshaStudent))
            ->assertForbidden();
    }

    public function test_a_teacher_only_marks_their_own_staff_attendance(): void
    {
        $colleague = Teacher::factory()->create();

        $page = $this->actingAs($this->teacherUser)
            ->get(route('attendance.staff.index'))
            ->assertOk();

        $this->assertCount(1, $page->viewData('sheet')['teachers']);
        $this->assertCount(0, $page->viewData('sheet')['employees']);

        $this->actingAs($this->teacherUser)
            ->post(route('attendance.staff.store'), [
                'attendance_date' => now()->toDateString(),
                'teachers' => [$this->teacher->id => 'present', $colleague->id => 'absent'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('staff_attendances', [
            'teacher_id' => $this->teacher->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseMissing('staff_attendances', ['teacher_id' => $colleague->id]);

        // The office still sees the whole register.
        $office = $this->actingAs($this->admin)
            ->get(route('attendance.staff.index'))
            ->assertOk();

        $this->assertGreaterThan(1, count($office->viewData('sheet')['teachers']));
    }

    private function buildAcademy(): void
    {
        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin');
        $this->principal = $this->makeUser($this->school, 'principal');
        $this->accountant = $this->makeUser($this->school, 'accountant');

        $teacherAccount = $this->makeUser($this->school, 'teacher', ['email' => 'teacher@example.test']);
        $this->teacherUser = $teacherAccount;

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);

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

        $this->bangla = Subject::query()->create([
            'name' => 'Bangla',
            'code' => 'BN6',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->teacher = Teacher::factory()->create(['user_id' => $teacherAccount->id]);

        [$this->ayesha, $this->ayeshaStudent] = $this->makeStudent('Ayesha', 'Rahman', $this->sectionA, 1);
        [$this->bilal, $this->bilalStudent] = $this->makeStudent('Bilal', 'Ahmed', $this->sectionB, 2);
    }

    /**
     * @return array{0: User, 1: Student}
     */
    private function makeStudent(string $first, string $last, Section $section, int $roll): array
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

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$user, $student];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slotPayload(array $overrides = []): array
    {
        return array_merge([
            'session' => $this->session->id,
            'class' => $this->class->id,
            'section' => $this->sectionA->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->bangla->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '09:40',
            'room' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function homeworkPayload(string $title, ?Section $section, array $overrides = []): array
    {
        return array_merge([
            // The unified form picks the period as one "class:section:subject"
            // string; the separate fields below are what the edit call re-posts.
            'period' => $this->class->id.':'.($section?->id ?? '').':'.$this->bangla->id,
            'academic_session_id' => $this->session->id,
            'work_type' => 'homework',
            'homework' => 'Write the answers in the notebook.',
            'class_id' => $this->class->id,
            'section_id' => $section?->id,
            'subject_id' => $this->bangla->id,
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'description' => 'Write the answers in the notebook.',
            'assigned_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            // The store action publishes unless the form asks for a draft.
            'publish' => true,
        ], $overrides);
    }

    /**
     * Put the Bangla periods for the given sections on the teacher's timetable.
     * Everything a teacher may reach - homework, marks, profiles - is read from
     * the routine, so a test that expects access has to assign the class first.
     */
    private function giveTeacherSections(Section ...$sections): void
    {
        foreach ($sections as $index => $section) {
            $start = Carbon::parse('21:00')->addMinutes($index * 30)->format('H:i');

            ClassRoutine::query()->create([
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $section->id,
                'subject_id' => $this->bangla->id,
                'teacher_id' => $this->teacher->id,
                'day_of_week' => 3,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addMinutes(40)->format('H:i'),
                'room' => null,
            ]);
        }
    }

    private function makeHomework(string $title, ?Section $section, ?Carbon $dueDate = null): Homework
    {
        return Homework::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section?->id,
            'subject_id' => $this->bangla->id,
            'teacher_id' => $this->teacher->id,
            'title' => $title,
            'description' => 'Complete every question in the exercise book.',
            'assigned_date' => now()->toDateString(),
            'due_date' => ($dueDate ?? now()->addDays(3))->toDateString(),
            'status' => Homework::PUBLISHED,
            'published_at' => now(),
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_a_draft_stays_hidden_until_it_is_published(): void
    {
        $this->giveTeacherSections($this->sectionA);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload('Field trip notes', $this->sectionA, ['publish' => '0']))
            ->assertSessionHasNoErrors();

        $draft = Homework::query()->where('title', 'Field trip notes')->sole();

        $this->assertSame(Homework::DRAFT, $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertSame(0, $this->ayesha->notifications()->count());

        // The portal cannot see or answer a draft.
        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertDontSee('Field trip notes');

        // The draft is not even resolvable from the portal, so a guessed id
        // cannot be answered early.
        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $draft), ['text_answer' => 'Too early.'])
            ->assertNotFound();

        $this->assertSame(0, HomeworkSubmission::query()->count());

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.publish', $draft))
            ->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame(Homework::PUBLISHED, $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.published', 'auditable_type' => Homework::class]);
        $this->assertSame(1, $this->ayesha->notifications()->count());

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Field trip notes');

        $this->actingAs($this->ayesha)
            ->post(route('student.homework.submit', $draft), ['text_answer' => 'We saw the birds.'])
            ->assertSessionHasNoErrors();

        // Cancelling withdraws it from the portal but keeps the answer.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.cancel', $draft))
            ->assertSessionHasNoErrors();

        $this->assertSame(Homework::CANCELLED, $draft->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'homework.cancelled', 'auditable_type' => Homework::class]);
        $this->assertDatabaseHas('homework_submissions', ['homework_id' => $draft->id, 'student_id' => $this->ayeshaStudent->id]);

        $this->actingAs($this->ayesha)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertDontSee('Field trip notes');

        // A cancelled assignment cannot be republished.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.publish', $draft))
            ->assertSessionHasErrors('status');
    }
}
