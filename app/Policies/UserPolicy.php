<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleHierarchy;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        if (! $actor->can('users.view')) {
            return false;
        }

        return $actor->school_id !== null && $actor->school_id === $target->school_id;
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.update') && RoleHierarchy::canManageUser($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.delete') && RoleHierarchy::canManageUser($actor, $target);
    }

    public function changeStatus(User $actor, User $target): bool
    {
        return $actor->can('users.update') && RoleHierarchy::canManageUser($actor, $target);
    }

    public function sendResetLink(User $actor, User $target): bool
    {
        // A reset link has nowhere to go for a mobile-only account.
        return $actor->can('users.reset-password')
            && filled($target->email)
            && RoleHierarchy::canManageUser($actor, $target);
    }
}
