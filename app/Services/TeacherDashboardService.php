<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Lesson;
use App\Models\MarkSubmission;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every number here is counted from the teacher's own timetable, classes and
 * records. A teacher with no periods assigned sees empty states rather than
 * school-wide figures, because those figures are not theirs to act on.
 */
class TeacherDashboardService
{
    public function __construct(
        private readonly TeachingScope $scope,
        private readonly AttendancePolicy $policy,
        private readonly HolidayService $holidays,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(User $actor): array
    {
        $today = now()->startOfDay();
        $session = $this->currentSession();
        $teacherId = $this->scope->teacherId($actor);
        $profile = $teacherId === null ? null : Teacher::query()->find($teacherId);

        $slots = $session === null
            ? collect()
            : ($this->scope->slots($actor, $session) ?? collect());

        $pairs = $slots
            ->map(fn (array $slot): array => [$slot['class_id'], $slot['section_id']])
            ->unique()
            ->values();

        return [
            'today' => $today,
            'session' => $session,
            'profile' => $profile,
            'slots' => $slots,
            'cards' => [
                'classes' => $pairs->count(),
                'subjects' => $slots->pluck('subject_id')->unique()->count(),
                'students' => $session === null ? 0 : $this->studentCount($session, $pairs),
            ],
            'timetable' => [
                'today' => $teacherId === null || $session === null
                    ? collect()
                    : $this->daySlots($session, $teacherId, $today),
                'week' => $teacherId === null || $session === null
                    ? collect()
                    : $this->weekCounts($session, $teacherId),
            ],
            'attendance' => $this->attendanceStatus($session, $pairs, $today),
            'lessons' => $this->lessons($actor, $session),
            'homework' => $this->homework($actor, $teacherId, $session, $pairs, $today),
            'marks' => $this->marks($actor, $session, $slots),
            'notices' => Notice::query()->published()
                ->whereIn('audience', ['all', 'teachers', 'staff'])
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(),
            'own' => [
                'staff_attendance' => $profile === null ? [] : $this->ownAttendance($profile, $today),
                'leave' => $profile === null
                    ? collect()
                    : $profile->leaves()->with('leaveType')->latest('start_date')->limit(3)->get(),
            ],
            'quick_actions' => $this->quickActions($actor),
        ];
    }

    private function currentSession(): ?AcademicSession
    {
        return AcademicSession::query()->orderByDesc('is_current')->orderByDesc('start_date')->first();
    }

    /**
     * @param  Collection<int, array{0: int, 1: int}>  $pairs
     */
    private function studentCount(AcademicSession $session, Collection $pairs): int
    {
        if ($pairs->isEmpty()) {
            return 0;
        }

        return Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->where($this->pairFilter($pairs))
            ->distinct()
            ->count('student_id');
    }

    /**
     * @param  Collection<int|string, array{0: int, 1: int}>  $pairs
     */
    private function pairFilter(Collection $pairs): Closure
    {
        return function (Builder $query) use ($pairs): void {
            foreach ($pairs as [$classId, $sectionId]) {
                $query->orWhere(function (Builder $inner) use ($classId, $sectionId): void {
                    $inner->where('class_id', $classId);

                    if ((int) $sectionId > 0) {
                        $inner->where('section_id', $sectionId);
                    } else {
                        $inner->whereNull('section_id');
                    }
                });
            }
        };
    }

    /**
     * @return Collection<int, ClassRoutine>
     */
    private function daySlots(AcademicSession $session, int $teacherId, Carbon $date): Collection
    {
        return ClassRoutine::query()
            ->with(['schoolClass', 'section', 'subject'])
            ->where('academic_session_id', $session->id)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', ClassRoutine::dayIndexOf($date))
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Periods per weekday, so the week strip shows the real load.
     *
     * @return Collection<int, int>
     */
    private function weekCounts(AcademicSession $session, int $teacherId): Collection
    {
        $counts = ClassRoutine::query()
            ->where('academic_session_id', $session->id)
            ->where('teacher_id', $teacherId)
            ->selectRaw('day_of_week, COUNT(*) as periods')
            ->groupBy('day_of_week')
            ->pluck('periods', 'day_of_week');

        return collect(range(0, 6))
            ->mapWithKeys(fn (int $day): array => [$day => (int) ($counts[$day] ?? 0)]);
    }

    /**
     * Today's marking state for each class-section pair on the timetable.
     *
     * @param  Collection<int, array{0: int, 1: int}>  $pairs
     * @return array{countable: bool, marked: int, total: int, pending: Collection<int, string>, closed: string|null}
     */
    private function attendanceStatus(?AcademicSession $session, Collection $pairs, Carbon $today): array
    {
        $status = ['countable' => false, 'marked' => 0, 'total' => $pairs->count(), 'pending' => collect(), 'to_submit' => 0, 'closed' => null];

        if ($session === null || $pairs->isEmpty()) {
            return $status;
        }

        if (! $this->policy->isWorkingDay($today)) {
            $status['closed'] = __('No attendance today');

            return $status;
        }

        $holiday = $this->holidays->on($today);

        if ($holiday !== null) {
            $status['closed'] = $holiday->name;

            return $status;
        }

        $status['countable'] = true;

        $classNames = SchoolClass::query()->pluck('name', 'id');
        $sectionNames = Section::query()->pluck('name', 'id');
        $pending = [];

        $handedOver = AttendanceSubmission::query()
            ->where('academic_session_id', $session->id)
            ->where('attendance_date', $today->toDateString())
            ->get(['class_id', 'section_id'])
            ->map(fn (AttendanceSubmission $submission): string => $submission->class_id.':'.$submission->section_id)
            ->flip();

        // One read for the whole timetable instead of an exists() per pair.
        $markedToday = StudentAttendance::query()
            ->where('academic_session_id', $session->id)
            ->where('attendance_date', $today->toDateString())
            ->where($this->pairFilter($pairs))
            ->select('class_id', 'section_id')
            ->distinct()
            ->get()
            ->map(fn (StudentAttendance $row): string => (int) $row->class_id.':'.(int) $row->section_id)
            ->flip();

        $status['to_submit'] = 0;

        foreach ($pairs as [$classId, $sectionId]) {
            if ($markedToday->has((int) $classId.':'.(int) $sectionId)) {
                $status['marked']++;

                // Marked but never handed over is the part a teacher still owes.
                if (! $handedOver->has($classId.':'.$sectionId) && ! $handedOver->has($classId.':')) {
                    $status['to_submit']++;
                }

                continue;
            }

            $pending[] = [
                'label' => ($classNames[$classId] ?? __('Class')).' · '.($sectionNames[$sectionId] ?? '—'),
                'class_id' => (int) $classId,
                'section_id' => (int) $sectionId > 0 ? (int) $sectionId : null,
            ];
        }

        $status['pending'] = collect($pending);

        return $status;
    }

    /**
     * @param  Collection<int, array{0: int, 1: int}>  $pairs
     * @return array{due: Collection<int, Homework>, pending_reviews: int}
     */
    /**
     * The lesson desk counts: what exists for the classes on this timetable,
     * how much of it is still a draft, and what was touched this week.
     *
     * @return array<string, int>
     */
    private function lessons(User $actor, ?AcademicSession $session): array
    {
        if (! $actor->can('lessons.view')) {
            return ['total' => 0, 'draft' => 0, 'updated' => 0];
        }

        $byStatus = $this->scope->constrainPeriods($actor, $session, Lesson::query())
            ->selectRaw('status, count(*) aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->all();

        return [
            'total' => (int) array_sum($byStatus),
            'draft' => (int) ($byStatus[Lesson::DRAFT] ?? 0),
            'updated' => $this->scope->constrainPeriods($actor, $session, Lesson::query())
                ->where('updated_at', '>=', now()->subWeek())
                ->count(),
        ];
    }

    private function homework(User $actor, ?int $teacherId, ?AcademicSession $session, Collection $pairs, Carbon $today): array
    {
        if ($session === null || $pairs->isEmpty()) {
            return ['due' => collect(), 'pending_reviews' => 0];
        }

        // Two teachers can hold the same period, so the period alone does not
        // say whose homework is waiting. This card counts only the actor's own.
        $authorFilter = function (Builder $query) use ($actor, $teacherId): void {
            $query->where(function (Builder $own) use ($actor, $teacherId): void {
                $own->where('created_by', $actor->id);

                if ($teacherId !== null) {
                    $own->orWhere('teacher_id', $teacherId);
                }
            });
        };

        $due = Homework::query()
            ->with(['schoolClass', 'section', 'subject'])
            ->withCount('submissions')
            ->where('academic_session_id', $session->id)
            // A cancelled assignment is no work waiting to be marked.
            ->where('status', '!=', Homework::CANCELLED)
            ->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->where($this->pairFilter($pairs))
            ->where($authorFilter)
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $pendingReviews = HomeworkSubmission::query()
            ->whereHas('homework', function (Builder $query) use ($session, $pairs, $authorFilter): void {
                $query->where('academic_session_id', $session->id)
                    ->where($this->pairFilter($pairs))
                    ->where($authorFilter);
            })
            ->whereIn('status', ['submitted', 'late'])
            ->count();

        return ['due' => $due, 'pending_reviews' => $pendingReviews];
    }

    /**
     * The result panel opens on the teacher's own draft papers, so it lists the
     * same papers the dashboard counts rather than a second query of its own.
     *
     * @return array<string, mixed>
     */
    public function results(User $actor): array
    {
        $session = $this->currentSession();

        return $this->marks(
            $actor,
            $session,
            $session === null ? collect() : ($this->scope->slots($actor, $session) ?? collect()),
        );
    }

    /**
     * Draft papers the teacher is responsible for and how many students on each
     * paper still have no mark.
     *
     * @param  Collection<int, array<string, mixed>>  $slots
     * @return array{papers: int, entered: int, to_submit: int, list: Collection<int, array<string, mixed>>, exams: Collection<int, array<string, mixed>>}
     */
    private function marks(User $actor, ?AcademicSession $session, Collection $slots): array
    {
        if ($session === null || $slots->isEmpty()) {
            return ['papers' => 0, 'entered' => 0, 'to_submit' => 0, 'list' => collect(), 'exams' => collect()];
        }

        $tuples = $slots
            ->map(fn (array $slot): array => [$slot['class_id'], $slot['subject_id']])
            ->unique()
            ->values();

        $papers = ExamSubject::query()
            ->with(['exam', 'schoolClass', 'subject'])
            ->whereHas('exam', fn (Builder $query): Builder => $query
                ->where('academic_session_id', $session->id)
                ->where('status', 'draft'))
            ->where(function (Builder $query) use ($tuples): void {
                foreach ($tuples as [$classId, $subjectId]) {
                    $query->orWhere(function (Builder $inner) use ($classId, $subjectId): void {
                        $inner->where('class_id', $classId)->where('subject_id', $subjectId);
                    });
                }
            })
            ->get();

        $entered = ExamMark::query()
            ->where('entered_by', $actor->id)
            ->whereIn('exam_subject_id', $papers->pluck('id'))
            ->where(fn (Builder $query): Builder => $query->whereNotNull('marks_obtained')->orWhere('is_absent', true))
            ->count();

        $paperIds = $papers->pluck('id');

        $enrolledByClass = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereIn('class_id', $papers->pluck('class_id')->unique())
            ->selectRaw('class_id, COUNT(*) as total')
            ->groupBy('class_id')
            ->pluck('total', 'class_id');

        $markedByPaper = ExamMark::query()
            ->whereIn('exam_subject_id', $paperIds)
            ->where(fn (Builder $query): Builder => $query->whereNotNull('marks_obtained')->orWhere('is_absent', true))
            ->selectRaw('exam_subject_id, COUNT(*) as total')
            ->groupBy('exam_subject_id')
            ->pluck('total', 'exam_subject_id');

        // Marked in full but never handed over is the part a teacher still owes.
        $handedOver = MarkSubmission::query()
            ->whereIn('exam_subject_id', $paperIds)
            ->pluck('exam_subject_id')
            ->unique()
            ->flip();

        $list = $papers->map(function (ExamSubject $paper) use ($enrolledByClass, $markedByPaper, $handedOver): array {
            $enrolled = (int) ($enrolledByClass[$paper->class_id] ?? 0);
            $marked = (int) ($markedByPaper[$paper->id] ?? 0);

            return [
                'paper' => $paper,
                'enrolled' => $enrolled,
                'marked' => $marked,
                'remaining' => max(0, $enrolled - $marked),
                'handed_over' => $handedOver->has($paper->id),
            ];
        })->values();

        return [
            'papers' => $papers->count(),
            'entered' => $entered,
            'to_submit' => $list->filter(
                fn (array $row): bool => $row['remaining'] === 0 && $row['marked'] > 0 && ! $row['handed_over'],
            )->count(),
            'list' => $list->values(),
            // The result panel opens on an exam, so the picker is the same draft
            // papers grouped by the exam they belong to.
            'exams' => $papers
                ->groupBy('exam_id')
                ->map(fn (Collection $rows): array => [
                    'exam' => $rows->first()->exam,
                    'papers' => $rows->count(),
                ])
                ->values(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function ownAttendance(Teacher $profile, Carbon $today): array
    {
        $counts = StaffAttendance::query()
            ->where('teacher_id', $profile->id)
            ->whereBetween('attendance_date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'leave' => (int) ($counts['leave'] ?? 0),
            'working_days' => $this->workingDaysInMonth($today),
        ];
    }

    private function workingDaysInMonth(Carbon $date): int
    {
        $cursor = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        $days = 0;

        while ($cursor->lte($end)) {
            if ($this->policy->isWorkingDay($cursor)) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Only actions the signed-in account is actually allowed to open. A panel
     * target stays on the dashboard; a route target is a real page, such as the
     * printable monthly report.
     *
     * @return list<array{label: string, route: string, icon: string, class: string}>
     */
    private function quickActions(User $actor): array
    {
        $actions = [];

        if ($actor->can('attendance.mark')) {
            $actions[] = [
                'label' => __('Mark attendance'),
                'route' => route('dashboard', ['panel' => 'attendance']),
                'icon' => 'bi-check2-square',
                'class' => 'is-sky',
            ];
        }

        if ($actor->can('attendance.report')) {
            $actions[] = [
                'label' => __('Attendance report'),
                'route' => route('attendance.students.report'),
                'icon' => 'bi-calendar3',
                'class' => 'is-teal',
            ];
        }

        if ($actor->can('homework.manage')) {
            $actions[] = [
                'label' => __('Add class work / homework'),
                'route' => route('dashboard', ['panel' => 'homework', 'reopen' => route('teacher.homework.create')]),
                'icon' => 'bi-journal-text',
                'class' => 'is-amber',
            ];
        }

        if ($actor->can('materials.manage')) {
            $actions[] = [
                'label' => __('Share study material'),
                'route' => route('dashboard', ['panel' => 'materials', 'reopen' => route('materials.create')]),
                'icon' => 'bi-journal-bookmark',
                'class' => 'is-teal',
            ];
        }

        if ($actor->can('marks.enter')) {
            $actions[] = [
                'label' => __('Enter marks'),
                'route' => route('dashboard', ['panel' => 'results']),
                'icon' => 'bi-pencil-square',
                'class' => 'is-purple',
            ];
        }

        if ($actor->can('timetable.view')) {
            $actions[] = [
                'label' => __('My routine'),
                'route' => route('teacher.routine'),
                'icon' => 'bi-clock-history',
                'class' => 'is-sky',
            ];

            $actions[] = [
                'label' => __('My classes'),
                'route' => route('teacher.classes'),
                'icon' => 'bi-diagram-3',
                'class' => 'is-purple',
            ];
        }

        return $actions;
    }
}
