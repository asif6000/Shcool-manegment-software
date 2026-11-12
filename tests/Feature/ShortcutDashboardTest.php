<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The shortcut grid is the small-screen entry point, so every tile has to be
 * a screen the signed-in account can actually open, and the summary has to be
 * the school's real money rather than a placeholder.
 */
class ShortcutDashboardTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
    }

    public function test_the_school_admin_sees_admin_tiles_and_the_real_pending_total(): void
    {
        $admin = $this->makeUser($this->school, 'school_admin');
        $student = Student::factory()->create(['status' => 'active']);

        $this->invoice($student, 'INV-1', 'issued', 500);
        $this->invoice($student, 'INV-2', 'partial', 250);
        $this->invoice($student, 'INV-3', 'paid', 0);

        $this->actingAs($admin)
            ->get(route('shortcuts.index'))
            ->assertOk()
            ->assertSee('Add Student')
            ->assertSee('Student List')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total_students'] === 1
                && (float) $summary['pending_fees'] === 750.0);
    }

    public function test_the_accountant_gets_the_accounts_tiles_only(): void
    {
        $accountant = $this->makeUser($this->school, 'accountant');

        $this->actingAs($accountant)
            ->get(route('shortcuts.index'))
            ->assertOk()
            ->assertSee('Fee Collection')
            ->assertSee('Cashbook')
            ->assertDontSee('Add Student')
            ->assertDontSee('Add Teacher');
    }

    public function test_a_teacher_gets_the_teaching_tiles_only(): void
    {
        $teacher = $this->makeUser($this->school, 'teacher');

        $this->actingAs($teacher)
            ->get(route('shortcuts.index'))
            ->assertOk()
            ->assertSee('Homework')
            ->assertSee('Classwork')
            ->assertDontSee('Fee Collection')
            ->assertDontSee('Add Student');
    }

    private function invoice(Student $student, string $number, string $status, float $due): void
    {
        FeeInvoice::query()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'invoice_no' => $number,
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'subtotal' => $due + 100,
            'total' => $due + 100,
            'paid_total' => 100,
            'due_total' => $due,
            'status' => $status,
        ]);
    }
}
