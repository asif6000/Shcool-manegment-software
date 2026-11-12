<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\HtmlContent;
use App\Support\YouTubeUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writing a lesson: what may be stored, how the rich text is cleaned, when a
 * draft becomes visible to the class and which files belong to it.
 *
 * Publishing needs nobody's approval — a teacher who teaches the period decides
 * when their own lesson is ready.
 */
class LessonService
{
    public const UPLOAD_PROFILE = 'lesson_attachment';

    public const IMAGE_PROFILE = 'lesson_image';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
        private readonly HtmlContent $html,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(User $actor, array $data, array $files = []): Lesson
    {
        $status = $this->statusOf($data);

        $lesson = DB::transaction(function () use ($actor, $data, $files, $status): Lesson {
            /** @var Lesson $lesson */
            $lesson = Lesson::query()->create(
                $this->attributes($data, $actor) + ['status' => Lesson::DRAFT] + ['created_by' => $actor->id]
            );

            foreach ($files as $file) {
                $this->addMaterial($lesson, $file);
            }

            if ($status === Lesson::PUBLISHED) {
                $lesson->forceFill(['status' => Lesson::PUBLISHED, 'published_at' => now()])->save();
            }

            $this->audit->log(
                event: 'lesson.created',
                auditable: $lesson,
                description: __('Lesson :title was created', ['title' => $lesson->title]),
                actor: $actor,
                newValues: $lesson->only(['title', 'class_id', 'section_id', 'subject_id', 'status']),
            );

            return $lesson;
        });

        return $lesson->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, int>  $removedMaterials
     */
    public function update(
        User $actor,
        Lesson $lesson,
        array $data,
        array $files = [],
        array $removedMaterials = [],
    ): Lesson {
        $old = $lesson->only(['title', 'class_id', 'section_id', 'subject_id', 'chapter_number', 'status']);
        $status = $this->statusOf($data);
        $wasDraft = $lesson->status === Lesson::DRAFT;

        DB::transaction(function () use ($actor, $lesson, $data, $files, $removedMaterials, $status, $wasDraft, $old): void {
            $lesson->update($this->attributes($data, $actor));

            foreach ($files as $file) {
                $this->addMaterial($lesson, $file);
            }

            foreach ($this->materialsToDrop($lesson, $removedMaterials) as $material) {
                $this->dropMaterial($material);
            }

            if ($status === Lesson::PUBLISHED && $wasDraft) {
                $lesson->forceFill(['status' => Lesson::PUBLISHED, 'published_at' => now()])->save();
            } elseif ($status === Lesson::DRAFT && ! $wasDraft) {
                $lesson->forceFill(['status' => Lesson::DRAFT, 'published_at' => null])->save();
            }

            $this->audit->log(
                event: 'lesson.updated',
                auditable: $lesson,
                description: __('Lesson :title was updated', ['title' => $lesson->title]),
                oldValues: $old,
                newValues: $lesson->only(['title', 'class_id', 'section_id', 'subject_id', 'chapter_number', 'status']),
                actor: $actor,
            );
        });

        return $lesson->refresh();
    }

    public function publish(User $actor, Lesson $lesson): Lesson
    {
        if ($lesson->status === Lesson::PUBLISHED) {
            return $lesson;
        }

        if ($this->bodyWords($lesson) === 0) {
            throw ValidationException::withMessages([
                'content' => __('Write the lesson before publishing it.'),
            ]);
        }

        DB::transaction(function () use ($actor, $lesson): void {
            $lesson->forceFill(['status' => Lesson::PUBLISHED, 'published_at' => now()])->save();

            $this->audit->log(
                event: 'lesson.published',
                auditable: $lesson,
                description: __('Lesson :title was published', ['title' => $lesson->title]),
                oldValues: ['status' => Lesson::DRAFT],
                newValues: ['status' => Lesson::PUBLISHED],
                actor: $actor,
            );
        });

        return $lesson->refresh();
    }

    /**
     * Withdrawing a lesson keeps the row and its files; it only closes the
     * portal view, so the work is still there to edit and republish.
     */
    public function unpublish(User $actor, Lesson $lesson): Lesson
    {
        if ($lesson->status === Lesson::DRAFT) {
            return $lesson;
        }

        DB::transaction(function () use ($actor, $lesson): void {
            $lesson->forceFill(['status' => Lesson::DRAFT, 'published_at' => null])->save();

            $this->audit->log(
                event: 'lesson.unpublished',
                auditable: $lesson,
                description: __('Lesson :title was moved back to drafts', ['title' => $lesson->title]),
                oldValues: ['status' => Lesson::PUBLISHED],
                newValues: ['status' => Lesson::DRAFT],
                actor: $actor,
            );
        });

        return $lesson->refresh();
    }

    public function delete(User $actor, Lesson $lesson): void
    {
        // The material sheet attached to the lesson is part of it, so the rows
        // and their files go together with the lesson.
        $this->deletes->delete($lesson, [
            'label' => __('lesson'),
            'description' => __('Lesson #:id was permanently deleted.', ['id' => $lesson->getKey()]),
            'actor' => $actor,
        ]);
    }

    public function addMaterial(Lesson $lesson, UploadedFile $file, ?string $title = null): LessonMaterial
    {
        $path = $this->uploads->storeDocument($file, self::UPLOAD_PROFILE, (string) $lesson->school_id);
        $extension = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));

        /** @var LessonMaterial $material */
        return $lesson->materials()->create([
            'school_id' => $lesson->school_id,
            'kind' => in_array($extension, self::IMAGE_EXTENSIONS, true) ? LessonMaterial::IMAGE : LessonMaterial::DOCUMENT,
            'title' => Str::limit(trim($title ?? '') !== '' ? trim((string) $title) : (string) $file->getClientOriginalName(), 160, ''),
            'file_path' => $path,
            'file_name' => Str::limit((string) $file->getClientOriginalName(), 160, ''),
            'file_size' => $file->getSize() === false ? null : (int) $file->getSize(),
        ]);
    }

    /**
     * A picture the editor drops into the body. The lesson may not exist yet —
     * a teacher can write and paste before the first save — so the folder is the
     * school's, not one row's.
     *
     * @return array{path: string, url: string, name: string}
     */
    public function storeEditorImage(UploadedFile $file, int $schoolId): array
    {
        $path = $this->uploads->store($file, self::IMAGE_PROFILE, (string) $schoolId);
        $url = $this->uploads->url($path, self::IMAGE_PROFILE);

        return [
            'path' => $path,
            'url' => (string) $url,
            'name' => Str::limit((string) $file->getClientOriginalName(), 160, ''),
        ];
    }

    /**
     * The body as it should be stored. Everything the editor may produce passes;
     * anything else arrives as plain text.
     */
    public function cleanBody(?string $html): string
    {
        return $this->html->sanitize($html);
    }

    public function bodyWords(Lesson $lesson): int
    {
        return $this->html->wordCount($lesson->content);
    }

    /**
     * The id a teacher's own rows are written under, so an account that is a
     * teacher cannot tag a lesson with somebody else's name.
     */
    private function teacherIdFor(User $actor): ?int
    {
        $id = Teacher::query()->where('user_id', $actor->id)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function teacherId(array $data, User $actor): ?int
    {
        if ($this->mayNameTeacher($actor) && filled($data['teacher_id'] ?? null)) {
            return (int) $data['teacher_id'];
        }

        return $this->teacherIdFor($actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, User $actor): array
    {
        $videoUrl = $this->html->safeUrl(Arr::get($data, 'video_url'));
        $youtubeId = YouTubeUrl::videoId((string) Arr::get($data, 'video_url'));

        return [
            'academic_session_id' => (int) $data['academic_session_id'],
            'class_id' => (int) $data['class_id'],
            'section_id' => filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            'subject_id' => (int) $data['subject_id'],
            // A teacher's lesson is always written under their own name; only the
            // office may choose which teacher a lesson belongs to.
            'teacher_id' => $this->teacherId($data, $actor),
            'title' => trim((string) $data['title']),
            'chapter_number' => filled($data['chapter_number'] ?? null) ? trim((string) $data['chapter_number']) : null,
            'chapter_name' => filled($data['chapter_name'] ?? null) ? trim((string) $data['chapter_name']) : null,
            'lesson_number' => filled($data['lesson_number'] ?? null) ? trim((string) $data['lesson_number']) : null,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'objectives' => filled($data['objectives'] ?? null) ? trim((string) $data['objectives']) : null,
            'content' => $this->cleanBody(Arr::get($data, 'content')),
            'video_url' => $videoUrl,
            'youtube_id' => $youtubeId,
            'reference_links' => $this->referenceLinks(Arr::get($data, 'reference_links')),
        ];
    }

    private function referenceLinks(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $lines = [];

        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $url = $this->html->safeUrl($line);

            if ($url !== null) {
                $lines[] = $url;
            }
        }

        return $lines === [] ? null : implode("\n", array_slice($lines, 0, 10));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statusOf(array $data): string
    {
        return in_array($data['status'] ?? null, Lesson::STATUSES, true)
            ? (string) $data['status']
            : Lesson::DRAFT;
    }

    private function mayNameTeacher(User $actor): bool
    {
        return $actor->hasAnyRole((array) config('attendance.unrestricted_roles', ['school_admin', 'principal']));
    }

    /**
     * Only ids that really belong to this lesson may be dropped, so a tampered
     * form can neither detach another lesson's file nor reach outside the table.
     *
     * @param  array<int, int>  $ids
     * @return list<LessonMaterial>
     */
    private function materialsToDrop(Lesson $lesson, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return LessonMaterial::query()
            ->where('lesson_id', $lesson->id)
            ->whereIn('id', array_map('intval', $ids))
            ->get()
            ->all();
    }

    private function dropMaterial(LessonMaterial $material): void
    {
        $this->uploads->delete($material->file_path, self::UPLOAD_PROFILE);
        $material->delete();
    }
}
