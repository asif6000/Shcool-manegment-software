<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeDiscount;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceDiscount;
use App\Models\FeeInvoiceItem;
use App\Models\FeeStudentAssignment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NumberSequence;
use App\Support\SchoolContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeInvoiceService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberSequence $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{invoices: list<FeeInvoice>, skipped: int, total: float}
     */
    public function generate(User $actor, array $data): array
    {
        $schoolId = $this->schoolId();
        $sessionId = (int) $data['academic_session_id'];
        $classId = (int) $data['class_id'];
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        $typeIds = array_values(array_unique(array_map('intval', (array) $data['fee_type_ids'])));
        $studentIds = array_values(array_unique(array_map('intval', (array) ($data['student_ids'] ?? []))));
        $billingPeriod = filled($data['billing_period'] ?? null) ? (string) $data['billing_period'] : null;
        $issueDate = Carbon::parse((string) $data['issue_date']);
        $dueDate = Carbon::parse((string) $data['due_date']);

        // An amount typed on the billing screen for a fee that has no class fee
        // and no assignment yet. It is the only way a freshly created fee type
        // can be billed before anyone configures a rate for it.
        $enteredAmounts = Collection::make($data['amounts'] ?? [])
            ->filter(fn ($amount): bool => is_numeric($amount) && (float) $amount > 0)
            ->mapWithKeys(fn ($amount, $typeId): array => [(int) $typeId => round((float) $amount, 2)]);

        if ($typeIds === []) {
            throw ValidationException::withMessages([
                'fee_type_ids' => __('Choose at least one fee type.'),
            ]);
        }

        $structures = FeeStructure::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->whereIn('fee_type_id', $typeIds)
            ->where('is_active', true)
            ->with(['feeType', 'schoolClass'])
            ->orderBy('id')
            ->get()
            ->keyBy('fee_type_id');

        $enrollments = Enrollment::query()
            ->with(['student', 'section'])
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->when($sectionId !== null, fn ($query) => $query->where('section_id', $sectionId))
            ->when($studentIds !== [], fn ($query) => $query->whereIn('student_id', $studentIds))
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null);

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'class_id' => $studentIds === []
                    ? __('There are no active students enrolled in this class for the selected session.')
                    : __('The selected students have no active enrollment in this class for the chosen session.'),
            ]);
        }

        $studentIds = $enrollments->pluck('student_id')->unique()->values();

        // A fee can also reach a student through an explicit assignment, which
        // is how a section or a named list of learners gets billed.
        $assignmentsByStudent = FeeStudentAssignment::query()
            ->where('academic_session_id', $sessionId)
            ->whereIn('student_id', $studentIds)
            ->whereIn('fee_type_id', $typeIds)
            ->active()
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('fee_type_id'));

        $assignedTypeIds = $assignmentsByStudent
            ->flatten()
            ->filter(fn (FeeStudentAssignment $assignment): bool => $assignment->coversDate($issueDate))
            ->pluck('fee_type_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $feeTypes = FeeType::query()->active()->whereIn('id', $typeIds)->get(['id', 'name'])->keyBy('id');

        $retired = array_values(array_diff($typeIds, $feeTypes->keys()->map(fn ($id): int => (int) $id)->all()));

        if ($retired !== []) {
            throw ValidationException::withMessages([
                'fee_type_ids' => __(':names is deactivated or deleted, so it cannot be billed any more. Reactivate it first, or generate the invoice without it.', [
                    'names' => $this->feeNames($retired),
                ]),
            ]);
        }

        $uncovered = array_values(array_diff(
            $typeIds,
            array_map(fn ($id): int => (int) $id, $structures->keys()->all()),
            $assignedTypeIds,
            array_map(fn (int $id): int => $id, $enteredAmounts->keys()->all()),
        ));

        if ($uncovered !== []) {
            $names = $this->feeNames($uncovered);

            throw ValidationException::withMessages([
                'fee_type_ids' => ($names === '' ? __('This fee') : $names).' '
                    .__('has no class fee, no fee assigned to this student and no amount typed, so it cannot be billed. Enter the amount on the billing screen or set the class fee first.'),
            ]);
        }

        $alreadyInvoiced = FeeInvoice::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_session_id', $sessionId)
            ->where('status', '!=', 'cancelled')
            ->when(
                $billingPeriod !== null,
                fn ($query) => $query->where('billing_period', $billingPeriod),
                fn ($query) => $query->whereHas('items', fn ($query) => $query->whereIn('fee_type_id', $typeIds)),
            )
            ->pluck('student_id')
            ->all();

        $eligible = $enrollments
            ->reject(fn (Enrollment $enrollment): bool => in_array($enrollment->student_id, $alreadyInvoiced, true))
            ->values();

        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'class_id' => __('Every active student in this class already has an invoice for the selected fee types and period.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $schoolId, $sessionId, $classId, $billingPeriod, $issueDate, $dueDate, $structures, $enrollments, $eligible, $typeIds, $feeTypes, $assignmentsByStudent, $enteredAmounts): array {
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            if ($billingPeriod !== null) {
                // The eligibility check above ran before this transaction, so a
                // concurrent generate may have billed the same period while we
                // waited for the school lock. Re-read under the lock.
                $invoicedNow = FeeInvoice::query()
                    ->whereIn('student_id', $eligible->pluck('student_id'))
                    ->where('academic_session_id', $sessionId)
                    ->where('status', '!=', 'cancelled')
                    ->where('billing_period', $billingPeriod)
                    ->lockForUpdate()
                    ->pluck('student_id')
                    ->all();

                $eligible = $eligible
                    ->reject(fn (Enrollment $enrollment): bool => in_array($enrollment->student_id, $invoicedNow, true))
                    ->values();
            }

            if ($eligible->isEmpty()) {
                throw ValidationException::withMessages([
                    'class_id' => __('Every active student in this class already has an invoice for the selected fee types and period.'),
                ]);
            }

            $invoices = [];
            $total = 0.0;

            foreach ($eligible as $enrollment) {
                $lines = $this->linesFor($enrollment, $typeIds, $structures, $assignmentsByStudent, $feeTypes, $issueDate, $enteredAmounts);

                if ($lines === []) {
                    continue;
                }

                try {
                    $invoice = FeeInvoice::query()->create([
                        'academic_session_id' => $sessionId,
                        'student_id' => $enrollment->student_id,
                        'enrollment_id' => $enrollment->id,
                        'invoice_no' => $this->numbers->next(FeeInvoice::class, 'invoice_no', 'INV', (int) $issueDate->year, $schoolId),
                        'billing_period' => $billingPeriod,
                        'issue_date' => $issueDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'subtotal' => 0,
                        'discount_total' => 0,
                        'configured_fee_amount' => 0,
                        'approved_discount_amount' => 0,
                        'final_payable_amount' => 0,
                        'total' => 0,
                        'paid_total' => 0,
                        'actual_collected_amount' => 0,
                        'due_total' => 0,
                        'status' => 'issued',
                        'created_by' => $actor->id,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // The unique billing key caught a race that started before
                    // this transaction held the school lock.
                    continue;
                }

                $subtotal = 0.0;

                // The amounts are copied here on purpose. A later change to the
                // fee type, class fee or assignment must not rewrite an invoice
                // that has already been issued.
                foreach ($lines as $line) {
                    $invoice->items()->create([
                        'fee_type_id' => $line['fee_type_id'],
                        'description' => $line['description'],
                        'quantity' => 1,
                        'unit_amount' => $line['amount'],
                        'discount_amount' => 0,
                        'line_total' => $line['amount'],
                    ]);

                    $subtotal += $line['amount'];
                }

                $subtotal = round($subtotal, 2);

                $invoice->update([
                    'subtotal' => $subtotal,
                    'configured_fee_amount' => $subtotal,
                    'total' => $subtotal,
                    'final_payable_amount' => $subtotal,
                    'due_total' => $subtotal,
                ]);

                $this->applyAutomaticDiscounts($actor, $invoice, $issueDate);
                $invoice->refresh();

                $invoices[] = $invoice;
                $total += (float) $invoice->total;
            }

            $this->audit->log(
                event: 'fee_invoices.generated',
                auditable: null,
                description: __(':count fee invoices were generated for :class', [
                    'count' => count($invoices),
                    'class' => $structures->first()?->schoolClass?->name ?? (string) $classId,
                ]),
                newValues: [
                    'academic_session_id' => $sessionId,
                    'class_id' => $classId,
                    'billing_period' => $billingPeriod,
                    'invoice_nos' => array_slice(array_map(fn (FeeInvoice $invoice): string => $invoice->invoice_no, $invoices), 0, 25),
                    'count' => count($invoices),
                    'total' => round($total, 2),
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return [
                'invoices' => $invoices,
                'skipped' => $enrollments->count() - count($invoices),
                'total' => round($total, 2),
            ];
        });
    }

    /**
     * One invoice for one student, written by hand: the admin picks the fee
     * type and types the amount. The billing period follows the same rule the
     * counter uses — a monthly fee names its month, a one-time fee is billed
     * once for the whole session under a session-wide period.
     *
     * @param  array<string, mixed>  $data
     */
    public function generateForStudent(User $actor, array $data): FeeInvoice
    {
        $schoolId = $this->schoolId();
        $session = AcademicSession::query()->findOrFail((int) $data['academic_session_id']);
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $feeType = FeeType::query()->findOrFail((int) $data['fee_type_id']);
        $month = (int) $data['month'];
        $amount = round((float) $data['amount'], 2);
        $issueDate = now()->startOfDay();

        if (! $feeType->is_active) {
            throw ValidationException::withMessages([
                'fee_type_id' => __(':name is deactivated, so it cannot be billed any more. Reactivate it first.', ['name' => $feeType->name]),
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('The invoice amount must be greater than zero.'),
            ]);
        }

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'student_id' => __('This student has no active enrollment in the selected session, so no invoice can be raised.'),
            ]);
        }

        $period = $this->periodFor($session, $month, $feeType);
        $dueDate = $issueDate->copy()->addDays(10);

        return DB::transaction(function () use ($actor, $schoolId, $session, $student, $feeType, $enrollment, $period, $amount, $issueDate, $dueDate): FeeInvoice {
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            /** @var FeeInvoice|null $invoice */
            $invoice = FeeInvoice::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $session->id)
                ->where('billing_period', $period)
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($invoice !== null) {
                $invoice = $this->appendLineOrFail($invoice, $feeType, $amount);
            } else {
                try {
                    $invoice = FeeInvoice::query()->create([
                        'academic_session_id' => $session->id,
                        'student_id' => $student->id,
                        'enrollment_id' => $enrollment->id,
                        'invoice_no' => $this->numbers->next(FeeInvoice::class, 'invoice_no', 'INV', (int) $issueDate->year, $schoolId),
                        'billing_period' => $period,
                        'issue_date' => $issueDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'subtotal' => $amount,
                        'discount_total' => 0,
                        'configured_fee_amount' => $amount,
                        'approved_discount_amount' => 0,
                        'final_payable_amount' => $amount,
                        'total' => $amount,
                        'paid_total' => 0,
                        'actual_collected_amount' => 0,
                        'due_total' => $amount,
                        'status' => 'issued',
                        'created_by' => $actor->id,
                    ]);

                    $invoice->items()->create([
                        'fee_type_id' => $feeType->id,
                        'description' => (string) $feeType->name,
                        'quantity' => 1,
                        'unit_amount' => $amount,
                        'discount_amount' => 0,
                        'line_total' => $amount,
                    ]);

                    $this->applyAutomaticDiscounts($actor, $invoice, $issueDate->toDateString());
                    $invoice->refresh();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'month' => __('This student already has an active invoice for the selected period.'),
                    ]);
                }
            }

            $this->audit->log(
                event: 'fee_invoices.generated',
                auditable: $invoice,
                description: __('Invoice :invoice was generated for :student', [
                    'invoice' => $invoice->invoice_no,
                    'student' => $student->full_name,
                ]),
                newValues: [
                    'academic_session_id' => (int) $session->id,
                    'student_id' => (int) $student->id,
                    'fee_type_id' => (int) $feeType->id,
                    'billing_period' => $period,
                    'amount' => $amount,
                    'total' => (float) $invoice->total,
                    'status' => $invoice->status,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $invoice->refresh();
        });
    }

    /**
     * The month already has an active invoice for this student, so the fee
     * either lands on that invoice as a new line or the request is refused.
     * An invoice with recorded payments is never touched — its financial
     * history stays exactly as it was.
     */
    private function appendLineOrFail(FeeInvoice $invoice, FeeType $feeType, float $amount): FeeInvoice
    {
        if ($invoice->items()->whereHas('feeType', fn ($query) => $query->whereKey($feeType->id))->exists()) {
            throw ValidationException::withMessages([
                'fee_type_id' => __('This student already has an invoice for :fee in this period.', ['fee' => $feeType->name]),
            ]);
        }

        if ($invoice->status !== 'issued' || (float) $invoice->paid_total > 0 || (float) $invoice->waived_total > 0) {
            throw ValidationException::withMessages([
                'month' => __('The student already has an invoice for this period that cannot take another fee line. Collect or reverse against the existing invoice.'),
            ]);
        }

        $invoice->items()->create([
            'fee_type_id' => $feeType->id,
            'description' => (string) $feeType->name,
            'quantity' => 1,
            'unit_amount' => $amount,
            'discount_amount' => 0,
            'line_total' => $amount,
        ]);

        $subtotal = round((float) $invoice->items()->sum('line_total'), 2);
        $invoice->update(['subtotal' => $subtotal, 'configured_fee_amount' => $subtotal]);

        $this->recalculateDiscounts($invoice);

        return $invoice;
    }

    /**
     * What one student would be billed right now, using exactly the rules the
     * generator applies. The billing screen shows this before any invoice row
     * is written, so the counter and the page can never disagree.
     *
     * Every active fee type is offered, not only the ones that already have a
     * rate, so a fee created a minute ago can be billed without a database
     * change. A line with no amount is one the office has to price by hand.
     *
     * @return array{enrollment: ?Enrollment, lines: list<array{fee_type_id: int, description: string, fee_type_name: string, amount: float|null, source: string}>, existing: Collection<int, FeeInvoice>}
     */
    public function quoteFor(Student $student, AcademicSession $session, Carbon $issueDate): array
    {
        $enrollment = Enrollment::query()
            ->with(['section', 'schoolClass'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            return ['enrollment' => null, 'lines' => [], 'existing' => FeeInvoice::query()->whereRaw('1 = 0')->get()];
        }

        $feeTypes = FeeType::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        $structures = FeeStructure::query()
            ->where('academic_session_id', $session->id)
            ->where('class_id', $enrollment->class_id)
            ->where('is_active', true)
            ->with('feeType')
            ->orderBy('id')
            ->get()
            ->filter(fn (FeeStructure $structure): bool => $feeTypes->has((int) $structure->fee_type_id))
            ->keyBy('fee_type_id');

        $assignments = FeeStudentAssignment::query()
            ->where('academic_session_id', $session->id)
            ->where('student_id', $student->id)
            ->active()
            ->get()
            ->filter(fn (FeeStudentAssignment $assignment): bool => $feeTypes->has((int) $assignment->fee_type_id))
            ->keyBy('fee_type_id');

        $typeIds = $feeTypes->keys()->map(fn ($id): int => (int) $id)->values()->all();

        $lines = $this->linesFor(
            $enrollment,
            $typeIds,
            $structures,
            new Collection([$student->id => $assignments]),
            $feeTypes,
            $issueDate,
        );

        $priced = array_map(fn (array $line): int => (int) $line['fee_type_id'], $lines);

        foreach ($feeTypes as $feeType) {
            if (in_array((int) $feeType->id, $priced, true)) {
                continue;
            }

            $lines[] = [
                'fee_type_id' => (int) $feeType->id,
                'description' => (string) $feeType->name,
                'fee_type_name' => (string) $feeType->name,
                'amount' => null,
                'source' => 'manual',
            ];
        }

        return [
            'enrollment' => $enrollment,
            'lines' => $lines,
            'existing' => FeeInvoice::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $session->id)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('billing_period')
                ->get(['id', 'invoice_no', 'billing_period', 'total', 'paid_total', 'due_total', 'status']),
        ];
    }

    /**
     * The fee lines one student is actually billed for this run: the class fee
     * unless an assignment names this learner, in which case the assignment
     * decides both applicability and amount. When neither has a rate, the
     * amount typed on the billing screen is used, if there is one.
     *
     * @param  list<int>  $typeIds
     * @param  Collection<int, FeeStructure>  $structures
     * @param  Collection<int, Collection<int, FeeStudentAssignment>>  $assignmentsByStudent
     * @param  Collection<int, FeeType>  $feeTypes
     * @param  Collection<int, float>|null  $enteredAmounts
     * @return list<array{fee_type_id: int, description: string, fee_type_name: string, amount: float, source: string}>
     */
    private function linesFor(
        Enrollment $enrollment,
        array $typeIds,
        Collection $structures,
        Collection $assignmentsByStudent,
        Collection $feeTypes,
        Carbon $issueDate,
        ?Collection $enteredAmounts = null,
    ): array {
        $assigneds = $assignmentsByStudent->get($enrollment->student_id, new Collection);
        $lines = [];

        foreach ($typeIds as $typeId) {
            /** @var FeeStructure|null $structure */
            $structure = $structures->get($typeId);
            $assignment = $assigneds->get($typeId);

            if ($assignment !== null && ! $assignment->coversDate($issueDate)) {
                continue;
            }

            $classAmount = $structure !== null && $structure->coversDate($issueDate)
                ? round((float) $structure->amount, 2)
                : null;

            // Without an assignment the class fee stands on its own; the
            // nullsafe call operator would swallow it, so branch explicitly.
            $amount = $assignment !== null
                ? $assignment->effectiveAmount($classAmount)
                : $classAmount;

            if ($amount === null || $amount <= 0) {
                $amount = $enteredAmounts?->get($typeId);
            }

            if ($amount === null || $amount <= 0) {
                continue;
            }

            $feeType = $feeTypes->get($typeId);

            $lines[] = [
                'fee_type_id' => $typeId,
                'description' => (string) ($structure?->name ?: ($feeType?->name ?? __('Fee'))),
                'fee_type_name' => (string) ($feeType?->name ?? ''),
                'amount' => round($amount, 2),
                'source' => $assignment !== null ? 'assignment' : ($structure !== null ? 'structure' : 'manual'),
            ];
        }

        return $lines;
    }

    /**
     * The names behind a set of fee type ids, including soft-deleted ones, so
     * an error message can always say which fee it means.
     *
     * @param  list<int>  $typeIds
     */
    private function feeNames(array $typeIds): string
    {
        if ($typeIds === []) {
            return '';
        }

        return FeeType::withTrashed()->whereIn('id', $typeIds)->pluck('name')->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyDiscount(User $actor, FeeInvoice $invoice, array $data): FeeInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $data): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            $this->guardEditable($locked);

            /** @var FeeDiscount $discount */
            $discount = FeeDiscount::query()->findOrFail((int) $data['fee_discount_id']);

            if (! $discount->is_active) {
                throw ValidationException::withMessages([
                    'fee_discount_id' => __('This discount is no longer active.'),
                ]);
            }

            if (! $discount->isAvailableOn($locked->issue_date)) {
                throw ValidationException::withMessages([
                    'fee_discount_id' => __('This discount is not available on the invoice date.'),
                ]);
            }

            $locked->loadMissing(['student', 'enrollment']);

            if ($locked->student !== null && ! $discount->appliesTo($locked->student, $locked->enrollment)) {
                throw ValidationException::withMessages([
                    'fee_discount_id' => __('This discount does not apply to this student or class.'),
                ]);
            }

            $duplicate = $locked->discounts()
                ->where('fee_discount_id', $discount->id)
                ->whereIn('status', ['pending', ...FeeInvoiceDiscount::ACTIVE_STATUSES])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'fee_discount_id' => __('This discount is already applied or waiting for approval on the invoice.'),
                ]);
            }

            $base = $discount->applies_to_fee_type_id !== null
                ? round((float) $locked->items()->where('fee_type_id', $discount->applies_to_fee_type_id)->sum('line_total'), 2)
                : round((float) $locked->subtotal, 2);

            if ($base <= 0) {
                throw ValidationException::withMessages([
                    'fee_discount_id' => __('This discount does not apply to any item on the invoice.'),
                ]);
            }

            $amount = $discount->type === 'percent'
                ? round($base * ((float) $discount->value / 100), 2)
                : round((float) $discount->value, 2);

            if ($discount->max_amount !== null) {
                $amount = min($amount, round((float) $discount->max_amount, 2));
            }

            $approved = round((float) $locked->discounts()->active()->sum('amount'), 2);
            $remaining = max(0, round((float) $locked->subtotal - $approved, 2));
            $amount = min($amount, $remaining);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'fee_discount_id' => __('There is no remaining balance to discount on this invoice.'),
                ]);
            }

            $threshold = (float) config('finance.discount_approval_threshold', 0);
            $needsApproval = $amount > $threshold && ! $actor->can('fees.discounts.approve');

            $applied = $locked->discounts()->create([
                'fee_discount_id' => $discount->id,
                'fee_type_id' => $discount->applies_to_fee_type_id,
                'code' => $discount->code,
                'name' => $discount->name,
                'type' => $discount->type,
                'frequency' => $discount->frequency,
                'scope' => $discount->scope,
                'value' => $discount->value,
                'reason' => $data['reason'] ?? null,
                'amount' => $amount,
                'base_amount' => $base,
                'max_amount' => $discount->max_amount,
                'starts_on' => $discount->starts_on,
                'ends_on' => $discount->ends_on,
                'status' => $needsApproval ? 'pending' : 'approved',
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'approved_by' => $needsApproval ? null : $actor->id,
                'approved_at' => $needsApproval ? null : now(),
            ]);

            if (! $needsApproval) {
                $this->recalculateDiscounts($locked);
            }

            $this->audit->log(
                event: $needsApproval ? 'fee_invoice.discount_requested' : 'fee_invoice.discount_applied',
                auditable: $locked,
                description: $needsApproval
                    ? __('Discount :name on invoice :invoice is waiting for approval', ['name' => $discount->name, 'invoice' => $locked->invoice_no])
                    : __('Discount :name was applied to invoice :invoice', ['name' => $discount->name, 'invoice' => $locked->invoice_no]),
                newValues: [
                    'fee_discount_id' => $discount->id,
                    'discount_id' => $applied->id,
                    'amount' => $amount,
                    'status' => $applied->status,
                    'total' => (float) $locked->refresh()->total,
                ],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function approveDiscount(User $actor, FeeInvoice $invoice, FeeInvoiceDiscount $discount): FeeInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $discount): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ((int) $discount->fee_invoice_id !== (int) $locked->id) {
                abort(404, __('Discount not found on this invoice.'));
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'invoice' => __('Cancelled invoices cannot be changed.'),
                ]);
            }

            if ($discount->status !== 'pending') {
                throw ValidationException::withMessages([
                    'discount' => __('Only discount requests waiting for approval can be approved.'),
                ]);
            }

            $approved = round((float) $locked->discounts()->active()->sum('amount'), 2);
            $remaining = max(0, round((float) $locked->subtotal - $approved, 2));
            $requestedAmount = round((float) $discount->amount, 2);
            $amount = min($requestedAmount, $remaining);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'discount' => __('There is no remaining balance to approve this discount.'),
                ]);
            }

            $discount->update([
                'amount' => $amount,
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $this->recalculateDiscounts($locked);

            $this->audit->log(
                event: 'fee_invoice.discount_approved',
                auditable: $locked,
                description: __('Discount :name on invoice :invoice was approved', ['name' => $discount->name, 'invoice' => $locked->invoice_no]),
                oldValues: ['status' => 'pending', 'amount' => $requestedAmount],
                newValues: ['status' => 'approved', 'amount' => (float) $discount->amount, 'total' => (float) $locked->refresh()->total],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function rejectDiscount(User $actor, FeeInvoice $invoice, FeeInvoiceDiscount $discount, string $reason): FeeInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $discount, $reason): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ((int) $discount->fee_invoice_id !== (int) $locked->id) {
                abort(404, __('Discount not found on this invoice.'));
            }

            if ($discount->status !== 'pending') {
                throw ValidationException::withMessages([
                    'discount' => __('Only discount requests waiting for approval can be rejected.'),
                ]);
            }

            $discount->update([
                'status' => 'rejected',
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->audit->log(
                event: 'fee_invoice.discount_rejected',
                auditable: $locked,
                description: __('Discount :name on invoice :invoice was rejected: :reason', [
                    'name' => $discount->name,
                    'invoice' => $locked->invoice_no,
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function addCharge(User $actor, FeeInvoice $invoice, array $data): FeeInvoice
    {
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('The charge amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice, $data, $amount): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'invoice' => __('Cancelled invoices cannot be changed.'),
                ]);
            }

            if ((float) $locked->paid_total > 0) {
                throw ValidationException::withMessages([
                    'invoice' => __('Payments already exist on this invoice. Add the charge before collecting payment.'),
                ]);
            }

            $locked->items()->create([
                'fee_type_id' => null,
                'description' => (string) $data['description'],
                'quantity' => 1,
                'unit_amount' => $amount,
                'discount_amount' => 0,
                'line_total' => $amount,
            ]);

            $subtotal = round((float) $locked->subtotal + $amount, 2);
            $discountTotal = round((float) $locked->discounts()->active()->sum('amount'), 2);
            $total = max(0, round($subtotal - $discountTotal, 2));

            $locked->update([
                'subtotal' => $subtotal,
                'configured_fee_amount' => $subtotal,
                'discount_total' => $discountTotal,
                'approved_discount_amount' => $discountTotal,
                'total' => $total,
                'final_payable_amount' => $total,
                'due_total' => $total,
                'status' => 'issued',
            ]);

            $this->audit->log(
                event: 'fee_invoice.charge_added',
                auditable: $locked,
                description: __('Charge :description was added to invoice :invoice', [
                    'description' => $data['description'],
                    'invoice' => $locked->invoice_no,
                ]),
                newValues: ['amount' => $amount, 'subtotal' => $subtotal, 'total' => $total],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function removeDiscount(User $actor, FeeInvoice $invoice, FeeInvoiceDiscount $discount): FeeInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $discount): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ((int) $discount->fee_invoice_id !== (int) $locked->id) {
                abort(404, __('Discount not found on this invoice.'));
            }

            if ($discount->isActive()) {
                $this->guardEditable($locked);
            }

            $discount->delete();

            $this->recalculateDiscounts($locked);

            $this->audit->log(
                event: 'fee_invoice.discount_removed',
                auditable: $locked,
                description: __('Discount :name was removed from invoice :invoice', ['name' => $discount->name, 'invoice' => $locked->invoice_no]),
                oldValues: ['name' => $discount->name, 'amount' => (float) $discount->amount],
                newValues: ['total' => (float) $locked->refresh()->total],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function cancelDiscount(User $actor, FeeInvoice $invoice, FeeInvoiceDiscount $discount, string $reason): FeeInvoice
    {
        return DB::transaction(function () use ($actor, $invoice, $discount, $reason): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ((int) $discount->fee_invoice_id !== (int) $locked->id) {
                abort(404, __('Discount not found on this invoice.'));
            }

            if ($discount->status === 'cancelled') {
                return $locked;
            }

            if ($discount->status === 'rejected') {
                throw ValidationException::withMessages([
                    'discount' => __('Rejected discounts cannot be cancelled.'),
                ]);
            }

            if ($discount->isActive()) {
                $this->guardEditable($locked);
            }

            $previousStatus = $discount->status;

            $discount->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->recalculateDiscounts($locked);

            $this->audit->log(
                event: 'fee_invoice.discount_cancelled',
                auditable: $locked,
                description: __('Discount :name on invoice :invoice was cancelled: :reason', [
                    'name' => $discount->name,
                    'invoice' => $locked->invoice_no,
                    'reason' => $reason,
                ]),
                oldValues: ['status' => $previousStatus, 'amount' => (float) $discount->amount],
                newValues: ['status' => 'cancelled', 'cancellation_reason' => $reason, 'total' => (float) $locked->refresh()->total],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    public function cancel(User $actor, FeeInvoice $invoice): FeeInvoice
    {
        return DB::transaction(function () use ($actor, $invoice): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ((float) $locked->paid_total > 0) {
                throw ValidationException::withMessages([
                    'invoice' => __('Invoices with recorded payments cannot be cancelled. Reverse the payments first.'),
                ]);
            }

            if ($locked->status === 'cancelled') {
                return $locked;
            }

            $locked->update(['status' => 'cancelled']);

            $this->audit->log(
                event: 'fee_invoice.cancelled',
                auditable: $locked,
                description: __('Invoice :invoice was cancelled', ['invoice' => $locked->invoice_no]),
                oldValues: ['status' => 'issued', 'total' => (float) $locked->total],
                newValues: ['status' => 'cancelled'],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    /**
     * Edit one line of an unpaid invoice. Only invoices with no recorded
     * payments and no write-off can change; a partial, paid or settled
     * invoice keeps its financial history untouched.
     *
     * @param  array{description?: string, amount?: float|int|string}  $data
     */
    public function updateItem(User $actor, FeeInvoice $invoice, FeeInvoiceItem $item, array $data): FeeInvoice
    {
        $amount = filled($data['amount'] ?? null) ? round((float) $data['amount'], 2) : null;

        if ($amount !== null && $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('The invoice amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice, $item, $amount, $data): FeeInvoice {
            /** @var FeeInvoice $locked */
            $locked = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ((int) $item->fee_invoice_id !== (int) $locked->id) {
                abort(404, __('Fee line not found on this invoice.'));
            }

            if ($locked->status !== 'issued' || (float) $locked->paid_total > 0 || (float) $locked->waived_total > 0) {
                throw ValidationException::withMessages([
                    'invoice' => __('Only an unpaid invoice can be edited. This invoice already has payments or a settlement.'),
                ]);
            }

            $line = FeeInvoiceItem::query()->lockForUpdate()->findOrFail($item->getKey());

            $previous = ['description' => $line->description, 'amount' => (float) $line->line_total];

            $line->update([
                'description' => filled($data['description'] ?? null) ? (string) $data['description'] : $line->description,
                'unit_amount' => $amount ?? (float) $line->unit_amount,
                'line_total' => $amount ?? (float) $line->line_total,
            ]);

            $subtotal = round((float) $locked->items()->sum('line_total'), 2);
            $locked->update(['subtotal' => $subtotal, 'configured_fee_amount' => $subtotal]);

            $this->recalculateDiscounts($locked);

            $this->audit->log(
                event: 'fee_invoice.item_updated',
                auditable: $locked,
                description: __('Fee line :description was edited on unpaid invoice :invoice', [
                    'description' => $line->description,
                    'invoice' => $locked->invoice_no,
                ]),
                oldValues: $previous + ['total' => $previous['amount']],
                newValues: [
                    'description' => $line->description,
                    'amount' => (float) $line->line_total,
                    'total' => (float) $locked->refresh()->total,
                ],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    private function recalculateDiscounts(FeeInvoice $invoice): void
    {
        $discountTotal = round((float) $invoice->discounts()->active()->sum('amount'), 2);
        $discountTotal = min($discountTotal, round((float) $invoice->subtotal, 2));
        $paid = round((float) $invoice->paid_total, 2);
        $waived = round((float) $invoice->waived_total, 2);
        $total = max(0, round((float) $invoice->subtotal - $discountTotal, 2));
        $due = max(0, round($total - $paid - $waived, 2));

        $status = FeeInvoice::statusForCollection($total, $paid, $due);

        $invoice->update([
            'configured_fee_amount' => (float) $invoice->subtotal,
            'discount_total' => $discountTotal,
            'approved_discount_amount' => $discountTotal,
            'total' => $total,
            'final_payable_amount' => $total,
            'actual_collected_amount' => $paid,
            'due_total' => $due,
            'status' => $status,
        ]);

        $invoice->refresh();
    }

    private function applyAutomaticDiscounts(User $actor, FeeInvoice $invoice, ?string $issueDate): void
    {
        $invoice->loadMissing(['student', 'enrollment']);

        if ($invoice->student === null) {
            return;
        }

        $day = $issueDate !== null ? Carbon::parse($issueDate) : now();

        $definitions = FeeDiscount::query()
            ->active()
            ->availableOn($day)
            ->where('auto_apply', true)
            ->orderByRaw("CASE WHEN scope = 'student' THEN 0 WHEN scope = 'class' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        foreach ($definitions as $definition) {
            if (! $definition->appliesTo($invoice->student, $invoice->enrollment)) {
                continue;
            }

            $alreadyRecorded = $invoice->discounts()
                ->where('fee_discount_id', $definition->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            try {
                $this->applyDiscount($actor, $invoice, ['fee_discount_id' => $definition->id]);
            } catch (ValidationException) {
                continue;
            }
        }
    }

    private function guardEditable(FeeInvoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice' => __('Cancelled invoices cannot be changed.'),
            ]);
        }

        if ((float) $invoice->paid_total > 0) {
            throw ValidationException::withMessages([
                'invoice' => __('Payments already exist on this invoice. Reverse them before changing discounts.'),
            ]);
        }
    }

    /**
     * The billing period a manual invoice belongs to. A monthly fee needs its
     * month; a one-time, yearly or exam fee is billed once for the session
     * under a session-wide period, so the counter can keep collecting on it.
     */
    private function periodFor(AcademicSession $session, int $month, FeeType $feeType): string
    {
        if (! $feeType->isMonthly()) {
            $start = $session->start_date ?? now();

            return sprintf('%04d-S1', (int) $start->year);
        }

        return $this->period($this->yearFor($session, $month), $month);
    }

    private function period(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * A session may run across two calendar years; a January selection in an
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
