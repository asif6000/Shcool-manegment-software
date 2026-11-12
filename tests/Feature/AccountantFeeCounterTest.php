<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
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
use App\Services\StudentPortalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The accountant counter: pick the learner, the month and the fee type, see
 * what the fee setup says is due, then take full or part of it - never more.
 */
class AccountantFeeCounterTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $accountant;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Student $student;

    private FeeType $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->accountant = $this->makeUser($this->school, 'accountant');

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

        $this->student = Student::factory()->create();

        Enrollment::query()->create([
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
    }

    public function test_the_counter_loads_the_scheduled_amount_from_the_fee_setup(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('fees.counter', [
            'session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'fee_type_id' => $this->tuition->id,
            'month' => 10,
        ]));

        $response->assertOk()
            ->assertSee('Tuition Fee')
            ->assertSee('Scheduled amount')
            ->assertSee('৳500')
            ->assertSee($this->student->full_name)
            ->assertSee('October')
            ->assertSee('id="counter-student-search"', false);

        // Nothing has been recorded just by looking at the fee.
        $this->assertSame(0, FeePayment::query()->count());
        $this->assertSame(0, FeeInvoice::query()->count());
    }

    public function test_a_full_payment_issues_a_receipt_with_an_automatic_transaction_id(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 500,
            'idempotency_key' => 'counter-full-0001',
        ]));

        $payment = FeePayment::query()->firstOrFail();

        $response->assertRedirect(route('fees.receipt', $payment));

        $this->assertSame('posted', $payment->status);
        $this->assertMatchesRegularExpression('/^TXN-\d{8}-[A-Z0-9]{6}$/', (string) $payment->transaction_ref);
        $this->assertSame('2026-10', $payment->billing_month);
        $this->assertSame($this->class->id, $payment->class_id);
        $this->assertSame($this->tuition->id, $payment->fee_type_id);
        $this->assertSame(1, FeeReceipt::query()->count());
        $this->assertNotNull($payment->receipt);

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('500.00', $invoice->total);
        $this->assertSame('500.00', $invoice->paid_total);
        $this->assertSame('0.00', $invoice->due_total);
        $this->assertSame('paid', $invoice->status);

        $this->assertSame(1, CashTransaction::query()->where('type', 'income')->count());
        $this->assertSame(500.0, (float) CashTransaction::query()->where('type', 'income')->sum('amount'));

        // The printed receipt carries the fee name, scheduled amount, student
        // details, payment date and the generated transaction id — and no
        // due/bokeya or discount figures.
        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee('Transaction ID')
            ->assertSee((string) $payment->transaction_ref)
            ->assertSee('Tuition Fee')
            ->assertSee('Scheduled amount')
            ->assertSee('500.00')
            ->assertSee($this->student->full_name)
            ->assertSee('Paid on')
            ->assertDontSee('<th>Discounts</th>', false)
            ->assertDontSee('Balance after', false)
            ->assertDontSee('Bokeya', false);

        // Admin sees the collection in the school-wide receipt list.
        $admin = $this->makeUser($this->school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('fees.receipts'))
            ->assertOk()
            ->assertSee($invoice->invoice_no)
            ->assertSee((string) $payment->transaction_ref);

        // Student (and guardian) fee history reads the same posted payment.
        $history = app(StudentPortalService::class)->fees($this->student, '');

        $this->assertSame(1, $history['payments']->count());
        $this->assertSame((string) $payment->transaction_ref, (string) $history['payments']->first()->transaction_ref);
    }

    public function test_partial_payments_accumulate_until_the_fee_is_cleared(): void
    {
        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 200,
            'idempotency_key' => 'counter-part-0001',
        ]))->assertRedirect();

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame('300.00', FeeInvoice::query()->firstOrFail()->due_total);

        $this->actingAs($this->accountant)
            ->get(route('fees.counter', [
                'session_id' => $this->session->id,
                'student_id' => $this->student->id,
                'fee_type_id' => $this->tuition->id,
                'month' => 10,
            ]))
            ->assertOk()
            ->assertSee('300.00');

        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 300,
            'idempotency_key' => 'counter-part-0002',
        ]))->assertRedirect();

        $invoice = FeeInvoice::query()->firstOrFail();
        $this->assertSame('500.00', $invoice->paid_total);
        $this->assertSame('0.00', $invoice->due_total);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(2, FeePayment::query()->count());
    }

    public function test_a_payment_above_the_remaining_balance_is_refused(): void
    {
        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 200,
            'idempotency_key' => 'counter-over-0001',
        ]))->assertRedirect();

        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 400,
            'idempotency_key' => 'counter-over-0002',
        ]))->assertSessionHasErrors('amount');

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(200.0, (float) FeePayment::query()->sum('amount'));
        $this->assertSame('300.00', FeeInvoice::query()->firstOrFail()->due_total);
    }

    public function test_a_fully_collected_fee_cannot_be_collected_again(): void
    {
        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 500,
            'idempotency_key' => 'counter-duplicate-0001',
        ]))->assertRedirect();

        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 500,
            'idempotency_key' => 'counter-duplicate-0002',
        ]))->assertSessionHasErrors('amount');

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(1, FeeReceipt::query()->count());
        $this->assertSame(500.0, (float) CashTransaction::query()->where('type', 'income')->sum('amount'));
    }

    public function test_a_zero_or_negative_amount_is_rejected_before_anything_is_written(): void
    {
        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => 0,
            'idempotency_key' => 'counter-zero-0001',
        ]))->assertSessionHasErrors('amount');

        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $this->payload([
            'amount' => -50,
            'idempotency_key' => 'counter-zero-0002',
        ]))->assertSessionHasErrors('amount');

        $this->assertSame(0, FeePayment::query()->count());
        $this->assertSame(0, FeeInvoice::query()->count());
        $this->assertSame(0, CashTransaction::query()->count());
    }

    public function test_a_monthly_fee_needs_a_month(): void
    {
        $payload = $this->payload([
            'amount' => 500,
            'idempotency_key' => 'counter-month-0001',
        ]);

        unset($payload['month']);

        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $payload)
            ->assertSessionHasErrors('month');

        $this->assertSame(0, FeePayment::query()->count());
    }

    public function test_a_non_monthly_fee_is_collected_for_the_whole_session_without_a_month(): void
    {
        $admission = FeeType::query()->create([
            'name' => 'Admission Fee',
            'code' => 'ADM',
            'frequency' => 'one_time',
            'is_active' => true,
        ]);

        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $admission->id,
            'name' => 'One-time admission',
            'amount' => 1000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $payload = $this->payload([
            'fee_type_id' => $admission->id,
            'amount' => 1000,
            'idempotency_key' => 'counter-session-0001',
        ]);

        unset($payload['month']);

        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $payload)->assertRedirect();

        $payment = FeePayment::query()->firstOrFail();
        $this->assertSame('2026-S1', $payment->billing_month);
        $this->assertSame($admission->id, $payment->fee_type_id);

        $this->actingAs($this->accountant)
            ->get(route('fees.counter', [
                'session_id' => $this->session->id,
                'student_id' => $this->student->id,
                'fee_type_id' => $admission->id,
            ]))
            ->assertOk()
            ->assertSee('Whole session')
            ->assertSee('This fee is fully collected');

        // A second collection for the same session-wide fee is refused.
        $this->actingAs($this->accountant)->post(route('fees.counter.store'), $payload)
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, FeePayment::query()->count());
    }

    public function test_the_counter_is_closed_to_admin_and_principal(): void
    {
        $admin = $this->makeUser($this->school, 'school_admin');

        $this->actingAs($admin)->get(route('fees.counter'))->assertForbidden();
        $this->actingAs($admin)->post(route('fees.counter.store'), $this->payload([]))->assertForbidden();

        $this->assertSame(0, FeePayment::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'fee_type_id' => $this->tuition->id,
            'month' => 10,
            'amount' => 500,
            'method' => 'cash',
            'idempotency_key' => 'counter-'.uniqid(),
        ], $overrides);
    }
}
