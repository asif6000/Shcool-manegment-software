<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the self-service portal accounts admission applicants use before the
 * school has any student record for them. It reuses the existing users table,
 * password hashing and student role — there is no second account system.
 */
class ApplicantAccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        $school = School::query()->where('is_active', true)->first() ?? School::query()->first();

        if ($school === null) {
            throw ValidationException::withMessages([
                'email' => __('The school is not configured yet. Please contact the school office.'),
            ]);
        }

        try {
            $user = DB::transaction(function () use ($data, $school): User {
                $user = new User;
                $user->fill(Arr::only($data, ['name', 'email', 'phone', 'password']));
                $user->forceFill([
                    'school_id' => $school->id,
                    'is_active' => true,
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]);
                $user->save();

                $user->syncRoles(['student']);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('An account with that email already exists. Sign in instead.'),
            ]);
        }

        $this->audit->log(
            event: 'user.registered',
            auditable: $user,
            description: __('Applicant account created for :email', ['email' => $user->email]),
            newValues: $user->only(['name', 'email', 'phone']),
            actor: $user,
            schoolId: (int) $school->id,
        );

        return $user;
    }
}
