<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\ApplyOwnLeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffLeave;
use App\Models\Teacher;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A teacher's own leave. The staff profile is read from the signed-in account,
 * so there is exactly one set of rows to list and one person allowed to write
 * them; the office register at staff/leaves stays the place decisions happen.
 */
class TeacherLeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leaves) {}

    public function index(Request $request): View
    {
        $this->requirePermission('leave.apply');

        $profile = $this->profile($request);
        $status = $request->query('status');
        $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : null;

        $rows = StaffLeave::query()
            ->with(['leaveType', 'approver'])
            ->where('teacher_id', $profile?->id ?? 0)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('teacher.leave', [
            'rows' => $rows,
            'status' => $status,
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(),
            'profile' => $profile,
            'pending' => $profile === null ? 0 : StaffLeave::query()->where('teacher_id', $profile->id)->where('status', 'pending')->count(),
            'daysThisYear' => $profile === null
                ? 0.0
                : (float) StaffLeave::query()
                    ->where('teacher_id', $profile->id)
                    ->where('status', 'approved')
                    ->whereBetween('start_date', [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()])
                    ->sum('total_days'),
        ]);
    }

    public function store(ApplyOwnLeaveRequest $request): RedirectResponse
    {
        $this->leaves->applyFor($request->user(), $request->validated());

        return redirect()
            ->route('teacher.leave.index')
            ->with('success', __('Your leave request is with the office.'));
    }

    /**
     * The staff profile behind this login, or null when the account has none.
     */
    private function profile(Request $request): ?Teacher
    {
        return Teacher::query()->where('user_id', $request->user()->id)->first();
    }
}
