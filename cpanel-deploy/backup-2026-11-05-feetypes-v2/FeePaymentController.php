<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\ReverseFeePaymentRequest;
use App\Http\Requests\Finance\StoreFeePaymentRequest;
use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\FeeCollectionAuthority;
use App\Services\FeePaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeePaymentController extends Controller
{
    public function __construct(
        private readonly FeePaymentService $payments,
        private readonly FeeCollectionAuthority $authority,
    ) {}

    public function dues(Request $request): View
    {
        $this->requirePermission('fees.view');

        $filters = [
            'search' => trim((string) $request->query('search')),
            'session_id' => (string) $request->query('session_id', ''),
            'class_id' => (string) $request->query('class_id', ''),
        ];

        $query = FeeInvoice::query()
            ->outstanding()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('invoice_no', 'like', $like)
                        ->orWhereHas('student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('student_id', 'like', $like);
                        });
                });
            })
            ->when($filters['session_id'] !== '', fn (Builder $query) => $query->where('academic_session_id', (int) $filters['session_id']))
            ->when($filters['class_id'] !== '', fn (Builder $query) => $query->whereHas(
                'enrollment',
                fn (Builder $query) => $query->where('class_id', (int) $filters['class_id']),
            ));

        $overdue = (clone $query)
            ->where('due_date', '<', now()->toDateString())
            ->count();

        $totalDue = (float) (clone $query)->sum('due_total');

        return view('fees.dues', [
            'filters' => $filters,
            'invoices' => $query
                ->with(['student', 'enrollment.schoolClass', 'enrollment.section'])
                ->orderBy('due_date')
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString(),
            'summary' => [
                'due' => $totalDue,
                'count' => (clone $query)->count(),
                'overdue' => $overdue,
                'students' => (clone $query)->distinct()->count('student_id'),
            ],
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name']),
        ]);
    }

    public function receipts(Request $request): View
    {
        $this->requirePermission('fees.view');

        $filters = [
            'search' => trim((string) $request->query('search')),
            'method' => in_array($request->query('method'), FeePayment::METHODS, true) ? (string) $request->query('method') : '',
            'from' => $request->filled('from') ? $this->dateOrToday($request->query('from'))->toDateString() : '',
            'to' => $request->filled('to') ? $this->dateOrToday($request->query('to'))->toDateString() : '',
        ];

        $query = FeePayment::query()
            ->posted()
            ->whereHas('receipt')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('transaction_ref', 'like', $like)
                        ->orWhereHas('receipt', fn (Builder $query) => $query->where('receipt_no', 'like', $like))
                        ->orWhereHas('allocations.invoice', fn (Builder $query) => $query->where('invoice_no', 'like', $like))
                        ->orWhereHas('student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('student_id', 'like', $like);
                        });
                });
            })
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('method', $filters['method']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('paid_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('paid_at', '<=', $filters['to']));

        return view('fees.receipts', [
            'filters' => $filters,
            'payments' => $query
                ->with(['receipt', 'student', 'allocations.invoice'])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'total' => (float) (clone $query)->sum('amount'),
                'count' => (clone $query)->count(),
            ],
            'methods' => FeePayment::METHODS,
        ]);
    }

    public function verifications(Request $request): View
    {
        $this->requirePermission('fees.verify');

        $filters = [
            'search' => trim((string) $request->query('search')),
            'method' => in_array($request->query('method'), FeePayment::METHODS, true) ? (string) $request->query('method') : '',
        ];

        $query = FeePayment::query()
            ->pendingVerification()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('transaction_ref', 'like', $like)
                        ->orWhereHas('student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('student_id', 'like', $like);
                        });
                });
            })
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('method', $filters['method']));

        return view('fees.verifications', [
            'filters' => $filters,
            'payments' => $query
                ->with(['student', 'allocations.invoice', 'receiver'])
                ->orderBy('paid_at')
                ->orderBy('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'count' => (clone $query)->count(),
                'total' => (float) (clone $query)->sum('amount'),
            ],
            'methods' => FeePayment::METHODS,
        ]);
    }

    public function verify(Request $request, FeePayment $payment): RedirectResponse
    {
        $this->requirePermission('fees.verify');

        $this->payments->verify($request->user(), $payment);

        return back()->with('success', __('The payment was verified, the invoice updated and the receipt issued.'));
    }

    public function rejectVerification(Request $request, FeePayment $payment): RedirectResponse
    {
        $this->requirePermission('fees.verify');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->payments->rejectVerification($request->user(), $payment, $data['reason']);

        return back()->with('success', __('The payment was rejected and the invoice balance was left unchanged.'));
    }

    public function store(StoreFeePaymentRequest $request, FeeInvoice $invoice): RedirectResponse
    {
        $this->authority->assertMayCollectInvoice($request->user(), $invoice, $invoice->academicSession);

        $payment = $this->payments->collect($request->user(), $invoice, $request->validated());

        if ($payment->status === 'pending') {
            return redirect()
                ->route('fees.show', $invoice)
                ->with('success', __('The payment was held for verification. The receipt is issued once it is confirmed.'));
        }

        $waived = round((float) $payment->allocations()->where('fee_invoice_id', $invoice->id)->value('waived_amount'), 2);

        if ($waived > 0) {
            return redirect()
                ->route('fees.receipt', $payment)
                ->with('success', __('The :received received was recorded and the invoice closed. Nothing carries forward as a due.', [
                    'received' => '৳ '.number_format((float) $payment->amount, 2),
                ]));
        }

        return redirect()
            ->route('fees.receipt', $payment)
            ->with('success', __('The payment was recorded and a receipt was issued.'));
    }

    public function receipt(Request $request, FeePayment $payment): View
    {
        $this->requirePermission('fees.view');

        $payment->load(['receipt', 'student', 'feeType', 'allocations.invoice.items', 'receiver']);

        if ($payment->receipt === null) {
            abort(404, __('Receipt not found.'));
        }

        // The receipt is a document, not a data-entry screen: the paper only
        // changes the print frame, never the stored numbers.
        $paper = in_array($request->query('paper'), ['a4', 'a5', '80mm'], true)
            ? (string) $request->query('paper')
            : 'a4';

        $sheets = [
            'a4' => ['size' => 'A4', 'margin' => '12mm', 'class' => 'a4'],
            'a5' => ['size' => 'A5', 'margin' => '6mm', 'class' => 'a5'],
            // Chrome ignores an `auto` page height, so the roll gets a standard
            // 80mm x 297mm page; a longer invoice simply continues on the roll.
            '80mm' => ['size' => '80mm 297mm', 'margin' => '4mm', 'class' => 'thermal'],
        ];

        return view('fees.receipt', [
            'payment' => $payment,
            'receipt' => $payment->receipt,
            'school' => School::query()->findOrFail((int) $payment->school_id),
            'paper' => $paper,
            'paperSize' => $sheets[$paper]['size'],
            'paperMargin' => $sheets[$paper]['margin'],
            'paperClass' => $sheets[$paper]['class'],
            'printNow' => $request->boolean('print'),
        ]);
    }

    public function reverse(ReverseFeePaymentRequest $request, FeePayment $payment): RedirectResponse
    {
        $invoiceId = $payment->allocations()->value('fee_invoice_id');

        $this->payments->reverse($request->user(), $payment, (string) $request->validated()['reason']);

        $redirect = $invoiceId !== null
            ? redirect()->route('fees.show', $invoiceId)
            : redirect()->route('fees.index');

        return $redirect->with('success', __('The payment was reversed and the cash ledger was updated.'));
    }
}
