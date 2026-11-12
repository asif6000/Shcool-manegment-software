<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\SalaryStructure;
use App\Models\SchoolClass;
use App\Models\StaffLeave;
use App\Models\Teacher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_school_admin_can_add_a_teacher_with_an_auto_generated_code(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('staff.teachers.store'), [
                'first_name' => 'Anisur',
                'last_name' => 'Rahman',
                'gender' => 'male',
                'date_of_birth' => '1990-04-12',
                'phone' => '01710000001',
                'email' => 'anisur@example.test',
                'designation' => 'Senior Teacher',
                'specialization' => 'Mathematics',
                'qualification' => 'M.Sc',
                'joining_date' => '2026-01-05',
                'employment_type' => 'full_time',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $teacher = Teacher::query()->where('first_name', 'Anisur')->firstOrFail();

        $this->assertSame($school->id, $teacher->school_id);
        $this->assertMatchesRegularExpression('/^TCH-2026-\d{4}$/', $teacher->employee_code);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'staff.created',
            'school_id' => $school->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('staff.teachers.show', $teacher))->assertOk();
    }

    public function test_a_teacher_login_can_be_created_from_the_teacher_form(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('staff.teachers.create'))
            ->assertOk()
            ->assertSee('Portal password');

        $this->actingAs($admin)
            ->post(route('staff.teachers.store'), [
                'first_name' => 'Anisur',
                'last_name' => 'Rahman',
                'gender' => 'male',
                'phone' => '01710000011',
                'password' => 'Teacher@2026',
                'password_confirmation' => 'Teacher@2026',
                'designation' => 'Senior Teacher',
                'joining_date' => '2026-01-05',
                'employment_type' => 'full_time',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $teacher = Teacher::query()->where('first_name', 'Anisur')->firstOrFail();
        $account = $teacher->user()->firstOrFail();

        $this->assertSame($school->id, $account->school_id);
        $this->assertSame('01710000011', $account->mobile_number);
        $this->assertTrue($account->hasRole('teacher'));
        $this->assertTrue($account->is_active);
        $this->assertTrue($account->must_change_password);
        $this->assertTrue(Hash::check('Teacher@2026', $account->password));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'staff.login_created',
            'school_id' => $school->id,
            'user_id' => $admin->id,
        ]);

        $this->post(route('logout'));

        $this->post(route('login'), [
            'role' => 'teacher',
            'mobile_number' => '01710000011',
            'password' => 'Teacher@2026',
        ])->assertRedirect(route('profile.password.change'));

        $this->assertAuthenticatedAs($account);
    }

    public function test_a_teacher_login_cannot_reuse_another_accounts_mobile_number(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->makeUser($school, 'teacher', ['mobile_number' => '01710000012']);

        $payload = [
            'first_name' => 'Loginless',
            'gender' => 'female',
            'phone' => '01710000012',
            'designation' => 'Teacher',
            'joining_date' => '2026-01-05',
            'employment_type' => 'full_time',
            'status' => 'active',
            'password' => 'Teacher@2026',
            'password_confirmation' => 'Teacher@2026',
        ];

        $this->actingAs($admin)
            ->post(route('staff.teachers.store'), $payload)
            ->assertSessionHasErrors('phone');

        // Without a password no login is created, so the number may be shared.
        $this->actingAs($admin)
            ->post(route('staff.teachers.store'), Arr::except($payload, ['password', 'password_confirmation']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Teacher::query()->where('first_name', 'Loginless')->count());
    }

    public function test_a_teacher_can_be_added_without_a_login_or_email(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('staff.teachers.store'), [
                'first_name' => 'NoLogin',
                'gender' => 'male',
                'phone' => '01710000013',
                'designation' => 'Teacher',
                'joining_date' => '2026-01-05',
                'employment_type' => 'full_time',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $teacher = Teacher::query()->where('first_name', 'NoLogin')->firstOrFail();

        $this->assertNull($teacher->user_id);
    }

    public function test_employee_records_use_the_staff_form(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('staff.employees.store'), [
                'first_name' => 'Mizan',
                'last_name' => 'Ahmed',
                'gender' => 'male',
                'phone' => '01710000002',
                'designation' => 'Accountant',
                'department' => 'Accounts',
                'joining_date' => '2026-02-01',
                'employment_type' => 'full_time',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $employee = Employee::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^EMP-2026-\d{4}$/', $employee->employee_code);
        $this->actingAs($admin)->get(route('staff.index', ['type' => 'employees']))->assertOk()->assertSee('Mizan');
    }

    public function test_teacher_role_cannot_open_the_staff_directory(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $principal = $this->makeUser($school, 'principal');

        $this->actingAs($teacher)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($principal)->get(route('staff.index'))->assertOk();
        $this->actingAs($principal)->get(route('staff.leaves.index'))->assertOk();
        $this->actingAs($principal)->get(route('staff.salary.index'))->assertOk();
    }

    public function test_staff_profiles_are_not_visible_across_schools(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $adminA = $this->makeUser($schoolA, 'school_admin');

        $this->useSchoolContext($schoolB->id);
        $teacherB = Teacher::factory()->create();

        $this->actingAs($adminA)
            ->get(route('staff.teachers.show', $teacherB))
            ->assertNotFound();
    }

    public function test_overlapping_leave_is_rejected_and_approval_is_audited(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeTeacher($school);

        $this->actingAs($admin)->post(route('staff.leave-types.store'), [
            'name' => 'Sick leave',
            'code' => 'SL',
            'max_days_per_year' => 10,
            'is_paid' => 1,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $leaveType = LeaveType::query()->firstOrFail();

        $this->actingAs($admin)->get(route('staff.leaves.index'))->assertOk()->assertSee('Sick leave');

        $this->actingAs($admin)->post(route('staff.leaves.store'), [
            'leave_type_id' => $leaveType->id,
            'teacher_id' => $teacher->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-04',
            'reason' => 'Flu',
        ])->assertSessionHasNoErrors();

        $leave = StaffLeave::query()->firstOrFail();
        $this->assertSame('pending', $leave->status);
        $this->assertSame('3.0', (string) $leave->total_days);

        $this->actingAs($admin)->post(route('staff.leaves.store'), [
            'leave_type_id' => $leaveType->id,
            'teacher_id' => $teacher->id,
            'start_date' => '2026-03-04',
            'end_date' => '2026-03-05',
            'reason' => 'Still sick',
        ])->assertSessionHasErrors('start_date');

        $this->actingAs($admin)
            ->post(route('staff.leaves.approve', $leave))
            ->assertSessionHasNoErrors();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($admin->id, $leave->approved_by);
        $this->assertNotNull($leave->approved_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'staff_leave.approved',
            'auditable_id' => $leave->id,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->post(route('staff.leaves.approve', $leave))
            ->assertSessionHasErrors('status');
    }

    public function test_an_employee_leave_can_be_rejected_with_a_reason(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->useSchoolContext($school->id);
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-2026-0009',
            'first_name' => 'Rokeya',
            'gender' => 'female',
            'phone' => '01710000003',
            'designation' => 'Clerk',
            'joining_date' => '2025-06-01',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);
        $leaveType = LeaveType::query()->create([
            'name' => 'Casual leave',
            'code' => 'CL',
            'max_days_per_year' => 12,
            'is_paid' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('staff.leaves.store'), [
            'leave_type_id' => $leaveType->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-01',
            'reason' => 'Family event',
        ])->assertSessionHasNoErrors();

        $leave = StaffLeave::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('staff.leaves.reject', $leave), ['reason' => 'Exam week'])
            ->assertSessionHasNoErrors();

        $leave->refresh();
        $this->assertSame('rejected', $leave->status);
        $this->assertSame('Exam week', $leave->rejection_reason);
        $this->assertSame($employee->id, $leave->employee_id);
    }

    public function test_a_new_active_salary_structure_closes_the_previous_one(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeTeacher($school);

        $this->actingAs($admin)->post(route('staff.salary.store'), [
            'teacher_id' => $teacher->id,
            'basic_salary' => 30000,
            'house_allowance' => 5000,
            'transport_allowance' => 1000,
            'effective_from' => '2026-01-01',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $first = SalaryStructure::query()->orderBy('id')->firstOrFail();
        $this->assertSame('36000.00', (string) $first->gross_salary);
        $this->assertTrue($first->is_active);

        $this->actingAs($admin)->post(route('staff.salary.store'), [
            'teacher_id' => $teacher->id,
            'basic_salary' => 32000,
            'house_allowance' => 5000,
            'effective_from' => '2026-07-01',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $first->refresh();
        $second = SalaryStructure::query()->orderByDesc('id')->firstOrFail();

        $this->assertFalse($first->is_active);
        $this->assertSame('2026-06-30', $first->effective_to?->toDateString());
        $this->assertSame('36000.00', (string) $first->gross_salary);
        $this->assertSame('37000.00', (string) $second->gross_salary);
        $this->assertTrue($second->is_active);

        $this->actingAs($admin)
            ->get(route('staff.salary.index', ['edit' => $first->id]))
            ->assertOk()
            ->assertSee('Edit salary structure');
    }

    public function test_a_salary_structure_referenced_by_payments_cannot_be_deleted(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeTeacher($school);

        $this->useSchoolContext($school->id);
        $structure = SalaryStructure::query()->create([
            'teacher_id' => $teacher->id,
            'basic_salary' => 30000,
            'gross_salary' => 30000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        DB::table('salary_payments')->insert([
            'school_id' => $school->id,
            'salary_structure_id' => $structure->id,
            'teacher_id' => $teacher->id,
            'salary_month' => '2026-01-01',
            'basic_salary' => 30000,
            'allowances_total' => 0,
            'deductions_total' => 0,
            'gross_salary' => 30000,
            'net_salary' => 30000,
            'payment_method' => 'cash',
            'paid_on' => '2026-01-31',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('staff.salary.destroy', $structure))
            ->assertSessionHasErrors('structure');

        $this->assertDatabaseHas('salary_structures', ['id' => $structure->id]);
    }

    public function test_an_admin_can_assign_classes_while_adding_a_teacher(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $six = $this->makeClass($school, 'Six', 6);
        $seven = $this->makeClass($school, 'Seven', 7);

        $this->actingAs($admin)->post(route('staff.teachers.store'), [
            'first_name' => 'Rehana',
            'last_name' => 'Parvin',
            'gender' => 'female',
            'phone' => '01710000009',
            'designation' => 'Teacher',
            'joining_date' => '2026-02-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'class_ids' => [$six->id, $seven->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $teacher = Teacher::query()->where('first_name', 'Rehana')->firstOrFail();

        $this->assertSame([$six->id, $seven->id], $teacher->classes()->pluck('classes.id')->all());
        $this->assertDatabaseHas('class_teachers', [
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'class_id' => $seven->id,
        ]);

        $this->actingAs($admin)
            ->get(route('staff.teachers.show', $teacher))
            ->assertOk()
            ->assertSee('Seven');
    }

    public function test_updating_a_teacher_replaces_the_assigned_classes(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $six = $this->makeClass($school, 'Six', 6);
        $seven = $this->makeClass($school, 'Seven', 7);
        $eight = $this->makeClass($school, 'Eight', 8);
        $teacher = $this->makeTeacher($school);

        $attributes = [
            'first_name' => 'Kabul',
            'gender' => 'male',
            'phone' => '01710000011',
            'designation' => 'Teacher',
            'joining_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
        ];

        $this->actingAs($admin)
            ->put(route('staff.teachers.update', $teacher), $attributes + ['class_ids' => [$six->id, $seven->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$six->id, $seven->id], $teacher->classes()->pluck('classes.id')->all());

        $this->actingAs($admin)
            ->put(route('staff.teachers.update', $teacher), $attributes + ['class_ids' => [$eight->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$eight->id], $teacher->classes()->pluck('classes.id')->all());
        $this->assertDatabaseMissing('class_teachers', ['teacher_id' => $teacher->id, 'class_id' => $six->id]);
    }

    public function test_a_class_belonging_to_another_school_cannot_be_assigned(): void
    {
        $school = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeTeacher($school);
        $foreign = $this->makeClass($other, 'Nine', 9);

        $this->actingAs($admin)->put(route('staff.teachers.update', $teacher), [
            'first_name' => 'Kabul',
            'gender' => 'male',
            'phone' => '01710000012',
            'designation' => 'Teacher',
            'joining_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'class_ids' => [$foreign->id],
        ])->assertSessionHasErrors('class_ids.0');

        $this->assertDatabaseCount('class_teachers', 0);
    }

    public function test_the_class_list_shows_only_this_schools_classes(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->makeClass($school, 'Five', 5);
        $foreign = $this->makeClass($this->makeSchool(), 'Nine', 9);

        $this->actingAs($admin)
            ->get(route('staff.teachers.create'))
            ->assertOk()
            ->assertSee('Five')
            ->assertDontSee($foreign->name);
    }

    private function makeClass($school, string $name, int $level): SchoolClass
    {
        $this->useSchoolContext($school->id);

        return SchoolClass::query()->create([
            'name' => $name,
            'level' => $level,
            'is_active' => true,
        ]);
    }

    private function makeTeacher($school): Teacher
    {
        $this->useSchoolContext($school->id);

        return Teacher::factory()->create();
    }
}
