<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudyMaterial extends Model
{
    use BelongsToSchool, SoftDeletes;

    public const MATERIAL = 'material';

    public const CLASSWORK = 'classwork';

    public const KINDS = [self::MATERIAL, self::CLASSWORK];

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const STATUSES = [self::DRAFT, self::PUBLISHED];

    protected $fillable = [
        'academic_session_id',
        'class_id',
        'section_id',
        'subject_id',
        'status',
        'kind',
        'teacher_id',
        'lesson_id',
        'work_date',
        'published_at',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function isClasswork(): bool
    {
        return $this->kind === self::CLASSWORK;
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
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

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Material addressed to a student: their own class, either published to the
     * whole class or to the section they are in, for the session asked for.
     * A draft is the teacher's own note and never reaches the portal.
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
        ?string $kind = null,
        ?string $from = null,
        ?string $to = null,
        ?string $status = null,
    ): Builder {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search ?? '')).'%';

        return $query->when($classId, fn (Builder $inner): Builder => $inner->where('class_id', $classId))
            ->when($sectionId, fn (Builder $inner): Builder => $inner->where('section_id', $sectionId))
            ->when($subjectId, fn (Builder $inner): Builder => $inner->where('subject_id', $subjectId))
            ->when(in_array($kind, self::KINDS, true), fn (Builder $inner): Builder => $inner->where('kind', $kind))
            ->when(in_array($status, self::STATUSES, true), fn (Builder $inner): Builder => $inner->where('status', $status))
            ->when(filled($search), fn (Builder $inner): Builder => $inner
                ->where(fn (Builder $like2): Builder => $like2
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)))
            ->when(filled($from), fn (Builder $inner): Builder => $inner->whereDate('created_at', '>=', $from))
            ->when(filled($to), fn (Builder $inner): Builder => $inner->whereDate('created_at', '<=', $to));
    }

    /** The name a download is offered under, falling back to the stored file. */
    public function downloadLabel(): string
    {
        return $this->file_name ?: basename((string) $this->file_path);
    }
}
