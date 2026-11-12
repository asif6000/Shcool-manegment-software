<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Holiday;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Notice;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeave;
use App\Models\StudentResult;
use App\Models\StudyMaterial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentPortalService
{
    public const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'leave', 'excused'];

    public function __construct(private readonly AttendancePolicy $attendancePolicy) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Student $student): array
    {
        $session = AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();

        $enrollment = $student->currentEnrollment();
        $today = now();

        $monthSummary = $this->attendanceSummary($student, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $sessionSummary = $session !== null
            ? $this->attendanceSummary($student, Carbon::parse($session->start_date), Carbon::parse($session->end_date))
            : null;

        $outstanding = (float) $student->feeInvoices()->outstanding()->sum('due_total');

        return [
            'session' => $session,
            'enrollment' => $enrollment,
            'monthSummary' => $monthSummary,
            'sessionSummary' => $sessionSummary,
            'attendanceSeries' => $this->attendanceSeries($student),
            'todayClasses' => $this->classesOn($enrollment, $session?->id, $today),
            'homeworkDue' => $this->homeworkDueSoon($student, $enrollment, $session?->id),
            'recentMaterials' => $this->recentMaterials($enrollment, $session?->id),
            'upcomingExams' => $this->upcomingExams($session?->id),
            'notices' => $this->notices(),
            'pendingLeaves' => $student->leaves()->pending()->count(),
            'fees' => [
                'outstanding' => $outstanding,
                'unpaid_invoices' => $student->feeInvoices()->outstanding()->count(),
                'next_due_date' => $student->feeInvoices()->outstanding()->min('due_date'),
                'paid_to_date' => (float) $student->feePayments()->posted()->sum('amount'),
            ],
            'recentInvoices' => $student->feeInvoices()
                ->with('academicSession')
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
            'recentPayments' => $student->feePayments()
                ->posted()
                ->with('receipt')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
            'recentAttendance' => $student->attendance()
                ->orderByDesc('attendance_date')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Attendance rate for the last few calendar months, oldest first, so the
     * portal can plot the trend without a query per month.
     *
     * @return list<array{label: string, period: string, percentage: float|null, recorded: int}>
     */
    public function attendanceSeries(Student $student, int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths(max(1, $months) - 1);
        $end = now()->endOfMonth();

        $rows = $student->attendance()
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(attendance_date, '%Y-%m') as period, status, COUNT(*) as total")
            ->groupBy('period', 'status')
            ->get();

        $buckets = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $buckets[$cursor->format('Y-m')] = ['label' => $cursor->translatedFormat('M'), 'period' => $cursor->format('Y-m'), 'present' => 0, 'recorded' => 0];
            $cursor->addMonth();
        }

        foreach ($rows as $row) {
            $period = (string) $row->period;

            if (! isset($buckets[$period])) {
                continue;
            }

            $buckets[$period]['recorded'] += (int) $row->total;

            if (in_array($row->status, ['present', 'late'], true)) {
                $buckets[$period]['present'] += (int) $row->total;
            }
        }

        return array_values(array_map(fn (array $bucket): array => [
            'label' => $bucket['label'],
            'period' => $bucket['period'],
            'recorded' => $bucket['recorded'],
            'percentage' => $bucket['recorded'] > 0 ? round(($bucket['present'] / $bucket['recorded']) * 100, 1) : null,
        ], $buckets));
    }

    /**
     * @return Collection<int, ClassRoutine>
     */
    public function classesOn(?Enrollment $enrollment, ?int $sessionId, Carbon $date): Collection
    {
        if ($enrollment === null) {
            return new Collection;
        }

        return ClassRoutine::query()
            ->where('class_id', $enrollment->class_id)
            ->where('section_id', $enrollment->section_id)
            ->where('day_of_week', ClassRoutine::dayIndexOf($date))
            ->when($sessionId !== null, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->with(['subject', 'teacher'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * The school week as the routine table stores it: Saturday first.
     *
     * @return list<array{index: int, label: string, date: Carbon, classes: Collection<int, ClassRoutine>, is_today: bool, is_holiday: bool, holiday: ?Holiday}>
     */
    public function weekRoutine(?Enrollment $enrollment, ?int $sessionId, Carbon $date): array
    {
        $weekStart = $date->copy()->startOfDay();
        // The school week opens on Saturday, so walk back by the school day index.
        $weekStart->subDays(ClassRoutine::dayIndexOf($weekStart));

        $days = [];

        for ($index = 0; $index < 7; $index++) {
            $day = $weekStart->copy()->addDays($index);
            $days[] = [
                'index' => $index,
                'label' => ClassRoutine::dayLabel($index),
                'date' => $day,
                'classes' => $this->classesOn($enrollment, $sessionId, $day),
                'is_today' => $day->toDateString() === now()->toDateString(),
                'is_holiday' => false,
                'holiday' => null,
            ];
        }

        if ($sessionId !== null) {
            $holidays = Holiday::query()
                ->where('academic_session_id', $sessionId)
                ->where('start_date', '<=', $days[6]['date'])
                ->where('end_date', '>=', $weekStart)
                ->get();

            foreach ($days as $position => $day) {
                $match = $holidays->first(fn (Holiday $holiday): bool => $this->spansDate($day['date'], $holiday));

                if ($match !== null) {
                    $days[$position]['is_holiday'] = true;
                    $days[$position]['holiday'] = $match;
                }
            }
        }

        return $days;
    }

    private function spansDate(Carbon $day, Holiday $holiday): bool
    {
        if ($holiday->start_date === null) {
            return false;
        }

        $end = $holiday->end_date ?? $holiday->start_date;

        return $day->toDateString() >= $holiday->start_date->toDateString()
            && $day->toDateString() <= $end->toDateString();
    }

    /**
     * The first and last day of a range that are visible inside a month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function clampRange(?Carbon $from, ?Carbon $to, Carbon $start, Carbon $end): array
    {
        $first = ($from !== null && $from->gt($start)) ? $from->copy() : $start->copy();
        $last = ($to !== null && $to->lt($end)) ? $to->copy() : $end->copy();

        return [$first->startOfDay(), $last->startOfDay()];
    }

    /**
     * Exams, holidays, notices and homework deadlines for one calendar month,
     * grouped by date so the view only has to render buckets.
     *
     * @return array{month: Carbon, events: array<string, list<array<string, mixed>>>, holidays: Collection<int, Holiday>, exams: Collection<int, Exam>, attendance: array<string, string>, leaves: Collection<int, StudentLeave>}
     */
    public function calendarMonth(Student $student, ?Enrollment $enrollment, ?int $sessionId, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $holidays = Holiday::query()
            ->where(fn ($query) => $query
                ->whereNull('academic_session_id')
                ->orWhere('academic_session_id', $sessionId ?? 0))
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('start_date')
            ->get();

        $exams = Exam::query()
            ->where('status', 'published')
            ->when($sessionId !== null, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('start_date')
            ->get();

        $homework = Homework::query()
            ->forEnrollment($enrollment, $sessionId)
            ->with('subject')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('due_date')
            ->get();

        $notices = Notice::query()
            ->published()
            ->whereIn('audience', ['all', 'students'])
            ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->latest('published_at')
            ->limit(20)
            ->get();

        $events = [];

        foreach ($holidays as $holiday) {
            [$cursor, $last] = $this->clampRange($holiday->start_date, $holiday->end_date, $start, $end);

            while ($cursor->lte($last)) {
                $events[$cursor->toDateString()][] = [
                    'type' => 'holiday',
                    'label' => $holiday->name,
                    'meta' => __(ucfirst($holiday->type ?? 'holiday')),
                    'icon' => 'bi-sunrise',
                    'url' => null,
                ];
                $cursor->addDay();
            }
        }

        foreach ($exams as $exam) {
            [$cursor, $last] = $this->clampRange($exam->start_date, $exam->end_date, $start, $end);

            while ($cursor->lte($last)) {
                $events[$cursor->toDateString()][] = [
                    'type' => 'exam',
                    'label' => $exam->name,
                    'meta' => __(ucfirst(str_replace('_', ' ', $exam->exam_type))),
                    'icon' => 'bi-journal-check',
                    'url' => route('student.results.index'),
                ];
                $cursor->addDay();
            }
        }

        foreach ($homework as $entry) {
            $due = $entry->due_date?->toDateString();

            if ($due !== null) {
                $events[$due][] = [
                    'type' => 'homework',
                    'label' => $entry->title,
                    'meta' => $entry->subject?->name,
                    'icon' => 'bi-pencil-square',
                    'url' => route('student.homework.show', $entry->id),
                ];
            }
        }

        foreach ($notices as $notice) {
            $day = $notice->published_at?->toDateString();

            if ($day !== null) {
                $events[$day][] = [
                    'type' => 'notice',
                    'label' => $notice->title,
                    'meta' => $notice->audienceLabel(),
                    'icon' => 'bi-megaphone',
                    'url' => route('public.notices.show', $notice->id),
                ];
            }
        }

        return [
            'month' => $start,
            'events' => $events,
            'holidays' => $holidays,
            'exams' => $exams,
            'attendance' => $student->attendance()
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                ->get(['attendance_date', 'status'])
                ->mapWithKeys(fn (StudentAttendance $row): array => [$row->attendance_date->toDateString() => $row->status])
                ->all(),
            'leaves' => $student->leaves()
                ->whereIn('status', ['pending', 'approved'])
                ->where('from_date', '<=', $end->toDateString())
                ->where('to_date', '>=', $start->toDateString())
                ->get(),
        ];
    }

    /**
     * @return array{rows: Collection<int, Homework>, statuses: array<int, string>}
     */
    public function homework(Student $student, ?Enrollment $enrollment, ?int $sessionId, string $filter): array
    {
        $rows = Homework::query()
            ->forEnrollment($enrollment, $sessionId)
            ->with(['subject', 'teacher', 'academicSession'])
            ->when($filter === 'pending', fn ($query) => $query->where('due_date', '>=', now()->toDateString())
                ->whereNotIn('id', fn ($inner) => $inner->select('homework_id')
                    ->from('homework_submissions')
                    ->where('student_id', $student->id)))
            ->when($filter === 'submitted', fn ($query) => $query->whereIn('id', fn ($inner) => $inner->select('homework_id')
                ->from('homework_submissions')
                ->where('student_id', $student->id)))
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return [
            'rows' => $rows,
            'statuses' => $this->submissionStatuses($student, $rows->items()),
        ];
    }

    /**
     * Homework due within the next week that has not been reviewed yet.
     *
     * @return array{rows: Collection<int, Homework>, statuses: array<int, string>}
     */
    public function homeworkDueSoon(Student $student, ?Enrollment $enrollment, ?int $sessionId, int $limit = 4): array
    {
        $rows = Homework::query()
            ->forEnrollment($enrollment, $sessionId)
            ->with('subject')
            ->whereBetween('due_date', [now()->toDateString(), now()->copy()->addDays(7)->toDateString()])
            ->orderBy('due_date')
            ->limit($limit)
            ->get();

        return [
            'rows' => $rows,
            'statuses' => $this->submissionStatuses($student, $rows),
        ];
    }

    /**
     * @param  array<Homework>|Collection<int, Homework>  $rows
     * @return array<int, string>
     */
    private function submissionStatuses(Student $student, array|Collection $rows): array
    {
        $ids = collect($rows)->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return HomeworkSubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('homework_id', $ids)
            ->pluck('status', 'homework_id')
            ->all();
    }

    /**
     * Homework addressed to this student, so a foreign id never resolves.
     */
    public function homeworkDetail(Student $student, ?Enrollment $enrollment, ?int $sessionId, int $homeworkId): array
    {
        $homework = Homework::query()
            ->forEnrollment($enrollment, $sessionId)
            ->with(['subject', 'teacher', 'schoolClass', 'section', 'academicSession'])
            ->findOrFail($homeworkId);

        $submission = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $student->id)
            ->first();

        return [
            'homework' => $homework,
            'submission' => $submission,
        ];
    }

    /**
     * @return array{rows: Collection<int, StudentLeave>, pending: int}
     */
    public function leaves(Student $student): array
    {
        $rows = $student->leaves()
            ->with(['decider', 'academicSession'])
            ->orderByDesc('from_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15));

        return [
            'rows' => $rows,
            'pending' => $student->leaves()->pending()->count(),
        ];
    }

    /**
     * @return Collection<int, Exam>
     */
    public function upcomingExams(?int $sessionId, int $limit = 4): Collection
    {
        return Exam::query()
            ->where('status', 'published')
            ->whereDate('start_date', '>=', now()->toDateString())
            ->when($sessionId !== null, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->orderBy('start_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Notice>
     */
    public function notices(int $limit = 5): Collection
    {
        return Notice::query()
            ->published()
            ->whereIn('audience', ['all', 'students'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The newest classwork or file the student's own class has been given.
     *
     * @return Collection<int, StudyMaterial>
     */
    public function recentMaterials(?Enrollment $enrollment, ?int $sessionId, int $limit = 5): Collection
    {
        return StudyMaterial::query()
            ->forEnrollment($enrollment, $sessionId)
            ->with(['subject', 'creator'])
            ->orderByDesc('work_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{counts: array<string, int>, recorded: int, attended: int, percentage: float|null}
     */
    public function attendanceSummary(Student $student, Carbon $from, Carbon $to): array
    {
        $rows = $student->attendance()
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get(['attendance_date', 'status']);

        $counts = array_fill_keys(self::ATTENDANCE_STATUSES, 0);

        foreach ($rows as $row) {
            if (! array_key_exists($row->status, $counts)) {
                continue;
            }

            if (! $this->attendancePolicy->isCountable(Carbon::parse($row->attendance_date))) {
                continue;
            }

            $counts[$row->status]++;
        }

        $percentage = $this->attendancePolicy->percentage($counts);

        return [
            'counts' => $counts,
            'recorded' => array_sum($counts),
            'attended' => $percentage['attended'],
            'percentage' => $percentage['rate'],
        ];
    }

    /**
     * @return array{month: Carbon, rows: Collection<int, StudentAttendance>, summary: array<string, mixed>, calendar: array<string, mixed>}
     */
    public function attendanceForMonth(Student $student, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $rows = $student->attendance()
            ->with(['schoolClass', 'section'])
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('attendance_date')
            ->get();

        return [
            'month' => $start,
            'rows' => $rows,
            'summary' => $this->attendanceSummary($student, $start, $end),
            'calendar' => $this->attendanceGrid($rows, $start, $end),
        ];
    }

    /**
     * One cell per calendar day so the register can be read as a month grid,
     * with the school week opening on Saturday.
     *
     * @param  Collection<int, StudentAttendance>  $rows
     * @return array<string, mixed>
     */
    private function attendanceGrid(Collection $rows, Carbon $start, Carbon $end): array
    {
        $byDate = $rows->filter(fn (StudentAttendance $entry): bool => $entry->attendance_date !== null)
            ->keyBy(fn (StudentAttendance $entry): string => $entry->attendance_date->toDateString());

        $holidays = Holiday::query()
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $entry = $byDate->get($cursor->toDateString());
            $holiday = $holidays->first(fn (Holiday $item): bool => $this->spansDate($cursor, $item));

            $days[$cursor->toDateString()] = [
                'date' => $cursor->copy(),
                'status' => $entry?->status,
                'remarks' => $entry?->remarks,
                'class_name' => $entry?->schoolClass?->name,
                'section_name' => $entry?->section?->name,
                'holiday' => $holiday?->name,
                'is_today' => $cursor->isToday(),
                'is_weekend' => ClassRoutine::isWeekend($cursor),
                'is_future' => $cursor->gt(now()->startOfDay()),
            ];

            $cursor->addDay();
        }

        return [
            'days' => $days,
            'lead' => ClassRoutine::dayIndexOf($start),
            'weekdays' => array_keys(ClassRoutine::DAYS),
            'recorded' => $byDate->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fees(Student $student, string $status): array
    {
        $status = in_array($status, FeeInvoice::STATUSES, true) ? $status : '';

        $base = $student->feeInvoices()->where('status', '!=', 'cancelled');

        return [
            'status' => $status,
            'invoices' => $student->feeInvoices()
                ->with(['academicSession'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'total' => (float) (clone $base)->sum('total'),
                'paid' => (float) (clone $base)->sum('paid_total'),
                'due' => (float) (clone $base)->sum('due_total'),
                'overdue' => (clone $base)->outstanding()->where('due_date', '<', now()->toDateString())->count(),
            ],
            'payments' => $student->feePayments()
                ->posted()
                ->with(['receipt', 'allocations.invoice'])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * @return array{results: Collection<int, StudentResult>}
     */
    public function results(Student $student): array
    {
        return [
            'results' => $student->results()
                ->with(['exam.academicSession'])
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    /**
     * @return array{result: StudentResult, snapshot: array<string, mixed>}
     */
    public function result(Student $student, int $examId): array
    {
        $result = $student->results()
            ->with(['exam.academicSession'])
            ->where('status', 'published')
            ->where('exam_id', $examId)
            ->firstOrFail();

        abort_if($result->snapshot === null, 404, __('The result snapshot is not available.'));

        return [
            'result' => $result,
            'snapshot' => $result->snapshot,
        ];
    }

    public function invoice(Student $student, int $invoiceId): FeeInvoice
    {
        return $student->feeInvoices()
            ->with([
                'academicSession',
                'items.feeType',
                'discounts',
                'allocations.payment.receipt',
            ])
            ->findOrFail($invoiceId);
    }

    public function payment(Student $student, int $paymentId): FeePayment
    {
        $payment = $student->feePayments()
            ->with(['receipt', 'allocations.invoice'])
            ->findOrFail($paymentId);

        abort_if($payment->receipt === null, 404, __('Receipt not found.'));

        return $payment;
    }
}
