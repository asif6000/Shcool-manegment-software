<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\FileUploadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class HomeBannerTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('school_uploads');
    }

    public function test_school_managers_get_a_banner_screen_from_the_sidebar(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('banner.edit'), false)
            ->assertSee(__('Homepage banner'));

        $this->actingAs($admin)
            ->get(route('banner.edit'))
            ->assertOk()
            ->assertSee(__('Default banner'))
            ->assertSee(route('banner.update'), false);
    }

    public function test_an_uploaded_banner_replaces_the_hero_on_the_public_home_page(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('banner.update'), [
                'banner' => $this->imageUpload('campus.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('banner.edit'));

        $path = Setting::query()->where('key', 'hero_image')->value('value');

        $this->assertIsString($path);
        $this->assertStringStartsWith('site/'.$school->id.'/', $path);
        Storage::disk('school_uploads')->assertExists($path);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'school.banner_updated',
            'school_id' => $school->id,
        ]);

        $url = app(FileUploadService::class)->url($path, 'hero_banner');

        $this->actingAs($admin)
            ->get(route('banner.edit'))
            ->assertOk()
            ->assertSee(__('Custom banner'))
            ->assertSee($url, false);

        $this->post(route('logout'))->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee($url, false)
            ->assertDontSee('assets/img/landing/hero-campus.jpg');
    }

    public function test_replacing_the_banner_removes_the_previous_file(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('banner.update'), ['banner' => $this->imageUpload('first.jpg')])
            ->assertSessionHasNoErrors();

        $first = Setting::query()->where('key', 'hero_image')->value('value');

        $this->actingAs($admin)
            ->post(route('banner.update'), ['banner' => $this->imageUpload('second.png')])
            ->assertSessionHasNoErrors();

        $second = Setting::query()->where('key', 'hero_image')->value('value');

        $this->assertNotSame($first, $second);
        Storage::disk('school_uploads')->assertMissing($first);
        Storage::disk('school_uploads')->assertExists($second);
    }

    public function test_removing_the_banner_restores_the_default_hero(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('banner.update'), ['banner' => $this->imageUpload('campus.jpg')])
            ->assertSessionHasNoErrors();

        $path = Setting::query()->where('key', 'hero_image')->value('value');

        $this->actingAs($admin)
            ->delete(route('banner.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('banner.edit'));

        $this->assertFalse(Setting::query()->where('key', 'hero_image')->exists());
        Storage::disk('school_uploads')->assertMissing($path);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'school.banner_removed',
            'school_id' => $school->id,
        ]);

        $this->post(route('logout'))->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('assets/img/landing/hero-campus.jpg');
    }

    public function test_a_missing_banner_file_falls_back_to_the_default_hero(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        Setting::query()->create([
            'group_name' => 'site',
            'key' => 'hero_image',
            'value' => 'site/'.$school->id.'/deleted.jpg',
            'type' => 'string',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('assets/img/landing/hero-campus.jpg');
    }

    public function test_only_school_managers_can_change_the_banner(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $teacher = $this->makeUser($school, 'teacher', ['email' => 'banner-teacher@example.test']);

        $this->actingAs($teacher)->get(route('banner.edit'))->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('banner.update'), ['banner' => $this->imageUpload('banner.jpg')])
            ->assertForbidden();

        $this->actingAs($teacher)->delete(route('banner.destroy'))->assertForbidden();

        $this->assertSame(0, Setting::query()->where('key', 'hero_image')->count());

        $this->actingAs($admin)->get(route('banner.edit'))->assertOk();
    }

    public function test_the_banner_must_be_an_image_of_an_allowed_type(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)
            ->post(route('banner.update'), [
                'banner' => UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasErrors('banner');

        $this->actingAs($admin)
            ->post(route('banner.update'), [
                'banner' => $this->imageUpload('clip.gif', 'image/gif'),
            ])
            ->assertSessionHasErrors('banner');

        $this->assertFalse(Setting::query()->where('key', 'hero_image')->exists());
        $this->assertSame(0, count(Storage::disk('school_uploads')->allFiles()));
    }

    /**
     * A real 1×1 image file, because this environment has no GD extension.
     */
    private function imageUpload(string $name, string $mime = 'image/png'): UploadedFile
    {
        $bytes = $mime === 'image/gif'
            ? base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
            : base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $path = tempnam(sys_get_temp_dir(), 'banner').($mime === 'image/gif' ? '.gif' : '.png');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
