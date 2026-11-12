<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLeave extends Model
{
    use BelongsToSchool;

    public const TYPES = ['sick', 'casual', 'family', 'emergency', 'other'];

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'student_id',
        'academic_session_id',
        'from_date',
        'to_date',
        'leave_type',
        'reason',
        'contact_phone',
        'status',
        'decision_notes',
        'decided_by',
        'decided_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function daysRequested(): int
    {
        if ($this->from_date === null || $this->to_date === null) {
            return 0;
        }

        return max(1, $this->from_date->diffInDays($this->to_date) + 1);
    }

    public function isDecided(): bool
    {
        return in_array($this->status, ['approved', 'rejected'], true);
    }

    public function typeLabel(): string
    {
        return __(ucfirst($this->leave_type));
    }
}
