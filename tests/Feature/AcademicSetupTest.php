<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class AcademicSetupTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_school_admin_can_create_an_academic_session_that_becomes_current(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)->post(route('academic.sessions.store'), [
            'name' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'active',
            'is_current' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $session = AcademicSession::query()->where('name', '2027')->firstOrFail();

        $this->assertTrue($session->is_current);
        $this->assertSame($school->id, $session->school_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'academic.session_created',
            'school_id' => $school->id,
        ]);
    }

    public function test_switching_the_current_session_is_exclusive(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->useSchoolContext($school->id);
        $first = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $second = AcademicSession::query()->create([
            'name' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('academic.sessions.current', $second))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
    }

    public function test_class_names_are_unique_within_a_school_but_not_across_schools(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $payload = [
            'name' => 'Class Six',
            'level' => 6,
            'is_active' => '1',
        ];

        $this->actingAs($admin)->post(route('academic.classes.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('academic.classes.store'), $payload)
            ->assertSessionHasErrors('name');

        $this->assertSame(1, SchoolClass::query()->where('name', 'Class Six')->count());
    }

    public function test_sections_cannot_be_created_for_another_schools_class(): void
    {
        $school = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->useSchoolContext($other->id);
        $foreignClass = SchoolClass::query()->create([
            'name' => 'Foreign Class',
            'level' => 1,
            'is_active' => true,
        ]);

        $this->useSchoolContext($school->id);

        $this->actingAs($admin)->post(route('academic.sections.store'), [
            'class_id' => $foreignClass->id,
            'name' => 'A',
        ])->assertNotFound();
    }

    public function test_curriculum_is_saved_for_a_session_and_class(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->useSchoolContext($school->id);
        $session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create(['name' => 'Five', 'level' => 5, 'is_active' => true]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('academic.curriculum.update'), [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'subjects' => [
                $subject->id => [
                    'enabled' => '1',
                    'full_marks' => 100,
                    'pass_marks' => 33,
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_subjects', [
            'school_id' => $school->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_pass_marks_cannot_exceed_full_marks(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->useSchoolContext($school->id);
        $session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create(['name' => 'Five', 'level' => 5, 'is_active' => true]);
        $subject = Subject::query()->create(['name' => 'Science', 'type' => 'theory', 'is_active' => true]);

        $this->actingAs($admin)->post(route('academic.curriculum.update'), [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'subjects' => [
                $subject->id => ['enabled' => '1', 'full_marks' => 50, 'pass_marks' => 60],
            ],
        ])->assertSessionHasErrors('subjects');
    }

    public function test_teacher_cannot_manage_academic_setup(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($teacher)->post(route('academic.classes.store'), [
            'name' => 'Unauthorized Class',
            'level' => 1,
        ])->assertForbidden();

        $this->assertFalse(SchoolClass::query()->where('name', 'Unauthorized Class')->exists());
    }

    public function test_section_cannot_be_deleted_while_it_has_enrollments(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->useSchoolContext($school->id);
        $session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create(['name' => 'Four', 'level' => 4, 'is_active' => true]);
        $section = Section::query()->create([
            'class_id' => $class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        Student::factory()->create([
            'student_id' => 'S-001',
            'admission_no' => 'A-001',
        ]);
        $student = Student::query()->firstOrFail();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-05',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('academic.sections.destroy', $section), ['permanent_delete' => 'DELETE'])
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($section);
    }

    public function test_the_sidebar_links_to_subject_creation(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('academic.index', ['tab' => 'subjects']), false)
            ->assertSee(__('Subjects'));

        $this->actingAs($admin)
            ->get(route('academic.index', ['tab' => 'subjects']))
            ->assertOk()
            ->assertSee(__('Add subject'))
            ->assertSee(route('academic.subjects.store'), false);
    }
}
