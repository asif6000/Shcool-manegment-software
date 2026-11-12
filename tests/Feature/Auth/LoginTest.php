<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_screen_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('Sign in'));
    }

    public function test_login_screen_asks_for_a_role_and_the_matching_identifier(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('Select Role'))
            ->assertSee(__('Email Address'))
            ->assertSee(__('Mobile Number'))
            ->assertSee(__('Principal'));
    }

    public function test_a_student_can_sign_in_with_the_mobile_number(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'student', ['email' => null, 'mobile_number' => '01712345678']);

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => '01712345678',
            'password' => 'password',
        ])
            ->assertRedirect(route('applicant.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_student_mobile_number_with_the_country_code_is_accepted(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'student', ['email' => null, 'mobile_number' => '01712345678']);

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => ' +8801712-345678 ',
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_teacher_signs_in_with_the_mobile_number(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher', ['mobile_number' => '01712345679']);

        $this->post(route('login.store'), [
            'role' => 'teacher',
            'mobile_number' => '01712345679',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_principal_signs_in_with_the_mobile_number(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'principal', ['mobile_number' => '01712345680']);

        $this->post(route('login.store'), [
            'role' => 'principal',
            'mobile_number' => '01712345680',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_cannot_be_claimed_under_another_role(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'teacher', ['mobile_number' => '01712345681']);

        $this->post(route('login.store'), [
            'role' => 'accountant',
            'mobile_number' => '01712345681',
            'password' => 'password',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_a_similar_password_is_rejected_for_a_mobile_sign_in(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'student', ['email' => null, 'mobile_number' => '01712345678']);

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => '01712345678',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_an_unrecognised_identifier_is_rejected_with_a_clear_message(): void
    {
        $this->makeSchool();

        $this->post(route('login.store'), [
            'role' => 'student',
            'mobile_number' => 'not-an-identifier',
            'password' => 'password',
        ])->assertSessionHasErrors([
            'mobile_number' => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
        ]);

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_active_user_can_sign_in(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'school_admin', ['email' => 'admin@example.test']);

        $response = $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'login',
        ]);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'teacher', [
            'mobile_number' => '01712345682',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'teacher',
            'mobile_number' => '01712345682',
            'password' => 'password',
        ])->assertSessionHasErrors('mobile_number');

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'school_admin', ['email' => 'admin@example.test']);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_with_temporary_password_is_redirected_to_password_change(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'teacher', [
            'mobile_number' => '01712345683',
            'must_change_password' => true,
        ]);

        $this->post(route('login.store'), [
            'role' => 'teacher',
            'mobile_number' => '01712345683',
            'password' => 'password',
        ])->assertRedirect(route('profile.password.change'));
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'school_admin', ['email' => 'throttled@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'role' => 'admin',
                'email' => 'throttled@example.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'throttled@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_sign_out(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUser($school, 'teacher');

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_forgot_password_does_not_reveal_registered_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'missing@example.test',
        ]);

        $response->assertSessionHas('status');
        $this->assertStringContainsString(
            'If the email is registered',
            (string) session('status'),
        );
    }
}
