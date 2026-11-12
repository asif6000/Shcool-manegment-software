<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CashLedgerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function postIncome(
        User $actor,
        float $amount,
        Carbon|string $date,
        string $description,
        ?Model $source = null,
        ?string $reference = null,
        ?string $channel = null,
        ?int $bankAccountId = null,
    ): CashTransaction {
        return $this->record($actor, 'income', $amount, $date, $description, $source, $reference, null, $channel, $bankAccountId);
    }

    public function postExpense(
        User $actor,
        float $amount,
        Carbon|string $date,
        string $description,
        ?Model $source = null,
        ?string $reference = null,
        ?string $channel = null,
        ?int $bankAccountId = null,
    ): CashTransaction {
        return $this->record($actor, 'expense', $amount, $date, $description, $source, $reference, null, $channel, $bankAccountId);
    }

    /**
     * Post the opposite entry for an existing transaction and link the two rows.
     */
    public function reverse(User $actor, CashTransaction $original, string $reason): CashTransaction
    {
        $amount = abs((float) $original->amount);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'cash' => __('This cash entry has no amount to reverse.'),
            ]);
        }

        $reversal = $this->record(
            $actor,
            $original->type,
            $amount,
            now()->toDateString(),
            __('Reversal of :description', ['description' => $original->description]),
            $original->source,
            $original->reference_no,
            $original,
            $original->channel,
            $original->bank_account_id !== null ? (int) $original->bank_account_id : null,
        );

        $this->audit->log(
            event: 'cash.reversed',
            auditable: $original,
            description: __('Cash entry :id was reversed: :reason', ['id' => $original->id, 'reason' => $reason]),
            actor: $actor,
            schoolId: (int) $original->school_id,
        );

        return $reversal;
    }

    public function reconcile(User $actor, CashTransaction $transaction): CashTransaction
    {
        if ($transaction->reconciled_at !== null) {
            return $transaction;
        }

        $transaction->update([
            'reconciled_at' => now(),
            'reconciled_by' => $actor->id,
        ]);

        $this->audit->log(
            event: 'cash.reconciled',
            auditable: $transaction,
            description: __('Cash entry :id was reconciled', ['id' => $transaction->id]),
            actor: $actor,
            schoolId: (int) $transaction->school_id,
        );

        return $transaction->refresh();
    }

    private function record(
        User $actor,
        string $type,
        float $amount,
        Carbon|string $date,
        string $description,
        ?Model $source = null,
        ?string $reference = null,
        ?CashTransaction $reversalOf = null,
        ?string $channel = null,
        ?int $bankAccountId = null,
    ): CashTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Cash transactions must have a positive amount.'),
            ]);
        }

        $signed = $type === 'income' ? $amount : -$amount;

        if ($reversalOf !== null) {
            $signed = -$signed;
        }

        $channel = $this->normalizeChannel($channel ?? $this->channelForSource($source));

        return CashTransaction::query()->create([
            'type' => $type,
            'channel' => $channel,
            'bank_account_id' => $channel === 'bank' ? $bankAccountId : null,
            'amount' => $signed,
            'transaction_date' => Carbon::parse($date)->toDateString(),
            'description' => $description,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reference_no' => $reference,
            'reversal_of_id' => $reversalOf?->id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Map a recorded payment method (cash, bkash, bank) to the ledger channel
     * that the cashbook and bank balance are built from.
     */
    public function channelForMethod(?string $method): string
    {
        $map = (array) config('finance.method_channels', []);

        return $this->normalizeChannel($map[$method ?? ''] ?? 'cash');
    }

    public function channelForSource(?Model $source): string
    {
        if ($source === null) {
            return 'cash';
        }

        $method = $source->getAttribute('method')
            ?? $source->getAttribute('payment_method');

        return $this->channelForMethod(is_string($method) ? $method : null);
    }

    public function normalizeChannel(?string $channel): string
    {
        return in_array($channel, CashTransaction::CHANNELS, true) ? $channel : 'cash';
    }

    public function originalFor(Model $source): ?CashTransaction
    {
        return CashTransaction::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reversal_of_id')
            ->orderBy('id')
            ->first();
    }

    public function reverseForSource(User $actor, Model $source, string $reason): ?CashTransaction
    {
        $original = $this->originalFor($source);

        return $original !== null
            ? $this->reverse($actor, $original, $reason)
            : null;
    }

    protected function schoolId(): int
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
