<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceEventLog extends Model
{
    use BelongsToSchool;

    protected $table = 'attendance_events';

    protected $fillable = [
        'school_id',
        'attendance_device_id',
        'student_id',
        'student_attendance_id',
        'source',
        'external_ref',
        'device_user_id',
        'occurred_at',
        'received_at',
        'status',
        'reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'school_id' => 'integer',
            'attendance_device_id' => 'integer',
            'student_id' => 'integer',
            'student_attendance_id' => 'integer',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(StudentAttendance::class, 'student_attendance_id');
    }

    /**
     * Scope for today's events.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('occurred_at', today()->toDateString());
    }

    /**
     * Scope for applied (valid) events.
     */
    public function scopeApplied($query)
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    /**
     * Scope for failed/rejected events.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_REJECTED, self::STATUS_DUPLICATE]);
    }

    const STATUS_APPLIED = 'applied';
    const STATUS_DUPLICATE = 'duplicate';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_REJECTED = 'rejected';
}
