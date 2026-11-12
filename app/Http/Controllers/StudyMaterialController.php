<?php

namespace App\Http\Controllers;

use App\Http\Requests\Materials\SaveStudyMaterialRequest;
use App\Models\AcademicSession;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudyMaterialService;
use App\Services\TeachingScope;
use App\Support\FileUploadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudyMaterialController extends Controller
{
    public function __construct(
        private readonly StudyMaterialService $materials,
        private readonly FileUploadService $uploads,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StudyMaterial::class);

        $scope = $this->teaching->currentSession();

        $records = $this->base($request, $scope)
            ->with(['schoolClass', 'section', 'subject', 'creator', 'teacher', 'lesson', 'academicSession'])
            ->filtered(
                $request->integer('class') ?: null,
                $request->integer('section') ?: null,
                $request->integer('subject') ?: null,
                $request->string('q')->toString() ?: null,
                $request->string('kind')->toString() ?: null,
                $request->date('from')?->toDateString(),
                $request->date('to')?->toDateString(),
                $request->string('status')->toString() ?: null,
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return $this->pageView($request, 'materials.index', 'materials', [
            'materials' => $records,
            'filters' => $request->only(['class', 'section', 'subject', 'q', 'kind', 'from', 'to', 'status']),
            'classes' => $this->teaching->classes($request->user(), $scope, SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level'])),
            'sections' => Section::query()->orderBy('class_id')->orderBy('name')->get(['id', 'class_id', 'name']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'thisWeek' => $this->base($request, $scope)->where('created_at', '>=', now()->subWeek())->count(),
            'drafts' => $this->base($request, $scope)->where('status', StudyMaterial::DRAFT)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StudyMaterial::class);

        return $this->pageView($request, 'materials.form', 'materials-form', [
            'material' => new StudyMaterial,
            ...$this->chooserData($request),
        ]);
    }

    public function store(SaveStudyMaterialRequest $request): RedirectResponse
    {
        $this->authorize('create', StudyMaterial::class);

        $data = $request->validated();
        $this->assertOwnPeriod($request->user(), $data);

        $material = $this->materials->create(
            $request->user(),
            $data,
            $request->hasFile('file') ? $request->file('file') : null,
        );

        return $this->saved(
            $request,
            'materials.index',
            ['class' => $material->class_id, 'subject' => $material->subject_id],
            $material->isPublished()
                ? __(':title is now shared with the class.', ['title' => $material->title])
                : __(':title is saved as a draft. It stays hidden until you share it.', ['title' => $material->title]),
        );
    }

    public function edit(Request $request, StudyMaterial $material): View
    {
        $this->authorize('update', $material);

        return $this->pageView($request, 'materials.form', 'materials-form', [
            'material' => $material,
            'current_file' => $material->file_name,
            ...$this->chooserData($request, $material),
        ]);
    }

    public function update(SaveStudyMaterialRequest $request, StudyMaterial $material): RedirectResponse
    {
        $data = $request->validated();
        $this->authorize('update', $material);
        $this->assertOwnPeriod($request->user(), $data);

        $material = $this->materials->update(
            $request->user(),
            $material,
            $data,
            $request->hasFile('file') ? $request->file('file') : null,
        );

        // The two save buttons on the form carry the status with them, so the
        // button that was pressed is the status the row ends up in.
        if ($request->has('publish')) {
            $material = filter_var($request->input('publish'), FILTER_VALIDATE_BOOLEAN)
                ? $this->materials->publish($request->user(), $material)
                : $this->materials->unpublish($request->user(), $material);
        }

        return $this->saved($request, 'materials.index', [], __(':title was updated.', ['title' => $material->title]));
    }

    public function publish(Request $request, StudyMaterial $material): RedirectResponse
    {
        $this->authorize('publish', $material);

        $this->materials->publish($request->user(), $material);

        return back()->with('success', __('The class can open it now.'));
    }

    public function unpublish(Request $request, StudyMaterial $material): RedirectResponse
    {
        $this->authorize('publish', $material);

        $this->materials->unpublish($request->user(), $material);

        return back()->with('success', __('It is back in your drafts. The class no longer sees it.'));
    }

    public function destroy(Request $request, StudyMaterial $material): RedirectResponse
    {
        $this->authorize('delete', $material);

        $this->materials->delete($request->user(), $material);

        return $this->saved($request, 'materials.index', [], __('The material was withdrawn.'));
    }

    public function file(Request $request, StudyMaterial $material): StreamedResponse
    {
        $this->authorize('view', $material);

        abort_if($material->file_path === null, 404);

        return $this->uploads->response($material->file_path, StudyMaterialService::UPLOAD_PROFILE, $material->downloadLabel());
    }

    /**
     * @return array<string, mixed>
     */
    private function chooserData(Request $request, ?StudyMaterial $material = null): array
    {
        $user = $request->user();
        $scope = $this->teaching->currentSession();
        $slots = $scope === null ? collect() : ($this->teaching->slots($user, $scope) ?? collect());

        return [
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'classes' => $this->teaching->classes($user, $scope, SchoolClass::query()->with('sections')->orderBy('level')->orderBy('name')->get()),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name'])
                ->when(! $this->teaching->isUnrestricted($user), fn ($rows): mixed => $rows->whereIn('id', $slots->pluck('subject_id'))),
            'selectedClass' => $material?->class_id ?? ($request->integer('class') ?: null),
            'selectedSession' => $material?->academic_session_id
                ?? $scope?->id
                ?? AcademicSession::query()->where('is_current', true)->value('id'),
            'selectedSection' => $material?->section_id ?? ($request->integer('section') ?: null),
            'selectedSubject' => $material?->subject_id ?? ($request->integer('subject') ?: null),
            // The classwork names the lesson it was set in. Only lessons of the
            // periods this actor reaches are offered, and the same class and
            // subject is re-checked on the way in.
            'lessons' => $this->lessonChoices($user, $scope, $material?->academic_session_id ?? $scope?->id),
            'mayNameTeacher' => $this->teaching->isUnrestricted($user),
            'teachers' => $this->teaching->isUnrestricted($user)
                ? Teacher::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                : collect(),
            'storageKb' => (int) config('school.uploads.'.StudyMaterialService::UPLOAD_PROFILE.'.max_kb'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Lesson>
     */
    private function lessonChoices(User $user, ?AcademicSession $scope, mixed $sessionId): \Illuminate\Database\Eloquent\Collection
    {
        if ($scope === null || $sessionId === null) {
            return Lesson::query()->whereRaw('1 = 0')->get();
        }

        return $this->teaching
            ->constrainPeriods($user, $scope, Lesson::query())
            ->where('academic_session_id', (int) $sessionId)
            ->with(['schoolClass', 'section', 'subject'])
            ->orderBy('class_id')
            ->orderBy('subject_id')
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->limit(300)
            ->get(['id', 'academic_session_id', 'class_id', 'section_id', 'subject_id', 'title', 'chapter_number', 'chapter_name', 'lesson_number']);
    }

    /**
     * The office sees every class. A teacher sees the classwork they set on their
     * own timetable; a row left without a named teacher (shared before this rule
     * existed) stays with the period so it can still be claimed.
     */
    private function base(Request $request, ?AcademicSession $scope): Builder
    {
        $actor = $request->user();
        $query = $this->teaching->constrainPeriods($actor, $scope, StudyMaterial::query());

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

        $session = AcademicSession::query()->find((int) $data['academic_session_id'])
            ?? $this->teaching->currentSession();

        if ($session === null) {
            throw new AuthorizationException(__('This material is outside your classes.'));
        }

        $this->teaching->authorize(
            $actor,
            $session,
            (int) $data['class_id'],
            filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            (int) $data['subject_id'],
        );
    }
}
