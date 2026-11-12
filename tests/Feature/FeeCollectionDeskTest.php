<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
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
use App\Services\FeeReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The invoice-first counter desk and the register printed from it.
 *
 * The desk takes an invoice, never a student or a fee type, so every figure on the
 * payment row comes from the record the money was actually handed over against.
 * The register reads posted payments only, at the same grain, which is why its
 * money and the fee-type totals always add back to the same number.
 */
class FeeCollectionDeskTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $accountant;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private FeeType $tuition;

    private FeeType $admission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->accountant = $this->makeUser($this->school, 'accountant');
        $this->admin = $this->makeUser($this->school, 'school_admin');

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
            'name' => 'Monthly Tuition Fee',
            'code' => 'TUI',
            'category' => 'tuition',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->admission = FeeType::query()->create([
            'name' => 'Admission Fee',
            'code' => 'ADM',
            'category' => 'admission',
            'frequency' => 'once',
            'is_active' => true,
        ]);

        foreach ([$this->tuition, $this->admission] as $index => $feeType) {
            FeeStructure::query()->create([
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'fee_type_id' => $feeType->id,
                'name' => $feeType->name,
                'amount' => $index === 0 ? 1000 : 500,
                'due_day' => 10,
                'is_active' => true,
            ]);
        }
    }

    public function test_the_desk_lists_unpaid_and_partly_paid_invoices_only(): void
    {
        $open = $this->bill('Rahim', 101);
        $partly = $this->bill('Karim', 102);
        $settled = $this->bill('Sabina', 103);
        $cancelled = $this->bill('Jamil', 104);

        $this->collect($partly, 400);
        $this->collect($settled, 1000);
        app(FeeInvoiceService::class)->cancel($this->admin, $cancelled->fresh());

        $desk = $this->actingAs($this->accountant)->get(route('fees.collect'));

        $desk->assertOk()
            ->assertSee($open->invoice_no)
            ->assertSee($partly->invoice_no)
            ->assertSee('Rahim')
            ->assertSee('Karim')
            ->assertDontSee($settled->invoice_no)
            ->assertDontSee($cancelled->invoice_no)
            ->assertDontSee('Sabina')
            ->assertDontSee('Jamil');
    }

    public function test_the_desk_searches_by_invoice_number_and_by_student_name(): void
    {
        $first = $this->bill('Ayesha Siddiqua', 201);
        $second = $this->bill('Nasrin Akter', 202);

        $this->actingAs($this->accountant)
            ->get(route('fees.collect', ['search' => $first->invoice_no]))
            ->assertOk()
            ->assertSee($first->invoice_no)
            ->assertDontSee($second->invoice_no);

        $this->actingAs($this->accountant)
            ->get(route('fees.collect', ['search' => 'Nasrin']))
            ->assertOk()
            ->assertSee($second->invoice_no)
            ->assertDontSee($first->invoice_no);
    }

    public function test_a_selected_invoice_carries_its_own_student_class_roll_session_and_balances(): void
    {
        $invoice = $this->bill('Mizanur Rahman', 301);

        $this->actingAs($this->accountant)
            ->get(route('fees.collect', ['invoice' => $invoice->id]))
            ->assertOk()
            ->assertSee($invoice->invoice_no)
            ->assertSee('Mizanur Rahman')
            ->assertSee('Class Six')
            ->assertSee('Section', false)
            ->assertSee('301')
            ->assertSee('October 2026')
            ->assertSee('Monthly Tuition Fee')
            ->assertSee('Previously received')
            ->assertSee('Remaining payable')
            ->assertSee('Received amount')
            ->assertSee('Collect Payment');
    }

    public function test_a_closed_invoice_is_never_offered_as_a_collection_target(): void
    {
        $invoice = $this->bill('Tanjila Rahman', 302);
        $this->collect($invoice, 1000);

        $this->actingAs($this->accountant)
            ->get(route('fees.collect', ['invoice' => $invoice->id]))
            ->assertOk()
            ->assertDontSee('Received amount');
    }

    public function test_a_partial_collection_stores_the_whole_payment_context(): void
    {
        $invoice = $this->bill('Shirin Akter', 401);

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '400'],
                'method' => 'cash',
                'paid_at' => '2026-10-05',
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-partial-1',
            ])
            ->assertRedirect();

        $payment = FeePayment::query()->firstOrFail();

        $this->assertSame('400.00', $payment->amount);
        $this->assertSame('posted', $payment->status);
        $this->assertSame($this->accountant->id, (int) $payment->received_by);
        $this->assertSame($this->session->id, (int) $payment->academic_session_id);
        $this->assertSame($invoice->id, (int) $payment->fee_invoice_id);
        $this->assertSame($invoice->invoice_no, $payment->invoice_no);
        $this->assertSame('1000.00', $payment->scheduled_amount);
        $this->assertSame($this->tuition->id, (int) $payment->fee_type_id);
        $this->assertSame('2026-10', $payment->billing_month);
        $this->assertSame('Shirin Akter', $payment->student->first_name);
        $this->assertStringStartsWith('TXN-', (string) $payment->transaction_ref);
        $this->assertSame('2026-10-05', $payment->paid_at->toDateString());
        $this->assertNotNull($payment->receipt);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('400.00', $invoice->paid_total);
        $this->assertSame('600.00', $invoice->due_total);
    }

    public function test_a_full_collection_closes_the_invoice_and_prints_a_receipt(): void
    {
        $invoice = $this->bill('Rafiul Islam', 501);

        $response = $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '1000'],
                'method' => 'bkash',
                'transaction_ref' => 'BKASH-DESK-501',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-full-1',
            ]);

        $payment = FeePayment::query()->firstOrFail();

        $response->assertRedirect(route('fees.receipt', $payment));

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->due_total);
        $this->assertSame('BKASH-DESK-501', $payment->transaction_ref);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt->receipt_no)
            ->assertSee('BKASH-DESK-501')
            ->assertSee('Rafiul Islam');
    }

    public function test_a_card_payment_without_the_provider_reference_is_refused(): void
    {
        $invoice = $this->bill('Nasir Uddin', 502);

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '500'],
                'method' => 'bkash',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-noref-1',
            ])
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame(0, FeePayment::query()->count());
        $this->assertSame('issued', $invoice->fresh()->status);
    }

    public function test_the_desk_refuses_more_money_than_the_invoice_still_needs(): void
    {
        $invoice = $this->bill('Farhana Yasmin', 601);

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '1000.01'],
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-over-1',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, FeePayment::query()->count());
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame('1000.00', $invoice->fresh()->due_total);
    }

    public function test_the_desk_refuses_a_collection_of_zero(): void
    {
        $invoice = $this->bill('Rubel Mia', 602);

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '0'],
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-zero-1',
            ])
            ->assertSessionHasErrors('amounts.'.$invoice->id);

        $this->assertSame(0, FeePayment::query()->count());
    }

    public function test_an_invoice_that_has_been_closed_stops_taking_money(): void
    {
        $invoice = $this->bill('Moriom Begum', 701);
        $this->collect($invoice, 1000);

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '100'],
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-closed-1',
            ])
            ->assertSessionHasErrors('amounts');

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame('1000.00', FeePayment::query()->firstOrFail()->amount);
        $this->assertSame('1000.00', $invoice->fresh()->paid_total);
    }

    public function test_a_browser_retry_with_the_same_key_replays_one_receipt(): void
    {
        $invoice = $this->bill('Sabbir Ahmed', 801);

        $payload = [
            'picked' => [$invoice->id],
            'amounts' => [$invoice->id => '250'],
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'academic_session_id' => $this->session->id,
            'idempotency_key' => 'desk-retry-1',
        ];

        $this->actingAs($this->accountant)->post(route('fees.collect.store'), $payload)->assertRedirect();
        $this->actingAs($this->accountant)->post(route('fees.collect.store'), $payload)->assertRedirect();

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(1, FeeReceipt::query()->count());
        $this->assertSame('250.00', FeePayment::query()->firstOrFail()->amount);
        $this->assertSame('750.00', $invoice->fresh()->due_total);
    }

    public function test_the_desk_is_closed_to_the_office_that_monitors_it(): void
    {
        $invoice = $this->bill('Imran Hossain', 802);

        $this->actingAs($this->admin)
            ->get(route('fees.collect'))
            ->assertForbidden();

        $this->actingAs($this->makeUser($this->school, 'principal'))
            ->get(route('fees.collect'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '100'],
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-admin-1',
            ])
            ->assertForbidden();

        $this->assertSame(0, FeePayment::query()->count());
    }

    public function test_the_desk_is_closed_to_a_teacher_without_the_collection_right(): void
    {
        $this->actingAs($this->makeUser($this->school, 'teacher'))
            ->get(route('fees.collect'))
            ->assertForbidden();
    }

    public function test_the_register_and_the_fee_type_summary_add_back_to_the_same_money(): void
    {
        $tuition = $this->bill('Register One', 901);
        $admission = $this->bill('Register Two', 902, $this->admission);

        $this->collect($tuition, 400);
        $this->collect($tuition->fresh(), 600);
        $admissionPayment = $this->collect($admission, 200);

        $this->actingAs($this->accountant)
            ->get(route('reports.collection', [
                'from' => '2020-01-01',
                'to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Collection register')
            ->assertSee($admissionPayment->transaction_ref)
            ->assertSee('Register One')
            ->assertSee('Monthly Tuition Fee')
            ->assertSee('Admission Fee')
            ->assertSee('Fee type summary')
            ->assertSee('Grand total');

        $filters = $this->filters(['from' => '2020-01-01', 'to' => now()->addDay()->toDateString()]);
        $reports = app(FeeReportService::class);

        $rows = $reports->registerRows($filters);

        // Two instalments of one tuition invoice plus one admission instalment.
        $this->assertCount(3, $rows);
        $this->assertSame(1200.0, round((float) $rows->sum('received'), 2));
        $this->assertSame(1200.0, $reports->feeTypeSummary($filters)['total']);
        $this->assertSame(1200.0, $reports->receivedTotal($filters));

        $groups = $reports->feeTypeSummary($filters)['groups']->keyBy('category');
        $this->assertSame(1000.0, $groups['tuition']['received']);
        $this->assertSame(200.0, $groups['admission']['received']);

        // The invoice total is quoted as the scheduled fee, never as cash.
        $this->assertSame([500.0, 1000.0, 1000.0], $rows->pluck('scheduled')->sort()->values()->all());

        $tuitionRow = $rows->firstWhere('invoice_no', $tuition->invoice_no);
        $admissionRow = $rows->firstWhere('invoice_no', $admission->invoice_no);

        $this->assertSame('October 2026', $tuitionRow['month_label']);
        $this->assertSame('Class Six', $tuitionRow['class']);
        $this->assertSame('A', $tuitionRow['section']);
        $this->assertSame('901', (string) $tuitionRow['roll']);
        $this->assertSame('Monthly Tuition Fee', $tuitionRow['fee_type']);
        $this->assertSame('2026', $tuitionRow['session']);
        $this->assertSame($this->accountant->name, $tuitionRow['collector']);
        $this->assertStringStartsWith('TXN-', $tuitionRow['transaction_ref']);
        $this->assertSame('Admission Fee', $admissionRow['fee_type']);
        $this->assertSame([1, 2, 3], $rows->pluck('serial')->all());
    }

    public function test_the_register_honours_every_filter_on_the_sheet(): void
    {
        $first = $this->bill('Filter First', 1101);
        $second = $this->bill('Filter Second', 1102);
        $third = $this->bill('Filter Third', 1103, $this->admission);

        $cash = $this->collect($first, 300);
        $this->collect($second, 700, 'bkash', 'TRX-FILTER-1');
        $this->collect($third, 200, 'cash');

        $base = ['from' => '2020-01-01', 'to' => now()->addDay()->toDateString()];
        $reports = app(FeeReportService::class);

        $this->assertCount(3, $reports->registerRows($this->filters($base)));

        $byStudent = $reports->registerRows($this->filters($base + ['student_id' => $first->student_id]));
        $this->assertCount(1, $byStudent);
        $this->assertSame($cash->transaction_ref, $byStudent->first()['transaction_ref']);

        $byMethod = $reports->registerRows($this->filters($base + ['method' => 'bkash']));
        $this->assertCount(1, $byMethod);
        $this->assertSame('TRX-FILTER-1', $byMethod->first()['transaction_ref']);

        $byTuition = $reports->registerRows($this->filters($base + ['fee_type_id' => $this->tuition->id]));
        $this->assertCount(2, $byTuition);
        $this->assertSame(1000.0, round((float) $byTuition->sum('received'), 2));

        $byAdmission = $reports->registerRows($this->filters($base + ['fee_type_id' => $this->admission->id]));
        $this->assertCount(1, $byAdmission);
        $this->assertSame(200.0, round((float) $byAdmission->sum('received'), 2));
        $this->assertSame($third->invoice_no, $byAdmission->first()['invoice_no']);

        $byMonth = $reports->registerRows($this->filters($base + ['month' => '2026-10']));
        $this->assertCount(3, $byMonth);
        $this->assertCount(0, $reports->registerRows($this->filters($base + ['month' => '2027-01'])));

        $bySession = $reports->registerRows($this->filters($base + ['session_id' => $this->session->id]));
        $this->assertCount(3, $bySession);

        $otherSession = AcademicSession::query()->create([
            'name' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'active',
        ]);
        $this->assertCount(0, $reports->registerRows($this->filters($base + ['session_id' => $otherSession->id])));

        $byClass = $reports->registerRows($this->filters($base + ['class_id' => $this->class->id]));
        $this->assertCount(3, $byClass);

        $otherClass = SchoolClass::query()->create(['name' => 'Class Seven', 'level' => 7, 'is_active' => true]);
        $this->assertCount(0, $reports->registerRows($this->filters($base + ['class_id' => $otherClass->id])));

        $bySection = $reports->registerRows($this->filters($base + ['section_id' => $this->section->id]));
        $this->assertCount(3, $bySection);

        $byCollector = $reports->registerRows($this->filters($base + ['collector_id' => $this->accountant->id]));
        $this->assertCount(3, $byCollector);

        // Money held for verification is not cash yet, so it stays off the sheet.
        $held = app(FeePaymentService::class)->collect($this->accountant, $first->fresh(), [
            'amount' => 100,
            'method' => 'nagad',
            'transaction_ref' => 'NAGAD-HELD-1',
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'register-held-1',
            'hold_for_verification' => true,
        ]);

        $this->assertSame('pending', $held->status);

        $afterHold = $reports->registerRows($this->filters($base));
        $this->assertCount(3, $afterHold);
        $this->assertSame(1200.0, round((float) $afterHold->sum('received'), 2));
        $this->assertNotContains('NAGAD-HELD-1', $afterHold->pluck('transaction_ref')->all());
    }

    public function test_a_reversed_payment_leaves_the_register_and_the_fee_type_totals(): void
    {
        $invoice = $this->bill('Reversed Nasreen', 1150);
        $payment = $this->collect($invoice, 1000);

        $filters = $this->filters(['from' => '2020-01-01', 'to' => now()->addDay()->toDateString()]);
        $reports = app(FeeReportService::class);

        $this->assertSame(1000.0, $reports->feeTypeSummary($filters)['total']);

        app(FeePaymentService::class)->reverse($this->admin, $payment, 'Entered the wrong student');

        $this->assertSame(0.0, round((float) $reports->registerRows($filters)->sum('received'), 2));
        $this->assertSame(0.0, $reports->feeTypeSummary($filters)['total']);
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame('1000.00', $invoice->fresh()->due_total);
    }

    public function test_a_fee_named_by_the_admin_lands_in_its_own_report_group(): void
    {
        // The add-fee screen asks for a name only, so the report has to read the
        // category out of that name — including for catalogue rows that were
        // created before any category column existed. A school may have several
        // tuition fees, so this one is named differently from the fixture's.
        $legacy = FeeType::query()->create([
            'name' => 'Monthly Class Fee',
            'code' => 'LEG',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $this->assertNull($legacy->category);
        $this->assertSame('tuition', FeeType::categoryForName('Monthly Class Fee'));

        $this->assertSame('tuition', FeeType::categoryForName('Monthly Tuition Fee'));
        $this->assertSame('tuition', FeeType::categoryForName('Class Fee-2026'));
        $this->assertSame('examination', FeeType::categoryForName('Terminal Examination'));
        $this->assertSame('admission', FeeType::categoryForName('Admission and registration'));
        $this->assertSame('transport', FeeType::categoryForName('Bus Pick and Drop'));
        $this->assertSame('other', FeeType::categoryForName('Science Club Materials'));

        $invoice = $this->bill('Grouped Shanti', 1175, $this->priced($legacy, 1200));
        $this->collect($invoice, 1200);

        $summary = app(FeeReportService::class)->feeTypeSummary(
            $this->filters(['from' => '2020-01-01', 'to' => now()->addDay()->toDateString()])
        );

        $this->assertSame(1200.0, $summary['groups']->keyBy('category')['tuition']['received']);
        $this->assertSame('Monthly Tuition Fee', $summary['groups']->keyBy('category')['tuition']['label']);
    }

    public function test_the_register_prints_the_whole_range_and_exports_as_a_file(): void
    {
        $invoice = $this->bill('Exportable Nadia', 1201);
        $payment = $this->collect($invoice, 1000);

        $query = ['from' => '2020-01-01', 'to' => now()->addDay()->toDateString()];

        $sheet = $this->actingAs($this->accountant)
            ->get(route('reports.collection', $query + ['all' => 1, 'print' => 1]))
            ->assertOk();

        $sheet->assertSee($payment->transaction_ref)
            ->assertSee('Collection register')
            ->assertSee($this->school->name);

        $export = $this->actingAs($this->accountant)
            ->get(route('reports.export', $query + ['report' => 'register']))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $this->assertStringContainsString(
            'fee-collection-register-'.now()->format('Y-m-d').'.csv',
            (string) $export->headers->get('content-disposition')
        );

        $teacher = $this->makeUser($this->school, 'teacher');

        $this->actingAs($teacher)->get(route('reports.collection', $query))->assertForbidden();
        $this->actingAs($teacher)->get(route('reports.export', $query + ['report' => 'register']))->assertForbidden();
    }

    public function test_the_accountant_dashboard_offers_the_invoice_first_desk(): void
    {
        $invoice = $this->bill('Dashboard Reza', 1301);

        $this->actingAs($this->accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Select Invoice')
            ->assertSee('Collect Payment')
            ->assertSee($invoice->invoice_no)
            ->assertDontSee('Select Student')
            ->assertDontSee('Remarks / Notes');
    }

    public function test_the_dashboard_modal_posts_the_same_invoice_first_contract(): void
    {
        $invoice = $this->bill('Modal Anwar', 1302);

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => '600'],
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'academic_session_id' => $this->session->id,
                'idempotency_key' => 'desk-modal-1',
            ])
            ->assertRedirect(route('fees.receipt', FeePayment::query()->firstOrFail()));

        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('400.00', $invoice->fresh()->due_total);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', FeePayment::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('Modal Anwar');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function filters(array $params): array
    {
        return app(FeeReportService::class)->filters(
            Request::create('/reports/collection', 'GET', $params)
        );
    }

    /**
     * Price a fee type for the test class so it can be billed.
     */
    private function priced(FeeType $feeType, float $amount): FeeType
    {
        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $feeType->id,
            'name' => $feeType->name,
            'amount' => $amount,
            'due_day' => 10,
            'is_active' => true,
        ]);

        return $feeType;
    }

    private function bill(string $name, int $roll, ?FeeType $feeType = null): FeeInvoice
    {
        $feeType ??= $this->tuition;

        $student = Student::factory()->create([
            'first_name' => $name,
            'last_name' => 'Khan',
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        $result = app(FeeInvoiceService::class)->generate($this->admin, [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'student_ids' => [$student->id],
            'fee_type_ids' => [$feeType->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ]);

        $invoice = $result['invoices'][0];

        $this->assertSame($student->id, (int) $invoice->student_id);

        return $invoice;
    }

    private function collect(FeeInvoice $invoice, float $amount, string $method = 'cash', ?string $reference = null): FeePayment
    {
        $payment = app(FeePaymentService::class)->collect($this->accountant, $invoice, [
            'amount' => $amount,
            'method' => $method,
            'transaction_ref' => $reference,
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'desk-'.str_replace(['.', ' '], '-', uniqid('', true)),
        ]);

        $this->assertNotEmpty($payment->transaction_ref);

        return $payment;
    }
}
