<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The accountant counter. It answers the three questions the desk actually
 * asks: which class, which learner, which month, and what fee is still due.
 *
 * A payment is never written against a guessed invoice. The invoice is locked,
 * the selected fee line is located (or appended once), and the amount is capped
 * by what remains on that line before FeePaymentService is allowed to run.
 */
class AccountantFeeCollectionService
{
    public function __construct(
        private readonly FeeCollectionAuthority $authority,
        private readonly FeeInvoiceService $invoices,
        private readonly FeePaymentService $payments,
    ) {}

    /**
     * @return EloquentCollection<int, AcademicSession>
     */
    public function sessions(): EloquentCollection
    {
        return AcademicSession::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, SchoolClass>
     */
    public function classes(): EloquentCollection
    {
        return SchoolClass::query()
            ->when(\Schema::hasColumn('classes', 'is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Student>
     */
    public function students(?AcademicSession $session, ?int $classId): EloquentCollection
    {
        if ($session === null || $classId === null) {
            return new EloquentCollection;
        }

        return Enrollment::query()
            ->with('student')
            ->where('academic_session_id', $session->id)
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->orderBy('id')
            ->get()
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @return EloquentCollection<int, FeeType>
     */
    public function feeTypes(?AcademicSession $session, ?int $classId): EloquentCollection
    {
        if ($session === null || $classId === null) {
            return new EloquentCollection;
        }

        return FeeType::query()
            ->active()
            ->where(function ($query) use ($session, $classId): void {
                $query->whereHas('structures', fn ($structure) => $structure
                    ->where('academic_session_id', $session->id)
                    ->where('class_id', $classId)
                    ->where('is_active', true))
                    ->orWhereHas('assignments', fn ($assignment) => $assignment
                        ->where('academic_session_id', $session->id)
                        ->where('class_id', $classId)
                        ->where('is_active', true));
            })
            ->orderBy('name')
            ->get();
    }

    public function period(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * What the counter should show before the accountant types an amount.
     *
     * @return array{enrollment: ?Enrollment, invoice: ?FeeInvoice, scheduled: float, paid: float, waived: float, balance: float, description: ?string}
     */
    public function snapshot(
        Student $student,
        AcademicSession $session,
        int $classId,
        FeeType $feeType,
        string $period,
        bool $lock = false,
    ): array {
        $enrollment = $this->enrollmentFor($student, $session, $classId);

        if ($enrollment === null) {
            return [
                'enrollment' => null,
                'invoice' => null,
                'scheduled' => 0.0,
                'paid' => 0.0,
                'waived' => 0.0,
                'balance' => 0.0,
                'description' => null,
            ];
        }

        $invoiceQuery = FeeInvoice::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('billing_period', $period)
            ->where('status', '!=', 'cancelled');

        if ($lock) {
            $invoiceQuery->lockForUpdate();
        }

        /** @var FeeInvoice|null $invoice */
        $invoice = $invoiceQuery->first();

        $itemQuery = $invoice?->items()->where('fee_type_id', $feeType->id);
        if ($itemQuery !== null && $lock) {
            $itemQuery->lockForUpdate();
        }

        $item = $itemQuery?->first();

        if ($item !== null) {
            $scheduled = round((float) $item->line_total, 2);
            $description = (string) ($item->description ?: $feeType->name);
        } else {
            $line = $this->scheduledLine($student, $session, $feeType, $period);
            $scheduled = round((float) $line['amount'], 2);
            $description = (string) $line['description'];
        }

        $paid = round((float) FeePayment::query()
            ->posted()
            ->where('student_id', $student->id)
            ->where('class_id', $classId)
            ->where('fee_type_id', $feeType->id)
            ->where('billing_month', $period)
            ->sum('amount'), 2);

        $waived = 0.0;

        // Old payments and settlements only have an invoice-level total.
        // When the invoice holds this single fee line, that total is the most
        // reliable figure and closes the gap left by the tracking columns.
        if ($invoice !== null && $item !== null && $invoice->items()->count() === 1) {
            $paid = max($paid, round((float) $invoice->paid_total, 2));
            $waived = round((float) $invoice->waived_total, 2);
        }

        $balance = max(0, round($scheduled - $paid - $waived, 2));

        if ($invoice !== null && $item !== null) {
            $balance = min($balance, max(0, round((float) $invoice->due_total, 2)));
        }

        return [
            'enrollment' => $enrollment,
            'invoice' => $invoice,
            'scheduled' => $scheduled,
            'paid' => $paid,
            'waived' => $waived,
            'balance' => round($balance, 2),
            'description' => $description,
        ];
    }

    /**
     * @param  array{class_id: int|string, student_id: int|string, academic_session_id: int|string, fee_type_id: int|string, year: int|string, month: int|string, amount: float|int|string, method: string, paid_at?: string|null, transaction_ref?: string|null, bank_account_id?: int|string|null, notes?: string|null, idempotency_key: string}  $data
     */
    public function collect(User $actor, array $data): FeePayment
    {
        $this->assertAccountant($actor);

        $session = AcademicSession::query()->findOrFail((int) $data['academic_session_id']);
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $feeType = FeeType::query()->findOrFail((int) $data['fee_type_id']);
        $classId = (int) $data['class_id'];
        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $period = $this->period($year, $month);
        $amount = round((float) $data['amount'], 2);
        $paidAt = filled($data['paid_at'] ?? null) ? Carbon::parse((string) $data['paid_at']) : now();

        $this->authority->assertMayCollectFor($actor, $student, $session);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Enter the amount received.'),
            ]);
        }

        try {
            return DB::transaction(function () use ($actor, $data, $session, $student, $feeType, $classId, $month, $year, $period, $amount, $paidAt): FeePayment {
                $invoice = $this->invoiceFor($actor, $student, $session, $feeType, $classId, $period, $paidAt);

                $snapshot = $this->snapshot($student, $session, $classId, $feeType, $period, true);

                if ($snapshot['balance'] <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => __('This fee is already settled for the selected month.'),
                    ]);
                }

                if ($amount > round($snapshot['balance'] + 0.009, 2)) {
                    throw ValidationException::withMessages([
                        'amount' => __('The amount cannot be more than the :amount still due for this fee.', [
                            'amount' => number_format($snapshot['balance'], 2),
                        ]),
                    ]);
                }

                $payment = $this->payments->collect($actor, $invoice, [
                    'amount' => $amount,
                    'method' => (string) $data['method'],
                    'paid_at' => $paidAt->toDateTimeString(),
                    'transaction_ref' => $data['transaction_ref'] ?? null,
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => (string) $data['idempotency_key'],
                    'hold_for_verification' => false,
                    'settle_in_full' => false,
                    'class_id' => $classId,
                    'fee_type_id' => $feeType->id,
                    'billing_month' => $period,
                    'year' => $year,
                    'month' => $month,
                    'invoice_no' => $invoice->invoice_no,
                ]);

                return $payment->fresh([
                    'student',
                    'schoolClass',
                    'feeType',
                    'receiver',
                    'receipt',
                    'allocations.invoice',
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'amount' => __('The payment could not be saved. Please try again.'),
            ]);
        }
    }

    public function assertAccountant(User $actor): void
    {
        if (! $actor->hasRole('accountant') || $actor->hasAnyRole(['school_admin', 'principal']) || ! $actor->can('fees.collect')) {
            abort(403, __('Only an accountant can use the fee counter.'));
        }
    }

    /**
     * @return EloquentCollection<int, FeePayment>
     */
    public function history(User $actor, ?int $studentId = null): EloquentCollection
    {
        return FeePayment::query()
            ->with(['student', 'schoolClass', 'feeType', 'receiver', 'receipt', 'allocations.invoice'])
            ->where('received_by', $actor->id)
            ->when($studentId !== null, fn ($query) => $query->where('student_id', $studentId))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    private function enrollmentFor(Student $student, AcademicSession $session, int $classId): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{fee_type_id: int, description: string, fee_type_name: string, amount: float, source: string}
     */
    private function scheduledLine(Student $student, AcademicSession $session, FeeType $feeType, string $period): array
    {
        [$year, $month] = array_map('intval', explode('-', $period));
        $issueDate = Carbon::create($year, $month, 1)->startOfMonth();

        $quote = $this->invoices->quoteFor($student, $session, $issueDate);

        /** @var array{fee_type_id: int, description: string, fee_type_name: string, amount: float, source: string}|null $line */
        $line = collect($quote['lines'])->firstWhere('fee_type_id', (int) $feeType->id);

        if ($line === null) {
            throw ValidationException::withMessages([
                'fee_type_id' => __('This fee type has no configured amount for the selected class and month.'),
            ]);
        }

        return $line;
    }

    private function invoiceFor(
        User $actor,
        Student $student,
        AcademicSession $session,
        FeeType $feeType,
        int $classId,
        string $period,
        Carbon $paidAt,
    ): FeeInvoice {
        $query = fn () => FeeInvoice::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('billing_period', $period)
            ->where('status', '!=', 'cancelled');

        /** @var FeeInvoice|null $invoice */
        $invoice = $query()->lockForUpdate()->first();

        if ($invoice === null) {
            // The standard generator applies the configured amount, automatic
            // discounts and the invoice number series. It can refuse because a
            // concurrent request already issued the month, so re-read under the
            // lock before treating that refusal as fatal.
            try {
                $this->invoices->generate($actor, [
                    'academic_session_id' => $session->id,
                    'class_id' => $classId,
                    'section_id' => null,
                    'fee_type_ids' => [$feeType->id],
                    'student_ids' => [$student->id],
                    'billing_period' => $period,
                    'issue_date' => $paidAt->toDateString(),
                    'due_date' => $paidAt->toDateString(),
                    'notes' => null,
                ]);
            } catch (ValidationException) {
                // Re-read below; if an invoice exists we can add the missing
                // fee line to it instead of failing the collection.
            }

            $invoice = $query()->lockForUpdate()->first();
        }

        if ($invoice === null) {
            throw ValidationException::withMessages([
                'fee_type_id' => __('The invoice for this month could not be created.'),
            ]);
        }

        $item = $invoice->items()->where('fee_type_id', $feeType->id)->lockForUpdate()->first();

        if ($item === null) {
            $line = $this->scheduledLine($student, $session, $feeType, $period);
            $this->appendCharge($invoice, (float) $line['amount'], (string) $line['description'], (int) $feeType->id);
            $invoice->refresh();
        }

        return $invoice;
    }

    private function appendCharge(FeeInvoice $invoice, float $amount, string $description, int $feeTypeId): void
    {
        if ($amount <= 0) {
            return;
        }

        $invoice->items()->create([
            'fee_type_id' => $feeTypeId,
            'description' => $description,
            'quantity' => 1,
            'unit_amount' => $amount,
            'discount_amount' => 0,
            'line_total' => $amount,
        ]);

        $subtotal = round((float) $invoice->subtotal + $amount, 2);
        $discountTotal = round((float) $invoice->discounts()->active()->sum('amount'), 2);
        $total = max(0, round($subtotal - $discountTotal, 2));
        $paid = round((float) $invoice->paid_total, 2);
        $waived = round((float) $invoice->waived_total, 2);
        $due = max(0, round($total - $paid - $waived, 2));

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'configured_fee_amount' => $subtotal,
            'approved_discount_amount' => $discountTotal,
            'final_payable_amount' => $total,
            'total' => $total,
            'due_total' => $due,
            'status' => FeeInvoice::statusForCollection($total, $paid, $due),
        ]);
    }
}
