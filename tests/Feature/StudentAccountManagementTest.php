<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuthenticationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Student accounts are opened by the office with a mobile number and a password,
 * signed in the same way, and can be deleted again. Admin, teacher, guardian and
 * accountant sign-in is untouched by any of this.
 */
class StudentAccountManagementTest extends TestCase
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

    private function academicFixtures(int $schoolId): void
    {
        $this->useSchoolContext($schoolId);

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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Nusrat',
            'last_name' => 'Jahan',
            'gender' => 'female',
            'date_of_birth' => '2014-03-12',
            'status' => 'active',
            'admission_date' => '2026-01-02',
            'phone' => '01712345678',
            'password' => 'First@2026',
            'password_confirmation' => 'First@2026',
            'present_address' => 'House 12, Road 3, Dhanmondi',
            'guardian_name' => 'Kamal Jahan',
            'guardian_relation' => 'father',
            'guardian_phone' => '01712345678',
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 1,
        ], $overrides);
    }

    private function admit(User $admin, array $overrides = []): Student
    {
        $this->actingAs($admin)
            ->post(route('students.store'), $this->payload($overrides))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->signOut();

        return Student::query()->where('phone', AuthenticationService::normaliseMobile(
            (string) ($overrides['phone'] ?? '01712345678')
        ))->firstOrFail();
    }

    /**
     * The workspace is a per-session thing, and the guest sign-in routes answer
     * an already signed-in admin with a redirect instead of the form.
     */
    private function signOut(): void
    {
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    /**
     * The confirmation dialog posts the typed word along with the form, and the
     * server refuses a critical delete that arrives without it.
     */
    private function deleteLogin(User $admin, Student $student, ?string $token = 'DELETE'): TestResponse
    {
        $this->signOut();
        $this->actingAs($admin);

        return $this->call('DELETE', route('students.login.destroy', $student), $token === null ? [] : ['permanent_delete' => $token]);
    }

    private function archive(User $admin, Student $student, bool $confirmed = true): TestResponse
    {
        $this->signOut();
        $this->actingAs($admin);

        return $this->call('PATCH', route('students.archive', $student), $confirmed ? ['archive_confirmed' => '1'] : []);
    }

    /**
     * The retention tables, filled with the minimum a real row needs: one
     * enrollment, one attendance day, five exam marks and one published result.
     */
    private function giveHistory(Student $student): Exam
    {
        $enrollment = Enrollment::query()->firstOrCreate([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
        ], [
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 12,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        DB::table('student_attendances')->insert([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'attendance_date' => '2026-02-02',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fee_invoices')->insert([
            'school_id' => $student->school_id,
            'academic_session_id' => $this->session->id,
            'student_id' => $student->id,
            'invoice_no' => 'INV-2026-0001',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-02-28',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Annual Examination 2026',
            'exam_type' => 'annual',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $total = 0;
        $subjectSnapshots = [];

        foreach (['Bangla', 'English', 'Mathematics', 'Science', 'Religion'] as $index => $name) {
            $subject = Subject::query()->create([
                'name' => $name,
                'code' => 'SUB-'.($index + 1),
                'type' => 'core',
                'is_active' => true,
            ]);

            $paper = ExamSubject::query()->create([
                'exam_id' => $exam->id,
                'class_id' => $this->class->id,
                'subject_id' => $subject->id,
                'full_marks' => 100,
                'pass_marks' => 33,
            ]);

            $obtained = 60 + ($index * 5);
            $total += $obtained;

            ExamMark::query()->create([
                'exam_subject_id' => $paper->id,
                'student_id' => $student->id,
                'marks_obtained' => $obtained,
                'is_absent' => false,
                'grade_letter' => 'A',
                'grade_point' => 4,
            ]);

            $subjectSnapshots[] = [
                'name' => $subject->name,
                'code' => $subject->code,
                'full_marks' => 100,
                'pass_marks' => 33,
                'obtained' => $obtained,
                'is_absent' => false,
                'grade_letter' => 'A',
                'grade_point' => 4,
                'remarks' => null,
            ];
        }

        StudentResult::query()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'total_full_marks' => 500,
            'total_obtained' => $total,
            'gpa' => 4,
            'grade_letter' => 'A',
            'position' => 1,
            'status' => 'published',
            'snapshot' => [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'admission_no' => $student->admission_no,
                    'name' => $student->full_name,
                ],
                'enrollment' => [
                    'session' => $this->session->name,
                    'class' => $this->class->name,
                    'section' => $this->section->name,
                    'roll_no' => $enrollment->roll_no,
                ],
                'subjects' => $subjectSnapshots,
                'total_full_marks' => 500,
                'total_obtained' => $total,
                'gpa' => 4,
                'grade_letter' => 'A',
                'position' => 1,
                'complete' => true,
                'outcome' => 'pass',
            ],
            'published_at' => now(),
        ]);

        return $exam;
    }

    public function test_an_admin_admits_a_student_with_a_mobile_number_and_a_password(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin, ['phone' => '017-1234 5678']);

        $account = $student->user()->firstOrFail();

        $this->assertNull($account->email, 'A student account must not carry an email address.');
        $this->assertSame('01712345678', $account->mobile_number);
        $this->assertTrue($account->hasRole('student'));
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);

        // Stored as a hash, never as the typed text.
        $this->assertNotSame('First@2026', $account->getRawOriginal('password'));
        $this->assertTrue(Hash::check('First@2026', $account->password));

        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame('Kamal Jahan', $student->primaryGuardian()?->name);
    }

    public function test_the_admitted_student_signs_in_with_the_mobile_number_and_password(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);

        $this->post(route('student.login.store'), [
            'mobile_number' => '01712345678',
            'password' => 'First@2026',
        ])->assertRedirect(route('student.dashboard'))->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($student->fresh()->user);
    }

    public function test_the_student_sign_in_needs_no_email_address_or_verification(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);

        $this->assertNull($student->user()->value('email'));

        // The sign-in screen asks for two things only, and the account signs in
        // without ever passing an email verification or OTP step.
        $this->get(route('student.login'))
            ->assertOk()
            ->assertSee('name="mobile_number"', false)
            ->assertDontSee('name="email"', false);

        $this->assertTrue($student->user()->firstOrFail()->hasVerifiedEmail());
    }

    public function test_a_wrong_password_is_rejected_and_the_student_stays_signed_out(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $this->admit($admin);

        $this->post(route('student.login.store'), [
            'mobile_number' => '01712345678',
            'password' => 'NotThePassword',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_a_duplicate_mobile_number_is_rejected_when_admitting(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $this->admit($admin);

        $this->actingAs($admin)->post(route('students.store'), $this->payload([
            'first_name' => 'Duplicate',
            'phone' => '01712345678',
            'roll_no' => 2,
        ]))->assertSessionHasErrors('phone');

        $this->assertSame(1, Student::query()->count());
        $this->assertSame(2, User::query()->count(), 'No half-made account may be left behind.');
    }

    public function test_a_student_number_cannot_take_over_a_staff_account(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $teacher = $this->makeUser($school, 'teacher', ['phone' => '01600000000']);
        $teacherHash = $teacher->getRawOriginal('password');

        $this->actingAs($admin)->post(route('students.store'), $this->payload([
            'first_name' => 'Opportunist',
            'phone' => '01600000000',
            'password' => 'Hijacked@2026',
            'password_confirmation' => 'Hijacked@2026',
        ]))->assertSessionHasErrors('phone');

        $fresh = $teacher->fresh();

        $this->assertSame($teacher->name, $fresh->name);
        $this->assertSame($teacherHash, $fresh->getRawOriginal('password'));
        $this->assertTrue($fresh->hasRole('teacher'));
        $this->assertDatabaseMissing('students', ['first_name' => 'Opportunist']);
    }

    public function test_the_admission_form_requires_a_password_confirmation(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $this->actingAs($admin)->post(route('students.store'), $this->payload([
            'password' => 'Mismatch@2026',
            'password_confirmation' => 'Different@2026',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('students', ['first_name' => 'Nusrat']);
    }

    public function test_an_admin_can_delete_a_student_login_without_deleting_the_profile(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $accountId = $student->user_id;
        $second = $this->admit($admin, ['first_name' => 'Other', 'phone' => '01812345678', 'roll_no' => 2]);

        $this->deleteLogin($admin, $student)
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertNull(Student::query()->findOrFail($student->id)->user_id);
        $this->assertDatabaseMissing('users', ['id' => $accountId]);
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $accountId)->count());
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id]);
        $this->assertDatabaseHas('student_guardians', ['student_id' => $student->id]);

        // Another student's account and profile are untouched.
        $this->assertDatabaseHas('students', ['id' => $second->id]);
        $this->assertDatabaseHas('users', ['id' => $second->user_id]);
    }

    public function test_a_student_whose_login_was_deleted_can_no_longer_sign_in(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $this->deleteLogin($admin, $student)->assertSessionHasNoErrors();
        $this->signOut();

        $this->post(route('student.login.store'), [
            'mobile_number' => '01712345678',
            'password' => 'First@2026',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();

        // The number is free again, so the office can open a new login later.
        $student->refresh();
        $this->assertNull($student->user_id);
    }

    public function test_deleting_the_login_keeps_the_profile_photo_and_attendance_card(): void
    {
        Storage::fake('school_uploads');

        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $accountId = $student->user_id;

        $photo = 'students/'.$school->id.'/face-1.jpg';
        Storage::disk('school_uploads')->put($photo, 'jpeg-bytes');
        $student->forceFill(['photo_path' => $photo])->save();

        DB::table('student_attendance_cards')->insert([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'token_hash' => hash('sha256', 'card-token'),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guardianId = $student->guardians()->firstOrFail()->id;

        $this->deleteLogin($admin, $student)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        Storage::disk('school_uploads')->assertExists($photo);
        $this->assertDatabaseHas('student_attendance_cards', ['student_id' => $student->id]);
        $this->assertDatabaseHas('guardians', ['id' => $guardianId]);
        $this->assertDatabaseHas('student_guardians', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $accountId]);
    }

    public function test_deleting_the_login_keeps_attendance_marks_and_results(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $exam = $this->giveHistory($student);
        $roll = (int) $student->enrollments()->value('roll_no');

        $this->deleteLogin($admin, $student)
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $student->user_id]);
        $this->assertSame(1, DB::table('student_attendances')->where('student_id', $student->id)->count());
        $this->assertSame(5, DB::table('exam_marks')->where('student_id', $student->id)->count());
        $this->assertSame(1, DB::table('student_results')->where('student_id', $student->id)->count());
        $this->assertDatabaseHas('student_results', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => 'published',
        ]);

        // The public result page still resolves the enrollment and result.
        $this->signOut();
        $this->get(route('public.results.index', [
            'exam_id' => $exam->id,
            'session_id' => $this->session->id,
            'roll_no' => $roll,
        ]))->assertOk()->assertSee('Nusrat Jahan');
    }

    public function test_archiving_the_profile_keeps_history_and_disables_portal_access(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $exam = $this->giveHistory($student);
        $account = $student->user()->firstOrFail();
        $account->createToken('before-archive');

        DB::table('sessions')->insert([
            'id' => 'student-session-before-archive',
            'user_id' => $account->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->archive($admin, $student)
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHasNoErrors();

        $student->refresh();
        $account->refresh();

        $this->assertSame(Student::STATUS_ARCHIVED, $student->status);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $account->id, 'is_active' => false]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $account->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $account->id)->count());
        $this->assertSame('withdrawn', $student->enrollments()->value('status'));

        $this->assertSame(1, DB::table('student_attendances')->where('student_id', $student->id)->count());
        $this->assertSame(5, DB::table('exam_marks')->where('student_id', $student->id)->count());
        $this->assertSame(1, DB::table('student_results')->where('student_id', $student->id)->count());

        // Archived profiles are out of the default register but remain readable.
        $this->signOut();
        $this->actingAs($admin)->get(route('students.index'))->assertOk()->assertDontSee('Nusrat Jahan');
        $this->actingAs($admin)->get(route('students.index', ['status' => 'archived']))->assertOk()->assertSee('Nusrat Jahan');

        // Reports still resolve the archived student and their published result.
        $this->actingAs($admin)
            ->get(route('exams.results.index', ['exam' => $exam->id, 'class_id' => $this->class->id]))
            ->assertOk()
            ->assertSee('Nusrat Jahan');

        $this->actingAs($admin)
            ->get(route('exams.results.show', ['exam' => $exam->id, 'student' => $student->id]))
            ->assertOk()
            ->assertSee('Nusrat Jahan');

        $this->signOut();
        $this->get(route('public.results.index', [
            'exam_id' => $exam->id,
            'session_id' => $this->session->id,
            'roll_no' => 1,
        ]))->assertOk()->assertSee('Nusrat Jahan');

        // The disabled login cannot be used after the profile is archived.
        $this->post(route('student.login.store'), [
            'mobile_number' => '01712345678',
            'password' => 'First@2026',
        ])->assertSessionHasErrors('mobile_number');
    }

    public function test_archiving_requires_confirmation(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);

        $this->archive($admin, $student, confirmed: false)
            ->assertSessionHasErrors('archive_confirmed');

        $this->assertSame('active', $student->fresh()->status);
        $this->assertTrue($student->user()->firstOrFail()->is_active);
    }

    public function test_the_login_deletion_is_logged_without_personal_details(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);

        $this->deleteLogin($admin, $student)->assertSessionHasNoErrors();

        $entry = DB::table('audit_logs')
            ->where('event', 'student.login_deleted')
            ->where('user_id', $admin->id)
            ->firstOrFail();

        $this->assertStringNotContainsString('Nusrat', $entry->description);
        $this->assertStringNotContainsString('01712345678', $entry->description);

        $payload = (array) json_decode((string) $entry->old_values, true);
        $this->assertStringNotContainsString('Nusrat', (string) json_encode($payload));
        $this->assertStringNotContainsString('01712345678', (string) json_encode($payload));
        $this->assertStringNotContainsString('Kamal', (string) json_encode($payload));
        $this->assertArrayHasKey('user_id', $payload);
    }

    public function test_only_a_permissioned_admin_can_delete_a_login(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $accountId = $student->user_id;
        $accountant = $this->makeUser($school, 'accountant');
        $otherSchoolAdmin = $this->makeUser($this->makeSchool(), 'school_admin');

        $this->actingAs($accountant)
            ->delete(route('students.login.destroy', $student))
            ->assertForbidden();

        // A record belonging to another school is not resolvable at all.
        $this->deleteLogin($otherSchoolAdmin, $student)
            ->assertNotFound();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $accountId]);
    }

    public function test_a_login_delete_that_skips_the_typed_confirmation_is_refused(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = $this->admit($admin);
        $accountId = $student->user_id;

        $this->deleteLogin($admin, $student, token: null)
            ->assertSessionHas('error');

        // The button was never confirmed, so nothing at all was touched.
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $accountId]);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'student.login_deleted']);
    }

    public function test_the_other_roles_keep_signing_in_their_own_way(): void
    {
        $school = $this->makeSchool();
        $this->academicFixtures($school->id);

        $admin = $this->makeUser($school, 'school_admin', ['email' => 'office@example.test']);
        $teacher = $this->makeUser($school, 'teacher', ['mobile_number' => '01744000021']);
        $accountant = $this->makeUser($school, 'accountant', ['mobile_number' => '01744000022']);
        $this->admit($admin);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'office@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($admin);
        $this->post(route('logout'));

        // Staff below the admin sign in with the mobile number on their account.
        foreach ([['teacher', $teacher], ['accountant', $accountant]] as [$role, $staff]) {
            $this->post(route('login.store'), [
                'role' => $role,
                'mobile_number' => $staff->mobile_number,
                'password' => 'password',
            ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

            $this->assertAuthenticatedAs($staff);
            $this->post(route('logout'));
        }

        // Staff keep their email identity; only student accounts are email-free.
        $this->assertNotNull($admin->fresh()->email);
        $this->assertNull(Student::query()->firstOrFail()->user()->value('email'));
    }
}
