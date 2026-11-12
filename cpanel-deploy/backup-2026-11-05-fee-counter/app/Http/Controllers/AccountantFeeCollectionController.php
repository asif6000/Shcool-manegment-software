<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\AccountantCollectFeeRequest;
use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\AccountantFeeCollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A dedicated accountant desk. Admin and principal accounts may monitor this
 * screen's output in reports, but they cannot open the collection form.
 */
class AccountantFeeCollectionController extends Controller
{
    public function __construct(private readonly AccountantFeeCollectionService $counter) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->counter->assertAccountant($actor);

        $sessions = $this->counter->sessions();
        $session = $this->resolveSession($request, $sessions);

        $classId = $request->filled('class_id') ? (int) $request->query('class_id') : null;
        $studentId = $request->filled('student_id') ? (int) $request->query('student_id') : null;
        $feeTypeId = $request->filled('fee_type_id') ? (int) $request->query('fee_type_id') : null;
        $year = (int) ($request->query('year') ?: ($session?->start_date?->year ?? now()->year));
        $month = (int) ($request->query('month') ?: now()->month);

        $students = $this->counter->students($session, $classId);
        $feeTypes = $this->counter->feeTypes($session, $classId);

        $student = $studentId !== null ? Student::query()->find($studentId) : null;
        $feeType = $feeTypeId !== null ? FeeType::query()->find($feeTypeId) : null;
        $period = $this->counter->period($year, $month);

        $snapshot = $student !== null && $feeType !== null && $session !== null && $classId !== null
            ? $this->counter->snapshot($student, $session, $classId, $feeType, $period)
            : null;

        return view('fees.counter', [
            'sessions' => $sessions,
            'session' => $session,
            'classes' => $this->counter->classes(),
            'classId' => $classId,
            'students' => $students,
            'student' => $student,
            'feeTypes' => $feeTypes,
            'feeType' => $feeType,
            'year' => $year,
            'month' => $month,
            'period' => $period,
            'snapshot' => $snapshot,
            'history' => $this->counter->history($actor, $studentId),
            'methods' => FeePayment::METHODS,
            'bankAccounts' => BankAccount::query()->active()->orderBy('name')->get(['id', 'name']),
            'referenceRequired' => (array) config('finance.reference_required_methods', []),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(AccountantCollectFeeRequest $request): RedirectResponse
    {
        $payment = $this->counter->collect($request->user(), $request->collectionData());

        return redirect()
            ->route('fees.receipt', $payment)
            ->with('success', __('Payment :invoice recorded for :student. Receipt :receipt is ready to print.', [
                'invoice' => $payment->invoice_no,
                'student' => $payment->student?->full_name ?? __('student'),
                'receipt' => $payment->receipt?->receipt_no,
            ]));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, AcademicSession>  $sessions
     */
    private function resolveSession(Request $request, $sessions): ?AcademicSession
    {
        $id = $request->query('session_id');

        if (filled($id)) {
            $found = AcademicSession::query()->find((int) $id);

            if ($found !== null) {
                return $found;
            }
        }

        return AcademicSession::query()->current()->first()
            ?? $sessions->first();
    }
}
