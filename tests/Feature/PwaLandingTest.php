<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class PwaLandingTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    public function test_manifest_is_installable_and_available_at_both_urls(): void
    {
        foreach (['/manifest.webmanifest', '/manifest.json'] as $url) {
            $response = $this->get($url);

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');

            $manifest = $response->json();

            $this->assertSame('standalone', $manifest['display']);
            $this->assertSame(config('pwa.app_name'), $manifest['name']);
            $this->assertContains('any', array_column($manifest['icons'], 'purpose'));
            $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        }
    }

    public function test_version_endpoint_and_service_worker_match_the_release(): void
    {
        $this->getJson('/pwa/version')
            ->assertOk()
            ->assertJsonPath('version', config('pwa.version'));

        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString(
            "const SW_VERSION = '".config('pwa.version')."'",
            $serviceWorker,
        );
    }

    public function test_landing_page_shows_install_triggers_and_platform_instructions(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-pwa-install-trigger', false)
            ->assertSee('data-pwa-install-open', false)
            ->assertSee('data-pwa-app', false)
            ->assertSee('icons/drms-192.png', false)
            ->assertSee('data-pwa-sheet-variant="android"', false)
            ->assertSee('data-pwa-sheet-variant="ios"', false)
            ->assertSee('data-pwa-sheet-variant="desktop"', false)
            ->assertSee('data-pwa-sheet-variant="unsupported"', false)
            ->assertSee('id="teachers"', false)
            ->assertSee('id="classes"', false);
    }

    public function test_landing_page_shows_active_teachers_and_classes(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $teacher = Teacher::factory()->create([
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'designation' => 'সহকারী শিক্ষক',
            'status' => 'active',
        ]);

        $class = SchoolClass::factory()->create([
            'name' => 'Class Six',
            'level' => 6,
            'is_active' => true,
        ]);

        $section = Section::factory()->create([
            'class_id' => $class->id,
            'name' => 'ক',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee($teacher->full_name)
            ->assertSee($teacher->designation)
            ->assertSee($class->name)
            ->assertSee($section->name);
    }
}
