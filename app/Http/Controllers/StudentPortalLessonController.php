<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPortalStudent;
use App\Models\AcademicSession;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Services\LessonService;
use App\Support\FileUploadService;
use App\Support\HtmlContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lessons as the class sees them: published only, their own class and section,
 * read-only. Nothing here takes an id from the URL as the source of scope — the
 * row is always re-resolved through the signed-in student's enrollment.
 */
class StudentPortalLessonController extends Controller
{
    use ResolvesPortalStudent;

    public function __construct(
        private readonly FileUploadService $uploads,
        private readonly HtmlContent $html,
    ) {}

    public function index(Request $request): View
    {
        $enrollment = $this->student($request)->currentEnrollment();
        $sessionId = $this->sessionId();

        $search = trim((string) $request->input('q'));
        $subjectId = $request->integer('subject') ?: null;
        $chapter = trim((string) $request->input('chapter'));
        $chapter = $chapter === '' ? null : mb_substr($chapter, 0, 20);

        $rows = Lesson::query()
            ->forEnrollment($enrollment, $sessionId)
            ->with(['subject', 'schoolClass', 'section', 'teacher'])
            ->withCount('materials')
            ->filtered(null, null, $subjectId, $search ?: null, $chapter)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        // Only the subjects and chapters this class actually has lessons for.
        $subjectIds = $this->base($enrollment, $sessionId)->distinct()->pluck('subject_id');
        $chapters = $this->base($enrollment, $sessionId)
            ->whereNotNull('chapter_number')
            ->distinct()
            ->orderBy('chapter_number')
            ->pluck('chapter_number');

        return view('student.lessons.index', [
            'student' => $this->student($request),
            'rows' => $rows,
            'subjects' => Subject::query()->whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name']),
            'chapters' => $chapters,
            'filters' => ['q' => $search, 'subject' => $subjectId, 'chapter' => $chapter],
        ]);
    }

    public function show(Request $request, Lesson $lesson): View
    {
        $enrollment = $this->student($request)->currentEnrollment();

        $visible = Lesson::query()
            ->forEnrollment($enrollment, $this->sessionId())
            ->with(['schoolClass', 'section', 'subject', 'teacher', 'academicSession', 'materials'])
            ->whereKey($lesson->id)
            ->firstOrFail();

        return view('student.lessons.show', [
            'lesson' => $visible,
            'links' => $this->html->linkLines($visible->reference_links),
            'related' => Lesson::query()
                ->forEnrollment($enrollment, $this->sessionId())
                ->where('subject_id', $visible->subject_id)
                ->whereKeyNot($visible->id)
                ->orderByDesc('published_at')
                ->limit(6)
                ->get(),
        ]);
    }

    /**
     * The download re-resolves the lesson through the enrollment and then insists
     * the file belongs to it, so a guessed pair of ids reaches nothing.
     */
    public function materialFile(Request $request, Lesson $lesson, LessonMaterial $material): StreamedResponse
    {
        $enrollment = $this->student($request)->currentEnrollment();

        $visible = Lesson::query()
            ->forEnrollment($enrollment, $this->sessionId())
            ->whereKey($lesson->id)
            ->firstOrFail();

        abort_unless($material->lesson_id === $visible->id, 404);

        return $this->uploads->response($material->file_path, LessonService::UPLOAD_PROFILE, $material->downloadLabel());
    }

    private function base(?Enrollment $enrollment, ?int $sessionId): Builder
    {
        return Lesson::query()->forEnrollment($enrollment, $sessionId);
    }

    private function sessionId(): ?int
    {
        return AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->value('id');
    }
}
