<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeReceipt;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class FeeBillingTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private FeeType $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_invoices_are_generated_and_payments_are_idempotent_and_reversible(): void
    {
        [, $accountant, $students] = $this->billingFixture(2);

        $result = app(FeeInvoiceService::class)->generate($accountant, [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ]);

        $this->assertCount(2, $result['invoices']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2000.0, $result['total']);
        $this->assertSame(2, FeeInvoice::query()->count());
        $this->assertSame(2, FeeInvoice::query()->distinct()->count('invoice_no'));
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_invoices.generated')->count());

        $invoice = FeeInvoice::query()->orderBy('id')->firstOrFail();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('1000.00', $invoice->total);
        $this->assertSame('1000.00', $invoice->due_total);
        $this->assertSame(1, $invoice->items()->count());

        $paymentService = app(FeePaymentService::class);

        $payload = [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'fee-test-payment-1',
        ];

        $payment = $paymentService->collect($accountant, $invoice, $payload);
        $replay = $paymentService->collect($accountant, $invoice, $payload);

        $this->assertSame($payment->id, $replay->id);
        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(1, FeeReceipt::query()->count());
        $this->assertSame(1, CashTransaction::query()->whereNull('reversal_of_id')->count());
        $this->assertSame('posted', $payment->status);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('600.00', $invoice->fresh()->due_total);
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_payment.posted')->count());

        $paymentService->collect($accountant, $invoice, [
            'amount' => 600,
            'method' => 'bkash',
            'transaction_ref' => 'BKASH-100',
            'paid_at' => '2026-10-06 10:00:00',
            'idempotency_key' => 'fee-test-payment-2',
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->due_total);

        $paymentService->reverse($accountant, $payment, 'Test reversal');

        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('400.00', $invoice->fresh()->due_total);
        $this->assertSame(1, CashTransaction::query()->whereNotNull('reversal_of_id')->count());
        $this->assertSame(2, FeeReceipt::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'fee_payment.reversed')->count());
        $this->assertSame(2, $students->count());
    }

    public function test_duplicate_billing_period_is_rejected_without_creating_invoices(): void
    {
        [, $accountant] = $this->billingFixture(1);

        $payload = [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ];

        $service = app(FeeInvoiceService::class);
        $service->generate($accountant, $payload);

        try {
            $service->generate($accountant, $payload);
            $this->fail('A duplicate billing period should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('class_id', $exception->errors());
        }

        $this->assertSame(1, FeeInvoice::query()->count());
    }

    public function test_cancelled_invoices_cannot_receive_payments(): void
    {
        [$admin, $accountant] = $this->billingFixture(1);

        $result = app(FeeInvoiceService::class)->generate($accountant, [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ]);

        $invoice = $result['invoices'][0];
        app(FeeInvoiceService::class)->cancel($accountant, $invoice);

        $this->expectException(ValidationException::class);

        app(FeePaymentService::class)->collect($admin, $invoice->fresh(), [
            'amount' => 100,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'fee-cancelled-1',
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Collection<int, Student>}
     */
    private function billingFixture(int $studentCount): array
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

        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $this->tuition->id,
            'name' => 'Monthly tuition',
            'amount' => 1000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $students = collect();

        for ($index = 1; $index <= $studentCount; $index++) {
            $student = Student::factory()->create();
            $students->push($student);

            Enrollment::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'roll_no' => $index,
                'enrolled_on' => '2026-01-02',
                'status' => 'active',
            ]);
        }

        return [$admin, $accountant, $students];
    }
}
