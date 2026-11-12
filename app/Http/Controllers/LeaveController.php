<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\SaveLeaveTypeRequest;
use App\Http\Requests\Staff\SaveStaffLeaveRequest;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\StaffLeave;
use App\Models\Teacher;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leaves) {}

    public function index(Request $request): View
    {
        $this->requirePermission('leave.view');

        $filters = $request->only(['status']);

        $requests = StaffLeave::query()
            ->with(['leaveType', 'teacher', 'employee', 'approver'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('start_date')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('staff.leaves', [
            'requests' => $requests,
            'filters' => $filters,
            'leaveTypes' => LeaveType::query()->withCount('leaves')->orderBy('name')->get(),
            'teachers' => Teacher::query()->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'employees' => Employee::query()->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function storeType(SaveLeaveTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_paid'] = $request->boolean('is_paid');
        $data['is_active'] = $request->boolean('is_active', true);

        $this->leaves->createType($request->user(), $data);

        return back()->with('success', __('The leave type was saved.'));
    }

    public function updateType(SaveLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $data = $request->validated();
        $data['is_paid'] = $request->boolean('is_paid');
        $data['is_active'] = $request->boolean('is_active');

        $this->leaves->updateType($request->user(), $leaveType, $data);

        return back()->with('success', __('The leave type was updated.'));
    }

    public function destroyType(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->requirePermission('leave.manage');

        $this->leaves->deleteType($request->user(), $leaveType);

        return back()->with('success', __('The leave type was deleted.'));
    }

    public function store(SaveStaffLeaveRequest $request): RedirectResponse
    {
        $leave = $this->leaves->request($request->user(), $request->validated());

        return back()->with('success', __('A leave request was recorded for :name.', ['name' => $leave->staffName()]));
    }

    public function approve(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $this->requirePermission('leave.manage');

        $this->leaves->decide($request->user(), $staffLeave, 'approved');

        return back()->with('success', __('The leave request was approved.'));
    }

    public function reject(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $this->requirePermission('leave.manage');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->leaves->decide($request->user(), $staffLeave, 'rejected', $data['reason']);

        return back()->with('success', __('The leave request was rejected.'));
    }
}
