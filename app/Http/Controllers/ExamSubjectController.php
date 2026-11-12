<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exams\SaveExamSubjectRequest;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamSubjectController extends Controller
{
    public function __construct(private readonly ExamService $exams) {}

    public function store(SaveExamSubjectRequest $request, Exam $exam): RedirectResponse
    {
        $paper = $this->exams->addPaper($request->user(), $exam, $request->validated());

        return redirect()
            ->route('exams.show', $exam)
            ->with('success', __('The paper was added to the exam.'))
            ->with('paper_added', $paper->id);
    }

    public function destroy(Request $request, Exam $exam, ExamSubject $paper): RedirectResponse
    {
        $this->requirePermission('exams.manage');

        abort_unless($paper->exam_id === $exam->id, 404);

        $this->exams->removePaper($request->user(), $exam, $paper);

        return redirect()
            ->route('exams.show', $exam)
            ->with('success', __('The paper was removed from the exam.'));
    }
}
