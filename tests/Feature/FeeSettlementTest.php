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
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * A monthly fee of BDT 500 may be collected short - BDT 400 - on purpose. The
 * invoice keeps showing the 500 it was billed at, only the 400 actually taken
 * is recorded and paid into the cashbook, and the difference never becomes a
 * due that follows the student around.
 */
class FeeSettlementTest extends TestCase
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

    public function test_a_settled_short_collection_leaves_no_due_and_books_only_the_cash_received(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant, '2026-10');

        $response = $this->actingAs($accountant)->post(route('fees.payments.store', $invoice), [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'settle-cash-0001',
            'settle_in_full' => '1',
        ]);

        $payment = FeePayment::query()->firstOrFail();
        $response->assertRedirect(route('fees.receipt', $payment));

        $invoice->refresh();

        // The configured standard fee is still what the invoice says.
        $this->assertSame('500.00', $invoice->total);
        $this->assertSame('500.00', $invoice->subtotal);
        $this->assertSame('400.00', $invoice->paid_total);
        $this->assertSame('0.00', $invoice->due_total);
        $this->assertSame('settled', $invoice->status);

        // Only the money received is a payment.
        $this->assertSame('400.00', $payment->amount);

        // Cashbook: exactly one income line of 400, no phantom 100.
        $this->assertSame(1, CashTransaction::query()->where('type', 'income')->count());
        $this->assertSame(400.0, (float) CashTransaction::query()->where('type', 'income')->sum('amount'));

        // Nothing outstanding anywhere: this is the scope every dues list,
        // dashboard tile, defaulter report and export reads.
        $this->assertSame(0, FeeInvoice::query()->outstanding()->count());
        $this->assertSame(0.0, (float) FeeInvoice::query()->outstanding()->sum('due_total'));
        $this->assertSame(0.0, (float) $invoice->student->feeInvoices()->outstanding()->sum('due_total'));

        $this->actingAs($accountant)
            ->get(route('fees.dues'))
            ->assertOk()
            ->assertDontSee($invoice->invoice_no);

        $this->assertSame(1, AuditLog::query()->where('event', 'fee_payment.settled_short')->count());

        $this->actingAs($accountant)
            ->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee('400.00')
            ->assertSee('500.00')
            ->assertDontSee('100.00');
    }

    public function test_the_written_off_difference_is_not_recreated_by_a_later_month(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $october = $this->generateInvoice($accountant, '2026-10');

        app(FeePaymentService::class)->collect($accountant, $october, [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'settle-nov-0001',
            'settle_in_full' => '1',
        ]);

        // The next month is billed at the standard fee again, not at 600.
        $november = $this->generateInvoice($accountant, '2026-11');

        $this->assertSame('500.00', $november->total);
        $this->assertSame('500.00', $november->due_total);
        $this->assertSame('0.00', $october->fresh()->due_total);
        $this->assertSame(1, FeeInvoice::query()->outstanding()->count());
    }

    public function test_a_short_payment_without_the_settlement_option_still_keeps_its_balance(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant, '2026-10');

        app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'settle-partial-0001',
        ]);

        $invoice->refresh();

        // An instalment is not a write-off; the family still owes 100.
        $this->assertSame('100.00', $invoice->due_total);
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('0.00', $invoice->waived_total);
        $this->assertSame(1, FeeInvoice::query()->outstanding()->count());
        $this->assertSame(0, AuditLog::query()->where('event', 'fee_payment.settled_short')->count());
    }

    public function test_a_settled_invoice_refuses_a_further_collection(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant, '2026-10');

        $service = app(FeePaymentService::class);

        $service->collect($accountant, $invoice, [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'settle-closed-0001',
            'settle_in_full' => '1',
        ]);

        try {
            $service->collect($accountant, $invoice->fresh(), [
                'amount' => 100,
                'method' => 'cash',
                'paid_at' => '2026-10-06 10:00:00',
                'idempotency_key' => 'settle-closed-0002',
            ]);
            $this->fail('A settled invoice should not accept another payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame('0.00', $invoice->fresh()->due_total);
        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(400.0, (float) CashTransaction::query()->where('type', 'income')->sum('amount'));
    }

    public function test_a_collection_larger_than_the_configured_fee_is_refused(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant, '2026-10');

        $this->expectException(ValidationException::class);

        app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 600,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'settle-over-0001',
            'settle_in_full' => '1',
        ]);
    }

    public function test_reversing_a_settled_payment_returns_the_invoice_to_the_fee_never_paid(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant, '2026-10');

        $payment = app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'settle-reverse-0001',
            'settle_in_full' => '1',
        ]);

        $this->assertSame('0.00', $invoice->fresh()->due_total);

        app(FeePaymentService::class)->reverse($accountant, $payment, 'The note was dishonoured');

        $invoice->refresh();

        // The receipt is cancelled, so the money and its write-off both go
        // back: the invoice is once again an unpaid 500, never a 100 due.
        $this->assertSame('0.00', $invoice->paid_total);
        $this->assertSame('0.00', $invoice->waived_total);
        $this->assertSame('500.00', $invoice->due_total);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(1, FeeInvoice::query()->outstanding()->count());

        // The cash entry is backed out with its own linked reversal row.
        $this->assertSame(1, CashTransaction::query()->whereNull('reversal_of_id')->count());
        $this->assertSame(1, CashTransaction::query()->whereNotNull('reversal_of_id')->count());
        $this->assertSame('400.00', CashTransaction::query()->whereNull('reversal_of_id')->value('amount'));
    }

    public function test_several_months_each_settled_short_book_only_the_cash_received(): void
    {
        [, $accountant] = $this->billingFixture(1);

        $service = app(FeePaymentService::class);
        $months = ['2026-08', '2026-09', '2026-10'];

        foreach ($months as $index => $month) {
            $invoice = $this->generateInvoice($accountant, $month);

            $service->collect($accountant, $invoice, [
                'amount' => 400,
                'method' => 'cash',
                'paid_at' => '2026-'.str_pad((string) ($index + 8), 2, '0', STR_PAD_LEFT).'-05 10:00:00',
                'idempotency_key' => 'settle-multi-'.$month,
                'settle_in_full' => '1',
            ]);
        }

        $this->assertSame(3, FeeInvoice::query()->count());
        // Settled short, so closed as settled rather than falsely fully paid.
        $this->assertSame(3, FeeInvoice::query()->where('status', 'settled')->count());
        $this->assertSame(0.0, (float) FeeInvoice::query()->sum('due_total'));
        $this->assertSame(1200.0, (float) FeePayment::query()->sum('amount'));
        $this->assertSame(1500.0, (float) FeeInvoice::query()->sum('total'));
        $this->assertSame(300.0, (float) FeeInvoice::query()->sum('waived_total'));

        // The cashbook holds the 1,200 actually taken, never the 1,500 billed.
        $this->assertSame(3, CashTransaction::query()->where('type', 'income')->whereNull('reversal_of_id')->count());
        $this->assertSame(1200.0, (float) CashTransaction::query()->whereNull('reversal_of_id')->sum('amount'));
        $this->assertSame(0, FeeInvoice::query()->outstanding()->count());
    }

    public function test_a_pending_settlement_writes_off_only_once_it_is_verified(): void
    {
        [, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant, '2026-10');

        $payment = app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 400,
            'method' => 'bkash',
            'transaction_ref' => 'BKASH-SETTLE-1',
            'paid_at' => '2026-10-05 10:00:00',
            'idempotency_key' => 'settle-hold-0001',
            'settle_in_full' => '1',
            'hold_for_verification' => '1',
        ]);

        $invoice->refresh();

        // Nothing is booked or written off while the transfer is unconfirmed.
        $this->assertSame('pending', $payment->status);
        $this->assertSame('500.00', $invoice->due_total);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(0, CashTransaction::query()->count());
        $this->assertSame(0, FeeReceipt::query()->count());

        app(FeePaymentService::class)->collect($accountant, $invoice->fresh(), [
            'amount' => 100,
            'method' => 'cash',
            'paid_at' => '2026-10-05 11:00:00',
            'idempotency_key' => 'settle-hold-0002',
        ]);

        // The held transfer still covers the rest of the fee, so the
        // write-off it was carrying is dropped rather than double counted.
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('400.00', $invoice->fresh()->due_total);
        $this->assertSame(100.0, (float) CashTransaction::query()->whereNull('reversal_of_id')->sum('amount'));

        $service = app(FeePaymentService::class);
        $service->verify($accountant, $payment->fresh());

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', $invoice->due_total);
        $this->assertSame('500.00', $invoice->paid_total);
        $this->assertSame('0.00', $invoice->waived_total);
        $this->assertSame(500.0, (float) CashTransaction::query()->whereNull('reversal_of_id')->sum('amount'));
        $this->assertSame(2, FeeReceipt::query()->count());
        $this->assertSame(0, FeeInvoice::query()->outstanding()->count());
    }

    public function test_collecting_requires_the_fee_collection_permission(): void
    {
        [$school, $accountant] = $this->billingFixture(1);
        $teacher = $this->makeUser($school, 'teacher');
        $invoice = $this->generateInvoice($accountant, '2026-10');

        $this->actingAs($teacher)->post(route('fees.payments.store', $invoice), [
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'settle-forbidden-1',
            'settle_in_full' => '1',
        ])->assertForbidden();

        $this->assertSame(0, FeePayment::query()->count());
        $this->assertSame('500.00', $invoice->fresh()->due_total);
        $this->assertSame(0, CashTransaction::query()->count());
    }

    /**
     * @return array{0: School, 1: User}
     */
    private function billingFixture(int $studentCount): array
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

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

        // The configured standard monthly fee.
        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $this->tuition->id,
            'name' => 'Monthly tuition',
            'amount' => 500,
            'due_day' => 10,
            'is_active' => true,
        ]);

        for ($index = 1; $index <= $studentCount; $index++) {
            $student = Student::factory()->create();

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

        return [$school, $accountant];
    }

    private function generateInvoice(User $accountant, string $billingPeriod): FeeInvoice
    {
        $result = app(FeeInvoiceService::class)->generate($accountant, [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => $billingPeriod,
            'issue_date' => $billingPeriod.'-01',
            'due_date' => $billingPeriod.'-10',
        ]);

        return $result['invoices'][0];
    }
}
