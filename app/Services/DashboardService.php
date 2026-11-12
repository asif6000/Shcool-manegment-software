<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\AttendanceSubmission;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Holiday;
use App\Models\MarkSubmission;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $actor): array
    {
        $students = Student::query()->where('status', 'active')->count();
        $teachers = Teacher::query()->where('status', 'active')->count();
        $staff = Employee::query()->where('status', 'active')->count();
        $can = fn (string $permission): bool => $actor->can($permission);

        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $session = AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();

        return [
            'session' => $session,
            'today' => now(),
            'stats' => [
                'students' => $students,
                'teachers' => $teachers,
                'staff' => $staff,
                // The office counts teaching and non-teaching people together.
                'team' => $teachers + $staff,
                'classes' => SchoolClass::query()->where('is_active', true)->count(),
            ],
            'growth' => [
                'students' => $this->percentageChange($students, $this->headcountBefore(Student::query(), $lastMonthEnd)),
                'team' => $this->percentageChange(
                    $teachers + $staff,
                    $this->headcountBefore(Teacher::query(), $lastMonthEnd) + $this->headcountBefore(Employee::query(), $lastMonthEnd),
                ),
            ],
            'chart' => [
                'labels' => $this->monthLabels(),
                'current' => $this->monthlyAdmissions((int) now()->year),
                'currentYear' => (int) now()->year,
            ],
            // Widgets stay hidden unless the signed-in user holds the module
            // permission, so shared dashboards never leak finance or admissions.
            'attendance' => $can('attendance.report') ? $this->attendanceSnapshot() : null,
            // Registers a teacher has handed over and nobody has decided yet.
            'attendance_approvals' => $can('attendance.approve')
                ? AttendanceSubmission::query()->where('status', AttendanceSubmission::STATUS_SUBMITTED)->count()
                : null,
            // Marks sheets a teacher has handed over and nobody has decided yet.
            'marks_approvals' => $can('marks.approve')
                ? MarkSubmission::query()->where('status', MarkSubmission::STATUS_SUBMITTED)->count()
                : null,
            'admissions' => $can('admissions.view') ? $this->admissionSnapshot($lastMonthEnd) : null,
            'fees' => $can('fees.view')
                ? $this->feeSnapshot($thisMonthStart, $thisMonthEnd, $lastMonthStart, $lastMonthEnd)
                : null,
            'schedule' => $this->schedule($can),
            'notices' => $can('notices.view') ? $this->latestNotices() : null,
            'recentAdmissions' => $can('students.view') ? $this->recentAdmissions() : null,
            'activity' => $this->recentActivity($actor),
        ];
    }

    private function percentageChange(float $current, float $previous): float
    {
        if ($previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * How many of these people existed at the end of the comparison month.
     * Status is the current one, which is accurate enough for a month-on-month
     * trend without keeping a headcount history table.
     */
    private function headcountBefore(Builder $query, Carbon $lastMonthEnd): float
    {
        return (float) $query
            ->where('status', 'active')
            ->whereDate('created_at', '<=', $lastMonthEnd->toDateString())
            ->count();
    }

    /**
     * @return list<int>
     */
    private function monthlyAdmissions(int $year): array
    {
        $counts = Student::query()
            ->whereYear('admission_date', $year)
            ->selectRaw('MONTH(admission_date) as month, COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $data[] = (int) ($counts[$month] ?? 0);
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function monthLabels(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month): string => Carbon::create(now()->year, $month, 1, 0, 0, 0, config('app.timezone'))
                ->locale(app()->getLocale())
                ->translatedFormat('M'))
            ->all();
    }

    /**
     * @return array{marked: int, present: int, absent: int, leave: int, percent: float|null, yesterdayPercent: float|null, growth: float|null, classes: Collection<int, array<string, mixed>>}
     */
    private function attendanceSnapshot(): array
    {
        $today = now()->toDateString();
        $counts = $this->attendanceCounts($today);
        $yesterday = $this->attendanceCounts(now()->subDay()->toDateString());
        $percent = $this->attendancePercent($counts);
        $yesterdayPercent = $this->attendancePercent($yesterday);

        return [
            'marked' => $counts['marked'],
            'present' => $counts['present'],
            'absent' => $counts['absent'],
            'leave' => $counts['leave'],
            'percent' => $percent,
            'yesterdayPercent' => $yesterdayPercent,
            'growth' => $percent !== null && $yesterdayPercent !== null
                ? $this->percentageChange($percent, $yesterdayPercent)
                : null,
            'classes' => $this->attendanceByClass($today),
        ];
    }

    /**
     * @return array{marked: int, present: int, absent: int, leave: int}
     */
    private function attendanceCounts(string $date): array
    {
        $counts = StudentAttendance::query()
            ->where('attendance_date', $date)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);
        $leave = (int) ($counts['leave'] ?? 0) + (int) ($counts['excused'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);

        return [
            'marked' => $present + $absent + $leave,
            'present' => $present,
            'absent' => $absent,
            'leave' => $leave,
        ];
    }

    /**
     * @param  array{marked: int, present: int, absent: int, leave: int}  $counts
     */
    private function attendancePercent(array $counts): ?float
    {
        return $counts['marked'] > 0
            ? round(($counts['present'] / $counts['marked']) * 100, 1)
            : null;
    }

    /**
     * @return Collection<int, array{class: string, present: int, absent: int, leave: int, percent: float|null}>
     */
    private function attendanceByClass(string $date): Collection
    {
        $grouped = StudentAttendance::query()
            ->where('attendance_date', $date)
            ->selectRaw('class_id, status, COUNT(*) as total')
            ->groupBy('class_id', 'status')
            ->get()
            ->groupBy('class_id');

        if ($grouped->isEmpty()) {
            return collect();
        }

        $rows = collect();

        foreach (SchoolClass::query()->orderBy('level')->get() as $class) {
            $group = $grouped->get($class->id);

            if ($group === null) {
                continue;
            }

            $counts = $group->pluck('total', 'status');
            $present = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);
            $absent = (int) ($counts['absent'] ?? 0);
            $leave = (int) ($counts['leave'] ?? 0) + (int) ($counts['excused'] ?? 0);
            $marked = $present + $absent + $leave;

            $rows->push([
                'class' => $class->name,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave,
                'percent' => $marked > 0 ? round(($present / $marked) * 100, 1) : null,
            ]);
        }

        return $rows;
    }

    /**
     * @return array{pending: int, thisMonth: int, approvedThisMonth: int, growth: float}
     */
    private function admissionSnapshot(Carbon $lastMonthEnd): array
    {
        $pending = Admission::query()->open()->count();

        $pendingBefore = max(
            0,
            Admission::query()->whereDate('created_at', '<=', $lastMonthEnd->toDateString())->count()
                - Admission::query()->whereDate('reviewed_at', '<=', $lastMonthEnd->toDateString())->count(),
        );

        return [
            'pending' => $pending,
            'thisMonth' => Admission::query()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'approvedThisMonth' => Admission::query()
                ->where('status', Admission::APPROVED)
                ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'growth' => $this->percentageChange($pending, $pendingBefore),
        ];
    }

    /**
     * @return Collection<int, Student>
     */
    private function recentAdmissions(): Collection
    {
        return Student::query()
            ->with(['enrollments' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['schoolClass:id,name', 'section:id,name'])
                ->latest('enrolled_on')])
            ->orderByDesc('admission_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function feeSnapshot(Carbon $monthStart, Carbon $monthEnd, Carbon $lastMonthStart, Carbon $lastMonthEnd): array
    {
        $collected = (float) FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $previousCollected = (float) FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $expense = (float) CashTransaction::query()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $previousExpense = (float) CashTransaction::query()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])
            ->sum('amount');

        $openInvoices = FeeInvoice::query()->whereIn('status', FeeInvoice::OPEN_STATUSES);
        $outstanding = (float) (clone $openInvoices)->sum('due_total');

        // Invoice story for the office: how many bills exist, how they stand
        // and how much money is promised versus actually received.
        $billed = FeeInvoice::query()->where('status', '!=', 'cancelled');
        $invoiceStats = [
            'count' => (clone $billed)->count(),
            'unpaid' => (clone $billed)->where('status', 'issued')->count(),
            'partial' => (clone $billed)->where('status', 'partial')->count(),
            'paid' => (clone $billed)->whereIn('status', ['paid', 'settled'])->count(),
            'invoiced' => (float) (clone $billed)->sum('total'),
            'collected' => (float) (clone $billed)->sum('paid_total'),
        ];

        // Balance a month ago through the ledger identity: everything billed
        // up to then minus everything paid up to then.
        $billedBefore = (float) FeeInvoice::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('issue_date', '<=', $lastMonthEnd->toDateString())
            ->sum('total');
        $paidBefore = (float) FeePayment::query()
            ->posted()
            ->where('paid_at', '<=', $lastMonthEnd)
            ->sum('amount');
        $outstandingBefore = max(0.0, $billedBefore - $paidBefore);

        $year = (int) now()->year;
        $collectionByMonth = FeePayment::query()
            ->posted()
            ->whereYear('paid_at', $year)
            ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');
        $expenseByMonth = CashTransaction::query()
            ->where('type', 'expense')
            ->whereYear('transaction_date', $year)
            ->selectRaw('MONTH(transaction_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthCollection = [];
        $monthExpense = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthCollection[] = (float) ($collectionByMonth[$month] ?? 0);
            $monthExpense[] = (float) ($expenseByMonth[$month] ?? 0);
        }

        return [
            'collected' => $collected,
            'previousCollected' => $previousCollected,
            'growth' => $this->percentageChange($collected, $previousCollected),
            'outstanding' => $outstanding,
            'outstandingPrevious' => $outstandingBefore,
            'outstandingGrowth' => $this->percentageChange($outstanding, $outstandingBefore),
            'invoices' => $invoiceStats,
            'overdue' => (float) (clone $openInvoices)
                ->where('due_date', '<', now()->toDateString())
                ->sum('due_total'),
            'overdueInvoices' => (clone $openInvoices)
                ->where('due_date', '<', now()->toDateString())
                ->count(),
            'expense' => $expense,
            'previousExpense' => $previousExpense,
            'expenseGrowth' => $this->percentageChange($expense, $previousExpense),
            'net' => round($collected - $expense, 2),
            'recentPayments' => FeePayment::query()
                ->posted()
                ->with('student')
                ->orderByDesc('paid_at')
                ->limit(5)
                ->get(),
            'monthLabels' => $this->monthLabels(),
            'monthCollection' => $monthCollection,
            'monthExpense' => $monthExpense,
        ];
    }

    /**
     * Exams and school holidays merged into one agenda, soonest first.
     *
     * @param  callable(string): bool  $can
     * @return Collection<int, array{date: Carbon, title: string, meta: string, type: string, url: string|null}>|null
     */
    private function schedule(callable $can): ?Collection
    {
        $mayViewExams = $can('exams.view');
        $mayViewEvents = $can('academic.view');

        if (! $mayViewExams && ! $mayViewEvents) {
            return null;
        }

        $items = collect();

        if ($mayViewExams) {
            Exam::query()
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orderBy('start_date')
                ->withCount('papers')
                ->limit(3)
                ->get()
                ->each(function (Exam $exam) use ($items): void {
                    $items->push([
                        'date' => $exam->start_date,
                        'title' => $exam->name,
                        'meta' => trans_choice(':count paper|:count papers', $exam->papers_count, ['count' => $exam->papers_count]),
                        'type' => 'exam',
                        'url' => route('exams.show', $exam),
                    ]);
                });
        }

        if ($mayViewEvents) {
            Holiday::query()
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orderBy('start_date')
                ->limit(3)
                ->get()
                ->each(function (Holiday $holiday) use ($items): void {
                    $items->push([
                        'date' => $holiday->start_date,
                        'title' => $holiday->name,
                        'meta' => $holiday->description ?: __('School closed'),
                        'type' => 'event',
                        'url' => route('academic.index'),
                    ]);
                });
        }

        return $items
            ->sortBy(fn (array $item): int => $item['date']->getTimestamp())
            ->take(5)
            ->values();
    }

    /**
     * @return Collection<int, array{title: string, published_at: Carbon|null, priority: string, url: string}>
     */
    private function latestNotices(): Collection
    {
        return Notice::query()
            ->published()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Notice $notice): array => [
                'title' => $notice->title,
                'published_at' => $notice->published_at,
                'priority' => $this->noticePriority($notice),
                'url' => route('notices.index'),
            ]);
    }

    /**
     * The notice table has a pin flag, not a priority column. Pinned notices
     * are urgent; ones about to expire need attention; the rest are routine.
     */
    private function noticePriority(Notice $notice): string
    {
        if ($notice->is_pinned) {
            return 'high';
        }

        if ($notice->expires_at !== null && $notice->expires_at->lte(now()->addDays(7))) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @return Collection<int, AuditLog>
     */
    private function recentActivity(User $actor): Collection
    {
        return AuditLog::query()
            ->forActor($actor)
            ->with('user')
            ->latest('created_at')
            ->limit(5)
            ->get();
    }
}
