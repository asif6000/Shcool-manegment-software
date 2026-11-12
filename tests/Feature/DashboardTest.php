<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\CashTransaction;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_reports_live_school_data_only(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->useSchoolContext($school->id);

        $session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);
        SchoolClass::query()->create(['name' => 'Class Seven', 'level' => 7, 'is_active' => true]);

        $students = Student::factory()->count(4)->create(['status' => 'active']);
        Student::factory()->create(['status' => 'inactive']);

        Teacher::factory()->count(2)->create(['status' => 'active']);
        Teacher::factory()->create(['status' => 'inactive']);

        foreach (['present', 'present', 'late', 'absent'] as $index => $status) {
            StudentAttendance::query()->create([
                'student_id' => $students[$index]->id,
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'attendance_date' => now()->toDateString(),
                'status' => $status,
            ]);
        }
        // Yesterday's register must not change today's percentage.
        StudentAttendance::query()->create([
            'student_id' => $students[0]->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'status' => 'absent',
        ]);

        $this->makeAdmission($session, $class, 'APP-1', Admission::PENDING);
        $this->makeAdmission($session, $class, 'APP-2', Admission::PENDING);
        $this->makeAdmission($session, $class, 'APP-3', Admission::WAITLISTED);
        $this->makeAdmission($session, $class, 'APP-4', Admission::APPROVED, $admin);

        $this->makeInvoice($students[0], $session, 'INV-1', [
            'total' => 1000,
            'paid_total' => 400,
            'due_total' => 600,
            'status' => 'partial',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);
        $this->makeInvoice($students[1], $session, 'INV-2', [
            'total' => 500,
            'paid_total' => 0,
            'due_total' => 500,
            'status' => 'issued',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        FeePayment::query()->create([
            'student_id' => $students[0]->id,
            'amount' => 400,
            'method' => 'cash',
            'paid_at' => now(),
            'status' => 'posted',
            'received_by' => $admin->id,
            'idempotency_key' => 'dash-test-1',
        ]);
        FeePayment::query()->create([
            'student_id' => $students[0]->id,
            'amount' => 250,
            'method' => 'cash',
            'paid_at' => now()->subMonthNoOverflow(),
            'status' => 'posted',
            'received_by' => $admin->id,
            'idempotency_key' => 'dash-test-2',
        ]);

        CashTransaction::query()->create([
            'type' => 'income',
            'amount' => 400,
            'transaction_date' => now()->toDateString(),
            'description' => 'Fee receipt',
        ]);
        CashTransaction::query()->create([
            'type' => 'expense',
            'amount' => 150,
            'transaction_date' => now()->toDateString(),
            'description' => 'Stationery',
        ]);
        CashTransaction::query()->create([
            'type' => 'income',
            'amount' => 250,
            'transaction_date' => now()->subMonthNoOverflow()->toDateString(),
            'description' => 'Fee receipt',
        ]);
        // A cancelled invoice never counts towards dues.
        $this->makeInvoice($students[2], $session, 'INV-3', [
            'total' => 900,
            'paid_total' => 0,
            'due_total' => 900,
            'status' => 'cancelled',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $exam = Exam::query()->create([
            'academic_session_id' => $session->id,
            'name' => 'Half Yearly 2026',
            'exam_type' => 'half_yearly',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'status' => 'draft',
        ]);
        $subject = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'core', 'is_active' => true]);
        ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'exam_date' => now()->addDays(2)->toDateString(),
            'full_marks' => 100,
            'pass_marks' => 40,
        ]);
        Exam::query()->create([
            'academic_session_id' => $session->id,
            'name' => 'Past Term',
            'exam_type' => 'term',
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(15)->toDateString(),
            'status' => 'published',
        ]);

        Notice::query()->create([
            'title' => 'Live announcement',
            'body' => 'Body',
            'audience' => 'all',
            'published_at' => now()->subHour(),
            'is_pinned' => true,
        ]);
        Notice::query()->create([
            'title' => 'Draft announcement',
            'body' => 'Body',
            'audience' => 'all',
            'published_at' => null,
        ]);
        Notice::query()->create([
            'title' => 'Expired announcement',
            'body' => 'Body',
            'audience' => 'all',
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        // Another school's records must never leak into this dashboard.
        $other = $this->makeSchool(['name' => 'Other School']);
        $this->useSchoolContext($other->id);
        Student::factory()->create(['status' => 'active']);
        $this->useSchoolContext($school->id);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(4, $stats['students']);
        $this->assertSame(2, $stats['teachers']);
        $this->assertSame(2, $stats['team']);
        $this->assertSame(2, $stats['classes']);

        $attendance = $response->viewData('attendance');
        $this->assertSame(4, $attendance['marked']);
        $this->assertSame(3, $attendance['present']);
        $this->assertSame(1, $attendance['absent']);
        $this->assertSame(0, $attendance['leave']);
        $this->assertSame(75.0, $attendance['percent']);
        $this->assertSame(0.0, $attendance['yesterdayPercent']);
        $this->assertCount(1, $attendance['classes']);
        $this->assertSame('Class Six', $attendance['classes']->first()['class']);
        $this->assertSame(3, $attendance['classes']->first()['present']);
        $this->assertSame(1, $attendance['classes']->first()['absent']);
        $this->assertSame(75.0, $attendance['classes']->first()['percent']);

        $admissions = $response->viewData('admissions');
        $this->assertSame(3, $admissions['pending']);
        $this->assertSame(4, $admissions['thisMonth']);
        $this->assertSame(1, $admissions['approvedThisMonth']);

        $fees = $response->viewData('fees');
        $this->assertSame(400.0, $fees['collected']);
        $this->assertSame(250.0, $fees['previousCollected']);
        $this->assertSame(60.0, $fees['growth']);
        $this->assertSame(1100.0, $fees['outstanding']);
        $this->assertSame(0.0, $fees['outstandingPrevious']);
        $this->assertSame(500.0, $fees['overdue']);
        $this->assertSame(1, $fees['overdueInvoices']);
        $this->assertSame(150.0, $fees['expense']);
        $this->assertSame(0.0, $fees['previousExpense']);
        $this->assertSame(250.0, $fees['net']);
        $this->assertCount(2, $fees['recentPayments']);
        $this->assertCount(12, $fees['monthLabels']);
        $this->assertSame(400.0, $fees['monthCollection'][now()->month - 1]);
        $this->assertSame(150.0, $fees['monthExpense'][now()->month - 1]);

        $schedule = $response->viewData('schedule');
        $this->assertCount(1, $schedule);
        $this->assertSame('Half Yearly 2026', $schedule->first()['title']);
        $this->assertSame('exam', $schedule->first()['type']);

        $notices = $response->viewData('notices');
        $this->assertCount(1, $notices);
        $this->assertSame('Live announcement', $notices->first()['title']);
        $this->assertSame('high', $notices->first()['priority']);

        $response->assertSee('Live announcement');
        $response->assertSee('Half Yearly 2026');
        $response->assertSee(e(__('Total Students')));
        $response->assertSee(e(__('Monthly Fee Collection')));
        $response->assertSee(e(__('Outstanding Fees')));
        $response->assertSee(e(__('Pending Admissions')));
        $response->assertSee(e(__('Recent Fee Payments')));
    }

    public function test_dashboard_without_records_shows_honest_empty_states(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $this->assertNull($response->viewData('attendance')['percent']);
        $this->assertNull($response->viewData('attendance')['growth']);
        $this->assertCount(0, $response->viewData('attendance')['classes']);
        $this->assertSame(0.0, $response->viewData('fees')['outstanding']);
        $this->assertCount(0, $response->viewData('fees')['recentPayments']);
        // The yearly chart keeps its 12 axis labels; every series stays at zero.
        $this->assertCount(12, $response->viewData('fees')['monthLabels']);
        $this->assertSame([], array_filter($response->viewData('fees')['monthCollection']));
        $this->assertSame([], array_filter($response->viewData('fees')['monthExpense']));
        $this->assertCount(0, $response->viewData('schedule'));
        $this->assertCount(0, $response->viewData('notices'));

        $response->assertSee(e(__('Attendance not marked yet today')));
        $response->assertSee(e(__('No exam is scheduled ahead.')));
        $response->assertSee(e(__('No notice has been published yet.')));
        $response->assertSee(e(__('Expense entries will appear here once they are recorded in the accounts module.')));
    }

    public function test_financial_and_admission_widgets_are_hidden_from_teachers(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        $response = $this->actingAs($teacher)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.teacher');
        $response->assertViewMissing('fees');
        $response->assertViewMissing('admissions');
        $response->assertDontSee(e(__('Outstanding Fees')));
        $response->assertDontSee(e(__('Pending Admissions')));
        $response->assertDontSee(e(__('Monthly Fee Collection')));

        // A teacher with no periods on the timetable gets an honest empty state
        // instead of school-wide numbers.
        $this->assertSame(0, $response->viewData('cards')['classes']);
        $this->assertSame(0, $response->viewData('cards')['students']);
        $response->assertSee(e(__('No class is assigned to you yet')));
    }

    public function test_student_accounts_are_redirected_to_their_own_workspace(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser($school, 'student');

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertRedirect();
        $this->assertNotSame(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_dashboard_requires_the_dashboard_permission(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        Role::findByName('teacher', 'web')->revokePermissionTo('dashboard.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($teacher->fresh())->get(route('dashboard'))->assertForbidden();
    }

    private function makeAdmission(
        AcademicSession $session,
        SchoolClass $class,
        string $number,
        string $status,
        ?User $reviewer = null,
    ): Admission {
        return Admission::query()->create([
            'academic_session_id' => $session->id,
            'applied_class_id' => $class->id,
            'application_no' => $number,
            'first_name' => 'Applicant',
            'last_name' => $number,
            'gender' => 'female',
            'date_of_birth' => '2014-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '01700000000',
            'status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewer !== null ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(Student $student, AcademicSession $session, string $number, array $overrides): FeeInvoice
    {
        return FeeInvoice::query()->create(array_merge([
            'academic_session_id' => $session->id,
            'student_id' => $student->id,
            'invoice_no' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => $overrides['total'] ?? 0,
            'total' => 0,
            'paid_total' => 0,
            'due_total' => 0,
            'status' => 'issued',
        ], $overrides));
    }
}
