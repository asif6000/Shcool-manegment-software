<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles student self-registration and authentication using mobile number only.
 * Students do NOT require an email address.
 */
class StudentMobileAccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Register a new student account using only mobile number and password.
     *
     * @param  array<string, mixed>  $data  Must contain: mobile_number, name, password
     */
    public function register(array $data): User
    {
        $school = School::query()->where('is_active', true)->first() ?? School::query()->first();

        if ($school === null) {
            throw ValidationException::withMessages([
                'mobile_number' => __('The school is not configured yet. Please contact the school office.'),
            ]);
        }

        try {
            $user = DB::transaction(function () use ($data, $school): User {
                $user = new User;
                $user->name = trim((string) ($data['name'] ?? 'Student'));
                $user->mobile_number = AuthenticationService::normaliseMobile((string) $data['mobile_number']);
                $user->password = (string) $data['password']; // cast to hashed by User model
                $user->forceFill([
                    'school_id'            => $school->id,
                    'is_active'            => true,
                    'must_change_password' => false,
                    'email'                => null,
                    'email_verified_at'    => now(),
                ]);
                $user->save();

                $user->syncRoles(['student']);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'mobile_number' => __('An account with that mobile number already exists. Sign in instead.'),
            ]);
        }

        $this->audit->log(
            event: 'user.registered',
            auditable: $user,
            description: __('Student account created for mobile :mobile', ['mobile' => $user->mobile_number]),
            newValues: ['name' => $user->name, 'mobile_number' => $user->mobile_number],
            actor: $user,
            schoolId: (int) $school->id,
        );

        return $user;
    }
}
