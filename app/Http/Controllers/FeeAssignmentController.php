<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\SaveFeeAssignmentRequest;
use App\Models\AcademicSession;
use App\Models\FeeStudentAssignment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\FeeAssignmentService;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Which learners a fee applies to. Nothing here bills anybody: it only records
 * the rule the invoice generator later follows.
 */
class FeeAssignmentController extends Controller
{
    public function __construct(private readonly FeeAssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $this->requirePermission('fees.view');

        $sessions = $this->sessions();
        $session = $sessions->firstWhere('id', (int) $request->query('session_id', (string) $sessions->first()->id));
        $filters = $request->validate([
            'fee_type_id' => ['nullable', 'integer'],
            'student' => ['nullable', 'string', 'max:80'],
        ]);

        $query = $this->listing($session?->id, $filters);

        return $this->view($session, [
            'assignments' => $this->paginate($query),
            'summary' => [
                'students' => (clone $query)->distinct()->count('student_id'),
                'value' => round((float) (clone $query)->where('is_active', true)->sum('amount'), 2),
                'inactive' => (clone $query)->where('is_active', false)->count(),
            ],
            'filters' => $filters,
            'preview' => null,
        ]);
    }

    public function preview(SaveFeeAssignmentRequest $request): View
    {
        $preview = $this->assignments->preview($request->user(), $request->validated());

        $session = AcademicSession::query()->find((int) $request->validated('academic_session_id'));
        $sessions = $this->sessions();

        return $this->view($session, [
            'assignments' => $this->paginate(
                FeeStudentAssignment::query()->where('academic_session_id', $session?->id ?? 0)
            ),
            'summary' => ['students' => 0, 'value' => 0.0, 'inactive' => 0],
            'filters' => $request->only(['fee_type_id', 'student']),
            'preview' => $preview + ['input' => $request->validated()],
        ]);
    }

    public function apply(SaveFeeAssignmentRequest $request): RedirectResponse
    {
        $result = $this->assignments->apply($request->user(), $request->validated());

        return redirect()
            ->route('fees.assignments.index', ['session_id' => $request->validated('academic_session_id')])
            ->with('success', __(':created student(s) were added to this fee. :skipped already had it and were not duplicated.', [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ]));
    }

    public function toggle(Request $request, FeeStudentAssignment $assignment, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        if ($assignment->is_active) {
            $this->assignments->deactivate($request->user(), $assignment);

            return back()->with('success', __('The fee was removed for that student. Invoices already issued were left as they are.'));
        }

        try {
            $assignment->update(['is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', __('That student is already on this fee, so nothing was reactivated.'));
        }

        $audit->log(
            event: 'fee_assignment.activated',
            auditable: $assignment,
            description: __(':fee was restored for :student', [
                'fee' => $assignment->feeType?->name ?? __('Fee'),
                'student' => $assignment->student?->full_name ?? __('Student'),
            ]),
            oldValues: ['is_active' => false],
            newValues: ['is_active' => true],
            actor: $request->user(),
        );

        return back()->with('success', __('The fee was reactivated for that student.'));
    }

    /**
     * @param  array{fee_type_id?: int|null, student?: string|null}  $filters
     */
    private function listing(?int $sessionId, array $filters): Builder
    {
        $query = FeeStudentAssignment::query()
            ->with(['student', 'feeType', 'schoolClass', 'section', 'academicSession'])
            ->where('academic_session_id', $sessionId ?? 0);

        if (filled($filters['fee_type_id'] ?? null)) {
            $query->where('fee_type_id', (int) $filters['fee_type_id']);
        }

        if (filled($filters['student'] ?? null)) {
            $like = '%'.$filters['student'].'%';
            $query->whereHas('student', fn (Builder $s): Builder => $s
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('student_id', 'like', $like));
        }

        return $query;
    }

    private function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->orderByDesc('id')->paginate(25)->withQueryString();
    }

    private function sessions(): Collection
    {
        return AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(?AcademicSession $session, array $data): View
    {
        return view('fees.assignments', $data + [
            'session' => $session,
            'feeTypes' => FeeType::query()->active()->orderBy('name')->get(['id', 'name', 'frequency']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name']),
            'sections' => Section::query()->orderBy('name')->get(['id', 'name', 'class_id']),
            'students' => Student::query()->orderBy('first_name')->limit(400)->get(['id', 'first_name', 'last_name', 'student_id']),
        ] + ['sessions' => $this->sessions()]);
    }
}
