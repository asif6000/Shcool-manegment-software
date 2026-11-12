<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashTransaction;
use App\Services\CashLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashbookController extends Controller
{
    public function __construct(private readonly CashLedgerService $ledger) {}

    public function index(Request $request): View
    {
        $this->requirePermission('cash.view');

        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $income = (float) (clone $query)->where('type', 'income')->sum('amount');
        $expense = (float) abs((clone $query)->where('type', 'expense')->sum('amount'));

        $transactions = $query
            ->with(['creator', 'bankAccount'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('cashbook.index', [
            'filters' => $filters,
            'transactions' => $transactions,
            'summary' => [
                'income' => $income,
                'expense' => $expense,
                'net' => round($income - $expense, 2),
                'count' => $transactions->total(),
            ],
            'balances' => $this->balances(),
            'bankAccounts' => BankAccount::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function closing(Request $request): View
    {
        $this->requirePermission('cash.view');

        $date = $this->dateOrToday($request->query('date'))->toDateString();

        $rows = CashTransaction::query()
            ->whereDate('transaction_date', $date)
            ->with(['creator', 'reconciler'])
            ->orderBy('id')
            ->get();

        return view('cashbook.closing', [
            'date' => Carbon::parse($date),
            'rows' => $rows,
            'byChannel' => $rows->groupBy('channel')->map(fn ($group): array => [
                'income' => round((float) $group->where('type', 'income')->sum('amount'), 2),
                'expense' => round(abs((float) $group->where('type', 'expense')->sum('amount')), 2),
                'net' => round((float) $group->sum('amount'), 2),
            ]),
            'totals' => [
                'income' => round((float) $rows->where('type', 'income')->sum('amount'), 2),
                'expense' => round(abs((float) $rows->where('type', 'expense')->sum('amount')), 2),
                'net' => round((float) $rows->sum('amount'), 2),
            ],
            'unreconciled' => $rows->whereNull('reconciled_at')->count(),
            'balances' => $this->balances(),
        ]);
    }

    public function reconcile(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $this->requirePermission('cash.view');

        $this->ledger->reconcile($request->user(), $transaction);

        return back()->with('success', __('The ledger entry was marked as reconciled.'));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('cash.view');

        $filters = $this->filters($request);

        $rows = $this->filteredQuery($filters)
            ->with(['creator', 'bankAccount'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Date'), __('Type'), __('Channel'), __('Bank account'), __('Description'),
                __('Reference'), __('Amount'), __('Reconciled'), __('Entered by'),
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->transaction_date?->toDateString(),
                    $row->type,
                    $row->channel,
                    $row->bankAccount?->name,
                    $row->description,
                    $row->reference_no,
                    number_format((float) $row->amount, 2, '.', ''),
                    $row->reconciled_at ? $row->reconciled_at->toDateTimeString() : '',
                    $row->creator?->name,
                ]);
            }

            fclose($out);
        }, 'cashbook-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function balances(): array
    {
        $channels = [];
        $totals = CashTransaction::query()
            ->selectRaw('channel, COALESCE(SUM(amount), 0) as balance')
            ->groupBy('channel')
            ->pluck('balance', 'channel');

        foreach (CashTransaction::CHANNELS as $channel) {
            $channels[$channel] = round((float) ($totals[$channel] ?? 0), 2);
        }

        $accounts = BankAccount::query()
            ->active()
            ->withSum('transactions as ledger_total', 'amount')
            ->orderBy('name')
            ->get()
            ->map(fn (BankAccount $account): array => [
                'id' => $account->id,
                'label' => $account->label(),
                'opening' => (float) $account->opening_balance,
                'ledger' => round((float) ($account->ledger_total ?? 0), 2),
                'balance' => round((float) $account->opening_balance + (float) ($account->ledger_total ?? 0), 2),
            ]);

        $opening = (float) BankAccount::query()->active()->sum('opening_balance');

        return [
            'cash' => $channels['cash'],
            'mobile' => $channels['mobile'],
            'bank_ledger' => $channels['bank'],
            'bank_opening' => round($opening, 2),
            'bank' => round($opening + $channels['bank'], 2),
            'total' => round($channels['cash'] + $channels['mobile'] + $opening + $channels['bank'], 2),
            'accounts' => $accounts,
        ];
    }

    /**
     * @return array{channel: string, type: string, from: string, to: string, search: string, reconciled: string}
     */
    private function filters(Request $request): array
    {
        $hasRange = $request->filled('from') || $request->filled('to');

        return [
            'channel' => in_array($request->query('channel'), CashTransaction::CHANNELS, true)
                ? (string) $request->query('channel')
                : '',
            'type' => in_array($request->query('type'), CashTransaction::TYPES, true)
                ? (string) $request->query('type')
                : '',
            'from' => $hasRange ? $this->dateOrToday($request->query('from'))->startOfMonth()->toDateString() : '',
            'to' => $hasRange ? $this->dateOrToday($request->query('to'))->toDateString() : '',
            'search' => trim((string) $request->query('search')),
            'reconciled' => in_array($request->query('reconciled'), ['yes', 'no'], true)
                ? (string) $request->query('reconciled')
                : '',
        ];
    }

    /**
     * @param  array{channel: string, type: string, from: string, to: string, search: string, reconciled: string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return CashTransaction::query()
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->channel($filters['channel']))
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('transaction_date', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('transaction_date', '<=', $filters['to']))
            ->when($filters['reconciled'] === 'yes', fn (Builder $query) => $query->whereNotNull('reconciled_at'))
            ->when($filters['reconciled'] === 'no', fn (Builder $query) => $query->whereNull('reconciled_at'))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('description', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                });
            });
    }
}
