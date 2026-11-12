<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One teacher's sign-off of a marks sheet — a paper, optionally narrowed to one
 * section — and the decision the office makes about it. The counts are a
 * snapshot taken at submission, not a live view of the marks rows.
 */
class MarkSubmission extends Model
{
    use BelongsToSchool;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'exam_id',
        'exam_subject_id',
        'section_id',
        'status',
        'enrolled_count',
        'entered_count',
        'absent_count',
        'unmarked_count',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'revision_count',
        'last_correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'enrolled_count' => 'integer',
            'entered_count' => 'integer',
            'absent_count' => 'integer',
            'unmarked_count' => 'integer',
            'revision_count' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class, 'exam_subject_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The sheet this submission covers. A whole-paper submission (no section)
     * matches only section-less sheets: one section is a different submission
     * with its own decision.
     */
    public static function forSheet(int $paperId, ?int $sectionId): ?self
    {
        return static::query()
            ->where('exam_subject_id', $paperId)
            ->when(
                $sectionId === null,
                fn (Builder $query) => $query->whereNull('section_id'),
                fn (Builder $query) => $query->where('section_id', $sectionId),
            )
            ->first();
    }

    /**
     * Every handed-over sheet a write to this paper touches. A whole-paper
     * submission is affected by any change, because its snapshot covers them
     * all; a section submission only by a change to that section.
     *
     * @param  list<int>  $touchedSectionIds
     * @return Collection<int, self>
     */
    public static function affectedByWrite(int $paperId, array $touchedSectionIds): Collection
    {
        return static::query()
            ->where('exam_subject_id', $paperId)
            ->where(
                fn (Builder $query): Builder => $query
                    ->whereNull('section_id')
                    ->when(
                        $touchedSectionIds !== [],
                        fn (Builder $or): Builder => $or->orWhereIn('section_id', $touchedSectionIds)
                    )
            )
            ->get();
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function statusLabel(): string
    {
        return __([
            self::STATUS_SUBMITTED => 'Awaiting review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Sent back',
        ][$this->status] ?? ucfirst($this->status));
    }

    public function sheetLabel(): string
    {
        $paper = $this->paper;

        return trim(
            ($paper?->schoolClass?->name ?? '—')
            .' '.($paper?->subject?->name ?? '')
            .' · '.($this->section?->name !== null
                ? __('Section :name', ['name' => $this->section->name])
                : __('all sections'))
        );
    }
}
