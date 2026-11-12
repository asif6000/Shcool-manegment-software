<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\StaffAttendance;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffAttendanceService
{
    public const STATUSES = ['present', 'absent', 'late', 'leave'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HolidayService $holidays,
    ) {}

    public function holidayOn(Carbon $date): ?Holiday
    {
        return $this->holidays->on($date);
    }

    /**
     * The desk sheet for one day. Passing a list of teacher ids narrows it to the
     * staff a member of the office may write - a teacher is given only their own
     * row and never the employee register.
     *
     * @param  array<int, int>|null  $teacherIds
     * @return array{
     *     teachers: Collection<int, Teacher>,
     *     employees: Collection<int, Employee>,
     *     teacherAttendance: Collection<int, StaffAttendance>,
     *     employeeAttendance: Collection<int, StaffAttendance>
     * }
     */
    public function sheet(Carbon $date, ?array $teacherIds = null, bool $withEmployees = true): array
    {
        // Employee rows carry no teacher_id, so the filter also keeps them out of
        // a narrowed sheet.
        $records = StaffAttendance::query()
            ->where('attendance_date', $date->toDateString())
            ->when($teacherIds !== null, fn ($query) => $query->whereIn('teacher_id', $teacherIds))
            ->get();

        return [
            'teachers' => Teacher::query()
                ->where('status', 'active')
                ->when($teacherIds !== null, fn ($query) => $query->whereIn('id', $teacherIds))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'employees' => $withEmployees
                ? Employee::query()
                    ->where('status', 'active')
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get()
                : collect(),
            'teacherAttendance' => $records->whereNotNull('teacher_id')->keyBy('teacher_id'),
            'employeeAttendance' => $records->whereNotNull('employee_id')->keyBy('employee_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $ownTeacherIds
     */
    public function mark(User $actor, array $data, ?array $ownTeacherIds = null, bool $withEmployees = true): int
    {
        $schoolId = app(SchoolContext::class)->id();
        $date = Carbon::parse($data['attendance_date'])->startOfDay();
        $holiday = $this->holidays->on($date);

        if ($holiday !== null && ! (bool) ($data['override_holiday'] ?? false)) {
            throw ValidationException::withMessages([
                'attendance_date' => __(':date is a holiday (:name). Confirm the override to mark attendance.', [
                    'date' => $date->format('d M Y'),
                    'name' => $holiday->name,
                ]),
            ]);
        }

        $teacherStatuses = (array) ($data['teachers'] ?? []);
        $employeeStatuses = $withEmployees ? (array) ($data['employees'] ?? []) : [];

        // Ids outside the actor's own row are dropped here rather than trusted.
        if ($ownTeacherIds !== null) {
            $teacherStatuses = array_intersect_key($teacherStatuses, array_flip($ownTeacherIds));
        }

        $teacherIds = Teacher::query()
            ->whereIn('id', array_keys($teacherStatuses))
            ->where('status', 'active')
            ->pluck('id')
            ->all();
        $employeeIds = Employee::query()
            ->whereIn('id', array_keys($employeeStatuses))
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $teacherRows = [];
        $employeeRows = [];
        $counts = array_fill_keys(self::STATUSES, 0);

        foreach ($teacherIds as $teacherId) {
            $status = (string) $teacherStatuses[$teacherId];

            if (! in_array($status, self::STATUSES, true)) {
                continue;
            }

            $teacherRows[] = $this->row($schoolId, $date, $actor, $status, teacherId: (int) $teacherId);
            $counts[$status]++;
        }

        foreach ($employeeIds as $employeeId) {
            $status = (string) $employeeStatuses[$employeeId];

            if (! in_array($status, self::STATUSES, true)) {
                continue;
            }

            $employeeRows[] = $this->row($schoolId, $date, $actor, $status, employeeId: (int) $employeeId);
            $counts[$status]++;
        }

        if ($teacherRows === [] && $employeeRows === []) {
            throw ValidationException::withMessages([
                'teachers' => __('No active staff matched the submitted attendance.'),
            ]);
        }

        DB::transaction(function () use ($teacherRows, $employeeRows, $actor, $date, $counts, $holiday, $schoolId): void {
            if ($teacherRows !== []) {
                StaffAttendance::query()->upsert(
                    $teacherRows,
                    ['school_id', 'teacher_id', 'attendance_date'],
                    ['status', 'marked_by', 'updated_at'],
                );
            }

            if ($employeeRows !== []) {
                StaffAttendance::query()->upsert(
                    $employeeRows,
                    ['school_id', 'employee_id', 'attendance_date'],
                    ['status', 'marked_by', 'updated_at'],
                );
            }

            $this->audit->log(
                event: 'staff_attendance.marked',
                auditable: null,
                description: __('Staff attendance was marked for :date', ['date' => $date->format('d M Y')]),
                newValues: [
                    'date' => $date->toDateString(),
                    'counts' => $counts,
                    'holiday_override' => $holiday !== null,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );
        });

        return count($teacherRows) + count($employeeRows);
    }

    /**
     * @param  array<int, int>|null  $ownTeacherIds
     * @return array<string, int>
     */
    public function monthlySummary(Carbon $date, ?array $ownTeacherIds = null, bool $withEmployees = true): array
    {
        $counts = StaffAttendance::query()
            ->whereBetween('attendance_date', [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
            ])
            ->when($ownTeacherIds !== null, fn ($query) => $query->whereIn('teacher_id', $ownTeacherIds))
            ->when(! $withEmployees, fn ($query) => $query->whereNull('employee_id'))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $summary = array_fill_keys(self::STATUSES, 0);

        foreach ($counts as $status => $total) {
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) $total;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        ?int $schoolId,
        Carbon $date,
        User $actor,
        string $status,
        ?int $teacherId = null,
        ?int $employeeId = null,
    ): array {
        return [
            'school_id' => $schoolId,
            'teacher_id' => $teacherId,
            'employee_id' => $employeeId,
            'attendance_date' => $date->toDateString(),
            'status' => $status,
            'marked_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
