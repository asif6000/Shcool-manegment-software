<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The two screens a teacher lives on: the routine their timetable gives them and
 * the classes that come with it. Both read class_routines, so a period the office
 * has not put on the grid simply does not exist here - there is no second
 * assignment table to fall out of sync with the timetable.
 */
class TeacherWorkspaceService
{
    public function __construct(
        private readonly TeachingScope $teaching,
        private readonly HolidayService $holidays,
    ) {}

    /**
     * The school week (Saturday first) that holds the given date.
     *
     * @return array<string, mixed>
     */
    public function week(User $actor, Carbon $date): array
    {
        $session = $this->teaching->currentSession();
        $byDay = $this->periods($actor, $session)->groupBy(fn (ClassRoutine $period): int => (int) $period->day_of_week);

        $start = $date->copy()->startOfDay();
        $start->subDays(ClassRoutine::dayIndexOf($start));

        $days = [];

        for ($index = 0; $index < 7; $index++) {
            $day = $start->copy()->addDays($index);
            $holiday = $this->holidays->on($day);

            $days[] = [
                'index' => $index,
                'label' => ClassRoutine::dayLabel($index),
                'date' => $day,
                'classes' => $byDay->get($index, collect())->sortBy('start_time')->values(),
                'is_today' => $day->toDateString() === now()->toDateString(),
                'is_holiday' => $holiday !== null,
                'holiday' => $holiday,
            ];
        }

        return [
            'session' => $session,
            'days' => $days,
            'weekStart' => $start,
            'weekEnd' => $start->copy()->addDays(6),
            'isOwn' => $this->isOwn($actor),
            'periodCount' => $byDay->flatten(1)->count(),
            'subjectCount' => $byDay->flatten(1)->pluck('subject_id')->unique()->count(),
        ];
    }

    /**
     * One day, with each period tagged past, now or upcoming so the desk shows
     * where the teacher is in the day rather than a flat list.
     *
     * @return array<string, mixed>
     */
    public function day(User $actor, Carbon $date): array
    {
        $session = $this->teaching->currentSession();
        $index = ClassRoutine::dayIndexOf($date);

        $rows = $this->periods($actor, $session)
            ->filter(fn (ClassRoutine $period): bool => (int) $period->day_of_week === $index)
            ->sortBy('start_time')
            ->values()
            ->map(function (ClassRoutine $period) use ($date): array {
                $startsAt = Carbon::parse($date->toDateString().' '.$this->time($period->start_time));
                $endsAt = Carbon::parse($date->toDateString().' '.$this->time($period->end_time));
                $now = now();

                return [
                    'period' => $period,
                    'state' => match (true) {
                        $now->greaterThan($endsAt) => 'past',
                        $now->between($startsAt, $endsAt) => 'now',
                        default => 'upcoming',
                    },
                ];
            });

        return [
            'session' => $session,
            'date' => $date,
            'dayIndex' => $index,
            'holiday' => $this->holidays->on($date),
            'isWeekend' => ClassRoutine::isWeekend($date),
            'isToday' => $date->toDateString() === now()->toDateString(),
            'periods' => $rows,
            'isOwn' => $this->isOwn($actor),
        ];
    }

    /**
     * The classes, sections and subjects the actor's timetable adds up to, each
     * with the roll it teaches. Nothing here is entered twice: an assignment is a
     * period on the grid or it is not an assignment.
     *
     * @return array<string, mixed>
     */
    public function assignments(User $actor): array
    {
        $session = $this->teaching->currentSession();
        $periods = $this->periods($actor, $session);
        $rolls = $this->rolls($session);

        $classes = $periods
            ->groupBy(fn (ClassRoutine $period): string => $period->class_id.':'.$period->section_id)
            ->map(function (Collection $rows) use ($rolls): array {
                $first = $rows->first();

                return [
                    'class' => $first->schoolClass,
                    'section' => $first->section,
                    'subjects' => $rows->pluck('subject')->unique('id')->sortBy('name')->values(),
                    'periods' => $rows->count(),
                    'days' => $rows->pluck('day_of_week')->unique()->count(),
                    'students' => $rolls->get($first->class_id.':'.$first->section_id, 0),
                ];
            })
            ->sortBy([['class.level', 'asc'], ['section.name', 'asc'], ['class.name', 'asc']])
            ->values();

        $subjects = $periods
            ->groupBy('subject_id')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'subject' => $first->subject,
                    'groups' => $rows
                        ->sortBy([['schoolClass.level', 'asc'], ['section.name', 'asc']])
                        ->map(fn (ClassRoutine $period): string => trim(($period->schoolClass?->name ?? '—').' '.($period->section?->name ?? '')))
                        ->unique()
                        ->values(),
                    'periods' => $rows->count(),
                    'minutes' => $rows->sum(fn (ClassRoutine $period): int => $this->length($period)),
                ];
            })
            ->sortBy(fn (array $row): string => (string) ($row['subject']?->name ?? ''))
            ->values();

        return [
            'session' => $session,
            'classes' => $classes,
            'subjects' => $subjects,
            'isOwn' => $this->isOwn($actor),
            'periodCount' => $periods->count(),
            'studentCount' => $classes->sum('students'),
        ];
    }

    /**
     * Every period the actor answers for, with the names the screens render.
     *
     * @return Collection<int, ClassRoutine>
     */
    private function periods(User $actor, ?AcademicSession $session): Collection
    {
        if ($session === null) {
            return collect();
        }

        $query = ClassRoutine::query()
            ->with(['schoolClass', 'section', 'subject', 'teacher'])
            ->where('academic_session_id', $session->id);

        if ($this->teaching->isUnrestricted($actor)) {
            return $query->orderBy('day_of_week')->orderBy('start_time')->get();
        }

        $teacherId = $this->teaching->teacherId($actor);

        if ($teacherId === null) {
            return collect();
        }

        return $query
            ->where('teacher_id', $teacherId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Active enrollment counts keyed by "class:section", read in one pass so a
     * long timetable does not turn into a query per class.
     *
     * @return Collection<string, int>
     */
    private function rolls(?AcademicSession $session): Collection
    {
        if ($session === null) {
            return collect();
        }

        return Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->get(['class_id', 'section_id', 'student_id'])
            ->groupBy(fn (Enrollment $enrollment): string => $enrollment->class_id.':'.($enrollment->section_id ?? 0))
            ->map(fn (Collection $rows): int => $rows->pluck('student_id')->unique()->count());
    }

    private function isOwn(User $actor): bool
    {
        return ! $this->teaching->isUnrestricted($actor);
    }

    private function time(?string $value): string
    {
        return $value === null || $value === '' ? '00:00:00' : $value;
    }

    private function length(ClassRoutine $period): int
    {
        if ($period->start_time === null || $period->end_time === null) {
            return 0;
        }

        return (int) max(0, Carbon::parse($this->time($period->start_time))->diffInMinutes(Carbon::parse($this->time($period->end_time))));
    }
}
