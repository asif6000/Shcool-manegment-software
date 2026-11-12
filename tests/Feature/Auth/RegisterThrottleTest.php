<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\StudentMobileAccountService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * The register form is the only write endpoint an anonymous visitor reaches,
 * so it is throttled — but the throttle must punish a hammering applicant,
 * not a school office whose families all share one mobile-broadband exit node.
 */
class RegisterThrottleTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function payload(int $n): array
    {
        $mobile = '017'.str_pad((string) $n, 8, '0', STR_PAD_LEFT);

        return [
            'name' => 'Applicant '.$mobile,
            'mobile_number' => $mobile,
            'password' => 'Applicant2026',
            'password_confirmation' => 'Applicant2026',
        ];
    }

    /**
     * Sign up as an anonymous applicant.
     *
     * A successful registration signs the applicant straight in, and the whole
     * auth group sits behind `guest` — which runs before the throttle. Without
     * this, the second and later requests in a loop are bounced by
     * RedirectIfAuthenticated and never reach the limiter at all: a harmless
     * 302 that hides exactly what is under test.
     */
    private function register(array $data, string $ip)
    {
        Auth::guard('web')->logout();
        $this->app['auth']->forgetGuards();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('register.store'), $data);
    }

    public function test_six_applicants_behind_one_connection_can_all_register_in_one_minute(): void
    {
        $this->makeSchool();

        foreach (range(1, 6) as $n) {
            $this->register($this->payload($n), '103.108.10.10')
                ->assertRedirect(route('applicant.apply'))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(6, User::query()->count());
    }

    public function test_the_seventh_applicant_from_one_connection_in_one_minute_is_asked_to_wait(): void
    {
        $this->makeSchool();

        foreach (range(1, 6) as $n) {
            $this->register($this->payload($n), '103.108.10.10')
                ->assertRedirect(route('applicant.apply'));
        }

        $this->register($this->payload(7), '103.108.10.10')->assertStatus(429);

        $this->assertSame(6, User::query()->count());
    }

    /**
     * This is the case that used to fail: the per-applicant cap keyed on an
     * email field the form never sends, so every visitor on the site shared
     * one bucket and the sixth sign-up anywhere was refused.
     */
    public function test_applicants_on_different_connections_do_not_share_one_quota(): void
    {
        $this->makeSchool();

        foreach (range(1, 6) as $n) {
            $this->register($this->payload($n), "103.108.10.{$n}")
                ->assertRedirect(route('applicant.apply'))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(6, User::query()->count());
    }

    public function test_one_applicant_is_still_capped_even_when_they_change_connection(): void
    {
        $this->makeSchool();

        $data = $this->payload(1);

        foreach (range(1, 5) as $n) {
            $this->register($data, "103.108.10.{$n}")->assertStatus(302);
        }

        $this->register($data, '103.108.10.6')->assertStatus(429);
    }

    public function test_a_server_side_failure_does_not_spend_an_applicants_quota(): void
    {
        $this->makeSchool();

        $real = $this->app->make(StudentMobileAccountService::class);

        $broken = Mockery::mock(StudentMobileAccountService::class);
        $broken->shouldReceive('register')->andThrow(new RuntimeException('column not found'));
        $this->app->instance(StudentMobileAccountService::class, $broken);

        foreach (range(1, 6) as $n) {
            $this->register($this->payload($n), '103.108.10.10')->assertStatus(500);
        }

        $this->app->instance(StudentMobileAccountService::class, $real);

        $this->register($this->payload(7), '103.108.10.10')
            ->assertRedirect(route('applicant.apply'))
            ->assertSessionHasNoErrors();
    }

    public function test_the_throttle_page_explains_the_wait_rather_than_blaming_the_applicant(): void
    {
        $this->makeSchool();

        foreach (range(1, 6) as $n) {
            $this->register($this->payload($n), '103.108.10.10')->assertStatus(302);
        }

        $this->register($this->payload(7), '103.108.10.10')
            ->assertStatus(429)
            ->assertSee('one attempt too many', false)
            ->assertSee('Families sharing one connection', false)
            ->assertSee('Return to sign in', false);
    }

    /**
     * Characterisation of the limiter that shipped, on the connection a school
     * office actually shares. Nothing here is new behaviour under test — the
     * point is that these assertions describe a lockout, and the real signup
     * limiter above does not reproduce it.
     */
    public function test_the_shipped_ip_cap_refused_the_fourth_family_on_one_connection(): void
    {
        $this->makeSchool();

        RateLimiter::for('signup', fn (Request $request): array => [
            Limit::perMinute(3)->by('old-ip|'.$request->ip()),
        ]);

        $statuses = [];
        foreach (range(1, 6) as $n) {
            $statuses[] = $this->register($this->payload($n), '103.108.10.10')->getStatusCode();
        }

        $this->assertSame([302, 302, 302, 429, 429, 429], $statuses);
        $this->assertSame(3, User::query()->count());
    }

    /**
     * And the same on six separate connections: the identity cap keyed on an
     * email field this form never posts, so every visitor in the country
     * shared one bucket of five an hour and the sixth was refused.
     */
    public function test_the_shipped_email_keyed_cap_refused_the_sixth_applicant_site_wide(): void
    {
        $this->makeSchool();

        RateLimiter::for('signup', fn (Request $request): array => [
            Limit::perMinute(3)->by('old-ip|'.$request->ip()),
            Limit::perHour(5)->by('old-email|'.Str::lower((string) $request->input('email'))),
        ]);

        $statuses = [];
        foreach (range(1, 6) as $n) {
            $statuses[] = $this->register($this->payload($n), "103.108.10.2{$n}")->getStatusCode();
        }

        $this->assertSame([302, 302, 302, 302, 302, 429], $statuses);
        $this->assertSame(5, User::query()->count());
    }

    public function test_repeated_failed_sign_ins_are_still_blocked_but_never_return_a_429(): void
    {
        $school = $this->makeSchool();
        $this->makeUser($school, 'school_admin', ['email' => 'admin@example.test']);

        foreach (range(1, 8) as $n) {
            $response = $this->post(route('login.store'), [
                'role' => 'admin',
                'email' => 'admin@example.test',
                'password' => 'wrong-password',
            ]);

            $this->assertNotSame(429, $response->getStatusCode(), 'attempt '.$n);
            $response->assertSessionHasErrors('email');
        }

        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('email')
        );

        $this->assertGuest();
    }
}
