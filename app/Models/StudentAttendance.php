<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'student_id',
        'enrollment_id',
        'academic_session_id',
        'class_id',
        'section_id',
        'attendance_date',
        'status',
        'source',
        'source_ref',
        'late_minutes',
        'remarks',
        'locked_at',
        'correction_reason',
        'corrected_by',
        'corrected_at',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'late_minutes' => 'integer',
            'locked_at' => 'datetime',
            'corrected_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
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

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function sourceLabel(): string
    {
        return __(config('attendance.sources.'.$this->source, ucfirst((string) $this->source)));
    }
}
