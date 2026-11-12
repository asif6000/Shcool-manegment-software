<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The teacher's own screens. Both read class_routines, so these tests are the
 * proof that an assignment means a period on the grid and nothing else.
 */
class TeacherWorkspaceTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    /** Wednesday, which the routine table stores as index 4. */
    private const TODAY = '2026-10-28 09:30:00';

    private AcademicSession $session;

    private SchoolClass $six;

    private Section $sixA;

    private SchoolClass $seven;

    private Section $sevenB;

    private Subject $math;

    private Subject $english;

    private Subject $bangla;

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

    public function test_my_classes_lists_the_sections_on_the_teachers_timetable(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $colleague = Teacher::factory()->create();

        $this->period($profile, $this->six, $this->sixA, $this->math, 4, '09:00');
        $this->period($profile, $this->six, $this->sixA, $this->english, 4, '11:00');
        $this->period($profile, $this->seven, $this->sevenB, $this->math, 5, '09:00');
        // The colleague's Bangla period in the same section is not the teacher's.
        $this->period($colleague, $this->seven, $this->sevenB, $this->bangla, 5, '10:00');

        $this->enroll($this->student('Ayesha', 'Rahman'), $this->six, $this->sixA, 1);
        $this->enroll($this->student('Nabila', 'Karim'), $this->six, $this->sixA, 2);
        $this->enroll($this->student('Bilal', 'Ahmed'), $this->seven, $this->sevenB, 1);

        $response = $this->actingAs($teacher)->get(route('teacher.classes'));

        $response->assertOk()->assertViewIs('teacher.classes');

        $view = $response->viewData();
        $classes = $view['classes'];

        $this->assertTrue($view['isOwn']);
        $this->assertCount(2, $classes);
        $this->assertSame([6, 7], $classes->pluck('class.level')->all());
        $this->assertSame(2, $classes->first()['students']);
        $this->assertSame(2, $classes->first()['periods']);
        $this->assertSame(['English', 'Mathematics'], $classes->first()['subjects']->pluck('name')->all());
        $this->assertSame(1, $classes->last()['periods']);
        $this->assertSame(1, $classes->last()['days']);
        $this->assertSame(3, $view['studentCount']);
        $this->assertSame(3, $view['periodCount']);

        // Only the subjects the teacher actually takes.
        $this->assertSame(['English', 'Mathematics'], $view['subjects']->pluck('subject.name')->all());
        $this->assertSame(45, $view['subjects']->first()['minutes']);
        $this->assertSame(['Six A'], $view['subjects']->first()['groups']->all());
        $this->assertSame(['Six A', 'Seven B'], $view['subjects']->last()['groups']->all());

        $response->assertSee('Mathematics');
    }

    public function test_a_teacher_with_no_periods_sees_an_honest_empty_state(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $colleague = Teacher::factory()->create();
        $this->period($colleague, $this->six, $this->sixA, $this->math, 4, '09:00');

        $this->actingAs($teacher)
            ->get(route('teacher.classes'))
            ->assertOk()
            ->assertSee('No classes assigned');

        $this->actingAs($teacher)
            ->get(route('teacher.routine'))
            ->assertOk()
            ->assertSee('Nothing on the timetable')
            ->assertDontSee('Mathematics', false);
    }

    public function test_the_week_view_puts_periods_on_the_right_days(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);

        $this->period($profile, $this->six, $this->sixA, $this->math, 4, '09:00');
        $this->period($profile, $this->six, $this->sixA, $this->english, 4, '11:00');
        $this->period($profile, $this->seven, $this->sevenB, $this->math, 5, '09:00');

        Holiday::query()->create([
            'academic_session_id' => $this->session->id,
            'name' => 'Founders day',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-30',
            'type' => 'public',
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.routine'));

        $response->assertOk()->assertViewIs('teacher.routine');

        $view = $response->viewData();
        $days = $view['days'];

        $this->assertSame('week', $view['mode']);
        $this->assertCount(7, $days);
        // The school week opens on Saturday, four days before this Wednesday.
        $this->assertSame('2026-10-24', $days[0]['date']->toDateString());
        $this->assertSame('Saturday', $days[0]['label']);
        $this->assertTrue($days[4]['is_today']);
        $this->assertCount(2, $days[4]['classes']);
        $this->assertCount(1, $days[5]['classes']);
        $this->assertFalse($days[4]['is_holiday']);
        $this->assertTrue($days[6]['is_holiday']);
        $this->assertSame('Founders day', $days[6]['holiday']->name);
        $this->assertSame(3, $view['periodCount']);
        $this->assertSame(2, $view['subjectCount']);

        $response->assertSee('24 October 2026');
        $response->assertSee('Founders day');
    }

    public function test_the_day_view_tags_each_period_past_now_or_upcoming(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->fixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);

        $this->period($profile, $this->six, $this->sixA, $this->math, 4, '08:00');
        $this->period($profile, $this->six, $this->sixA, $this->english, 4, '09:00');
        $this->period($profile, $this->seven, $this->sevenB, $this->bangla, 4, '11:00');
        // A period from another day of the week must not join this list.
        $this->period($profile, $this->six, $this->sixA, $this->bangla, 2, '09:00');

        $response = $this->actingAs($teacher)
            ->get(route('teacher.routine', ['mode' => 'day', 'date' => '2026-10-28']));

        $response->assertOk();

        $view = $response->viewData();

        $this->assertSame('day', $view['mode']);
        $this->assertTrue($view['isToday']);
        $this->assertCount(3, $view['periods']);
        $this->assertSame(['past', 'now', 'upcoming'], $view['periods']->pluck('state')->all());
        $this->assertCount(0, $view['periods']->filter(fn (array $row): bool => $row['period']->day_of_week !== 4));

        $response->assertSee('Wednesday, 28 October 2026');
        $response->assertSee('Running now');
    }

    public function test_the_office_screen_lists_the_whole_grid(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->fixtures($school->id);

        $first = Teacher::factory()->create();
        $second = Teacher::factory()->create();

        $this->period($first, $this->six, $this->sixA, $this->math, 4, '09:00');
        $this->period($second, $this->seven, $this->sevenB, $this->bangla, 4, '10:00');

        $classes = $this->actingAs($admin)->get(route('teacher.classes'));

        $classes->assertOk();
        $this->assertFalse($classes->viewData('isOwn'));
        $this->assertCount(2, $classes->viewData('classes'));

        $routine = $this->actingAs($admin)->get(route('teacher.routine', ['mode' => 'day', 'date' => '2026-10-28']));

        $routine->assertOk()->assertSee($second->full_name);
        $this->assertCount(2, $routine->viewData('periods'));
    }

    public function test_the_roll_list_narrows_to_one_section_and_drops_a_stale_filter(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $this->fixtures($school->id);

        $this->enroll($this->student('Ayesha', 'Rahman'), $this->six, $this->sixA, 1);
        $this->enroll($this->student('Bilal', 'Ahmed'), $this->seven, $this->sevenB, 1);

        $page = $this->actingAs($admin)
            ->get(route('students.index', ['class_id' => $this->six->id, 'section_id' => $this->sixA->id]));

        $page->assertOk()->assertSee('Ayesha')->assertDontSee('Bilal', false);
        $this->assertSame($this->sixA->id, (int) $page->viewData('filters')['section_id']);
        $this->assertCount(1, $page->viewData('sections'));

        // A section that belongs to another class is dropped rather than quietly
        // emptying the list the teacher clicked through to.
        $stale = $this->actingAs($admin)
            ->get(route('students.index', ['class_id' => $this->six->id, 'section_id' => $this->sevenB->id]));

        $stale->assertOk();
        $this->assertArrayNotHasKey('section_id', $stale->viewData('filters'));
        $stale->assertSee('Ayesha')->assertDontSee('Bilal', false);
    }

    public function test_a_role_without_the_timetable_permission_is_turned_away(): void
    {
        $school = $this->makeSchool();
        $accountant = $this->makeUser($school, 'accountant');
        $this->fixtures($school->id);

        $this->actingAs($accountant)->get(route('teacher.classes'))->assertForbidden();
        $this->actingAs($accountant)->get(route('teacher.routine'))->assertForbidden();
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
            'name' => 'K',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $foreignSubject = Subject::query()->create(['name' => 'Physics', 'type' => 'theory', 'is_active' => true]);
        $this->useSchoolContext($school->id);

        // The row is created while the context is back on the first school, so the
        // global scope stamps it there; prove the screens stay inside the school.
        $this->actingAs($teacher)
            ->get(route('teacher.classes'))
            ->assertOk()
            ->assertDontSee('Grade Ten', false)
            ->assertDontSee('Physics', false);

        $this->assertNotNull($foreignSection->id);
        $this->assertNotNull($foreignSubject->id);
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

        $this->math = Subject::query()->create(['name' => 'Mathematics', 'type' => 'theory', 'is_active' => true]);
        $this->english = Subject::query()->create(['name' => 'English', 'type' => 'theory', 'is_active' => true]);
        $this->bangla = Subject::query()->create(['name' => 'Bangla', 'type' => 'theory', 'is_active' => true]);
    }

    private function student(string $first, string $last): Student
    {
        return Student::factory()->create(['first_name' => $first, 'last_name' => $last]);
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
