<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\CashTransaction;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeDiscount;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\SalaryStructure;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use App\Support\SchoolContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountantDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $actor): array
    {
        $school = School::first();
        if ($school && app(SchoolContext::class)->id() === null) {
            app(SchoolContext::class)->set($school->id);
        }

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        // 1. Today's Collection
        $todayCollection = (float) FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$today, now()->endOfDay()])
            ->sum('amount');
        $todayCount = FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$today, now()->endOfDay()])
            ->count();

        // 2. Monthly Collection
        $monthCollection = (float) FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');
        $lastMonthCollection = (float) FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');
        $monthCount = FeePayment::query()
            ->posted()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->count();

        // 3. Expenses
        $todayExpenses = (float) Expense::query()
            ->where('status', 'paid')
            ->whereDate('expense_date', $today->toDateString())
            ->sum('amount');
        $monthExpenses = (float) Expense::query()
            ->where('status', 'paid')
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');
        $totalExpenses = (float) Expense::query()
            ->where('status', 'paid')
            ->sum('amount');
        $expenseCount = Expense::query()
            ->where('status', 'paid')
            ->count();

        // 4. Cash Balances
        $cashInHand = $this->channelBalance('cash');
        $mobileBalance = $this->channelBalance('mobile');
        $bankBalance = $this->bankBalance();
        $totalCash = round($cashInHand + $bankBalance + $mobileBalance, 2);

        // 5. Total Discounts
        $totalDiscounts = (float) FeeInvoice::query()->sum('discount_total');
        $discountedInvoicesCount = FeeInvoice::query()->where('discount_total', '>', 0)->count();
        $activeDiscountSchemes = FeeDiscount::query()->where('is_active', true)->count();

        // 6. Online Payments
        $onlinePaymentsTotal = (float) FeePayment::query()
            ->posted()
            ->where('method', '!=', 'cash')
            ->sum('amount');
        $onlinePaymentsCount = FeePayment::query()
            ->posted()
            ->where('method', '!=', 'cash')
            ->count();
        $onlinePaymentsToday = (float) FeePayment::query()
            ->posted()
            ->where('method', '!=', 'cash')
            ->whereBetween('paid_at', [$today, now()->endOfDay()])
            ->sum('amount');

        // Outstanding fees
        $outstanding = (float) FeeInvoice::query()
            ->outstanding()
            ->sum('due_total');

        // Lists for Action Card Modals & Below-Action-Card Tables
        $recentPayments = FeePayment::query()
            ->posted()
            ->with(['student.enrollments.schoolClass', 'receiver'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $recentExpenses = Expense::query()
            ->with(['category', 'creator', 'approver'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $openInvoices = FeeInvoice::query()
            ->outstanding()
            ->with(['student', 'enrollment.schoolClass', 'enrollment.section', 'academicSession', 'items.feeType'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(25)
            ->get();

        $expenseCategories = ExpenseCategory::query()
            ->active()
            ->orderBy('name')
            ->get();

        $bankAccounts = BankAccount::query()
            ->active()
            ->orderBy('name')
            ->get();

        $feeDiscounts = FeeDiscount::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $teachers = Teacher::query()
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        $employees = Employee::query()
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        $salaryStructures = SalaryStructure::query()
            ->with(['teacher', 'employee'])
            ->where('is_active', true)
            ->limit(20)
            ->get();

        $academicSessions = AcademicSession::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_current']);

        $currentSession = AcademicSession::query()->where('is_current', true)->first()
            ?? AcademicSession::query()->orderByDesc('start_date')->first();

        // Today's Transactions
        $todayTransactions = CashTransaction::query()
            ->with('source')
            ->whereDate('transaction_date', $today->toDateString())
            ->orderByDesc('id')
            ->get();

        // If no transactions logged specifically today, grab the most recent day-book entries
        if ($todayTransactions->isEmpty()) {
            $todayTransactions = CashTransaction::query()
                ->with('source')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(8)
                ->get();
        }

        $pendingExpenses = Expense::query()->where('status', 'pending');
        $pendingVerification = FeePayment::query()->pendingVerification();

        return [
            'today' => $today,
            'actor' => $actor,
            'school' => $school,
            'cards' => [
                'today_collection' => [
                    'value' => $todayCollection,
                    'count' => $todayCount,
                ],
                'month_collection' => [
                    'value' => $monthCollection,
                    'growth' => $this->percentageChange($monthCollection, $lastMonthCollection),
                    'count' => $monthCount,
                ],
                'total_expenses' => [
                    'value' => $monthExpenses > 0 ? $monthExpenses : $totalExpenses,
                    'month_value' => $monthExpenses,
                    'today_value' => $todayExpenses,
                    'total_value' => $totalExpenses,
                    'count' => $expenseCount,
                ],
                'cash_balance' => [
                    'value' => $totalCash,
                    'cash' => $cashInHand,
                    'bank' => $bankBalance,
                    'mobile' => $mobileBalance,
                ],
                'total_discounts' => [
                    'value' => $totalDiscounts,
                    'invoices_count' => $discountedInvoicesCount,
                    'schemes_count' => $activeDiscountSchemes,
                ],
                'online_payments' => [
                    'value' => $onlinePaymentsTotal,
                    'count' => $onlinePaymentsCount,
                    'today_value' => $onlinePaymentsToday,
                ],
                'outstanding' => [
                    'value' => $outstanding,
                    'students' => FeeInvoice::query()->outstanding()->distinct()->count('student_id'),
                ],
                'cash_in_hand' => ['value' => $cashInHand],
                'bank_balance' => ['value' => $bankBalance],
                'pending_expenses' => [
                    'count' => (clone $pendingExpenses)->count(),
                    'value' => (float) (clone $pendingExpenses)->sum('amount'),
                ],
                'pending_verification' => [
                    'count' => (clone $pendingVerification)->count(),
                    'value' => (float) (clone $pendingVerification)->sum('amount'),
                ],
            ],
            'position' => [
                'cash' => $cashInHand,
                'bank' => $bankBalance,
                'mobile' => $mobileBalance,
                'total' => $totalCash,
            ],
            'collectionSummary' => $this->collectionSummary($monthStart, $monthEnd, $monthCollection, $monthCount, $outstanding),
            'cashbookSummary' => $this->cashbookSummary($today, $monthStart, $monthEnd, $cashInHand, $bankBalance, $mobileBalance),
            'chart' => $this->monthlyChart(),
            'recent' => $this->recentTransactions(),
            'recentPayments' => $recentPayments,
            'recentExpenses' => $recentExpenses,
            'todayTransactions' => $todayTransactions,
            'openInvoices' => $openInvoices,
            'expenseCategories' => $expenseCategories,
            'bankAccounts' => $bankAccounts,
            'feeDiscounts' => $feeDiscounts,
            'teachers' => $teachers,
            'employees' => $employees,
            'salaryStructures' => $salaryStructures,
            'academicSessions' => $academicSessions,
            'currentSession' => $currentSession,
            'paymentMethods' => FeePayment::METHODS,
            'referenceRequiredMethods' => array_values((array) config('finance.reference_required_methods', [])),
            'expenseMethods' => Expense::METHODS,
            'idempotencyKey' => (string) Str::uuid(),
            'pendingExpenseList' => (clone $pendingExpenses)->with('category')->orderByDesc('id')->limit(5)->get(),
            'pendingVerificationList' => (clone $pendingVerification)->with('student')->orderByDesc('id')->limit(5)->get(),
            'expenseLimit' => (float) config('finance.expense_approval_limit', 0),
            'canApprove' => $actor->can('expenses.approve'),
        ];
    }

    private function channelBalance(string $channel): float
    {
        return round((float) CashTransaction::query()
            ->where('channel', $channel)
            ->sum('amount'), 2);
    }

    private function bankBalance(): float
    {
        $opening = (float) BankAccount::query()->sum('opening_balance');

        return round($opening + $this->channelBalance('bank'), 2);
    }

    /**
     * This month's collection read the same way the collection report reads it, so
     * the dashboard and the printed register can never disagree.
     *
     * @return array<string, mixed>
     */
    private function collectionSummary(
        Carbon $monthStart,
        Carbon $monthEnd,
        float $monthCollection,
        int $monthCount,
        float $outstanding,
    ): array {
        $report = app(FeeReportService::class);
        $filters = $report->filters(Request::create('/reports/collection', 'GET', [
            'from' => $monthStart->toDateString(),
            'to' => $monthEnd->toDateString(),
        ]));

        $summary = $report->summary($filters);
        $feeTypes = $report->feeTypeSummary($filters);

        return [
            'received' => round($monthCollection, 2),
            'payments' => $monthCount,
            'billed' => $summary['standard_fee'] ?? 0.0,
            'rate' => $report->collectionRate($filters),
            'outstanding' => round($outstanding, 2),
            'open_invoices' => FeeInvoice::query()->outstanding()->count(),
            'groups' => $feeTypes['groups']->take(5)->values()->all(),
            'total_grouped' => $feeTypes['total'],
        ];
    }

    /**
     * Where the money physically sits and how it moved, straight off the cash ledger.
     *
     * @return array<string, mixed>
     */
    private function cashbookSummary(
        Carbon $today,
        Carbon $monthStart,
        Carbon $monthEnd,
        float $cashInHand,
        float $bankBalance,
        float $mobileBalance,
    ): array {
        $signed = fn (Carbon $from, Carbon $to, bool $inflow): float => round(abs((float) CashTransaction::query()
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->when($inflow, fn ($query) => $query->where('amount', '>=', 0), fn ($query) => $query->where('amount', '<', 0))
            ->sum('amount')), 2);

        return [
            'cash' => round($cashInHand, 2),
            'bank' => round($bankBalance, 2),
            'mobile' => round($mobileBalance, 2),
            'total' => round($cashInHand + $bankBalance + $mobileBalance, 2),
            'today_in' => $signed($today, $today, true),
            'today_out' => $signed($today, $today, false),
            'month_in' => $signed($monthStart, $monthEnd, true),
            'month_out' => $signed($monthStart, $monthEnd, false),
            'entries' => CashTransaction::query()
                ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
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
     * @return array{labels: list<string>, collection: list<float>, expense: list<float>}
     */
    private function monthlyChart(): array
    {
        $labels = [];
        $collection = [];
        $expense = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = Carbon::now()->startOfMonth()->subMonthsNoOverflow($offset);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $labels[] = $month->locale(app()->getLocale())->translatedFormat('M Y');
            $collection[] = (float) FeePayment::query()
                ->posted()
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');
            $expense[] = (float) CashTransaction::query()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');
        }

        return [
            'labels' => $labels,
            'collection' => $collection,
            'expense' => $expense,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function recentTransactions()
    {
        return CashTransaction::query()
            ->with('source')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (CashTransaction $transaction): array => [
                'date' => $transaction->transaction_date,
                'description' => $transaction->description,
                'channel' => $transaction->channel,
                'direction' => (float) $transaction->amount >= 0 ? 'in' : 'out',
                'amount' => abs((float) $transaction->amount),
                'formatted' => Money::format(abs((float) $transaction->amount)),
                'reference' => $transaction->reference_no,
                'type' => $this->sourceLabel($transaction),
            ]);
    }

    private function sourceLabel(CashTransaction $transaction): string
    {
        return match ($transaction->source_type) {
            FeePayment::class => __('Fee payment'),
            Expense::class => __('Expense'),
            Voucher::class => __('Voucher'),
            \App\Models\SalaryPayment::class => __('Salary'),
            default => __('Ledger entry'),
        };
    }
}
