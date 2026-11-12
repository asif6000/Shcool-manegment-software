<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateSchoolRequest;
use App\Models\School;
use App\Services\SchoolService;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function __construct(private readonly SchoolService $schools) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('schools.show');
    }

    public function show(Request $request): View
    {
        $school = $this->currentSchool($request);
        $this->authorize('view', $school);

        $school->loadCount(['users', 'students', 'teachers', 'employees', 'academicSessions', 'classes']);

        return view('schools.show', [
            'school' => $school,
        ]);
    }

    public function edit(Request $request): View
    {
        $school = $this->currentSchool($request);
        $this->authorize('update', $school);

        return view('schools.edit', [
            'school' => $school,
            'divisions' => [
                'Barishal', 'Chattogram', 'Dhaka', 'Khulna', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Sylhet',
            ],
        ]);
    }

    public function update(UpdateSchoolRequest $request, SchoolService $schools): RedirectResponse
    {
        $school = $this->currentSchool($request);

        $schools->update(
            $request->user(),
            $school,
            $request->validated(),
            $request->file('logo'),
        );

        return redirect()
            ->route('schools.show')
            ->with('success', __('The school profile has been updated.'));
    }

    private function currentSchool(Request $request): School
    {
        $schoolId = app(SchoolContext::class)->id() ?? $request->user()->school_id;

        abort_if($schoolId === null, 403, __('Your account is not linked to a school.'));

        return School::query()->findOrFail($schoolId);
    }
}
