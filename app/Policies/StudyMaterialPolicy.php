<?php

namespace App\Policies;

use App\Models\AcademicSession;
use App\Models\StudyMaterial;
use App\Models\User;
use App\Services\TeachingScope;

/**
 * Classwork and study material live in the same table and follow the same rule
 * as homework: the teacher role, the period the admin assigned, and the
 * teacher's own hand on it. The office is refused even if the editable
 * permission matrix grants it materials.manage.
 */
class StudyMaterialPolicy
{
    public function __construct(
        private readonly TeachingScope $teaching,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $this->isTeacher($actor) && $actor->can('materials.view');
    }

    public function view(User $actor, StudyMaterial $material): bool
    {
        return $this->isTeacher($actor)
            && $actor->can('materials.view')
            && $this->inScope($actor, $material)
            && $this->isMine($actor, $material);
    }

    public function create(User $actor): bool
    {
        return $this->isTeacher($actor) && $actor->can('materials.manage');
    }

    public function update(User $actor, StudyMaterial $material): bool
    {
        return $this->isTeacher($actor)
            && $actor->can('materials.manage')
            && $this->inScope($actor, $material)
            && $this->isMine($actor, $material);
    }

    public function delete(User $actor, StudyMaterial $material): bool
    {
        return $this->update($actor, $material);
    }

    public function publish(User $actor, StudyMaterial $material): bool
    {
        return $this->update($actor, $material);
    }

    /**
     * Administrators and principals are not teachers for this module.
     */
    private function isTeacher(User $actor): bool
    {
        return $actor->hasRole('teacher')
            && ! $actor->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    }

    private function inScope(User $actor, StudyMaterial $material): bool
    {
        $session = $material->relationLoaded('academicSession')
            ? $material->academicSession
            : (AcademicSession::query()->find($material->academic_session_id) ?? $this->teaching->currentSession());

        if ($session === null) {
            return false;
        }

        return $this->teaching->allows(
            $actor,
            $session,
            (int) $material->class_id,
            $material->section_id === null ? null : (int) $material->section_id,
            (int) $material->subject_id,
        );
    }

    private function isMine(User $actor, StudyMaterial $material): bool
    {
        if ((int) $material->created_by === (int) $actor->id) {
            return true;
        }

        // A row filed before a teacher was recorded still belongs to the period,
        // so the teacher who holds that period may open and claim it.
        if ($material->teacher_id === null) {
            return true;
        }

        $teacherId = $this->teaching->teacherId($actor);

        return $teacherId !== null
            && $material->teacher_id !== null
            && (int) $material->teacher_id === $teacherId;
    }
}
