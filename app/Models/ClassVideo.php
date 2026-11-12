<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\FileUploadService;
use App\Support\YouTubeUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A lesson video for one class. It arrives either as a YouTube link or as a file
 * uploaded to the school's own disk, and only rows flagged for the website are
 * ever rendered to visitors.
 */
class ClassVideo extends Model
{
    use BelongsToSchool, SoftDeletes;

    public const YOUTUBE = 'youtube';

    public const UPLOAD = 'upload';

    public const SOURCES = [self::YOUTUBE, self::UPLOAD];

    public const UPLOAD_PROFILE = 'class_video';

    protected $fillable = [
        'academic_session_id',
        'class_id',
        'section_id',
        'subject_id',
        'title',
        'description',
        'source',
        'youtube_url',
        'youtube_id',
        'show_on_website',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'show_on_website' => 'boolean',
        ];
    }

    public function isYoutube(): bool
    {
        return $this->source === self::YOUTUBE;
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The frame or file a player opens. A YouTube row gets our own embed URL,
     * built from the stored id, never from the link as pasted.
     */
    public function playerUrl(): ?string
    {
        if ($this->isYoutube()) {
            return $this->youtube_id === null ? null : YouTubeUrl::embedUrl($this->youtube_id);
        }

        return $this->file_path === null
            ? null
            : app(FileUploadService::class)->url($this->file_path, self::UPLOAD_PROFILE);
    }

    /** The card image: YouTube's own thumbnail, or null for an uploaded clip. */
    public function thumbnailUrl(): ?string
    {
        return $this->isYoutube() && $this->youtube_id !== null
            ? YouTubeUrl::thumbnailUrl($this->youtube_id)
            : null;
    }

    /** The page a staff member can open the video on outside our own player. */
    public function openUrl(): ?string
    {
        if ($this->isYoutube()) {
            return $this->youtube_id === null ? null : YouTubeUrl::watchUrl($this->youtube_id);
        }

        return $this->file_path === null
            ? null
            : app(FileUploadService::class)->url($this->file_path, self::UPLOAD_PROFILE);
    }

    /** The name a download or the admin list shows for the stored file. */
    public function fileLabel(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        return $this->file_name ?: basename($this->file_path);
    }

    public function scopeOnWebsite(Builder $query): Builder
    {
        return $query->where('show_on_website', true);
    }

    public function scopeFiltered(
        Builder $query,
        ?int $classId,
        ?int $sectionId,
        ?string $search = null,
        ?string $source = null,
        ?string $website = null,
    ): Builder {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search ?? '')).'%';

        return $query->when($classId, fn (Builder $inner): Builder => $inner->where('class_id', $classId))
            ->when($sectionId, fn (Builder $inner): Builder => $inner->where('section_id', $sectionId))
            ->when(in_array($source, self::SOURCES, true), fn (Builder $inner): Builder => $inner->where('source', $source))
            ->when($website === '1', fn (Builder $inner): Builder => $inner->where('show_on_website', true))
            ->when($website === '0', fn (Builder $inner): Builder => $inner->where('show_on_website', false))
            ->when(filled($search), fn (Builder $inner): Builder => $inner
                ->where(fn (Builder $like2): Builder => $like2
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)));
    }
}
