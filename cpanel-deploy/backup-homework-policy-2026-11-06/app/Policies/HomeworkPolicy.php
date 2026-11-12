<?php

namespace App\Policies;

use App\Models\AcademicSession;
use App\Models\Homework;
use App\Models\User;
use App\Services\TeachingScope;

/**
 * Homework belongs to teachers, never to the office. Three things have to be
 * true before a row can be worked on: the teacher role, the period the admin
 * assigned on the actor's own timetable, and the author's hand on the record.
 * Administrators and principals are refused even if a stale permission row
 * still grants them homework.manage.
 */
class HomeworkPolicy
{
    public function __construct(
        private readonly TeachingScope $teaching,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $this->isTeacher($actor) && $actor->can('homework.view');
    }

    public function view(User $actor, Homework $homework): bool
    {
        return $this->isTeacher($actor)
            && $actor->can('homework.view')
            && $this->inScope($actor, $homework)
            && $this->isMine($actor, $homework);
    }

    public function create(User $actor): bool
    {
        return $this->isTeacher($actor) && $actor->can('homework.manage');
    }

    public function update(User $actor, Homework $homework): bool
    {
        return $this->isTeacher($actor)
            && $actor->can('homework.manage')
            && $this->inScope($actor, $homework)
            && $this->isMine($actor, $homework);
    }

    public function delete(User $actor, Homework $homework): bool
    {
        return $this->update($actor, $homework);
    }

    public function publish(User $actor, Homework $homework): bool
    {
        return $this->update($actor, $homework);
    }

    /**
     * Answering a pupil's work belongs to the teacher who set it, so a review
     * needs the same ground as an edit.
     */
    public function review(User $actor, Homework $homework): bool
    {
        return $this->update($actor, $homework);
    }

    /**
     * Administrators and principals are not teachers for this module. The role
     * check is deliberately hard-coded so the editable permission matrix can
     * never hand homework to the office.
     */
    private function isTeacher(User $actor): bool
    {
        return $actor->hasRole('teacher')
            && ! $actor->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    }

    private function inScope(User $actor, Homework $homework): bool
    {
        $session = $this->session($homework);

        if ($session === null) {
            return false;
        }

        return $this->teaching->allows(
            $actor,
            $session,
            (int) $homework->class_id,
            $homework->section_id === null ? null : (int) $homework->section_id,
            (int) $homework->subject_id,
        );
    }

    /**
     * Two teachers can both hold a class, section and subject, so the period
     * alone cannot say whose homework a row is. The author's account or the
     * teacher profile stored on the row has to match the actor.
     */
    private function isMine(User $actor, Homework $homework): bool
    {
        if ((int) $homework->created_by === (int) $actor->id) {
            return true;
        }

        // A row filed before a teacher was recorded still belongs to the period,
        // so the teacher who holds that period may open and claim it.
        if ($homework->teacher_id === null) {
            return true;
        }

        $teacherId = $this->teaching->teacherId($actor);

        return $teacherId !== null
            && $homework->teacher_id !== null
            && (int) $homework->teacher_id === $teacherId;
    }

    private function session(Homework $homework): ?AcademicSession
    {
        if ($homework->relationLoaded('academicSession')) {
            return $homework->academicSession;
        }

        return AcademicSession::query()->find($homework->academic_session_id)
            ?? $this->teaching->currentSession();
    }
}
