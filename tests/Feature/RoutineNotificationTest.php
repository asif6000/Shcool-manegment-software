<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TimetableService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class RoutineNotificationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Section $otherSection;

    private Subject $subject;

    private Teacher $teacher;

    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->otherSection = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'B',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->teacherUser = $this->makeUser($this->school, 'teacher');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);
    }

    public function test_a_new_slot_notifies_the_section_students_and_the_teacher_but_not_other_sections(): void
    {
        [$student, $studentUser] = $this->enrolledStudent($this->section, 1);
        [, $otherStudentUser] = $this->enrolledStudent($this->otherSection, 2);

        $routine = app(TimetableService::class)->create($this->admin, $this->slotData());

        $studentNotification = $studentUser->notifications()->firstOrFail();
        $teacherNotification = $this->teacherUser->notifications()->firstOrFail();

        $this->assertSame('routine', $studentNotification->category);
        $this->assertSame(route('student.routine'), $studentNotification->data['url']);
        $this->assertStringContainsString($this->class->name, $studentNotification->data['message']);
        $this->assertStringNotContainsString($this->class->name, (string) $studentNotification->data['push_body']);

        $this->assertSame('routine', $teacherNotification->category);
        $this->assertSame(route('teacher.routine'), $teacherNotification->data['url']);

        $this->assertSame(0, $otherStudentUser->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());
        $this->assertSame($this->section->id, $routine->section_id);
    }

    public function test_updating_the_slot_sends_a_new_routine_notification(): void
    {
        [, $studentUser] = $this->enrolledStudent($this->section, 1);

        $service = app(TimetableService::class);
        $routine = $service->create($this->admin, $this->slotData());

        $this->assertSame(1, $studentUser->notifications()->count());
        $this->assertSame(1, $this->teacherUser->notifications()->count());

        $service->update($this->admin, $routine, $this->slotData([
            'start_time' => '10:00',
            'end_time' => '10:45',
            'room' => 'Lab 2',
        ]));

        $this->assertSame(2, $studentUser->notifications()->count());
        $this->assertSame(2, $this->teacherUser->notifications()->count());
    }

    public function test_removing_the_slot_sends_a_removal_notice(): void
    {
        [, $studentUser] = $this->enrolledStudent($this->section, 1);

        $service = app(TimetableService::class);
        $routine = $service->create($this->admin, $this->slotData());

        $service->delete($this->admin, $routine);

        $this->assertSame(2, $studentUser->notifications()->count());
        $this->assertSame(2, $this->teacherUser->notifications()->count());

        $removal = $studentUser->notifications()
            ->where('dedupe_key', 'like', 'routine.deleted:%')
            ->firstOrFail();

        $this->assertStringContainsString('removed', $removal->data['message']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slotData(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '09:45',
            'room' => 'Room 101',
        ], $overrides);
    }

    /**
     * @return array{0: Student, 1: User}
     */
    private function enrolledStudent(Section $section, int $roll): array
    {
        $student = Student::factory()->create();
        $user = $this->makeUser($this->school, 'student');

        $student->forceFill(['user_id' => $user->id])->save();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'roll_no' => $roll,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);

        return [$student, $user];
    }
}
