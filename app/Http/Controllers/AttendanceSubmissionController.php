<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\ReviewAttendanceSubmissionRequest;
use App\Http\Requests\Attendance\SubmitAttendanceRequest;
use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Services\AttendanceSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The queue side of attendance: a teacher hands a day over, the office decides
 * it. A teacher only ever sees their own submissions; the office sees the school.
 */
class AttendanceSubmissionController extends Controller
{
    public function __construct(private readonly AttendanceSubmissionService $submissions) {}

    public function index(Request $request): View
    {
        $this->requireAnyPermission(['attendance.approve', 'attendance.submit']);

        $actor = $request->user();
        $reviews = (bool) $actor->can('attendance.approve');
        $status = $this->statusFilter($request->query('status'));
        $sessionId = $request->filled('session_id') ? (int) $request->query('session_id') : null;

        $submissions = AttendanceSubmission::query()
            ->with(['schoolClass', 'section', 'academicSession', 'submittedBy', 'reviewedBy'])
            ->when(! $reviews, fn ($query) => $query->where('submitted_by', $actor->id))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($sessionId !== null, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->orderByDesc('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('attendance.submissions', [
            'reviews' => $reviews,
            'status' => $status,
            'sessionId' => $sessionId,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name', 'is_current']),
            'submissions' => $submissions,
            'awaitingCount' => AttendanceSubmission::query()
                ->where('status', AttendanceSubmission::STATUS_SUBMITTED)
                ->when(! $reviews, fn ($query) => $query->where('submitted_by', $actor->id))
                ->count(),
        ]);
    }

    public function store(SubmitAttendanceRequest $request): RedirectResponse
    {
        $submission = $this->submissions->submit($request->user(), $request->validated());

        return $this->saved(
            $request,
            'attendance.students.index',
            [
                'session_id' => $submission->academic_session_id,
                'class_id' => $submission->class_id,
                'section_id' => $submission->section_id,
                'date' => $submission->attendance_date->toDateString(),
            ],
            __('Attendance for :register on :date was submitted for review.', [
                'register' => $submission->registerLabel(),
                'date' => $submission->attendance_date->format('d M Y'),
            ]),
        );
    }

    public function decide(
        ReviewAttendanceSubmissionRequest $request,
        AttendanceSubmission $submission,
        string $action,
    ): RedirectResponse {
        $note = $request->validated('note');

        $decided = $action === 'reject'
            ? $this->submissions->reject($request->user(), $submission, (string) $note)
            : $this->submissions->approve($request->user(), $submission, filled($note) ? (string) $note : null);

        return back()->with('success', __('Attendance for :register on :date was :decision.', [
            'register' => $decided->registerLabel(),
            'date' => $decided->attendance_date->format('d M Y'),
            'decision' => $action === 'reject' ? __('sent back') : __('approved'),
        ]));
    }

    private function statusFilter(mixed $value): ?string
    {
        return is_string($value) && in_array($value, AttendanceSubmission::STATUSES, true)
            ? $value
            : null;
    }
}
