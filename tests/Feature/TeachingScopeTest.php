<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\TeachingScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class TeachingScopeTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $math;

    private Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_teachers_assignments_come_from_the_timetable(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $this->period($profile, $this->math, 1, '09:00');
        $this->period($profile, $this->english, 1, '10:00');
        $this->period($profile, $this->math, 2, '09:00');

        $scope = app(TeachingScope::class);

        $slots = $scope->slots($teacher->fresh(), $this->session);

        $this->assertNotNull($slots);
        $this->assertCount(3, $slots);
        $this->assertSame(
            [$this->class->id, $this->section->id, $this->math->id],
            [$slots->first()['class_id'], $slots->first()['section_id'], $slots->first()['subject_id']],
        );

        $this->assertCount(1, $scope->pairs($teacher->fresh(), $this->session));
        $this->assertSame([$this->section->id], $scope->sectionIds($teacher->fresh(), $this->session, $this->class->id)->all());
        $this->assertEqualsCanonicalizing(
            [$this->math->id, $this->english->id],
            $scope->subjectIds($teacher->fresh(), $this->session, $this->class->id)->all(),
        );

        $foreignClass = SchoolClass::query()->create(['name' => 'Class Seven', 'level' => 7, 'is_active' => true]);

        $this->assertSame([], $scope->subjectIds($teacher->fresh(), $this->session, $foreignClass->id)->all());
    }

    public function test_allows_narrows_by_section_and_subject(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $other = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $profile = Teacher::factory()->create(['user_id' => $teacher->id]);
        $this->period($profile, $this->math, 1, '09:00');
        $foreignSection = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'B',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $scope = app(TeachingScope::class);

        $this->assertTrue($scope->allows($teacher, $this->session, $this->class->id, $this->section->id));
        $this->assertTrue($scope->allows($teacher, $this->session, $this->class->id, null, $this->math->id));
        $this->assertFalse($scope->allows($teacher, $this->session, $this->class->id, $foreignSection->id));
        $this->assertFalse($scope->allows($teacher, $this->session, $this->class->id, null, $this->english->id));

        Teacher::factory()->create(['user_id' => $other->id]);

        $this->assertFalse($scope->allows($other, $this->session, $this->class->id));
        $this->assertCount(0, $scope->slots($other, $this->session));
    }

    public function test_administrators_and_principals_are_unrestricted(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');
        $principal = $this->makeUser($school, 'principal');
        $this->academicFixtures($school->id);

        $scope = app(TeachingScope::class);

        $this->assertNull($scope->slots($admin, $this->session));
        $this->assertNull($scope->pairs($principal, $this->session));
        $this->assertTrue($scope->allows($principal, $this->session, $this->class->id, $this->section->id, $this->english->id));
    }

    public function test_a_staff_member_without_a_teacher_profile_sees_no_classes(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $this->academicFixtures($school->id);

        $scope = app(TeachingScope::class);

        $this->assertSame([], $scope->slots($teacher, $this->session)->all());
        $this->assertFalse($scope->allows($teacher, $this->session, $this->class->id));
        $this->assertNull($scope->teacherId($teacher));
    }

    public function test_a_teacher_cannot_reach_the_financial_reports(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        $accountant = $this->makeUser($school, 'accountant');
        $this->academicFixtures($school->id);

        Teacher::factory()->create(['user_id' => $teacher->id]);

        // A stale reports.view row must not be enough on its own.
        $teacher->givePermissionTo('reports.view');

        $this->actingAs($teacher->fresh())
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($teacher->fresh())
            ->get(route('reports.collection'))
            ->assertForbidden();

        $this->actingAs($teacher->fresh())
            ->get(route('reports.students'))
            ->assertForbidden();

        $this->actingAs($teacher->fresh())
            ->get(route('reports.export', ['report' => 'collection']))
            ->assertForbidden();

        $this->actingAs($accountant)
            ->get(route('reports.index'))
            ->assertOk();
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

        $this->math = Subject::query()->create(['name' => 'Mathematics', 'type' => 'theory', 'is_active' => true]);
        $this->english = Subject::query()->create(['name' => 'English', 'type' => 'theory', 'is_active' => true]);
    }

    private function period(Teacher $teacher, Subject $subject, int $day, string $startTime): void
    {
        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => $day,
            'start_time' => $startTime,
            'end_time' => (string) (Carbon::parse($startTime)->addMinutes(45)),
        ]);
    }
}
