<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AttendanceRule extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'name',
        'start_time',
        'end_time',
        'late_threshold_minutes',
        'very_late_threshold_minutes',
        'window_hours',
        'duplicate_interval_seconds',
        'allow_holiday_attendance',
        'skip_weekends',
        'working_days',
        'send_sms_on_present',
        'send_sms_on_late',
        'send_sms_to_guardian',
        'max_checks_per_day',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'start_time' => 'datetime:H:i:s',
            'end_time' => 'datetime:H:i:s',
            'late_threshold_minutes' => 'integer',
            'very_late_threshold_minutes' => 'integer',
            'window_hours' => 'float',
            'duplicate_interval_seconds' => 'integer',
            'allow_holiday_attendance' => 'boolean',
            'skip_weekends' => 'boolean',
            'working_days' => 'array',
            'send_sms_on_present' => 'boolean',
            'send_sms_on_late' => 'boolean',
            'send_sms_to_guardian' => 'boolean',
            'max_checks_per_day' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * Get the default rule for a school.
     */
    public static function getDefaultForSchool(int $schoolId): ?self
    {
        return static::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();
    }

    /**
     * Is the current time within working hours?
     */
    public function isWorkingTime(Carbon $time): bool
    {
        if ($this->skip_weekends && in_array((int)$time->dayOfWeek, [6, 0], true)) {
            return false;
        }

        $timeStr = $time->format('H:i:s');
        $startStr = $this->start_time instanceof Carbon ? $this->start_time->format('H:i:s') : (string)($this->start_time ?? '08:30:00');
        $endStr = $this->end_time instanceof Carbon ? $this->end_time->format('H:i:s') : (string)($this->end_time ?? '15:00:00');

        return $timeStr >= $startStr && $timeStr <= $endStr;
    }

    /**
     * Calculate how late (in minutes) someone is based on check-in time.
     */
    public function calculateLateMinutes(Carbon $checkInTime): int
    {
        $startStr = $this->start_time instanceof Carbon ? $this->start_time->format('H:i:s') : (string)($this->start_time ?? '08:30:00');
        $startTime = Carbon::parse($checkInTime->toDateString() . ' ' . $startStr, $checkInTime->timezone);

        $diffSeconds = $checkInTime->diffInSeconds($startTime, false);

        return (int)ceil($diffSeconds / 60);
    }

    /**
     * Determine if an attendance record should be marked as late.
     */
    public function isLate(Carbon $occurredAt): bool
    {
        return $this->calculateLateMinutes($occurredAt) > 0
            && $this->calculateLateMinutes($occurredAt) <= $this->late_threshold_minutes;
    }

    /**
     * Check if an event occurred during a holiday period.
     */
    public function isHoliday(Carbon $date): bool
    {
        // This would query your holidays table
        if (! class_exists(\App\Models\AcademicHoliday::class)) {
            return false;
        }

        return \App\Models\AcademicHoliday::query()
            ->whereDate('date', $date->toDateString())
            ->exists();
    }

    /**
     * Validate attendance timestamp against window constraints.
     */
    public function isValidWindow(Carbon $occurredAt): bool
    {
        if (! $this->isWorkingTime($occurredAt)) {
            return false;
        }

        $hoursAgo = now()->subHours($this->window_hours);
        $hoursForward = now()->addHours($this->window_hours);

        return !$occurredAt->lt($hoursAgo) && !$occurredAt->gt($hoursForward);
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(): string
    {
        return $this->is_active ? __('Active') : __('Inactive');
    }
}
