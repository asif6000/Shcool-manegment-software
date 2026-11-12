<?php

namespace App\Services;

use App\Models\ClassVideo;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\YouTubeUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ClassVideoService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, ?UploadedFile $file = null): ClassVideo
    {
        return DB::transaction(function () use ($actor, $data, $file): ClassVideo {
            $video = ClassVideo::query()->create([
                ...$this->period($data),
                'title' => trim((string) $data['title']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'source' => $data['source'],
                ...$this->media($data),
                'show_on_website' => (bool) ($data['show_on_website'] ?? false),
                'created_by' => $actor->id,
            ]);

            if ($file !== null) {
                $this->store($video, $file);
            }

            $this->audit->log(
                event: 'class_video.created',
                auditable: $video,
                description: __('Class video :title was added', ['title' => $video->title]),
                newValues: $video->only(['title', 'class_id', 'section_id', 'source', 'show_on_website']),
                actor: $actor,
            );

            return $video;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ClassVideo $video, array $data, ?UploadedFile $file = null): ClassVideo
    {
        $old = $video->only(['title', 'description', 'source', 'show_on_website']);

        DB::transaction(function () use ($actor, $video, $data, $file, $old): void {
            $switched = $video->source !== $data['source'];
            $previousFile = $video->file_path;

            $video->update([
                ...$this->period($data),
                'title' => trim((string) $data['title']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'source' => $data['source'],
                ...$this->media($data, $switched),
                'show_on_website' => (bool) ($data['show_on_website'] ?? false),
            ]);

            if ($file !== null) {
                $this->store($video, $file);
            }

            if ($previousFile !== null && $previousFile !== $video->file_path) {
                $this->uploads->delete($previousFile, ClassVideo::UPLOAD_PROFILE);
            }

            $this->audit->log(
                event: 'class_video.updated',
                auditable: $video,
                description: __('Class video :title was updated', ['title' => $video->title]),
                oldValues: $old,
                newValues: $video->only(['title', 'description', 'source', 'show_on_website']),
                actor: $actor,
            );
        });

        return $video->refresh();
    }

    /**
     * Publishing is the only thing a visitor can see, so it gets its own audit
     * line instead of hiding inside a generic update.
     */
    public function toggleWebsite(User $actor, ClassVideo $video): ClassVideo
    {
        DB::transaction(function () use ($actor, $video): void {
            $video->update(['show_on_website' => ! $video->show_on_website]);

            $this->audit->log(
                event: 'class_video.website_toggled',
                auditable: $video,
                description: $video->show_on_website
                    ? __('Class video :title was put on the website', ['title' => $video->title])
                    : __('Class video :title was taken off the website', ['title' => $video->title]),
                newValues: ['show_on_website' => $video->show_on_website],
                actor: $actor,
            );
        });

        return $video->refresh();
    }

    /**
     * The player closes because the row is gone: an uploaded clip is deleted
     * from storage with it, and a YouTube link has no file to remove.
     */
    public function delete(User $actor, ClassVideo $video): void
    {
        $this->deletes->delete($video, [
            'label' => __('class video'),
            'description' => __('Class video #:id was permanently deleted.', ['id' => $video->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function period(array $data): array
    {
        return [
            'academic_session_id' => (int) $data['academic_session_id'],
            'class_id' => (int) $data['class_id'],
            'section_id' => filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            'subject_id' => filled($data['subject_id'] ?? null) ? (int) $data['subject_id'] : null,
        ];
    }

    /**
     * Only the id we parsed ourselves is stored, and the link is rebuilt from it,
     * so no pasted string is ever echoed into the website. A row that changes
     * source loses the media of the old one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function media(array $data, bool $switched = false): array
    {
        if (($data['source'] ?? null) === ClassVideo::UPLOAD) {
            return ['youtube_id' => null, 'youtube_url' => null];
        }

        $id = YouTubeUrl::videoId((string) ($data['youtube_url'] ?? ''));
        $attributes = [
            'youtube_id' => $id,
            'youtube_url' => $id === null ? null : YouTubeUrl::watchUrl($id),
        ];

        return $switched
            ? [...$attributes, 'file_path' => null, 'file_name' => null, 'file_size' => null]
            : $attributes;
    }

    private function store(ClassVideo $video, UploadedFile $file): void
    {
        $video->forceFill([
            'file_path' => $this->uploads->storeVideo($file, ClassVideo::UPLOAD_PROFILE, (string) $video->school_id),
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 160),
            'file_size' => $file->getSize() === false ? null : (int) $file->getSize(),
        ])->save();
    }
}
