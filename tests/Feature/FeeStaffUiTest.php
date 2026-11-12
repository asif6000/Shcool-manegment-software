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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class FeeStaffUiTest extends TestCase
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

    public function test_accountant_can_view_the_fee_workspace(): void
    {
        [$school, $accountant] = $this->billingFixture(1);
        $invoice = $this->generateInvoice($accountant);

        $this->actingAs($accountant)
            ->get(route('fees.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_no)
            ->assertSee(__('Fees & payments'));

        $this->actingAs($accountant)
            ->get(route('fees.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_no)
            ->assertSee(__('Collect payment'));

        $this->actingAs($accountant)
            ->get(route('fees.dues'))
            ->assertOk()
            ->assertSee($invoice->invoice_no);
    }

    public function test_principal_can_view_but_cannot_generate_or_collect_payments(): void
    {
        [$school, $accountant] = $this->billingFixture(1);
        $principal = $this->makeUser($school, 'principal');
        $invoice = $this->generateInvoice($accountant);

        $this->actingAs($principal)
            ->get(route('fees.index'))
            ->assertOk();

        $this->actingAs($principal)
            ->get(route('fees.create'))
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'fee_type_ids' => [$this->tuition->id],
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('fees.payments.store', $invoice), [
                'amount' => 100,
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'idempotency_key' => 'principal-payment-1',
            ])
            ->assertForbidden();
    }

    public function test_invoices_can_be_generated_through_the_admin_ui(): void
    {
        [$school, $admin] = $this->billingFixture(2);
        $students = Student::query()->orderBy('id')->get();
        $target = $students->first();

        $this->actingAs($admin)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'search' => $target->student_id]))
            ->assertOk()
            ->assertSee($target->full_name);

        $this->actingAs($admin)
            ->get(route('fees.create', ['session_id' => $this->session->id, 'student' => $target->id]))
            ->assertOk()
            ->assertSee($this->tuition->name)
            ->assertSee('৳ 1,000.00');

        $response = $this->actingAs($admin)->post(route('fees.generate'), [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'student_ids' => [$target->id],
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ]);

        $invoice = FeeInvoice::query()->firstOrFail();

        $response->assertRedirect(route('fees.show', $invoice));
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('1000.00', $invoice->total);
        $this->assertSame(1, $invoice->items()->count());

        // Billing one learner must not sweep the rest of the class along.
        $this->assertSame(1, FeeInvoice::query()->count());
        $this->assertSame($target->id, $invoice->student_id);
    }

    public function test_a_student_without_an_enrollment_in_the_session_cannot_be_billed(): void
    {
        [$school, $admin] = $this->billingFixture(1);
        $student = Student::query()->firstOrFail();

        $otherSession = AcademicSession::query()->create([
            'name' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'active',
            'is_current' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('fees.create', ['session_id' => $otherSession->id, 'student' => $student->id]))
            ->assertOk()
            ->assertSee('no active enrollment');

        $response = $this->actingAs($admin)->post(route('fees.generate'), [
            'academic_session_id' => $otherSession->id,
            'class_id' => $this->class->id,
            'student_ids' => [$student->id],
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => '2027-10',
            'issue_date' => '2027-10-01',
            'due_date' => '2027-10-10',
        ]);

        $response->assertSessionHasErrors('class_id');
        $this->assertSame(0, FeeInvoice::query()->count());
    }

    public function test_payment_collection_issues_a_receipt_and_reversal_restores_the_balance(): void
    {
        [$school, $accountant] = $this->billingFixture(1);
        $admin = $this->makeUser($school, 'school_admin');
        $invoice = $this->generateInvoice($accountant);

        $response = $this->actingAs($accountant)->post(route('fees.payments.store', $invoice), [
            'amount' => 400,
            'method' => 'bkash',
            'transaction_ref' => 'BKASH-UI-1',
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'ui-payment-0001',
        ]);

        $payment = FeePayment::query()->firstOrFail();
        $receipt = FeeReceipt::query()->firstOrFail();

        $response->assertRedirect(route('fees.receipt', $payment));
        $this->assertSame('400.00', $payment->amount);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('600.00', $invoice->fresh()->due_total);

        $this->actingAs($accountant)
            ->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee($receipt->receipt_no)
            ->assertSee('Bkash')
            ->assertSee('BKASH-UI-1');

        // The counter cannot undo its own receipt; only the office can.
        $this->actingAs($accountant)
            ->post(route('fees.payments.reverse', $payment), ['reason' => 'Cheque bounced at the bank'])
            ->assertForbidden();

        $this->assertSame('posted', $payment->fresh()->status);

        $this->actingAs($admin)
            ->post(route('fees.payments.reverse', $payment), ['reason' => 'Cheque bounced at the bank'])
            ->assertRedirect(route('fees.show', $invoice));

        $this->assertSame('reversed', $payment->fresh()->status);
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame('1000.00', $invoice->fresh()->due_total);

        $this->actingAs($accountant)
            ->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee(__('This payment has been reversed.'));
    }

    public function test_payment_reversal_requires_the_reverse_permission_and_a_reason(): void
    {
        [$school, $accountant] = $this->billingFixture(1);
        $principal = $this->makeUser($school, 'principal');
        $admin = $this->makeUser($school, 'school_admin');
        $invoice = $this->generateInvoice($accountant);

        $this->actingAs($accountant)->post(route('fees.payments.store', $invoice), [
            'amount' => 250,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'ui-payment-0002',
        ])->assertRedirect();

        $payment = FeePayment::query()->firstOrFail();

        $this->actingAs($principal)
            ->post(route('fees.payments.reverse', $payment), ['reason' => 'Not authorised'])
            ->assertForbidden();

        // Money the accountant took themselves is not theirs to take back, even
        // though an older role matrix once handed them the reverse permission.
        $this->actingAs($accountant)
            ->post(route('fees.payments.reverse', $payment), ['reason' => 'Paid against the wrong student'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('fees.payments.reverse', $payment), ['reason' => 'No'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('posted', $payment->fresh()->status);
    }

    public function test_fee_records_are_not_visible_across_schools(): void
    {
        [$schoolA, $accountantA] = $this->billingFixture(1);
        $invoiceA = $this->generateInvoice($accountantA);

        [$schoolB, $accountantB] = $this->billingFixture(1);
        $invoiceB = $this->generateInvoice($accountantB);

        $paymentB = app(FeePaymentService::class)->collect($accountantB, $invoiceB, [
            'amount' => 100,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'idempotency_key' => 'school-b-payment-1',
        ]);

        $this->actingAs($accountantA)
            ->get(route('fees.show', $invoiceB))
            ->assertNotFound();

        $this->actingAs($accountantA)
            ->get(route('fees.receipt', $paymentB))
            ->assertNotFound();

        $this->actingAs($accountantA)
            ->post(route('fees.payments.store', $invoiceB), [
                'amount' => 100,
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
                'idempotency_key' => 'cross-school-payment',
            ])
            ->assertNotFound();

        $this->assertSame(0, FeePayment::query()->count());
        $this->assertSame(1, FeePayment::withoutSchoolScope()->count());
        $this->assertSame('posted', $paymentB->fresh()->status);
    }

    public function test_fee_export_is_permission_gated(): void
    {
        [$school, $accountant] = $this->billingFixture(1);
        $principal = $this->makeUser($school, 'principal');
        $this->generateInvoice($accountant);

        $this->actingAs($principal)
            ->get(route('fees.export'))
            ->assertForbidden();

        $response = $this->actingAs($accountant)->get(route('fees.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
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

        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $this->tuition->id,
            'name' => 'Monthly tuition',
            'amount' => 1000,
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

    private function generateInvoice(User $actor): FeeInvoice
    {
        $result = app(FeeInvoiceService::class)->generate($actor, [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'fee_type_ids' => [$this->tuition->id],
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
        ]);

        return $result['invoices'][0];
    }
}
