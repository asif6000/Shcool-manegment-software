<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exams\UpdateGradeScaleRequest;
use App\Services\GradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GradeScaleController extends Controller
{
    public function __construct(private readonly GradeService $grades) {}

    public function edit(): View
    {
        $this->requirePermission('exams.manage');

        $scale = $this->grades->ensureDefaultScale();

        return view('exams.grade-scale', [
            'scale' => $scale,
            'items' => $scale->items->sortBy('min_percentage')->values(),
        ]);
    }

    public function update(UpdateGradeScaleRequest $request): RedirectResponse
    {
        $this->grades->updateScale($request->user(), $request->validated()['items']);

        return redirect()
            ->route('exams.grade-scale.edit')
            ->with('success', __('The grading scale was updated.'));
    }
}
