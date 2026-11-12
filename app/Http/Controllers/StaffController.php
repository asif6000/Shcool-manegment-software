<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\SaveEmployeeRequest;
use App\Http\Requests\Staff\SaveTeacherRequest;
use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\StaffService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staff) {}

    public function index(Request $request): View
    {
        $this->requirePermission('staff.view');

        $type = in_array($request->query('type'), ['teachers', 'employees'], true)
            ? (string) $request->query('type')
            : 'teachers';

        $filters = $request->only(['search', 'status']);

        return view('staff.index', [
            'type' => $type,
            'staff' => $this->staff->paginate($type, $filters),
            'filters' => $filters,
            'counts' => [
                'teachers' => Teacher::query()->count(),
                'employees' => Employee::query()->count(),
            ],
        ]);
    }

    public function createTeacher(): View
    {
        $this->requirePermission('staff.create');

        return view('staff.create', [
            'type' => 'teachers',
            'classes' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function createEmployee(): View
    {
        $this->requirePermission('staff.create');

        return view('staff.create', ['type' => 'employees']);
    }

    public function storeTeacher(SaveTeacherRequest $request): RedirectResponse
    {
        $teacher = $this->staff->create('teachers', $request->validated(), $request->file('photo'), $request->user());

        return redirect()
            ->route('staff.teachers.show', $teacher)
            ->with('success', __('Teacher :name was added.', ['name' => $teacher->full_name]));
    }

    public function storeEmployee(SaveEmployeeRequest $request): RedirectResponse
    {
        $employee = $this->staff->create('employees', $request->validated(), $request->file('photo'), $request->user());

        return redirect()
            ->route('staff.employees.show', $employee)
            ->with('success', __('Staff member :name was added.', ['name' => $employee->full_name]));
    }

    public function showTeacher(Teacher $teacher): View
    {
        $this->requirePermission('staff.view');

        return $this->profile('teachers', $teacher);
    }

    public function showEmployee(Employee $employee): View
    {
        $this->requirePermission('staff.view');

        return $this->profile('employees', $employee);
    }

    public function editTeacher(Teacher $teacher): View
    {
        $this->requirePermission('staff.update');

        return view('staff.edit', [
            'type' => 'teachers',
            'person' => $teacher,
            'classes' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function editEmployee(Employee $employee): View
    {
        $this->requirePermission('staff.update');

        return view('staff.edit', ['type' => 'employees', 'person' => $employee]);
    }

    public function updateTeacher(SaveTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $this->staff->update('teachers', $teacher, $request->validated(), $request->file('photo'), $request->user());

        return redirect()
            ->route('staff.teachers.show', $teacher)
            ->with('success', __('Teacher :name was updated.', ['name' => $teacher->full_name]));
    }

    public function updateEmployee(SaveEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->staff->update('employees', $employee, $request->validated(), $request->file('photo'), $request->user());

        return redirect()
            ->route('staff.employees.show', $employee)
            ->with('success', __('Staff member :name was updated.', ['name' => $employee->full_name]));
    }

    public function destroyTeacher(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->requirePermission('staff.delete');

        $name = $teacher->full_name;
        $this->staff->delete('teachers', $teacher, $request->user());

        return redirect()
            ->route('staff.index', ['type' => 'teachers'])
            ->with('success', __('Teacher :name was permanently deleted.', ['name' => $name]));
    }

    public function destroyEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $this->requirePermission('staff.delete');

        $name = $employee->full_name;
        $this->staff->delete('employees', $employee, $request->user());

        return redirect()
            ->route('staff.index', ['type' => 'employees'])
            ->with('success', __('Staff member :name was permanently deleted.', ['name' => $name]));
    }

    public function teacherPhoto(Teacher $teacher): StreamedResponse
    {
        $this->requirePermission('staff.view');

        return $this->photoResponse($teacher->photo_path);
    }

    public function employeePhoto(Employee $employee): StreamedResponse
    {
        $this->requirePermission('staff.view');

        return $this->photoResponse($employee->photo_path);
    }

    private function profile(string $type, Model $person): View
    {
        $person->load([
            'leaves' => fn ($query) => $query->with('leaveType')->orderByDesc('start_date')->limit(8),
            'salaryStructures' => fn ($query) => $query->orderByDesc('effective_from'),
        ]);

        return view('staff.show', [
            'type' => $type,
            'person' => $person,
        ]);
    }

    private function photoResponse(?string $path): StreamedResponse
    {
        abort_if($path === null || ! str_starts_with($path, 'teachers/'), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
