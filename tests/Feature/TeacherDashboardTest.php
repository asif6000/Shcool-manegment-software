<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LeaveType;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private const TODAY = '2026-10-28 09:30:00';

    private AcademicSession $session;

    private SchoolClass $six;

    private Section $sixA;

    private SchoolClass $seven;

    private Section $sevenB;

    private SchoolClass $eight;

    private Section $eightC;

    private Subject $math;

    private Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TODAY));

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_dashboard_counts_only_the_teachers_own_classes(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);

        // Two sections on the timetable, plus one class the teacher never sees.
        $this->period($profile, $this->six, $this->sixA, $this->math, 4, '09:00');
        $this->period($profile, $this->six, $this->sixA, $this->english, 4, '10:00');
        $this->period($profile, $this->seven, $this->sevenB, $this->math, 5, '09:00');

        $sixStudents = Student::factory()->count(2)->create();
        $sixFirst = $this->enroll($sixStudents[0], $this->six, $this->sixA, 1);
        $sixSecond = $this->enroll($sixStudents[1], $this->six, $this->sixA, 2);
        $this->enroll(Student::factory()->create(), $this->seven, $this->sevenB, 1);
        $this->enroll(Student::factory()->create(), $this->eight, $this->eightC, 1);

        // Class Six A is already marked for today; Seven B is not.
        foreach ([$sixFirst, $sixSecond] as $index => $enrollment) {
            StudentAttendance::query()->create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->six->id,
                'section_id' => $this->sixA->id,
                'attendance_date' => '2026-10-28',
                'status' => $index === 0 ? 'present' : 'absent',
                'source' => 'manual',
                'marked_by' => $teacher->id,
            ]);
        }

        $homework = Homework::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->six->id,
            'section_id' => $this->sixA->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $profile->id,
            'title' => 'Algebra worksheet',
            'description' => 'Exercise 4.2',
            'assigned_date' => '2026-10-26',
            'due_date' => '2026-10-30',
            'created_by' => $teacher->id,
        ]);

        HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $sixStudents[0]->id,
            'status' => 'submitted',
            'submitted_at' => '2026-10-27 15:00:00',
        ]);

        // A foreign class with homework due tomorrow must not leak in.
        Homework::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->eight->id,
            'section_id' => $this->eightC->id,
            'subject_id' => $this->math->id,
            'title' => 'Foreign homework',
            'description' => 'Not mine',
            'assigned_date' => '2026-10-26',
            'due_date' => '2026-10-29',
            'created_by' => $teacher->id,
        ]);

        $exam = Exam::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Term Test 2026',
            'exam_type' => 'term',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'created_by' => $teacher->id,
        ]);

        $paper = ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'class_id' => $this->six->id,
            'subject_id' => $this->math->id,
            'exam_date' => '2026-10-22',
            'full_marks' => 100,
            'pass_marks' => 40,
        ]);

        ExamMark::query()->create([
            'exam_subject_id' => $paper->id,
            'student_id' => $sixStudents[0]->id,
            'marks_obtained' => 78,
            'entered_by' => $teacher->id,
        ]);

        Notice::query()->create([
            'academic_session_id' => $this->session->id,
            'title' => 'Staff meeting on Sunday',
            'body' => 'Please attend.',
            'audience' => 'teachers',
            'published_at' => '2026-10-27 09:00:00',
            'created_by' => $teacher->id,
        ]);

        StaffAttendance::query()->create([
            'teacher_id' => $profile->id,
            'attendance_date' => '2026-10-28',
            'status' => 'late',
            'late_minutes' => 12,
        ]);

        $leaveType = LeaveType::query()->create([
            'name' => 'Casual leave',
            'code' => 'casual',
            'max_days_per_year' => 10,
            'is_paid' => true,
            'is_active' => true,
        ]);

        StaffLeave::query()->create([
            'leave_type_id' => $leaveType->id,
            'teacher_id' => $profile->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'total_days' => 2,
            'reason' => 'Family work',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($teacher)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.teacher');

        $view = $response->viewData();

        $this->assertSame(2, data_get($view, 'cards.classes'));
        $this->assertSame(2, data_get($view, 'cards.subjects'));
        $this->assertSame(3, data_get($view, 'cards.students'));

        $this->assertCount(2, data_get($view, 'timetable.today'));
        $this->assertSame(2, data_get($view, 'timetable.week')[4]);
        $this->assertSame(1, data_get($view, 'timetable.week')[5]);

        $this->assertTrue(data_get($view, 'attendance.countable'));
        $this->assertSame(1, data_get($view, 'attendance.marked'));
        $this->assertSame(2, data_get($view, 'attendance.total'));
        $this->assertSame('Seven · B', data_get($view, 'attendance.pending')->first()['label']);

        $this->assertCount(1, data_get($view, 'homework.due'));
        $this->assertSame('Algebra worksheet', data_get($view, 'homework.due')->first()->title);
        $this->assertSame(1, data_get($view, 'homework.pending_reviews'));

        $this->assertSame(1, data_get($view, 'marks.papers'));
        $this->assertSame(1, data_get($view, 'marks.entered'));
        $this->assertSame(1, data_get($view, 'marks.list')->first()['remaining']);

        $this->assertCount(1, data_get($view, 'notices'));
        $this->assertSame(1, data_get($view, 'own.staff_attendance')['late']);
        $this->assertCount(1, data_get($view, 'own.leave'));

        // Finance and admissions never appear on a teacher dashboard.
        $response->assertDontSee(e(__('Outstanding Fees')));
        $response->assertDontSee('Foreign homework');

        // The page itself is the four module cards plus their panels.
        $response->assertSee('data-td-panel="attendance"', false);
        $response->assertSee('data-td-panel="results"', false);

        // The four module screens are folded into this page, so their content is
        // served by the same routes asked for as a fragment. Same permission
        // check, same timetable scope, same data — only the chrome is missing.
        $this->actingAs($teacher)
            ->get(route('teacher.homework.index', ['fragment' => 1]))
            ->assertOk()
            ->assertSee('Algebra worksheet')
            ->assertDontSee('Foreign homework');

        $this->actingAs($teacher)
            ->get(route('attendance.students.index', [
                'session_id' => $this->session->id,
                'class_id' => $this->seven->id,
                'section_id' => $this->sevenB->id,
                'date' => '2026-10-28',
                'fragment' => 1,
            ]))
            ->assertOk()
            ->assertSee('Seven · B');

        $this->actingAs($teacher)
            ->get(route('materials.index', ['kind' => 'classwork', 'fragment' => 1]))
            ->assertOk();

        $this->actingAs($teacher)
            ->get(route('dashboard.results'))
            ->assertOk();
    }

    public function test_a_teacher_from_another_school_sees_nothing(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $this->period($profile, $this->six, $this->sixA, $this->math, 4, '09:00');

        $other = $this->makeSchool();
        $this->useSchoolContext($other->id);
        $foreignClass = SchoolClass::query()->create(['name' => 'Grade Ten', 'level' => 10, 'is_active' => true]);
        $foreignSection = Section::query()->create([
            'class_id' => $foreignClass->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
        Notice::query()->create([
            'title' => 'Other school notice',
            'body' => 'Should never appear.',
            'audience' => 'teachers',
            'published_at' => '2026-10-27 09:00:00',
        ]);
        $this->useSchoolContext($school->id);

        $response = $this->actingAs($teacher)->get(route('dashboard'));

        $response->assertOk();
        $view = $response->viewData();
        $this->assertSame(1, data_get($view, 'cards.classes'));
        $this->assertSame(0, data_get($view, 'cards.students'));
        $response->assertDontSee('Other school notice');
        $response->assertDontSee('Grade Ten');
    }

    private function fixtures(int $schoolId): void
    {
        $this->useSchoolContext($schoolId);

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->six = SchoolClass::query()->create(['name' => 'Six', 'level' => 6, 'is_active' => true]);
        $this->sixA = Section::query()->create(['class_id' => $this->six->id, 'name' => 'A', 'capacity' => 40, 'is_active' => true]);
        $this->seven = SchoolClass::query()->create(['name' => 'Seven', 'level' => 7, 'is_active' => true]);
        $this->sevenB = Section::query()->create(['class_id' => $this->seven->id, 'name' => 'B', 'capacity' => 40, 'is_active' => true]);
        $this->eight = SchoolClass::query()->create(['name' => 'Eight', 'level' => 8, 'is_active' => true]);
        $this->eightC = Section::query()->create(['class_id' => $this->eight->id, 'name' => 'C', 'capacity' => 40, 'is_active' => true]);

        $this->math = Subject::query()->create(['name' => 'Mathematics', 'type' => 'theory', 'is_active' => true]);
        $this->english = Subject::query()->create(['name' => 'English', 'type' => 'theory', 'is_active' => true]);
    }

    private function period(Teacher $teacher, SchoolClass $class, Section $section, Subject $subject, int $day, string $start): ClassRoutine
    {
        return ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => Carbon::parse($start)->addMinutes(45)->format('H:i'),
        ]);
    }

    private function enroll(Student $student, SchoolClass $class, Section $section, int $roll): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
    }
}
