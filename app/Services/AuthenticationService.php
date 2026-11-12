<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Failures allowed from one address across all accounts. The per-account
     * limit alone lets one address spray a single guess at unlimited accounts.
     */
    public const MAX_IP_ATTEMPTS = 30;

    public const DECAY_SECONDS = 60;

    /** A Bangladeshi mobile number, with or without the country code. */
    public const MOBILE_PATTERN = '/^(?:\+?88)?01[3-9]\d{8}$/';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function ensureNotRateLimited(string $email, ?string $ip): void
    {
        $this->ensureAddressIsNotSpraying($ip, 'email');

        $key = $this->throttleKey($email, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
    }

    /**
     * Verify credentials without revealing whether the email exists, the
     * password was wrong, or the account is deactivated.
     */
    public function verifyCredentials(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->is_active) {
            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }

    public function isMobileIdentifier(string $identifier): bool
    {
        return preg_match(self::MOBILE_PATTERN, self::normaliseMobile($identifier)) === 1;
    }

    /**
     * Strip separators and the optional +88/88 country code so the value can
     * be matched against users.mobile_number / users.phone as stored.
     *
     * Static because the same reduction is applied when an account is written,
     * not only when it is read: a number stored with dashes could otherwise
     * never be matched by the sign-in lookup.
     */
    public static function normaliseMobile(string $identifier): string
    {
        $identifier = preg_replace('/[\s\-()]/', '', $identifier);

        if (str_starts_with($identifier, '+88')) {
            return substr($identifier, 3);
        }

        if (str_starts_with($identifier, '88')) {
            return substr($identifier, 2);
        }

        return $identifier;
    }

    /**
     * Sign-in identifier detection: a valid email is authenticated through
     * the existing email flow, everything else that looks like a mobile
     * number goes through the mobile flow. Both share the same hash check.
     */
    public function verifyCredentialsByIdentifier(string $identifier, string $password): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return $this->verifyCredentials($identifier, $password);
        }

        return $this->verifyCredentialsByMobile(self::normaliseMobile($identifier), $password);
    }

    /**
     * Verify student credentials using mobile_number (stored in users.mobile_number).
     * Used exclusively for the student mobile login flow.
     */
    public function verifyCredentialsByMobile(string $mobile, string $password): ?User
    {
        $variants = array_values(array_unique([$mobile, '88'.$mobile, '+88'.$mobile]));

        // Accounts written before normalisation may still hold the +88 form, so
        // both spellings have to match the same person. A student's sign-in
        // number lives in mobile_number, so that column always wins; phone is
        // only a fallback for staff accounts that never had one stored.
        $user = User::query()->whereIn('mobile_number', $variants)->first()
            ?? User::query()->whereIn('phone', $variants)->first();

        if ($user === null || ! $user->is_active) {
            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }

    public function ensureNotRateLimitedByMobile(string $mobile, ?string $ip): void
    {
        $this->ensureAddressIsNotSpraying($ip, 'mobile_number');

        $key = $this->throttleKeyMobile($mobile, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'mobile_number' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
    }

    public function recordFailureByMobile(string $mobile, ?string $ip): void
    {
        RateLimiter::hit($this->throttleKeyMobile($mobile, $ip), self::DECAY_SECONDS);
        RateLimiter::hit($this->throttleKeyIp($ip), self::DECAY_SECONDS);
    }

    public function clearFailuresByMobile(string $mobile, ?string $ip): void
    {
        RateLimiter::clear($this->throttleKeyMobile($mobile, $ip));
    }

    public function recordFailure(string $email, ?string $ip): void
    {
        RateLimiter::hit($this->throttleKey($email, $ip), self::DECAY_SECONDS);
        RateLimiter::hit($this->throttleKeyIp($ip), self::DECAY_SECONDS);

        $user = User::query()->where('email', $email)->first();

        $this->auditLogger->log(
            event: 'login_failed',
            auditable: $user,
            description: __('Failed sign-in attempt for :email', ['email' => $email]),
            newValues: ['email' => $email],
            actor: $user,
        );
    }

    public function clearFailures(string $email, ?string $ip): void
    {
        RateLimiter::clear($this->throttleKey($email, $ip));
    }

    public function markLoggedIn(User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->log(
            event: 'login',
            auditable: $user,
            description: __(':name signed in', ['name' => $user->name]),
            actor: $user,
        );
    }

    public function markLoggedOut(User $user): void
    {
        $this->auditLogger->log(
            event: 'logout',
            auditable: $user,
            description: __(':name signed out', ['name' => $user->name]),
            actor: $user,
        );
    }

    private function throttleKey(string $email, ?string $ip): string
    {
        return 'login:'.Str::lower($email).'|'.$ip;
    }

    private function throttleKeyMobile(string $mobile, ?string $ip): string
    {
        return 'login_mobile:'.Str::lower($mobile).'|'.$ip;
    }

    private function throttleKeyIp(?string $ip): string
    {
        return 'login_ip:'.$ip;
    }

    private function ensureAddressIsNotSpraying(?string $ip, string $field): void
    {
        $key = $this->throttleKeyIp($ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_IP_ATTEMPTS)) {
            throw ValidationException::withMessages([
                $field => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
    }
}
