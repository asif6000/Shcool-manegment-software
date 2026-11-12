<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPortalStudent;
use App\Http\Requests\Portal\ApplyLeaveRequest;
use App\Models\StudentLeave;
use App\Services\StudentLeaveService;
use App\Services\StudentPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalLeaveController extends Controller
{
    use ResolvesPortalStudent;

    public function __construct(
        private readonly StudentPortalService $portal,
        private readonly StudentLeaveService $leaves,
    ) {}

    public function index(Request $request): View
    {
        $student = $this->student($request);
        $student->loadMissing('guardians');

        return view('student.leave.index', [
            'student' => $student,
            'types' => StudentLeave::TYPES,
            'guardianPhone' => $student->primaryGuardian()?->phone,
            ...$this->portal->leaves($student),
        ]);
    }

    public function store(ApplyLeaveRequest $request): RedirectResponse
    {
        $student = $this->student($request);

        $this->leaves->apply($student, $request->user(), $request->validated());

        return redirect()
            ->route('student.leave.index')
            ->with('success', __('Your leave request has been sent to the school office.'));
    }

    public function cancel(Request $request, StudentLeave $leave): RedirectResponse
    {
        $student = $this->student($request);

        $this->leaves->cancel($student, $request->user(), $leave);

        return redirect()
            ->route('student.leave.index')
            ->with('success', __('The leave request has been withdrawn.'));
    }
}
