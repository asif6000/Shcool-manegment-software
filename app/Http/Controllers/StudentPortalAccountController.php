<?php

namespace App\Http\Controllers;

use App\Http\Requests\Students\ManageStudentPortalRequest;
use App\Models\Student;
use App\Services\StudentAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentPortalAccountController extends Controller
{
    public function __construct(private readonly StudentAccountService $accounts) {}

    public function store(ManageStudentPortalRequest $request, Student $student): RedirectResponse
    {
        $result = $this->accounts->create($request->user(), $student, $request->validated());

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('A portal account was created for :name.', ['name' => $student->full_name]))
            ->with('portal_password', $result['password']);
    }

    public function reset(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->can('users.reset-password'), 403);

        $result = $this->accounts->resetPassword($request->user(), $student);

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('The portal password was reset. Share the new password once, then ask the student to change it.'))
            ->with('portal_password', $result['password']);
    }

    public function status(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->can('users.update'), 403);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->accounts->setActive($request->user(), $student, (bool) $data['is_active']);

        return redirect()
            ->route('students.show', $student)
            ->with('success', $data['is_active']
                ? __('Portal access was enabled for this student.')
                : __('Portal access was disabled for this student.'));
    }
}
