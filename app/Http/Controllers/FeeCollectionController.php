<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\CollectFeesRequest;
use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Services\FeeCollectionAuthority;
use App\Services\FeePaymentService;
use App\Services\TeachingScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The counter desk, invoice first: find the fee record that is actually waiting
 * for money, read its balance back to the accountant, then record only the cash
 * that was really handed over against it and print one receipt.
 */
class FeeCollectionController extends Controller
{
    public function __construct(
        private readonly FeePaymentService $payments,
        private readonly FeeCollectionAuthority $authority,
        private readonly TeachingScope $scope,
    ) {}

    public function index(Request $request): View
    {
        $this->requireAnyPermission(['fees.collect', 'fees.collect.scoped']);

        $actor = $request->user();

        if ($actor !== null && $this->authority->isMonitoringOnly($actor)) {
            abort(403, __('Administrators and principals can monitor fee collections but cannot collect fees.'));
        }

        $sessions = AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']);
        $session = $this->resolveSession($request, $sessions);
        $search = trim((string) $request->query('search'));

        $invoices = $this->eligibleInvoices($actor, $session, $search)
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        $invoice = $request->filled('invoice')
            ? FeeInvoice::query()->with($this->invoiceRelations())->find((int) $request->query('invoice'))
            : null;

        if ($invoice !== null) {
            $this->authority->assertMayCollectFor($actor, $invoice->student, $invoice->academicSession);

            // A closed invoice is not a collection target; keep the desk on the
            // list instead of showing a form that can only fail.
            if (! $this->isOpen($invoice)) {
                $invoice = null;
            }
        }

        return view('fees.collect', [
            'search' => $search,
            'invoices' => $invoices,
            'invoice' => $invoice,
            'session' => $session,
            'sessions' => $sessions,
            'methods' => FeePayment::METHODS,
            'idempotencyKey' => (string) Str::uuid(),
            'referenceRequired' => (array) config('finance.reference_required_methods', []),
            'unrestricted' => $this->authority->isUnrestricted($actor),
        ]);
    }

    public function store(CollectFeesRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $invoiceId = array_key_first($request->lines());
        $student = FeeInvoice::query()->findOrFail($invoiceId)->student;
        $session = AcademicSession::query()->find((int) $request->validated('academic_session_id')) ?? $this->currentSession();

        $this->authority->assertMayCollectFor($actor, $student, $session);

        $payment = $this->payments->collectAcross($actor, $request->lines(), [
            'student_id' => $student->id,
            'method' => (string) $request->validated('method'),
            'transaction_ref' => $request->validated('transaction_ref'),
            'paid_at' => (string) $request->validated('paid_at'),
            'idempotency_key' => (string) $request->validated('idempotency_key'),
        ]);

        if ($payment->status === 'pending') {
            return redirect()
                ->route('fees.collect', ['invoice' => $invoiceId])
                ->with('success', __('The collection was held for verification. The receipt is issued once it is confirmed.'));
        }

        return redirect()
            ->route('fees.receipt', $payment)
            ->with('success', __(':received was recorded against :invoice and a receipt was issued.', [
                'received' => '৳ '.number_format((float) $payment->amount, 2),
                'invoice' => $payment->invoice_no,
            ]));
    }

    /**
     * Only an unpaid or part-paid invoice with a live balance can be collected
     * against. Paid, settled and cancelled records never reach the desk.
     *
     * @return Builder<FeeInvoice>
     */
    private function eligibleInvoices(?object $actor, ?AcademicSession $session, string $search): Builder
    {
        $query = FeeInvoice::query()
            ->whereIn('fee_invoices.status', FeeInvoice::OPEN_STATUSES)
            ->where('fee_invoices.due_total', '>', 0)
            ->when($session !== null, fn (Builder $q): Builder => $q->where('fee_invoices.academic_session_id', $session->id))
            ->with($this->invoiceRelations())
            ->orderBy('fee_invoices.due_date')
            ->orderBy('fee_invoices.id');

        if ($actor !== null && ! $this->authority->isUnrestricted($actor)) {
            $query->whereHas('student', fn (Builder $student): Builder => $this->scope->constrainStudents(
                $actor,
                $session,
                $student,
            ));
        }

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $inner) use ($like, $search): void {
                $inner->where('fee_invoices.invoice_no', 'like', $like)
                    ->orWhereHas('student', function (Builder $student) use ($like): void {
                        $student->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_id', 'like', $like);
                    })
                    ->orWhereHas('enrollment', fn (Builder $enrollment): Builder => $enrollment->where('roll_no', 'like', $search));
            });
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function invoiceRelations(): array
    {
        return [
            'student',
            'academicSession',
            'enrollment.schoolClass',
            'enrollment.section',
            'items.feeType',
            'allocations.payment',
        ];
    }

    private function isOpen(FeeInvoice $invoice): bool
    {
        return in_array($invoice->status, FeeInvoice::OPEN_STATUSES, true)
            && round((float) $invoice->due_total, 2) > 0;
    }

    private function resolveSession(Request $request, Collection $sessions): ?AcademicSession
    {
        $id = $request->query('session_id');

        if ($id !== null && $id !== '') {
            $found = $sessions->firstWhere('id', (int) $id);

            if ($found !== null) {
                return AcademicSession::query()->find($found->id);
            }
        }

        return $this->currentSession();
    }

    private function currentSession(): ?AcademicSession
    {
        return AcademicSession::query()->current()->first()
            ?? AcademicSession::query()->orderByDesc('start_date')->first();
    }
}
