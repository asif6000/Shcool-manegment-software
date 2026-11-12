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
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The rule this file protects: the office assigns periods to teachers, and only
 * the teacher who holds the period writes the homework and classwork. Every
 * assertion goes through the real route, so a hidden button cannot stand in for
 * a missing backend check.
 */
class TeacherHomeworkControlTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $teacherUser;

    private Teacher $teacher;

    private User $colleagueUser;

    private Teacher $colleague;

    private User $studentUser;

    private Student $student;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private Subject $mathematics;

    private Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->buildSchool();
    }

    public function test_the_office_assigns_a_period_and_the_teacher_can_then_work_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('timetable.store'), [
                'session' => $this->session->id,
                'class' => $this->class->id,
                'section' => $this->sectionA->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->english->id,
                'teacher_id' => $this->teacher->id,
                'day_of_week' => 5,
                'start_time' => '09:00',
                'end_time' => '09:40',
                'room' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_routines', [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->english->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
        ]);

        // The newly assigned subject now appears in the teacher's chooser and
        // can carry an assignment.
        $form = $this->actingAs($this->teacherUser)->get(route('teacher.homework.create'))->assertOk();
        $this->assertContains($this->english->id, $form->viewData('subjects')->pluck('id')->all());

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->english->id,
                'title' => 'Essay on the rainy day',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('homework', [
            'title' => 'Essay on the rainy day',
            'subject_id' => $this->english->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_the_office_cannot_write_or_publish_homework_through_the_routes(): void
    {
        $homework = $this->publishedHomework();

        $this->actingAs($this->admin)->get(route('teacher.homework.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('teacher.homework.create'))->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.store'), $this->homeworkPayload(['title' => 'Written by the office']))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->put(route('teacher.homework.update', $homework), $this->editPayload(['title' => 'Rewritten by the office']))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.unpublish', $homework))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->delete(route('teacher.homework.destroy', $homework))
            ->assertForbidden();

        $this->assertSame('Algebra worksheet', $homework->refresh()->title);
        $this->assertSame(Homework::PUBLISHED, $homework->status);
        $this->assertSame(1, Homework::query()->count());
    }

    public function test_the_office_cannot_write_share_or_hide_classwork_through_the_routes(): void
    {
        $material = $this->sharedClasswork();

        $this->actingAs($this->admin)->get(route('materials.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('materials.create'))->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('materials.store'), $this->classworkPayload(['title' => 'Written by the office']))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->put(route('materials.update', $material), $this->classworkPayload(['title' => 'Rewritten by the office']))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('materials.unpublish', $material))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->delete(route('materials.destroy', $material))
            ->assertForbidden();

        $this->assertSame('Fraction circles', $material->refresh()->title);
        $this->assertSame(StudyMaterial::PUBLISHED, $material->status);
        $this->assertSame(1, StudyMaterial::query()->count());
    }

    public function test_the_principal_is_refused_even_when_the_permission_matrix_grants_the_modules(): void
    {
        $homework = $this->publishedHomework();
        $material = $this->sharedClasswork();

        $principal = $this->makeUser($this->school, 'principal');
        $principal->givePermissionTo([
            'homework.view',
            'homework.manage',
            'materials.view',
            'materials.manage',
            'timetable.manage',
        ]);

        $this->actingAs($principal)->get(route('teacher.homework.index'))->assertForbidden();
        $this->actingAs($principal)->get(route('teacher.homework.create'))->assertForbidden();
        $this->actingAs($principal)
            ->post(route('teacher.homework.store'), $this->homeworkPayload(['title' => 'Written by the principal']))
            ->assertForbidden();
        $this->actingAs($principal)
            ->put(route('teacher.homework.update', $homework), $this->editPayload(['title' => 'Rewritten by the principal']))
            ->assertForbidden();
        $this->actingAs($principal)
            ->delete(route('teacher.homework.destroy', $homework))
            ->assertForbidden();

        $this->actingAs($principal)->get(route('materials.index'))->assertForbidden();
        $this->actingAs($principal)->get(route('materials.create'))->assertForbidden();
        $this->actingAs($principal)
            ->post(route('materials.store'), $this->classworkPayload(['title' => 'Written by the principal']))
            ->assertForbidden();
        $this->actingAs($principal)
            ->delete(route('materials.destroy', $material))
            ->assertForbidden();

        // Assigning the period is the administrator's job alone.
        $this->actingAs($principal)
            ->post(route('timetable.store'), [
                'session' => $this->session->id,
                'class' => $this->class->id,
                'section' => $this->sectionA->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->english->id,
                'teacher_id' => $this->teacher->id,
                'day_of_week' => 5,
                'start_time' => '11:00',
                'end_time' => '11:40',
            ])
            ->assertForbidden();

        $this->assertSame('Algebra worksheet', $homework->refresh()->title);
        $this->assertSame('Fraction circles', $material->refresh()->title);
        $this->assertCount(3, ClassRoutine::query()->get());
    }

    public function test_a_teacher_can_only_file_work_against_their_own_periods(): void
    {
        // An unassigned subject is refused before anything is written.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->english->id,
                'title' => 'Unassigned subject',
            ]))
            ->assertSessionHasErrors('period');

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'period' => $this->class->id.':'.$this->sectionB->id.':'.$this->mathematics->id,
                'title' => 'Unassigned section',
            ]))
            ->assertSessionHasErrors('period');

        // Naming a colleague cannot move the work onto them: the field is not
        // part of the form, so the row is always filed under the signed-in teacher.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'title' => 'Filed under a colleague',
                'teacher_id' => $this->colleague->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $this->teacher->id,
            Homework::query()->where('title', 'Filed under a colleague')->sole()->teacher_id,
        );

        $this->actingAs($this->teacherUser)
            ->post(route('materials.store'), $this->classworkPayload([
                'subject_id' => $this->english->id,
                'title' => 'Unassigned classwork',
            ]))
            ->assertForbidden();

        $this->assertSame(1, Homework::query()->count());
        $this->assertSame(0, StudyMaterial::query()->count());
    }

    public function test_a_record_cannot_be_moved_to_a_period_the_teacher_does_not_hold(): void
    {
        $homework = $this->publishedHomework();

        // A hand-edited request that names the colleague's parallel section, or
        // a subject nobody assigned, is refused by the backend rather than by
        // the dropdown the browser was given.
        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $homework), $this->editPayload([
                'section_id' => $this->sectionB->id,
            ]))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $homework), $this->editPayload([
                'subject_id' => $this->english->id,
            ]))
            ->assertForbidden();

        $homework->refresh();
        $this->assertSame($this->sectionA->id, $homework->section_id);
        $this->assertSame($this->mathematics->id, $homework->subject_id);
        $this->assertSame('Algebra worksheet', $homework->title);

        // The same teacher still saves the period they do hold.
        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $homework), $this->editPayload([
                'title' => 'Algebra worksheet, exercise 5',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Algebra worksheet, exercise 5', $homework->refresh()->title);
    }

    public function test_the_classwork_tab_files_edits_publishes_and_deletes_the_same_way(): void
    {
        // The second tab of the one screen files through the same route, in the
        // same table, and only differs by category and the missing deadline.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->classworkOnlyPayload())
            ->assertSessionHasNoErrors();

        $classwork = Homework::query()->where('title', 'Fractions on the board')->sole();
        $this->assertSame(Homework::CATEGORY_CLASSWORK, $classwork->category);
        $this->assertNull($classwork->due_date);
        $this->assertSame(Homework::PUBLISHED, $classwork->status);

        $this->actingAs($this->studentUser)
            ->get(route('student.homework.index', ['category' => Homework::CATEGORY_CLASSWORK]))
            ->assertOk()
            ->assertSee('Fractions on the board');

        // The office watches this tab exactly as closely as the other one.
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.store'), $this->classworkOnlyPayload(['title' => 'Written by the office']))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->put(route('teacher.homework.update', $classwork), $this->editPayload(['title' => 'Rewritten by the office']))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.publish', $classwork))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.cancel', $classwork))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->delete(route('teacher.homework.destroy', $classwork))
            ->assertForbidden();

        $this->assertSame(Homework::PUBLISHED, $classwork->refresh()->status);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.unpublish', $classwork))
            ->assertSessionHasNoErrors();
        $this->assertSame(Homework::DRAFT, $classwork->refresh()->status);

        $this->actingAs($this->studentUser)
            ->get(route('student.homework.index', ['category' => Homework::CATEGORY_CLASSWORK]))
            ->assertOk()
            ->assertDontSee('Fractions on the board');

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.publish', $classwork))
            ->assertSessionHasNoErrors();
        $this->assertSame(Homework::PUBLISHED, $classwork->refresh()->status);

        // Editing never re-files a class work record as homework.
        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $classwork), $this->editPayload([
                'title' => 'Fractions on the board, part two',
            ]))
            ->assertSessionHasNoErrors();

        $classwork->refresh();
        $this->assertSame('Fractions on the board, part two', $classwork->title);
        $this->assertSame(Homework::CATEGORY_CLASSWORK, $classwork->category);

        $this->actingAs($this->teacherUser)
            ->delete(route('teacher.homework.destroy', $classwork))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('homework', ['id' => $classwork->id]);
    }

    public function test_a_removed_assignment_stops_new_work_but_not_the_authors_custody(): void
    {
        $homework = $this->publishedHomework();
        $slot = ClassRoutine::query()->where('teacher_id', $this->teacher->id)->sole();

        $this->actingAs($this->admin)
            ->delete(route('timetable.destroy', $slot))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('class_routines', ['id' => $slot->id]);

        // The period is gone, so no new work can name it.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload(['title' => 'After the reassignment']))
            ->assertSessionHasErrors('period');
        $this->assertDatabaseMissing('homework', ['title' => 'After the reassignment']);

        $form = $this->actingAs($this->teacherUser)->get(route('teacher.homework.create'))->assertOk();
        $this->assertSame([], $form->viewData('subjects')->pluck('id')->all());

        // What the teacher already filed survives the move: the class keeps
        // reading it, the office keeps watching it, and the author keeps the
        // record itself. The tab lists stay bound to the current timetable, so
        // the work is reached through its own screen rather than a class the
        // teacher no longer holds.
        $list = $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index'))
            ->assertOk();
        $this->assertFalse($list->viewData('homeworkRecords')->contains('id', $homework->id));

        $this->actingAs($this->studentUser)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Algebra worksheet');

        $this->actingAs($this->admin)
            ->get(route('work-monitor.show', $homework))
            ->assertOk()
            ->assertSee('Algebra worksheet');

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.show', $homework))
            ->assertOk()
            ->assertSee('Algebra worksheet');

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.edit', $homework))
            ->assertOk();

        $this->actingAs($this->teacherUser)
            ->put(route('teacher.homework.update', $homework), $this->editPayload([
                'title' => 'Algebra worksheet, corrected',
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Algebra worksheet, corrected', $homework->refresh()->title);

        // Only the author can withdraw it: the office still cannot write.
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.unpublish', $homework))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.unpublish', $homework))
            ->assertSessionHasNoErrors();
        $this->assertSame(Homework::DRAFT, $homework->refresh()->status);

        $this->actingAs($this->studentUser)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertDontSee('Algebra worksheet, corrected');
    }

    public function test_a_colleague_on_the_same_period_cannot_touch_someone_elses_work(): void
    {
        $homework = $this->publishedHomework();
        $material = $this->sharedClasswork();

        // Even on the same timetable, a colleague's list and record are not
        // readable; the principal keeps the whole-school view.
        $colleagueList = $this->actingAs($this->colleagueUser)
            ->get(route('teacher.homework.index'))
            ->assertOk();
        $this->assertFalse($colleagueList->viewData('homeworkRecords')->contains('id', $homework->id));

        $this->actingAs($this->colleagueUser)
            ->get(route('teacher.homework.show', $homework))
            ->assertForbidden();
        $this->actingAs($this->colleagueUser)
            ->get(route('teacher.homework.edit', $homework))
            ->assertForbidden();
        $this->actingAs($this->colleagueUser)
            ->put(route('teacher.homework.update', $homework), $this->editPayload(['title' => 'Someone else wrote this']))
            ->assertForbidden();
        $this->actingAs($this->colleagueUser)
            ->post(route('teacher.homework.unpublish', $homework))
            ->assertForbidden();
        $this->actingAs($this->colleagueUser)
            ->delete(route('teacher.homework.destroy', $homework))
            ->assertForbidden();

        $this->actingAs($this->colleagueUser)
            ->get(route('materials.edit', $material))
            ->assertForbidden();
        $this->actingAs($this->colleagueUser)
            ->put(route('materials.update', $material), $this->classworkPayload(['title' => 'Someone else wrote this']))
            ->assertForbidden();
        $this->actingAs($this->colleagueUser)
            ->post(route('materials.unpublish', $material))
            ->assertForbidden();

        $this->assertSame('Algebra worksheet', $homework->refresh()->title);
        $this->assertSame(Homework::PUBLISHED, $homework->status);
        $this->assertSame('Fraction circles', $material->refresh()->title);
        $this->assertSame(StudyMaterial::PUBLISHED, $material->status);
    }

    public function test_students_see_only_published_work_for_their_own_section(): void
    {
        $this->publishedHomework();

        $draft = $this->homeworkPayload([
            'title' => 'Draft worksheet',
            'publish' => '0',
        ]);
        $this->actingAs($this->teacherUser)->post(route('teacher.homework.store'), $draft)->assertSessionHasNoErrors();

        $this->actingAs($this->colleagueUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'period' => $this->class->id.':'.$this->sectionB->id.':'.$this->mathematics->id,
                'title' => 'Section B only',
            ]))
            ->assertSessionHasNoErrors();

        $homeworkList = $this->actingAs($this->studentUser)
            ->get(route('student.homework.index'))
            ->assertOk();

        // Asserted on the rows, because the teacher's flash message still lives
        // in the shared test session and would otherwise satisfy assertSee.
        $this->assertSame(
            ['Algebra worksheet'],
            $homeworkList->viewData('rows')->pluck('title')->all(),
        );

        $this->sharedClasswork();

        $draftClasswork = $this->classworkPayload([
            'title' => 'Draft classwork',
            'publish' => '0',
        ]);
        $this->actingAs($this->teacherUser)->post(route('materials.store'), $draftClasswork)->assertSessionHasNoErrors();

        $classworkList = $this->actingAs($this->studentUser)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk();

        $this->assertSame(
            ['Fraction circles'],
            $classworkList->viewData('rows')->pluck('title')->all(),
        );
    }

    public function test_the_teacher_publishes_and_withdraws_classwork_and_the_student_follows(): void
    {
        $draft = StudyMaterial::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathematics->id,
            'kind' => StudyMaterial::CLASSWORK,
            'status' => StudyMaterial::DRAFT,
            'title' => 'Long division practice',
            'work_date' => now()->toDateString(),
            'created_by' => $this->teacherUser->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->studentUser)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk()
            ->assertDontSee('Long division practice');

        $this->actingAs($this->teacherUser)
            ->post(route('materials.publish', $draft))
            ->assertSessionHasNoErrors();

        $this->assertSame(StudyMaterial::PUBLISHED, $draft->refresh()->status);
        $this->assertNotNull($draft->published_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'material.published', 'user_id' => $this->teacherUser->id]);

        $this->actingAs($this->studentUser)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk()
            ->assertSee('Long division practice');

        $this->actingAs($this->teacherUser)
            ->post(route('materials.unpublish', $draft))
            ->assertSessionHasNoErrors();

        $this->assertSame(StudyMaterial::DRAFT, $draft->refresh()->status);
        $this->assertNull($draft->published_at);

        $this->actingAs($this->studentUser)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk()
            ->assertDontSee('Long division practice');
    }

    public function test_a_submission_round_trip_ends_in_marks_and_feedback_for_the_student(): void
    {
        $homework = $this->publishedHomework();

        $this->actingAs($this->studentUser)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => 'I solved every sum and checked them twice.',
            ])
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()->sole();
        $this->assertSame($this->student->id, $submission->student_id);

        // A colleague on the same period cannot answer for the owner.
        $this->actingAs($this->colleagueUser)
            ->post(route('teacher.homework.submissions.review', $submission), [
                'marks' => '20',
                'feedback' => 'Not my class.',
            ])
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.submissions.review', $submission), [
                'marks' => '18',
                'feedback' => 'Show the working for question four next time.',
            ])
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame('reviewed', $submission->status);
        $this->assertSame(18.0, (float) $submission->marks);
        $this->assertSame($this->teacherUser->id, $submission->reviewed_by);

        $this->actingAs($this->studentUser)
            ->get(route('student.homework.show', $homework))
            ->assertOk()
            ->assertSee('Show the working for question four next time.')
            ->assertSee('Reviewed');
    }

    public function test_homework_instructions_reach_the_student(): void
    {
        $homework = $this->publishedHomework();

        $this->assertSame(
            'Bring your geometry box and draw each figure twice.',
            $homework->instructions,
        );

        $this->actingAs($this->studentUser)
            ->get(route('student.homework.show', $homework))
            ->assertOk()
            ->assertSee('Bring your geometry box and draw each figure twice.');
    }

    private function buildSchool(): void
    {
        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin');
        $this->teacherUser = $this->makeUser($this->school, 'teacher');
        $this->colleagueUser = $this->makeUser($this->school, 'teacher');

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

        $this->mathematics = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->english = Subject::query()->create([
            'name' => 'English',
            'code' => 'ENG',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);
        $this->colleague = Teacher::factory()->create(['user_id' => $this->colleagueUser->id]);

        // Both teachers hold the same mathematics period in section A, which is
        // exactly the case a scope-only check would let them edit each other.
        $this->routine($this->teacher, $this->sectionA, 3, '09:00');
        $this->routine($this->colleague, $this->sectionA, 3, '11:00');
        // The colleague also holds section B, so a parallel-section homework can
        // be created through the real route.
        $this->routine($this->colleague, $this->sectionB, 4, '09:00');

        [$this->studentUser, $this->student] = $this->enrolledStudent();
    }

    private function routine(Teacher $teacher, Section $section, int $day, string $start): void
    {
        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'subject_id' => $this->mathematics->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => Carbon::parse($start)->addMinutes(40)->format('H:i'),
        ]);
    }

    /**
     * @return array{0: User, 1: Student}
     */
    private function enrolledStudent(): array
    {
        $user = $this->makeUser($this->school, 'student');
        $student = Student::factory()->create();

        $student->forceFill(['user_id' => $user->id])->save();
        $user->setRelation('student', $student);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$user, $student];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function homeworkPayload(array $overrides = []): array
    {
        return array_merge([
            // The unified screen names the period in one box; the request splits it.
            'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->mathematics->id,
            'academic_session_id' => $this->session->id,
            'work_type' => 'homework',
            'title' => 'Algebra worksheet',
            'homework' => 'Answer every question in exercise 4.',
            'instructions' => 'Bring your geometry box and draw each figure twice.',
            'assigned_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'publish' => true,
        ], $overrides);
    }

    /**
     * The same period, filed as class work instead of homework.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function classworkOnlyPayload(array $overrides = []): array
    {
        return $this->homeworkPayload(array_merge([
            'work_type' => 'classwork',
            'title' => 'Fractions on the board',
            'homework' => null,
            'classwork' => 'We cut the circles and compared the halves.',
            'due_date' => null,
        ], $overrides));
    }

    /**
     * The edit screen still posts the period as separate fields, so a valid
     * payload here proves a refusal came from the policy and not from a
     * validation error on a hand-built request.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathematics->id,
            'title' => 'Algebra worksheet',
            'description' => 'Answer every question in exercise 4.',
            'instructions' => 'Bring your geometry box and draw each figure twice.',
            'assigned_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'publish' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function classworkPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathematics->id,
            'kind' => StudyMaterial::CLASSWORK,
            'title' => 'Fraction circles',
            'description' => 'Cut the circles and compare the halves.',
            'work_date' => now()->toDateString(),
            'publish' => true,
        ], $overrides);
    }

    private function publishedHomework(): Homework
    {
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload())
            ->assertSessionHasNoErrors();

        return Homework::query()->where('title', 'Algebra worksheet')->sole();
    }

    private function sharedClasswork(): StudyMaterial
    {
        $this->actingAs($this->teacherUser)
            ->post(route('materials.store'), $this->classworkPayload())
            ->assertSessionHasNoErrors();

        return StudyMaterial::query()->where('title', 'Fraction circles')->sole();
    }
}
