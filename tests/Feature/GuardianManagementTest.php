<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_browse_guardians_and_linked_students(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school);
        $this->useSchoolContext($school->id);

        $student = Student::factory()->create();
        $guardian = Guardian::query()->create([
            'name' => 'Guardian One',
            'relation' => 'father',
            'phone' => '01700000000',
            'email' => 'guardian@example.com',
        ]);

        $guardian->students()->attach($student->id, [
            'school_id' => $school->id,
            'is_primary' => true,
            'is_emergency_contact' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('guardians.index'))
            ->assertOk()
            ->assertSee('Guardian Management')
            ->assertSee('Guardian One')
            ->assertSee($student->student_id);
    }

    public function test_guardian_directory_requires_student_view_permission(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->useSchoolContext($school->id);

        $this->actingAs($teacher)
            ->get(route('guardians.index'))
            ->assertForbidden();
    }
}
