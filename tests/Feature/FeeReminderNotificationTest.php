<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class FeeReminderNotificationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 09:00:00');

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_due_reminders_reach_the_student_and_guardian_once_without_an_amount(): void
    {
        [$student, $studentUser] = $this->enrolledStudent();
        $guardianUser = $this->makeUser($this->school, 'student');
        $this->attachGuardian($student, $guardianUser, 'Guardian One');

        $invoice = $this->invoice($student, 1750, now()->addDays(2));

        $this->artisan('fees:send-reminders', ['--days' => 3])
            ->assertSuccessful();

        $studentNotification = $studentUser->notifications()->firstOrFail();
        $guardianNotification = $guardianUser->notifications()->firstOrFail();

        $this->assertSame('fees', $studentNotification->category);
        $this->assertSame(route('student.fees.show', $invoice->id), $studentNotification->data['url']);
        $this->assertStringContainsString($invoice->invoice_no, $studentNotification->data['message']);
        $this->assertStringNotContainsString('1,750', $studentNotification->data['message']);
        $this->assertStringNotContainsString('1750', (string) $studentNotification->data['push_body']);

        $this->assertSame('fees', $guardianNotification->category);
        $this->assertSame(route('notifications.index'), $guardianNotification->data['url']);
        $this->assertStringNotContainsString($student->full_name, $guardianNotification->data['message']);
        $this->assertStringNotContainsString($student->full_name, (string) $guardianNotification->data['push_body']);

        $this->artisan('fees:send-reminders', ['--days' => 3])
            ->assertSuccessful();

        $this->assertSame(1, $studentUser->notifications()->count());
        $this->assertSame(1, $guardianUser->notifications()->count());
    }

    public function test_an_overdue_invoice_uses_the_overdue_reminder(): void
    {
        [$student, $studentUser] = $this->enrolledStudent();

        $this->invoice($student, 1200, now()->subDays(2));

        $this->artisan('fees:send-reminders', ['--days' => 3])
            ->assertSuccessful();

        $notification = $studentUser->notifications()->firstOrFail();

        $this->assertSame('Fee payment overdue', $notification->data['title']);
        $this->assertStringContainsString('still open', $notification->data['message']);
        $this->assertStringContainsString('past due', (string) $notification->data['push_body']);
    }

    public function test_future_and_closed_invoices_are_not_reminded(): void
    {
        [$futureStudent, $futureUser] = $this->enrolledStudent(1);
        [$paidStudent, $paidUser] = $this->enrolledStudent(2);

        $this->invoice($futureStudent, 900, now()->addDays(10));
        $this->invoice($paidStudent, 900, now()->addDays(2), 'paid');

        $this->artisan('fees:send-reminders', ['--days' => 3])
            ->assertSuccessful();

        $this->assertSame(0, $futureUser->notifications()->count());
        $this->assertSame(0, $paidUser->notifications()->count());
    }

    /**
     * @return array{0: Student, 1: User}
     */
    private function enrolledStudent(int $roll = 1): array
    {
        $student = Student::factory()->create();
        $user = $this->makeUser($this->school, 'student');

        $student->forceFill(['user_id' => $user->id])->save();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$student, $user];
    }

    private function attachGuardian(Student $student, User $user, string $name): Guardian
    {
        $guardian = Guardian::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'relation' => 'father',
            'phone' => '01710000001',
        ]);

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'school_id' => $student->school_id,
                'is_primary' => true,
                'is_emergency_contact' => false,
            ],
        ]);

        return $guardian;
    }

    private function invoice(Student $student, float $total, Carbon $dueDate, string $status = 'issued'): FeeInvoice
    {
        $enrollment = $student->enrollments()->firstOrFail();
        $closed = $status === 'paid';

        return FeeInvoice::query()->create([
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'invoice_no' => 'INV-'.Str::upper(Str::random(8)),
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => $total,
            'discount_total' => 0,
            'total' => $total,
            'paid_total' => $closed ? $total : 0,
            'due_total' => $closed ? 0 : $total,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }
}
