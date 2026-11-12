<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\YouTubeUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A lesson a teacher writes for one period of their own timetable. The row
 * carries the chapter it belongs to and the rich text body; the files that go
 * with it live in lesson_materials.
 */
class Lesson extends Model
{
    use BelongsToSchool, SoftDeletes;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const STATUSES = [self::DRAFT, self::PUBLISHED];

    protected $fillable = [
        'academic_session_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'title',
        'chapter_number',
        'chapter_name',
        'lesson_number',
        'description',
        'objectives',
        'content',
        'video_url',
        'youtube_id',
        'reference_links',
        'status',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class)->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /**
     * The player frame for the stored link, or null when the link is not one of
     * the two hosts this module embeds. Only a checked id is interpolated, so a
     * hand-edited column can never point the frame at another site.
     */
    public function playerUrl(): ?string
    {
        if (filled($this->youtube_id)) {
            return YouTubeUrl::embedUrl((string) $this->youtube_id);
        }

        $id = self::vimeoId($this->video_url);

        return $id === null ? null : 'https://player.vimeo.com/video/'.$id;
    }

    /**
     * A Vimeo address keeps only its digits, the same way a YouTube link keeps
     * only its eleven-character id.
     */
    private static function vimeoId(?string $url): ?string
    {
        $candidate = trim((string) $url);

        if ($candidate === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/'))));
        $id = end($segments);

        return is_string($id) && preg_match('/^\d{4,20}$/', $id) === 1 ? $id : null;
    }

    /**
     * "Chapter 4: Algebra · Lesson 2", or whichever parts the teacher filled in.
     */
    public function chapterLabel(): ?string
    {
        $chapter = null;

        if (filled($this->chapter_number)) {
            $chapter = __('Chapter :n', ['n' => $this->chapter_number]);

            if (filled($this->chapter_name)) {
                $chapter .= ': '.$this->chapter_name;
            }
        } elseif (filled($this->chapter_name)) {
            $chapter = $this->chapter_name;
        }

        if (filled($this->lesson_number)) {
            $label = __('Lesson :n', ['n' => $this->lesson_number]);
            $chapter = $chapter === null ? $label : $chapter.' · '.$label;
        }

        return $chapter;
    }

    /**
     * Lessons addressed to a student: their own class, lessons published to the
     * whole class (section_id is null), the current session, published only.
     */
    public function scopeForEnrollment(Builder $query, ?Enrollment $enrollment, ?int $sessionId = null): Builder
    {
        if ($enrollment === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('status', self::PUBLISHED)
            ->where('class_id', (int) $enrollment->class_id)
            ->where(fn (Builder $inner): Builder => $inner
                ->whereNull('section_id')
                ->orWhere('section_id', (int) $enrollment->section_id))
            ->when($sessionId !== null, fn (Builder $inner): Builder => $inner->where('academic_session_id', $sessionId));
    }

    public function scopeFiltered(
        Builder $query,
        ?int $classId,
        ?int $sectionId,
        ?int $subjectId,
        ?string $search = null,
        ?string $chapter = null,
    ): Builder {
        return $query->when($classId, fn (Builder $inner): Builder => $inner->where('class_id', $classId))
            ->when($sectionId, fn (Builder $inner): Builder => $inner->where('section_id', $sectionId))
            ->when($subjectId, fn (Builder $inner): Builder => $inner->where('subject_id', $subjectId))
            ->when($chapter, fn (Builder $inner): Builder => $inner->where('chapter_number', $chapter))
            ->when($search, fn (Builder $inner): Builder => $inner->where(
                fn (Builder $or): Builder => $or
                    ->where('title', 'like', $this->likePattern($search))
                    ->orWhere('chapter_name', 'like', $this->likePattern($search))
                    ->orWhere('description', 'like', $this->likePattern($search))
            ));
    }

    /**
     * A LIKE term is echoed into the query, so the wildcards a user types are
     * matched literally instead of widening the search.
     */
    public function likePattern(string $search): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search)).'%';
    }
}
