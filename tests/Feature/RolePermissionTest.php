<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $principal;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin');
        $this->principal = $this->makeUser($this->school, 'principal');
        $this->teacher = $this->makeUser($this->school, 'teacher');
    }

    public function test_the_school_admin_sees_the_full_permission_matrix(): void
    {
        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Roles & permissions')
            ->assertSee('Save permissions')
            ->assertSee('School Admin')
            ->assertSee('Principal')
            ->assertSee('Teacher')
            ->assertSee('students.view')
            ->assertSee('roles[teacher][]', false)
            ->assertSee('Editable by School Admin');
    }

    public function test_only_catalogue_roles_are_managed_from_the_matrix(): void
    {
        $legacy = Role::findOrCreate('parent', 'web');
        $legacy->syncPermissions(['students.view']);

        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertDontSee('roles[parent]', false)
            ->assertDontSee('Level 0');

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => [
                    'parent' => ['students.view'],
                    'teacher' => ['dashboard.view'],
                ],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($legacy->fresh()->hasPermissionTo('students.view'));
    }

    public function test_the_principal_can_read_the_matrix_but_cannot_change_it(): void
    {
        $this->actingAs($this->principal)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Read only')
            ->assertDontSee('Save permissions')
            ->assertDontSee('roles[teacher][]', false);

        $this->actingAs($this->principal)
            ->put(route('roles.permissions.update'), ['roles' => ['teacher' => ['dashboard.view']]])
            ->assertForbidden();

        $this->assertTrue(Role::findByName('teacher')->hasPermissionTo('dashboard.view'));
    }

    public function test_a_teacher_cannot_open_or_change_the_matrix(): void
    {
        $this->actingAs($this->teacher)->get(route('roles.index'))->assertForbidden();

        $this->actingAs($this->teacher)
            ->put(route('roles.permissions.update'), ['roles' => ['teacher' => []]])
            ->assertForbidden();
    }

    public function test_the_school_admin_can_change_role_permissions_and_it_is_audited(): void
    {
        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => [
                    'principal' => ['dashboard.view', 'roles.view'],
                    'teacher' => ['dashboard.view', 'notices.view'],
                ],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHasNoErrors();

        $teacher = Role::findByName('teacher');

        $this->assertSame(
            ['dashboard.view', 'notices.view'],
            $teacher->permissions->pluck('name')->sort()->values()->all(),
        );

        $this->assertSame(
            ['dashboard.view', 'roles.view'],
            Role::findByName('principal')->permissions->pluck('name')->sort()->values()->all(),
        );

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'role.permissions_updated',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_permission_changes_apply_to_who_can_open_a_protected_screen(): void
    {
        Role::findByName('teacher')->givePermissionTo('students.view');

        $this->actingAs($this->teacher)->get(route('students.index'))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['teacher' => ['dashboard.view']],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->get(route('students.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('dashboard'))->assertOk();
    }

    public function test_the_school_admin_cannot_lock_itself_out_of_the_matrix(): void
    {
        $permissions = Role::findByName('school_admin')->permissions->pluck('name')->all();
        $permissions = array_values(array_diff($permissions, ['roles.view']));

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['school_admin' => $permissions],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue(Role::findByName('school_admin')->hasPermissionTo('roles.view'));
        $this->actingAs($this->admin)->get(route('roles.index'))->assertOk();
    }

    public function test_homework_and_classwork_permissions_stay_with_the_teacher_role(): void
    {
        $principalPermissions = Role::findByName('principal')->permissions->pluck('name')->all();

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['principal' => array_merge($principalPermissions, ['homework.view'])],
            ])
            ->assertSessionHasErrors('roles.principal');

        $this->assertFalse(Role::findByName('principal')->hasPermissionTo('homework.view'));
        $this->assertTrue(Role::findByName('principal')->hasPermissionTo('lessons.view'));
    }

    public function test_the_school_admin_cannot_be_given_a_learning_permission(): void
    {
        $permissions = Role::findByName('school_admin')->permissions->pluck('name')->all();
        $permissions[] = 'homework.view';

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['school_admin' => $permissions],
            ])
            ->assertSessionHasErrors('roles.school_admin');

        $this->assertFalse(Role::findByName('school_admin')->hasPermissionTo('homework.view'));
    }

    public function test_unknown_permission_names_are_rejected(): void    {
        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['teacher' => ['dashboard.view', 'made.up.permission']],
            ])
            ->assertSessionHasErrors('roles.teacher.1');

        $this->assertTrue(Role::findByName('teacher')->hasPermissionTo('dashboard.view'));
    }

    public function test_an_unchanged_submission_reports_no_changes(): void
    {
        $permissions = Role::findByName('teacher')->permissions->pluck('name')->all();

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['teacher' => $permissions],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('success', __('No permission changes were needed.'));

        $this->assertDatabaseMissing('audit_logs', ['event' => 'role.permissions_updated']);
    }

    public function test_the_empty_hidden_field_does_not_grant_an_empty_permission(): void
    {
        $this->assertNotNull(Permission::findByName('dashboard.view'));

        $this->actingAs($this->admin)
            ->put(route('roles.permissions.update'), [
                'roles' => ['teacher' => ['', 'dashboard.view']],
            ])
            ->assertSessionHasNoErrors();

        $teacher = Role::findByName('teacher');

        $this->assertSame(['dashboard.view'], $teacher->permissions->pluck('name')->all());
        $this->assertFalse($teacher->hasPermissionTo('students.view'));
    }
}
