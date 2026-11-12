<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\AccountantCollectFeeRequest;
use App\Models\AcademicSession;
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

        $studentId = $request->filled('student_id') ? (int) $request->query('student_id') : null;
        $feeTypeId = $request->filled('fee_type_id') ? (int) $request->query('fee_type_id') : null;
        $month = $request->filled('month') ? (int) $request->query('month') : (int) now()->month;

        $enrollments = $this->counter->enrollments($session);
        $enrollment = $studentId !== null ? $enrollments->firstWhere('student_id', $studentId) : null;
        $student = $enrollment?->student;
        $classId = $enrollment !== null ? (int) $enrollment->class_id : null;

        $feeTypes = $this->counter->feeTypes($session, $classId);
        $feeType = $feeTypeId !== null ? $feeTypes->firstWhere('id', $feeTypeId) : null;

        $period = null;
        $snapshot = null;

        if ($student !== null && $enrollment !== null && $session !== null && $feeType !== null) {
            $period = $this->counter->periodFor($session, $feeType->isMonthly() ? $month : null, $feeType);
            $snapshot = $this->counter->snapshot($student, $session, $classId, $feeType, $period);
        }

        return view('fees.counter', [
            'sessions' => $sessions,
            'session' => $session,
            'enrollments' => $enrollments,
            'enrollment' => $enrollment,
            'student' => $student,
            'classId' => $classId,
            'feeTypes' => $feeTypes,
            'feeType' => $feeType,
            'month' => $month,
            'period' => $period,
            'snapshot' => $snapshot,
            'history' => $this->counter->history($actor, $studentId),
            'methods' => FeePayment::METHODS,
            'referenceRequired' => (array) config('finance.reference_required_methods', []),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(AccountantCollectFeeRequest $request): RedirectResponse
    {
        $data = $request->collectionData();

        // The class is never taken from the form: it is the learner's own
        // active enrollment for the chosen session.
        $enrollment = $this->counter->enrollmentFor(
            Student::query()->findOrFail((int) $data['student_id']),
            AcademicSession::query()->findOrFail((int) $data['academic_session_id']),
        );

        $data['class_id'] = $enrollment?->class_id;

        $payment = $this->counter->collect($request->user(), $data);

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
