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
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * Every learner the desk can collect from this session, with the class and
     * roll the accountant needs to recognise them. The student comes first on
     * the screen; the class is derived from this enrollment.
     *
     * @return EloquentCollection<int, Enrollment>
     */
    public function enrollments(?AcademicSession $session): EloquentCollection
    {
        if ($session === null) {
            return new EloquentCollection;
        }

        return Enrollment::query()
            ->with(['student', 'schoolClass', 'section'])
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->whereHas('student')
            ->orderBy('class_id')
            ->orderBy('roll_no')
            ->orderBy('id')
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null)
            ->unique('student_id')
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
     * The billing period the collection belongs to. A monthly fee needs its
     * month. A one-time, yearly or exam fee does not: it is billed once for the
     * session under a session-wide period, so partial receipts pile up on the
     * same invoice instead of creating one invoice per month.
     */
    public function periodFor(AcademicSession $session, ?int $month, FeeType $feeType): string
    {
        if (! $feeType->isMonthly()) {
            $start = $session->start_date ?? now();

            return sprintf('%04d-S1', (int) $start->year);
        }

        if ($month === null) {
            throw ValidationException::withMessages([
                'month' => __('Choose the month this fee belongs to.'),
            ]);
        }

        return $this->period($this->yearFor($session, $month), $month);
    }

    /**
     * A session may run across two calendar years; a January collection in an
     * April-to-March session belongs to the closing year.
     */
    private function yearFor(AcademicSession $session, int $month): int
    {
        $start = $session->start_date ?? now()->startOfYear();
        $end = $session->end_date ?? $start->copy()->endOfYear();

        if ((int) $start->year !== (int) $end->year && $month < (int) $start->month) {
            return (int) $end->year;
        }

        return (int) $start->year;
    }

    public function isSessionPeriod(string $period): bool
    {
        return str_ends_with($period, '-S1');
    }

    /**
     * What the counter should show before the accountant types an amount.
     *
     * @return array{enrollment: ?Enrollment, invoice: ?FeeInvoice, scheduled: float, paid: float, waived: float, balance: float, description: ?string, configured: bool}
     */
    public function snapshot(
        Student $student,
        AcademicSession $session,
        int $classId,
        FeeType $feeType,
        string $period,
        bool $lock = false,
    ): array {
        $enrollment = $this->enrollmentFor($student, $session);

        if ($enrollment === null) {
            return [
                'enrollment' => null,
                'invoice' => null,
                'scheduled' => 0.0,
                'paid' => 0.0,
                'waived' => 0.0,
                'balance' => 0.0,
                'description' => null,
                'configured' => false,
            ];
        }

        $configured = true;

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
            $line = $this->scheduledLine($student, $session, $feeType, $this->issueDate($session, $period));
            $scheduled = round((float) ($line['amount'] ?? 0), 2);
            $description = $line !== null ? (string) $line['description'] : null;
            $configured = $line !== null;
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
            'configured' => $configured,
        ];
    }

    /**
     * @param  array{class_id: int|string, student_id: int|string, academic_session_id: int|string, fee_type_id: int|string, year?: int|string|null, month?: int|string|null, amount: float|int|string, method: string, paid_at?: string|null, transaction_ref?: string|null, bank_account_id?: int|string|null, notes?: string|null, idempotency_key: string}  $data
     */
    public function collect(User $actor, array $data): FeePayment
    {
        $this->assertAccountant($actor);

        $session = AcademicSession::query()->findOrFail((int) $data['academic_session_id']);
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $feeType = FeeType::query()->findOrFail((int) $data['fee_type_id']);
        $classId = (int) $data['class_id'];
        $month = filled($data['month'] ?? null) ? (int) $data['month'] : null;
        $period = $this->periodFor($session, $month, $feeType);
        $amount = round((float) $data['amount'], 2);
        $paidAt = filled($data['paid_at'] ?? null) ? Carbon::parse((string) $data['paid_at']) : now();

        $this->authority->assertMayCollectFor($actor, $student, $session);

        $enrollment = $this->enrollmentFor($student, $session);

        if ($enrollment === null || (int) $enrollment->class_id !== $classId) {
            throw ValidationException::withMessages([
                'student_id' => __('This student is not actively enrolled in the selected class for this session.'),
            ]);
        }

        $reference = trim((string) ($data['transaction_ref'] ?? ''));

        if ($reference === '') {
            $reference = $this->nextTransactionId();
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Enter the amount received.'),
            ]);
        }

        try {
            return DB::transaction(function () use ($actor, $data, $session, $student, $feeType, $classId, $month, $period, $amount, $paidAt, $reference): FeePayment {
                $invoice = $this->invoiceFor($actor, $student, $session, $feeType, $classId, $period, $paidAt);

                $snapshot = $this->snapshot($student, $session, $classId, $feeType, $period, true);

                if (! $snapshot['configured']) {
                    throw ValidationException::withMessages([
                        'fee_type_id' => __('This fee type has no configured amount for the selected class and session.'),
                    ]);
                }

                if ($snapshot['balance'] <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => __('This fee is already fully collected for the selected period.'),
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
                    'transaction_ref' => $reference,
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => (string) $data['idempotency_key'],
                    'hold_for_verification' => false,
                    'settle_in_full' => false,
                    'class_id' => $classId,
                    'fee_type_id' => $feeType->id,
                    'billing_month' => $period,
                    'year' => (int) substr($period, 0, 4),
                    'month' => $feeType->isMonthly() ? $month : null,
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

    public function enrollmentFor(Student $student, AcademicSession $session): ?Enrollment
    {
        return Enrollment::query()
            ->with(['schoolClass', 'section'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The date the fee is quoted at. A monthly period names its month; the
     * session-wide period for a one-time fee is quoted at the session opening.
     */
    private function issueDate(AcademicSession $session, string $period): Carbon
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches) === 1) {
            return Carbon::create((int) $matches[1], (int) $matches[2], 1)->startOfMonth();
        }

        return ($session->start_date ?? now())->copy()->startOfMonth();
    }

    /**
     * @return array{fee_type_id: int, description: string, fee_type_name: string, amount: float, source: string}|null
     */
    private function scheduledLine(Student $student, AcademicSession $session, FeeType $feeType, Carbon $issueDate): ?array
    {
        $quote = $this->invoices->quoteFor($student, $session, $issueDate);

        /** @var array{fee_type_id: int, description: string, fee_type_name: string, amount: float, source: string}|null $line */
        $line = collect($quote['lines'])->firstWhere('fee_type_id', (int) $feeType->id);

        return $line;
    }

    /**
     * A readable, unique id for money that arrived without a provider
     * reference (cash at the counter).
     */
    private function nextTransactionId(): string
    {
        do {
            $id = 'TXN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (FeePayment::query()->where('transaction_ref', $id)->exists());

        return $id;
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
            $line = $this->scheduledLine($student, $session, $feeType, $this->issueDate($session, $period));

            if ($line !== null) {
                $this->appendCharge($invoice, (float) $line['amount'], (string) $line['description'], (int) $feeType->id);
                $invoice->refresh();
            }
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
