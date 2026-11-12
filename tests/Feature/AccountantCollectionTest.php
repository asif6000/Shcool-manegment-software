<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountantCollectionService;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use App\Support\Money;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Accountant management and collection monitoring.
 *
 * The roster must show real collectors and real posted money, must never count a
 * held or reversed receipt as a collection, and must not let one account holder
 * read another's figures. Every number here is the same one the cashbook and the
 * money reports already publish, so these tests also assert the screens reconcile.
 */
class AccountantCollectionTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private FeeType $tuition;

    private User $accountant;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 12:00:00');

        $this->seed(RolePermissionSeeder::class);

        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $this->accountant = $this->makeUser($school, 'accountant');
        $this->admin = $this->makeUser($school, 'school_admin');

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_admin_sees_every_collector_with_their_real_posted_totals(): void
    {
        $this->collect($this->invoice('2026-10'), 400, '2026-10-05');
        $this->collect($this->invoice('2026-09'), 250, '2026-09-04');

        $page = $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSeeText($this->accountant->name)
            ->assertSee(Money::format(650))
            ->assertSee(Money::format(400));

        $this->assertStringNotContainsString('6500', $page->getContent());
    }

    public function test_an_admin_can_create_an_accountant_from_the_accountant_module(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('accountants.create'), false)
            ->assertSee('Add accountant');

        // The form asks for the mobile number and nothing else that identifies
        // the person: no email, no phone, no role picker.
        $form = $this->actingAs($this->admin)
            ->get(route('accountants.create'))
            ->assertOk()
            ->assertSee('Create accountant')
            ->assertSee('name="mobile_number"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="role"', false);

        $this->assertStringNotContainsString('Email address', $form->getContent());

        $this->actingAs($this->admin)
            ->post(route('accountants.store'), [
                'name' => 'Finance Desk',
                'mobile_number' => '01744000041',
                'password' => 'Finance@2026',
                'password_confirmation' => 'Finance@2026',
            ])
            ->assertRedirect(route('accountants.index'))
            ->assertSessionHasNoErrors();

        $created = User::query()->where('mobile_number', '01744000041')->firstOrFail();

        $this->assertSame($this->admin->school_id, $created->school_id);
        $this->assertNull($created->email);
        $this->assertTrue($created->hasRole('accountant'));
        $this->assertTrue($created->is_active);
        $this->assertTrue($created->must_change_password);

        // The accountant signs in with the mobile number, so the new login works.
        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'role' => 'accountant',
            'mobile_number' => '01744000041',
            'password' => 'Finance@2026',
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticated();

        $this->actingAs($this->accountant)
            ->get(route('accountants.create'))
            ->assertForbidden();
    }

    public function test_a_held_or_reversed_receipt_is_never_counted_as_collected(): void
    {
        $held = $this->invoice('2026-10');
        $this->actingAs($this->accountant)->post(route('fees.payments.store', $held), [
            'amount' => 400,
            'method' => 'bkash',
            'paid_at' => '2026-10-05',
            'transaction_ref' => 'BX-1',
            'hold_for_verification' => '1',
            'idempotency_key' => 'acct-held',
        ])->assertSessionHasNoErrors();

        // The deletion dialog on this page lists what would be removed, so the
        // assertion is about the collection figures the roster computes.
        $page = $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSeeText($this->accountant->name);

        $stats = $page->viewData('accountants')->first()->collection_stats;
        $this->assertSame(0.0, $stats['total']);
        $this->assertSame(0, $stats['payments']);

        $posted = $this->collect($this->invoice('2026-09'), 300, '2026-09-04');
        app(FeePaymentService::class)->reverse($this->accountant, $posted, 'Paid against the wrong student');

        $page = $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSeeText($this->accountant->name);

        $stats = $page->viewData('accountants')->first()->collection_stats;
        $this->assertSame(0.0, $stats['total']);
        $this->assertSame(0.0, $stats['today']);
        $this->assertSame(0.0, $stats['month']);
        $this->assertSame(0, $stats['payments']);
    }

    public function test_the_daily_and_monthly_screens_reconcile_with_the_roster(): void
    {
        $this->collect($this->invoice('2026-10'), 400, '2026-10-05');
        $this->collect($this->invoice('2026-09'), 250, '2026-09-04');

        $daily = $this->actingAs($this->admin)
            ->get(route('accountants.daily', ['from' => '2026-09-01', 'to' => '2026-10-05']))
            ->assertOk()
            ->assertSee('05 Oct 2026')
            ->assertSee(Money::format(400))
            ->assertSee(Money::format(650));

        $monthly = $this->actingAs($this->admin)
            ->get(route('accountants.monthly', ['from' => '2026-01-01', 'to' => '2026-10-05']))
            ->assertOk()
            ->assertSee(Money::format(400))
            ->assertSee(Money::format(250))
            ->assertSee(Money::format(650));

        $this->assertSame(650.0, $this->rosterTotalFor($this->accountant));
        $daily->assertSee($this->accountant->name);
        $monthly->assertSee($this->accountant->name);
    }

    public function test_the_detail_page_lists_this_collector_only_and_reuses_the_report_figures(): void
    {
        $invoice = $this->invoice('2026-10');
        $this->collect($invoice, 400, '2026-10-05');

        $other = $this->makeSchool();
        $outsider = $this->makeUser($other, 'accountant');

        $this->actingAs($this->admin)
            ->get(route('accountants.show', $this->accountant))
            ->assertOk()
            ->assertSeeText($this->accountant->name)
            ->assertSee(Money::format(400))
            ->assertSee($invoice->invoice_no, false)
            ->assertSee('Class Six')
            ->assertSee('05 Oct 2026')
            ->assertDontSeeText($outsider->name);
    }

    public function test_an_accountant_can_open_their_own_page_but_never_anothers(): void
    {
        $this->collect($this->invoice('2026-10'), 400, '2026-10-05');

        $colleague = $this->makeUser($this->accountant->school, 'accountant');

        $this->actingAs($this->accountant)
            ->get(route('accountants.show', $this->accountant))
            ->assertOk()
            ->assertDontSee('Accountant list');

        $this->actingAs($this->accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Accountant Dashboard')
            ->assertSee("Today's Collection");

        $this->actingAs($this->accountant)
            ->get(route('accountants.show', $colleague))
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->get(route('accountants.index'))
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->get(route('accountants.daily'))
            ->assertForbidden();
    }

    public function test_a_teacher_cannot_reach_the_accountant_module(): void
    {
        $teacher = $this->makeUser($this->accountant->school, 'teacher');

        foreach (['accountants.index', 'accountants.daily', 'accountants.monthly', 'accountants.export'] as $route) {
            $this->actingAs($teacher)->get(route($route))->assertForbidden();
        }

        $this->actingAs($teacher)->get(route('accountants.show', $this->accountant))->assertForbidden();
    }

    public function test_another_schools_collectors_and_money_never_appear(): void
    {
        $this->collect($this->invoice('2026-10'), 400, '2026-10-05');

        $other = $this->makeSchool();
        $outsiderCollector = $this->makeUser($other, 'accountant');
        $outsiderAdmin = $this->makeUser($other, 'school_admin');

        $this->actingAs($this->admin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSeeText($this->accountant->name)
            ->assertDontSeeText($outsiderCollector->name);

        $this->actingAs($outsiderAdmin)
            ->get(route('accountants.index'))
            ->assertOk()
            ->assertSeeText($outsiderCollector->name)
            ->assertDontSeeText($this->accountant->name)
            ->assertDontSee(Money::format(400));

        $this->actingAs($outsiderAdmin)
            ->get(route('accountants.show', $this->accountant))
            ->assertForbidden();
    }

    public function test_the_export_carries_the_same_roster_and_needs_its_own_permission(): void
    {
        $this->collect($this->invoice('2026-10'), 400, '2026-10-05');

        $csv = $this->actingAs($this->admin)->get(route('accountants.export'))->streamedContent();
        $this->assertStringContainsString($this->accountant->name, $csv);
        $this->assertStringContainsString('400.00', $csv);

        $viewer = $this->makeUser($this->accountant->school, 'teacher');
        $viewer->givePermissionTo('accountants.view');

        $this->actingAs($viewer)->get(route('accountants.index'))->assertOk();
        $this->actingAs($viewer)->get(route('accountants.export'))->assertForbidden();

        $daily = $this->actingAs($this->admin)
            ->get(route('accountants.export', ['scope' => 'daily', 'from' => '2026-10-01', 'to' => '2026-10-05']))
            ->streamedContent();
        $this->assertStringContainsString('2026-10-05', $daily);
        $this->assertStringContainsString('400.00', $daily);
    }

    public function test_a_retried_counter_collection_returns_the_same_single_receipt(): void
    {
        $invoice = $this->invoice('2026-10');

        $payload = [
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'picked' => [$invoice->id],
            'amounts' => [$invoice->id => 500],
            'method' => 'cash',
            'paid_at' => '2026-10-05',
            'idempotency_key' => 'counter-retry-1',
        ];

        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), $payload)
            ->assertSessionHasNoErrors();

        // The browser retries the same submission after the first one closed
        // the invoice; the same receipt must come back, not a validation error.
        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(1, \App\Models\FeePaymentAllocation::query()->count());
    }

    private function rosterTotalFor(User $user): float
    {
        $page = app(AccountantCollectionService::class)
            ->roster(Request::create('/admin/accountants', 'GET'), 50);

        foreach ($page as $row) {
            if ($row->id === $user->id) {
                return (float) $row->collection_stats['total'];
            }
        }

        return 0.0;
    }

    private function collect(FeeInvoice $invoice, float $amount, string $paidAt): FeePayment
    {
        $this->actingAs($this->accountant)->post(route('fees.payments.store', $invoice), [
            'amount' => $amount,
            'method' => 'cash',
            'paid_at' => $paidAt,
            'idempotency_key' => 'acct-'.$invoice->id.'-'.str_replace('-', '', $paidAt),
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
}
