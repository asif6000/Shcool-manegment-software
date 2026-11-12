<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\RoleHierarchy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FileUploadService $uploads,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['school', 'roles'])
            ->visibleTo($actor);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if (filled($filters['role'] ?? null)) {
            $query->whereHas('roles', fn (Builder $query) => $query->where('name', $filters['role']));
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $requestedSort = (string) ($filters['sort'] ?? '');
        $sort = in_array($requestedSort, ['name', 'email', 'created_at', 'last_login_at'], true)
            ? $requestedSort
            : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        if (! $actor->can('create', User::class)) {
            throw new AuthorizationException(__('You are not allowed to create users.'));
        }

        $role = (string) $data['role'];

        if (! RoleHierarchy::canAssignRole($actor, $role)) {
            throw ValidationException::withMessages([
                'role' => __('You cannot assign the :role role.', ['role' => $role]),
            ]);
        }

        if ($role === 'student') {
            throw ValidationException::withMessages([
                'role' => __('Student portal accounts are created from the student record, not from user management.'),
            ]);
        }

        $schoolId = $this->resolveSchoolIdForCreate($actor);

        $user = DB::transaction(function () use ($actor, $data, $role, $schoolId): User {
            $user = new User;

            // Admin uses email, other roles use mobile_number
            if ($role === 'school_admin' || $role === 'principal' || $role === 'super_admin') {
                $user->fill(Arr::only($data, ['name', 'email', 'phone', 'is_active']));
                $user->email_verified_at = now();
            } else {
                $user->fill(Arr::only($data, ['name', 'mobile_number', 'phone', 'is_active']));
            }

            $user->school_id = $schoolId;
            $user->password = $data['password'];
            $user->must_change_password = true;
            $user->save();

            $user->syncRoles([$role]);

            $this->auditLogger->log(
                event: 'user.created',
                auditable: $user,
                description: __('User :name was created', ['name' => $user->name]),
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile_number' => $user->mobile_number,
                    'phone' => $user->phone,
                    'role' => $role,
                    'school_id' => $schoolId,
                    'is_active' => $user->is_active,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $user;
        });

        $user->notify(new WelcomeNotification($user->school?->name));

        return $user->load(['school', 'roles']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => __('Use your profile page to update your own account.'),
            ]);
        }

        if (! $actor->can('update', $target)) {
            throw new AuthorizationException(__('You are not allowed to update this user.'));
        }

        $newRole = filled($data['role'] ?? null) ? (string) $data['role'] : null;

        if ($newRole !== null && ! RoleHierarchy::canAssignRole($actor, $newRole)) {
            throw ValidationException::withMessages([
                'role' => __('You cannot assign the :role role.', ['role' => $newRole]),
            ]);
        }

        if ($newRole === 'student' && ! $target->hasRole('student')) {
            throw ValidationException::withMessages([
                'role' => __('Student portal accounts are created from the student record, not from user management.'),
            ]);
        }

        DB::transaction(function () use ($actor, $target, $data, $newRole): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());

            $oldRoles = $target->getRoleNames()->all();
            $oldValues = [
                'name' => $target->name,
                'email' => $target->email,
                'mobile_number' => $target->mobile_number,
                'phone' => $target->phone,
                'is_active' => $target->is_active,
                'roles' => $oldRoles,
            ];

            $deactivating = array_key_exists('is_active', $data) && ! (bool) $data['is_active'] && $target->is_active;

            if ($deactivating) {
                $this->guardDeactivation($target);
            }

            $roleChanged = $newRole !== null && ! in_array($newRole, $oldRoles, true);

            if ($roleChanged) {
                $this->guardRoleChange($target);
            }

            $target->fill(Arr::only($data, ['name', 'email', 'mobile_number', 'phone', 'is_active']));

            if (filled($data['password'] ?? null)) {
                $target->password = $data['password'];
                $target->must_change_password = true;
                $this->revokeAccess($target);
            }

            $target->save();

            if ($roleChanged) {
                $target->syncRoles([$newRole]);
            }

            $this->auditLogger->log(
                event: 'user.updated',
                auditable: $target,
                description: __('User :name was updated', ['name' => $target->name]),
                oldValues: $oldValues,
                newValues: [
                    'name' => $target->name,
                    'email' => $target->email,
                    'mobile_number' => $target->mobile_number,
                    'phone' => $target->phone,
                    'is_active' => $target->is_active,
                    'roles' => $target->getRoleNames()->all(),
                    'password_reset' => filled($data['password'] ?? null),
                ],
                actor: $actor,
                schoolId: $target->school_id,
            );
        });

        return $target->refresh()->load(['school', 'roles']);
    }

    public function toggleStatus(User $actor, User $target): User
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot change your own account status.'),
            ]);
        }

        if (! $actor->can('changeStatus', $target)) {
            throw new AuthorizationException(__('You are not allowed to change this user status.'));
        }

        DB::transaction(function () use ($actor, $target): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());
            $oldStatus = $target->is_active;

            if ($oldStatus) {
                $this->guardDeactivation($target);
            }

            $target->is_active = ! $oldStatus;
            $target->save();

            if (! $target->is_active) {
                $this->revokeAccess($target);
            }

            $this->auditLogger->log(
                event: 'user.status_changed',
                auditable: $target,
                description: $target->is_active
                    ? __('User :name was activated', ['name' => $target->name])
                    : __('User :name was deactivated', ['name' => $target->name]),
                oldValues: ['is_active' => $oldStatus],
                newValues: ['is_active' => $target->is_active],
                actor: $actor,
                schoolId: $target->school_id,
            );
        });

        return $target->refresh();
    }

    public function delete(User $actor, User $target): void
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        if (! $actor->can('delete', $target)) {
            throw new AuthorizationException(__('You are not allowed to delete this user.'));
        }

        DB::transaction(function () use ($actor, $target): void {
            /** @var User $target */
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());

            if ($target->is_active) {
                $this->guardDeactivation($target);
            }

            // Permanent: the row, its role and permission assignments, API
            // tokens, live sessions, notifications and profile picture all go.
            // Records this account created keep themselves and only lose the
            // reference to it, so no history is taken with the login.
            $this->deletes->delete($target, [
                'label' => __('login'),
                'description' => __('Login #:id was permanently deleted.', ['id' => $target->getKey()]),
                'actor' => $actor,
            ]);
        });
    }

    public function sendResetLink(User $actor, User $target): void
    {
        if (! $actor->can('sendResetLink', $target)) {
            throw new AuthorizationException(__('You are not allowed to reset this user password.'));
        }

        $status = Password::sendResetLink(['email' => $target->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        $this->auditLogger->log(
            event: 'password_reset_requested',
            auditable: $target,
            description: __('A password reset link was sent to :email', ['email' => $target->email]),
            actor: $actor,
            schoolId: $target->school_id,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $oldAvatar = $user->avatar_path;
        $newAvatar = $avatar !== null
            ? $this->uploads->store($avatar, 'user_avatar', (string) $user->id)
            : null;

        try {
            DB::transaction(function () use ($user, $data, $oldAvatar, $newAvatar): void {
                $oldValues = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_path' => $oldAvatar,
                ];

                $user->fill(Arr::only($data, ['name', 'email', 'phone']));

                if ($newAvatar !== null) {
                    $user->avatar_path = $newAvatar;
                }

                $user->save();

                $this->auditLogger->log(
                    event: 'profile.updated',
                    auditable: $user,
                    description: __(':name updated their profile', ['name' => $user->name]),
                    oldValues: $oldValues,
                    newValues: [
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'avatar_path' => $user->avatar_path,
                    ],
                    actor: $user,
                    schoolId: $user->school_id,
                );
            });
        } catch (\Throwable $exception) {
            if ($newAvatar !== null) {
                $this->uploads->delete($newAvatar, 'user_avatar');
            }

            throw $exception;
        }

        if ($newAvatar !== null) {
            $this->uploads->delete($oldAvatar, 'user_avatar');
        }

        return $user->refresh();
    }

    public function updatePassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->must_change_password = false;
            $user->save();

            $user->tokens()->delete();

            // Other browsers signed in with the old password lose their web
            // session too; the one making this change stays signed in.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->when(session()->isStarted(), fn ($query) => $query->where('id', '!=', session()->getId()))
                    ->delete();
            }

            $this->auditLogger->log(
                event: 'password_changed',
                auditable: $user,
                description: __(':name changed their password', ['name' => $user->name]),
                actor: $user,
                schoolId: $user->school_id,
            );
        });
    }

    private function resolveSchoolIdForCreate(User $actor): int
    {
        if ($actor->school_id === null) {
            throw new AuthorizationException(__('Your account is not linked to a school.'));
        }

        return (int) $actor->school_id;
    }

    private function guardDeactivation(User $target): void
    {
        if ($target->hasRole('school_admin')) {
            $this->guardLastSchoolAdmin($target);
        }
    }

    private function guardRoleChange(User $target): void
    {
        if ($target->hasRole('school_admin')) {
            $this->guardLastSchoolAdmin($target);
        }
    }

    private function guardLastSchoolAdmin(User $target): void
    {
        if ($target->school_id === null) {
            return;
        }

        $count = User::query()
            ->where('school_id', $target->school_id)
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'school_admin'))
            ->count();

        if ($count <= 1) {
            throw ValidationException::withMessages([
                'user' => __('The last active school administrator cannot be removed or deactivated.'),
            ]);
        }
    }

    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
