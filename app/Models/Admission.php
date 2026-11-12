<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Admission extends Model
{
    use BelongsToSchool;

    public const PENDING = 'pending';

    public const WAITLISTED = 'waitlisted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [self::PENDING, self::WAITLISTED, self::APPROVED, self::REJECTED];

    /**
     * Statuses that still occupy the applicant: they cannot submit again while
     * one of these is open for the same session.
     *
     * @var list<string>
     */
    public const OPEN_STATUSES = [self::PENDING, self::WAITLISTED];

    protected $fillable = [
        'academic_session_id',
        'applied_class_id',
        'application_no',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'address',
        'previous_school',
        'status',
        'student_id',
        'reviewed_by',
        'reviewed_at',
        'notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The portal account that submitted this application. Never mass assignable:
     * it is written by AdmissionService from the authenticated user only.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function appliedClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'applied_class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForApplicant(Builder $query, User $applicant): Builder
    {
        return $query->where($query->qualifyColumn('user_id'), $applicant->getKey());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), self::OPEN_STATUSES);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Guard against a path written outside the admissions upload directory.
     */
    public function hasPhoto(): bool
    {
        return is_string($this->photo_path)
            && Str::startsWith($this->photo_path, 'admissions/');
    }

    /**
     * Only the applicant-facing fields; internal notes stay private.
     */
    public function applicantSummary(): array
    {
        return [
            'application_no' => $this->application_no,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'student_code' => $this->student?->student_id,
            'admission_no' => $this->student?->admission_no,
        ];
    }
}
