<?php

namespace Tests\Concerns;

use App\Models\School;
use App\Models\User;
use App\Support\SchoolContext;

trait CreatesSchoolData
{
    protected function makeSchool(array $overrides = []): School
    {
        return School::factory()->create($overrides);
    }

    protected function makeUser(
        School $school,
        string $role = 'school_admin',
        array $overrides = [],
    ): User {
        return User::factory()
            ->forSchool($school)
            ->withRole($role)
            ->create($overrides);
    }

    protected function useSchoolContext(?int $schoolId): void
    {
        app(SchoolContext::class)->set($schoolId);
    }

    protected function clearSchoolContext(): void
    {
        app(SchoolContext::class)->clear();
    }
}
