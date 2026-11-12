<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\CashTransaction;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeReceipt;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountantFeeCollectionService;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class FeeManualInvoiceTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_generates_one_manual_invoice_per_period(): void
    {
        [$admin,, $student] = $this->fixture();

        $invoice = app(FeeInvoiceService::class)->generateForStudent($admin->fresh(), [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1234.50,
        ]);

        $this->assertSame('issued', $invoice->status);
        $this->assertSame('2026-10', $invoice->billing_period);
        $this->assertSame('1234.50', $invoice->total);
        $this->assertSame('0.00', $invoice->paid_total);
        $this->assertSame('1234.50', $invoice->due_total);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame($this->tuition->id, $invoice->items()->first()->fee_type_id);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{4}$/', $invoice->invoice_no);

        $next = app(FeeInvoiceService::class)->generateForStudent($admin->fresh(), [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 11,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
        ]);

        $this->assertSame('2026-11', $next->billing_period);
        $this->assertNotSame($invoice->invoice_no, $next->invoice_no);
    }

    public function test_duplicate_fee_type_for_same_student_session_and_period_is_rejected(): void
    {
        [$admin,, $student] = $this->fixture();

        $service = app(FeeInvoiceService::class);
        $payload = [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
        ];

        $service->generateForStudent($admin, $payload);

        $this->expectException(ValidationException::class);

        try {
            $service->generateForStudent($admin, $payload);
        } finally {
            $invoice = FeeInvoice::query()->firstOrFail();
            $this->assertSame(1, $invoice->items()->where('fee_type_id', $this->tuition->id)->count());
            $this->assertSame('1000.00', $invoice->total);
        }
    }

    public function test_a_second_fee_type_joins_the_unpaid_invoice_for_the_same_month(): void
    {
        [$admin,, $student] = $this->fixture();

        $service = app(FeeInvoiceService::class);

        $service->generateForStudent($admin, [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
        ]);

        $transport = FeeType::query()->create([
            'name' => 'Transport Fee',
            'code' => 'TRN',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $invoice = $service->generateForStudent($admin, [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $transport->id,
            'amount' => 500,
        ]);

        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame('1500.00', $invoice->fresh()->total);
        $this->assertSame('issued', $invoice->status);
    }

    public function test_overpayment_and_duplicate_payment_are_refused(): void
    {
        [$admin, $accountant, $student] = $this->fixture();

        $invoice = app(FeeInvoiceService::class)->generateForStudent($admin, [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
        ]);

        app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'manual-partial-1',
        ]);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);

        $this->expectException(ValidationException::class);

        app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 1600,
            'method' => 'cash',
            'paid_at' => '2026-10-06 10:00:00',
            'idempotency_key' => 'manual-overpay-1',
        ]);
    }

    public function test_unpaid_invoice_is_editable_then_frozen_after_payment(): void
    {
        [$admin, $accountant, $student] = $this->fixture();

        $service = app(FeeInvoiceService::class);
        $invoice = $service->generateForStudent($admin, [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
        ]);

        $item = $invoice->items()->firstOrFail();

        $service->updateItem($admin->fresh(), $invoice, $item, [
            'description' => 'Tuition (October 2026, corrected)',
            'amount' => 1200,
        ]);

        $invoice->refresh();
        $line = $invoice->items()->firstOrFail();

        $this->assertSame('1200.00', $invoice->total);
        $this->assertSame('1200.00', $invoice->due_total);
        $this->assertSame('1200.00', $line->line_total);
        $this->assertSame('Tuition (October 2026, corrected)', $line->description);

        app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 1200,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'manual-edit-2',
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);

        $this->expectException(ValidationException::class);

        $service->updateItem($admin->fresh(), $invoice, $invoice->items()->firstOrFail(), [
            'description' => 'Frozen',
            'amount' => 500,
        ]);
    }

    public function test_accountant_collects_full_and_partial_payment_on_a_manual_invoice(): void
    {
        [$admin, $accountant, $student] = $this->fixture();

        app(FeeInvoiceService::class)->generateForStudent($admin, [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
        ]);

        $counter = app(AccountantFeeCollectionService::class);

        $base = [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $this->tuition->id,
            'month' => 10,
            'method' => 'cash',
        ];

        $partial = $counter->collect($accountant->fresh(), $base + [
            'amount' => 400,
            'paid_at' => '2026-10-06 10:00:00',
            'idempotency_key' => 'manual-counter-1',
        ]);

        $this->assertSame('posted', $partial->status);
        $this->assertSame(1, FeeReceipt::query()->count());

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('400.00', $invoice->fresh()->paid_total);
        $this->assertSame('600.00', $invoice->fresh()->due_total);

        $replay = $counter->collect($accountant->fresh(), $base + [
            'amount' => 400,
            'paid_at' => '2026-10-06 10:00:00',
            'idempotency_key' => 'manual-counter-1',
        ]);

        $this->assertSame($partial->id, $replay->id);
        $this->assertSame(1, FeePayment::query()->count());

        $counter->collect($accountant->fresh(), $base + [
            'amount' => 600,
            'paid_at' => '2026-10-07 10:00:00',
            'idempotency_key' => 'manual-counter-2',
        ]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', $invoice->due_total);
        $this->assertSame(2, FeeReceipt::query()->count());
        $this->assertSame(2, CashTransaction::query()->whereNull('reversal_of_id')->count());

        $this->expectException(ValidationException::class);

        $counter->collect($accountant->fresh(), $base + [
            'amount' => 100,
            'paid_at' => '2026-10-08 10:00:00',
            'idempotency_key' => 'manual-counter-3',
        ]);
    }

    public function test_the_admin_http_form_issues_an_invoice(): void
    {
        [$admin,, $student] = $this->fixture();

        $this->actingAs($admin);

        $this->get(route('fees.single'))->assertOk();
        $this->get(route('fees.single', ['student' => $student->id, 'session_id' => $this->session->id]))->assertOk();

        $this->post(route('fees.single.store'), [
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'month' => 10,
            'fee_type_id' => $this->tuition->id,
            'amount' => '900',
        ])->assertRedirect();

        $this->assertSame(1, FeeInvoice::query()->count());

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('900.00', $invoice->total);
        $this->assertSame('issued', $invoice->status);
    }

    /**
     * @return array{0: User, 1: User, 2: Student}
     */
    private function fixture(): array
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $accountant = $this->makeUser($school, 'accountant');

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

        $this->tuition = FeeType::query()->create([
            'name' => 'Tuition Fee',
            'code' => 'TUI',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $student = Student::factory()->create();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$admin, $accountant, $student];
    }
}
