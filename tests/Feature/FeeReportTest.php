<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\CashTransaction;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use App\Services\FeeReportService;
use App\Support\Money;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The reports module reads the same money the cashbook holds: the fee as issued
 * and the cash actually taken are two separate figures, refunds and unverified
 * money stay out of the collected total, and nothing in this module adds up,
 * shows or carries a balance owed.
 */
class FeeReportTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private FeeType $tuition;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->billingFixture();
    }

    public function test_a_settled_500_fee_reports_500_issued_and_400_received_with_no_due_metric(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05', settle: true);

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(500.0, $service->standardFeeTotal($filters));
        $this->assertSame(400.0, $service->receivedTotal($filters));
        $this->assertSame(400.0, $service->cashbookInflow($filters)['gross']);
        $this->assertSame(400.0, $service->cashbookInflow($filters)['net']);

        $summary = $service->summary($filters);
        $this->assertSame(500.0, $summary['standard_fee']);
        $this->assertSame(400.0, $summary['received']);
        $this->assertSame(1, $summary['payments']);
        $this->assertSame(1, $summary['invoices']);

        // No report number is a balance owed.
        $this->assertSame(
            [],
            collect($summary)->keys()
                ->filter(fn (string $key): bool => str_contains($key, 'due') || str_contains($key, 'outstanding'))
                ->values()
                ->all(),
        );

        $page = $this->actingAs($this->accountant)->get(route('reports.collection', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $page->assertOk()
            ->assertSee(Money::format(500))
            ->assertSee(Money::format(400))
            ->assertDontSee('Outstanding', false)
            ->assertDontSee(Money::format(100));

        foreach (['students', 'classes', 'fee-types', 'methods', 'collectors'] as $report) {
            $this->actingAs($this->accountant)
                ->get(route('reports.'.$report, ['from' => '2026-10-01', 'to' => '2026-10-31']))
                ->assertOk()
                ->assertSee(Money::format(400))
                ->assertDontSee('Outstanding', false);
        }
    }

    public function test_only_money_paid_inside_the_range_is_counted(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05');

        $service = app(FeeReportService::class);

        $october = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));
        $november = $service->filters($this->reportRequest(['from' => '2026-11-01', 'to' => '2026-11-30']));

        $this->assertSame(400.0, $service->receivedTotal($october));
        $this->assertSame(0.0, $service->receivedTotal($november));
        $this->assertSame(0.0, $service->standardFeeTotal($november));
        $this->assertSame(0, $service->summary($november)['invoices']);
    }

    public function test_the_fee_month_and_the_payment_date_are_different_dimensions(): void
    {
        $invoice = $this->invoice('2026-09');
        $this->collect($invoice, 500, '2026-10-05');

        $service = app(FeeReportService::class);
        $paid = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(['2026-10'], $service->byPaymentMonth($paid)->pluck('key')->all());
        $this->assertSame(['2026-09'], $service->byFeeMonth($paid)->pluck('key')->all());

        $forSeptember = $service->filters($this->reportRequest([
            'from' => '2026-01-01', 'to' => '2026-12-31', 'month' => '2026-09',
        ]));
        $forOctober = $service->filters($this->reportRequest([
            'from' => '2026-01-01', 'to' => '2026-12-31', 'month' => '2026-10',
        ]));

        $this->assertSame(500.0, $service->receivedTotal($forSeptember));
        $this->assertSame(0.0, $service->receivedTotal($forOctober));
    }

    public function test_several_months_collected_at_once_are_each_counted_once(): void
    {
        foreach (['2026-08', '2026-09', '2026-10'] as $month) {
            $this->collect($this->invoice($month), 400, '2026-10-05', settle: true);
        }

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(1200.0, $service->receivedTotal($filters));
        $this->assertSame(1500.0, $service->standardFeeTotal($filters));
        $this->assertSame(3, $service->summary($filters)['invoices']);
        $this->assertSame(1200.0, $service->cashbookInflow($filters)['gross']);

        // 800 written off across the three settlements is not a due anywhere.
        $this->assertSame(0, FeeInvoice::query()->outstanding()->count());
        $this->assertSame(0.0, (float) FeeInvoice::query()->outstanding()->sum('due_total'));

        $byMonth = $service->byFeeMonth($filters);
        $this->assertSame(500.0, $byMonth->firstWhere('key', '2026-08')['standard_fee']);
        $this->assertSame(400.0, $byMonth->firstWhere('key', '2026-08')['received']);
    }

    public function test_one_receipt_spread_over_two_fee_records_is_counted_once(): void
    {
        $september = $this->invoice('2026-09');
        $october = $this->invoice('2026-10');

        $payment = $this->collect($september, 300, '2026-10-05');

        // The same receipt also covered the next month: two allocation rows,
        // one payment. Summing payments per invoice would read this as 600.
        FeePaymentAllocation::query()->create([
            'fee_payment_id' => $payment->id,
            'fee_invoice_id' => $october->id,
            'amount' => 200,
        ]);

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(500.0, $service->receivedTotal($filters));
        $this->assertSame(1000.0, $service->standardFeeTotal($filters));
        $this->assertSame(1, $service->summary($filters)['payments']);
        $this->assertSame(2, $service->summary($filters)['invoices']);

        $byDay = $service->byDay($filters);
        $this->assertSame(500.0, $byDay->first()['received']);
        $this->assertSame(2, $byDay->first()['invoices']);
    }

    public function test_a_refund_is_reported_apart_from_cash_received(): void
    {
        // The reversal is stamped with "now", so the clock has to sit inside the
        // month the report asks for.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));

        $invoice = $this->invoice('2026-10');
        $payment = $this->collect($invoice, 400, '2026-10-05');

        app(FeePaymentService::class)->reverse($this->accountant, $payment, 'Paid against the wrong student');

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(0.0, $service->receivedTotal($filters));
        $this->assertSame(0.0, $service->standardFeeTotal($filters));
        $this->assertSame(1, $service->summary($filters)['reversed_count']);
        $this->assertSame(400.0, $service->summary($filters)['reversed_amount']);
        $this->assertSame(400.0, $service->cashbookInflow($filters)['gross']);
        $this->assertSame(0.0, $service->cashbookInflow($filters)['net']);
        $this->assertSame(0.0, (float) CashTransaction::query()->sum('amount'));

        $this->assertCount(1, $service->reversals($filters));
        $this->assertSame(400.0, $service->reversals($filters)->first()['amount']);

        $this->actingAs($this->accountant)
            ->get(route('reports.collection', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee(__('Reversed in range'))
            ->assertSee(Money::format(400));
    }

    public function test_a_payment_waiting_verification_is_not_collected_money(): void
    {
        $invoice = $this->invoice('2026-10');

        $this->actingAs($this->accountant)->post(route('fees.payments.store', $invoice), [
            'amount' => 400,
            'method' => 'bkash',
            'paid_at' => '2026-10-05',
            'transaction_ref' => 'BX-1',
            'hold_for_verification' => '1',
            'idempotency_key' => 'reports-held-0001',
        ])->assertSessionHasNoErrors();

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(0.0, $service->receivedTotal($filters));
        $this->assertSame(1, $service->summary($filters)['held_count']);
        $this->assertSame(400.0, $service->summary($filters)['held_amount']);
        $this->assertSame(0.0, $service->cashbookInflow($filters)['gross']);
        $this->assertCount(1, $service->heldPayments($filters));
    }

    public function test_class_section_and_fee_type_splits_reconcile_to_the_total(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05', settle: true);

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest(['from' => '2026-10-01', 'to' => '2026-10-31']));

        $this->assertSame(400.0, $service->byClass($filters)->sum('received'));
        $this->assertSame(400.0, $service->bySection($filters)->sum('received'));
        $this->assertSame(400.0, $service->byFeeType($filters)->sum('received'));
        $this->assertSame(400.0, $service->byStudent($filters)->sum('received'));
        $this->assertSame(400.0, $service->byCollector($filters)->sum('received'));

        $this->assertSame('Class Six', $service->byClass($filters)->first()['label']);
        $this->assertSame('Class Six · A', $service->bySection($filters)->first()['label']);
        $this->assertSame('Tuition Fee', $service->byFeeType($filters)->first()['label']);
        $this->assertSame(500.0, $service->byFeeType($filters)->first()['billed']);
    }

    public function test_the_report_can_be_narrowed_to_one_fee_type(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05', settle: true);

        $service = app(FeeReportService::class);
        $filters = $service->filters($this->reportRequest([
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'fee_type_id' => $this->tuition->id,
        ]));

        $this->assertSame(400.0, $service->byClass($filters)->sum('received'));
        $this->assertSame(400.0, $service->byFeeType($filters)->sum('received'));
        $this->assertSame(500.0, $service->byFeeType($filters)->sum('billed'));
    }

    public function test_exports_carry_the_same_figures_as_the_screen(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05', settle: true);

        $range = ['from' => '2026-10-01', 'to' => '2026-10-31'];

        $collection = $this->actingAs($this->accountant)
            ->get(route('reports.export', array_merge($range, ['report' => 'collection'])))
            ->assertOk();
        $csv = $collection->streamedContent();

        $this->assertStringContainsString('Standard fee of invoice (BDT)', $csv);
        $this->assertStringContainsString('Cash received against invoice (BDT)', $csv);
        $this->assertStringContainsString('500.00', $csv);
        $this->assertStringContainsString('400.00', $csv);
        $this->assertStringNotContainsString('Due', $csv);

        foreach (['students', 'classes', 'fee-types', 'methods', 'collectors', 'months'] as $report) {
            $response = $this->actingAs($this->accountant)
                ->get(route('reports.export', array_merge($range, ['report' => $report])))
                ->assertOk();

            $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('400.00', $response->streamedContent());
        }
    }

    public function test_a_teacher_cannot_reach_any_fee_report_or_export(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05');

        $teacher = $this->makeUser($invoice->school, 'teacher');
        $teacher->givePermissionTo('reports.view');

        foreach (['index', 'collection', 'students', 'classes', 'fee-types', 'methods', 'collectors'] as $page) {
            $this->actingAs($teacher)->get(route('reports.'.$page))->assertForbidden();
        }

        $this->actingAs($teacher)
            ->get(route('reports.export', ['report' => 'collection']))
            ->assertForbidden();
    }

    public function test_export_needs_its_own_permission_but_not_the_page(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05');

        // The accountant role grants reports.export; take it away to prove the
        // screen stays open while the download does not.
        Role::findByName('accountant')->revokePermissionTo('reports.export');
        $accountant = $this->accountant->fresh();

        $this->actingAs($accountant)
            ->get(route('reports.collection', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertDontSee(__('Export CSV'), false);

        $this->actingAs($accountant)
            ->get(route('reports.export', ['report' => 'collection']))
            ->assertForbidden();
    }

    public function test_another_schools_money_never_shows(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05');
        $student = $invoice->student;

        $other = $this->makeSchool();
        $otherAccountant = $this->makeUser($other, 'accountant');

        $page = $this->actingAs($otherAccountant)
            ->get(route('reports.students', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk();

        $page->assertDontSee($student->full_name, false)
            ->assertDontSee(Money::format(400));
    }

    /**
     * @param  array<string, string>  $query
     */
    private function reportRequest(array $query): Request
    {
        return Request::create('/reports/collection', 'GET', $query);
    }

    private function collect(FeeInvoice $invoice, float $amount, string $paidAt, bool $settle = false): FeePayment
    {
        $this->actingAs($this->accountant)->post(route('fees.payments.store', $invoice), [
            'amount' => $amount,
            'method' => 'cash',
            'paid_at' => $paidAt,
            'idempotency_key' => 'reports-'.$invoice->id.'-'.str_replace('-', '', $paidAt).'-'.($settle ? 's' : 'p'),
            'settle_in_full' => $settle ? '1' : null,
        ])->assertSessionHasNoErrors();

        return FeePayment::query()->latest('id')->firstOrFail();
    }

    private function invoice(string $billingPeriod): FeeInvoice
    {
        $result = app(FeeInvoiceService::class)->generate($this->accountant, [
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

    private function billingFixture(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $this->accountant = $this->makeUser($school, 'accountant');

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
            'amount' => 500,
            'due_day' => 10,
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
    }
}
