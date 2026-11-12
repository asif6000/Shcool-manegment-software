<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Services\FeePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_portal_shows_the_signed_in_students_own_records(): void
    {
        [$studentUser, $student, $invoice, $payment] = $this->academicFixture();

        $this->actingAs($studentUser)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Ayesha')
            ->assertSee($invoice->invoice_no)
            ->assertSee('Due fees');

        $this->actingAs($studentUser)
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee($student->student_id);

        $this->actingAs($studentUser)
            ->get(route('student.attendance'))
            ->assertOk()
            ->assertSee('Attendance');

        $this->actingAs($studentUser)
            ->get(route('student.fees.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_no);

        $this->actingAs($studentUser)
            ->get(route('student.fees.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_no)
            ->assertSee('Balance due');

        $this->actingAs($studentUser)
            ->get(route('student.fees.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt->receipt_no)
            ->assertSee('Official receipt');
    }

    public function test_student_cannot_open_another_students_invoice_or_receipt(): void
    {
        [$studentUser, , , , $admin] = $this->academicFixture();

        $otherStudent = Student::factory()->create([
            'first_name' => 'Foreign',
            'last_name' => 'Student',
        ]);

        $otherEnrollment = Enrollment::query()->create([
            'student_id' => $otherStudent->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 2,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        $otherInvoice = FeeInvoice::query()->create([
            'academic_session_id' => $this->session->id,
            'student_id' => $otherStudent->id,
            'enrollment_id' => $otherEnrollment->id,
            'invoice_no' => 'INV-2026-9999',
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'subtotal' => 1200,
            'discount_total' => 0,
            'total' => 1200,
            'paid_total' => 0,
            'due_total' => 1200,
            'status' => 'issued',
            'created_by' => $admin->id,
        ]);

        $otherPayment = app(FeePaymentService::class)->collect($admin, $otherInvoice, [
            'amount' => 200,
            'method' => 'cash',
            'paid_at' => now()->subDay()->toDateTimeString(),
            'idempotency_key' => 'portal-foreign-payment',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.fees.show', $otherInvoice))
            ->assertNotFound();

        $this->actingAs($studentUser)
            ->get(route('student.fees.receipt', $otherPayment))
            ->assertNotFound();
    }

    public function test_staff_cannot_enter_the_portal_and_students_cannot_enter_the_admin_panel(): void
    {
        [$studentUser, , , , $admin] = $this->academicFixture();

        // Each workspace sends the wrong visitor to their own home instead of a
        // dead-end 403 page; JSON callers still get the refusal.
        $this->actingAs($admin)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');

        $this->actingAs($studentUser)
            ->get('/admin')
            ->assertRedirect(route('student.dashboard'));

        $this->actingAs($studentUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard'));

        $this->actingAs($admin)
            ->getJson(route('student.dashboard'))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->getJson(route('dashboard'))
            ->assertForbidden();
    }

    public function test_student_login_redirects_to_the_portal(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $studentUser = $this->makeUser($school, 'student', [
            'mobile_number' => '01744000011',
            'password' => 'password',
        ]);

        Student::factory()
            ->create()
            ->forceFill(['user_id' => $studentUser->id])
            ->save();

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => '01744000011',
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($studentUser);
    }

    public function test_student_accounts_without_a_record_sign_in_to_the_application_desk(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $this->makeUser($school, 'student', [
            'mobile_number' => '01744000012',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => '01744000012',
            'password' => 'password',
        ])
            ->assertRedirect(route('applicant.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_unlinked_student_sessions_are_redirected_to_their_applications(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $orphan = $this->makeUser($school, 'student');

        $this->actingAs($orphan)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertAuthenticatedAs($orphan);
    }

    public function test_student_with_a_temporary_password_must_change_it_before_entering_the_portal(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $studentUser = $this->makeUser($school, 'student', [
            'mobile_number' => '01744000013',
            'must_change_password' => true,
        ]);

        Student::factory()
            ->create()
            ->forceFill(['user_id' => $studentUser->id])
            ->save();

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => '01744000013',
            'password' => 'password',
        ])->assertRedirect(route('student.password.change'));

        $this->get(route('student.dashboard'))
            ->assertRedirect(route('student.password.change'));

        $this->put(route('student.password.update'), [
            'current_password' => 'password',
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertFalse($studentUser->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NewSecret@123', $studentUser->fresh()->password));
    }

    public function test_school_admin_can_provision_and_disable_a_student_portal_account(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);
        $admin = $this->makeUser($school, 'school_admin');
        $student = Student::factory()->create();

        $this->actingAs($admin)
            ->post(route('students.portal.store', $student), [
                'mobile_number' => '017-1234 5678',
                'password' => 'Portal@1234',
                'password_confirmation' => 'Portal@1234',
            ])
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('portal_password');

        $user = User::query()->where('mobile_number', '01712345678')->firstOrFail();

        $this->assertSame($school->id, $user->school_id);
        $this->assertTrue($user->hasRole('student'));
        $this->assertNull($user->email);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Portal@1234', $user->password));
        $this->assertSame($user->id, $student->fresh()->user_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'student.portal_created',
            'school_id' => $school->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('students.portal.store', $student), [
                'mobile_number' => '01799999999',
            ])
            ->assertSessionHasErrors('mobile_number');

        $this->actingAs($admin)
            ->patch(route('students.portal.status', $student), ['is_active' => false])
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_a_student_photo_is_streamed_to_the_owner_and_the_office(): void
    {
        Storage::fake('school_uploads');

        [$studentUser, $student, , , $admin] = $this->academicFixture();

        $student->forceFill(['photo_path' => 'students/'.$student->school_id.'/face.png'])->save();
        Storage::disk('school_uploads')->put($student->photo_path, 'image bytes');

        $this->actingAs($studentUser)
            ->get(route('student.photo'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($admin)
            ->get(route('students.photo', $student))
            ->assertOk();

        $student->forceFill(['photo_path' => null])->save();

        $this->actingAs($studentUser)->get(route('student.photo'))->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Student, 2: FeeInvoice, 3: FeePayment, 4: User}
     */
    private function academicFixture(): array
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');
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

        $studentUser = $this->makeUser($school, 'student', ['email' => 'ayesha@example.test']);

        $student = Student::factory()->create([
            'first_name' => 'Ayesha',
            'last_name' => 'Rahman',
        ]);

        $student->forceFill(['user_id' => $studentUser->id])->save();

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        StudentAttendance::query()->create([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $admin->id,
        ]);

        $invoice = FeeInvoice::query()->create([
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'invoice_no' => 'INV-2026-0001',
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'subtotal' => 1500,
            'discount_total' => 0,
            'total' => 1500,
            'paid_total' => 0,
            'due_total' => 1500,
            'status' => 'issued',
            'created_by' => $admin->id,
        ]);

        $invoice->items()->create([
            'description' => 'Monthly tuition',
            'quantity' => 1,
            'unit_amount' => 1500,
            'discount_amount' => 0,
            'line_total' => 1500,
        ]);

        $payment = app(FeePaymentService::class)->collect($accountant, $invoice, [
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => now()->subDays(2)->toDateTimeString(),
            'idempotency_key' => 'portal-test-payment-1',
            'notes' => 'Test payment',
        ]);

        return [$studentUser, $student, $invoice->refresh(), $payment, $admin];
    }
}
