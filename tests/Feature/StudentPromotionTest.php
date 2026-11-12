<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPromotion;
use App\Models\StudentResult;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class StudentPromotionTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtures(School $school, int $adminId): array
    {
        $this->useSchoolContext($school->id);

        $current = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $next = AcademicSession::query()->create([
            'name' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        $currentClass = SchoolClass::query()->create([
            'name' => 'Class 1',
            'level' => 1,
            'is_active' => true,
        ]);

        $targetClass = SchoolClass::query()->create([
            'name' => 'Class 2',
            'level' => 2,
            'is_active' => true,
        ]);

        $currentSection = Section::query()->create([
            'class_id' => $currentClass->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $targetSection = Section::query()->create([
            'class_id' => $targetClass->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $exam = Exam::query()->create([
            'academic_session_id' => $current->id,
            'name' => 'Final Examination',
            'exam_type' => 'annual',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $adminId,
        ]);

        return compact('current', 'next', 'currentClass', 'targetClass', 'currentSection', 'targetSection', 'exam');
    }

    /**
     * @param  array<string, mixed>  $fixtures
     * @return array{0: Student, 1: Enrollment, 2: StudentResult}
     */
    private function student(array $fixtures, string $firstName, int $roll, float $gpa = 3.50): array
    {
        $student = Student::factory()->create([
            'first_name' => $firstName,
            'status' => 'active',
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $fixtures['current']->id,
            'class_id' => $fixtures['currentClass']->id,
            'section_id' => $fixtures['currentSection']->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-05',
            'status' => 'active',
        ]);

        $result = StudentResult::query()->create([
            'exam_id' => $fixtures['exam']->id,
            'student_id' => $student->id,
            'total_full_marks' => 500,
            'total_obtained' => $gpa >= 2 ? 350 : 180,
            'gpa' => $gpa,
            'grade_letter' => $gpa >= 2 ? 'A-' : 'F',
            'position' => $roll,
            'status' => 'published',
            'snapshot' => ['source' => 'test'],
            'published_at' => now(),
        ]);

        return [$student, $enrollment, $result];
    }

    /**
     * @param  array<string, mixed>  $fixtures
     * @return array<string, mixed>
     */
    private function promotePayload(array $fixtures, array $studentIds, string $action = 'promote'): array
    {
        return [
            'from_session_id' => $fixtures['current']->id,
            'action' => $action,
            'student_ids' => $studentIds,
            'target_session_id' => $fixtures['next']->id,
            'target_class_id' => $fixtures['targetClass']->id,
            'target_section_id' => $fixtures['targetSection']->id,
            'roll_strategy' => 'auto',
        ];
    }

    public function test_workspace_and_preview_show_results_eligibility_and_rolls(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$eligible] = $this->student($fixtures, 'Ayesha', 1, 3.50);
        [$review] = $this->student($fixtures, 'Rafiq', 2, 1.50);

        $this->actingAs($admin)
            ->get(route('promotions.index', ['from_session_id' => $fixtures['current']->id]))
            ->assertOk()
            ->assertSee('Ayesha')
            ->assertSee('Rafiq')
            ->assertSee('Eligible')
            ->assertSee('Needs review');

        $this->actingAs($admin)
            ->postJson(route('promotions.preview'), $this->promotePayload($fixtures, [$eligible->id, $review->id]))
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.ready', 2)
            ->assertJsonPath('summary.conflicts', 0)
            ->assertJsonPath('rows.0.status', 'ready')
            ->assertJsonPath('rows.0.eligibility.key', 'eligible')
            ->assertJsonPath('rows.1.eligibility.key', 'ineligible')
            ->assertJsonPath('rows.0.to_roll_no', 1)
            ->assertJsonPath('rows.1.to_roll_no', 2);
    }

    public function test_promotion_preserves_historical_records_and_creates_new_enrollment(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$student, $oldEnrollment, $result] = $this->student($fixtures, 'Nusrat', 7, 3.80);

        $attendance = StudentAttendance::query()->create([
            'student_id' => $student->id,
            'enrollment_id' => $oldEnrollment->id,
            'academic_session_id' => $fixtures['current']->id,
            'class_id' => $fixtures['currentClass']->id,
            'section_id' => $fixtures['currentSection']->id,
            'attendance_date' => '2026-03-10',
            'status' => 'present',
            'source' => 'manual',
            'marked_by' => $admin->id,
        ]);

        $invoice = FeeInvoice::query()->create([
            'academic_session_id' => $fixtures['current']->id,
            'student_id' => $student->id,
            'enrollment_id' => $oldEnrollment->id,
            'invoice_no' => 'INV-PROMO-1',
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'subtotal' => 1000,
            'total' => 1000,
            'paid_total' => 400,
            'due_total' => 600,
            'status' => 'partial',
            'created_by' => $admin->id,
        ]);

        $oldSessionId = $oldEnrollment->academic_session_id;
        $oldClassId = $oldEnrollment->class_id;
        $oldSectionId = $oldEnrollment->section_id;
        $oldRoll = $oldEnrollment->roll_no;

        $this->actingAs($admin)
            ->post(route('promotions.store'), $this->promotePayload($fixtures, [$student->id]))
            ->assertRedirect(route('promotions.index', ['from_session_id' => $fixtures['current']->id]))
            ->assertSessionHasNoErrors();

        $oldEnrollment->refresh();
        $this->assertSame('completed', $oldEnrollment->status);
        $this->assertSame($oldSessionId, $oldEnrollment->academic_session_id);
        $this->assertSame($oldClassId, $oldEnrollment->class_id);
        $this->assertSame($oldSectionId, $oldEnrollment->section_id);
        $this->assertSame($oldRoll, $oldEnrollment->roll_no);

        $newEnrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $fixtures['next']->id)
            ->firstOrFail();

        $this->assertSame('active', $newEnrollment->status);
        $this->assertSame($fixtures['targetClass']->id, $newEnrollment->class_id);
        $this->assertSame($fixtures['targetSection']->id, $newEnrollment->section_id);
        $this->assertSame(1, $newEnrollment->roll_no);

        $this->assertDatabaseHas('student_promotions', [
            'student_id' => $student->id,
            'from_enrollment_id' => $oldEnrollment->id,
            'to_enrollment_id' => $newEnrollment->id,
            'action' => 'promote',
            'status' => 'completed',
            'eligibility' => 'eligible',
            'promoted_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('student_attendances', [
            'id' => $attendance->id,
            'enrollment_id' => $oldEnrollment->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('fee_invoices', [
            'id' => $invoice->id,
            'enrollment_id' => $oldEnrollment->id,
            'paid_total' => 400,
            'status' => 'partial',
        ]);
        $this->assertDatabaseHas('student_results', [
            'id' => $result->id,
            'gpa' => 3.80,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student.promoted']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student.promotion_batch']);
    }

    public function test_duplicate_promotion_is_rejected_and_batch_is_atomic(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$first] = $this->student($fixtures, 'First', 1, 3.60);
        [$second, $secondEnrollment] = $this->student($fixtures, 'Second', 2, 3.40);

        $this->actingAs($admin)
            ->post(route('promotions.store'), $this->promotePayload($fixtures, [$first->id]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('promotions.store'), $this->promotePayload($fixtures, [$first->id, $second->id]))
            ->assertSessionHasErrors('students');

        $this->assertSame(1, StudentPromotion::query()->where('student_id', $first->id)->count());
        $this->assertSame(0, StudentPromotion::query()->where('student_id', $second->id)->count());
        $this->assertSame('active', $secondEnrollment->refresh()->status);
        $this->assertSame(0, Enrollment::query()
            ->where('student_id', $second->id)
            ->where('academic_session_id', $fixtures['next']->id)
            ->count());
    }

    public function test_repeat_hold_and_transfer_out_actions_are_recorded_correctly(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$repeater, $repeaterEnrollment] = $this->student($fixtures, 'Repeater', 1, 1.80);
        [$held, $heldEnrollment] = $this->student($fixtures, 'Held', 2, 3.20);
        [$transferred, $transferredEnrollment] = $this->student($fixtures, 'Transferred', 3, 3.40);

        $repeatPayload = $this->promotePayload($fixtures, [$repeater->id], 'repeat');
        $repeatPayload['target_class_id'] = $fixtures['currentClass']->id;
        $repeatPayload['target_section_id'] = $fixtures['currentSection']->id;

        $this->actingAs($admin)
            ->post(route('promotions.store'), $repeatPayload)
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $repeaterEnrollment->refresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $repeater->id,
            'academic_session_id' => $fixtures['next']->id,
            'class_id' => $fixtures['currentClass']->id,
            'section_id' => $fixtures['currentSection']->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('student_promotions', [
            'student_id' => $repeater->id,
            'action' => 'repeat',
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->post(route('promotions.store'), [
                'from_session_id' => $fixtures['current']->id,
                'action' => 'hold',
                'student_ids' => [$held->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $heldEnrollment->refresh()->status);
        $this->assertSame(0, Enrollment::query()
            ->where('student_id', $held->id)
            ->where('academic_session_id', $fixtures['next']->id)
            ->count());
        $this->assertDatabaseHas('student_promotions', [
            'student_id' => $held->id,
            'action' => 'hold',
            'status' => 'held',
        ]);

        $this->actingAs($admin)
            ->post(route('promotions.store'), [
                'from_session_id' => $fixtures['current']->id,
                'action' => 'transfer_out',
                'student_ids' => [$transferred->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('transferred', $transferredEnrollment->refresh()->status);
        $this->assertSame('transferred', $transferred->refresh()->status);
        $this->assertDatabaseHas('student_promotions', [
            'student_id' => $transferred->id,
            'action' => 'transfer_out',
            'status' => 'transferred',
        ]);
    }

    public function test_a_conflict_rolls_the_whole_bulk_batch_back(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$first, $firstEnrollment] = $this->student($fixtures, 'First', 1, 3.60);
        [$second] = $this->student($fixtures, 'Second', 2, 3.40);

        Enrollment::query()->create([
            'student_id' => $second->id,
            'academic_session_id' => $fixtures['next']->id,
            'class_id' => $fixtures['targetClass']->id,
            'section_id' => $fixtures['targetSection']->id,
            'roll_no' => 1,
            'enrolled_on' => '2027-01-01',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('promotions.store'), $this->promotePayload($fixtures, [$first->id, $second->id]))
            ->assertSessionHasErrors('students');

        $this->assertSame('active', $firstEnrollment->refresh()->status);
        $this->assertSame(0, Enrollment::query()
            ->where('student_id', $first->id)
            ->where('academic_session_id', $fixtures['next']->id)
            ->count());
        $this->assertSame(0, StudentPromotion::query()->where('student_id', $first->id)->count());
        $this->assertSame(1, Enrollment::query()
            ->where('student_id', $second->id)
            ->where('academic_session_id', $fixtures['next']->id)
            ->count());
    }

    public function test_preview_reports_target_section_and_session_conflicts(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$student] = $this->student($fixtures, 'Conflict', 1, 3.20);

        $payload = $this->promotePayload($fixtures, [$student->id]);
        $payload['target_session_id'] = $fixtures['current']->id;

        $this->actingAs($admin)
            ->postJson(route('promotions.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('summary.conflicts', 1)
            ->assertJsonPath('rows.0.status', 'conflict');

        $payload = $this->promotePayload($fixtures, [$student->id]);
        $payload['target_section_id'] = $fixtures['currentSection']->id;

        $this->actingAs($admin)
            ->postJson(route('promotions.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('summary.conflicts', 1);
    }

    public function test_manual_rolls_are_single_only_and_keep_roll_falls_back_when_taken(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$first] = $this->student($fixtures, 'First', 1, 3.60);
        [$second] = $this->student($fixtures, 'Second', 2, 3.40);
        $occupant = Student::factory()->create(['first_name' => 'Occupant']);

        Enrollment::query()->create([
            'student_id' => $occupant->id,
            'academic_session_id' => $fixtures['next']->id,
            'class_id' => $fixtures['targetClass']->id,
            'section_id' => $fixtures['targetSection']->id,
            'roll_no' => 1,
            'enrolled_on' => '2027-01-01',
            'status' => 'active',
        ]);

        $keep = $this->promotePayload($fixtures, [$first->id]);
        $keep['roll_strategy'] = 'keep';

        $preview = $this->actingAs($admin)
            ->postJson(route('promotions.preview'), $keep)
            ->assertOk()
            ->json();

        $this->assertSame(2, $preview['rows'][0]['to_roll_no']);
        $this->assertNotEmpty($preview['rows'][0]['warnings']);

        $manual = $this->promotePayload($fixtures, [$first->id, $second->id]);
        $manual['roll_strategy'] = 'manual';
        $manual['roll_no'] = 10;

        $this->actingAs($admin)
            ->post(route('promotions.store'), $manual)
            ->assertSessionHasErrors('roll_strategy');

        $this->assertSame(0, Enrollment::query()
            ->whereIn('student_id', [$first->id, $second->id])
            ->where('academic_session_id', $fixtures['next']->id)
            ->count());

        $manual['student_ids'] = [$first->id];

        $this->actingAs($admin)
            ->post(route('promotions.store'), $manual)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $first->id,
            'academic_session_id' => $fixtures['next']->id,
            'roll_no' => 10,
            'status' => 'active',
        ]);
    }

    public function test_reports_and_csv_export_include_processed_students(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $fixtures = $this->fixtures($school, $admin->id);

        [$student] = $this->student($fixtures, 'Reported', 1, 3.50);

        $this->actingAs($admin)
            ->post(route('promotions.store'), $this->promotePayload($fixtures, [$student->id]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('promotions.report', ['from_session_id' => $fixtures['current']->id]))
            ->assertOk()
            ->assertSee('Reported')
            ->assertSee('Promote')
            ->assertSee('Final Examination');

        $response = $this->actingAs($admin)
            ->get(route('promotions.report.export', ['from_session_id' => $fixtures['current']->id]));

        $response->assertOk();
        $this->assertStringContainsString('Reported', $response->streamedContent());
    }

    public function test_promotion_workspace_is_permission_gated(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $principal = $this->makeUser($school, 'principal');
        $accountant = $this->makeUser($school, 'accountant');
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school, $admin->id);

        $this->actingAs($admin)->get(route('promotions.index'))->assertOk();
        $this->actingAs($principal)->get(route('promotions.index'))->assertOk();
        $this->actingAs($accountant)->get(route('promotions.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('promotions.index'))->assertForbidden();
    }
}
