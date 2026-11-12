<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Student accounts are created and signed in with a mobile number and a
 * password — an email address is never required. These tests cover only the
 * student flows; admin, teacher and guardian sign-in is untouched.
 */
class StudentMobileAuthTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_screen_has_no_email_field(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('Mobile number'))
            ->assertDontSee('name="email"', false)
            ->assertSee('name="mobile_number"', false);
    }

    public function test_a_student_can_register_with_only_a_mobile_number_and_password(): void
    {
        $school = $this->makeSchool();

        $this->post(route('register.store'), [
            'name' => 'Habibur Rahman',
            'mobile_number' => ' 017-123 45678 ',
            'password' => 'Applicant2026',
            'password_confirmation' => 'Applicant2026',
        ])
            ->assertRedirect(route('applicant.apply'))
            ->assertSessionHasNoErrors();

        $applicant = User::query()->where('mobile_number', '01712345678')->firstOrFail();

        $this->assertTrue($applicant->hasRole('student'));
        $this->assertNull($applicant->email);
        $this->assertNull($applicant->phone);
        $this->assertTrue(Hash::check('Applicant2026', $applicant->password));
        $this->assertAuthenticatedAs($applicant);
    }

    public function test_a_student_can_sign_in_with_the_mobile_number(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'student', ['mobile_number' => '01798765432']);

        $this->post(route('student.login.store'), [
            'mobile_number' => '01798765432',
            'password' => 'password',
        ])
            ->assertRedirect(route('applicant.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_mobile_password_is_rejected(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'student', ['mobile_number' => '01798765432']);

        $this->post(route('student.login.store'), [
            'mobile_number' => '01798765432',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_non_student_accounts_cannot_use_the_student_sign_in(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'school_admin', ['mobile_number' => '01512345678']);

        $this->post(route('student.login.store'), [
            'mobile_number' => '01512345678',
            'password' => 'password',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_the_student_sign_in_screen_shows_mobile_fields_only(): void
    {
        $this->get(route('student.login'))
            ->assertOk()
            ->assertSee('name="mobile_number"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_a_duplicate_mobile_number_is_refused_at_registration(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'student', ['mobile_number' => '01798765432']);

        $this->post(route('register.store'), [
            'name' => 'Duplicate Try',
            'mobile_number' => '01798765432',
            'password' => 'Applicant2026',
            'password_confirmation' => 'Applicant2026',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_admin_sign_in_with_email_still_works(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'school_admin', ['email' => 'admin@example.test']);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
