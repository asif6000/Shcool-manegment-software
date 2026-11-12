<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exams\ReviewMarkSubmissionRequest;
use App\Models\Exam;
use App\Models\MarkSubmission;
use App\Services\MarkSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The queue side of marks: a teacher hands a sheet over, the office decides it.
 * A teacher only ever sees their own submissions; the office sees the school.
 */
class MarkSubmissionController extends Controller
{
    public function __construct(private readonly MarkSubmissionService $submissions) {}

    public function index(Request $request): View
    {
        $this->requireAnyPermission(['marks.approve', 'marks.submit']);

        $actor = $request->user();
        $reviews = (bool) $actor->can('marks.approve');
        $status = $this->statusFilter($request->query('status'));
        $examId = $request->filled('exam_id') ? (int) $request->query('exam_id') : null;

        $submissions = MarkSubmission::query()
            ->with(['paper.schoolClass', 'paper.subject', 'exam', 'section', 'submittedBy', 'reviewedBy'])
            ->when(! $reviews, fn ($query) => $query->where('submitted_by', $actor->id))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($examId !== null, fn ($query) => $query->where('exam_id', $examId))
            ->orderByDesc('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('exams.mark-submissions', [
            'reviews' => $reviews,
            'status' => $status,
            'examId' => $examId,
            'exams' => Exam::query()->orderByDesc('start_date')->get(['id', 'name', 'exam_type']),
            'submissions' => $submissions,
            'awaitingCount' => MarkSubmission::query()->where('status', MarkSubmission::STATUS_SUBMITTED)->count(),
        ]);
    }

    public function decide(
        ReviewMarkSubmissionRequest $request,
        MarkSubmission $submission,
        string $action,
    ): RedirectResponse {
        $note = $request->validated('note');

        $decided = $action === 'reject'
            ? $this->submissions->reject($request->user(), $submission, (string) $note)
            : $this->submissions->approve($request->user(), $submission, filled($note) ? (string) $note : null);

        return back()->with('success', __('Marks for :sheet were :decision.', [
            'sheet' => $decided->sheetLabel(),
            'decision' => $action === 'reject' ? __('sent back') : __('approved'),
        ]));
    }

    private function statusFilter(mixed $value): ?string
    {
        return is_string($value) && in_array($value, MarkSubmission::STATUSES, true)
            ? $value
            : null;
    }
}
