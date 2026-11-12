<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClassRoutine extends Model
{
    use BelongsToSchool;

    public const DAYS = [
        0 => 'Saturday',
        1 => 'Sunday',
        2 => 'Monday',
        3 => 'Tuesday',
        4 => 'Wednesday',
        5 => 'Thursday',
        6 => 'Friday',
    ];

    /** The weekly holidays of a Saturday-first school week: Friday and Saturday. */
    public const WEEKEND_INDICES = [0, 6];

    /**
     * Carbon counts weeks from Sunday while the routine table counts them from
     * Saturday, so every lookup converts through this helper.
     */
    public static function dayIndexOf(Carbon|string $date): int
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return (((int) $carbon->dayOfWeek) + 1) % 7;
    }

    public static function isWeekend(Carbon|string $date): bool
    {
        return in_array(self::dayIndexOf($date), self::WEEKEND_INDICES, true);
    }

    public static function dayLabel(int $index): string
    {
        return __(self::DAYS[$index] ?? '—');
    }

    public static function timeLabel(?string $time): string
    {
        if ($time === null || $time === '') {
            return '—';
        }

        return Carbon::parse($time)->translatedFormat('h:i A');
    }

    protected $fillable = [
        'academic_session_id',
        'class_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
    ];

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
}
