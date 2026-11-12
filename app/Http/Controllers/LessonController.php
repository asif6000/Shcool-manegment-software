<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lessons\SaveLessonRequest;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LessonService;
use App\Services\TeachingScope;
use App\Support\FileUploadService;
use App\Support\HtmlContent;
use App\Support\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The lesson desk for staff. A teacher works inside their own timetable and
 * their own drafts; the office sees the whole school. Every write re-checks
 * both, because the form only ever shows what the actor may pick.
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly LessonService $lessons,
        private readonly FileUploadService $uploads,
        private readonly TeachingScope $teaching,
        private readonly HtmlContent $html,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('lessons.view');

        $scope = $this->teaching->currentSession();

        $rows = $this->base($request, $scope)
            ->with(['schoolClass', 'section', 'subject', 'teacher', 'creator'])
            ->withCount('materials')
            ->filtered(
                $request->integer('class') ?: null,
                $request->integer('section') ?: null,
                $request->integer('subject') ?: null,
                $request->string('q')->toString() ?: null,
                $request->string('chapter')->toString() ?: null,
            )
            ->when(in_array($request->input('status'), Lesson::STATUSES, true), fn (Builder $query): Builder => $query->where('status', $request->input('status')))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        $counts = $this->counts($request, $scope);

        // A filter that lists sections the actor has never taught only produces an
        // empty table, so the choices are narrowed to the same periods the rows are.
        $taught = $scope === null || $this->teaching->isUnrestricted($request->user())
            ? null
            : ($this->teaching->slots($request->user(), $scope) ?? collect());

        return $this->pageView($request, 'lessons.index', 'lessons', [
            'lessons' => $rows,
            'counts' => $counts,
            'filters' => $request->only(['class', 'section', 'subject', 'q', 'chapter', 'status']),
            'classes' => $this->teaching->classes($request->user(), $scope, SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level'])),
            'sections' => Section::query()
                ->when($taught !== null, fn ($query): Builder => $query->whereIn('id', $taught->pluck('section_id')))
                ->orderBy('class_id')->orderBy('name')->get(['id', 'class_id', 'name']),
            'subjects' => Subject::query()
                ->when($taught !== null, fn ($query): Builder => $query->whereIn('id', $taught->pluck('subject_id')))
                ->orderBy('name')->get(['id', 'name']),
            'mayManage' => $request->user()->can('lessons.manage'),
            'mayDeleteAny' => $this->teaching->isUnrestricted($request->user()),
            'storageKb' => (int) config('school.uploads.'.LessonService::UPLOAD_PROFILE.'.max_kb'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('lessons.manage');

        return $this->pageView($request, 'lessons.form', 'lessons-form', [
            'lesson' => new Lesson(['status' => Lesson::DRAFT]),
            'materials' => collect(),
            ...$this->chooserData($request),
        ]);
    }

    public function store(SaveLessonRequest $request): RedirectResponse|JsonResponse
    {
        $this->assertOwnPeriod($request->user(), $request->validated());

        $lesson = $this->lessons->create(
            $request->user(),
            $request->validated(),
            $this->uploadedFiles($request),
        );

        return $this->written($request, $lesson, $this->statusMessage($lesson));
    }

    public function edit(Request $request, Lesson $lesson): View
    {
        $this->requirePermission('lessons.manage');
        $this->assertWorkable($request->user(), $lesson);

        return $this->pageView($request, 'lessons.form', 'lessons-form', [
            'lesson' => $lesson,
            'materials' => $lesson->materials,
            ...$this->chooserData($request, $lesson),
        ]);
    }

    public function update(SaveLessonRequest $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $this->assertWorkable($request->user(), $lesson);
        $this->assertOwnPeriod($request->user(), $request->validated());

        $saved = $this->lessons->update(
            $request->user(),
            $lesson,
            $request->validated(),
            $this->uploadedFiles($request),
            array_map('intval', (array) $request->input('remove_materials', [])),
        );

        return $this->written($request, $saved, __('The lesson was updated.'));
    }

    public function show(Request $request, Lesson $lesson): View
    {
        $this->requirePermission('lessons.view');
        $this->assertOwnRecord($request->user(), $lesson);

        return $this->pageView($request, 'lessons.show', 'lessons-detail', [
            'lesson' => $lesson->load(['schoolClass', 'section', 'subject', 'teacher', 'academicSession', 'creator', 'materials']),
            'preview' => $request->input('as') === 'student',
            'mayManage' => $request->user()->can('lessons.manage'),
            'mayEdit' => $this->mayEditFor($request->user(), $lesson),
            'links' => $this->html->linkLines($lesson->reference_links),
            'words' => $this->lessons->bodyWords($lesson),
            'history' => $this->teaching->isUnrestricted($request->user())
                ? AuditLog::query()
                    ->where('school_id', $lesson->school_id)
                    ->where('auditable_type', Lesson::class)
                    ->where('auditable_id', $lesson->id)
                    ->with('user')
                    ->orderByDesc('id')
                    ->limit(30)
                    ->get()
                : collect(),
        ]);
    }

    public function publish(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $this->requirePermission('lessons.manage');
        $this->assertWorkable($request->user(), $lesson);

        $saved = $this->lessons->publish($request->user(), $lesson);

        return $this->written($request, $saved, __('The lesson is now visible to the class.'), back: true);
    }

    public function unpublish(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $this->requirePermission('lessons.manage');
        $this->assertWorkable($request->user(), $lesson);

        $saved = $this->lessons->unpublish($request->user(), $lesson);

        return $this->written($request, $saved, __('The lesson is back in your drafts.'), back: true);
    }

    /**
     * A teacher withdraws their own draft. Once the class has seen a lesson the
     * row stays, unpublished, so nothing a pupil may have opened disappears.
     */
    public function destroy(Request $request, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $this->requirePermission('lessons.manage');
        $this->assertWorkable($request->user(), $lesson);

        if ($lesson->isPublished() && ! $this->teaching->isUnrestricted($request->user())) {
            abort(403, __('Only a draft of your own can be deleted. Unpublish it first.'));
        }

        $this->lessons->delete($request->user(), $lesson);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $lesson->id]);
        }

        return $this->saved($request, 'lessons.index', [], __('The lesson was deleted.'));
    }

    /**
     * The picture the editor inserts. It answers with the address to put in the
     * img tag; the file itself stays inside the school's uploads folder.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $this->requirePermission('lessons.manage');

        $maxKb = (int) config('school.uploads.'.LessonService::IMAGE_PROFILE.'.max_kb');

        $request->validate([
            'file' => [
                'required', 'file',
                'mimes:'.implode(',', (array) config('school.uploads.'.LessonService::IMAGE_PROFILE.'.mimes')),
                'max:'.$maxKb,
            ],
        ], [], ['file' => __('picture')]);

        $stored = $this->lessons->storeEditorImage(
            $request->file('file'),
            (int) app(SchoolContext::class)->id(),
        );

        return response()->json($stored);
    }

    public function materialFile(Request $request, Lesson $lesson, LessonMaterial $material): StreamedResponse
    {
        $this->requirePermission('lessons.view');
        $this->assertOwnRecord($request->user(), $lesson);

        abort_unless($material->lesson_id === $lesson->id, 404);

        return $this->uploads->response($material->file_path, LessonService::UPLOAD_PROFILE, $material->downloadLabel());
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function uploadedFiles(SaveLessonRequest $request): array
    {
        return array_values(array_filter((array) $request->file('materials', [])));
    }

    private function statusMessage(Lesson $lesson): string
    {
        return $lesson->isPublished()
            ? __('The lesson was saved and published for the class.')
            : __('The lesson was saved as a draft. It stays hidden until you publish it.');
    }

    /**
     * AJAX from the dashboard panel gets JSON; a plain form post keeps the
     * redirect-back-to-the-panel behaviour the other teacher modules use.
     */
    private function written(Request $request, Lesson $lesson, string $message, bool $back = false): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'lesson' => $this->present($lesson),
            ]);
        }

        if ($back) {
            return back()->with('success', $message);
        }

        return $this->saved($request, 'lessons.show', ['lesson' => $lesson], $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Lesson $lesson): array
    {
        return [
            'id' => (int) $lesson->id,
            'title' => (string) $lesson->title,
            'chapter' => $lesson->chapterLabel(),
            'status' => (string) $lesson->status,
            'class' => $lesson->schoolClass?->name,
            'section' => $lesson->section?->name,
            'subject' => $lesson->subject?->name,
            'updated_at' => $lesson->updated_at?->format('d M Y H:i'),
            'urls' => [
                'show' => route('lessons.show', $lesson),
                'edit' => route('lessons.edit', $lesson),
                'publish' => route('lessons.publish', $lesson),
                'unpublish' => route('lessons.unpublish', $lesson),
                'destroy' => route('lessons.destroy', $lesson),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function counts(Request $request, ?AcademicSession $scope): array
    {
        $byStatus = $this->base($request, $scope)
            ->selectRaw('status, count(*) aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->all();

        return [
            'total' => array_sum($byStatus),
            'published' => (int) ($byStatus[Lesson::PUBLISHED] ?? 0),
            'draft' => (int) ($byStatus[Lesson::DRAFT] ?? 0),
            'recent' => $this->base($request, $scope)->where('updated_at', '>=', now()->subWeek())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chooserData(Request $request, ?Lesson $lesson = null): array
    {
        $user = $request->user();
        $scope = $this->teaching->currentSession();
        $unrestricted = $this->teaching->isUnrestricted($user);

        $sessionRows = AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name', 'status', 'is_current']);
        $classRows = $this->teaching->classes(
            $user,
            $scope,
            SchoolClass::query()->with('sections')->orderBy('level')->orderBy('name')->get(),
        );
        $subjectRows = Subject::query()->orderBy('name')->get(['id', 'name'])
            ->when(! $unrestricted, fn ($rows): mixed => $rows->whereIn(
                'id',
                ($scope === null ? collect() : ($this->teaching->slots($user, $scope) ?? collect()))->pluck('subject_id'),
            ));

        $periods = $this->periodMap($user, $sessionRows, $classRows, $subjectRows, $unrestricted);

        // A teacher gets only the sessions their timetable actually covers, so a
        // session can never be chosen that would leave the other three empty.
        if (! $unrestricted) {
            $sessionRows = $sessionRows->filter(fn (AcademicSession $row): bool => ($periods[(int) $row->id] ?? []) !== []);
        }

        // 0 has to be read as "nothing chosen", because integer() returns it for
        // an absent parameter and would otherwise hide every period row.
        $selectedSession = $lesson?->academic_session_id
            ?: $request->integer('session')
            ?: ($scope?->id ?: $sessionRows->first()?->id);

        return [
            'sessions' => $sessionRows,
            'classes' => $classRows,
            'subjects' => $subjectRows,
            'periods' => $periods,
            'teachers' => Teacher::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'mayNameTeacher' => $unrestricted,
            'selectedClass' => $lesson?->class_id ?? ($request->integer('class') ?: null),
            'selectedSection' => $lesson?->section_id,
            'selectedSession' => $selectedSession,
            'storageKb' => (int) config('school.uploads.'.LessonService::UPLOAD_PROFILE.'.max_kb'),
            'imageStorageKb' => (int) config('school.uploads.'.LessonService::IMAGE_PROFILE.'.max_kb'),
            'imageUrl' => route('lessons.images.store'),
        ];
    }

    /**
     * Every class · section · subject the actor may file a lesson under, per
     * session. A teacher's map is their timetable; the office may name any
     * combination, so its map is the whole cross product. The form builds all
     * three choices out of this and the page hands the same map to the browser,
     * so nothing on screen can be a choice the server would refuse.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function periodMap(User $user, $sessions, $classes, $subjects, bool $unrestricted): array
    {
        $map = [];

        foreach ($sessions as $session) {
            $rows = [];

            if ($unrestricted) {
                foreach ($classes as $class) {
                    $sections = $class->sections->isNotEmpty()
                        ? $class->sections
                        : collect([[0, '—']]);

                    foreach ($sections as $section) {
                        foreach ($subjects as $subject) {
                            $rows[] = [
                                'class' => (int) $class->id,
                                'class_label' => $this->classLabel($class),
                                'section' => (int) (is_array($section) ? $section[0] : $section->id),
                                'section_label' => is_array($section) ? $section[1] : (string) $section->name,
                                'subject' => (int) $subject->id,
                                'subject_label' => (string) $subject->name,
                            ];
                        }
                    }
                }
            } else {
                $lookup = $classes->keyBy('id');
                $subjectNames = $subjects->keyBy('id');

                foreach (($this->teaching->slots($user, $session) ?? collect()) as $slot) {
                    $class = $lookup->get($slot['class_id']);
                    $section = $class?->sections->firstWhere('id', $slot['section_id']);

                    // A subject outside the chooser's list (a subject retired since
                    // the routine was written) would render as an unselectable id.
                    if ($class === null || $subjectNames->has($slot['subject_id']) === false) {
                        continue;
                    }

                    $rows[] = [
                        'class' => $slot['class_id'],
                        'class_label' => $this->classLabel($class),
                        'section' => $slot['section_id'],
                        'section_label' => (string) ($section?->name ?? '#'.$slot['section_id']),
                        'subject' => $slot['subject_id'],
                        'subject_label' => (string) $subjectNames->get($slot['subject_id'])->name,
                    ];
                }
            }

            $map[(int) $session->id] = $rows;
        }

        return $map;
    }

    private function classLabel(SchoolClass $class): string
    {
        return $class->name;
    }

    /**
     * The rows this actor may reach: the office sees every class, a teacher sees
     * the periods on their own timetable.
     */
    private function base(Request $request, ?AcademicSession $scope): Builder
    {
        return $this->teaching->constrainPeriods($request->user(), $scope, Lesson::query());
    }

    /**
     * Reading a lesson needs the period, nothing more.
     *
     * @throws AuthorizationException
     */
    private function assertOwnRecord(User $actor, Lesson $lesson): void
    {
        if ($this->teaching->isUnrestricted($actor)) {
            return;
        }

        $this->authorizePeriod($actor, $lesson->academic_session_id, $lesson->class_id, $lesson->section_id, $lesson->subject_id);
    }

    /**
     * Changing a lesson needs the period and the author's hand on it: two
     * teachers may both teach Six A Maths, but neither writes the other's lesson.
     */
    private function assertWorkable(User $actor, Lesson $lesson): void
    {
        $this->assertOwnRecord($actor, $lesson);

        if ($this->teaching->isUnrestricted($actor)) {
            return;
        }

        $teacherId = $this->teaching->teacherId($actor);

        $mine = (int) $lesson->created_by === (int) $actor->id
            || ($teacherId !== null && $lesson->teacher_id !== null && (int) $lesson->teacher_id === $teacherId);

        if (! $mine) {
            throw new AuthorizationException(__('Only the teacher who wrote this lesson can change it.'));
        }
    }

    /**
     * Whether the buttons on the screen are worth showing at all. The server
     * still checks the same rule on the write; this only decides what to draw.
     */
    private function mayEditFor(User $actor, Lesson $lesson): bool
    {
        if (! $actor->can('lessons.manage')) {
            return false;
        }

        try {
            $this->assertWorkable($actor, $lesson);
        } catch (AuthorizationException) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function assertOwnPeriod(User $actor, array $data): void
    {
        if ($this->teaching->isUnrestricted($actor)) {
            return;
        }

        $session = AcademicSession::query()->find((int) ($data['academic_session_id'] ?? 0));

        if ($session === null) {
            throw new AuthorizationException(__('This lesson is outside your classes.'));
        }

        $classId = (int) $data['class_id'];
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        $subjectId = (int) $data['subject_id'];

        if ($this->teaching->allows($actor, $session, $classId, $sectionId, $subjectId)) {
            return;
        }

        // The three choices are drawn from this same timetable, so arriving here
        // means the post was assembled outside the form. Say which part is not
        // one of the periods instead of a dead-end page nobody can act on.
        $slots = $this->teaching->slots($actor, $session) ?? collect();
        $holdsClass = $slots->contains(fn (array $slot): bool => $slot['class_id'] === $classId);

        $field = 'class_id';
        $message = __('You do not teach that class in :session.', ['session' => $session->name]);

        if ($holdsClass) {
            $field = 'subject_id';
            $message = __('You do not teach that subject for that class in :session.', ['session' => $session->name]);

            if ($sectionId !== null && ! $slots->contains(
                fn (array $slot): bool => $slot['class_id'] === $classId && $slot['section_id'] === $sectionId
            )) {
                $field = 'section_id';
                $message = __('You do not teach that section in :session.', ['session' => $session->name]);
            }
        }

        throw ValidationException::withMessages([$field => $message]);
    }

    private function authorizePeriod(
        User $actor,
        mixed $sessionId,
        int $classId,
        mixed $sectionId,
        int $subjectId,
    ): void {
        $session = AcademicSession::query()->find((int) $sessionId) ?? $this->teaching->currentSession();

        if ($session === null) {
            throw new AuthorizationException(__('This lesson is outside your classes.'));
        }

        $this->teaching->authorize(
            $actor,
            $session,
            $classId,
            $sectionId === null ? null : (int) $sectionId,
            $subjectId,
        );
    }
}
