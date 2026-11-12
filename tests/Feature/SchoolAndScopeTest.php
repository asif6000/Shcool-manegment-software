<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class SchoolAndScopeTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_school_owned_records_receive_the_active_school_id(): void
    {
        $school = $this->makeSchool();

        $this->useSchoolContext($school->id);
        $student = Student::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->assertSame($school->id, $student->school_id);
        $this->assertSame($school->id, $teacher->school_id);
    }

    public function test_records_from_another_school_are_never_leaked(): void
    {
        $school = $this->makeSchool();
        $other = $this->makeSchool();

        $this->useSchoolContext($school->id);
        $ownStudent = Student::factory()->create();

        $this->useSchoolContext($other->id);
        Student::factory()->create();

        $this->useSchoolContext($school->id);

        $this->assertSame([$ownStudent->id], Student::query()->pluck('id')->all());
    }

    public function test_school_admin_can_view_their_school_profile(): void
    {
        $school = $this->makeSchool(['name' => 'My Own School']);
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('schools.show'))
            ->assertOk()
            ->assertSee('My Own School');
    }

    public function test_school_directory_redirects_to_the_profile(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('schools.index'))
            ->assertRedirect(route('schools.show'));
    }

    public function test_school_admin_can_update_their_school_profile(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)->put(route('schools.update'), [
            'name' => 'Updated School Name',
            'short_name' => 'USN',
            'code' => strtoupper($school->code),
            'eiin' => $school->eiin,
            'email' => 'office@example.test',
            'phone' => '01700000000',
            'address' => 'Updated address',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
            'locale' => 'en',
        ])->assertRedirect(route('schools.show'))->assertSessionHasNoErrors();

        $this->assertSame('Updated School Name', $school->fresh()->name);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'school.updated',
            'school_id' => $school->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_audit_trail_is_scoped_to_the_actors_school(): void
    {
        $school = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        AuditLog::query()->create([
            'school_id' => $school->id,
            'user_id' => $admin->id,
            'event' => 'school.updated',
            'description' => 'Own-school audit entry',
            'created_at' => now(),
        ]);

        AuditLog::query()->create([
            'school_id' => $other->id,
            'event' => 'school.updated',
            'description' => 'Foreign audit entry',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Own-school audit entry')
            ->assertDontSee('Foreign audit entry');
    }

    public function test_accounts_without_a_school_are_blocked(): void
    {
        $user = User::factory()->withRole('teacher')->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_admin_root_redirects_to_the_dashboard(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('dashboard'));
    }

    public function test_teacher_accounts_can_enter_the_staff_workspace(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_student_accounts_cannot_enter_the_staff_workspace(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser($school, 'student');

        $this->actingAs($student)
            ->get(route('students.index'))
            ->assertRedirect(route('applicant.dashboard'));

        $this->actingAs($student)
            ->getJson(route('students.index'))
            ->assertForbidden();
    }
}
