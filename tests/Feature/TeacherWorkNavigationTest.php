<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Homework;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The navigation rule: Homework and Classwork are one button, never two. The
 * button opens one screen that carries both kinds as tabs, and the only other
 * entry for the same records is the office's read-only monitor.
 */
class TeacherWorkNavigationTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $teacherUser;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool();
        $this->useSchoolContext($this->school->id);

        $this->admin = $this->makeUser($this->school, 'school_admin');
        $this->teacherUser = $this->makeUser($this->school, 'teacher');

        $this->session = AcademicSession::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
        ]);

        $class = SchoolClass::query()->create(['name' => 'Class Six', 'level' => 6, 'is_active' => true]);
        $section = Section::query()->create([
            'class_id' => $class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'type' => 'theory',
            'is_active' => true,
        ]);

        $teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);

        ClassRoutine::query()->create([
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '09:40',
        ]);

        Homework::query()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Fractions worksheet',
            'category' => Homework::CATEGORY_HOMEWORK,
            'description' => 'Complete exercise 4.2.',
            'assigned_date' => '2026-10-05',
            'due_date' => '2026-10-08',
            'status' => Homework::PUBLISHED,
            'created_by' => $this->teacherUser->id,
        ]);

        Homework::query()->create([
            'school_id' => $this->school->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Fraction circles in class',
            'category' => Homework::CATEGORY_CLASSWORK,
            'description' => 'Cut and compared the circles.',
            'assigned_date' => '2026-10-05',
            'due_date' => null,
            'status' => Homework::PUBLISHED,
            'created_by' => $this->teacherUser->id,
        ]);
    }

    public function test_the_teacher_nav_offers_one_button_for_both_kinds_of_work(): void
    {
        $html = $this->navHtml($this->actingAs($this->teacherUser)->get(route('dashboard')));
        $buttons = $this->navButtons($html);

        // The desktop bar and the compact mobile row are the same button at two
        // widths, so the combined label appears exactly twice and nowhere else.
        $this->assertCount(2, $this->withLabel($buttons, 'Homework & Classwork'));

        foreach (['Homework', 'Classwork', 'Old classwork'] as $split) {
            $this->assertSame([], $this->withLabel($buttons, $split), "A separate \"{$split}\" button must not come back.");
        }

        $this->assertSame([], $this->navLinksTo($html, 'panel=classwork'));
    }

    public function test_the_button_opens_one_screen_that_carries_both_tabs(): void
    {
        $page = $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index'))
            ->assertOk();

        $tabs = $this->tabLabels($page->getContent());

        $this->assertContains('Homework', $tabs);
        $this->assertContains('Classwork', $tabs);

        $page->assertSee('Fractions worksheet')
            ->assertSee('Fraction circles in class');

        // Each tab narrows the same screen to its own kind.
        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index', ['category' => Homework::CATEGORY_CLASSWORK]))
            ->assertOk()
            ->assertSee('Fraction circles in class')
            ->assertDontSee('Fractions worksheet');

        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.index', ['category' => Homework::CATEGORY_HOMEWORK]))
            ->assertOk()
            ->assertSee('Fractions worksheet')
            ->assertDontSee('Fraction circles in class');

        // One form files either kind, or both at once.
        $this->actingAs($this->teacherUser)
            ->get(route('teacher.homework.create'))
            ->assertOk()
            ->assertSee('Class Work + Homework', false)
            ->assertSee('Homework', false);

        foreach ([Homework::CATEGORY_HOMEWORK, Homework::CATEGORY_CLASSWORK] as $category) {
            $row = Homework::query()->where('category', $category)->sole();
            $title = Homework::CATEGORY_CLASSWORK === $category
                ? 'Fraction circles in class'
                : 'Fractions worksheet';

            $this->actingAs($this->teacherUser)
                ->get(route('teacher.homework.edit', $row))
                ->assertOk()
                ->assertSee($title, false);
        }
    }

    public function test_the_office_sees_one_monitor_button_and_no_writing_screen(): void
    {
        $html = $this->navHtml($this->actingAs($this->admin)->get(route('dashboard')));
        $buttons = $this->navButtons($html);

        $this->assertCount(1, $this->withLabel($buttons, 'Homework & Classwork'));
        $this->assertSame([], $this->withLabel($buttons, 'Homework'));
        $this->assertSame([], $this->navLinksTo($html, 'teacher/homework'));
        $this->assertNotEmpty($this->navLinksTo($html, 'work-monitor'));

        // Reading the record is allowed; writing it is not.
        $this->actingAs($this->admin)->get(route('work-monitor.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('teacher.homework.create'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('teacher.homework.store'), [])->assertForbidden();
    }

    /**
     * Only the navigation regions: the dashboard body legitimately repeats the
     * words homework and classwork in module notes. Blade escapes the ampersand
     * in the label, so the searched-for form is the HTML one.
     */
    private function navHtml(TestResponse $response): string
    {
        $html = $response->getContent();

        preg_match_all('/<nav\b.*?<\/nav>|<aside class="app-sidebar".*?<\/aside>/s', $html, $matches);

        return implode("\n", $matches[0] ?? []);
    }

    /**
     * Every navigation anchor with the text a person actually reads.
     *
     * @return array<int, array{href: string, text: string}>
     */
    private function navButtons(string $html): array
    {
        preg_match_all('/<a\b([^>]*)>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(static function (array $match): array {
            preg_match('/href="([^"]*)"/', $match[1], $href);

            return [
                'href' => html_entity_decode($href[1] ?? '', ENT_QUOTES),
                'text' => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($match[2]), ENT_QUOTES))),
            ];
        }, $matches);
    }

    /**
     * @param  array<int, array{href: string, text: string}>  $buttons
     * @return array<int, array{href: string, text: string}>
     */
    private function withLabel(array $buttons, string $label): array
    {
        return array_values(array_filter(
            $buttons,
            static fn (array $button): bool => $button['text'] === $label
        ));
    }

    /**
     * @return array<int, string>
     */
    private function navLinksTo(string $html, string $needle): array
    {
        preg_match_all('/<a\b[^>]*href="([^"]*)"[^>]*>/', $html, $matches);

        return array_values(array_filter(
            $matches[1] ?? [],
            fn (string $href): bool => str_contains($href, $needle)
        ));
    }

    /**
     * @return array<int, string>
     */
    private function tabLabels(string $html): array
    {
        preg_match_all('/role="tab"[^>]*>\s*(?:<[^>]+>\s*)*([^<]+?)\s*</', $html, $matches);

        return array_map(static fn (string $label): string => trim($label), $matches[1] ?? []);
    }
}
