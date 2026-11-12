<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The office half of the unified homework screen: it reads every teacher's
 * homework and class work, but it has no write route at all. Each assertion
 * goes through a real URL so a hidden button cannot stand in for the backend.
 */
class HomeworkMonitorReadTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $teacherUser;

    private Teacher $teacher;

    private User $colleagueUser;

    private Teacher $colleague;

    private User $studentUser;

    private Student $student;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $sectionA;

    private Subject $mathematics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->buildSchool();
    }

    public function test_the_office_reads_homework_and_class_work_from_every_teacher(): void
    {
        $this->publishHomework('Algebra worksheet', $this->teacherUser);
        $this->publishClasswork('Fraction circles', $this->colleagueUser);

        $list = $this->actingAs($this->admin)
            ->get(route('work-monitor.index'))
            ->assertOk();

        $this->assertSame(
            ['Fraction circles', 'Algebra worksheet'],
            $list->viewData('records')->pluck('title')->all(),
        );

        $counts = $list->viewData('counts');
        $this->assertSame(2, $counts['all']);
        $this->assertSame(1, $counts['homework']);
        $this->assertSame(1, $counts['classwork']);
        $this->assertSame(2, $counts['published']);

        // One tab shows only that kind of work.
        $this->assertSame(
            ['Fraction circles'],
            $this->actingAs($this->admin)
                ->get(route('work-monitor.index', ['type' => 'classwork']))
                ->assertOk()
                ->viewData('records')
                ->pluck('title')
                ->all(),
        );

        $this->assertSame(
            ['Algebra worksheet'],
            $this->actingAs($this->admin)
                ->get(route('work-monitor.index', ['type' => 'homework']))
                ->assertOk()
                ->viewData('records')
                ->pluck('title')
                ->all(),
        );

        // A draft is invisible to students but still on the office list.
        $this->actingAs($this->teacherUser)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'title' => 'Draft worksheet',
                'publish' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Draft worksheet'],
            $this->actingAs($this->admin)
                ->get(route('work-monitor.index', ['status' => 'draft']))
                ->assertOk()
                ->viewData('records')
                ->pluck('title')
                ->all(),
        );
    }

    public function test_the_monitor_screen_carries_no_write_action_at_all(): void
    {
        $names = array_filter(
            array_keys(Route::getRoutes()->getRoutesByName()),
            fn (string $name): bool => Str::startsWith($name, 'work-monitor.'),
        );

        $this->assertNotEmpty($names);

        foreach ($names as $name) {
            $this->assertSame(
                ['GET', 'HEAD'],
                Route::getRoutes()->getRoutesByName()[$name]->methods(),
                "The office screen {$name} must stay read-only.",
            );
        }

        $homework = $this->publishedHomework();

        // The record page offers no save, publish or delete control.
        $page = $this->actingAs($this->admin)
            ->get(route('work-monitor.show', $homework))
            ->assertOk()
            ->assertSee('Algebra worksheet');

        $html = $page->getContent();
        $this->assertStringNotContainsString((string) route('teacher.homework.destroy', $homework), $html);
        $this->assertStringNotContainsString((string) route('teacher.homework.publish', $homework), $html);
        $this->assertStringNotContainsString((string) route('teacher.homework.edit', $homework), $html);
        $this->assertStringNotContainsString('name="_method"', $html);
    }

    public function test_the_office_opens_a_record_and_sees_the_roll_it_was_set_for(): void
    {
        $homework = $this->publishedHomework();

        $page = $this->actingAs($this->admin)
            ->get(route('work-monitor.show', $homework))
            ->assertOk();

        $this->assertSame($homework->id, $page->viewData('homework')->id);
        $this->assertSame(1, $page->viewData('expected'));
        $this->assertSame(0, $page->viewData('submissions')->total());
    }

    public function test_a_teacher_or_student_cannot_open_the_office_monitor(): void
    {
        $homework = $this->publishedHomework();

        // A teacher is staff, so the request reaches the permission check and is
        // answered with a refusal rather than a redirect.
        $this->assertFalse($this->teacherUser->can('homework.monitor'));
        $this->actingAs($this->teacherUser)->get(route('work-monitor.index'))->assertForbidden();
        $this->actingAs($this->teacherUser)->get(route('work-monitor.show', $homework))->assertForbidden();
        $this->actingAs($this->teacherUser)->get(route('work-monitor.attachment', $homework))->assertForbidden();

        // A pupil account never enters the staff workspace at all, so the gate
        // for that area sends them back to their own portal.
        $this->assertFalse($this->studentUser->can('homework.monitor'));
        $this->actingAs($this->studentUser)
            ->get(route('work-monitor.index'))
            ->assertRedirect();

        $accountant = $this->makeUser($this->school, 'accountant');

        $this->assertFalse($accountant->can('homework.monitor'));
        $this->actingAs($accountant)->get(route('work-monitor.index'))->assertForbidden();
    }

    public function test_the_office_downloads_a_teachers_attachment_and_a_pupils_answer(): void
    {
        Storage::fake('school_uploads');

        $homework = $this->publishedHomework();
        $homework->forceFill(['attachment_path' => 'homework/'.$this->school->id.'/worksheet.txt'])->save();
        Storage::disk('school_uploads')->put($homework->attachment_path, 'answer the sums');

        $submission = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $this->student->id,
            'status' => 'submitted',
            'text_answer' => 'I finished every question.',
            'attachment_path' => 'submissions/'.$this->school->id.'/answer.txt',
            'attachment_name' => 'answer.txt',
            'submitted_at' => now(),
        ]);
        Storage::disk('school_uploads')->put($submission->attachment_path, 'pupil writing');

        $this->actingAs($this->admin)
            ->get(route('work-monitor.attachment', $homework))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->admin)
            ->get(route('work-monitor.submission.file', $submission))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $empty = $this->publishedHomework('Word problems');

        // A record with no file is a 404 rather than an empty download.
        $this->actingAs($this->admin)
            ->get(route('work-monitor.attachment', $empty))
            ->assertNotFound();
    }

    public function test_the_admin_marks_and_reviews_nothing_from_the_office_urls(): void
    {
        $homework = $this->publishedHomework();

        $submission = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $this->student->id,
            'status' => 'submitted',
            'text_answer' => 'I finished every question.',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('teacher.homework.create'))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->get(route('teacher.homework.edit', $homework))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.cancel', $homework))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('teacher.homework.submissions.review', $submission), [
                'marks' => '20',
                'feedback' => 'The office is marking.',
            ])
            ->assertForbidden();

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
        $this->assertNull($submission->marks);
        $this->assertSame(Homework::PUBLISHED, $homework->refresh()->status);
    }

    public function test_every_work_list_carries_the_poller(): void
    {
        $this->publishHomework('Algebra worksheet', $this->teacherUser);

        $screens = [
            'teacher' => $this->actingAs($this->teacherUser)->get(route('teacher.homework.index')),
            'student' => $this->actingAs($this->studentUser)->get(route('student.homework.index')),
            'office' => $this->actingAs($this->admin)->get(route('work-monitor.index')),
        ];

        foreach ($screens as $role => $response) {
            $response->assertOk();

            // The region the poller replaces, the hint it writes, and the script.
            $this->assertStringContainsString('data-work-live-list', $response->getContent(), $role.' list');
            $this->assertStringContainsString('data-work-live-state', $response->getContent(), $role.' hint');
            $this->assertStringContainsString('assets/js/work-live.js', $response->getContent(), $role.' script');
        }
    }

    private function buildSchool(): void
    {
        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin');
        $this->teacherUser = $this->makeUser($this->school, 'teacher');
        $this->colleagueUser = $this->makeUser($this->school, 'teacher');

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);

        $this->sectionA = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->mathematics = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);
        $this->colleague = Teacher::factory()->create(['user_id' => $this->colleagueUser->id]);

        $this->routine($this->teacher, 3, '09:00');
        $this->routine($this->colleague, 4, '10:00');

        $this->studentUser = $this->makeUser($this->school, 'student');
        $this->student = Student::factory()->create();
        $this->student->forceFill(['user_id' => $this->studentUser->id])->save();

        Enrollment::query()->create([
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'roll_no' => 1,
            'enrolled_on' => '2026-01-02',
            'status' => 'active',
        ]);
    }

    private function routine(Teacher $teacher, int $day, string $start): void
    {
        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->sectionA->id,
            'subject_id' => $this->mathematics->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => \Illuminate\Support\Carbon::parse($start)->addMinutes(40)->format('H:i'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function homeworkPayload(array $overrides = []): array
    {
        return array_merge([
            'period' => $this->class->id.':'.$this->sectionA->id.':'.$this->mathematics->id,
            'academic_session_id' => $this->session->id,
            'work_type' => 'homework',
            'title' => 'Algebra worksheet',
            'homework' => 'Answer every question in exercise 4.',
            'assigned_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'publish' => true,
        ], $overrides);
    }

    private function publishHomework(string $title, User $actor): Homework
    {
        $this->actingAs($actor)
            ->post(route('teacher.homework.store'), $this->homeworkPayload(['title' => $title]))
            ->assertSessionHasNoErrors();

        return Homework::query()->where('title', $title)->sole();
    }

    private function publishClasswork(string $title, User $actor): Homework
    {
        $this->actingAs($actor)
            ->post(route('teacher.homework.store'), $this->homeworkPayload([
                'work_type' => 'classwork',
                'title' => $title,
                'homework' => null,
                'classwork' => 'We cut the circles and compared the halves.',
                'due_date' => null,
            ]))
            ->assertSessionHasNoErrors();

        return Homework::query()->where('title', $title)->sole();
    }

    private function publishedHomework(string $title = 'Algebra worksheet'): Homework
    {
        return $this->publishHomework($title, $this->teacherUser);
    }
}
