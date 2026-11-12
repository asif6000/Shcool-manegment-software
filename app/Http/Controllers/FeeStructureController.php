<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\SaveFeeStructureRequest;
use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Services\FeeStructureService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The class-level amount for a fee type in a session: "tuition is ৳500 a month
 * for Class Six in 2026".
 */
class FeeStructureController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FeeStructureService $structures,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('fees.view');

        $sessions = AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']);
        $sessionId = (int) $request->query('session_id', (string) ($sessions->first()->id ?? 0));

        return view('fees.structures', [
            'sessions' => $sessions,
            'session' => $sessions->firstWhere('id', $sessionId),
            'structures' => FeeStructure::query()
                ->where('academic_session_id', $sessionId)
                ->with(['feeType', 'schoolClass'])
                ->orderBy('class_id')
                ->orderBy('fee_type_id')
                ->get(),
            'feeTypes' => FeeType::query()->active()->orderBy('name')->get(['id', 'name', 'frequency']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name', 'level']),
        ]);
    }

    public function store(SaveFeeStructureRequest $request): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $structure = FeeStructure::query()->create($request->validated());

        $this->audit->log(
            event: 'fee_structure.created',
            auditable: $structure,
            description: __('Fee :fee was set at :amount for class :class', [
                'fee' => $structure->feeType?->name ?? __('Fee'),
                'amount' => '৳ '.number_format((float) $structure->amount, 2),
                'class' => $structure->schoolClass?->name ?? (string) $structure->class_id,
            ]),
            newValues: $request->validated(),
            actor: $request->user(),
        );

        return back()->with('success', __('The class fee was saved.'));
    }

    public function update(SaveFeeStructureRequest $request, FeeStructure $feeStructure): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $before = $feeStructure->only(array_keys($request->validated()));

        // Deliberately not re-billing: invoices already issued keep the amount
        // they were generated with, so history is never rewritten.
        $feeStructure->update($request->validated());

        $this->audit->log(
            event: 'fee_structure.updated',
            auditable: $feeStructure,
            description: __('Fee :fee for class :class was changed', [
                'fee' => $feeStructure->feeType?->name ?? __('Fee'),
                'class' => $feeStructure->schoolClass?->name ?? (string) $feeStructure->class_id,
            ]),
            oldValues: $before,
            newValues: $request->validated(),
            actor: $request->user(),
        );

        return back()->with('success', __('The class fee was updated. Invoices already issued keep the amounts they were billed with.'));
    }

    public function toggle(Request $request, FeeStructure $feeStructure): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $this->structures->toggle($request->user(), $feeStructure);

        return back()->with('success', __('The class fee was updated.'));
    }

    /**
     * Copy this session's class fees into another session, skipping any pair
     * that already exists so nothing is duplicated silently.
     */
    public function copy(Request $request): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $data = $request->validate([
            'from_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')],
            'to_session_id' => ['required', 'integer', 'different:from_session_id', Rule::exists('academic_sessions', 'id')],
        ]);

        $result = $this->structures->copySession(
            $request->user(),
            (int) $data['from_session_id'],
            (int) $data['to_session_id'],
        );

        return back()->with('success', __('Copied :copied class fees. :skipped were already set in the new session.', [
            'copied' => $result['copied'],
            'skipped' => $result['skipped'],
        ]));
    }
}
