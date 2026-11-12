<?php

namespace App\Services;

use App\Models\ClassRoutine;
use Illuminate\Support\Carbon;

/**
 * One place for the rules that decide which days count towards an attendance
 * percentage. The staff report and the student portal both use it, so a mark
 * cannot be 80% in one screen and 75% in another.
 */
class AttendancePolicy
{
    public function __construct(private readonly HolidayService $holidays) {}

    public function isWorkingDay(Carbon $date): bool
    {
        return in_array(
            ClassRoutine::dayIndexOf($date),
            (array) config('attendance.working_days', [1, 2, 3, 4, 5]),
            true,
        );
    }

    public function isCountable(Carbon $date): bool
    {
        return $this->isWorkingDay($date) && $this->holidays->on($date) === null;
    }

    /**
     * Percentage of the countable working days where the student attended.
     * Leave and excused days are staff decisions, not absences, so they are
     * removed from the denominator instead of counting against the student.
     *
     * @param  array<string, int>  $counts
     * @return array{attended: int, counted: int, rate: ?float}
     */
    public function percentage(array $counts): array
    {
        $attended = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);
        $counted = array_sum($counts)
            - (int) ($counts['leave'] ?? 0)
            - (int) ($counts['excused'] ?? 0);

        return [
            'attended' => $attended,
            'counted' => max(0, $counted),
            'rate' => $counted > 0 ? round(($attended / $counted) * 100, 1) : null,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{attended: int, counted: int, rate: ?float}
     */
    public function total(array $counts): array
    {
        return $this->percentage($counts);
    }
}
