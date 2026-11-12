<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One teacher's sign-off of a class register for one day, and the decision the
 * office makes about it. It is a workflow record, not a copy of the attendance
 * rows: the counts are a snapshot taken when the register was submitted.
 */
class AttendanceSubmission extends Model
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
        'academic_session_id',
        'class_id',
        'section_id',
        'attendance_date',
        'status',
        'student_count',
        'present_count',
        'absent_count',
        'late_count',
        'leave_count',
        'excused_count',
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
            'attendance_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'student_count' => 'integer',
            'present_count' => 'integer',
            'absent_count' => 'integer',
            'late_count' => 'integer',
            'leave_count' => 'integer',
            'excused_count' => 'integer',
            'unmarked_count' => 'integer',
            'revision_count' => 'integer',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The register this submission covers. A whole-class submission (no section)
     * deliberately matches only section-less registers: narrowing to one section
     * is a different submission with its own decision.
     */
    public static function forRegister(
        int $sessionId,
        int $classId,
        ?int $sectionId,
        string $date,
    ): ?self {
        return static::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->when(
                $sectionId === null,
                fn (Builder $query) => $query->whereNull('section_id'),
                fn (Builder $query) => $query->where('section_id', $sectionId),
            )
            ->where('attendance_date', $date)
            ->first();
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isDecided(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }

    public function statusLabel(): string
    {
        return __([
            self::STATUS_SUBMITTED => 'Awaiting review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Sent back',
        ][$this->status] ?? ucfirst($this->status));
    }

    public function registerLabel(): string
    {
        return trim(($this->schoolClass?->name ?? '—').' '.($this->section?->name !== null
            ? __('Section :name', ['name' => $this->section->name])
            : __('all sections')));
    }
}
