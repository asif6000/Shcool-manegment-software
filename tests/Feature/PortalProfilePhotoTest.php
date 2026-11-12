<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Self-service profile pictures in the two portals. These tests are the proof
 * that an account can only ever reach its own row, that any shape of image is
 * accepted, and that a change or a removal lands in storage and the database
 * together.
 */
class PortalProfilePhotoTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('school_uploads');
    }

    public function test_a_student_uploads_a_photo_that_is_stored_and_streamed_back(): void
    {
        [$user, $student] = $this->studentPair();

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('student.photo.store'), ['image' => $this->photo('square.png', 300, 300)])
            ->assertOk()
            ->assertJsonPath('success', true);

        $student->refresh();

        $this->assertNotNull($student->photo_path);
        $this->assertStringStartsWith('students/', $student->photo_path);
        Storage::disk('school_uploads')->assertExists($student->photo_path);

        $this->actingAs($user)
            ->get(route('student.photo', ['v' => $student->photoVersion()]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($user)
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee(route('student.photo', ['v' => $student->photoVersion()]), false);
    }

    public function test_any_resolution_and_ratio_is_accepted_because_dimensions_are_not_a_rule(): void
    {
        [$user, $student] = $this->studentPair();

        foreach ([
            'tiny' => [1, 1, false],
            'portrait' => [300, 900, false],
            'landscape' => [1600, 400, false],
            'square' => [512, 512, false],
            'high-resolution' => [1200, 1200, true],
        ] as $label => [$width, $height, $random]) {
            $upload = $this->photo($label.'.png', $width, $height, $random);
            $sent = filesize($upload->getRealPath());

            $this->actingAs($user)
                ->withHeaders(['Accept' => 'application/json'])
                ->post(route('student.photo.store'), ['image' => $upload])
                ->assertOk();

            $student->refresh();

            $this->assertNotNull($student->photo_path, "The {$label} image was rejected.");
            // Nothing re-encodes the upload, so the stored file is the file sent.
            $this->assertSame(
                $this->pngSize($student->photo_path),
                [$width, $height],
                "The {$label} image was resized or cropped on the way in."
            );
            $this->assertSame(
                $sent,
                strlen(Storage::disk('school_uploads')->get($student->photo_path)),
                "The {$label} image was rewritten instead of stored as uploaded."
            );
        }
    }

    public function test_changing_a_photo_replaces_the_file_and_removing_it_clears_both(): void
    {
        [$user, $student] = $this->studentPair();

        $this->actingAs($user)->post(route('student.photo.store'), ['image' => $this->photo('first.png', 200, 200)]);
        $first = $student->refresh()->photo_path;

        $this->actingAs($user)->post(route('student.photo.store'), ['image' => $this->photo('second.png', 240, 180)]);
        $second = $student->refresh()->photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('school_uploads')->assertMissing($first);
        Storage::disk('school_uploads')->assertExists($second);

        $this->actingAs($user)->delete(route('student.photo.destroy'))->assertRedirect();
        $student->refresh();

        $this->assertNull($student->photo_path);
        Storage::disk('school_uploads')->assertMissing($second);

        $this->actingAs($user)->get(route('student.photo'))->assertNotFound();
    }

    public function test_a_non_image_and_an_oversized_file_are_refused_without_touching_the_record(): void
    {
        [$user, $student] = $this->studentPair();

        $this->actingAs($user)
            ->post(route('student.photo.store'), [
                'image' => UploadedFile::fake()->createWithContent('payload.php', "<?php echo 1; ?>"),
            ])
            ->assertSessionHasErrors('image');

        $this->assertNull($student->refresh()->photo_path);
        $this->assertSame([], Storage::disk('school_uploads')->allFiles('students'));

        $this->actingAs($user)
            ->post(route('student.photo.store'), ['image' => UploadedFile::fake()->create('huge.png', 9000)])
            ->assertSessionHasErrors('image');

        $this->assertNull($student->refresh()->photo_path);
    }

    public function test_a_student_can_only_ever_change_their_own_picture(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        [$aliceUser, $alice] = $this->studentPair($school);
        [$bobUser, $bob] = $this->studentPair($school);

        // Bob tries to point the upload at Alice's record. The portal never reads
        // an id from the request, so only Bob's own row moves.
        $this->actingAs($bobUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('student.photo.store'), [
                'image' => $this->photo('mine.png', 200, 200),
                'student_id' => $alice->id,
                'id' => $alice->id,
            ])
            ->assertOk();

        $this->assertNotNull($bob->refresh()->photo_path);
        $this->assertNull($alice->refresh()->photo_path);

        $this->actingAs($bobUser)->delete(route('student.photo.destroy'));
        $this->assertNull($bob->refresh()->photo_path);
        $this->assertNull($alice->refresh()->photo_path);

        // Alice's stream stays a 404 while she has no picture of her own.
        $this->actingAs($aliceUser)->get(route('student.photo'))->assertNotFound();
    }

    public function test_a_staff_account_is_not_allowed_into_the_student_upload(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser($school, 'teacher');
        [, $student] = $this->studentPair($school);

        $this->actingAs($teacher)
            ->post(route('student.photo.store'), ['image' => $this->photo('sneaky.png', 200, 200)])
            ->assertRedirect();

        $this->assertNull($student->refresh()->photo_path);

        $this->actingAs($teacher)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('student.photo.store'), ['image' => $this->photo('sneaky.png', 200, 200)])
            ->assertForbidden();

        $this->assertNull($student->refresh()->photo_path);
    }

    public function test_a_guest_cannot_reach_the_student_upload_or_its_stream(): void
    {
        $this->post(route('student.photo.store'), ['image' => $this->photo('anon.png', 200, 200)])
            ->assertRedirect(route('login'));

        $this->get(route('student.photo'))->assertRedirect(route('login'));
    }

    public function test_a_teacher_uploads_and_removes_a_photo_without_the_staff_permission(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $user = $this->makeUser($school, 'teacher');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $this->assertFalse($user->hasPermissionTo('staff.view'));

        $this->actingAs($user)->get(route('teacher.profile'))->assertOk()->assertSee(__('Profile photo'));

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('teacher.profile.photo.store'), ['image' => $this->photo('wide.png', 1200, 400)])
            ->assertOk()
            ->assertJsonPath('photo.version', $teacher->refresh()->photoVersion());

        $teacher->refresh();
        $this->assertStringStartsWith('teachers/', $teacher->photo_path);
        Storage::disk('school_uploads')->assertExists($teacher->photo_path);

        // The portal's own stream is scoped to the owner, so it works for a
        // teacher; the staff-screen route still demands staff.view.
        $this->actingAs($user)
            ->get(route('teacher.profile.photo', ['v' => $teacher->photoVersion()]))
            ->assertOk();

        $this->actingAs($user)->get(route('staff.teachers.photo', $teacher))->assertForbidden();

        $this->actingAs($user)->delete(route('teacher.profile.photo.destroy'))->assertRedirect();
        $this->assertNull($teacher->refresh()->photo_path);
        $this->assertSame([], Storage::disk('school_uploads')->allFiles('teachers'));
    }

    public function test_an_account_without_a_teacher_record_is_sent_to_its_own_profile(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser($school, 'school_admin');

        $this->actingAs($admin)->get(route('teacher.profile'))->assertRedirect(route('profile.edit'));

        $this->actingAs($admin)
            ->post(route('teacher.profile.photo.store'), ['image' => $this->photo('nope.png', 200, 200)])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame([], Storage::disk('school_uploads')->allFiles('teachers'));
    }

    public function test_a_new_photo_reaches_the_student_header_after_a_refresh(): void
    {
        [$user, $student] = $this->studentPair();

        $this->actingAs($user)->post(route('student.photo.store'), ['image' => $this->photo('header.png', 200, 200)]);
        $version = $student->refresh()->photoVersion();

        $this->assertNotNull($version);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('v='.$version, false);

        // A second upload changes the version, so no browser cache can hold the
        // old picture in place.
        $this->actingAs($user)->post(route('student.photo.store'), ['image' => $this->photo('fresh.png', 200, 260)]);
        $this->assertNotSame($version, $student->refresh()->photoVersion());
    }

    /**
     * @return array{0: User, 1: Student}
     */
    private function studentPair(?School $school = null): array
    {
        $school ??= $this->makeSchool();
        $this->useSchoolContext($school->id);

        $user = $this->makeUser($school, 'student');
        $student = Student::factory()->create();
        $student->forceFill(['user_id' => $user->id, 'school_id' => $school->id])->save();

        return [$user, $student];
    }

    /**
     * A real PNG built without GD. The upload service sniffs the bytes, so a
     * faked mime type would prove nothing, and the width and height written into
     * IHDR let a test check that the file was stored untouched.
     */
    private function photo(string $name, int $width = 200, int $height = 200): UploadedFile
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data))
            .$type.$data.pack('N', crc32($type.$data));

        $scanline = str_repeat("\x00", 1 + $width * 3);

        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('N2', $width, $height).chr(8).chr(2).chr(0).chr(0).chr(0))
            .$chunk('IDAT', gzcompress(str_repeat($scanline, $height)))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pngSize(string $path): array
    {
        $bytes = substr(Storage::disk('school_uploads')->get($path), 16, 8);

        return array_values(unpack('N2', $bytes));
    }
}
