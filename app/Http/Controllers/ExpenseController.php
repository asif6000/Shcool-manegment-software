<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\ExpenseDecisionRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): View
    {
        $this->requirePermission('expenses.view');

        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $paid = (float) (clone $query)->where('status', 'paid')->sum('amount');
        $pending = (clone $query)->where('status', 'pending');
        $pendingCount = (clone $pending)->count();
        $pendingTotal = (float) (clone $pending)->sum('amount');

        return view('expenses.index', [
            'filters' => $filters,
            'expenses' => $query
                ->with(['category', 'approver', 'creator'])
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'paid' => $paid,
                'pending_count' => $pendingCount,
                'pending_total' => $pendingTotal,
                'count' => (clone $query)->count(),
            ],
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'approvalLimit' => $this->expenses->approvalLimit(),
        ]);
    }

    public function create(): View
    {
        $this->requirePermission('expenses.manage');

        return view('expenses.create', [
            'categories' => ExpenseCategory::query()->active()->orderBy('name')->get(),
            'bankAccounts' => BankAccount::query()->active()->orderBy('name')->get(),
            'methods' => Expense::METHODS,
            'approvalLimit' => $this->expenses->approvalLimit(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->record($request->user(), $request->validated());

        $message = $expense->status === 'pending'
            ? __('Expense :no was saved and is waiting for approval.', ['no' => $expense->expense_no])
            : __('Expense :no was recorded.', ['no' => $expense->expense_no]);

        return redirect()->route('expenses.show', $expense)->with('success', $message);
    }

    public function show(Expense $expense): View
    {
        $this->requirePermission('expenses.view');

        $expense->load(['category', 'approver', 'creator', 'canceller', 'bankAccount', 'cashTransactions.creator']);

        return view('expenses.show', [
            'expense' => $expense,
            'canApproveAmount' => $this->expenses->canApproveAmount(request()->user(), (float) $expense->amount),
            'approvalLimit' => $this->expenses->approvalLimit(),
        ]);
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->requirePermission('expenses.approve');

        $this->expenses->approve($request->user(), $expense);

        return back()->with('success', __('Expense :no was approved and posted to the ledger.', ['no' => $expense->expense_no]));
    }

    public function reject(ExpenseDecisionRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->reject($request->user(), $expense, (string) $request->validated()['reason']);

        return back()->with('success', __('Expense :no was rejected.', ['no' => $expense->expense_no]));
    }

    public function cancel(ExpenseDecisionRequest $request, Expense $expense): RedirectResponse
    {
        $this->requirePermission('expenses.manage');

        $this->expenses->cancel($request->user(), $expense, (string) $request->validated()['reason']);

        return back()->with('success', __('Expense :no was cancelled and the ledger was reversed.', ['no' => $expense->expense_no]));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('expenses.view');

        $filters = $this->filters($request);

        $expenses = $this->filteredQuery($filters)
            ->with(['category', 'approver'])
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($expenses): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Expense no'), __('Date'), __('Title'), __('Category'), __('Paid to'),
                __('Method'), __('Reference'), __('Amount'), __('Status'), __('Approved by'),
            ]);

            foreach ($expenses as $expense) {
                fputcsv($out, [
                    $expense->expense_no,
                    $expense->expense_date?->toDateString(),
                    $expense->title,
                    $expense->category?->name,
                    $expense->paid_to,
                    $expense->payment_method,
                    $expense->reference_no,
                    number_format((float) $expense->amount, 2, '.', ''),
                    $expense->status,
                    $expense->approver?->name,
                ]);
            }

            fclose($out);
        }, 'expenses-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{search: string, status: string, category_id: string, from: string, to: string}
     */
    private function filters(Request $request): array
    {
        $from = $this->dateOrToday($request->query('from'))->startOfMonth();
        $to = $this->dateOrToday($request->query('to'));

        return [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'from' => $request->filled('from') || $request->filled('to') ? $from->toDateString() : '',
            'to' => $request->filled('from') || $request->filled('to') ? $to->toDateString() : '',
        ];
    }

    /**
     * @param  array{search: string, status: string, category_id: string, from: string, to: string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return Expense::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('expense_no', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('paid_to', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['category_id'] !== '', fn (Builder $query) => $query->where('expense_category_id', (int) $filters['category_id']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('expense_date', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('expense_date', '<=', $filters['to']));
    }
}
