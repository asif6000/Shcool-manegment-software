<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Homework extends Model
{
    use BelongsToSchool, SoftDeletes;

    /** The table name is identical in singular and plural English. */
    protected $table = 'homework';

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::DRAFT, self::PUBLISHED, self::CANCELLED];

    /**
     * The unified teacher screen files both kinds of work in this one table:
     * what was taught in class ("classwork") and what the student takes home.
     * Rows written before the merge keep the default 'homework' category.
     */
    public const CATEGORY_HOMEWORK = 'homework';

    public const CATEGORY_CLASSWORK = 'classwork';

    public const CATEGORIES = [self::CATEGORY_HOMEWORK, self::CATEGORY_CLASSWORK];

    protected $fillable = [
        'academic_session_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'title',
        'category',
        'description',
        'instructions',
        'assigned_date',
        'due_date',
        'status',
        'published_at',
        'attachment_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'due_date' => 'date',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    /**
     * Homework addressed to a student: their own class, plus entries published
     * to the whole class (section_id is null) and the current session only.
     * Drafts and cancelled homework never reach the portal.
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

    /**
     * Staff accounts see every class; the section filter is optional so a
     * teacher can review one class at a time.
     */
    public function scopeFiltered(Builder $query, ?int $classId, ?int $sectionId, ?int $subjectId): Builder
    {
        return $query->when($classId, fn (Builder $inner): Builder => $inner->where('class_id', $classId))
            ->when($sectionId, fn (Builder $inner): Builder => $inner->where('section_id', $sectionId))
            ->when($subjectId, fn (Builder $inner): Builder => $inner->where('subject_id', $subjectId));
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return in_array($category, self::CATEGORIES, true)
            ? $query->where('category', $category)
            : $query;
    }

    public function isClasswork(): bool
    {
        return $this->category === self::CATEGORY_CLASSWORK;
    }

    public function typeLabel(): string
    {
        return $this->isClasswork() ? __('Classwork') : __('Homework');
    }

    public function isPastDue(?string $today = null): bool
    {
        $today ??= now()->toDateString();

        return $this->due_date !== null && $this->due_date->toDateString() < $today;
    }

    public function closesToday(?string $today = null): bool
    {
        return $this->due_date !== null && $this->due_date->toDateString() === ($today ?? now()->toDateString());
    }
}
