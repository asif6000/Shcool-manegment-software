<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function view(User $actor, School $school): bool
    {
        if (! $actor->can('school.view')) {
            return false;
        }

        return $actor->school_id !== null && $actor->school_id === $school->id;
    }

    public function update(User $actor, School $school): bool
    {
        if (! $actor->can('school.update')) {
            return false;
        }

        return $actor->school_id !== null && $actor->school_id === $school->id;
    }
}
