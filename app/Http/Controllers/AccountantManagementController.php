<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\PurgeAccountantRequest;
use App\Http\Requests\Admin\ResetAccountantPasswordRequest;
use App\Http\Requests\Admin\StoreAccountantRequest;
use App\Http\Requests\Admin\UpdateAccountantRequest;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountantCollectionService;
use App\Services\AccountantPurgeService;
use App\Services\FeeReportService;
use App\Services\UserService;
use App\Support\RoleHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Accountant management and collection monitoring.
 *
 * The roster answers the question the money reports cannot: which account
 * holder stands behind a collector, and how much has actually passed through
 * their hands. Nothing here keeps its own ledger — every figure is read through
 * FeeReportService, the same accounting read the cashbook uses, so a collection
 * shown on this screen and the same collection in Collector activity are one
 * number by construction.
 */
class AccountantManagementController extends Controller
{
    public function __construct(
        private readonly AccountantCollectionService $accountants,
        private readonly FeeReportService $reports,
        private readonly UserService $users,
        private readonly AccountantPurgeService $purge,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('accountants.view');

        $accountants = $this->accountants->roster($request);
        $actor = $request->user();

        // Only the rows this admin may delete carry a confirmation dialog, so a
        // lower-ranked manager never sees an action the policy would refuse.
        $purges = $accountants->getCollection()
            ->filter(fn (User $user): bool => $actor->can('delete', $user))
            ->mapWithKeys(fn (User $user): array => [$user->id => $this->purge->preview($user)])
            ->all();

        return view('accountants.index', [
            'accountants' => $accountants,
            'totals' => $this->accountants->rosterTotals($request),
            'purges' => $purges,
            'search' => is_string($request->query('q')) ? trim($request->query('q')) : '',
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : '',
            'canExport' => $request->user()->can('reports.export'),
            'canCreate' => $request->user()->can('create', User::class)
                && RoleHierarchy::canAssignRole($request->user(), 'accountant'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);
        abort_unless(RoleHierarchy::canAssignRole($request->user(), 'accountant'), 403, __('This action is unauthorized.'));

        return view('accountants.create');
    }

    public function store(StoreAccountantRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['role'] = 'accountant';
        // A new finance desk login always starts active; the list is where it
        // gets paused or resumed.
        $data['is_active'] = true;

        $accountant = $this->users->create($request->user(), $data);

        return redirect()
            ->route('accountants.index')
            ->with('success', __('Accountant :name was created and notified.', ['name' => $accountant->name]));
    }

    public function edit(Request $request, User $accountant): View
    {
        $this->assertAccountant($accountant);
        $this->authorize('update', $accountant);

        return view('accountants.edit', [
            'accountant' => $accountant,
            'purge' => $this->purge->preview($accountant),
        ]);
    }

    public function update(UpdateAccountantRequest $request, User $accountant): RedirectResponse
    {
        $this->assertAccountant($accountant);

        // validated() carries the name and the sign-in number only, so the
        // role, the password and the email column are left exactly as they are.
        $this->users->update($request->user(), $accountant, $request->validated());

        return redirect()
            ->route('accountants.index')
            ->with('success', __('The details for :name were updated.', ['name' => $accountant->name]));
    }

    /**
     * Pause or resume the login. A deactivated accountant keeps every receipt
     * they posted, but can no longer sign in.
     */
    public function toggleStatus(Request $request, User $accountant): RedirectResponse
    {
        $this->assertAccountant($accountant);

        $updated = $this->users->toggleStatus($request->user(), $accountant);

        return back()->with(
            'success',
            $updated->is_active
                ? __(':name was activated and can sign in again.', ['name' => $updated->name])
                : __(':name was deactivated and signed out.', ['name' => $updated->name]),
        );
    }

    public function resetPassword(ResetAccountantPasswordRequest $request, User $accountant): RedirectResponse
    {
        $this->assertAccountant($accountant);

        // The shared updater marks the account as needing a new password and
        // drops the sessions and tokens opened with the old one.
        $this->users->update($request->user(), $accountant, $request->validated());

        return back()->with(
            'success',
            __('The password was reset. :name must choose a new one at the next sign-in.', ['name' => $accountant->name]),
        );
    }

    /**
     * The admin asked for the account holder and everything posted in their
     * name to disappear, not merely to be paused. AccountantPurgeService backs
     * up the records, reopens the invoices they had settled and removes the
     * login, profile, sessions and tokens in one transaction.
     */
    public function destroy(PurgeAccountantRequest $request, User $accountant): RedirectResponse
    {
        $this->assertAccountant($accountant);

        $result = $this->purge->purge($request->user(), $accountant);

        return redirect()
            ->route('accountants.index')
            ->with('success', __(
                ':name and every associated record were permanently deleted. Backup: :backup',
                ['name' => $accountant->name, 'backup' => $result['backup']],
            ));
    }

    /**
     * These URLs are the finance desk's own management screens; another role's
     * record is not editable from here.
     */
    private function assertAccountant(User $accountant): void
    {
        abort_unless($accountant->hasRole('accountant'), 404);
    }

    /**
     * One account holder's own record. Someone else's row needs the roster
     * permission, so an accountant can always open their own page and never
     * another's.
     */
    public function show(Request $request, User $accountant): View
    {
        $actor = $request->user();

        abort_unless($actor->school_id === $accountant->school_id, 403, __('This action is unauthorized.'));

        if (! $actor->can('accountants.view')) {
            abort_unless($actor->is($accountant), 403, __('This action is unauthorized.'));
        }

        $now = Carbon::now();
        $year = $this->accountants->range($now->copy()->startOfYear(), $now->copy()->endOfDay(), $accountant->id);
        $allTime = $this->accountants->range(Carbon::parse(AccountantCollectionService::ALL_TIME), $now->copy()->endOfDay(), $accountant->id);

        return view('accountants.show', [
            'accountant' => $accountant->load(['roles']),
            'employee' => $accountant->employee,
            'totals' => $this->accountants->totals($accountant->id),
            'summary' => $this->summary($year),
            'lifetime_payments' => (int) $this->reports->summary($allTime)['payments'],
            'byMonth' => $this->reports->byPaymentMonth($allTime)->take(-12)->values(),
            'byStudent' => $this->reports->byStudent($year),
            'payments' => $this->reports->payments($this->historyFilters($request, $accountant->id, $allTime)),
            'audit' => $actor->can('audit.view')
                ? AuditLog::query()
                    ->forActor($actor)
                    ->where('user_id', $accountant->id)
                    ->where('event', 'like', 'fee_payment.%')
                    ->latest('created_at')
                    ->paginate(15)
                    ->withQueryString()
                : null,
            'canManage' => $actor->can('accountants.manage'),
            'filters' => $this->historyFilters($request, $accountant->id, $allTime),
        ]);
    }

    /**
     * Day-wise collection for a range, split by who took the money.
     */
    public function daily(Request $request): View
    {
        $this->requirePermission('accountants.view');

        if (! $request->filled('from') && ! $request->filled('to')) {
            $request->merge([
                'from' => Carbon::now()->startOfMonth()->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ]);
        }

        $f = $this->reports->filters($request);

        return view('accountants.daily', $this->filterViewData($f, 'accountants.daily') + [
            'summary' => $this->summary($f),
            'byDay' => $this->reports->byDay($f),
            'byCollector' => $this->reports->byCollector($f),
            'split' => $this->reports->byCollectorDay($f)->groupBy('day'),
        ]);
    }

    /**
     * Month-wise collection, on both grains the daily screen uses: the month the
     * money arrived and the month it was billed for.
     */
    public function monthly(Request $request): View
    {
        $this->requirePermission('accountants.view');

        if (! $request->filled('from') && ! $request->filled('to') && ! $request->filled('month')) {
            $request->merge([
                'from' => Carbon::now()->copy()->startOfYear()->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ]);
        }

        $f = $this->reports->filters($request);

        return view('accountants.monthly', $this->filterViewData($f, 'accountants.monthly') + [
            'summary' => $this->summary($f),
            'byPaymentMonth' => $this->reports->byPaymentMonth($f),
            'byFeeMonth' => $this->reports->byFeeMonth($f),
            'byCollector' => $this->reports->byCollector($f),
            'months' => $this->monthMatrix($f),
        ]);
    }

    /**
     * One export route for the three screens of this module, so a spreadsheet
     * always carries the rows exactly as the page above it shows them.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('accountants.view');
        $this->requirePermission('reports.export');

        $scope = in_array($request->query('scope'), ['daily', 'monthly'], true) ? (string) $request->query('scope') : 'roster';

        if ($scope === 'roster') {
            return $this->exportRoster($request);
        }

        $f = $this->reports->filters($request);

        return $scope === 'daily'
            ? $this->csv('daily-collection', function ($out) use ($f): void {
                fputcsv($out, [__('Date'), __('Accountant'), __('Payments'), __('Cash received (BDT)')]);
                foreach ($this->reports->byCollectorDay($f) as $row) {
                    fputcsv($out, [
                        $row['day'],
                        $row['collector'],
                        $row['payments'],
                        number_format($row['received'], 2, '.', ''),
                    ]);
                }
            })
            : $this->csv('monthly-collection', function ($out) use ($f): void {
                fputcsv($out, [__('Month'), __('Payments'), __('Invoices'), __('Standard fee (BDT)'), __('Cash received (BDT)')]);
                foreach ($this->reports->byPaymentMonth($f) as $row) {
                    fputcsv($out, [
                        $row['label'],
                        $row['payments'],
                        $row['invoices'],
                        number_format($row['standard_fee'], 2, '.', ''),
                        number_format($row['received'], 2, '.', ''),
                    ]);
                }

                $split = $this->reports->byCollectorDay($f)
                    ->groupBy(fn (array $row): string => substr($row['day'], 0, 7))
                    ->map(fn (Collection $rows): Collection => $rows
                        ->groupBy(fn (array $row): string => (string) $row['collector_id'])
                        ->map(fn (Collection $group): array => [
                            'collector' => $group->first()['collector'],
                            'received' => round((float) $group->sum('received'), 2),
                            'payments' => (int) $group->sum('payments'),
                        ]));

                fputcsv($out, []);
                fputcsv($out, [__('Month'), __('Accountant'), __('Payments'), __('Cash received (BDT)')]);
                foreach ($this->reports->byPaymentMonth($f) as $month) {
                    foreach ($split->get((string) $month['key'], collect()) as $part) {
                        fputcsv($out, [
                            $month['label'],
                            $part['collector'],
                            $part['payments'],
                            number_format($part['received'], 2, '.', ''),
                        ]);
                    }
                }
            });
    }

    private function exportRoster(Request $request): StreamedResponse
    {
        $rows = $this->accountants->roster($request, 5000);

        return $this->csv('accountant-collections', function ($out) use ($rows): void {
            fputcsv($out, [
                __('Name'), __('Employee ID'), __('Mobile number'), __('Phone'),
                __('Status'), __('Total collection (BDT)'), __('Today (BDT)'), __('This month (BDT)'),
                __('Payments'), __('Students collected from'), __('Roles'),
            ]);

            foreach ($rows as $user) {
                $stats = $user->collection_stats;
                fputcsv($out, [
                    $user->name,
                    $user->getAttribute('employee_code') ?? '',
                    $user->mobile_number ?? '',
                    $user->getAttribute('employee_phone') ?: ($user->phone ?? ''),
                    $user->is_active ? __('Active') : __('Inactive'),
                    number_format((float) $stats['total'], 2, '.', ''),
                    number_format((float) $stats['today'], 2, '.', ''),
                    number_format((float) $stats['month'], 2, '.', ''),
                    (int) $stats['payments'],
                    (int) $stats['students'],
                    $user->roles->pluck('name')->implode(' / '),
                ]);
            }
        });
    }

    private function csv(string $name, callable $callback): StreamedResponse
    {
        return response()->streamDownload(function () use ($callback): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $callback($out);
            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The transaction list on the detail page: the accountant's own collections,
     * narrowed by whatever dates the query string asks for. With no dates in the
     * URL the whole history is listed, which is what "collection history" means
     * on a profile screen.
     *
     * @param  array<string, mixed>  $allTime
     * @return array<string, mixed>
     */
    private function historyFilters(Request $request, int $collectorId, array $allTime): array
    {
        if (! $request->filled('from') && ! $request->filled('to')) {
            return $allTime;
        }

        return $this->accountants->range(
            $this->dateOrToday($request->query('from')),
            $this->dateOrToday($request->query('to')),
            $collectorId,
        );
    }

    /**
     * Month × collector, so a monthly sheet shows the accountant split under each
     * month instead of two unrelated tables. Derived from the one collector-day
     * read, never re-aggregated per month.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<string, array<string, mixed>>
     */
    private function monthMatrix(array $f): Collection
    {
        $split = $this->reports->byCollectorDay($f)
            ->groupBy(fn (array $row): string => substr($row['day'], 0, 7))
            ->map(fn (Collection $rows): Collection => $rows
                ->groupBy(fn (array $row): string => (string) $row['collector_id'])
                ->map(fn (Collection $group): array => [
                    'collector' => $group->first()['collector'],
                    'received' => round((float) $group->sum('received'), 2),
                    'payments' => (int) $group->sum('payments'),
                ])
                ->sortByDesc('received'));

        return $this->reports->byPaymentMonth($f)
            ->map(fn (array $row): array => $row + ['collectors' => $split->get((string) $row['key'], collect())])
            ->keyBy('key');
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function summary(array $f): array
    {
        return $this->reports->summary($f) + ['rate' => $this->reports->collectionRate($f)];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function filterViewData(array $f, string $route): array
    {
        return [
            'route' => $route,
            'filters' => $f,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name']),
            'sections' => Section::query()->with('schoolClass:id,name')->orderBy('name')->get(['id', 'class_id', 'name']),
            'feeTypes' => FeeType::query()->orderBy('name')->get(['id', 'name']),
            'collectors' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'students' => Student::query()->active()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'student_id']),
            'methods' => FeePayment::METHODS,
        ];
    }
}
