<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\SchoolContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    public function test_guests_see_the_single_school_landing_page(): void
    {
        $this->makeSchool([
            'name' => 'Green Valley Model School',
            'short_name' => 'Green Valley',
            'motto' => 'Knowledge and character',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Green Valley Model School')
            ->assertSee('Knowledge and character')
            ->assertSee('লগইন')
            ->assertSee('নোটিস বোর্ড')
            ->assertSee('অনলাইন ভর্তি')
            ->assertSee('ফলাফল')
            ->assertDontSee('Pricing')
            ->assertDontSee('Parent Portal');
    }

    public function test_the_landing_page_only_counts_active_records_from_the_configured_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool(['is_active' => false]);

        app(SchoolContext::class)->run($school->id, function (): void {
            Student::factory()->count(3)->create();
            Student::factory()->inactive()->create();
            Teacher::factory()->count(2)->create();
            SchoolClass::factory()->count(4)->sequence(
                fn ($sequence): array => [
                    'name' => 'Class '.($sequence->index + 1),
                    'level' => $sequence->index + 1,
                ]
            )->create();
            SchoolClass::factory()->create([
                'name' => 'Class 9',
                'level' => 9,
                'is_active' => false,
            ]);
        });

        app(SchoolContext::class)->run($otherSchool->id, function (): void {
            Student::factory()->count(2)->create();
            Teacher::factory()->count(3)->create();
        });

        $this->get('/')
            ->assertOk()
            ->assertViewHas('stats.students', 3)
            ->assertViewHas('stats.teachers', 2)
            ->assertViewHas('stats.classes', 4)
            ->assertViewHas('stats.subjects', 0);
    }

    public function test_the_landing_page_renders_in_bengali_when_the_guest_prefers_it(): void
    {
        $this->makeSchool([
            'name' => 'সবুজ ভ্যালি মডেল স্কুল',
            'short_name' => 'সবুজ ভ্যালি',
            'motto' => 'জ্ঞান ও চরিত্র',
        ]);

        $this->withSession(['locale' => 'bn'])
            ->get('/')
            ->assertOk()
            ->assertSee('সবুজ ভ্যালি মডেল স্কুল')
            ->assertSee('জ্ঞান ও চরিত্র')
            ->assertSee('নোটিস বোর্ড')
            ->assertSee('আমাদের সম্পর্কে')
            ->assertSee('শিক্ষাই জাতির উন্নয়ন');
    }

    public function test_the_landing_page_features_arabic_programming_and_every_active_subject(): void
    {
        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        Subject::query()->create([
            'name' => 'Bangla',
            'code' => 'BAN',
            'type' => 'core',
            'is_active' => true,
        ]);
        // Already featured on the page, so it must not be listed a second time.
        Subject::query()->create([
            'name' => 'Arabic',
            'code' => 'ARB',
            'type' => 'theory',
            'is_active' => true,
        ]);
        Subject::query()->create([
            'name' => 'Physical Education',
            'code' => 'PE',
            'type' => 'practical',
            'is_active' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('আরবি')
            ->assertSee('প্রোগ্রামিং')
            ->assertSee('Bangla')
            ->assertDontSee('Physical Education')
            ->assertViewHas('subjects', fn ($subjects): bool => $subjects->count() === 2);
    }

    public function test_authenticated_users_are_redirected_to_their_own_workspace(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $school = $this->makeSchool();
        $this->useSchoolContext($school->id);

        $admin = $this->makeUser($school, 'school_admin');
        $applicant = $this->makeUser($school, 'student', ['email' => 'applicant@example.test']);

        $enrolled = $this->makeUser($school, 'student', ['email' => 'enrolled@example.test']);
        Student::factory()->create()->forceFill(['user_id' => $enrolled->id])->save();

        $this->actingAs($admin)->get('/')->assertRedirect(route('dashboard'));

        // An application is not a student record: the portal is still closed to
        // this account, so it belongs in the application desk.
        $this->actingAs($applicant)->get('/')->assertRedirect(route('applicant.dashboard'));

        $this->actingAs($enrolled)->get('/')->assertRedirect(route('student.dashboard'));
    }
}
