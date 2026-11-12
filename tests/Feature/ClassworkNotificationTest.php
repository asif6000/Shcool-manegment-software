<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\User;
use App\Services\SchoolEventNotifier;
use App\Services\StudyMaterialService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class ClassworkNotificationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Section $otherSection;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

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

        $this->section = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->otherSection = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'B',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'type' => 'theory',
            'is_active' => true,
        ]);
    }

    public function test_new_classwork_notifies_the_section_once_and_regular_materials_do_not(): void
    {
        [, $studentUser] = $this->enrolledStudent($this->section, 1);
        [, $otherStudentUser] = $this->enrolledStudent($this->otherSection, 2);

        $materials = app(StudyMaterialService::class);
        $material = $materials->create($this->admin, $this->materialData([
            'kind' => StudyMaterial::CLASSWORK,
            'work_date' => '2026-10-05',
        ]));

        $notification = $studentUser->notifications()->firstOrFail();

        $this->assertSame('classwork', $notification->category);
        $this->assertSame(route('student.materials.index'), $notification->data['url']);
        $this->assertStringContainsString($this->subject->name, $notification->data['message']);
        $this->assertStringNotContainsString($this->subject->name, (string) $notification->data['push_body']);

        $this->assertSame(0, $otherStudentUser->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());

        app(SchoolEventNotifier::class)->classworkShared($material);

        $this->assertSame(1, $studentUser->notifications()->count());

        $materials->create($this->admin, $this->materialData([
            'kind' => StudyMaterial::MATERIAL,
            'title' => 'Reference notes',
        ]));

        $this->assertSame(1, $studentUser->notifications()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function materialData(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'kind' => StudyMaterial::CLASSWORK,
            'title' => 'Fractions practice',
            'description' => 'Complete exercises 1 to 10 in the workbook.',
            'work_date' => '2026-10-05',
        ], $overrides);
    }

    /**
     * @return array{0: Student, 1: User}
     */
    private function enrolledStudent(Section $section, int $roll): array
    {
        $student = Student::factory()->create();
        $user = $this->makeUser($this->school, 'student');

        $student->forceFill(['user_id' => $user->id])->save();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$student, $user];
    }
}
