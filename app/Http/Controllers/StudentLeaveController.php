<?php

namespace App\Http\Controllers;

use App\Http\Requests\Students\DecideStudentLeaveRequest;
use App\Models\SchoolClass;
use App\Models\StudentLeave;
use App\Services\StudentLeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentLeaveController extends Controller
{
    public function __construct(private readonly StudentLeaveService $leaves) {}

    public function index(Request $request): View
    {
        $this->requirePermission('leave.view');

        $status = $request->input('status');
        $classId = $request->integer('class') ?: null;

        $records = StudentLeave::query()
            // Class and section live on the enrollment, so one eager load covers
            // the whole page instead of a lookup per row.
            ->with([
                'decider',
                // An eager-load constraint receives the relation, not a plain
                // query builder.
                'student.enrollments' => fn (HasMany $query): HasMany => $query
                    ->where('status', 'active')
                    ->with(['schoolClass', 'section']),
            ])
            ->when(in_array($status, StudentLeave::STATUSES, true), fn (Builder $q): Builder => $q->where('status', $status))
            ->when($classId !== null, fn (Builder $q): Builder => $q->whereHas('student.enrollments',
                fn (Builder $inner): Builder => $inner->where('class_id', $classId)->where('status', 'active')))
            ->when($request->filled('from'), fn (Builder $q): Builder => $q->whereDate('to_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q): Builder => $q->whereDate('from_date', '<=', $request->date('to')))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('from_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('student_leaves.index', [
            'leaves' => $records,
            'filters' => $request->only(['status', 'class', 'from', 'to']),
            'statuses' => StudentLeave::STATUSES,
            'pendingCount' => StudentLeave::query()->pending()->count(),
            'classes' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level']),
        ]);
    }

    public function decide(DecideStudentLeaveRequest $request, StudentLeave $leave): RedirectResponse
    {
        $decision = (string) $request->input('decision');

        $this->leaves->decide($request->user(), $leave, $decision, ['notes' => $request->input('notes')]);

        return back()->with('success', $decision === 'approved'
            ? __('The leave was approved and the student has been notified.')
            : __('The leave was rejected and the student has been notified.'));
    }
}
