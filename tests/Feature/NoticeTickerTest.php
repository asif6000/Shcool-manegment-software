<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class NoticeTickerTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool(['name' => 'Dream Roots Millennium School']);
        $this->useSchoolContext($this->school->id);
        $this->admin = $this->makeUser($this->school, 'school_admin');
    }

    public function test_the_home_page_renders_a_ticker_with_live_notices(): void
    {
        $notice = $this->notice(['title' => 'Annual sports day']);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-ticker', $html);
        $this->assertStringContainsString('জরুরি নোটিশ', $html);
        $this->assertStringContainsString('Annual sports day', $html);
        $this->assertStringContainsString(route('public.notices.show', $notice), $html);
        $this->assertStringContainsString(route('public.notices.index'), $html);
        $this->assertStringContainsString('notice-ticker.js', $html);
    }

    public function test_the_ticker_hides_draft_future_expired_and_internal_notices(): void
    {
        $live = $this->notice(['title' => 'Live admission notice']);

        $this->notice(['title' => 'Draft notice', 'published_at' => null]);
        $this->notice(['title' => 'Scheduled notice', 'published_at' => now()->addWeek()]);
        $this->notice(['title' => 'Old expired notice', 'published_at' => now()->subMonth(), 'expires_at' => now()->subDay()]);
        $this->notice(['title' => 'Staff only notice', 'audience' => 'teachers']);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString($live->title, $html);
        $this->assertStringNotContainsString('Draft notice', $html);
        $this->assertStringNotContainsString('Scheduled notice', $html);
        $this->assertStringNotContainsString('Old expired notice', $html);
        $this->assertStringNotContainsString('Staff only notice', $html);
    }

    public function test_the_ticker_shows_urgent_notices_first(): void
    {
        $this->notice(['title' => 'Regular notice one', 'published_at' => now()->subHours(3)]);
        $this->notice(['title' => 'Regular notice two', 'published_at' => now()->subHour()]);
        $this->notice(['title' => 'Urgent office closure', 'published_at' => now()->subDays(5), 'is_pinned' => true]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $urgent = strpos($html, 'Urgent office closure');
        $latest = strpos($html, 'Regular notice two');

        $this->assertNotFalse($urgent);
        $this->assertNotFalse($latest);
        $this->assertLessThan($latest, $urgent);
        $this->assertStringContainsString('pub-ticker-badge', $html);
    }

    public function test_the_ticker_falls_back_when_nothing_is_published(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('এই মুহূর্তে কোনো নোটিশ প্রকাশিত হয়নি।', $html);
        $this->assertStringNotContainsString('data-ticker-item', $html);
    }

    public function test_the_ticker_exposes_playback_and_accessibility_controls(): void
    {
        $this->notice(['title' => 'Notice one']);
        $this->notice(['title' => 'Notice two']);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-ticker-toggle', $html);
        $this->assertStringContainsString('data-ticker-dots', $html);
        $this->assertStringContainsString('aria-label="চলমান নোটিশ"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function notice(array $overrides = []): Notice
    {
        return Notice::query()->create(array_merge([
            'title' => 'Notice',
            'body' => 'Notice body for the public website.',
            'audience' => 'all',
            'published_at' => now()->subDay(),
            'expires_at' => null,
            'is_pinned' => false,
            'created_by' => $this->admin->id,
        ], $overrides));
    }
}
