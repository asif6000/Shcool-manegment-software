<?php

namespace App\Http\Controllers;

use App\Http\Requests\Materials\SaveClassVideoRequest;
use App\Models\AcademicSession;
use App\Models\ClassVideo;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassVideoService;
use App\Services\TeachingScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassVideoController extends Controller
{
    public function __construct(
        private readonly ClassVideoService $videos,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('videos.view');

        $scope = $this->teaching->currentSession();
        $website = $request->string('website')->toString();
        $filters = $request->only(['class', 'section', 'q', 'source', 'website']);

        $records = $this->base($request, $scope)
            ->with(['schoolClass', 'section', 'subject', 'creator'])
            ->filtered(
                $request->integer('class') ?: null,
                $request->integer('section') ?: null,
                $request->string('q')->toString() ?: null,
                $request->string('source')->toString() ?: null,
                in_array($website, ['0', '1'], true) ? $website : null,
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('videos.index', [
            'videos' => $records,
            'filters' => $filters,
            'classes' => $this->teaching->classes($request->user(), $scope, SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level'])),
            'sections' => Section::query()->orderBy('class_id')->orderBy('name')->get(['id', 'class_id', 'name']),
            'onWebsite' => $this->base($request, $scope)->onWebsite()->count(),
            'youtubeCount' => $this->base($request, $scope)->where('source', ClassVideo::YOUTUBE)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('videos.manage');

        return view('videos.form', [
            'video' => new ClassVideo,
            ...$this->chooserData($request),
        ]);
    }

    public function store(SaveClassVideoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertOwnPeriod($request->user(), $data);

        $video = $this->videos->create(
            $request->user(),
            $data,
            $request->hasFile('file') ? $request->file('file') : null,
        );

        return redirect()
            ->route('videos.index', ['class' => $video->class_id])
            ->with('success', __(':title is saved. Turn on the website switch to show it to visitors.', ['title' => $video->title]));
    }

    public function edit(Request $request, ClassVideo $video): View
    {
        $this->requirePermission('videos.manage');
        $this->assertOwnRecord($request->user(), $video);

        return view('videos.form', [
            'video' => $video,
            'current_file' => $video->fileLabel(),
            ...$this->chooserData($request, $video),
        ]);
    }

    public function update(SaveClassVideoRequest $request, ClassVideo $video): RedirectResponse
    {
        $data = $request->validated();
        $this->assertOwnRecord($request->user(), $video);
        $this->assertOwnPeriod($request->user(), $data);

        $this->videos->update(
            $request->user(),
            $video,
            $data,
            $request->hasFile('file') ? $request->file('file') : null,
        );

        return redirect()
            ->route('videos.index')
            ->with('success', __(':title was updated.', ['title' => $video->title]));
    }

    public function toggleWebsite(Request $request, ClassVideo $video): RedirectResponse
    {
        $this->requirePermission('videos.manage');
        $this->assertOwnRecord($request->user(), $video);

        $video = $this->videos->toggleWebsite($request->user(), $video);

        return redirect()
            ->back()
            ->with('success', $video->show_on_website
                ? __(':title is now on the website.', ['title' => $video->title])
                : __(':title is off the website again.', ['title' => $video->title]));
    }

    public function destroy(Request $request, ClassVideo $video): RedirectResponse
    {
        $this->requirePermission('videos.manage');
        $this->assertOwnRecord($request->user(), $video);

        $this->videos->delete($request->user(), $video);

        return redirect()->route('videos.index')->with('success', __('The video was withdrawn.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function chooserData(Request $request, ?ClassVideo $video = null): array
    {
        $user = $request->user();
        $scope = $this->teaching->currentSession();
        $slots = $scope === null ? collect() : ($this->teaching->slots($user, $scope) ?? collect());

        return [
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'classes' => $this->teaching->classes($user, $scope, SchoolClass::query()->with('sections')->orderBy('level')->orderBy('name')->get()),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name'])
                ->when(! $this->teaching->isUnrestricted($user), fn ($rows): mixed => $rows->whereIn('id', $slots->pluck('subject_id'))),
            'selectedClass' => $video?->class_id ?? ($request->integer('class') ?: null),
            'selectedSession' => $video?->academic_session_id
                ?? $scope?->id
                ?? AcademicSession::query()->where('is_current', true)->value('id'),
            'selectedSection' => $video?->section_id ?? ($request->integer('section') ?: null),
            'selectedSubject' => $video?->subject_id,
            'storageKb' => (int) config('school.uploads.'.ClassVideo::UPLOAD_PROFILE.'.max_kb'),
        ];
    }

    /**
     * A video belongs to a period, so a teacher only ever reaches the classes on
     * their own timetable while the office reaches every class.
     */
    private function base(Request $request, ?AcademicSession $scope): Builder
    {
        return $this->teaching->constrainPeriods($request->user(), $scope, ClassVideo::query());
    }

    /**
     * @throws AuthorizationException
     */
    private function assertOwnRecord(User $actor, ClassVideo $video): void
    {
        if ($this->teaching->isUnrestricted($actor)) {
            return;
        }

        $session = AcademicSession::query()->find($video->academic_session_id)
            ?? $this->teaching->currentSession();

        if ($session === null) {
            throw new AuthorizationException(__('This video is outside your classes.'));
        }

        $this->teaching->authorize(
            $actor,
            $session,
            (int) $video->class_id,
            $video->section_id === null ? null : (int) $video->section_id,
            $video->subject_id === null ? null : (int) $video->subject_id,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOwnPeriod(User $actor, array $data): void
    {
        if ($this->teaching->isUnrestricted($actor)) {
            return;
        }

        $session = AcademicSession::query()->find((int) $data['academic_session_id'])
            ?? $this->teaching->currentSession();

        if ($session === null) {
            throw new AuthorizationException(__('This video is outside your classes.'));
        }

        $this->teaching->authorize(
            $actor,
            $session,
            (int) $data['class_id'],
            filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            filled($data['subject_id'] ?? null) ? (int) $data['subject_id'] : null,
        );
    }
}
