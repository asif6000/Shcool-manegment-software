<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Assignment management belongs to the school administrator alone. The role
 * check is hard-coded so a stale or edited permission row cannot hand it to a
 * teacher or principal, and the regular staff permissions still have to be in
 * place before the screen opens.
 */
class TeacherAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) && $user->can('staff.view');
    }

    public function view(User $user, TeacherAssignment $assignment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) && $user->can('staff.update');
    }

    public function update(User $user, TeacherAssignment $assignment): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, TeacherAssignment $assignment): bool
    {
        return $this->create($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('school_admin');
    }
}
