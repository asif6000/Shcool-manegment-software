<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\CashTransaction;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SalaryPayment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AccountantPurgeService;
use App\Services\CashLedgerService;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The accountant desk: one login per mobile number, created and maintained by
 * the office without an email address anywhere in the flow.
 */
class AccountantManagementTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);
        $this->admin = $this->makeUser($this->school, 'school_admin');
    }

    public function test_an_accountant_is_created_with_a_name_mobile_and_password_only(): void
    {
        $accountant = $this->createAccountant(['name' => 'Sumon']);

        $this->assertSame('Sumon', $accountant->name);
        $this->assertSame('01744000041', $accountant->mobile_number);
        $this->assertNull($accountant->email);
        $this->assertNull($accountant->phone);
        $this->assertTrue($accountant->hasRole('accountant'));
        $this->assertTrue($accountant->is_active);
        $this->assertTrue($accountant->must_change_password);

        // The roster lists the new login by its mobile number.
        $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSee('Sumon')
            ->assertSee('01744000041');
    }

    public function test_the_admin_can_edit_the_name_and_the_sign_in_number(): void
    {
        $accountant = $this->createAccountant();

        $this->actingAs($this->admin)
            ->get(route('accountants.edit', $accountant))
            ->assertOk()
            ->assertSee('Finance Desk')
            ->assertSee('name="mobile_number"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="role"', false);

        $this->actingAs($this->admin)
            ->put(route('accountants.update', $accountant), [
                'name' => 'Finance Desk Two',
                'mobile_number' => '+880 1744-000099',
            ])
            ->assertRedirect(route('accountants.index'))
            ->assertSessionHasNoErrors();

        $accountant->refresh();
        $this->assertSame('Finance Desk Two', $accountant->name);
        $this->assertSame('01744000099', $accountant->mobile_number);
        $this->assertTrue($accountant->hasRole('accountant'));
        $this->assertNull($accountant->email);

        // The edited number is the one that now opens the desk.
        $this->post(route('logout'));
        $this->signIn('01744000099', 'Finance@2026')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($accountant);
    }

    public function test_a_duplicate_mobile_number_is_refused(): void
    {
        $this->createAccountant();

        $this->actingAs($this->admin)
            ->post(route('accountants.store'), [
                'name' => 'Second Desk',
                'mobile_number' => '01744000041',
                'password' => 'Finance@2026',
                'password_confirmation' => 'Finance@2026',
            ])
            ->assertSessionHasErrors('mobile_number');

        $existing = $this->makeUser($this->school, 'accountant', ['mobile_number' => '01744000077']);
        $other = $this->createAccountant([
            'name' => 'Third Desk',
            'mobile_number' => '01744000088',
        ]);

        // An edit cannot steal a number that already belongs to another login.
        $this->actingAs($this->admin)
            ->put(route('accountants.update', $other), [
                'name' => 'Third Desk',
                'mobile_number' => $existing->mobile_number,
            ])
            ->assertSessionHasErrors('mobile_number');

        $this->assertSame(1, User::query()->where('mobile_number', '01744000041')->count());
        $this->assertSame('01744000088', $other->refresh()->mobile_number);
    }

    public function test_a_deactivated_accountant_is_signed_out_and_cannot_return_until_reactivated(): void
    {
        $accountant = $this->createAccountant();
        $this->post(route('logout'));

        $this->actingAs($this->admin)
            ->patch(route('accountants.status', $accountant))
            ->assertSessionHasNoErrors();

        $this->assertFalse($accountant->refresh()->is_active);

        $this->post(route('logout'));
        $this->signIn($accountant->mobile_number, 'Finance@2026')
            ->assertSessionHasErrors('mobile_number');
        $this->assertGuest();

        $this->actingAs($this->admin)
            ->patch(route('accountants.status', $accountant))
            ->assertSessionHasNoErrors();

        $this->assertTrue($accountant->refresh()->is_active);

        $this->post(route('logout'));
        $this->signIn($accountant->mobile_number, 'Finance@2026')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($accountant);
    }

    public function test_the_admin_resets_the_password_and_the_old_one_stops_working(): void
    {
        $accountant = $this->createAccountant(['name' => 'Finance Desk']);
        $this->post(route('logout'));

        $this->actingAs($this->admin)
            ->post(route('accountants.password', $accountant), [
                'password' => 'NewSecret2026',
                'password_confirmation' => 'NewSecret2026',
            ])
            ->assertSessionHasNoErrors();

        $accountant->refresh();
        $this->assertTrue($accountant->must_change_password);

        $this->post(route('logout'));

        $this->signIn($accountant->mobile_number, 'Finance@2026')
            ->assertSessionHasErrors('mobile_number');
        $this->assertGuest();

        $this->signIn($accountant->mobile_number, 'NewSecret2026')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($accountant);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $accountant = $this->createAccountant();
        $this->post(route('logout'));

        $this->signIn($accountant->mobile_number, 'NotThePassword1')
            ->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_the_office_urls_refuse_a_teacher_and_another_accountant(): void
    {
        $accountant = $this->createAccountant();
        $colleague = $this->createAccountant([
            'name' => 'Second Desk',
            'mobile_number' => '01744000055',
        ]);

        $teacher = $this->makeUser($this->school, 'teacher');

        // A fresh login is forced through the password-change screen first;
        // clear that flag so the request reaches the authorization check.
        $accountant->forceFill(['must_change_password' => false])->save();

        $this->actingAs($teacher)->get(route('accountants.create'))->assertForbidden();
        $this->actingAs($teacher)->get(route('accountants.edit', $accountant))->assertForbidden();

        $this->actingAs($accountant)->get(route('accountants.edit', $colleague))->assertForbidden();
        $this->actingAs($accountant)
            ->put(route('accountants.update', $colleague), [
                'name' => 'Hijacked',
                'mobile_number' => '01744000066',
            ])
            ->assertForbidden();
        $this->actingAs($accountant)
            ->patch(route('accountants.status', $colleague))
            ->assertForbidden();
        $this->actingAs($accountant)
            ->post(route('accountants.password', $colleague), [
                'password' => 'Hijacked2026',
                'password_confirmation' => 'Hijacked2026',
            ])
            ->assertForbidden();

        $this->assertSame('Second Desk', $colleague->refresh()->name);
        $this->assertSame('01744000055', $colleague->mobile_number);
        $this->assertTrue($colleague->is_active);
    }

    public function test_purging_an_accountant_removes_every_record_in_their_name_and_writes_off_what_they_collected(): void
    {
        Storage::fake('local');
        Storage::fake('school_uploads');

        [$doomed, $colleague, $invoices] = $this->collectionFixture();

        $doomedPaymentIds = FeePayment::query()->where('received_by', $doomed->id)->pluck('id')->all();
        $keptPayment = FeePayment::query()->where('received_by', $colleague->id)->sole();

        $this->assertCount(2, $doomedPaymentIds);

        $studentIds = FeePayment::query()
            ->where('received_by', $doomed->id)
            ->distinct()
            ->pluck('student_id')
            ->all();

        $teacher = $this->makeUser($this->school, 'teacher');

        // Staff profile and the attendance, leave and payroll rows that would
        // otherwise block the employee row from being deleted.
        $employeeId = DB::table('employees')->insertGetId([
            'school_id' => $this->school->id,
            'user_id' => $doomed->id,
            'employee_code' => 'EMP-PURGE',
            'first_name' => 'Doomed',
            'last_name' => 'Desk',
            'gender' => 'female',
            'phone' => $doomed->mobile_number,
            'designation' => 'Accountant',
            'joining_date' => '2026-01-01',
            'status' => 'active',
            'photo_path' => 'teachers/purge-photo.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('staff_attendances')->insert([
            'school_id' => $this->school->id,
            'employee_id' => $employeeId,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leaveTypeId = DB::table('leave_types')->insertGetId([
            'school_id' => $this->school->id,
            'name' => 'Casual',
            'code' => 'CAS',
            'is_paid' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('staff_leaves')->insert([
            'school_id' => $this->school->id,
            'leave_type_id' => $leaveTypeId,
            'employee_id' => $employeeId,
            'start_date' => '2026-10-06',
            'end_date' => '2026-10-06',
            'total_days' => 1,
            'reason' => 'Personal',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_structures')->insert([
            'school_id' => $this->school->id,
            'employee_id' => $employeeId,
            'basic_salary' => 900,
            'gross_salary' => 900,
            'effective_from' => '2026-01-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salaryPaymentId = DB::table('salary_payments')->insertGetId([
            'school_id' => $this->school->id,
            'employee_id' => $employeeId,
            'salary_month' => '2026-10-01',
            'basic_salary' => 900,
            'allowances_total' => 0,
            'deductions_total' => 0,
            'gross_salary' => 900,
            'net_salary' => 900,
            'payment_method' => 'cash',
            'paid_on' => '2026-10-31',
            'status' => 'paid',
            'paid_by' => $doomed->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CashLedgerService::class)->postExpense(
            $doomed,
            900,
            '2026-10-31',
            'Salary paid to the doomed accountant',
            SalaryPayment::query()->findOrFail($salaryPaymentId),
        );

        $categoryId = DB::table('expense_categories')->insertGetId([
            'school_id' => $this->school->id,
            'name' => 'Office',
            'code' => 'OFF',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseId = DB::table('expenses')->insertGetId([
            'school_id' => $this->school->id,
            'expense_category_id' => $categoryId,
            'expense_no' => 'EXP-PURGE-1',
            'title' => 'Purge expense',
            'amount' => 100,
            'expense_date' => '2026-10-07',
            'payment_method' => 'cash',
            'status' => 'paid',
            'created_by' => $doomed->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CashLedgerService::class)->postExpense(
            $doomed,
            100,
            '2026-10-07',
            'Purge expense',
            Expense::query()->findOrFail($expenseId),
        );

        $voucherId = DB::table('vouchers')->insertGetId([
            'school_id' => $this->school->id,
            'voucher_no' => 'VCH-PURGE-1',
            'type' => 'expense',
            'voucher_date' => '2026-10-08',
            'amount' => 50,
            'channel' => 'cash',
            'description' => 'Purge voucher',
            'status' => 'posted',
            'created_by' => $doomed->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CashLedgerService::class)->postExpense(
            $doomed,
            50,
            '2026-10-08',
            'Purge voucher',
            Voucher::query()->findOrFail($voucherId),
        );

        Storage::disk('school_uploads')->put('avatars/purge-avatar.png', 'avatar');
        $doomed->forceFill(['avatar_path' => 'avatars/purge-avatar.png'])->save();

        DB::table('sessions')->insert([
            'id' => 'purge-session',
            'user_id' => $doomed->id,
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $doomed->createToken('purge-test');

        DB::table('notification_preferences')->insert([
            'user_id' => $doomed->id,
            'category' => 'fee',
            'channel' => 'webpush',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WelcomeNotification',
            'notifiable_type' => $doomed->getMorphClass(),
            'notifiable_id' => $doomed->id,
            'data' => json_encode(['message' => 'Welcome']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('device_push_tokens')->insert([
            'user_id' => $doomed->id,
            'platform' => 'android',
            'token' => 'purge-token',
            'token_hash' => hash('sha256', 'purge-token'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sms_logs')->insert([
            'school_id' => $this->school->id,
            'user_id' => $doomed->id,
            'phone' => '01744000041',
            'message' => 'Fee reminder',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The edit screen states the full impact before the admin commits.
        $preview = app(AccountantPurgeService::class)->preview($doomed);

        $this->assertSame(2, $preview['receipts']);
        $this->assertSame(1, $preview['expenses']);
        $this->assertGreaterThanOrEqual(5, $preview['cash']);
        $this->assertGreaterThan(
            $preview['receipts'] + $preview['expenses'] + $preview['cash'],
            $preview['total'],
        );

        $this->actingAs($this->admin)
            ->get(route('accountants.edit', $doomed))
            ->assertOk()
            ->assertSee('Permanently delete accountant')
            ->assertSee('DELETE ALL')
            ->assertSee('name="admin_password"', false)
            ->assertSee('name="delete_confirmation"', false)
            ->assertSee('Total related records to be deleted')
            ->assertSee(number_format($preview['total']))
            ->assertSee('money the school already received')
            ->assertSee($doomed->mobile_number);

        $this->actingAs($this->admin)
            ->delete(route('accountants.destroy', $doomed), [
                'admin_password' => 'password',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertRedirect(route('accountants.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // The login, profile and every relationship that does not cascade.
        $this->assertDatabaseMissing('users', ['id' => $doomed->id]);
        $this->assertDatabaseMissing('employees', ['id' => $employeeId]);
        $this->assertDatabaseMissing('staff_attendances', ['employee_id' => $employeeId]);
        $this->assertDatabaseMissing('staff_leaves', ['employee_id' => $employeeId]);
        $this->assertDatabaseMissing('salary_structures', ['employee_id' => $employeeId]);
        $this->assertDatabaseMissing('salary_payments', ['id' => $salaryPaymentId]);
        $this->assertSame(0, DB::table('model_has_roles')
            ->where('model_type', $doomed->getMorphClass())
            ->where('model_id', $doomed->id)
            ->count());

        $this->assertDatabaseMissing('sessions', ['user_id' => $doomed->id]);
        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $doomed->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $doomed->id]);
        $this->assertDatabaseMissing('device_push_tokens', ['user_id' => $doomed->id]);
        $this->assertDatabaseMissing('sms_logs', ['user_id' => $doomed->id]);
        $this->assertSame(0, DB::table('personal_access_tokens')
            ->where('tokenable_type', $doomed->getMorphClass())
            ->where('tokenable_id', $doomed->id)
            ->count());

        // Financial rows posted in this accountant's name.
        foreach ($doomedPaymentIds as $paymentId) {
            $this->assertDatabaseMissing('fee_payments', ['id' => $paymentId]);
            $this->assertDatabaseMissing('fee_receipts', ['fee_payment_id' => $paymentId]);
            $this->assertDatabaseMissing('fee_payment_allocations', ['fee_payment_id' => $paymentId]);
        }

        // Nothing is left pointing at the deleted login.
        $this->assertSame(0, DB::table('fee_payments')->where('received_by', $doomed->id)->count());
        $this->assertSame(0, DB::table('fee_receipts')->where('issued_by', $doomed->id)->count());
        $this->assertSame(0, DB::table('expenses')->where('created_by', $doomed->id)->count());

        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
        $this->assertDatabaseMissing('vouchers', ['id' => $voucherId]);
        $this->assertSame(0, CashTransaction::query()->where('created_by', $doomed->id)->count());

        // The money the accountant had received stays cleared: it moves from
        // paid_total to waived_total, so no outstanding due reopens. The
        // colleague's 200 on the shared invoice stays as collected money.
        $shared = $invoices[0]->fresh();
        $this->assertSame('200.00', $shared->paid_total);
        $this->assertSame('300.00', $shared->waived_total);
        $this->assertSame('0.00', $shared->due_total);
        $this->assertSame('settled', $shared->status);

        $onlyTheirs = $invoices[1]->fresh();
        $this->assertSame('0.00', $onlyTheirs->paid_total);
        $this->assertSame('500.00', $onlyTheirs->waived_total);
        $this->assertSame('0.00', $onlyTheirs->due_total);
        $this->assertSame('settled', $onlyTheirs->status);

        // Neither invoice comes back on the Outstanding dues screen.
        $this->actingAs($this->admin)
            ->get(route('fees.dues'))
            ->assertOk()
            ->assertSeeText(__('No outstanding dues'));

        // Another accountant and their money are untouched.
        $this->assertDatabaseHas('users', ['id' => $colleague->id]);
        $this->assertDatabaseHas('fee_payments', ['id' => $keptPayment->id]);
        $this->assertDatabaseHas('fee_receipts', ['fee_payment_id' => $keptPayment->id]);
        $this->assertDatabaseHas('cash_transactions', [
            'source_type' => (new FeePayment)->getMorphClass(),
            'source_id' => $keptPayment->id,
            'created_by' => $colleague->id,
        ]);

        // The students who paid and the unrelated teacher keep their records.
        foreach ($studentIds as $studentId) {
            $this->assertDatabaseHas('students', ['id' => $studentId]);
        }

        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        // Files and the pre-delete backup.
        Storage::disk('school_uploads')->assertMissing('avatars/purge-avatar.png');
        Storage::disk('school_uploads')->assertMissing('teachers/purge-photo.png');

        $backups = Storage::disk('local')->files('backups/accountant-purge');
        $this->assertCount(1, $backups);

        $backup = json_decode((string) Storage::disk('local')->get($backups[0]), true);
        $this->assertSame($doomed->name, $backup['accountant']['name']);
        $this->assertSame($doomed->mobile_number, $backup['accountant']['mobile_number']);
        $this->assertCount(2, $backup['rows']['payments']);
        $this->assertCount(2, $backup['rows']['receipts']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'accountant.purged',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_purging_an_accountant_keeps_a_balance_they_never_collected_payable(): void
    {
        Storage::fake('local');
        Storage::fake('school_uploads');

        [$doomed] = $this->collectionFixture();

        $session = AcademicSession::query()->where('name', '2026')->sole();
        $class = SchoolClass::query()->where('name', 'Class Six')->sole();
        $section = Section::query()->where('class_id', $class->id)->sole();

        $november = app(FeeInvoiceService::class)->generate($this->admin, [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'fee_type_ids' => [FeeType::query()->where('code', 'TUI')->sole()->id],
            'billing_period' => '2026-11',
            'issue_date' => '2026-11-01',
            'due_date' => '2026-11-10',
        ])['invoices'];

        app(FeePaymentService::class)->collect($doomed, $november[0], [
            'amount' => 200,
            'method' => 'cash',
            'paid_at' => '2026-11-03 10:00:00',
            'idempotency_key' => 'purge-doomed-partial',
        ]);

        $this->assertSame('300.00', $november[0]->fresh()->due_total);

        $this->actingAs($this->admin)
            ->delete(route('accountants.destroy', $doomed), [
                'admin_password' => 'password',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertRedirect(route('accountants.index'));

        // The 200 the accountant received is written off, while the 300 they
        // never collected stays a real outstanding due.
        $partial = $november[0]->fresh();
        $this->assertSame('0.00', $partial->paid_total);
        $this->assertSame('200.00', $partial->waived_total);
        $this->assertSame('300.00', $partial->due_total);
        $this->assertSame('issued', $partial->status);

        $this->actingAs($this->admin)
            ->get(route('fees.dues'))
            ->assertOk()
            ->assertSeeText('৳ 300.00')
            ->assertDontSeeText(__('No outstanding dues'));
    }

    public function test_the_purge_is_refused_without_the_password_and_the_exact_phrase(): void
    {
        $accountant = $this->createAccountant();

        $this->actingAs($this->admin)
            ->delete(route('accountants.destroy', $accountant), [
                'admin_password' => 'not-my-password',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertSessionHasErrors('admin_password');

        $this->actingAs($this->admin)
            ->delete(route('accountants.destroy', $accountant), [
                'admin_password' => 'password',
                'delete_confirmation' => 'delete all',
            ])
            ->assertSessionHasErrors('delete_confirmation');

        $this->assertDatabaseHas('users', ['id' => $accountant->id]);
    }

    public function test_only_an_authorized_admin_can_purge_an_accountant(): void
    {
        $accountant = $this->createAccountant();
        $colleague = $this->createAccountant([
            'name' => 'Second Desk',
            'mobile_number' => '01744000055',
        ]);

        // Fresh logins are forced through the password screen; clear the flag so
        // the request reaches the authorization check.
        $colleague->forceFill(['must_change_password' => false])->save();
        $teacher = $this->makeUser($this->school, 'teacher');

        $this->actingAs($colleague)
            ->delete(route('accountants.destroy', $accountant), [
                'admin_password' => 'Finance@2026',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete(route('accountants.destroy', $accountant), [
                'admin_password' => 'password',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertForbidden();

        // A non-accountant record cannot be reached through the accountant URLs.
        $this->actingAs($this->admin)
            ->delete(route('accountants.destroy', $teacher), [
                'admin_password' => 'password',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $accountant->id]);
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
    }

    public function test_the_accountant_list_offers_the_same_guarded_delete_dialog(): void
    {
        $accountant = $this->createAccountant();
        $preview = app(AccountantPurgeService::class)->preview($accountant);

        $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSee('data-accountant-delete="'.$accountant->id.'"', false)
            ->assertSee('data-bs-target="#purgeAccountantModal-'.$accountant->id.'"', false)
            ->assertSee('id="purgeAccountantModal-'.$accountant->id.'"', false)
            ->assertSee('name="admin_password"', false)
            ->assertSee('name="delete_confirmation"', false)
            ->assertSeeText('is written off as settled')
            ->assertSee('Total related records to be deleted: '.number_format($preview['total']));
    }

    public function test_a_failed_list_delete_reopens_that_rows_dialog_with_the_gate_error(): void
    {
        $accountant = $this->createAccountant();

        $this->actingAs($this->admin)
            ->from(route('accountants.index'))
            ->delete(route('accountants.destroy', $accountant), [
                'purged_id' => $accountant->id,
                'admin_password' => 'not-my-password',
                'delete_confirmation' => 'DELETE ALL',
            ])
            ->assertRedirect(route('accountants.index'))
            ->assertSessionHasErrors('admin_password');

        // The page reopens the row the admin was working on and shows why the
        // server refused, instead of silently closing the dialog.
        $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSee('const failedId = "'.$accountant->id.'";', false)
            ->assertSee('That password does not match your account.');

        $this->assertDatabaseHas('users', ['id' => $accountant->id]);
    }

    public function test_the_list_delete_action_is_hidden_from_a_viewer_without_delete_rights(): void
    {
        $accountant = $this->createAccountant();
        $viewer = $this->makeUser($this->school, 'teacher');
        $viewer->givePermissionTo('accountants.view');

        $this->actingAs($viewer)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertDontSee('data-accountant-delete', false)
            ->assertDontSee('purgeAccountantModal-'.$accountant->id, false);
    }

    public function test_the_generic_user_and_api_paths_refuse_to_delete_an_accountant(): void
    {
        $accountant = $this->createAccountant();

        $student = Student::factory()->create();

        FeePayment::query()->create([
            'student_id' => $student->id,
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => now(),
            'received_by' => $accountant->id,
            'status' => 'posted',
            'idempotency_key' => 'generic-delete-guard',
        ]);

        // /users would drop only the login row and leave the receipt pointing at
        // nobody, so the web path refuses and points to the purge screen.
        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $accountant), ['permanent_delete' => 'DELETE'])
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $accountant->id]);
        $this->assertDatabaseHas('fee_payments', ['received_by' => $accountant->id]);

        $token = $this->admin->createToken('api-purge-guard')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson(route('api.v1.users.destroy', $accountant))
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $accountant->id]);
        $this->assertDatabaseHas('fee_payments', ['received_by' => $accountant->id]);
    }

    /**
     * Two accountants collecting against the same month: the colleague keeps a
     * 200 payment on an invoice the doomed accountant also paid 300 towards,
     * and the doomed accountant alone settles a second invoice in full.
     *
     * @return array{0: User, 1: User, 2: list<FeeInvoice>}
     */
    private function collectionFixture(): array
    {
        $doomed = $this->createAccountant([
            'name' => 'Doomed Desk',
            'mobile_number' => '01744000091',
        ]);
        $colleague = $this->createAccountant([
            'name' => 'Kept Desk',
            'mobile_number' => '01744000092',
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $class = SchoolClass::query()->create([
            'name' => 'Class Six',
            'level' => 6,
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'class_id' => $class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $tuition = FeeType::query()->create([
            'name' => 'Tuition Fee',
            'code' => 'TUI',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        FeeStructure::query()->create([
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'fee_type_id' => $tuition->id,
            'name' => 'Monthly tuition',
            'amount' => 500,
            'due_day' => 10,
            'is_active' => true,
        ]);

        for ($index = 1; $index <= 2; $index++) {
            $student = Student::factory()->create();

            Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'section_id' => $section->id,
                'roll_no' => $index,
                'enrolled_on' => '2026-01-02',
                'status' => 'active',
            ]);
        }

        $generated = app(FeeInvoiceService::class)->generate($this->admin, [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'fee_type_ids' => [$tuition->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ])['invoices'];

        app(FeePaymentService::class)->collect($doomed, $generated[0], [
            'amount' => 300,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'purge-doomed-shared',
        ]);

        app(FeePaymentService::class)->collect($colleague, $generated[0]->fresh(), [
            'amount' => 200,
            'method' => 'cash',
            'paid_at' => '2026-10-06 10:00:00',
            'idempotency_key' => 'purge-kept-shared',
        ]);

        app(FeePaymentService::class)->collect($doomed, $generated[1], [
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => '2026-10-07 10:00:00',
            'idempotency_key' => 'purge-doomed-full',
        ]);

        return [$doomed, $colleague, $generated];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAccountant(array $overrides = []): User
    {
        $payload = array_merge([
            'name' => 'Finance Desk',
            'mobile_number' => '01744000041',
            'password' => 'Finance@2026',
            'password_confirmation' => 'Finance@2026',
        ], $overrides);

        $this->actingAs($this->admin)
            ->post(route('accountants.store'), $payload)
            ->assertSessionHasNoErrors();

        return User::query()->where('mobile_number', $payload['mobile_number'])->sole();
    }

    private function signIn(string $mobile, string $password): TestResponse
    {
        return $this->post(route('login.store'), [
            'role' => 'accountant',
            'mobile_number' => $mobile,
            'password' => $password,
        ]);
    }
}
