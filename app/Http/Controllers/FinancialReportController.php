<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\CashLedgerService;
use App\Services\FeeReportService;
use App\Support\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The reports module. Everything about fee money here comes from
 * FeeReportService, which is the same accounting read the cashbook uses, so a
 * report can never invent a second ledger. Standard fee and cash received are
 * separate figures; no report here calculates, shows or carries a balance
 * owed.
 */
class FinancialReportController extends Controller
{
    public function __construct(
        private readonly FeeReportService $reports,
        private readonly CashLedgerService $ledger,
    ) {}

    /**
     * These screens read the ledger, so they are opened by a money-handling
     * permission rather than the generic reports permission, which teaching
     * staff also hold for their own result reports.
     */
    private function requireFinanceAccess(): void
    {
        $this->requireAnyPermission(['fees.view', 'cash.view', 'expenses.view']);
    }

    public function index(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);
        $summary = $this->reports->summary($f);

        $ledger = CashTransaction::query()
            ->whereBetween('transaction_date', [$f['from']->toDateString(), $f['to']->toDateString()]);

        return view('reports.index', $this->filterViewData($request, $f, 'reports.index') + [
            'summary' => $summary,
            'totals' => [
                'income' => round((float) (clone $ledger)->where('amount', '>', 0)->sum('amount'), 2),
                'expense' => round(abs((float) (clone $ledger)->where('amount', '<', 0)->sum('amount')), 2),
            ],
        ]);
    }

    public function collection(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);

        // A printed register is the whole range, not one page of it, so ?all=1
        // widens the page and the print button asks for exactly that.
        $register = $this->reports->register($f, $request->filled('all') ? 1000 : 50);

        return view('reports.collection', $this->filterViewData($request, $f, 'reports.collection') + [
            'summary' => $this->summary($f),
            'register' => $register,
            'feeSummary' => $this->reports->feeTypeSummary($f),
            'school' => School::query()->find(app(SchoolContext::class)->id()),
            'byDay' => $this->reports->byDay($f),
            'byMethod' => $this->reports->byMethod($f),
            'byPaymentMonth' => $this->reports->byPaymentMonth($f),
            'reversals' => $this->reports->reversals($f),
            'held' => $this->reports->heldPayments($f),
        ]);
    }

    public function students(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);

        return view('reports.students', $this->filterViewData($request, $f, 'reports.students') + [
            'summary' => $this->summary($f),
            'rows' => $this->page($this->reports->byStudent($f), $request),
            'payments' => $this->reports->payments($f),
        ]);
    }

    public function classes(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);

        return view('reports.classes', $this->filterViewData($request, $f, 'reports.classes') + [
            'summary' => $this->summary($f),
            'byClass' => $this->reports->byClass($f),
            'bySection' => $this->reports->bySection($f),
        ]);
    }

    public function feeTypes(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);

        return view('reports.fee-types', $this->filterViewData($request, $f, 'reports.fee-types') + [
            'summary' => $this->summary($f),
            'rows' => $this->reports->byFeeType($f),
        ]);
    }

    public function methods(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);

        return view('reports.methods', $this->filterViewData($request, $f, 'reports.methods') + [
            'summary' => $this->summary($f),
            'rows' => $this->reports->byMethod($f)->map(fn (array $row): array => $row + [
                'channel' => $this->ledger->channelForMethod(is_string($row['key']) ? $row['key'] : null),
            ]),
        ]);
    }

    public function collectors(Request $request): View
    {
        $this->requireFinanceAccess();

        $f = $this->reports->filters($request);
        $actor = $request->user();

        return view('reports.collectors', $this->filterViewData($request, $f, 'reports.collectors') + [
            'summary' => $this->summary($f),
            'rows' => $this->reports->byCollector($f),
            'audit' => $actor !== null && $actor->can('audit.view')
                ? AuditLog::query()
                    ->forActor($actor)
                    ->where('event', 'like', 'fee_payment.%')
                    ->whereBetween('created_at', [$f['from']->toDateTimeString(), $f['to']->copy()->endOfDay()->toDateTimeString()])
                    ->with('user')
                    ->latest('created_at')
                    ->paginate(25)
                    ->withQueryString()
                : null,
        ]);
    }

    public function expenses(Request $request): View
    {
        $this->requireFinanceAccess();

        [$from, $to] = $this->range($request);

        $expenses = Expense::query()
            ->where('status', 'paid')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->with('category')
            ->get();

        return view('reports.expenses', [
            'from' => $from,
            'to' => $to,
            'byCategory' => $expenses->groupBy(fn (Expense $expense): string => $expense->category?->name ?? __('Uncategorised'))
                ->map(fn ($group): array => [
                    'count' => $group->count(),
                    'amount' => round((float) $group->sum('amount'), 2),
                ]),
            'byChannel' => $expenses->groupBy(fn (Expense $expense): string => $this->ledger
                ->channelForMethod($expense->payment_method))
                ->map(fn ($group): float => round((float) $group->sum('amount'), 2)),
            'total' => round((float) $expenses->sum('amount'), 2),
            'count' => $expenses->count(),
            'pending' => round((float) Expense::query()->where('status', 'pending')
                ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->sum('amount'), 2),
        ]);
    }

    public function statement(Request $request): View
    {
        $this->requireFinanceAccess();

        $start = $request->filled('year')
            ? Carbon::create((int) $request->query('year'), 1, 1)->startOfYear()
            : now()->startOfYear();

        $rows = [];
        $totals = ['opening' => 0.0, 'income' => 0.0, 'expense' => 0.0, 'net' => 0.0];

        $opening = round((float) CashTransaction::query()
            ->where('transaction_date', '<', $start->toDateString())
            ->sum('amount'), 2);

        $totals['opening'] = $opening;
        $running = $opening;

        for ($month = 0; $month < 12; $month++) {
            $monthStart = $start->copy()->addMonthsNoOverflow($month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $income = round((float) CashTransaction::query()
                ->where('amount', '>', 0)
                ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount'), 2);

            $expense = round(abs((float) CashTransaction::query()
                ->where('amount', '<', 0)
                ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount')), 2);

            $net = round($income - $expense, 2);
            $running = round($running + $net, 2);

            $rows[] = [
                'month' => $monthStart->locale(app()->getLocale())->translatedFormat('F Y'),
                'income' => $income,
                'expense' => $expense,
                'net' => $net,
                'running' => $running,
            ];

            $totals['income'] = round($totals['income'] + $income, 2);
            $totals['expense'] = round($totals['expense'] + $expense, 2);
            $totals['net'] = round($totals['net'] + $net, 2);
        }

        return view('reports.statement', [
            'year' => $start->year,
            'rows' => collect($rows),
            'totals' => $totals,
            'closing' => $running,
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $this->requireFinanceAccess();
        $this->requirePermission('reports.export');

        if ($report === 'expenses') {
            return $this->exportExpenses($request);
        }

        if ($report === 'statement') {
            return $this->exportStatement($request);
        }

        $f = $this->reports->filters($request);

        return match ($report) {
            'students' => $this->exportGrouped(
                'student-collections',
                [__('Student ID'), __('Student'), __('Class'), __('Section'), __('Invoices'), __('Payments'), __('Standard fee (BDT)'), __('Cash received (BDT)')],
                $this->reports->byStudent($f),
                fn (array $row): array => [$row['code'] ?? '', $row['label'], $row['class'] ?? '', $row['section'] ?? '', $row['invoices'], $row['payments'], $this->paise($row['standard_fee']), $this->paise($row['received'])],
            ),
            'classes' => $this->exportGrouped(
                'class-section-collections',
                [__('Grouping'), __('Class / section'), __('Invoices'), __('Payments'), __('Standard fee (BDT)'), __('Cash received (BDT)')],
                $this->classExportRows($f),
                fn (array $row): array => [$row['grouping'], $row['label'], $row['invoices'], $row['payments'], $this->paise($row['standard_fee']), $this->paise($row['received'])],
            ),
            'fee-types' => $this->exportGrouped(
                'fee-type-collections',
                [__('Fee type'), __('Fee lines'), __('Standard fee (BDT)'), __('Cash received (BDT)')],
                $this->reports->byFeeType($f),
                fn (array $row): array => [$row['label'], $row['lines'], $this->paise($row['billed']), $this->paise($row['received'])],
            ),
            'methods' => $this->exportGrouped(
                'payment-method-collections',
                [__('Method'), __('Payments'), __('Invoices'), __('Standard fee of invoices paid this way (BDT)'), __('Cash received (BDT)')],
                $this->reports->byMethod($f),
                fn (array $row): array => [$row['label'], $row['payments'], $row['invoices'], $this->paise($row['standard_fee']), $this->paise($row['received'])],
            ),
            'collectors' => $this->exportGrouped(
                'collector-activity',
                [__('Collector'), __('Payments'), __('Invoices'), __('Standard fee (BDT)'), __('Cash received (BDT)'), __('Reversals signed'), __('Waiting verification')],
                $this->reports->byCollector($f),
                fn (array $row): array => [$row['label'], $row['payments'], $row['invoices'], $this->paise($row['standard_fee']), $this->paise($row['received']), $row['reversed'], $row['held']],
            ),
            'months' => $this->exportGrouped(
                'fee-month-collections',
                [__('Fee month'), __('Invoices'), __('Payments'), __('Standard fee (BDT)'), __('Cash received (BDT)')],
                $this->reports->byFeeMonth($f),
                fn (array $row): array => [$row['label'], $row['invoices'], $row['payments'], $this->paise($row['standard_fee']), $this->paise($row['received'])],
            ),
            'register' => $this->exportRegister($f),
            default => $this->exportCollection($f),
        };
    }

    /**
     * The counter register as a file: the same rows and the same twelve columns the
     * print sheet carries, so a downloaded report cannot drift from the paper one.
     *
     * @param  array<string, mixed>  $f
     */
    private function exportRegister(array $f): StreamedResponse
    {
        return $this->csv('fee-collection-register', function ($out) use ($f): void {
            fputcsv($out, [
                __('Serial'), __('Transaction ID'), __('Student Name'), __('Class'), __('Section'), __('Roll'),
                __('Fee Type'), __('Month'), __('Invoice'), __('Scheduled Amount (BDT)'), __('Received Amount (BDT)'),
                __('Payment Method'), __('Collection Date'), __('Collected By'),
            ]);

            foreach ($this->reports->registerRows($f) as $row) {
                fputcsv($out, [
                    $row['serial'],
                    $row['transaction_ref'],
                    $row['student'],
                    $row['class'],
                    $row['section'],
                    $row['roll'],
                    $row['fee_type'],
                    $row['month_label'],
                    $row['invoice_no'],
                    $this->paise($row['scheduled']),
                    $this->paise($row['received']),
                    $row['method'],
                    $row['collected_on']?->toDateTimeString(),
                    $row['collector'],
                ]);
            }
        });
    }

    /**
     * One row per money actually received. The invoice total is the standard fee
     * that was issued, never a claim about cash.
     *
     * @param  array<string, mixed>  $f
     */
    private function exportCollection(array $f): StreamedResponse
    {
        $rows = $this->reports->allocationRows($f);

        return $this->csv('fee-collection', function ($out) use ($rows): void {
            fputcsv($out, [
                __('Paid at'), __('Receipt'), __('Invoice'), __('Fee month'), __('Student ID'), __('Student'),
                __('Class'), __('Section'), __('Method'), __('Collector'),
                __('Standard fee of invoice (BDT)'), __('Cash received against invoice (BDT)'),
            ]);

            foreach ($rows as $allocation) {
                $payment = $allocation->payment;
                $invoice = $allocation->invoice;
                $enrollment = $invoice?->enrollment;

                fputcsv($out, [
                    $payment?->paid_at?->toDateTimeString(),
                    $payment?->receipt?->receipt_no,
                    $invoice?->invoice_no,
                    $invoice?->billing_period,
                    $payment?->student?->student_id,
                    $payment?->student?->full_name,
                    $enrollment?->schoolClass?->name,
                    $enrollment?->section?->name,
                    $payment?->method,
                    $payment?->receiver?->name,
                    number_format((float) ($invoice?->total ?? 0), 2, '.', ''),
                    number_format((float) $allocation->amount, 2, '.', ''),
                ]);
            }
        });
    }

    private function exportExpenses(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $rows = Expense::query()
            ->where('status', 'paid')
            ->with(['category', 'approver'])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('expense_date')
            ->get();

        return $this->csv('expense-report', function ($out) use ($rows): void {
            fputcsv($out, [__('Date'), __('Expense no'), __('Title'), __('Category'), __('Paid to'), __('Method'), __('Amount'), __('Approved by')]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->expense_date?->toDateString(),
                    $row->expense_no,
                    $row->title,
                    $row->category?->name,
                    $row->paid_to,
                    $row->payment_method,
                    number_format((float) $row->amount, 2, '.', ''),
                    $row->approver?->name,
                ]);
            }
        });
    }

    private function exportStatement(Request $request): StreamedResponse
    {
        $start = $request->filled('year')
            ? Carbon::create((int) $request->query('year'), 1, 1)->startOfYear()
            : now()->startOfYear();

        return $this->csv('income-expense-statement', function ($out) use ($start): void {
            fputcsv($out, [__('Month'), __('Income'), __('Expense'), __('Net')]);

            for ($month = 0; $month < 12; $month++) {
                $monthStart = $start->copy()->addMonthsNoOverflow($month)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();

                $income = round((float) CashTransaction::query()
                    ->where('amount', '>', 0)
                    ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('amount'), 2);

                $expense = round(abs((float) CashTransaction::query()
                    ->where('amount', '<', 0)
                    ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('amount'), 2));

                fputcsv($out, [
                    $monthStart->format('Y-m'),
                    number_format($income, 2, '.', ''),
                    number_format($expense, 2, '.', ''),
                    number_format($income - $expense, 2, '.', ''),
                ]);
            }
        });
    }

    /**
     * Class rows then section rows, so one file covers both summaries.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    private function classExportRows(array $f): Collection
    {
        return $this->reports->byClass($f)
            ->map(fn (array $row): array => $row + ['grouping' => __('Class')])
            ->concat($this->reports->bySection($f)->map(fn (array $row): array => $row + ['grouping' => __('Section')]))
            ->values();
    }

    /**
     * @param  list<string>  $headers
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): list<mixed>  $mapper
     */
    private function exportGrouped(string $name, array $headers, Collection $rows, callable $mapper): StreamedResponse
    {
        return $this->csv($name, function ($out) use ($headers, $rows, $mapper): void {
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $mapper($row));
            }
        });
    }

    private function paise(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Totals plus the collection percentage with its denominator named, since a
     * percentage without one can be read as money owed.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function summary(array $f): array
    {
        return $this->reports->summary($f) + [
            'rate' => $this->reports->collectionRate($f),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function filterViewData(Request $request, array $f, string $route): array
    {
        return [
            'route' => $route,
            'filters' => $f,
            'from' => $f['from'],
            'to' => $f['to'],
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name']),
            'sections' => Section::query()->with('schoolClass:id,name')->orderBy('name')->get(['id', 'class_id', 'name']),
            'feeTypes' => FeeType::query()->orderBy('name')->get(['id', 'name']),
            'collectors' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'students' => Student::query()->active()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'student_id']),
            'methods' => FeePayment::METHODS,
        ];
    }

    /**
     * Grouped rows are already fully aggregated in memory, so they page here
     * instead of re-reading the ledger once per page.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function page(Collection $rows, Request $request, int $perPage = 25): LengthAwarePaginator
    {
        $page = max(1, $request->integer('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()],
        );
    }

    /**
     * @param  callable(mixed): void  $callback
     */
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
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? $this->dateOrToday($request->query('from'))->startOfMonth()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? $this->dateOrToday($request->query('to'))
            : now()->startOfDay();

        return [$from, $to];
    }
}
