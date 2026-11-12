<?php

namespace App\Http\Controllers;

use App\Http\Requests\Homework\ReviewSubmissionRequest;
use App\Http\Requests\Homework\SaveHomeworkRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\HomeworkService;
use App\Services\TeachingScope;
use App\Support\FileUploadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherHomeworkController extends Controller
{
    private const UPLOAD_PROFILE = 'homework_attachment';

    public function __construct(
        private readonly HomeworkService $homework,
        private readonly FileUploadService $uploads,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Homework::class);

        $scope = $this->teaching->currentSession();

        $records = $this->base($request, $scope)
            ->with(['schoolClass', 'section', 'subject', 'teacher', 'academicSession', 'creator'])
            ->withCount('submissions')
            ->filtered(
                $request->integer('class') ?: null,
                $request->integer('section') ?: null,
                $request->integer('subject') ?: null,
            )
            ->when($request->input('due') === 'open', fn ($query): mixed => $query->where('due_date', '>=', now()->toDateString()))
            ->when($request->input('due') === 'past', fn ($query): mixed => $query->where('due_date', '<', now()->toDateString()))
            ->when(in_array($request->input('status'), Homework::STATUSES, true), fn ($query): mixed => $query->where('status', $request->input('status')))
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return $this->pageView($request, 'homework.index', 'homework', [
            'homeworkRecords' => $records,
            'filters' => $request->only(['class', 'section', 'subject', 'due', 'status']),
            'classes' => $this->teaching->classes($request->user(), $scope, SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level'])),
            'sections' => Section::query()->orderBy('class_id')->orderBy('name')->get(['id', 'class_id', 'name']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name'])
                ->when(! $this->teaching->isUnrestricted($request->user()),
                    fn ($rows): mixed => $rows->whereIn('id', $scope === null ? [] : ($this->teaching->slots($request->user(), $scope) ?? collect())->pluck('subject_id'))),
            'dueCount' => $this->base($request, $scope)
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Homework::class);

        return $this->pageView($request, 'homework.form', 'homework-form', [
            'homework' => new Homework([
                'assigned_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
            ]),
            'attachments' => [],
            ...$this->chooserData($request),
        ]);
    }

    public function store(SaveHomeworkRequest $request): RedirectResponse
    {
        $this->authorize('create', Homework::class);

        $this->assertOwnPeriod($request->user(), $request->validated());

        $homework = $this->homework->create(
            $request->user(),
            $request->validated(),
            $request->hasFile('attachment') ? $request->file('attachment') : null,
        );

        return $this->saved(
            $request,
            'teacher.homework.show',
            ['homework' => $homework],
            $homework->status === Homework::PUBLISHED
                ? __('Homework was assigned and the class was notified.')
                : __('The homework was saved as a draft. It stays hidden until you publish it.'),
        );
    }

    public function publish(Request $request, Homework $homework): RedirectResponse
    {
        $this->authorize('publish', $homework);

        $this->homework->publish($request->user(), $homework);

        return back()->with('success', __('The homework is now visible to the class.'));
    }

    public function unpublish(Request $request, Homework $homework): RedirectResponse
    {
        $this->authorize('publish', $homework);

        $this->homework->unpublish($request->user(), $homework);

        return back()->with('success', __('The homework is back in your drafts. Students no longer see it.'));
    }

    public function cancel(Request $request, Homework $homework): RedirectResponse
    {
        $this->authorize('publish', $homework);

        $this->homework->cancel($request->user(), $homework);

        return back()->with('success', __('The homework was cancelled. Submissions already made are kept.'));
    }

    public function edit(Request $request, Homework $homework): View
    {
        $this->authorize('update', $homework);

        return $this->pageView($request, 'homework.form', 'homework-form', [
            'homework' => $homework,
            'attachments' => $this->attachmentSummary($homework),
            ...$this->chooserData($request, $homework),
        ]);
    }

    public function update(SaveHomeworkRequest $request, Homework $homework): RedirectResponse
    {
        $this->authorize('update', $homework);
        $this->assertOwnPeriod($request->user(), $request->validated());

        $updated = $this->homework->update(
            $request->user(),
            $homework,
            $request->validated(),
            $request->hasFile('attachment') ? $request->file('attachment') : null,
        );

        return $this->saved(
            $request,
            'teacher.homework.show',
            ['homework' => $updated],
            __('The homework was updated.'),
        );
    }

    public function destroy(Request $request, Homework $homework): RedirectResponse
    {
        $this->authorize('delete', $homework);

        $this->homework->delete($request->user(), $homework);

        return $this->saved($request, 'teacher.homework.index', [], __('The homework was deleted.'));
    }

    public function show(Request $request, Homework $homework): View
    {
        $this->authorize('view', $homework);

        $submissions = $homework->submissions()
            ->with(['student', 'reviewer'])
            ->orderBy('status')
            ->orderByDesc('submitted_at')
            ->paginate((int) config('school.pagination.per_page', 15));

        $expected = $this->expectedCount($homework);

        return $this->pageView($request, 'homework.show', 'homework-detail', [
            'homework' => $homework->load(['schoolClass', 'section', 'subject', 'teacher', 'academicSession', 'creator']),
            'submissions' => $submissions,
            'expected' => $expected,
            'notSubmitted' => max(0, $expected - $submissions->total()),
            'reviewed' => $homework->submissions()->where('status', 'reviewed')->count(),
            'attachments' => $this->attachmentSummary($homework),
            'late' => $homework->submissions()->where('status', 'late')->count(),
        ]);
    }

    public function review(ReviewSubmissionRequest $request, HomeworkSubmission $submission): RedirectResponse
    {
        $this->authorize('review', $submission->homework);

        $this->homework->review($request->user(), $submission, $request->validated());

        return back()->with('success', __('The submission was marked as reviewed.'));
    }

    public function attachment(Request $request, Homework $homework): StreamedResponse
    {
        $this->authorize('view', $homework);

        abort_unless($homework->attachment_path !== null, 404);

        return $this->serve($homework->attachment_path, self::UPLOAD_PROFILE, $homework->title);
    }

    public function submissionFile(Request $request, HomeworkSubmission $submission): StreamedResponse
    {
        $this->authorize('view', $submission->homework);

        abort_unless($submission->attachment_path !== null, 404);

        return $this->serve($submission->attachment_path, 'homework_submission', $submission->attachment_name ?? 'submission');
    }

    /**
     * Students on the roll for the target class and section, in the same session.
     */
    private function expectedCount(Homework $homework): int
    {
        return Enrollment::query()
            ->where('academic_session_id', $homework->academic_session_id)
            ->where('class_id', $homework->class_id)
            ->when($homework->section_id !== null, fn ($query): mixed => $query->where('section_id', $homework->section_id))
            ->where('status', 'active')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function chooserData(Request $request, ?Homework $homework = null): array
    {
        $selectedClass = $homework?->class_id ?? $request->integer('class') ?: null;
        $user = $request->user();
        $scope = $this->teaching->currentSession();
        $slots = $scope === null ? collect() : ($this->teaching->slots($user, $scope) ?? collect());

        return [
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'classes' => $this->teaching->classes($user, $scope, SchoolClass::query()->with('sections')->orderBy('level')->orderBy('name')->get()),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name'])
                ->when(! $this->teaching->isUnrestricted($user), fn ($rows): mixed => $rows->whereIn('id', $slots->pluck('subject_id'))),
            'teachers' => Teacher::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            // A teacher's name is their own and is not a choice on the form;
            // only the office may file work under another teacher.
            'mayNameTeacher' => $this->teaching->isUnrestricted($user),
            'selectedClass' => $selectedClass,
            'selectedSession' => $homework?->academic_session_id
                ?? AcademicSession::query()->where('is_current', true)->value('id'),
            'selectedSection' => $homework?->section_id,
        ];
    }

    /**
     * The office sees every class. A teacher sees the homework they set on their
     * own timetable; a row left without a named teacher (filed before this rule
     * existed) stays with the period so it can still be claimed.
     */
    private function base(Request $request, ?AcademicSession $scope): Builder
    {
        $actor = $request->user();
        $query = $this->teaching->constrainPeriods($actor, $scope, Homework::query());

        if ($this->teaching->isUnrestricted($actor)) {
            return $query;
        }

        $teacherId = $this->teaching->teacherId($actor);

        return $query->where(function (Builder $own) use ($actor, $teacherId): void {
            $own->where('created_by', $actor->id)->orWhereNull('teacher_id');

            if ($teacherId !== null) {
                $own->orWhere('teacher_id', $teacherId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOwnPeriod(User $actor, array $data): void
    {
        if ($this->teaching->isUnrestricted($actor)) {
            return;
        }

        $session = AcademicSession::query()->find((int) $data['academic_session_id']);
        $reference = $session ?? $this->teaching->currentSession();

        if ($reference === null) {
            throw new AuthorizationException(__('This homework is outside your classes.'));
        }

        $this->teaching->authorize(
            $actor,
            $reference,
            (int) $data['class_id'],
            filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            (int) $data['subject_id'],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachmentSummary(Homework $homework): array
    {
        if ($homework->attachment_path === null) {
            return [];
        }

        $path = $homework->attachment_path;

        return [[
            'name' => basename($path),
            'size' => Storage::disk('school_uploads')->exists($path) ? Storage::disk('school_uploads')->size($path) : null,
            'route' => route('teacher.homework.attachment', $homework),
        ]];
    }

    private function serve(string $path, string $profile, ?string $label): StreamedResponse
    {
        return $this->uploads->response($path, $profile, $label);
    }
}
