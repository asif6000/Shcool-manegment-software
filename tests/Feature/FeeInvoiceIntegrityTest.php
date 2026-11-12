<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class FeeInvoiceIntegrityTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private AcademicSession $session;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->makeUser($this->school, 'school_admin');
        $this->useSchoolContext($this->school->id);

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);
        $this->student = Student::factory()->create(['status' => 'active']);
    }

    public function test_a_student_cannot_have_two_active_invoices_for_the_same_period(): void
    {
        $this->makeInvoice('INV-1');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->makeInvoice('INV-2');
    }

    public function test_cancelled_invoices_do_not_block_the_period(): void
    {
        $this->makeInvoice('INV-1', ['status' => 'cancelled']);

        $this->assertNull(
            DB::table('fee_invoices')->where('invoice_no', 'INV-1')->value('active_billing_key')
        );

        // Re-billing after a cancellation is legitimate.
        $this->makeInvoice('INV-2');

        // And cancelling again still leaves the slot free for the next attempt.
        FeeInvoice::query()->where('invoice_no', 'INV-2')->update(['status' => 'cancelled']);
        $this->makeInvoice('INV-3');

        $this->assertSame(3, FeeInvoice::query()->count());
        $this->assertSame(2, DB::table('fee_invoices')->whereNull('active_billing_key')->count());
    }

    public function test_invoices_without_a_billing_period_are_not_constrained(): void
    {
        $this->makeInvoice('INV-1', ['billing_period' => null]);
        $this->makeInvoice('INV-2', ['billing_period' => null]);

        $this->assertSame(2, FeeInvoice::query()->count());
        $this->assertSame(
            2,
            DB::table('fee_invoices')->whereNull('active_billing_key')->count()
        );
    }

    public function test_other_periods_and_other_students_are_unaffected(): void
    {
        $other = Student::factory()->create(['status' => 'active']);

        $this->makeInvoice('INV-1', ['billing_period' => '2026-10']);
        $this->makeInvoice('INV-2', ['billing_period' => '2026-11']);
        $this->makeInvoice('INV-3', ['student_id' => $other->id, 'billing_period' => '2026-10']);

        $this->assertSame(3, FeeInvoice::query()->count());
    }

    public function test_the_active_billing_key_identifies_school_student_session_and_period(): void
    {
        $this->makeInvoice('INV-1');

        $key = DB::table('fee_invoices')->where('invoice_no', 'INV-1')->value('active_billing_key');

        $this->assertSame(
            implode(':', [$this->school->id, $this->student->id, $this->session->id, '2026-10']),
            $key
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(string $number, array $overrides = []): FeeInvoice
    {
        return FeeInvoice::query()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'invoice_no' => $number,
            'billing_period' => '2026-10',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
            'paid_total' => 0,
            'due_total' => 100,
            'status' => 'issued',
        ], $overrides));
    }
}
