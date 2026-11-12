<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassRoutine;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\StudentAttendanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class AttendanceTest extends TestCase
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

    public function test_a_class_can_be_marked_in_bulk_and_re_marking_updates_the_same_rows(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $students = Student::factory()->count(3)->create()->values();

        foreach ($students as $index => $student) {
            $this->enroll($student, $index + 1);
        }

        $payload = [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-10-01',
            'statuses' => [
                $students[0]->id => 'present',
                $students[1]->id => 'absent',
                $students[2]->id => 'late',
            ],
        ];

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), $payload)
            ->assertRedirect(route('attendance.students.index', [
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'date' => '2026-10-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, StudentAttendance::query()->count());
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $students[1]->id,
            'attendance_date' => '2026-10-01',
            'status' => 'absent',
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'marked_by' => $admin->id,
        ]);

        $payload['statuses'] = [
            $students[0]->id => 'late',
            $students[1]->id => 'present',
            $students[2]->id => 'present',
        ];

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(3, StudentAttendance::query()->count());
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $students[0]->id,
            'attendance_date' => '2026-10-01',
            'status' => 'late',
        ]);
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $students[1]->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
        ]);
        $this->assertSame(2, AuditLog::query()->where('event', 'student_attendance.marked')->count());

        $this->actingAs($admin)
            ->get(route('attendance.students.index', [
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'date' => '2026-10-01',
            ]))
            ->assertOk()
            ->assertSee('Save attendance')
            ->assertSee($students[0]->full_name);
    }

    public function test_attendance_on_a_holiday_requires_an_explicit_override(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = Student::factory()->create();
        $this->enroll($student, 1);

        Holiday::query()->create([
            'name' => 'Autumn break',
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-15',
            'type' => 'public',
        ]);

        $payload = [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'attendance_date' => '2026-10-15',
            'statuses' => [$student->id => 'present'],
        ];

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), $payload)
            ->assertSessionHasErrors('attendance_date');

        $this->assertSame(0, StudentAttendance::query()->count());

        $payload['override_holiday'] = 1;

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, StudentAttendance::query()->count());

        $log = AuditLog::query()->where('event', 'student_attendance.marked')->firstOrFail();
        $this->assertTrue((bool) $log->new_values['holiday_override']);
    }

    public function test_attendance_must_fall_inside_the_academic_session(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = Student::factory()->create();
        $this->enroll($student, 1);

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'attendance_date' => '2025-12-31',
                'statuses' => [$student->id => 'present'],
            ])
            ->assertSessionHasErrors('attendance_date');

        $this->assertSame(0, StudentAttendance::query()->count());
    }

    public function test_attendance_cannot_be_marked_across_schools(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $adminA = $this->makeUser($schoolA, 'school_admin');
        $this->academicFixtures($schoolA->id);

        $studentA = Student::factory()->create();
        $this->enroll($studentA, 1);

        $this->useSchoolContext($schoolB->id);
        $sessionB = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);
        $classB = SchoolClass::query()->create([
            'name' => 'Class Seven',
            'level' => 7,
            'is_active' => true,
        ]);
        $studentB = Student::factory()->create();
        Enrollment::query()->create([
            'student_id' => $studentB->id,
            'academic_session_id' => $sessionB->id,
            'class_id' => $classB->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        $this->actingAs($adminA)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $sessionB->id,
                'class_id' => $classB->id,
                'attendance_date' => '2026-10-02',
                'statuses' => [$studentB->id => 'present'],
            ])
            ->assertSessionHasErrors(['academic_session_id', 'class_id']);

        $this->assertSame(0, StudentAttendance::query()->withoutGlobalScopes()->count());

        $this->useSchoolContext($schoolA->id);

        $this->actingAs($adminA)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'attendance_date' => '2026-10-02',
                'statuses' => [$studentB->id => 'present'],
            ])
            ->assertSessionHasErrors('statuses');

        $this->assertSame(0, StudentAttendance::query()->count());
    }

    public function test_the_monthly_report_aggregates_statuses_and_exports_a_csv(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = Student::factory()->create();
        $enrollment = $this->enroll($student, 1);

        $dates = ['2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'];

        foreach (['present', 'present', 'present', 'late', 'absent'] as $offset => $status) {
            StudentAttendance::query()->create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'attendance_date' => $dates[$offset],
                'status' => $status,
                'marked_by' => $admin->id,
            ]);
        }

        $filters = [
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'month' => '2026-10',
        ];

        $this->actingAs($admin)
            ->get(route('attendance.students.report', $filters))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('80.0%')
            ->assertSee('Working days');

        $export = $this->actingAs($admin)->get(route('attendance.students.export', $filters));

        $export->assertOk()->assertDownload('attendance-'.$this->class->id.'-2026-10.csv');
        $content = $export->streamedContent();

        $this->assertStringContainsString('Attendance %', $content);
        $this->assertStringContainsString($student->student_id, $content);
        $this->assertStringContainsString('80.0', $content);
    }

    public function test_correcting_an_automated_mark_requires_a_reason_and_locks_the_row(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        $student = Student::factory()->create();
        $enrollment = $this->enroll($student, 1);

        StudentAttendance::query()->create([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'source' => 'qr',
        ]);

        $payload = [
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'attendance_date' => '2026-10-05',
            'statuses' => [$student->id => 'absent'],
        ];

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), $payload)
            ->assertSessionHasErrors('correction_reason');

        $row = StudentAttendance::query()->firstOrFail();
        $this->assertSame('present', $row->status);
        $this->assertNull($row->locked_at);

        $this->actingAs($admin)
            ->post(route('attendance.students.store'), [
                ...$payload,
                'correction_reason' => 'The card belonged to another student',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $row->refresh();
        $this->assertSame('absent', $row->status);
        $this->assertSame('manual', $row->source);
        $this->assertNotNull($row->locked_at);
        $this->assertSame('The card belonged to another student', $row->correction_reason);
        $this->assertSame($admin->id, $row->corrected_by);

        $log = AuditLog::query()->where('event', 'student_attendance.corrected')->firstOrFail();
        $this->assertSame(['status' => 'present'], $log->old_values);
        $this->assertSame('absent', $log->new_values['status']);

        $outcome = app(StudentAttendanceService::class)->applyAutomated(
            actor: null,
            student: $student,
            source: 'fingerprint',
            occurredAt: Carbon::parse('2026-10-05 09:00:00'),
            sourceRef: 'punch-after-correction',
        );

        $this->assertSame('skipped', $outcome['status']);
        $this->assertSame('locked', $outcome['reason']);
        $this->assertSame('absent', $row->fresh()->status);
    }

    public function test_the_report_rate_ignores_weekends_holidays_leave_and_excused(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->academicFixtures($school->id);

        Holiday::query()->create([
            'name' => 'Autumn break',
            'start_date' => '2026-10-11',
            'end_date' => '2026-10-11',
        ]);

        $student = Student::factory()->create();
        $enrollment = $this->enroll($student, 1);

        $rows = [
            '2026-10-04' => 'present',
            '2026-10-05' => 'late',
            '2026-10-06' => 'leave',
            '2026-10-07' => 'excused',
            '2026-10-08' => 'absent',
            '2026-10-09' => 'present', // Friday, a weekend day
            '2026-10-11' => 'present', // holiday
        ];

        foreach ($rows as $date => $status) {
            StudentAttendance::query()->create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'attendance_date' => $date,
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('attendance.students.report', [
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'month' => '2026-10',
            ]))
            ->assertOk()
            ->assertSee('66.7%');

        $report = $response->viewData('report');

        $this->assertSame(5, $report['working_days']);
        $this->assertSame(2, $report['excluded']);
        $this->assertSame(66.7, $report['rows']->first()['rate']);
    }

    public function test_attendance_pages_enforce_role_permissions(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher');
        $accountant = $this->makeUser($school, 'accountant');
        $this->academicFixtures($school->id);

        $student = Student::factory()->create();
        $this->enroll($student, 1);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $this->routine($profile);

        $this->actingAs($teacher)
            ->get(route('attendance.students.index', ['class_id' => $this->class->id]))
            ->assertOk();

        $this->actingAs($teacher)
            ->get(route('attendance.students.report', ['class_id' => $this->class->id, 'month' => '2026-10']))
            ->assertOk();

        $this->actingAs($teacher)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'attendance_date' => '2026-10-05',
                'statuses' => [$student->id => 'present'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $student->id,
            'attendance_date' => '2026-10-05',
            'source' => 'manual',
        ]);

        $this->actingAs($accountant)->get(route('attendance.students.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('attendance.students.report'))->assertForbidden();
        $this->actingAs($accountant)->get(route('attendance.students.export'))->assertForbidden();
        $this->actingAs($accountant)->get(route('attendance.staff.index'))->assertForbidden();

        $this->actingAs($accountant)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'attendance_date' => '2026-10-05',
                'statuses' => [$student->id => 'present'],
            ])
            ->assertForbidden();
    }

    public function test_a_teacher_is_restricted_to_the_classes_on_their_timetable(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $otherTeacher = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $this->routine($profile);

        Teacher::factory()->create(['user_id' => $otherTeacher->id]);

        $foreignClass = SchoolClass::query()->create([
            'name' => 'Class Seven',
            'level' => 7,
            'is_active' => true,
        ]);
        $foreignSection = Section::query()->create([
            'class_id' => $foreignClass->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $foreign = Student::factory()->create();
        Enrollment::query()->create([
            'student_id' => $foreign->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $foreignClass->id,
            'section_id' => $foreignSection->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->get(route('attendance.students.index'))
            ->assertOk()
            ->assertSee('Class Six')
            ->assertDontSee('Class Seven');

        $this->actingAs($teacher)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $foreignClass->id,
                'attendance_date' => '2026-10-05',
                'statuses' => [$foreign->id => 'present'],
            ])
            ->assertForbidden();

        $this->actingAs($otherTeacher)
            ->post(route('attendance.students.store'), [
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'attendance_date' => '2026-10-05',
                'statuses' => [$foreign->id => 'present'],
            ])
            ->assertForbidden();

        $this->assertSame(0, StudentAttendance::query()->count());
    }

    public function test_staff_attendance_is_marked_for_teachers_and_employees_without_duplicates(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->useSchoolContext($school->id);

        $teacher = Teacher::factory()->create();
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-2026-0100',
            'first_name' => 'Salma',
            'last_name' => 'Khatun',
            'gender' => 'female',
            'phone' => '01710000100',
            'designation' => 'Accountant',
            'department' => 'Accounts',
            'joining_date' => '2026-01-04',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $payload = [
            'attendance_date' => '2026-10-06',
            'teachers' => [$teacher->id => 'present'],
            'employees' => [$employee->id => 'late'],
        ];

        $this->actingAs($admin)
            ->post(route('attendance.staff.store'), $payload)
            ->assertRedirect(route('attendance.staff.index', ['date' => '2026-10-06']))
            ->assertSessionHasNoErrors();

        $this->useSchoolContext($school->id);

        $this->assertSame(2, StaffAttendance::query()->count());
        $this->assertDatabaseHas('staff_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => '2026-10-06',
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('staff_attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-06',
            'status' => 'late',
        ]);
        $this->assertSame(1, AuditLog::query()->where('event', 'staff_attendance.marked')->count());

        $this->actingAs($admin)
            ->post(route('attendance.staff.store'), [
                'attendance_date' => '2026-10-06',
                'teachers' => [$teacher->id => 'absent'],
            ])
            ->assertSessionHasNoErrors();

        $this->useSchoolContext($school->id);

        $this->assertSame(2, StaffAttendance::query()->count());
        $this->assertDatabaseHas('staff_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => '2026-10-06',
            'status' => 'absent',
        ]);
        $this->assertDatabaseHas('staff_attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-10-06',
            'status' => 'late',
        ]);

        $this->actingAs($admin)
            ->get(route('attendance.staff.index', ['date' => '2026-10-06']))
            ->assertOk()
            ->assertSee($teacher->full_name)
            ->assertSee('Salma Khatun')
            ->assertSee('October 2026');
    }

    public function test_staff_attendance_respects_holidays(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->useSchoolContext($school->id);

        $teacher = Teacher::factory()->create();

        Holiday::query()->create([
            'name' => 'Founder day',
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-07',
            'type' => 'school',
        ]);

        $this->actingAs($admin)
            ->post(route('attendance.staff.store'), [
                'attendance_date' => '2026-10-07',
                'teachers' => [$teacher->id => 'present'],
            ])
            ->assertSessionHasErrors('attendance_date');

        $this->useSchoolContext($school->id);
        $this->assertSame(0, StaffAttendance::query()->count());

        $this->actingAs($admin)
            ->post(route('attendance.staff.store'), [
                'attendance_date' => '2026-10-07',
                'teachers' => [$teacher->id => 'present'],
                'override_holiday' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->useSchoolContext($school->id);
        $this->assertSame(1, StaffAttendance::query()->count());
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

    private function enroll(Student $student, int $roll): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
    }

    private function routine(Teacher $teacher): void
    {
        $subject = Subject::query()->firstOrCreate(
            ['name' => 'Mathematics'],
            ['type' => 'theory', 'is_active' => true],
        );

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '09:45',
        ]);
    }
}
