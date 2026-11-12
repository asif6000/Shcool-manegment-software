<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * A teacher may only mark attendance for the class/section pairs that appear on
 * their own timetable. The scope itself lives in TeachingScope, because marks,
 * homework and study material are limited by the same assignments.
 */
class AttendanceScope
{
    public function __construct(private readonly TeachingScope $teaching) {}

    /**
     * @return Collection<int, array{class_id: int, section_id: int}>|null
     *                                                                     Null means unrestricted.
     */
    public function pairs(User $user, AcademicSession $session): ?Collection
    {
        return $this->teaching->pairs($user, $session);
    }

    /**
     * Sections of one class the user may touch, or null when unrestricted.
     *
     * @return Collection<int, int>|null
     */
    public function sectionIds(User $user, AcademicSession $session, int $classId): ?Collection
    {
        return $this->teaching->sectionIds($user, $session, $classId);
    }

    public function allows(User $user, AcademicSession $session, int $classId, ?int $sectionId = null): bool
    {
        return $this->teaching->allows($user, $session, $classId, $sectionId);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(User $user, AcademicSession $session, int $classId, ?int $sectionId = null): void
    {
        if (! $this->allows($user, $session, $classId, $sectionId)) {
            throw new AuthorizationException(__('You can only manage attendance for the classes on your timetable.'));
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @return Collection<int, SchoolClass>
     */
    public function classes(User $user, ?AcademicSession $session, Collection $classes): Collection
    {
        return $this->teaching->classes($user, $session, $classes);
    }
}
