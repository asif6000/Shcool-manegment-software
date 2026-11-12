<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_school_admin_only_sees_users_from_their_school(): void
    {
        $schoolA = $this->makeSchool(['name' => 'Alpha School']);
        $schoolB = $this->makeSchool(['name' => 'Beta School']);

        $admin = $this->makeUser($schoolA, 'school_admin', ['name' => 'Alpha Admin']);
        $this->makeUser($schoolA, 'teacher', ['name' => 'Alpha Teacher']);
        $this->makeUser($schoolB, 'teacher', ['name' => 'Beta Teacher']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Alpha Admin')
            ->assertSee('Alpha Teacher')
            ->assertDontSee('Beta Teacher');
    }

    public function test_teacher_without_permission_cannot_manage_users(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($teacher)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_school_admin_can_create_a_teacher_in_their_school(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Teacher',
            'mobile_number' => '01744000051',
            'email' => 'new.teacher@example.test',
            'phone' => '01700000000',
            'role' => 'teacher',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'new.teacher@example.test')->firstOrFail();

        $this->assertSame($school->id, $created->school_id);
        $this->assertSame('01744000051', $created->mobile_number);
        $this->assertTrue($created->hasRole('teacher'));
        $this->assertTrue($created->must_change_password);

        // A teacher signs in with the mobile number, so the login is usable.
        $this->post(route('login.store'), [
            'role' => 'teacher',
            'mobile_number' => '01744000051',
            'password' => 'Secret@123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'user_id' => $admin->id,
            'school_id' => $school->id,
        ]);
    }

    public function test_a_mobile_role_cannot_be_created_without_a_mobile_number(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Numberless Teacher',
            'email' => 'numberless@example.test',
            'role' => 'teacher',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertDatabaseMissing('users', ['email' => 'numberless@example.test']);
    }

    public function test_a_mobile_number_cannot_be_reused_for_another_staff_account(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher', ['mobile_number' => '01744000052']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Duplicate Mobile',
            'mobile_number' => '01744000052',
            'role' => 'teacher',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertDatabaseMissing('users', ['name' => 'Duplicate Mobile']);
        $this->assertSame('01744000052', $teacher->fresh()->mobile_number);
    }

    public function test_school_admin_cannot_assign_an_equal_or_higher_role(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Second Admin',
            'email' => 'second.admin@example.test',
            'role' => 'school_admin',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'second.admin@example.test']);
    }

    public function test_student_portal_accounts_cannot_be_created_from_user_management(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Orphan Student',
            'email' => 'orphan.student@example.test',
            'role' => 'student',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'orphan.student@example.test']);
    }

    public function test_staff_cannot_be_demoted_to_a_student_account(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($admin)->put(route('users.update', $teacher), [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'role' => 'student',
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($teacher->fresh()->hasRole('teacher'));
    }

    public function test_unlinked_student_accounts_are_flagged_on_the_edit_screen(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $orphan = $this->makeUser($school, 'student');

        $this->actingAs($admin)
            ->get(route('users.edit', $orphan))
            ->assertOk()
            ->assertSee('not linked to a student record');
    }

    public function test_school_admin_cannot_edit_a_user_from_another_school(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $admin = $this->makeUser($schoolA, 'school_admin');
        $foreign = $this->makeUser($schoolB, 'teacher', ['email' => 'foreign.teacher@example.test']);

        $this->actingAs($admin)
            ->put(route('users.update', $foreign), [
                'name' => 'Hijacked',
                'email' => $foreign->email,
                'role' => 'teacher',
            ])
            ->assertForbidden();

        $this->assertSame('foreign.teacher@example.test', $foreign->fresh()->email);
    }

    public function test_admin_is_redirected_from_editing_their_own_account_in_user_admin(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('users.edit', $admin))
            ->assertRedirect(route('profile.edit'));
    }
}
