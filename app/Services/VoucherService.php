<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\School;
use App\Models\User;
use App\Models\Voucher;
use App\Support\AuditLogger;
use App\Support\NumberSequence;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberSequence $numbers,
        private readonly CashLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(User $actor, array $data): Voucher
    {
        $schoolId = $this->schoolId();
        $date = Carbon::parse((string) $data['voucher_date']);
        $amount = round((float) $data['amount'], 2);
        $type = (string) $data['type'];
        $channel = (string) ($data['channel'] ?? 'cash');

        if (! in_array($type, Voucher::TYPES, true)) {
            throw ValidationException::withMessages(['type' => __('Choose a valid voucher type.')]);
        }

        if (! in_array($channel, Voucher::CHANNELS, true)) {
            throw ValidationException::withMessages(['channel' => __('Choose a valid payment channel.')]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The voucher amount must be greater than zero.')]);
        }

        return DB::transaction(function () use ($actor, $data, $schoolId, $date, $amount, $type, $channel): Voucher {
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            $bankAccountId = null;

            if ($channel === 'bank') {
                $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
                $account = BankAccount::query()->whereKey($bankAccountId)->where('is_active', true)->first();

                if ($account === null) {
                    throw ValidationException::withMessages([
                        'bank_account_id' => __('Choose an active bank account for bank vouchers.'),
                    ]);
                }
            }

            $voucher = Voucher::query()->create([
                'voucher_no' => $this->numbers->next(Voucher::class, 'voucher_no', 'VCH', (int) $date->year, $schoolId),
                'type' => $type,
                'voucher_date' => $date->toDateString(),
                'amount' => $amount,
                'channel' => $channel,
                'bank_account_id' => $bankAccountId,
                'category' => $data['category'] ?? null,
                'payee' => $data['payee'] ?? null,
                'description' => (string) $data['description'],
                'reference_no' => $data['reference_no'] ?? null,
                'status' => 'posted',
                'created_by' => $actor->id,
            ]);

            $description = __('Voucher :no · :description', [
                'no' => $voucher->voucher_no,
                'description' => $voucher->description,
            ]);

            $transaction = $type === 'income'
                ? $this->ledger->postIncome($actor, $amount, $date, $description, $voucher, $voucher->voucher_no, $channel, $bankAccountId)
                : $this->ledger->postExpense($actor, $amount, $date, $description, $voucher, $voucher->voucher_no, $channel, $bankAccountId);

            $voucher->update(['cash_transaction_id' => $transaction->id]);

            $this->audit->log(
                event: 'voucher.posted',
                auditable: $voucher,
                description: __('Voucher :no was posted', ['no' => $voucher->voucher_no]),
                newValues: [
                    'type' => $type,
                    'amount' => $amount,
                    'channel' => $channel,
                    'voucher_date' => $date->toDateString(),
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $voucher;
        });
    }

    public function cancel(User $actor, Voucher $voucher, string $reason): Voucher
    {
        return DB::transaction(function () use ($actor, $voucher, $reason): Voucher {
            /** @var Voucher $locked */
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());

            if ($locked->status !== 'posted') {
                throw ValidationException::withMessages([
                    'voucher' => __('Only posted vouchers can be cancelled.'),
                ]);
            }

            $this->ledger->reverseForSource($actor, $locked, $reason);

            $locked->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->audit->log(
                event: 'voucher.cancelled',
                auditable: $locked,
                description: __('Voucher :no was cancelled: :reason', ['no' => $locked->voucher_no, 'reason' => $reason]),
                oldValues: ['status' => 'posted', 'amount' => (float) $locked->amount],
                newValues: ['status' => 'cancelled', 'cancel_reason' => $reason],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
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
