<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who teaches what. A teacher's assignments are the periods on their timetable:
 * every class_routines row is a (class, section, subject) tuple, so the routine
 * table stays the single source of truth and there is no second assignment
 * matrix to keep in sync. Administrators and principals are unrestricted.
 */
class TeachingScope
{
    /**
     * @var Collection<string, Collection<int, array<string, mixed>>|null>
     */
    private Collection $memo;

    public function __construct()
    {
        $this->memo = collect();
    }

    /**
     * Drop the per-request timetable cache. ResolveSchoolContext calls this as
     * every request begins, so an instance that outlives the request - a long
     * lived worker, or a test that sends two requests - cannot answer with a
     * picture of the timetable that has since changed.
     */
    public function reset(): void
    {
        $this->memo = collect();
    }

    public function isUnrestricted(User $user): bool
    {
        return $user->hasAnyRole((array) config('attendance.unrestricted_roles', ['school_admin', 'principal']));
    }

    public function teacherId(User $user): ?int
    {
        $id = Teacher::query()->where('user_id', $user->id)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The periods assigned to the user, or null when the user is unrestricted.
     * An empty collection means the user is a staff member with no periods on
     * the timetable, which is deliberately different from "no restriction".
     *
     * @return Collection<int, array{class_id: int, section_id: int, subject_id: int, day_of_week: int, start_time: string, end_time: string, room: string|null}>|null
     */
    public function slots(User $user, AcademicSession $session): ?Collection
    {
        $key = $user->id.':'.$session->id;

        if ($this->memo->has($key)) {
            return $this->memo->get($key);
        }

        $slots = null;

        if (! $this->isUnrestricted($user)) {
            $teacherId = $this->teacherId($user);

            $slots = $teacherId === null
                ? collect()
                : ClassRoutine::query()
                    ->where('academic_session_id', $session->id)
                    ->where('teacher_id', $teacherId)
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get(['class_id', 'section_id', 'subject_id', 'day_of_week', 'start_time', 'end_time', 'room'])
                    ->map(fn (ClassRoutine $routine): array => [
                        'class_id' => (int) $routine->class_id,
                        'section_id' => (int) $routine->section_id,
                        'subject_id' => (int) $routine->subject_id,
                        'day_of_week' => (int) $routine->day_of_week,
                        'start_time' => (string) $routine->start_time,
                        'end_time' => (string) $routine->end_time,
                        'room' => $routine->room,
                    ])
                    ->values();
        }

        $this->memo->put($key, $slots);

        return $slots;
    }

    /**
     * @return Collection<int, array{class_id: int, section_id: int}>|null
     */
    public function pairs(User $user, AcademicSession $session): ?Collection
    {
        $slots = $this->slots($user, $session);

        if ($slots === null) {
            return null;
        }

        return $slots
            ->map(fn (array $slot): array => [
                'class_id' => $slot['class_id'],
                'section_id' => $slot['section_id'],
            ])
            ->unique(fn (array $pair): string => $pair['class_id'].':'.$pair['section_id'])
            ->values();
    }

    /**
     * Sections of one class the user teaches, or null when unrestricted.
     *
     * @return Collection<int, int>|null
     */
    public function sectionIds(User $user, AcademicSession $session, int $classId): ?Collection
    {
        $pairs = $this->pairs($user, $session);

        if ($pairs === null) {
            return null;
        }

        return $pairs
            ->where('class_id', $classId)
            ->pluck('section_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Subjects the user teaches to a class, optionally narrowed to one section.
     * Unrestricted users are not limited by subject at all, so they get an empty
     * list here; call allows() when the question is yes/no rather than a picker.
     *
     * @return Collection<int, int>
     */
    public function subjectIds(User $user, AcademicSession $session, int $classId, ?int $sectionId = null): Collection
    {
        $slots = $this->slots($user, $session);

        if ($slots === null) {
            return collect();
        }

        return $slots
            ->where('class_id', $classId)
            ->when($sectionId !== null, fn (Collection $rows): Collection => $rows->where('section_id', $sectionId))
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Sections the user teaches one particular subject in, or null when the user
     * is unrestricted. Used by the marks sheet, where a paper names a class and a
     * subject but the roll is split per section.
     *
     * @return Collection<int, int>|null
     */
    public function sectionIdsForSubject(User $user, AcademicSession $session, int $classId, int $subjectId): ?Collection
    {
        $slots = $this->slots($user, $session);

        if ($slots === null) {
            return null;
        }

        return $slots
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->pluck('section_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * A null section or subject means "any of the ones I teach".
     */
    public function allows(User $user, AcademicSession $session, int $classId, ?int $sectionId = null, ?int $subjectId = null): bool
    {
        $slots = $this->slots($user, $session);

        if ($slots === null) {
            return true;
        }

        return $slots->contains(fn (array $slot): bool => $slot['class_id'] === $classId
            && ($sectionId === null || $slot['section_id'] === $sectionId)
            && ($subjectId === null || $slot['subject_id'] === $subjectId));
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(User $user, AcademicSession $session, int $classId, ?int $sectionId = null, ?int $subjectId = null): void
    {
        if (! $this->allows($user, $session, $classId, $sectionId, $subjectId)) {
            throw new AuthorizationException(__('You can only manage classes and subjects on your own timetable.'));
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @return Collection<int, SchoolClass>
     */
    public function classes(User $user, ?AcademicSession $session, Collection $classes): Collection
    {
        if ($this->isUnrestricted($user)) {
            return $classes;
        }

        // No session means no timetable to read, so a teacher has no classes.
        // Returning the full list here would fail open while every other scope
        // check fails closed.
        if ($session === null) {
            return collect();
        }

        $pairs = $this->pairs($user, $session);

        if ($pairs === null) {
            return $classes;
        }

        $allowed = $pairs->pluck('class_id')->unique();

        return $classes->filter(fn (SchoolClass $class): bool => $allowed->contains((int) $class->id))->values();
    }

    public function currentSession(): ?AcademicSession
    {
        return AcademicSession::query()->where('is_current', true)->orderByDesc('start_date')->first();
    }

    /**
     * Narrow a student query to the learners on the viewer's own timetable, so a
     * teacher cannot browse guardian details for a class they do not teach.
     */
    public function constrainStudents(User $viewer, ?AcademicSession $session, Builder $query): Builder
    {
        $pairs = $session === null
            ? ($this->isUnrestricted($viewer) ? null : collect())
            : $this->pairs($viewer, $session);

        if ($pairs === null) {
            return $query;
        }

        if ($pairs->isEmpty()) {
            // No periods assigned means no students, not "every student".
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($pairs, $session): void {
            foreach ($pairs as $pair) {
                $outer->orWhereHas('enrollments', fn (Builder $inner): Builder => $this->enrollmentMatch($inner, $pair, $session));
            }
        });
    }

    /**
     * Narrow a query whose rows name a class, an optional section and a subject
     * (homework, papers, materials) to the actor's own periods. A row without a
     * section is shared by the whole class, so it matches any section the actor
     * holds for that subject.
     */
    public function constrainPeriods(User $actor, ?AcademicSession $session, Builder $query): Builder
    {
        $slots = $session === null
            ? ($this->isUnrestricted($actor) ? null : collect())
            : $this->slots($actor, $session);

        if ($slots === null) {
            return $query;
        }

        if ($slots->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $triples = $slots
            ->map(fn (array $slot): string => $slot['class_id'].':'.$slot['section_id'].':'.$slot['subject_id'])
            ->unique()
            ->values();

        return $query->where(function (Builder $outer) use ($triples): void {
            foreach ($triples as $triple) {
                [$classId, $sectionId, $subjectId] = array_map('intval', explode(':', $triple));

                $outer->orWhere(fn (Builder $inner): Builder => $inner
                    ->where('class_id', $classId)
                    ->where('subject_id', $subjectId)
                    ->where(fn (Builder $shared): Builder => $shared
                        ->where('section_id', $sectionId)
                        ->orWhereNull('section_id')));
            }
        });
    }

    public function mayViewStudent(User $viewer, Student $student, ?AcademicSession $session): bool
    {
        if ($this->isUnrestricted($viewer)) {
            return true;
        }

        if ($session === null) {
            return false;
        }

        $pairs = $this->pairs($viewer, $session);

        if ($pairs->isEmpty()) {
            return false;
        }

        return $student->enrollments()
            ->where(function (Builder $outer) use ($pairs, $session): void {
                foreach ($pairs as $pair) {
                    $outer->orWhere(fn (Builder $inner): Builder => $this->enrollmentMatch($inner, $pair, $session));
                }
            })
            ->exists();
    }

    /**
     * @param  array{class_id: int, section_id: int}  $pair
     */
    private function enrollmentMatch(Builder $query, array $pair, AcademicSession $session): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('academic_session_id', $session->id)
            ->where('class_id', $pair['class_id'])
            ->where('section_id', $pair['section_id']);
    }
}
