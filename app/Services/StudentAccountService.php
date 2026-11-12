<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Opens and maintains the portal login of a student profile.
 *
 * A student signs in with a mobile number and a password. No student account
 * carries an email address, an email verification step or an email OTP, and no
 * other role is touched by this service.
 */
class StudentAccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Give a student profile its mobile-number login, adopting an already
     * registered applicant account when the number matches one.
     *
     * @param  array{mobile_number?: string|null, password?: string|null}  $data
     * @return array{user: User, password: string}
     */
    public function create(User $actor, Student $student, array $data): array
    {
        $mobile = AuthenticationService::normaliseMobile(
            trim((string) ($data['mobile_number'] ?? $data['phone'] ?? ''))
        );

        $supplied = filled($data['password'] ?? null) ? (string) $data['password'] : null;

        try {
            return DB::transaction(function () use ($actor, $student, $mobile, $supplied): array {
                /** @var Student $locked */
                $locked = Student::query()->lockForUpdate()->findOrFail($student->getKey());

                if ($locked->user_id !== null) {
                    throw ValidationException::withMessages([
                        'mobile_number' => __('This student already has a portal account.'),
                    ]);
                }

                $plain = $supplied ?? Str::password(12, true, true, false, false);
                $user = $this->claimOrOpen($locked, $mobile, $plain, $supplied === null);

                $this->audit->log(
                    event: 'student.portal_created',
                    auditable: $locked,
                    description: __('Portal access was created for :name', ['name' => $locked->full_name]),
                    newValues: ['user_id' => $user->id, 'mobile_number' => $user->mobile_number],
                    actor: $actor,
                    schoolId: (int) $locked->school_id,
                );

                return ['user' => $user, 'password' => $plain];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'mobile_number' => __('That mobile number already signs in to another account.'),
            ]);
        }
    }

    /**
     * Keep an existing login in step with the profile: the name and the sign-in
     * number follow the form, and a typed password replaces the stored hash.
     * A blank password leaves the current one alone.
     */
    public function syncFromProfile(User $user, Student $student, string $mobile, ?string $password, User $actor): User
    {
        return DB::transaction(function () use ($user, $student, $mobile, $password, $actor): User {
            $before = $user->only(['name', 'mobile_number', 'phone']);

            $user->name = $student->full_name;

            if ($mobile !== '') {
                $taken = User::query()
                    ->withTrashed()
                    ->where('id', '!=', $user->id)
                    ->where(fn ($q) => $q->where('mobile_number', $mobile)->orWhere('phone', $mobile))
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages([
                        'phone' => __('That mobile number already signs in to another account.'),
                    ]);
                }

                $user->mobile_number = $mobile;
                $user->phone = $mobile;
            }

            if (filled($password)) {
                $user->password = (string) $password;
                $user->must_change_password = false;
            }

            $user->is_active = true;
            $user->save();

            $this->audit->log(
                event: 'student.portal_synced',
                auditable: $student,
                description: __('Portal sign-in for :name was updated', ['name' => $student->full_name]),
                oldValues: $before,
                newValues: $user->only(['name', 'mobile_number', 'phone']),
                actor: $actor,
                schoolId: (int) $student->school_id,
            );

            return $user;
        });
    }

    /**
     * @return array{user: User, password: string}
     */
    public function resetPassword(User $actor, Student $student): array
    {
        return DB::transaction(function () use ($actor, $student): array {
            /** @var Student $locked */
            $locked = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $user = $locked->user;

            if ($user === null) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('This student does not have a portal account yet.'),
                ]);
            }

            $plain = Str::password(12, true, true, false, false);

            $user->password = $plain;
            $user->must_change_password = true;
            $user->save();
            $user->tokens()->delete();

            $this->audit->log(
                event: 'student.portal_password_reset',
                auditable: $locked,
                description: __('The portal password for :name was reset', ['name' => $locked->full_name]),
                newValues: ['user_id' => $user->id],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return ['user' => $user, 'password' => $plain];
        });
    }

    public function setActive(User $actor, Student $student, bool $active): User
    {
        return DB::transaction(function () use ($actor, $student, $active): User {
            /** @var Student $locked */
            $locked = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $user = $locked->user;

            if ($user === null) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('This student does not have a portal account yet.'),
                ]);
            }

            $user->forceFill(['is_active' => $active])->save();

            if (! $active) {
                $user->tokens()->delete();
            }

            $this->audit->log(
                event: 'student.portal_status_changed',
                auditable: $locked,
                description: $active
                    ? __('Portal access for :name was enabled', ['name' => $locked->full_name])
                    : __('Portal access for :name was disabled', ['name' => $locked->full_name]),
                newValues: ['user_id' => $user->id, 'is_active' => $active],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $user->refresh();
        });
    }

    /**
     * Find the account that already owns this number, or open a new one.
     *
     * An existing number is only ever adopted when it is a student-role login with
     * no profile attached to it yet, which is the applicant-was-admitted case. It
     * is never re-pointed at a staff account, so an unrelated number typed into
     * this form cannot rename or re-password somebody else's login.
     */
    private function claimOrOpen(Student $student, string $mobile, string $password, bool $generated): User
    {
        if (preg_match(AuthenticationService::MOBILE_PATTERN, $mobile) !== 1) {
            throw ValidationException::withMessages([
                'mobile_number' => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
            ]);
        }

        $existing = User::query()
            ->withTrashed()
            ->where(fn ($q) => $q->where('mobile_number', $mobile)->orWhere('phone', $mobile))
            ->first();

        if ($existing !== null) {
            if ($existing->deleted_at !== null) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('That mobile number is reserved by an archived account. Contact the administrator.'),
                ]);
            }

            if ($existing->school_id !== $student->school_id
                || $existing->hasStudentRecord()
                || ! $existing->hasRole('student')
                || $existing->teacher()->exists()
                || $existing->employee()->exists()) {
                throw ValidationException::withMessages([
                    'mobile_number' => __('That mobile number already signs in to another account.'),
                ]);
            }

            $existing->forceFill([
                'name' => $student->full_name,
                'mobile_number' => $mobile,
                'phone' => $mobile,
                'email' => null,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => $generated,
                'email_verified_at' => now(),
            ])->save();

            $student->forceFill(['user_id' => $existing->id])->save();

            return $existing;
        }

        $user = new User;
        $user->forceFill([
            'school_id' => $student->school_id,
            'name' => $student->full_name,
            'email' => null,
            'phone' => $mobile,
            'mobile_number' => $mobile,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => $generated,
            'email_verified_at' => now(),
        ])->save();

        $user->syncRoles(['student']);

        $student->forceFill(['user_id' => $user->id])->save();

        return $user;
    }
}
