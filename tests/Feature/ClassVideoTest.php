<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\ClassVideo;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class ClassVideoTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private AcademicSession $session;

    private SchoolClass $class;

    private SchoolClass $otherClass;

    private Section $section;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_an_admin_posts_a_youtube_lesson_and_the_home_page_offers_it(): void
    {
        [$admin] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('videos.store'), $this->payload([
                'title' => 'Fractions revision',
                'youtube_url' => 'https://www.youtube.com/watch?v=AbCdEfGhIjK',
                'show_on_website' => '1',
            ]))
            ->assertSessionHasNoErrors();

        /** @var ClassVideo $video */
        $video = ClassVideo::query()->sole();

        $this->assertSame('AbCdEfGhIjK', $video->youtube_id);
        $this->assertSame('youtube', $video->source);
        $this->assertTrue($video->show_on_website);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'class_video.created',
            'auditable_type' => ClassVideo::class,
        ]);

        $this->signOutFromWorkspace();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Fractions revision', $html);
        $this->assertStringContainsString('https://www.youtube.com/embed/AbCdEfGhIjK', $html);
        $this->assertStringContainsString('assets/js/class-videos.js', $html);
    }

    public function test_a_video_stays_off_the_home_page_until_it_is_switched_on(): void
    {
        [$admin] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('videos.store'), $this->payload([
                'title' => 'Private mock test walkthrough',
                'youtube_url' => 'https://youtu.be/XyZ12345678',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(ClassVideo::query()->sole()->show_on_website);

        $this->signOutFromWorkspace();
        $this->get('/')->assertOk()->assertDontSee('Private mock test walkthrough');
    }

    public function test_the_website_switch_adds_and_removes_a_video_from_the_home_page(): void
    {
        [$admin] = $this->fixtures();

        $video = $this->makeVideo($admin, [
            'title' => 'Arabic alphabet song',
            'youtube_url' => 'https://www.youtube.com/watch?v=QwErTyUiOp1',
        ]);

        $this->signOutFromWorkspace();
        $this->get('/')->assertOk()->assertDontSee('Arabic alphabet song');

        $this->actingAs($admin)->post(route('videos.website', $video))->assertRedirect();

        $this->assertTrue($video->refresh()->show_on_website);
        $this->signOutFromWorkspace();
        $this->get('/')->assertOk()->assertSee('Arabic alphabet song');
        $this->assertDatabaseHas('audit_logs', ['event' => 'class_video.website_toggled', 'user_id' => $admin->id]);

        $this->actingAs($admin)->post(route('videos.website', $video));

        $this->assertFalse($video->refresh()->show_on_website);
        $this->signOutFromWorkspace();
        $this->get('/')->assertOk()->assertDontSee('Arabic alphabet song');
    }

    public function test_only_the_parsed_video_id_is_kept_from_a_link_full_of_extra_parameters(): void
    {
        [$admin] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('videos.store'), $this->payload([
                'title' => 'Science fair tour',
                'youtube_url' => 'https://www.youtube.com/watch?v=AbCdEfGhIjK&list=PLsecret&autoplay=1&evil=%3Cscript%3E',
                'show_on_website' => '1',
            ]))
            ->assertSessionHasNoErrors();

        /** @var ClassVideo $video */
        $video = ClassVideo::query()->sole();

        $this->assertSame('AbCdEfGhIjK', $video->youtube_id);
        // The pasted string is replaced by our own canonical link, so no extra
        // parameter and no tag from the request can reach the website.
        $this->assertSame('https://www.youtube.com/watch?v=AbCdEfGhIjK', $video->youtube_url);

        $this->signOutFromWorkspace();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('https://www.youtube.com/embed/AbCdEfGhIjK?rel=0', $html);
        $this->assertStringNotContainsString('PLsecret', $html);
    }

    public function test_a_link_that_is_not_a_youtube_video_is_refused(): void
    {
        [$admin] = $this->fixtures();

        foreach ([
            'https://vimeo.com/12345',
            'https://www.youtube.com/playlist?list=PLaaaaaaaa',
            'not a link at all',
            'https://youtube.com.evil.test/watch?v=AbCdEfGhIjK',
        ] as $url) {
            $this->actingAs($admin)
                ->post(route('videos.store'), $this->payload(['youtube_url' => $url]))
                ->assertSessionHasErrors('youtube_url');
        }

        $this->assertSame(0, ClassVideo::query()->count());
    }

    public function test_an_uploaded_clip_is_stored_on_the_school_disk_and_played_from_the_home_page(): void
    {
        Storage::fake('school_uploads');
        [$admin] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('videos.store'), $this->payload([
                'title' => 'PTM address',
                'source' => 'upload',
                'youtube_url' => null,
                'file' => UploadedFile::fake()->create('address.mp4', 1200, 'video/mp4'),
                'show_on_website' => '1',
            ]))
            ->assertSessionHasNoErrors();

        /** @var ClassVideo $video */
        $video = ClassVideo::query()->sole();

        $this->assertSame('upload', $video->source);
        $this->assertNull($video->youtube_id);
        $this->assertSame('address.mp4', $video->file_name);
        $this->assertSame(1200 * 1024, $video->file_size);
        $this->assertStringStartsWith('videos/'.$this->school->id.'/', $video->file_path);
        Storage::disk('school_uploads')->assertExists($video->file_path);

        $this->signOutFromWorkspace();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('PTM address', $html);
        $this->assertStringContainsString($video->file_path, $html);
    }

    public function test_a_file_that_is_not_one_of_the_allowed_video_types_is_refused(): void
    {
        Storage::fake('school_uploads');
        [$admin] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('videos.store'), $this->payload([
                'title' => 'Renamed script',
                'source' => 'upload',
                'file' => UploadedFile::fake()->create('lesson.php', 12, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ClassVideo::query()->count());
    }

    public function test_an_upload_needs_a_file_when_nothing_is_stored_yet(): void
    {
        [$admin] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('videos.store'), $this->payload([
                'title' => 'Missing clip',
                'source' => 'upload',
            ]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ClassVideo::query()->count());
    }

    public function test_editing_an_uploaded_clip_keeps_the_file_and_replacing_it_clears_the_old_one(): void
    {
        Storage::fake('school_uploads');
        [$admin] = $this->fixtures();

        $video = $this->makeVideo($admin, [
            'title' => 'Annual day part one',
            'source' => 'upload',
            'file' => UploadedFile::fake()->create('part-one.mp4', 300, 'video/mp4'),
        ]);

        $firstPath = $video->file_path;

        // Only the title changes: the stored clip stays where it is.
        $this->actingAs($admin)
            ->put(route('videos.update', $video), $this->payload([
                'title' => 'Annual day — opening',
                'source' => 'upload',
            ]))
            ->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertSame('Annual day — opening', $video->title);
        $this->assertSame($firstPath, $video->file_path);
        Storage::disk('school_uploads')->assertExists($firstPath);

        $this->actingAs($admin)
            ->put(route('videos.update', $video), $this->payload([
                'title' => 'Annual day — opening',
                'source' => 'upload',
                'file' => UploadedFile::fake()->create('part-two.mp4', 400, 'video/mp4'),
            ]))
            ->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertNotSame($firstPath, $video->file_path);
        Storage::disk('school_uploads')->assertExists($video->file_path);
        Storage::disk('school_uploads')->assertMissing($firstPath);
        $this->assertDatabaseHas('audit_logs', ['event' => 'class_video.updated', 'user_id' => $admin->id]);
    }

    public function test_a_teacher_only_reaches_the_classes_on_their_own_timetable(): void
    {
        [, $teacher] = $this->fixtures(withRoutine: true);

        // Class videos are an office screen by default; a teacher who is given
        // the permission is still held to the classes on their own timetable.
        $teacher->givePermissionTo(['videos.view', 'videos.manage']);

        $this->actingAs($teacher)
            ->post(route('videos.store'), $this->payload([
                'title' => 'My class clip',
                'youtube_url' => 'https://youtu.be/AbCdEfGhIjK',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('videos.index', ['class' => $this->class->id]));

        $this->actingAs($teacher)
            ->post(route('videos.store'), $this->payload([
                'title' => 'Somebody else’s class',
                'class_id' => $this->otherClass->id,
                'section_id' => null,
                'youtube_url' => 'https://youtu.be/AbCdEfGhIjK',
            ]))
            ->assertForbidden();

        $this->assertSame(1, ClassVideo::query()->count());

        $foreign = $this->makeVideo($this->makeUser($this->school, 'school_admin'), [
            'title' => 'Office clip for Class Seven',
            'class_id' => $this->otherClass->id,
            'section_id' => null,
        ]);

        $this->actingAs($teacher)->get(route('videos.edit', $foreign))->assertForbidden();
        $this->actingAs($teacher)->post(route('videos.website', $foreign))->assertForbidden();

        // The list never shows a video from a class the teacher does not hold.
        $this->actingAs($teacher)
            ->get(route('videos.index'))
            ->assertOk()
            ->assertSee('My class clip')
            ->assertDontSee('Office clip for Class Seven');
    }

    public function test_staff_without_the_materials_permission_cannot_open_the_video_screen(): void
    {
        [$admin] = $this->fixtures();
        $accountant = $this->makeUser($this->school, 'accountant');

        $this->actingAs($admin)->get(route('videos.index'))->assertOk();
        $this->actingAs($accountant)->get(route('videos.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('videos.create'))->assertForbidden();
    }

    public function test_withdrawing_a_video_closes_the_home_page_player(): void
    {
        [$admin] = $this->fixtures();

        $video = $this->makeVideo($admin, [
            'title' => 'Sports day highlights',
            'youtube_url' => 'https://youtu.be/MnOpQrStUvW',
            'show_on_website' => '1',
        ]);

        $this->signOutFromWorkspace();
        $this->get('/')->assertOk()->assertSee('Sports day highlights');

        $this->actingAs($admin)->delete(route('videos.destroy', $video))->assertRedirect(route('videos.index'));

        $this->assertDatabaseMissing('class_videos', ['id' => $video->id]);
        $this->signOutFromWorkspace();
        $this->get('/')->assertOk()->assertDontSee('Sports day highlights');
        $this->assertDatabaseHas('audit_logs', ['event' => 'class_video.permanently_deleted', 'user_id' => $admin->id]);
    }

    /**
     * The home page is a guest page: an acting user is sent to their own
     * dashboard instead, so every website check starts by signing out.
     */
    private function signOutFromWorkspace(): void
    {
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function fixtures(bool $withRoutine = false): array
    {
        $this->school = $this->makeSchool();
        $admin = $this->makeUser($this->school, 'school_admin');
        $teacher = $this->makeUser($this->school, 'teacher');

        $this->useSchoolContext($this->school->id);

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

        $this->otherClass = SchoolClass::query()->create([
            'name' => 'Class Seven',
            'level' => 7,
            'is_active' => true,
        ]);

        $this->section = Section::query()->create([
            'class_id' => $this->class->id,
            'name' => 'A',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->subject = Subject::query()->firstOrCreate(
            ['name' => 'Mathematics'],
            ['type' => 'theory', 'is_active' => true],
        );

        if ($withRoutine) {
            ClassRoutine::query()->create([
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => Teacher::factory()->create(['user_id' => $teacher->id])->id,
                'day_of_week' => 2,
                'start_time' => '09:00',
                'end_time' => '09:45',
            ]);
        }

        return [$admin, $teacher];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'title' => 'Fractions revision',
            'description' => 'Watch the first eight minutes.',
            'source' => 'youtube',
            'youtube_url' => 'https://youtu.be/AbCdEfGhIjK',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVideo(User $actor, array $overrides = []): ClassVideo
    {
        $payload = $this->payload($overrides);
        $file = $payload['file'] ?? null;
        unset($payload['file']);

        $this->actingAs($actor)
            ->post(route('videos.store'), $file === null ? $payload : $payload + ['file' => $file])
            ->assertSessionHasNoErrors();

        return ClassVideo::query()->where('title', $payload['title'])->sole();
    }
}
