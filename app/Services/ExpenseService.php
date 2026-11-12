<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NumberSequence;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberSequence $numbers,
        private readonly CashLedgerService $ledger,
    ) {}

    public function approvalLimit(): float
    {
        return (float) config('finance.expense_approval_limit', 0);
    }

    public function canApproveAmount(User $actor, float $amount): bool
    {
        if (! $actor->can('expenses.approve')) {
            return false;
        }

        return $amount <= $this->approvalLimit() || $actor->can('expenses.approve.high');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(User $actor, array $data): Expense
    {
        $schoolId = $this->schoolId();
        $date = Carbon::parse((string) $data['expense_date']);
        $amount = round((float) $data['amount'], 2);
        $approved = $this->canApproveAmount($actor, $amount);

        return DB::transaction(function () use ($actor, $data, $schoolId, $date, $amount, $approved): Expense {
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            /** @var ExpenseCategory $category */
            $category = ExpenseCategory::query()->findOrFail((int) $data['expense_category_id']);

            if (! $category->is_active) {
                throw ValidationException::withMessages([
                    'expense_category_id' => __('This expense category is inactive.'),
                ]);
            }

            $expense = Expense::query()->create([
                'expense_category_id' => $category->id,
                'expense_no' => $this->numbers->next(Expense::class, 'expense_no', 'EXP', (int) $date->year, $schoolId),
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'expense_date' => $date->toDateString(),
                'payment_method' => (string) $data['payment_method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'paid_to' => $data['paid_to'] ?? null,
                'status' => $approved ? 'paid' : 'pending',
                'approved_by' => $approved ? $actor->id : null,
                'approved_at' => $approved ? now() : null,
                'created_by' => $actor->id,
            ]);

            if ($approved) {
                $this->postToLedger($actor, $expense);
            }

            $this->audit->log(
                event: $approved ? 'expense.recorded' : 'expense.submitted',
                auditable: $expense,
                description: $approved
                    ? __('Expense :no was recorded', ['no' => $expense->expense_no])
                    : __('Expense :no was submitted for approval', ['no' => $expense->expense_no]),
                newValues: [
                    'amount' => (float) $expense->amount,
                    'category' => $category->name,
                    'expense_date' => $expense->expense_date->toDateString(),
                    'method' => $expense->payment_method,
                    'status' => $expense->status,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $expense;
        });
    }

    public function approve(User $actor, Expense $expense): Expense
    {
        return DB::transaction(function () use ($actor, $expense): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'expense' => __('Only expenses waiting for approval can be approved.'),
                ]);
            }

            if (! $this->canApproveAmount($actor, (float) $locked->amount)) {
                throw ValidationException::withMessages([
                    'expense' => __('This expense is above your approval limit.'),
                ]);
            }

            $locked->update([
                'status' => 'paid',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->postToLedger($actor, $locked);

            $this->audit->log(
                event: 'expense.approved',
                auditable: $locked,
                description: __('Expense :no was approved', ['no' => $locked->expense_no]),
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'paid', 'amount' => (float) $locked->amount],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function reject(User $actor, Expense $expense, string $reason): Expense
    {
        return DB::transaction(function () use ($actor, $expense, $reason): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'expense' => __('Only expenses waiting for approval can be rejected.'),
                ]);
            }

            $locked->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            $this->audit->log(
                event: 'expense.rejected',
                auditable: $locked,
                description: __('Expense :no was rejected: :reason', ['no' => $locked->expense_no, 'reason' => $reason]),
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'rejected', 'rejection_reason' => $reason],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function cancel(User $actor, Expense $expense, string $reason): Expense
    {
        return DB::transaction(function () use ($actor, $expense, $reason): Expense {
            /** @var Expense $locked */
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());

            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages([
                    'expense' => __('Only approved expenses can be cancelled.'),
                ]);
            }

            $this->ledger->reverseForSource($actor, $locked, $reason);

            $locked->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            $this->audit->log(
                event: 'expense.cancelled',
                auditable: $locked,
                description: __('Expense :no was cancelled: :reason', ['no' => $locked->expense_no, 'reason' => $reason]),
                oldValues: ['status' => 'paid'],
                newValues: ['status' => 'cancelled', 'cancel_reason' => $reason],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    private function postToLedger(User $actor, Expense $expense): void
    {
        $this->ledger->postExpense(
            actor: $actor,
            amount: (float) $expense->amount,
            date: $expense->expense_date->toDateString(),
            description: __('Expense :no · :title', ['no' => $expense->expense_no, 'title' => $expense->title]),
            source: $expense,
            reference: $expense->expense_no,
            channel: $this->ledger->channelForMethod($expense->payment_method),
            bankAccountId: $expense->bank_account_id !== null ? (int) $expense->bank_account_id : null,
        );
    }

    private function schoolId(): int
    {
        $schoolId = app(SchoolContext::class)->id();

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school' => __('A school context is required.'),
            ]);
        }

        return $schoolId;
    }
}
