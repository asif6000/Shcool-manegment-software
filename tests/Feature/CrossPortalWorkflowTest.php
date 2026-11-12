<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Models\CashTransaction;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeReceipt;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\MarkSubmission;
use App\Models\Notice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * One record set, five audiences. The office admits a pupil, assigns the
 * period, bills and publishes; the teacher works the period; the student sees
 * only their own published record; the accountant collects the money. Every
 * step goes through the real HTTP route, and every visibility claim is checked
 * against both the database and the receiving portal.
 *
 * The guardian side is a directory entry, not a sign-in portal: guardians hold
 * no role and no route. That is asserted where it matters (the link exists)
 * and reported as an unavailable portal rather than pretended.
 */
class CrossPortalWorkflowTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $accountant;

    private User $teacherUser;

    private Teacher $teacher;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 10:00:00');

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin', ['email' => 'admin@audit.test']);
        $this->accountant = $this->makeUser($this->school, 'accountant');
        $this->teacherUser = $this->makeUser($this->school, 'teacher');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);

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

        $this->sectionA = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->sectionB = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'B',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->math = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'type' => 'theory',
            'is_active' => true,
        ]);

        // The office assigns the subject to the teacher; nothing else gives the
        // teacher the right to touch this class.
        $this->actingAs($this->admin)
            ->post(route('timetable.store'), [
                'session' => $this->session->id,
                'class' => $this->class->id,
                'section' => $this->sectionA->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->math->id,
                'teacher_id' => $this->teacher->id,
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '09:40',
            ])
            ->assertSessionHasNoErrors();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_one_record_set_stays_in_sync_across_the_admin_teacher_student_and_accountant_portals(): void
    {
        // ---- Admin admits a pupil: profile, guardian link, mobile login, enrollment.
        $student = $this->admit('Ayesha', '01712345678', $this->sectionA, 1);
        $studentUser = $student->user()->firstOrFail();

        $this->assertSame('01712345678', $studentUser->mobile_number);
        $this->assertNull($studentUser->email);
        $this->assertFalse($studentUser->must_change_password);
        $this->assertTrue($studentUser->hasRole('student'));

        $guardian = Guardian::query()->where('phone', '01798765432')->sole();
        $this->assertTrue($guardian->students->contains($student->id));
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'status' => 'active',
        ]);

        // ---- The student signs in with the mobile number and password only.
        // The office session from the admission form is dropped first: the
        // sign-in screen sits behind the guest middleware.
        $this->app['auth']->forgetGuards();

        $this->post(route('student.login.store'), [
            'mobile_number' => '01712345678',
            'password' => 'Student@2026',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($studentUser);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Ayesha');

        // ---- Teacher writes and publishes homework and classwork from the one
        // combined screen: a single save files both kinds against one period.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), [
                'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->math->id,
                'academic_session_id' => $this->session->id,
                'work_type' => 'both',
                'title' => 'Fractions worksheet',
                'classwork' => 'Cut fraction circles and compared the pieces.',
                'homework' => 'Complete exercise 4.2.',
                'instructions' => 'Show every step.',
                'assigned_date' => '2026-10-05',
                'due_date' => '2026-10-08',
                'publish' => true,
            ])
            ->assertSessionHasNoErrors();

        $homework = Homework::query()
            ->where('title', 'Fractions worksheet')
            ->where('category', Homework::CATEGORY_HOMEWORK)
            ->sole();
        $classwork = Homework::query()
            ->where('title', 'Fractions worksheet')
            ->where('category', Homework::CATEGORY_CLASSWORK)
            ->sole();

        foreach ([$homework, $classwork] as $row) {
            $this->assertSame(Homework::PUBLISHED, $row->status);
            $this->assertSame($this->teacher->id, (int) $row->teacher_id);
            $this->assertSame($this->sectionA->id, (int) $row->section_id);
        }

        $this->actingAs($this->teacherUser)
            ->post(route('materials.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->math->id,
                'kind' => StudyMaterial::CLASSWORK,
                'title' => 'Fraction circles in class',
                'description' => 'Cut and compare the circles.',
                'work_date' => '2026-10-05',
                'publish' => true,
            ])
            ->assertSessionHasNoErrors();

        $classwork = StudyMaterial::query()->where('title', 'Fraction circles in class')->sole();
        $this->assertSame(StudyMaterial::PUBLISHED, $classwork->status);

        // The student reads both, in their own portal, immediately on refresh.
        $this->actingAs($studentUser)
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertSee('Fractions worksheet');

        // Each tab of the combined student screen carries its own kind.
        $this->actingAs($studentUser)
            ->get(route('student.homework.index', ['category' => Homework::CATEGORY_CLASSWORK]))
            ->assertOk()
            ->assertSee('Cut fraction circles');

        $this->actingAs($studentUser)
            ->get(route('student.homework.index', ['category' => Homework::CATEGORY_HOMEWORK]))
            ->assertOk()
            ->assertSee('Complete exercise 4.2.');

        $this->actingAs($studentUser)
            ->get(route('student.materials.index', ['kind' => 'classwork']))
            ->assertOk()
            ->assertSee('Fraction circles in class');

        // ---- The student answers the homework; the teacher reviews it and
        // returns marks and feedback to the same student.
        $this->actingAs($studentUser)
            ->post(route('student.homework.submit', $homework), [
                'text_answer' => '1/4 + 2/4 = 3/4',
            ])
            ->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()->sole();
        $this->assertSame($student->id, (int) $submission->student_id);
        $this->assertSame('submitted', $submission->status);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.submissions.review', $submission), [
                'marks' => 9,
                'feedback' => 'Clear working. Watch the last step.',
            ])
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame('reviewed', $submission->status);
        $this->assertSame($this->teacherUser->id, (int) $submission->reviewed_by);

        $this->actingAs($studentUser)
            ->get(route('student.homework.show', $homework))
            ->assertOk()
            ->assertSee('Clear working. Watch the last step.');

        // The office cannot write or publish either record.
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'subject_id' => $this->math->id,
                'title' => 'Office homework',
                'description' => 'Should never exist.',
                'assigned_date' => '2026-10-05',
                'due_date' => '2026-10-08',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('teacher.homework.publish', $homework))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('teacher.homework.submissions.review', $submission), ['marks' => 10])
            ->assertForbidden();

        // Neither the take-home nor the class note was duplicated by the office.
        $this->assertSame(2, Homework::query()->count());

        // ---- Teacher marks the register; a repeat submit updates, never duplicates.
        $attendancePayload = [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'attendance_date' => '2026-10-05',
            'statuses' => [$student->id => 'present'],
        ];

        $this->actingAs($this->teacherUser)
            ->post(route('attendance.students.store'), $attendancePayload)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacherUser)
            ->post(route('attendance.students.store'), $attendancePayload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, StudentAttendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_date', '2026-10-05')
            ->count());

        $this->actingAs($this->teacherUser)
            ->post(route('attendance.submissions.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'attendance_date' => '2026-10-05',
            ])
            ->assertSessionHasNoErrors();

        $attendanceSubmission = AttendanceSubmission::query()->sole();
        $this->assertSame(AttendanceSubmission::STATUS_SUBMITTED, $attendanceSubmission->status);

        $this->actingAs($this->admin)
            ->post(route('attendance.submissions.decide', [$attendanceSubmission, 'approve']))
            ->assertRedirect();

        $this->assertSame(AttendanceSubmission::STATUS_APPROVED, $attendanceSubmission->refresh()->status);

        $attendancePage = $this->actingAs($studentUser)
            ->get(route('student.attendance', ['month' => '2026-10']))
            ->assertOk();

        $this->assertSame(1, $attendancePage->viewData('summary')['counts']['present']);

        // ---- Admin builds an exam; teacher enters and hands over; admin approves and publishes.
        $this->actingAs($this->admin)
            ->post(route('exams.store'), [
                'academic_session_id' => $this->session->id,
                'name' => 'First Term',
                'exam_type' => 'term',
                'start_date' => '2026-10-06',
                'end_date' => '2026-10-08',
            ])
            ->assertSessionHasNoErrors();

        $exam = Exam::query()->where('name', 'First Term')->sole();

        $this->actingAs($this->admin)
            ->post(route('exams.papers.store', $exam), [
                'class_id' => $this->class->id,
                'subject_id' => $this->math->id,
                'exam_date' => '2026-10-06',
                'full_marks' => 100,
                'pass_marks' => 40,
            ])
            ->assertSessionHasNoErrors();

        $paper = $exam->papers()->sole();

        $this->actingAs($this->teacherUser)
            ->post(route('exams.marks.store', $exam), [
                'paper_id' => $paper->id,
                'section_id' => $this->sectionA->id,
                'marks' => [
                    $student->id => ['marks_obtained' => 85, 'is_absent' => false],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacherUser)
            ->post(route('exams.marks.submit', $exam), [
                'paper_id' => $paper->id,
                'section_id' => $this->sectionA->id,
            ])
            ->assertSessionHasNoErrors();

        $markSubmission = MarkSubmission::query()->sole();
        $this->assertSame(MarkSubmission::STATUS_SUBMITTED, $markSubmission->status);

        // Before approval and publication the student must not see the exam.
        $this->actingAs($studentUser)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertDontSee('First Term');

        $this->actingAs($this->admin)
            ->post(route('marks.submissions.decide', [$markSubmission, 'approve']), ['note' => 'Checked against the key.'])
            ->assertRedirect();

        $this->assertSame(MarkSubmission::STATUS_APPROVED, $markSubmission->refresh()->status);

        $this->actingAs($this->admin)
            ->post(route('exams.results.publish', $exam))
            ->assertRedirect();

        $this->assertTrue($exam->refresh()->isPublished());

        $this->actingAs($studentUser)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee('First Term');

        // ---- Admin bills the pupil; the accountant collects at the counter.
        $tuition = FeeType::query()->create([
            'name' => 'Tuition Fee',
            'code' => 'TUI',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $tuition->id,
            'name' => 'Monthly tuition',
            'amount' => 500,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'fee_type_ids' => [$tuition->id],
                'student_ids' => [$student->id],
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
                'billing_period' => '2026-10',
            ])
            ->assertSessionHasNoErrors();

        $invoice = FeeInvoice::query()->where('student_id', $student->id)->sole();
        $this->assertSame(500.0, (float) $invoice->due_total);

        $collection = $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => 500],
                'method' => 'cash',
                'paid_at' => '2026-10-05',
                'idempotency_key' => 'audit-collection-1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(500.0, (float) FeePayment::query()->sole()->amount);
        $collection->assertRedirect(route('fees.receipt', FeePayment::query()->sole()));

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->due_total);
        $this->assertSame('paid', $invoice->status);

        // The same counter submission cannot book the money twice.
        $replay = $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => 500],
                'method' => 'cash',
                'paid_at' => '2026-10-05',
                'idempotency_key' => 'audit-collection-1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FeePayment::query()->count());
        $replay->assertRedirect(route('fees.receipt', FeePayment::query()->sole()));

        // A closed invoice cannot take a second, differently keyed payment.
        $this->actingAs($this->accountant)
            ->post(route('fees.collect.store'), [
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'picked' => [$invoice->id],
                'amounts' => [$invoice->id => 100],
                'method' => 'cash',
                'paid_at' => '2026-10-05',
                'idempotency_key' => 'audit-collection-2',
            ])
            ->assertSessionHasErrors('amounts');

        $this->assertSame(1, FeePayment::query()->count());

        $this->actingAs($studentUser)
            ->get(route('student.fees.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_no);

        $this->actingAs($studentUser)
            ->get(route('student.fees.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_no);

        // ---- The payment issued exactly one permanent receipt; the accountant
        // can preview, print, reprint and search it without booking new money.
        $payment = FeePayment::query()->sole();
        $receipt = $payment->receipt;

        $this->assertNotNull($receipt);
        $this->assertSame(1, FeeReceipt::query()->count());
        $this->assertSame($payment->id, (int) $receipt->fee_payment_id);
        $this->assertSame($this->school->id, (int) $receipt->school_id);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', $payment))
            ->assertOk()
            ->assertSee('Invoice preview')
            ->assertSee($receipt->receipt_no)
            ->assertSee('Download PDF')
            ->assertSee('Reprint')
            ->assertSee('Search invoices')
            ->assertSee('receipt-paper-a4', false)
            ->assertSee('size: A4', false);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', [$payment, 'paper' => 'a5']))
            ->assertOk()
            ->assertSee('receipt-paper-a5', false)
            ->assertSee('size: A5', false);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', [$payment, 'paper' => '80mm']))
            ->assertOk()
            ->assertSee('receipt-paper-thermal', false)
            ->assertSee('size: 80mm 297mm', false);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', [$payment, 'paper' => 'legal']))
            ->assertOk()
            ->assertSee('receipt-paper-a4', false);

        // A reprint is a read of the original: same number, no new payment.
        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', [$payment, 'paper' => 'a4', 'print' => 1]))
            ->assertOk()
            ->assertSee($receipt->receipt_no);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipt', [$payment, 'paper' => 'a5', 'print' => 1]))
            ->assertOk()
            ->assertSee($receipt->receipt_no);

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(1, FeeReceipt::query()->count());
        $this->assertSame($receipt->receipt_no, $payment->refresh()->receipt->receipt_no);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipts', ['search' => $receipt->receipt_no]))
            ->assertOk()
            ->assertSee($receipt->receipt_no);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipts', ['search' => $invoice->invoice_no]))
            ->assertOk()
            ->assertSee($receipt->receipt_no);

        $this->actingAs($this->accountant)
            ->get(route('fees.receipts', ['search' => $student->student_id]))
            ->assertOk()
            ->assertSee($receipt->receipt_no);

        $this->actingAs($this->teacherUser)
            ->get(route('fees.receipt', $payment))
            ->assertForbidden();

        // ---- Accountant records an expense and reconciles the cashbook and KPIs.
        $this->actingAs($this->accountant)
            ->post(route('expenses.categories.store'), [
                'name' => 'Utilities',
                'code' => 'UTIL',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $category = ExpenseCategory::query()->where('code', 'UTIL')->sole();

        $this->actingAs($this->accountant)
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'title' => 'Electricity bill',
                'amount' => 300,
                'expense_date' => '2026-10-05',
                'payment_method' => 'cash',
            ])
            ->assertSessionHasNoErrors();

        $expense = Expense::query()->where('title', 'Electricity bill')->sole();
        $this->assertSame('paid', $expense->status);
        $this->assertSame(1, CashTransaction::query()
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->count());

        $this->actingAs($this->accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Financial KPIs')
            ->assertSee('Fee Collection')
            ->assertSee('Cash Balance');

        $this->actingAs($this->accountant)
            ->get(route('cashbook.index'))
            ->assertOk()
            ->assertSee('Electricity bill');

        $this->actingAs($this->accountant)
            ->get(route('reports.index'))
            ->assertOk();

        // ---- Admin publishes a notice; the student and the public website see it.
        $this->actingAs($this->admin)
            ->post(route('notices.store'), [
                'title' => 'School reopens on Sunday',
                'body' => 'All classes resume at 8:00 am.',
                'audience' => 'students',
            ])
            ->assertSessionHasNoErrors();

        $notice = Notice::query()->where('title', 'School reopens on Sunday')->sole();

        $this->actingAs($this->admin)
            ->post(route('notices.publish', $notice), ['notify_audience' => true])
            ->assertRedirect();

        $this->assertNotNull($notice->refresh()->published_at);

        $this->actingAs($studentUser)
            ->get(route('student.notices.index'))
            ->assertOk()
            ->assertSee('School reopens on Sunday');

        $this->get(route('public.notices.index'))
            ->assertOk()
            ->assertSee('School reopens on Sunday');
    }

    public function test_teacher_scope_and_student_isolation_hold_across_portals(): void
    {
        $studentA = $this->admit('Ayesha', '01712345678', $this->sectionA, 1);
        $studentB = $this->admit('Bilal', '01712345679', $this->sectionB, 2);

        $teacherBUser = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $teacherBUser->id]);

        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), [
                'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->math->id,
                'academic_session_id' => $this->session->id,
                'work_type' => 'homework',
                'title' => 'Section A only',
                'homework' => 'Visible to section A.',
                'assigned_date' => '2026-10-05',
                'due_date' => '2026-10-08',
                'publish' => true,
            ])
            ->assertSessionHasNoErrors();

        $homework = Homework::query()->where('title', 'Section A only')->sole();

        // A teacher without the period cannot read it (a hard 403) and also
        // cannot file work against it (the form only offers their own periods).
        $this->actingAs($teacherBUser)
            ->get(route('teacher.homework.show', $homework))
            ->assertForbidden();

        // The form only ever offers their own periods, and a hand-made request
        // against someone else's period is refused by the same timetable rule
        // before anything is written.
        $this->actingAs($teacherBUser)
            ->post(route('teacher.homework.store'), [
                'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->math->id,
                'academic_session_id' => $this->session->id,
                'work_type' => 'homework',
                'title' => 'Not mine',
                'homework' => 'Should be refused.',
                'assigned_date' => '2026-10-05',
                'due_date' => '2026-10-08',
            ])
            ->assertSessionHasErrors('period');

        $this->assertDatabaseMissing('homework', ['title' => 'Not mine']);

        // A pupil in another section never sees it.
        $this->actingAs($studentB->user()->firstOrFail())
            ->get(route('student.homework.index'))
            ->assertOk()
            ->assertDontSee('Section A only');

        // Billing for one pupil is not readable by another.
        $tuition = FeeType::query()->create([
            'name' => 'Tuition Fee',
            'code' => 'TUI',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        FeeStructure::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_type_id' => $tuition->id,
            'name' => 'Monthly tuition',
            'amount' => 500,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('fees.generate'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->sectionA->id,
                'fee_type_ids' => [$tuition->id],
                'student_ids' => [$studentA->id],
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-10',
                'billing_period' => '2026-10',
            ])
            ->assertSessionHasNoErrors();

        $invoice = FeeInvoice::query()->where('student_id', $studentA->id)->sole();

        $this->actingAs($studentB->user()->firstOrFail())
            ->get(route('student.fees.show', $invoice))
            ->assertNotFound();

        // Workspace boundaries: staff-only screens refuse pupils, and the office
        // refuses the learning screens the teacher owns.
        $this->actingAs($studentB->user()->firstOrFail())
            ->getJson(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->get(route('teacher.homework.index'))
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->get(route('fees.collect'))
            ->assertForbidden();

        // Guardian records are links, not accounts: no guardian role can sign in.
        $guardian = Guardian::query()->where('phone', '01798765432')->sole();
        $this->assertTrue($guardian->students->contains($studentA->id));
        $this->assertFalse(array_key_exists('guardian', (array) config('school.roles')));
    }

    public function test_the_json_api_and_the_web_portals_read_the_same_database(): void
    {
        $this->admit('Ayesha', '01712345678', $this->sectionA, 1);

        // No token, no data. The admission left a web guard on the test client,
        // so it is cleared before asking the API as an anonymous caller.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
            'device_name' => 'audit-script',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $token = (string) $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['school', 'stats']]);

        // The same admitted pupil is the one the web register shows.
        $this->actingAs($this->admin)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Ayesha');
    }

    private function admit(string $firstName, string $mobile, Section $section, int $roll): Student
    {
        $this->actingAs($this->admin)
            ->post(route('students.store'), [
                'first_name' => $firstName,
                'last_name' => 'Audit',
                'gender' => 'female',
                'date_of_birth' => '2014-02-01',
                'status' => 'active',
                'admission_date' => '2026-01-02',
                'phone' => $mobile,
                'password' => 'Student@2026',
                'password_confirmation' => 'Student@2026',
                'guardian_name' => 'Karim Audit',
                'guardian_relation' => 'father',
                'guardian_phone' => '01798765432',
                'present_address' => '12 Green Road, Dhaka',
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $section->id,
                'roll_no' => $roll,
            ])
            ->assertSessionHasNoErrors();

        return Student::query()->where('first_name', $firstName)->sole();
    }
}
