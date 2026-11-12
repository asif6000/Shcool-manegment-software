<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\SaveSalaryStructureRequest;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\Teacher;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryController extends Controller
{
    public function __construct(private readonly SalaryService $salaries) {}

    public function index(Request $request): View
    {
        $this->requirePermission('salary.manage');

        return view('staff.salary', [
            'editing' => $request->filled('edit')
                ? SalaryStructure::query()->findOrFail((int) $request->query('edit'))
                : null,
            'structures' => SalaryStructure::query()
                ->with(['teacher', 'employee'])
                ->orderByDesc('is_active')
                ->orderByDesc('effective_from')
                ->get(),
            'teachers' => Teacher::query()->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'employees' => Employee::query()->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(SaveSalaryStructureRequest $request): RedirectResponse
    {
        $structure = $this->salaries->createStructure($request->user(), $request->validated());

        return back()->with('success', __('A salary structure was saved for :name.', ['name' => $structure->staffName()]));
    }

    public function update(SaveSalaryStructureRequest $request, SalaryStructure $salaryStructure): RedirectResponse
    {
        $this->salaries->updateStructure($request->user(), $salaryStructure, $request->validated());

        return back()->with('success', __('The salary structure was updated.'));
    }

    public function destroy(Request $request, SalaryStructure $salaryStructure): RedirectResponse
    {
        $this->requirePermission('salary.manage');

        $this->salaries->deleteStructure($request->user(), $salaryStructure);

        return back()->with('success', __('The salary structure was deleted.'));
    }
}
