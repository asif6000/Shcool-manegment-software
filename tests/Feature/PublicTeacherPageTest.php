<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class PublicTeacherPageTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    public function test_the_public_teacher_card_shows_the_uploaded_photo(): void
    {
        Storage::fake('school_uploads');

        $school = $this->makeSchool(['is_active' => true]);
        $this->useSchoolContext($school->id);

        $path = 'teachers/'.$school->id.'/asif-shekh.png';
        Storage::disk('school_uploads')->put($path, 'photo-bytes');

        $teacher = Teacher::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Asif',
            'last_name' => 'Shekh',
            'designation' => 'Teacher',
            'status' => 'active',
            'photo_path' => $path,
        ]);

        $this->assertNotNull($teacher->publicPhotoUrl());
        $this->assertStringContainsString($path, (string) $teacher->publicPhotoUrl());

        $this->get(route('public.teachers.index'))
            ->assertOk()
            ->assertSee('alt="Asif Shekh"', false)
            ->assertSee($path, false)
            ->assertSee('<img ', false);
    }

    public function test_a_teacher_without_a_photo_keeps_the_initial_letter(): void
    {
        $school = $this->makeSchool(['is_active' => true]);
        $this->useSchoolContext($school->id);

        Teacher::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Rifat',
            'last_name' => 'Chowdhury',
            'status' => 'active',
            'photo_path' => null,
        ]);

        $response = $this->get(route('public.teachers.index'))->assertOk();

        $response->assertDontSee('loading="lazy"', false);
        $this->assertMatchesRegularExpression('/pub-teacher-avatar">\s*R/', (string) $response->getContent());
    }
}
