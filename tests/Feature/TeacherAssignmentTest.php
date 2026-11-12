<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TeachingScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class TeacherAssignmentTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private SchoolClass $otherClass;

    private Section $otherSection;

    private Subject $math;

    private Subject $english;

    private Teacher $teacher;

    private User $admin;

    private User $principal;

    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $this->admin = $this->makeUser($school, 'school_admin');
        $this->principal = $this->makeUser($school, 'principal');
        $this->teacherUser = $this->makeUser($school, 'teacher');

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);
        $this->otherClass = SchoolClass::query()->create(['name' => 'Class Seven', 'level' => 7, 'is_active' => true]);

        $this->sectionA = Section::query()->create([
            'class_id' => $this->class->id, 'name' => 'A', 'capacity' => 40, 'is_active' => true,
        ]);
        $this->sectionB = Section::query()->create([
            'class_id' => $this->class->id, 'name' => 'B', 'capacity' => 40, 'is_active' => true,
        ]);
        $this->otherSection = Section::query()->create([
            'class_id' => $this->otherClass->id, 'name' => 'A', 'capacity' => 40, 'is_active' => true,
        ]);

        $this->math = Subject::query()->create(['name' => 'Mathematics', 'type' => 'theory', 'is_active' => true]);
        $this->english = Subject::query()->create(['name' => 'English', 'type' => 'theory', 'is_active' => true]);

        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);
    }

    public function test_the_admin_assigns_a_subject_and_the_teacher_portal_offers_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('teacher_assignments.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teacher_assignments.index', ['session' => $this->session->id]));

        $this->assertDatabaseHas('teacher_assignments', [
            'academic_session_id' => $this->session->id,
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'status' => TeacherAssignment::STATUS_ACTIVE,
        ]);

        $slots = $this->scope()->slots($this->teacherUser->fresh(), $this->session);

        $this->assertNotNull($slots);
        $this->assertTrue($slots->contains(
            fn (array $slot): bool => $slot['class_id'] === $this->class->id
                && $slot['section_id'] === $this->sectionA->id
                && $slot['subject_id'] === $this->math->id
        ));

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.create'))
            ->assertOk()
            ->assertSee('Class Six')
            ->assertSee('Mathematics');

        $this->actingAs($this->teacherUser)
            ->get(route('lessons.create'))
            ->assertOk()
            ->assertSee('Mathematics');
    }

    public function test_a_duplicate_assignment_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('teacher_assignments.store'), $this->payload());
        $this->actingAs($this->admin)
            ->post(route('teacher_assignments.store'), $this->payload())
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, TeacherAssignment::query()->count());
    }

    public function test_the_section_must_belong_to_the_chosen_class(): void
    {
        $this->actingAs($this->admin)
            ->post(route('teacher_assignments.store'), $this->payload([
                'section_id' => $this->otherSection->id,
            ]))
            ->assertSessionHasErrors('section_id');

        $this->assertSame(0, TeacherAssignment::query()->count());
    }

    public function test_the_admin_can_edit_and_remove_an_assignment(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->admin)
            ->put(route('teacher_assignments.update', $assignment), $this->payload([
                'subject_id' => $this->english->id,
                'section_id' => $this->sectionB->id,
                'assigned_on' => '2026-11-07',
                'status' => TeacherAssignment::STATUS_INACTIVE,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teacher_assignments.index', ['session' => $this->session->id]));

        $this->assertDatabaseHas('teacher_assignments', [
            'id' => $assignment->id,
            'section_id' => $this->sectionB->id,
            'subject_id' => $this->english->id,
            'status' => TeacherAssignment::STATUS_INACTIVE,
        ]);

        // An inactive assignment is not offered to the teacher.
        $slots = $this->scope()->slots($this->teacherUser->fresh(), $this->session);
        $this->assertFalse($slots->contains(fn (array $slot): bool => $slot['subject_id'] === $this->english->id));

        $this->actingAs($this->admin)
            ->delete(route('teacher_assignments.destroy', $assignment))
            ->assertRedirect(route('teacher_assignments.index', ['session' => $this->session->id]));

        $this->assertDatabaseMissing('teacher_assignments', ['id' => $assignment->id]);
    }

    public function test_only_the_school_admin_may_manage_assignments(): void
    {
        $assignment = $this->assignment();

        foreach ([$this->teacherUser, $this->principal] as $actor) {
            $actor->givePermissionTo('staff.view', 'staff.update');

            $this->actingAs($actor->fresh())
                ->get(route('teacher_assignments.index'))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->post(route('teacher_assignments.store'), $this->payload(['subject_id' => $this->english->id]))
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->put(route('teacher_assignments.update', $assignment), $this->payload())
                ->assertForbidden();

            $this->actingAs($actor->fresh())
                ->delete(route('teacher_assignments.destroy', $assignment))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('teacher_assignments', ['id' => $assignment->id]);
    }

    public function test_a_teacher_can_save_work_for_an_assigned_subject_but_not_an_unassigned_one(): void
    {
        $this->assignment();

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->workPayload($this->math))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('homework', [
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'created_by' => $this->teacherUser->id,
        ]);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->workPayload($this->english))
            ->assertSessionHasErrors('period');

        $this->assertDatabaseMissing('homework', [
            'subject_id' => $this->english->id,
            'created_by' => $this->teacherUser->id,
        ]);
    }

    public function test_removing_an_assignment_revokes_access_but_keeps_saved_work(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->workPayload($this->math))
            ->assertSessionHasNoErrors();

        $homework = Homework::query()->where('created_by', $this->teacherUser->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('teacher_assignments.destroy', $assignment))
            ->assertRedirect();

        $this->assertDatabaseHas('homework', ['id' => $homework->id]);

        $slots = $this->scope()->slots($this->teacherUser->fresh(), $this->session);
        $this->assertFalse($slots->contains(
            fn (array $slot): bool => $slot['class_id'] === $this->class->id
                && $slot['section_id'] === $this->sectionA->id
                && $slot['subject_id'] === $this->math->id
        ));

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->workPayload($this->math))
            ->assertSessionHasErrors('period');
    }

    private function assignment(array $overrides = []): TeacherAssignment
    {
        return TeacherAssignment::query()->create($this->payload($overrides) + [
            'created_by' => $this->admin->id,
        ]);
    }

    private function scope(): TeachingScope
    {
        $scope = app(TeachingScope::class);
        $scope->reset();

        return $scope;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->math->id,
            'assigned_on' => '2026-11-06',
            'status' => TeacherAssignment::STATUS_ACTIVE,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function workPayload(Subject $subject): array
    {
        return [
            'period' => $this->class->id.':'.$this->sectionA->id.':'.$subject->id,
            'academic_session_id' => $this->session->id,
            'work_type' => 'homework',
            'title' => 'Practice the chapter',
            'homework' => 'Solve the exercises on page ten.',
            'assigned_date' => '2026-11-06',
            'publish' => '0',
        ];
    }
}
