<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\FeePaymentAllocation;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The accountant roster and what each collector has actually taken in.
 *
 * Every money figure here comes from FeeReportService, which is the same
 * accounting read the cashbook and the money reports use: cash received is the
 * sum of allocations on payments whose status is 'posted', so a pending, held
 * or reversed payment can never be counted as a collection. A figure is never
 * recomputed here with its own query, otherwise this screen and the reports
 * could disagree about the same takings.
 */
class AccountantCollectionService
{
    /** Any payment ever posted is older than this, so 'total' means total. */
    public const ALL_TIME = '2015-01-01';

    public function __construct(private readonly FeeReportService $reports) {}

    /**
     * Collectors of this school: everyone with the accountant role, plus anyone
     * who has actually posted a payment, so a collector who has since been
     * re-assigned still shows the money that passed through their hands.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function roster(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $schoolId = app(SchoolContext::class)->id();

        $page = $this->rosterQuery($request)->paginate($perPage)->withQueryString();
        $ids = collect($page->items())->map(fn (User $user): int => $user->id)->all();

        if ($ids === []) {
            return $page;
        }

        $today = $this->byCollector(Carbon::now()->startOfDay(), Carbon::now()->endOfDay());
        $month = $this->byCollector(Carbon::now()->startOfMonth(), Carbon::now()->endOfDay());
        $total = $this->byCollector(Carbon::parse(self::ALL_TIME), Carbon::now()->endOfDay());
        $students = $this->distinctStudents($ids);
        $employees = Employee::query()
            ->whereIn('user_id', $ids)
            ->where('school_id', $schoolId)
            ->get()
            ->keyBy('user_id');

        $page->getCollection()->transform(function (User $user) use ($today, $month, $total, $students, $employees): User {
            $id = $user->id;
            $employee = $employees[$id] ?? null;

            $user->setAttribute('collection_stats', [
                'today' => (float) ($today[$id]['received'] ?? 0),
                'month' => (float) ($month[$id]['received'] ?? 0),
                'total' => (float) ($total[$id]['received'] ?? 0),
                'payments' => (int) ($total[$id]['payments'] ?? 0),
                'students' => (int) ($students[$id] ?? 0),
            ]);
            $user->setAttribute('employee_code', $employee?->employee_code);
            $user->setAttribute('employee_phone', $employee?->phone);
            $user->setAttribute('employee_email', $employee?->email);
            $user->setAttribute('employee_designation', $employee?->designation);

            return $user;
        });

        return $page;
    }

    /**
     * The same list reduced to one set of numbers, over every row the filters
     * match rather than only the current page, so a footer total cannot disagree
     * with a list that is paginated or searched.
     *
     * @return array<string, int|float>
     */
    public function rosterTotals(Request $request): array
    {
        $ids = $this->rosterQuery($request)->pluck('users.id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return ['accountants' => 0, 'active' => 0, 'today' => 0.0, 'month' => 0.0, 'total' => 0.0, 'payments' => 0];
        }

        $today = $this->byCollector(Carbon::now()->startOfDay(), Carbon::now()->endOfDay());
        $month = $this->byCollector(Carbon::now()->startOfMonth(), Carbon::now()->endOfDay());
        $total = $this->byCollector(Carbon::parse(self::ALL_TIME), Carbon::now()->endOfDay());

        $sum = fn (array $rows): float => round(array_sum(array_map(
            fn (int $id): float => (float) ($rows[$id]['received'] ?? 0),
            $ids,
        )), 2);

        return [
            'accountants' => count($ids),
            'active' => (int) User::query()->whereIn('id', $ids)->where('is_active', true)->count(),
            'today' => $sum($today),
            'month' => $sum($month),
            'total' => $sum($total),
            'payments' => array_sum(array_map(
                fn (int $id): int => (int) ($total[$id]['payments'] ?? 0),
                $ids,
            )),
        ];
    }

    /**
     * The collection block on the admin dashboard. Same ranges as the roster
     * footer, plus the two counts that only the school as a whole can answer.
     *
     * @return array<string, int|float>
     */
    public function collectionSummary(): array
    {
        $now = Carbon::now();
        $schoolId = app(SchoolContext::class)->id();

        $today = $this->sumAcross($this->byCollector($now->copy()->startOfDay(), $now->copy()->endOfDay()));
        $month = $this->sumAcross($this->byCollector($now->copy()->startOfMonth(), $now->copy()->endOfDay()));
        $year = $this->sumAcross($this->byCollector($now->copy()->startOfYear(), $now->copy()->endOfDay()));
        $allTime = $this->byCollector(Carbon::parse(self::ALL_TIME), $now->copy()->endOfDay());

        return [
            'today' => $today,
            'month' => $month,
            'year' => $year,
            'total' => $this->sumAcross($allTime),
            'transactions' => array_sum(array_map(fn (array $row): int => (int) $row['payments'], $allTime)),
            'active_accountants' => (int) User::query()
                ->where('users.school_id', $schoolId)
                ->where('users.is_active', true)
                ->whereIn('users.id', fn (Builder $sub): Builder => $sub
                    ->select('model_has_roles.model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'accountant')
                    ->where('model_has_roles.model_type', '=', User::class))
                ->count(),
            'due' => round((float) FeeInvoice::query()
                ->whereIn('status', FeeInvoice::OPEN_STATUSES)
                ->sum('due_total'), 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sumAcross(array $rows): float
    {
        return round(array_sum(array_map(fn (array $row): float => (float) $row['received'], $rows)), 2);
    }

    /**
     * Who the roster is made of, before paging and before any money is read.
     */
    private function rosterQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $schoolId = app(SchoolContext::class)->id();
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $status = $request->query('status');

        return User::query()
            ->with(['roles:id,name'])
            ->where('users.school_id', $schoolId)
            ->where(function ($outer): void {
                $outer->whereIn(
                    'users.id',
                    fn (Builder $sub): Builder => $sub
                        ->select('model_has_roles.model_id')
                        ->from('model_has_roles')
                        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                        ->where('roles.name', 'accountant')
                        ->where('model_has_roles.model_type', '=', User::class)
                )->orWhereIn('users.id', fn (Builder $sub): Builder => $sub
                    ->select('fee_payments.received_by')
                    ->from('fee_payments')
                    ->where('fee_payments.status', 'posted')
                    ->whereNotNull('fee_payments.received_by'));
            })
            ->when($status === 'active', fn ($q): \Illuminate\Database\Eloquent\Builder => $q->where('users.is_active', true))
            ->when($status === 'inactive', fn ($q): \Illuminate\Database\Eloquent\Builder => $q->where('users.is_active', false))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $q->where(fn ($or): \Illuminate\Database\Eloquent\Builder => $or
                    ->where('users.name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhereIn('users.id', fn (Builder $sub): Builder => $sub
                        ->select('employees.user_id')
                        ->from('employees')
                        ->where('employees.employee_code', 'like', $like)));
            })
            ->orderByDesc('users.is_active')
            ->orderBy('users.name');
    }

    /**
     * One collector's own figures. Ranges are calendar-anchored so "this month"
     * on the detail page means the same thing as on the list.
     *
     * @return array<string, float|int>
     */
    public function totals(int $collectorId): array
    {
        $now = Carbon::now();

        $periods = [
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'total' => [Carbon::parse(self::ALL_TIME), $now->copy()->endOfDay()],
        ];

        $out = [];
        foreach ($periods as $key => [$from, $to]) {
            $rows = $this->byCollector($from, $to, $collectorId);
            $out[$key] = (float) ($rows[$collectorId]['received'] ?? 0);
        }

        return $out;
    }

    /**
     * Filters for one collector and one range, in the shape FeeReportService
     * expects. Building them here keeps the accounting rules in one place.
     *
     * @return array<string, mixed>
     */
    public function range(Carbon $from, Carbon $to, ?int $collectorId = null): array
    {
        return $this->reports->filters(Request::create('/', 'GET', array_filter([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'collector_id' => $collectorId,
        ], static fn (mixed $value): bool => $value !== null)));
    }

    /**
     * Posted cash per collector for one range, keyed by user id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byCollector(Carbon $from, Carbon $to, ?int $collectorId = null): array
    {
        return $this->reports->byCollector($this->range($from, $to, $collectorId))
            ->mapWithKeys(function (array $row): array {
                $id = $row['key'] === null ? null : (int) $row['key'];

                return $id === null ? [] : [$id => $row];
            })
            ->all();
    }

    /**
     * How many distinct students' invoices each collector has been paid against.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function distinctStudents(array $ids): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return FeePaymentAllocation::query()
            ->join('fee_payments', 'fee_payments.id', '=', 'fee_payment_allocations.fee_payment_id')
            ->join('fee_invoices', 'fee_invoices.id', '=', 'fee_payment_allocations.fee_invoice_id')
            ->where('fee_payments.status', 'posted')
            ->where('fee_payments.school_id', $schoolId)
            ->whereIn('fee_payments.received_by', $ids)
            ->groupBy('fee_payments.received_by')
            ->selectRaw('fee_payments.received_by as collector_id, COUNT(DISTINCT fee_invoices.student_id) as students')
            ->pluck('students', 'collector_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }
}
