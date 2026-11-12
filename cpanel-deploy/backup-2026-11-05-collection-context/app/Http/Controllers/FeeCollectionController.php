<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\CollectFeesRequest;
use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\FeeCollectionAuthority;
use App\Services\FeePaymentService;
use App\Services\TeachingScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The counter desk: find a learner, see the fees actually assigned to them,
 * say how much money was really handed over, and get one receipt for it all.
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

        $students = Student::query()
            ->with('enrollments.schoolClass')
            ->when(! $this->authority->isUnrestricted($actor), fn (Builder $query): Builder => $this->scope->constrainStudents(
                $actor,
                $session,
                $query,
            ))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('student_id', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereHas('enrollments', fn (Builder $enrollment): Builder => $enrollment->where('roll_no', 'like', $search));
                });
            })
            ->orderBy('first_name')
            ->limit(30)
            ->get(['id', 'student_id', 'first_name', 'last_name']);

        $student = $request->filled('student')
            ? Student::query()->findOrFail((int) $request->query('student'))
            : null;

        if ($student !== null) {
            $this->authority->assertMayCollectFor($actor, $student, $session);
        }

        return view('fees.collect', [
            'search' => $search,
            'students' => $students,
            'student' => $student,
            'invoices' => $student === null
                ? collect()
                : $this->openInvoices($student, $session),
            'session' => $session,
            'sessions' => $sessions,
            'methods' => FeePayment::METHODS,
            'idempotencyKey' => (string) Str::uuid(),
            'bankAccounts' => BankAccount::query()->active()->orderBy('name')->get(['id', 'name']),
            'referenceRequired' => (array) config('finance.reference_required_methods', []),
            'unrestricted' => $this->authority->isUnrestricted($actor),
        ]);
    }

    public function store(CollectFeesRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $student = Student::query()->findOrFail((int) $request->validated('student_id'));
        $session = AcademicSession::query()->find((int) $request->validated('academic_session_id')) ?? $this->currentSession();

        $this->authority->assertMayCollectFor($actor, $student, $session);

        $lines = $request->lines();

        $payment = $this->payments->collectAcross($actor, $lines, [
            'student_id' => $student->id,
            'method' => (string) $request->validated('method'),
            'bank_account_id' => $request->validated('bank_account_id'),
            'transaction_ref' => $request->validated('transaction_ref'),
            'paid_at' => (string) $request->validated('paid_at'),
            'notes' => $request->validated('notes'),
            'settle_in_full' => (bool) $request->boolean('settle_in_full'),
            'idempotency_key' => (string) $request->validated('idempotency_key'),
        ]);

        if ($payment->status === 'pending') {
            return redirect()
                ->route('fees.collect', ['student' => $student->id])
                ->with('success', __('The collection was held for verification. The receipt is issued once it is confirmed.'));
        }

        return redirect()
            ->route('fees.receipt', $payment)
            ->with('success', __(':received was recorded against :count fee(s) and one receipt was issued.', [
                'received' => '৳ '.number_format((float) $payment->amount, 2),
                'count' => $payment->allocations()->count(),
            ]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, FeeInvoice>
     */
    private function openInvoices(Student $student, ?AcademicSession $session): \Illuminate\Support\Collection
    {
        return FeeInvoice::query()
            ->where('student_id', $student->id)
            ->when($session !== null, fn (Builder $query): Builder => $query->where('academic_session_id', $session->id))
            ->whereIn('status', FeeInvoice::OPEN_STATUSES)
            ->where('due_total', '>', 0)
            ->with(['academicSession', 'items.feeType', 'enrollment.schoolClass', 'enrollment.section'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    private function resolveSession(Request $request, \Illuminate\Support\Collection $sessions): ?AcademicSession
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
