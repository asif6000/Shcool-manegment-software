<?php

namespace App\Services;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\FeeReceipt;
use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NumberSequence;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FeePaymentService
{
    /** @var list<string>|null */
    private static ?array $contextColumns = null;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberSequence $numbers,
        private readonly CashLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function collect(User $actor, FeeInvoice $invoice, array $data): FeePayment
    {
        return $this->collectAcross($actor, [$invoice->id => (float) $data['amount']], $data);
    }

    /**
     * One collection, one receipt and one cashbook posting that can settle
     * several invoices — for example three months of tuition at once.
     *
     * @param  array<int|string, float>  $lineAmounts  invoice id => amount received against it
     * @param  array<string, mixed>  $data
     */
    public function collectAcross(User $actor, array $lineAmounts, array $data): FeePayment
    {
        $schoolId = $this->schoolId();
        $key = (string) $data['idempotency_key'];
        $paidAt = Carbon::parse((string) $data['paid_at']);
        $hold = (bool) ($data['hold_for_verification'] ?? false);
        $settleInFull = (bool) ($data['settle_in_full'] ?? false);

        $lines = $this->normaliseLines($lineAmounts);
        $amount = round(array_sum($lines), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Enter the amount received for at least one fee.'),
            ]);
        }

        $payment = DB::transaction(function () use ($actor, $lines, $amount, $data, $schoolId, $key, $paidAt, $hold, $settleInFull): FeePayment {
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            $existing = FeePayment::query()->where('school_id', $schoolId)->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                $linked = array_map('intval', FeePaymentAllocation::query()
                    ->where('fee_payment_id', $existing->id)
                    ->pluck('fee_invoice_id')
                    ->all());

                $wanted = array_keys($lines);
                sort($linked);
                sort($wanted);

                if ($linked !== $wanted) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => __('This submission key was already used for a different set of fees.'),
                    ]);
                }

                return $existing;
            }

            // Lock in id order so two concurrent collections cannot deadlock.
            $invoices = FeeInvoice::query()
                ->whereIn('id', array_keys($lines))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($invoices->count() !== count($lines)) {
                throw ValidationException::withMessages([
                    'invoice' => __('One of the selected fees no longer exists.'),
                ]);
            }

            if ($invoices->pluck('student_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages([
                    'invoice' => __('One receipt can only cover the fees of a single student.'),
                ]);
            }

            // The caller named a student; a forged invoice id must not be able
            // to bill somebody else's child through the same basket.
            $claimedStudent = isset($data['student_id']) ? (int) $data['student_id'] : null;

            if ($claimedStudent !== null && (int) $invoices->first()->student_id !== $claimedStudent) {
                throw ValidationException::withMessages([
                    'student_id' => __('These fee records do not belong to the selected student.'),
                ]);
            }

            $reservedByInvoice = $this->reservedByInvoice(array_keys($lines));

            foreach ($invoices as $invoice) {
                if ($invoice->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'amount' => __('Cancelled invoices cannot receive payments.'),
                    ]);
                }

                $due = round((float) $invoice->due_total, 2);
                $available = round($due - ($reservedByInvoice[$invoice->id] ?? 0), 2);

                if ($due <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => __('Invoice :invoice is already closed.', ['invoice' => $invoice->invoice_no]),
                    ]);
                }

                $line = round($lines[$invoice->id], 2);

                if ($line <= 0 || $line > $available) {
                    throw ValidationException::withMessages([
                        'amount' => __('The amount for invoice :invoice must be greater than zero and cannot exceed :due.', [
                            'invoice' => $invoice->invoice_no,
                            'due' => number_format(max(0, $available), 2),
                        ]),
                    ]);
                }
            }

            $verificationStatus = $hold
                ? 'pending'
                : ((string) $data['method'] === 'cash' ? 'not_required' : 'verified');

            $primaryInvoice = $invoices->first();

            $payment = FeePayment::query()->create([
                'student_id' => $primaryInvoice->student_id,
                ...$this->billingContext($primaryInvoice),
                'class_id' => $data['class_id'] ?? $primaryInvoice->enrollment?->class_id,
                'fee_type_id' => $data['fee_type_id'] ?? $this->feeTypeOf($primaryInvoice),
                'billing_month' => $data['billing_month'] ?? $primaryInvoice->billing_period,
                'invoice_no' => $data['invoice_no'] ?? $primaryInvoice->invoice_no,
                'amount' => $amount,
                'method' => (string) $data['method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'transaction_ref' => $this->reference($data['transaction_ref'] ?? null),
                'paid_at' => $paidAt->toDateTimeString(),
                'received_by' => $actor->id,
                'status' => $hold ? 'pending' : 'posted',
                'verification_status' => $verificationStatus,
                'verified_by' => $hold ? null : $actor->id,
                'verified_at' => $hold ? null : now(),
                'idempotency_key' => $key,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($invoices as $invoice) {
                $line = round($lines[$invoice->id], 2);

                // A settlement closes the invoice on the money actually
                // received. The difference is written off here and never
                // becomes a balance.
                $waived = $settleInFull
                    ? round(max(0, (float) $invoice->due_total - ($reservedByInvoice[$invoice->id] ?? 0) - $line), 2)
                    : 0.0;

                $payment->allocations()->create([
                    'fee_invoice_id' => $invoice->id,
                    'amount' => $line,
                    'waived_amount' => $waived,
                ]);
            }

            if ($hold) {
                $this->audit->log(
                    event: 'fee_payment.pending_verification',
                    auditable: $payment,
                    description: __('Payment :amount for :count fee(s) was held for verification', [
                        'amount' => number_format($amount, 2),
                        'count' => $invoices->count(),
                    ]),
                    newValues: [
                        'invoice_nos' => $invoices->pluck('invoice_no')->all(),
                        'amount' => $amount,
                        'method' => $payment->method,
                        'reference' => $payment->transaction_ref,
                    ],
                    actor: $actor,
                    schoolId: $schoolId,
                );

                return $payment->refresh();
            }

            $this->settle($actor, $payment, $invoices, $paidAt, $schoolId);

            return $payment->refresh();
        });

        if ($payment->status === 'posted') {
            $this->notify($payment);
        }

        return $payment;
    }

    /**
     * @param  array<int|string, float>  $lineAmounts
     * @return array<int, float>
     */
    private function normaliseLines(array $lineAmounts): array
    {
        $lines = [];

        foreach ($lineAmounts as $invoiceId => $lineAmount) {
            $id = (int) $invoiceId;

            if ($id <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => __('A fee record is required.'),
                ]);
            }

            $lines[$id] = round((float) $lineAmount, 2);
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'invoice' => __('Choose at least one fee to collect.'),
            ]);
        }

        return $lines;
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return array<int, float>
     */
    private function reservedByInvoice(array $invoiceIds): array
    {
        $rows = FeePaymentAllocation::query()
            ->whereIn('fee_invoice_id', $invoiceIds)
            ->whereHas('payment', fn ($query) => $query->where('status', 'pending'))
            ->selectRaw('fee_invoice_id, COALESCE(SUM(amount), 0) as reserved')
            ->groupBy('fee_invoice_id')
            ->get();

        return $rows->mapWithKeys(fn (object $row): array => [(int) $row->fee_invoice_id => round((float) $row->reserved, 2)])->all();
    }

    /**
     * Confirm a payment that was held because the online/bank transaction was
     * not yet confirmed. This is a manual verification step; no gateway is
     * contacted.
     */
    public function verify(User $actor, FeePayment $payment): FeePayment
    {
        return DB::transaction(function () use ($actor, $payment): FeePayment {
            $schoolId = $this->schoolId();

            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            /** @var FeePayment $locked */
            $locked = FeePayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status !== 'pending' || $locked->verification_status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => __('Only payments waiting for verification can be confirmed.'),
                ]);
            }

            $invoiceIds = FeePaymentAllocation::query()
                ->where('fee_payment_id', $locked->id)
                ->pluck('fee_invoice_id')
                ->all();

            if ($invoiceIds === []) {
                throw ValidationException::withMessages([
                    'payment' => __('This payment is not linked to an invoice.'),
                ]);
            }

            $invoices = FeeInvoice::query()
                ->whereIn('id', $invoiceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($invoice->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'payment' => __('Invoice :invoice was cancelled while this payment waited for verification.', [
                            'invoice' => $invoice->invoice_no,
                        ]),
                    ]);
                }
            }

            $lines = FeePaymentAllocation::query()
                ->where('fee_payment_id', $locked->id)
                ->get()
                ->keyBy('fee_invoice_id');

            $invoices = $invoices->filter(fn (FeeInvoice $invoice): bool => $lines->has($invoice->id))->values();

            foreach ($invoices as $invoice) {
                $received = round((float) $lines->get($invoice->id)->amount, 2);

                if ($received > round((float) $invoice->due_total, 2)) {
                    throw ValidationException::withMessages([
                        'payment' => __('The balance on invoice :invoice changed. Reject this payment and record it again.', [
                            'invoice' => $invoice->invoice_no,
                        ]),
                    ]);
                }
            }

            $locked->update([
                'status' => 'posted',
                'verification_status' => 'verified',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'rejection_reason' => null,
                'paid_at' => $locked->paid_at,
            ]);

            $this->settle($actor, $locked, $invoices, Carbon::parse($locked->paid_at), $schoolId);

            $this->audit->log(
                event: 'fee_payment.verified',
                auditable: $locked,
                description: __('Payment of :amount for :count fee record(s) was verified', [
                    'amount' => number_format((float) $locked->amount, 2),
                    'count' => $invoices->count(),
                ]),
                oldValues: ['status' => 'pending'],
                newValues: [
                    'status' => 'posted',
                    'verification_status' => 'verified',
                    'invoice_nos' => $invoices->pluck('invoice_no')->all(),
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $locked->refresh();
        });
    }

    public function rejectVerification(User $actor, FeePayment $payment, string $reason): FeePayment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): FeePayment {
            /** @var FeePayment $locked */
            $locked = FeePayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status !== 'pending' || $locked->verification_status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => __('Only payments waiting for verification can be rejected.'),
                ]);
            }

            $locked->allocations()->delete();

            $locked->update([
                'status' => 'rejected',
                'verification_status' => 'rejected',
                'rejection_reason' => $reason,
                'paid_at' => $locked->paid_at,
            ]);

            $this->audit->log(
                event: 'fee_payment.verification_rejected',
                auditable: $locked,
                description: __('Payment of :amount was rejected: :reason', [
                    'amount' => number_format((float) $locked->amount, 2),
                    'reason' => $reason,
                ]),
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'rejected', 'rejection_reason' => $reason],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function reverse(User $actor, FeePayment $payment, string $reason): FeePayment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): FeePayment {
            /** @var FeePayment $locked */
            $locked = FeePayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status !== 'posted') {
                throw ValidationException::withMessages([
                    'payment' => __('Only posted payments can be reversed.'),
                ]);
            }

            $allocations = FeePaymentAllocation::query()
                ->where('fee_payment_id', $locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                /** @var FeeInvoice $invoice */
                $invoice = FeeInvoice::query()->lockForUpdate()->findOrFail($allocation->fee_invoice_id);

                $paid = max(0, round((float) $invoice->paid_total - (float) $allocation->amount, 2));

                // Undoing the settlement undoes its write-off too, so the
                // invoice goes back to the fee that was never actually paid.
                $waived = max(0, round((float) $invoice->waived_total - (float) $allocation->waived_amount, 2));
                $due = max(0, round((float) $invoice->total - $paid - $waived, 2));

                $invoice->update([
                    'paid_total' => $paid,
                    'actual_collected_amount' => $paid,
                    'waived_total' => $waived,
                    'due_total' => $due,
                    'status' => FeeInvoice::statusForCollection((float) $invoice->total, $paid, $due),
                ]);
            }

            $this->ledger->reverseForSource($actor, $locked, $reason);

            $locked->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
                'paid_at' => $locked->paid_at,
            ]);

            $this->audit->log(
                event: 'fee_payment.reversed',
                auditable: $locked,
                description: __('Payment of :amount was reversed: :reason', [
                    'amount' => number_format((float) $locked->amount, 2),
                    'reason' => $reason,
                ]),
                oldValues: ['status' => 'posted', 'amount' => (float) $locked->amount],
                newValues: ['status' => 'reversed', 'reversal_reason' => $reason],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    /**
     * Receipt, invoice balances, cash ledger and audit entries for a confirmed
     * payment. Runs inside the caller's transaction with the school row locked.
     *
     * @param  Collection<int, FeeInvoice>  $invoices
     */
    private function settle(User $actor, FeePayment $payment, Collection $invoices, Carbon $paidAt, int $schoolId): void
    {
        $amount = round((float) $payment->amount, 2);

        $allocations = FeePaymentAllocation::query()
            ->where('fee_payment_id', $payment->id)
            ->get()
            ->keyBy('fee_invoice_id');

        $lines = [];

        foreach ($invoices as $invoice) {
            $allocation = $allocations->get($invoice->id);

            if ($allocation === null) {
                continue;
            }

            $received = round((float) $allocation->amount, 2);
            $paidTotal = round((float) $invoice->paid_total + $received, 2);

            // A write-off can never push the balance below zero.
            $waived = min(round((float) $allocation->waived_amount, 2), max(0, round((float) $invoice->total - $paidTotal, 2)));
            $dueTotal = max(0, round((float) $invoice->total - $paidTotal - $waived, 2));

            $invoice->update([
                'paid_total' => $paidTotal,
                'waived_total' => round((float) $invoice->waived_total + $waived, 2),
                'due_total' => $dueTotal,
                'status' => FeeInvoice::statusForCollection((float) $invoice->total, $paidTotal, $dueTotal),
            ]);

            $lines[] = [
                'invoice' => [
                    'invoice_no' => $invoice->invoice_no,
                    'billing_period' => $invoice->billing_period,
                    'total' => round((float) $invoice->total, 2),
                    'discount_total' => round((float) $invoice->discount_total, 2),
                    'paid_after' => $paidTotal,
                    'due_after' => $dueTotal,
                    'waived_after' => round((float) $invoice->waived_total, 2),
                    'settled_in_full' => $waived > 0,
                ],
                'received' => $received,
                'waived' => $waived,
            ];
        }

        $receiptNo = $this->numbers->next(FeeReceipt::class, 'receipt_no', 'RCP', (int) $paidAt->year, $schoolId);

        $snapshot = $this->snapshot($invoices->first(), $payment, $receiptNo, $lines, $actor);

        $receipt = FeeReceipt::query()->create([
            'fee_payment_id' => $payment->id,
            'receipt_no' => $receiptNo,
            'snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            'issued_at' => now(),
            'issued_by' => $actor->id,
        ]);

        $this->ledger->postIncome(
            actor: $actor,
            amount: $amount,
            date: $paidAt->toDateString(),
            description: __('Fee payment :receipt · :student', [
                'receipt' => $receiptNo,
                'student' => $invoices->first()->student?->full_name ?? __('Student'),
            ]),
            source: $payment,
            reference: $receiptNo,
            channel: $this->ledger->channelForMethod($payment->method),
            bankAccountId: $payment->bank_account_id !== null ? (int) $payment->bank_account_id : null,
        );

        $this->audit->log(
            event: 'fee_payment.posted',
            auditable: $payment,
            description: __('Payment :receipt of :amount was received for :count fee record(s)', [
                'receipt' => $receiptNo,
                'amount' => number_format($amount, 2),
                'count' => count($lines),
            ]),
            newValues: [
                'invoice_nos' => array_map(fn (array $line): string => $line['invoice']['invoice_no'], $lines),
                'receipt_no' => $receiptNo,
                'amount' => $amount,
                'method' => $payment->method,
                'lines' => count($lines),
                'snapshot_hash' => $receipt->snapshot_hash,
            ],
            actor: $actor,
            schoolId: $schoolId,
        );

        foreach ($lines as $line) {
            if ($line['waived'] <= 0) {
                continue;
            }

            $this->audit->log(
                event: 'fee_payment.settled_short',
                auditable: $payment,
                description: __('Invoice :invoice was settled with :received against a fee of :total; :waived was written off and is not a due.', [
                    'invoice' => $line['invoice']['invoice_no'],
                    'received' => number_format($line['received'], 2),
                    'total' => number_format($line['invoice']['total'], 2),
                    'waived' => number_format($line['waived'], 2),
                ]),
                newValues: [
                    'invoice_no' => $line['invoice']['invoice_no'],
                    'standard_fee' => $line['invoice']['total'],
                    'amount_received' => $line['received'],
                    'waived_amount' => $line['waived'],
                    'due_after' => $line['invoice']['due_after'],
                ],
                actor: $actor,
                schoolId: $schoolId,
            );
        }
    }

    private function notify(FeePayment $payment): void
    {
        try {
            app(SchoolEventNotifier::class)->feePaymentRecorded($payment);
        } catch (Throwable $exception) {
            // The payment and receipt are committed; notification delivery is
            // a follow-up concern and must not fail the cashier's request.
            report($exception);
        }
    }

    /**
     * @param  list<array{invoice: array<string, mixed>, received: float, waived: float}>  $lines
     * @return array<string, mixed>
     */
    private function snapshot(FeeInvoice $invoice, FeePayment $payment, string $receiptNo, array $lines, User $actor): array
    {
        $invoice->loadMissing(['student', 'enrollment.schoolClass', 'enrollment.section']);
        $school = School::query()->findOrFail((int) $invoice->school_id);

        return [
            'school' => [
                'name' => $school->name,
                'address' => $school->address,
                'phone' => $school->phone,
                'eiin' => $school->eiin,
            ],
            'student' => [
                'name' => $invoice->student?->full_name,
                'student_id' => $invoice->student?->student_id,
                'class' => $invoice->enrollment?->schoolClass?->name,
                'section' => $invoice->enrollment?->section?->name,
                'roll' => $invoice->enrollment?->roll_no,
            ],
            // 'invoice' stays the first line so older receipt renders keep
            // working; 'lines' carries the full breakdown of a multi-fee
            // collection.
            'invoice' => $lines[0]['invoice'],
            'lines' => $lines,
            'invoice_count' => count($lines),
            'payment' => [
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->transaction_ref,
                'paid_at' => $payment->paid_at?->toDateString(),
                'received_by' => $actor->name,
            ],
            'receipt_no' => $receiptNo,
            'issued_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The session, invoice and scheduled fee a payment was taken against, so a
     * register row needs no join. Production has no shell, so these columns only
     * exist once the owner has run the migration SQL; writing them conditionally
     * keeps the cash desk working on either schema.
     *
     * @return array<string, mixed>
     */
    private function billingContext(FeeInvoice $invoice): array
    {
        if (self::$contextColumns === null) {
            self::$contextColumns = array_values(array_intersect(
                Schema::getColumnListing('fee_payments'),
                ['academic_session_id', 'fee_invoice_id', 'scheduled_amount'],
            ));
        }

        if (count(self::$contextColumns) < 3) {
            return [];
        }

        return [
            'academic_session_id' => $invoice->academic_session_id,
            'fee_invoice_id' => $invoice->id,
            'scheduled_amount' => round((float) $invoice->total, 2),
        ];
    }

    /**
     * The fee the money was for. An invoice prices one line more often than not,
     * and a payment has to say which fee type it settled even when the counter
     * never named one.
     */
    private function feeTypeOf(FeeInvoice $invoice): ?int
    {
        $feeTypeId = $invoice->items()->orderBy('id')->value('fee_type_id');

        return $feeTypeId === null ? null : (int) $feeTypeId;
    }

    /**
     * Money always carries a readable id. A provider reference is kept exactly
     * as typed; cash at the counter gets a unique one so the register, the
     * receipt and a later query all name the same transaction.
     */
    private function reference(mixed $typed): string
    {
        $reference = trim((string) $typed);

        if ($reference !== '') {
            return $reference;
        }

        do {
            $reference = 'TXN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (FeePayment::query()->where('transaction_ref', $reference)->exists());

        return $reference;
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
