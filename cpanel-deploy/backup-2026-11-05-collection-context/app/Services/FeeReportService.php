<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads the fee money for the reports module.
 *
 * Two grains are kept apart on purpose. Cash is summed over `fee_payment_allocations`
 * rows of payments that are still posted, so one receipt spread over several fee
 * records is counted once in total and its instalments never repeat the invoice.
 * The standard fee is summed over the distinct invoices those allocations touched,
 * so it is never multiplied by the number of instalments. Nothing is reported as a
 * balance carried forward: the gap between the two figures is not a due.
 */
class FeeReportService
{
    /**
     * @return array<string, mixed>
     */
    public function filters(Request $request): array
    {
        $month = $this->month($request->query('month'));

        $from = $request->filled('from')
            ? $this->date($request->query('from'))->startOfDay()
            : ($month === null ? now()->copy()->startOfMonth() : Carbon::parse($month.'-01')->startOfMonth());

        $to = $request->filled('to')
            ? $this->date($request->query('to'))->startOfDay()
            : ($month === null ? now()->startOfDay() : Carbon::parse($month.'-01')->endOfMonth()->startOfDay());

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        $method = is_string($request->query('method')) && in_array($request->query('method'), FeePayment::METHODS, true)
            ? (string) $request->query('method')
            : null;

        return [
            'from' => $from,
            'to' => $to,
            'session_id' => $request->integer('session_id') ?: null,
            'fee_month' => $month,
            'class_id' => $request->integer('class_id') ?: null,
            'section_id' => $request->integer('section_id') ?: null,
            'student_id' => $request->integer('student_id') ?: null,
            'fee_type_id' => $request->integer('fee_type_id') ?: null,
            'method' => $method,
            'collector_id' => $request->integer('collector_id') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, float|int>
     */
    public function summary(array $f): array
    {
        $cashbook = $this->cashbookInflow($f);
        $invoices = $this->collectedInvoices($f);

        return [
            'standard_fee' => round((float) $invoices->sum('fee_invoices.total'), 2),
            'received' => round((float) $this->cash($f)->sum('fee_payment_allocations.amount'), 2),
            'payments' => (int) $this->cash($f)->distinct()->count('fee_payment_allocations.fee_payment_id'),
            'invoices' => (int) $invoices->count(),
            'students' => (int) $invoices->distinct()->count('fee_invoices.student_id'),
            'reversed_count' => $this->reversed($f)->count(),
            'reversed_amount' => round((float) $this->reversed($f)->sum('amount'), 2),
            'held_count' => $this->held($f)->count(),
            'held_amount' => round((float) $this->held($f)->sum('amount'), 2),
            'cashbook_gross' => $cashbook['gross'],
            'cashbook_net' => $cashbook['net'],
        ];
    }

    /**
     * Cash received as a share of the standard fee of the same invoices. The
     * denominator is the invoices these collections touched, not everything the
     * school has ever billed.
     *
     * @param  array<string, mixed>  $f
     */
    public function collectionRate(array $f): float
    {
        $standard = round((float) $this->collectedInvoices($f)->sum('fee_invoices.total'), 2);

        return $standard > 0 ? round($this->receivedTotal($f) / $standard * 100, 1) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byDay(array $f): Collection
    {
        return $this->pair('DATE(fee_payments.paid_at)', $f)
            ->map(fn (array $row): array => $row + [
                'label' => $row['key'] === null ? __('No date') : Carbon::parse((string) $row['key'])->format('d M Y'),
            ])
            ->sortBy('key')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byPaymentMonth(array $f): Collection
    {
        return $this->pair("DATE_FORMAT(fee_payments.paid_at, '%Y-%m')", $f)
            ->map(fn (array $row): array => $row + [
                'label' => $row['key'] === null ? __('No date') : $this->monthLabel((string) $row['key']),
            ])
            ->sortBy('key')
            ->values();
    }

    /**
     * The other dimension: the billing month the money was for, not the day it
     * arrived.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byFeeMonth(array $f): Collection
    {
        return $this->pair('fee_invoices.billing_period', $f)
            ->map(fn (array $row): array => $row + [
                'label' => $row['key'] === null ? __('Not monthed') : $this->monthLabel((string) $row['key']),
            ])
            ->sortBy('key')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byMethod(array $f): Collection
    {
        return $this->pair('fee_payments.method', $f)
            ->map(fn (array $row): array => $row + [
                'label' => $row['key'] === null ? __('Unrecorded method') : __(ucfirst((string) $row['key'])),
            ])
            ->sortByDesc('received')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byClass(array $f): Collection
    {
        $rows = $this->pair('enrollments.class_id', $f);
        $names = SchoolClass::query()->whereIn('id', $this->ids($rows))->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn (array $row): array => $row + [
            'label' => $row['key'] === null ? __('Class not recorded') : ($names[(int) $row['key']]->name ?? __('Class not recorded')),
        ])->sortByDesc('received')->values();
    }

    /**
     * Class and section together, since a section only means something inside its
     * class.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function bySection(array $f): Collection
    {
        $rows = $this->pair('enrollments.section_id', $f);

        $sections = Section::query()
            ->with('schoolClass:id,name')
            ->whereIn('id', $this->ids($rows))
            ->get()
            ->keyBy('id');

        return $rows->map(function (array $row) use ($sections): array {
            $section = $row['key'] === null ? null : ($sections[(int) $row['key']] ?? null);

            return $row + [
                'label' => $section === null
                    ? __('Section not recorded')
                    : ($section->schoolClass?->name ?? __('Class not recorded')).' · '.$section->name,
            ];
        })->sortByDesc('received')->values();
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byStudent(array $f): Collection
    {
        $rows = $this->pair('fee_invoices.student_id', $f);

        $students = Student::query()
            ->with(['enrollments.schoolClass:id,name', 'enrollments.section:id,name'])
            ->whereIn('id', $this->ids($rows))
            ->get()
            ->keyBy('id');

        return $rows->map(function (array $row) use ($students): array {
            $student = $row['key'] === null ? null : ($students[(int) $row['key']] ?? null);
            $enrollment = $student?->enrollments->firstWhere('status', 'active') ?? $student?->enrollments->first();

            return $row + [
                'label' => $student?->full_name ?? __('Student not found'),
                'code' => $student?->student_id,
                'class' => $enrollment?->schoolClass?->name,
                'section' => $enrollment?->section?->name,
            ];
        })->sortByDesc('received')->values();
    }

    /**
     * Who collected what. Reversals are credited to the person who signed them off,
     * held payments to the person who took the money but has not verified it yet.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byCollector(array $f): Collection
    {
        $rows = $this->pair('fee_payments.received_by', $f);
        $names = User::query()->whereIn('id', $this->ids($rows))->get(['id', 'name'])->keyBy('id');

        $reversals = $this->countBy($this->reversed($f), 'reversed_by');
        $held = $this->countBy($this->held($f), 'received_by');

        return $rows->map(function (array $row) use ($names, $reversals, $held): array {
            $id = $row['key'] === null ? null : (int) $row['key'];

            return $row + [
                'label' => $id === null ? __('Removed collector') : ($names[$id]->name ?? __('Removed collector')),
                'reversed' => (int) ($reversals[self::slug($row['key'])] ?? 0),
                'held' => (int) ($held[self::slug($row['key'])] ?? 0),
            ];
        })->sortByDesc('received')->values();
    }

    /**
     * Collector and paid date at once. A daily sheet needs to say whose money
     * landed on which day, which neither single-axis grouping can answer.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byCollectorDay(array $f): Collection
    {
        $rows = $this->cash($f)
            ->selectRaw('fee_payments.received_by as collector_id')
            ->selectRaw('DATE(fee_payments.paid_at) as paid_day')
            ->selectRaw('COALESCE(SUM(fee_payment_allocations.amount), 0) as received')
            ->selectRaw('COUNT(DISTINCT fee_payment_allocations.fee_payment_id) as payments')
            ->groupByRaw('fee_payments.received_by, DATE(fee_payments.paid_at)')
            ->orderBy('paid_day')
            ->get();

        $names = User::query()
            ->whereIn('id', $rows->pluck('collector_id')->filter()->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows->map(fn ($row): array => [
            'collector_id' => $row->collector_id === null ? null : (int) $row->collector_id,
            'collector' => $row->collector_id === null
                ? __('Removed collector')
                : ($names[(int) $row->collector_id]->name ?? __('Removed collector')),
            'day' => (string) $row->paid_day,
            'received' => round((float) $row->received, 2),
            'payments' => (int) $row->payments,
        ]);
    }

    /**
     * Fee-type split. Cash is apportioned across the lines of an invoice in
     * proportion to each line and the last line absorbs the rounding, so the rows
     * add back to the collection total instead of repeating it per line.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function byFeeType(array $f): Collection
    {
        $received = $this->cash($f)
            ->selectRaw('fee_payment_allocations.fee_invoice_id as invoice_id')
            ->selectRaw('COALESCE(SUM(fee_payment_allocations.amount), 0) as amount')
            ->groupBy('fee_payment_allocations.fee_invoice_id')
            ->get()
            ->keyBy('invoice_id');

        $invoices = $this->collectedInvoices($f)->with('items')->get();

        $buckets = [];

        foreach ($invoices as $invoice) {
            $amount = round((float) ($received[$invoice->id]->amount ?? 0), 2);
            $subtotal = round((float) $invoice->subtotal, 2);
            $items = $invoice->items;

            if ($subtotal <= 0 || $items->isEmpty()) {
                continue;
            }

            $last = $items->count() - 1;
            $shared = 0.0;

            foreach ($items->values() as $index => $item) {
                $share = $index === $last
                    ? round($amount - $shared, 2)
                    : round($amount * ((float) $item->line_total / $subtotal), 2);
                $shared = round($shared + $share, 2);

                $key = $item->fee_type_id === null ? 0 : (int) $item->fee_type_id;

                $buckets[$key] ??= [
                    'key' => $key,
                    'label' => $item->fee_type_id === null ? ($item->description ?: __('Other charges')) : '',
                    'billed' => 0.0,
                    'received' => 0.0,
                    'lines' => 0,
                ];
                $buckets[$key]['billed'] = round($buckets[$key]['billed'] + (float) $item->line_total, 2);
                $buckets[$key]['received'] = round($buckets[$key]['received'] + $share, 2);
                $buckets[$key]['lines']++;
            }
        }

        $names = FeeType::query()
            ->whereIn('id', array_keys($buckets))
            ->get(['id', 'name'])
            ->keyBy('id');

        return collect($buckets)
            ->map(function (array $row) use ($names): array {
                $row['label'] = $row['key'] === 0
                    ? $row['label']
                    : ($names[$row['key']]->name ?? ($row['label'] === '' ? __('Fee type removed') : $row['label']));

                return $row;
            })
            ->sortByDesc('received')
            ->values();
    }

    /**
     * One row per invoice a payment was applied to, so a single receipt that
     * covered three fee records shows three rows whose amounts add up to that one
     * receipt rather than three receipts.
     *
     * @param  array<string, mixed>  $f
     */
    public function payments(array $f, int $perPage = 25): Paginator
    {
        return $this->allocationQuery($f)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The same rows without the page limit, for exports.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, FeePaymentAllocation>
     */
    public function allocationRows(array $f): Collection
    {
        return $this->allocationQuery($f)->get();
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function allocationQuery(array $f): Builder
    {
        return $this->cash($f)
            ->with([
                'payment.student',
                'payment.receiver',
                'payment.receipt',
                'invoice.academicSession',
                'invoice.enrollment.schoolClass',
                'invoice.enrollment.section',
            ])
            ->select('fee_payment_allocations.*')
            ->orderByDesc('fee_payments.paid_at')
            ->orderByDesc('fee_payments.id')
            ->orderBy('fee_payment_allocations.fee_invoice_id');
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function reversals(array $f): Collection
    {
        return $this->reversed($f)
            ->with(['student', 'receipt', 'reverser'])
            ->orderByDesc('reversed_at')
            ->get()
            ->map(fn (FeePayment $payment): array => [
                'receipt_no' => $payment->receipt?->receipt_no,
                'student' => $payment->student?->full_name,
                'amount' => round((float) $payment->amount, 2),
                'method' => __(ucfirst($payment->method)),
                'reversed_at' => $payment->reversed_at,
                'by' => $payment->reverser?->name,
                'reason' => $payment->reversal_reason,
            ]);
    }

    /**
     * Money taken but not yet verified, so it is not in the cashbook either.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function heldPayments(array $f): Collection
    {
        return $this->held($f)
            ->with(['student', 'receiver'])
            ->orderBy('paid_at')
            ->get()
            ->map(fn (FeePayment $payment): array => [
                'student' => $payment->student?->full_name,
                'amount' => round((float) $payment->amount, 2),
                'method' => __(ucfirst($payment->method)),
                'paid_at' => $payment->paid_at,
                'by' => $payment->receiver?->name,
            ]);
    }

    /**
     * Read from the ledger itself so the report and the cashbook cannot drift.
     * Only the original income lines are gross inflow; a reversal is its own row
     * with the opposite sign and is reported separately.
     *
     * @param  array<string, mixed>  $f
     * @return array{gross: float, reversed: float, net: float}
     */
    public function cashbookInflow(array $f): array
    {
        $ledger = fn (): Builder => CashTransaction::query()
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$f['from']->toDateString(), $f['to']->toDateString()]);

        return [
            'gross' => round((float) $ledger()->whereNull('reversal_of_id')->where('amount', '>', 0)->sum('amount'), 2),
            'reversed' => round(abs((float) $ledger()->whereNotNull('reversal_of_id')->sum('amount')), 2),
            'net' => round((float) $ledger()->sum('amount'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     */
    public function receivedTotal(array $f): float
    {
        return round((float) $this->cash($f)->sum('fee_payment_allocations.amount'), 2);
    }

    /**
     * Sum of the invoice totals of the distinct invoices collected in range.
     *
     * @param  array<string, mixed>  $f
     */
    public function standardFeeTotal(array $f): float
    {
        return round((float) $this->collectedInvoices($f)->sum('fee_invoices.total'), 2);
    }

    /**
     * Allocation rows carrying the posted cash for these filters. Payments,
     * invoices and enrollments are joined once so every grouping shares the same
     * restrictions and the same school scope on the allocation row.
     *
     * @param  array<string, mixed>  $f
     */
    private function cash(array $f): Builder
    {
        return FeePaymentAllocation::query()
            ->join('fee_payments', 'fee_payments.id', '=', 'fee_payment_allocations.fee_payment_id')
            ->join('fee_invoices', 'fee_invoices.id', '=', 'fee_payment_allocations.fee_invoice_id')
            ->leftJoin('enrollments', 'enrollments.id', '=', 'fee_invoices.enrollment_id')
            ->where('fee_payments.status', 'posted')
            ->whereBetween('fee_payments.paid_at', [
                $f['from']->toDateTimeString(),
                $f['to']->copy()->endOfDay()->toDateTimeString(),
            ])
            ->when($f['method'] !== null, fn (Builder $q): Builder => $q->where('fee_payments.method', $f['method']))
            ->when($f['collector_id'] !== null, fn (Builder $q): Builder => $q->where('fee_payments.received_by', $f['collector_id']))
            ->when($f['session_id'] !== null, fn (Builder $q): Builder => $q->where('fee_invoices.academic_session_id', $f['session_id']))
            ->when($f['fee_month'] !== null, fn (Builder $q): Builder => $q->where('fee_invoices.billing_period', $f['fee_month']))
            ->when($f['class_id'] !== null, fn (Builder $q): Builder => $q->where('enrollments.class_id', $f['class_id']))
            ->when($f['section_id'] !== null, fn (Builder $q): Builder => $q->where('enrollments.section_id', $f['section_id']))
            ->when($f['student_id'] !== null, fn (Builder $q): Builder => $q->where('fee_invoices.student_id', $f['student_id']))
            ->when($f['fee_type_id'] !== null, fn (Builder $q): Builder => $q->whereExists(
                fn ($sub): \Illuminate\Database\Query\Builder => $sub->selectRaw('1')
                    ->from('fee_invoice_items')
                    ->whereColumn('fee_invoice_items.fee_invoice_id', 'fee_invoices.id')
                    ->where('fee_invoice_items.fee_type_id', $f['fee_type_id']),
            ));
    }

    /**
     * The distinct invoices those collections were applied to.
     *
     * @param  array<string, mixed>  $f
     */
    private function collectedInvoices(array $f): Builder
    {
        return FeeInvoice::query()
            ->whereIn('fee_invoices.id', $this->cash($f)->select('fee_payment_allocations.fee_invoice_id'));
    }

    /**
     * Cash and standard fee side by side for one grouping column. The standard fee
     * comes from a per-invoice sub row so an invoice with three instalments, or
     * three fee lines paid by one receipt, is added to its group once.
     *
     * @param  array<string, mixed>  $f
     * @return Collection<int, array<string, mixed>>
     */
    private function pair(string $expr, array $f): Collection
    {
        $cash = $this->cash($f)
            ->selectRaw("{$expr} as gkey")
            ->selectRaw('COALESCE(SUM(fee_payment_allocations.amount), 0) as received')
            ->selectRaw('COUNT(DISTINCT fee_payment_allocations.fee_payment_id) as payments')
            ->selectRaw('COUNT(DISTINCT fee_payment_allocations.fee_invoice_id) as invoices')
            ->groupByRaw($expr)
            ->get();

        $fees = DB::query()->fromSub(
            $this->cash($f)
                ->selectRaw("{$expr} as gkey, fee_invoices.id as invoice_id")
                ->selectRaw('MAX(fee_invoices.total) as invoice_total')
                ->groupByRaw("{$expr}, fee_invoices.id"),
            'touched',
        )->selectRaw('gkey')
            ->selectRaw('COALESCE(SUM(invoice_total), 0) as standard_fee')
            ->groupBy('gkey')
            ->get();

        $map = [];
        foreach ($fees as $row) {
            $map[self::slug($row->gkey)] = round((float) $row->standard_fee, 2);
        }

        return $cash->map(fn ($row): array => [
            'key' => $row->gkey,
            'received' => round((float) $row->received, 2),
            'payments' => (int) $row->payments,
            'invoices' => (int) $row->invoices,
            'standard_fee' => $map[self::slug($row->gkey)] ?? 0.0,
        ]);
    }

    /**
     * Payments reversed inside the range. The date is the reversal date, not the
     * day the money first arrived, so a refund shows in the period it left.
     *
     * @param  array<string, mixed>  $f
     */
    private function reversed(array $f): Builder
    {
        return $this->restrict(
            FeePayment::query()
                ->where('fee_payments.status', 'reversed')
                ->whereBetween('fee_payments.reversed_at', [
                    $f['from']->toDateTimeString(),
                    $f['to']->copy()->endOfDay()->toDateTimeString(),
                ]),
            $f,
        );
    }

    /**
     * Payments taken but still waiting for verification.
     *
     * @param  array<string, mixed>  $f
     */
    private function held(array $f): Builder
    {
        return $this->restrict(
            FeePayment::query()
                ->pendingVerification()
                ->whereBetween('fee_payments.paid_at', [
                    $f['from']->toDateTimeString(),
                    $f['to']->copy()->endOfDay()->toDateTimeString(),
                ]),
            $f,
        );
    }

    /**
     * Apply the invoice-side filters to a payment query through its allocations,
     * which keeps one row per payment instead of fanning it out.
     *
     * @param  array<string, mixed>  $f
     */
    private function restrict(Builder $query, array $f): Builder
    {
        $needsInvoice = $f['session_id'] !== null || $f['fee_month'] !== null || $f['class_id'] !== null
            || $f['section_id'] !== null || $f['fee_type_id'] !== null;

        if ($needsInvoice) {
            $query->whereExists(fn ($sub): \Illuminate\Database\Query\Builder => $sub
                ->selectRaw('1')
                ->from('fee_payment_allocations')
                ->join('fee_invoices', 'fee_invoices.id', '=', 'fee_payment_allocations.fee_invoice_id')
                ->when($f['fee_type_id'] !== null, fn ($q): \Illuminate\Database\Query\Builder => $q
                    ->join('fee_invoice_items', 'fee_invoice_items.fee_invoice_id', '=', 'fee_invoices.id'))
                ->leftJoin('enrollments', 'enrollments.id', '=', 'fee_invoices.enrollment_id')
                ->whereColumn('fee_payment_allocations.fee_payment_id', 'fee_payments.id')
                ->when($f['session_id'] !== null, fn ($q): \Illuminate\Database\Query\Builder => $q->where('fee_invoices.academic_session_id', $f['session_id']))
                ->when($f['fee_month'] !== null, fn ($q): \Illuminate\Database\Query\Builder => $q->where('fee_invoices.billing_period', $f['fee_month']))
                ->when($f['class_id'] !== null, fn ($q): \Illuminate\Database\Query\Builder => $q->where('enrollments.class_id', $f['class_id']))
                ->when($f['section_id'] !== null, fn ($q): \Illuminate\Database\Query\Builder => $q->where('enrollments.section_id', $f['section_id']))
                ->when($f['fee_type_id'] !== null, fn ($q): \Illuminate\Database\Query\Builder => $q->where('fee_invoice_items.fee_type_id', $f['fee_type_id'])));
        }

        return $query
            ->when($f['method'] !== null, fn (Builder $q): Builder => $q->where('fee_payments.method', $f['method']))
            ->when($f['collector_id'] !== null, fn (Builder $q): Builder => $q->where('fee_payments.received_by', $f['collector_id']))
            ->when($f['student_id'] !== null, fn (Builder $q): Builder => $q->where('fee_payments.student_id', $f['student_id']));
    }

    /**
     * @param  Builder<FeePayment>  $query
     * @return Collection<string, int>
     */
    private function countBy(Builder $query, string $column): Collection
    {
        return $query->clone()
            ->selectRaw("COALESCE({$column}, 0) as gkey, COUNT(*) as aggregate")
            ->groupByRaw("COALESCE({$column}, 0)")
            ->pluck('aggregate', 'gkey');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function ids(Collection $rows): array
    {
        return $rows->pluck('key')
            ->filter(fn (mixed $key): bool => $key !== null && $key !== '')
            ->map(fn (mixed $key): int => (int) $key)
            ->unique()
            ->values()
            ->all();
    }

    private static function slug(mixed $key): string
    {
        return $key === null ? '' : trim((string) $key);
    }

    private function monthLabel(string $period): string
    {
        try {
            return Carbon::parse($period.'-01')
                ->locale(app()->getLocale())
                ->translatedFormat('F Y');
        } catch (Throwable) {
            return $period;
        }
    }

    private function month(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? $value : null;
    }

    private function date(mixed $value): Carbon
    {
        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return now()->startOfDay();
        }
    }
}
