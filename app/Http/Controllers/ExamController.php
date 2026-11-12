<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exams\SaveExamRequest;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\ExamService;
use App\Services\GradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly GradeService $grades,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('exams.view');
        $this->grades->ensureDefaultScale();

        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $sessionId = $request->has('session_id')
            ? (int) $request->query('session_id')
            : (int) ($sessions->firstWhere('is_current', true)?->id ?? $sessions->first()?->id);
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search'));

        $query = Exam::query()
            ->with(['academicSession'])
            ->withCount('papers');

        $base = clone $query;

        $exams = $query
            ->when($sessionId > 0, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->when(in_array($status, Exam::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->search($search))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('exams.index', [
            'sessions' => $sessions,
            'exams' => $exams,
            'filters' => [
                'session_id' => (string) ($sessionId > 0 ? $sessionId : ''),
                'status' => $status,
                'search' => $search,
            ],
            'summary' => [
                'total' => (clone $base)->count(),
                'published' => (clone $base)->where('status', 'published')->count(),
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'papers' => DB::table('exam_subjects')
                    ->whereIn('exam_id', (clone $base)->select('id'))
                    ->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->requirePermission('exams.manage');
        $this->grades->ensureDefaultScale();

        return view('exams.create', [
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'currentSession' => AcademicSession::query()->where('is_current', true)->first(),
        ]);
    }

    public function store(SaveExamRequest $request): RedirectResponse
    {
        $exam = $this->exams->create($request->user(), $request->validated());

        return redirect()
            ->route('exams.show', $exam)
            ->with('success', __('The exam was created. Add subject papers next.'));
    }

    public function show(Exam $exam): View
    {
        $this->requirePermission('exams.view');

        $exam->load(['academicSession', 'creator']);

        $papers = $exam->papers()
            ->with(['subject', 'schoolClass'])
            ->get()
            ->sortBy(fn ($paper): string => sprintf(
                '%03d-%s',
                $paper->schoolClass?->level ?? 0,
                (string) ($paper->subject?->name),
            ))
            ->values();

        $entered = ExamMark::query()
            ->whereIn('exam_subject_id', $papers->pluck('id'))
            ->where(function ($query): void {
                $query->whereNotNull('marks_obtained')->orWhere('is_absent', true);
            })
            ->selectRaw('exam_subject_id, COUNT(*) as total')
            ->groupBy('exam_subject_id')
            ->pluck('total', 'exam_subject_id');

        $classes = SchoolClass::query()->orderBy('level')->orderBy('name')->get();
        $subjects = Subject::query()->orderBy('name')->get();

        $studentsByClass = $classes->mapWithKeys(fn (SchoolClass $class): array => [
            $class->id => $this->exams->enrollments($exam, $class->id)->count(),
        ]);

        return view('exams.show', [
            'exam' => $exam,
            'papers' => $papers,
            'entered' => $entered,
            'classes' => $classes,
            'subjects' => $subjects,
            'studentsByClass' => $studentsByClass,
            'classCount' => $papers->pluck('class_id')->unique()->count(),
            'resultsCount' => $exam->results()->where('status', 'published')->count(),
        ]);
    }

    public function edit(Exam $exam): View
    {
        $this->requirePermission('exams.manage');

        return view('exams.edit', [
            'exam' => $exam,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
        ]);
    }

    public function update(SaveExamRequest $request, Exam $exam): RedirectResponse
    {
        $this->exams->update($request->user(), $exam, $request->validated());

        return redirect()
            ->route('exams.show', $exam)
            ->with('success', __('The exam was updated.'));
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $this->requirePermission('exams.manage');

        $this->exams->delete(request()->user(), $exam);

        return redirect()
            ->route('exams.index')
            ->with('success', __('The exam was deleted.'));
    }
}
