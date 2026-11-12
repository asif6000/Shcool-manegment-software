<?php

namespace App\Services;

use App\Models\StudyMaterial;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudyMaterialService
{
    public const UPLOAD_PROFILE = 'study_material';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
        private readonly SchoolEventNotifier $notifier,
        private readonly PermanentDeleteService $deletes,
        private readonly TeachingScope $teaching,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, ?UploadedFile $file = null): StudyMaterial
    {
        // Sharing has always been immediate, so only an unchecked box makes a
        // draft; homework, which a class is marked on, starts the other way.
        $publish = ! array_key_exists('publish', $data) || filter_var($data['publish'], FILTER_VALIDATE_BOOLEAN);

        $material = DB::transaction(function () use ($actor, $data, $file, $publish): StudyMaterial {
            $material = StudyMaterial::query()->create($this->attributes($actor, $data) + [
                'status' => $publish ? StudyMaterial::PUBLISHED : StudyMaterial::DRAFT,
                'published_at' => $publish ? now() : null,
                'created_by' => $actor->id,
            ]);

            if ($file !== null) {
                $this->store($material, $file);
            }

            $this->audit->log(
                event: 'material.created',
                auditable: $material,
                description: __('Study material :title was shared', ['title' => $material->title]),
                newValues: $material->only(['title', 'class_id', 'section_id', 'subject_id', 'kind', 'status']),
                actor: $actor,
            );

            return $material;
        });

        if ($material->isClasswork() && $material->isPublished()) {
            $this->announceClasswork($material);
        }

        return $material;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, StudyMaterial $material, array $data, ?UploadedFile $file = null): StudyMaterial
    {
        $old = $material->only(['title', 'description', 'kind', 'work_date', 'lesson_id']);

        // A post that leaves out the type must not silently re-file the row as a
        // study material.
        $data['kind'] = in_array($data['kind'] ?? null, StudyMaterial::KINDS, true) ? $data['kind'] : $material->kind;

        DB::transaction(function () use ($actor, $material, $data, $file, $old): void {
            $material->update($this->attributes($actor, $data));

            if ($file !== null) {
                $previous = $material->file_path;
                $this->store($material, $file);

                if ($previous !== null && $previous !== $material->file_path) {
                    $this->uploads->delete($previous, self::UPLOAD_PROFILE);
                }
            }

            $this->audit->log(
                event: 'material.updated',
                auditable: $material,
                description: __('Study material :title was updated', ['title' => $material->title]),
                oldValues: $old,
                newValues: $material->only(['title', 'description', 'kind', 'work_date', 'lesson_id']),
                actor: $actor,
            );
        });

        return $material->refresh();
    }

    /**
     * A draft reaches the class only here, which is also when the inbox notice
     * goes out for classwork.
     */
    public function publish(User $actor, StudyMaterial $material): StudyMaterial
    {
        if ($material->isPublished()) {
            return $material;
        }

        DB::transaction(function () use ($actor, $material): void {
            $material->forceFill([
                'status' => StudyMaterial::PUBLISHED,
                'published_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'material.published',
                auditable: $material,
                description: __(':title was shared with the class', ['title' => $material->title]),
                oldValues: ['status' => StudyMaterial::DRAFT],
                newValues: ['status' => StudyMaterial::PUBLISHED],
                actor: $actor,
            );
        });

        if ($material->isClasswork()) {
            $this->announceClasswork($material);
        }

        return $material->refresh();
    }

    /**
     * Withdrawing keeps the row and its file; it only closes the portal view, so
     * the work can be edited and shared again.
     */
    public function unpublish(User $actor, StudyMaterial $material): StudyMaterial
    {
        if (! $material->isPublished()) {
            return $material;
        }

        DB::transaction(function () use ($actor, $material): void {
            $material->forceFill([
                'status' => StudyMaterial::DRAFT,
                'published_at' => null,
            ])->save();

            $this->audit->log(
                event: 'material.unpublished',
                auditable: $material,
                description: __(':title was put back to draft', ['title' => $material->title]),
                oldValues: ['status' => StudyMaterial::PUBLISHED],
                newValues: ['status' => StudyMaterial::DRAFT],
                actor: $actor,
            );
        });

        return $material->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(User $actor, array $data): array
    {
        return [
            'academic_session_id' => (int) $data['academic_session_id'],
            'class_id' => (int) $data['class_id'],
            'section_id' => filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            'subject_id' => (int) $data['subject_id'],
            'teacher_id' => $this->teacherFor($actor, $data),
            'lesson_id' => filled($data['lesson_id'] ?? null) ? (int) $data['lesson_id'] : null,
            'kind' => in_array($data['kind'] ?? null, StudyMaterial::KINDS, true) ? $data['kind'] : StudyMaterial::MATERIAL,
            'work_date' => filled($data['work_date'] ?? null) ? (string) $data['work_date'] : null,
            'title' => trim((string) $data['title']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function teacherFor(User $actor, array $data): ?int
    {
        if (! $this->teaching->isUnrestricted($actor)) {
            return $this->teaching->teacherId($actor);
        }

        return filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : null;
    }

    private function announceClasswork(StudyMaterial $material): void
    {
        try {
            $this->notifier->classworkShared($material);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function store(StudyMaterial $material, UploadedFile $file): void
    {
        $material->forceFill([
            'file_path' => $this->uploads->storeDocument($file, self::UPLOAD_PROFILE, (string) $material->school_id),
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 160),
            'file_size' => $file->getSize() === false ? null : (int) $file->getSize(),
        ])->save();
    }

    /**
     * Withdrawal used to hide the row; it now removes the file and the row, and
     * any later reference to it is gone with it.
     */
    public function delete(User $actor, StudyMaterial $material): void
    {
        $this->deletes->delete($material, [
            'label' => __('study material'),
            'description' => __('Study material #:id was permanently deleted.', ['id' => $material->getKey()]),
            'actor' => $actor,
        ]);
    }
}
