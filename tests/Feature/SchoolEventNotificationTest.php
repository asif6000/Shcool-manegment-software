<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\User;
use App\Services\FeePaymentService;
use App\Services\SchoolEventNotifier;
use App\Services\StudentAttendanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class SchoolEventNotificationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);
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
    }

    public function test_absence_notifies_the_student_and_each_linked_guardian_once(): void
    {
        $student = $this->enrolledStudent(['first_name' => 'Ayesha', 'last_name' => 'Rahman']);
        $studentUser = $this->attachPortalAccount($student);
        $guardianUser = $this->makeUser($student->school, 'student');
        $this->attachGuardian($student, $guardianUser, 'Karim Rahman');

        $this->markAttendance($student, 'absent');

        $studentNotification = $studentUser->notifications()->firstOrFail();
        $guardianNotification = $guardianUser->notifications()->firstOrFail();

        $this->assertSame('attendance', $studentNotification->category);
        $this->assertSame(route('student.attendance'), $studentNotification->data['url']);
        $this->assertStringContainsString('02 Oct 2026', $studentNotification->data['message']);

        $this->assertSame('attendance', $guardianNotification->category);
        $this->assertSame(route('notifications.index'), $guardianNotification->data['url']);
        $this->assertStringNotContainsString('Ayesha', $guardianNotification->data['message']);
        $this->assertStringNotContainsString('Rahman', $guardianNotification->data['message']);
        $this->assertStringNotContainsString('Ayesha', (string) $guardianNotification->data['push_body']);

        // Marking the same absence again must not duplicate the alert.
        $this->markAttendance($student, 'absent');

        $this->assertSame(1, $studentUser->notifications()->count());
        $this->assertSame(1, $guardianUser->notifications()->count());
    }

    public function test_only_a_new_absence_triggers_a_notification(): void
    {
        $student = $this->enrolledStudent();
        $studentUser = $this->attachPortalAccount($student);

        $this->markAttendance($student, 'present');
        $this->assertSame(0, $studentUser->notifications()->count());

        $this->markAttendance($student, 'absent');
        $this->assertSame(1, $studentUser->notifications()->count());

        $this->markAttendance($student, 'late');
        $this->assertSame(1, $studentUser->notifications()->count());
        $this->assertSame(1, $studentUser->unreadNotifications()->count());
    }

    public function test_inactive_accounts_are_not_notified(): void
    {
        $student = $this->enrolledStudent();
        $studentUser = $this->attachPortalAccount($student, ['is_active' => false]);
        $guardianUser = $this->makeUser($student->school, 'student', ['is_active' => false]);
        $this->attachGuardian($student, $guardianUser, 'Inactive Guardian');

        $this->markAttendance($student, 'absent');

        $this->assertSame(0, $studentUser->notifications()->count());
        $this->assertSame(0, $guardianUser->notifications()->count());
    }

    public function test_a_guardian_is_not_notified_about_a_student_they_are_not_linked_to(): void
    {
        $absentStudent = $this->enrolledStudent(['first_name' => 'Absent', 'last_name' => 'Child'], 1);
        $otherStudent = $this->enrolledStudent(['first_name' => 'Present', 'last_name' => 'Child'], 2);

        $absentGuardian = $this->makeUser($absentStudent->school, 'student');
        $otherGuardian = $this->makeUser($absentStudent->school, 'student');

        $this->attachGuardian($absentStudent, $absentGuardian, 'Absent Parent');
        $this->attachGuardian($otherStudent, $otherGuardian, 'Other Parent');

        $this->markAttendance($absentStudent, 'absent');
        $this->markAttendance($otherStudent, 'present');

        $this->assertSame(1, $absentGuardian->notifications()->count());
        $this->assertSame(0, $otherGuardian->notifications()->count());
    }

    public function test_one_account_linked_as_both_student_and_guardian_is_notified_twice(): void
    {
        $student = $this->enrolledStudent();
        $shared = $this->attachPortalAccount($student);

        $guardian = Guardian::query()->create([
            'user_id' => $shared->id,
            'name' => 'Shared Account',
            'relation' => 'guardian',
            'phone' => '01710000000',
        ]);

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'school_id' => $student->school_id,
                'is_primary' => true,
                'is_emergency_contact' => false,
            ],
        ]);

        $this->markAttendance($student, 'absent');

        $this->assertSame(1, $shared->notifications()->count());
    }

    public function test_a_fee_payment_notification_omits_the_amount_and_links_to_the_receipt(): void
    {
        $student = $this->enrolledStudent();
        $studentUser = $this->attachPortalAccount($student);

        $invoice = $this->invoice($student, 1500);

        $payment = app(FeePaymentService::class)->collect($this->admin, $invoice, [
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
            'idempotency_key' => 'notification-fee-payment',
        ]);

        $notification = $studentUser->notifications()->firstOrFail();

        $this->assertSame('fees', $notification->category);
        $this->assertSame(route('student.fees.show', $invoice->id), $notification->data['url']);
        $this->assertStringNotContainsString('500', $notification->data['message']);
        $this->assertStringNotContainsString('500', (string) $notification->data['push_body']);

        app(SchoolEventNotifier::class)->feePaymentRecorded($payment);

        $this->assertSame(1, $studentUser->notifications()->count());
    }

    public function test_results_publication_notifies_only_students_with_published_results(): void
    {
        $published = $this->enrolledStudent(['first_name' => 'Published'], 1);
        $draft = $this->enrolledStudent(['first_name' => 'Draft'], 2);

        $publishedUser = $this->attachPortalAccount($published);
        $draftUser = $this->attachPortalAccount($draft);

        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Half Yearly 2026',
            'exam_type' => 'half_yearly',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        StudentResult::query()->create([
            'exam_id' => $exam->id,
            'student_id' => $published->id,
            'total_full_marks' => 500,
            'total_obtained' => 420,
            'gpa' => 4.5,
            'grade_letter' => 'A+',
            'status' => 'published',
            'published_at' => now(),
        ]);

        StudentResult::query()->create([
            'exam_id' => $exam->id,
            'student_id' => $draft->id,
            'total_full_marks' => 500,
            'total_obtained' => 400,
            'gpa' => 4.0,
            'grade_letter' => 'A',
            'status' => 'draft',
        ]);

        app(SchoolEventNotifier::class)->resultsPublished($exam);

        $notification = $publishedUser->notifications()->firstOrFail();

        $this->assertSame('exams', $notification->category);
        $this->assertSame(route('student.results.show', $exam->id), $notification->data['url']);
        $this->assertStringContainsString('Half Yearly 2026', $notification->data['message']);
        $this->assertStringNotContainsString('4.5', $notification->data['message']);
        $this->assertSame(0, $draftUser->notifications()->count());

        app(SchoolEventNotifier::class)->resultsPublished($exam);

        $this->assertSame(1, $publishedUser->notifications()->count());
        $this->assertSame(0, $draftUser->notifications()->count());
    }

    private function enrolledStudent(array $overrides = [], int $roll = 1): Student
    {
        $student = Student::factory()->create($overrides);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return $student;
    }

    private function attachPortalAccount(Student $student, array $overrides = []): User
    {
        $user = $this->makeUser($student->school, 'student', $overrides);

        $student->forceFill(['user_id' => $user->id])->save();

        return $user;
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

    private function markAttendance(Student $student, string $status): void
    {
        app(StudentAttendanceService::class)->mark($this->admin, [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-10-02',
            'statuses' => [$student->id => $status],
        ]);
    }

    private function invoice(Student $student, float $total): FeeInvoice
    {
        $enrollment = $student->enrollments()->firstOrFail();

        return FeeInvoice::query()->create([
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'invoice_no' => 'INV-2026-'.str_pad((string) $student->id, 4, '0', STR_PAD_LEFT),
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'subtotal' => $total,
            'discount_total' => 0,
            'total' => $total,
            'paid_total' => 0,
            'due_total' => $total,
            'status' => 'issued',
            'created_by' => $this->admin->id,
        ]);
    }
}
