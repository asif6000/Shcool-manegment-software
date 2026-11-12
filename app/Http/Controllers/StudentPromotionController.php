<?php

namespace App\Http\Controllers;

use App\Http\Requests\Students\PromoteStudentsRequest;
use App\Services\StudentPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentPromotionController extends Controller
{
    public function __construct(
        private readonly StudentPromotionService $promotions,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('academic.view');

        $filters = $request->only(['from_session_id', 'class_id', 'section_id', 'search', 'state']);

        return view('promotions.index', $this->promotions->workspace($filters));
    }

    public function preview(PromoteStudentsRequest $request): JsonResponse
    {
        return response()->json($this->promotions->preview($request->validated()));
    }

    public function store(PromoteStudentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $result = $this->promotions->process($validated, $request->user());

        return redirect()
            ->route('promotions.index', ['from_session_id' => $validated['from_session_id']])
            ->with('success', __(':action completed for :count student(s).', [
                'action' => $result['action_label'],
                'count' => $result['processed'],
            ]))
            ->with('promotion_result', $result);
    }

    public function report(Request $request): View
    {
        $this->requirePermission('academic.view');

        $filters = $request->only(['from_session_id', 'to_session_id', 'action', 'class_id', 'search']);

        return view('promotions.reports', $this->promotions->report($filters));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('academic.view');

        $filters = $request->only(['from_session_id', 'to_session_id', 'action', 'class_id', 'search']);
        $rows = $this->promotions->exportRows($filters);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                __('Student ID'),
                __('Admission no'),
                __('Student'),
                __('Action'),
                __('From session'),
                __('From class'),
                __('From section'),
                __('From roll'),
                __('To session'),
                __('To class'),
                __('To section'),
                __('To roll'),
                __('Eligibility'),
                __('Exam'),
                __('GPA'),
                __('Grade'),
                __('Processed by'),
                __('Processed at'),
                __('Notes'),
            ]);

            foreach ($rows as $promotion) {
                fputcsv($handle, [
                    $promotion->student?->student_id,
                    $promotion->student?->admission_no,
                    $promotion->student?->full_name,
                    $promotion->actionLabel(),
                    $promotion->fromSession?->name,
                    $promotion->fromClass?->name,
                    $promotion->fromSection?->name,
                    $promotion->from_roll_no,
                    $promotion->toSession?->name,
                    $promotion->toClass?->name,
                    $promotion->toSection?->name,
                    $promotion->to_roll_no,
                    $promotion->eligibility,
                    $promotion->resultExam?->name,
                    $promotion->result_snapshot['gpa'] ?? null,
                    $promotion->result_snapshot['grade_letter'] ?? null,
                    $promotion->promoter?->name,
                    $promotion->promoted_at?->format('Y-m-d H:i:s'),
                    $promotion->notes,
                ]);
            }

            fclose($handle);
        }, 'student-promotions-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
