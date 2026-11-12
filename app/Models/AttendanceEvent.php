<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    use BelongsToSchool;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_DUPLICATE,
        self::STATUS_SKIPPED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => __('Applied'),
            self::STATUS_DUPLICATE => __('Duplicate'),
            self::STATUS_SKIPPED => __('Skipped'),
            default => __('Rejected'),
        };
    }

    public function reasonLabel(?string $studentName = null): string
    {
        $name = $studentName ?? $this->student?->full_name ?? __('the student');

        if ($this->status === self::STATUS_APPLIED) {
            return __('Attendance marked for :name.', ['name' => $name]);
        }

        return match ($this->reason) {
            'already_marked' => __('Attendance was already marked for :name today.', ['name' => $name]),
            'locked' => __('Attendance for :name was corrected by staff and was not overwritten.', ['name' => $name]),
            'protected_status' => __('Attendance for :name is on leave or excused and was not changed.', ['name' => $name]),
            'holiday' => __('This date is a holiday; attendance was not changed.'),
            'non_working_day' => __('This date is not a working day; attendance was not changed.'),
            'not_enrolled' => __(':name has no active enrollment for the current session.', ['name' => $name]),
            'out_of_scope' => __(':name is not in your assigned classes.', ['name' => $name]),
            'outside_session' => __('The scan date is outside the academic session.'),
            'future_date' => __('The scan date is in the future.'),
            'clock_skew' => __('The device clock is too far ahead; the event was refused.'),
            'stale_event' => __('The event is older than the accepted window; it was refused.'),
            'unknown_device_user' => __('The device user ID is not mapped to any student in this school.'),
            'unknown_source' => __('Unknown attendance method.'),
            default => __('The scan could not be applied.'),
        };
    }
}
