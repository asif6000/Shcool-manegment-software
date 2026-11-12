<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class RoleHierarchy
{
    public static function level(?string $role): int
    {
        if ($role === null) {
            return 0;
        }

        return (int) config("school.roles.{$role}.level", 0);
    }

    public static function actorLevel(User $actor): int
    {
        return $actor->getRoleNames()
            ->map(fn (string $role): int => self::level($role))
            ->max() ?? 0;
    }

    public static function targetLevel(User $target): int
    {
        return $target->getRoleNames()
            ->map(fn (string $role): int => self::level($role))
            ->max() ?? 0;
    }

    /**
     * Roles whose sign-in identifier is the email address. Every other role
     * signs in with the mobile number, so an account created without one can
     * never reach its portal.
     */
    public static function usesMobileLogin(string $role): bool
    {
        return ! in_array($role, ['school_admin', 'super_admin'], true);
    }

    public static function canAssignRole(User $actor, string $role): bool
    {
        if (! array_key_exists($role, (array) config('school.roles'))) {
            return false;
        }

        return self::level($role) < self::actorLevel($actor);
    }

    /**
     * @return Collection<int, array{name: string, label: string, level: int}>
     */
    public static function assignableRoles(User $actor): Collection
    {
        $actorLevel = self::actorLevel($actor);

        return collect((array) config('school.roles'))
            ->filter(fn (array $meta): bool => $meta['level'] < $actorLevel)
            ->map(fn (array $meta, string $name): array => [
                'name' => $name,
                'label' => $meta['label'],
                'level' => $meta['level'],
            ])
            ->sortByDesc('level')
            ->values();
    }

    /**
     * Roles that can be assigned from user management. Student portal accounts
     * are provisioned from a student record instead.
     *
     * @return Collection<int, array{name: string, label: string, level: int}>
     */
    public static function assignableStaffRoles(User $actor): Collection
    {
        $staffRoles = (array) config('school.workspace_roles');

        return self::assignableRoles($actor)
            ->filter(fn (array $role): bool => in_array($role['name'], $staffRoles, true))
            ->values();
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return $actor->school_id !== null
            && $actor->school_id === $target->school_id
            && self::targetLevel($target) < self::actorLevel($actor);
    }
}
