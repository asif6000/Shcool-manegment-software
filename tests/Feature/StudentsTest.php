<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudentsTest extends TestCase
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
     * The admission form opens the student's login as well as the register row, so
     * a complete submission carries a sign-in mobile, an initial password, an
     * address and a class place.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function admissionPayload(array $overrides = []): array
    {
        return array_merge([
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

    public function test_school_admin_can_admit_a_student_with_guardian_and_enrollment(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $this->actingAs($admin)->post(route('students.store'), $this->admissionPayload([
            'first_name' => 'Nusrat',
            'last_name' => 'Jahan',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $student = Student::query()->where('first_name', 'Nusrat')->firstOrFail();

        $this->assertSame($school->id, $student->school_id);
        $this->assertMatchesRegularExpression('/^STU-2026-\d{4}$/', $student->student_id);
        $this->assertMatchesRegularExpression('/^ADM-2026-\d{4}$/', $student->admission_no);
        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame(1, $student->enrollments()->first()->roll_no);
        $this->assertSame(1, Guardian::query()->where('phone', '01712345678')->count());
        $this->assertTrue($student->primaryGuardian()?->is($student->guardians()->first()));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'student.created',
            'school_id' => $school->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'student.enrolled',
            'school_id' => $school->id,
        ]);
    }

    public function test_a_duplicate_roll_number_is_rejected(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        Student::factory()->create(['student_id' => 'STU-2026-0001', 'admission_no' => 'ADM-2026-0001']);
        $existing = Student::query()->firstOrFail();

        Enrollment::query()->create([
            'student_id' => $existing->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 7,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('students.store'), $this->admissionPayload([
            'first_name' => 'Rafiq',
            'gender' => 'male',
            'date_of_birth' => '2014-05-01',
            'admission_date' => '2026-01-03',
            'guardian_name' => 'Abbas Ali',
            'guardian_phone' => '01812345678',
            'phone' => '01887654321',
            'roll_no' => 7,
        ]))->assertSessionHasErrors('roll_no');

        $this->assertSame(1, Student::query()->count());
    }

    public function test_teacher_cannot_open_or_create_students(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');

        // The student register is an office screen; teachers reach their own
        // classes through attendance, marks and the learning modules.
        $this->actingAs($teacher)->get(route('students.index'))->assertForbidden();

        $this->actingAs($teacher)->post(route('students.store'), [
            'first_name' => 'Blocked',
            'gender' => 'male',
            'date_of_birth' => '2014-05-01',
            'status' => 'active',
            'admission_date' => '2026-01-03',
        ])->assertForbidden();
    }

    public function test_student_profiles_are_not_visible_across_schools(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $adminA = $this->makeUser($schoolA, 'school_admin');

        $this->useSchoolContext($schoolB->id);
        $studentB = Student::factory()->create([
            'student_id' => 'STU-B-0001',
            'admission_no' => 'ADM-B-0001',
        ]);

        $this->actingAs($adminA)
            ->get(route('students.show', $studentB))
            ->assertNotFound();
    }

    public function test_student_csv_export_is_permission_gated(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');

        $this->actingAs($teacher)
            ->get(route('students.export'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('students.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_approving_an_admission_creates_a_student_and_enrollment_atomically(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $admission = Admission::query()->create([
            'academic_session_id' => $this->session->id,
            'applied_class_id' => $this->class->id,
            'application_no' => 'APP-2026-0001',
            'first_name' => 'Sadia',
            'last_name' => 'Islam',
            'gender' => 'female',
            'date_of_birth' => '2013-08-20',
            'guardian_name' => 'Shahid Islam',
            'guardian_phone' => '01912345678',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admissions.approve', $admission), [
                'section_id' => $this->section->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $admission->refresh();

        $this->assertSame('approved', $admission->status);
        $this->assertNotNull($admission->student_id);
        $this->assertSame($admin->id, $admission->reviewed_by);

        $student = Student::query()->findOrFail($admission->student_id);
        $this->assertSame('Sadia', $student->first_name);
        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame($this->section->id, $student->enrollments()->first()->section_id);

        $this->actingAs($admin)
            ->post(route('admissions.approve', $admission), [
                'section_id' => $this->section->id,
            ])
            ->assertSessionHasErrors('admission');

        $this->assertSame(1, Student::query()->where('first_name', 'Sadia')->count());
    }

    public function test_an_application_can_be_rejected_with_a_reason(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $admission = Admission::query()->create([
            'academic_session_id' => $this->session->id,
            'applied_class_id' => $this->class->id,
            'application_no' => 'APP-2026-0002',
            'first_name' => 'Rejected',
            'gender' => 'male',
            'date_of_birth' => '2013-02-02',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '01612345678',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admissions.reject', $admission), ['reason' => 'No seats available'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertSame('rejected', $admission->status);
        $this->assertSame('No seats available', $admission->rejection_reason);
        $this->assertNull($admission->notes);
        $this->assertSame(0, Student::query()->count());
    }
}
