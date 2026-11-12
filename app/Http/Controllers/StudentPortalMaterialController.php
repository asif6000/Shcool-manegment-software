<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPortalStudent;
use App\Models\AcademicSession;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Services\StudyMaterialService;
use App\Support\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentPortalMaterialController extends Controller
{
    use ResolvesPortalStudent;

    public function __construct(
        private readonly FileUploadService $uploads,
    ) {}

    public function index(Request $request): View
    {
        $student = $this->student($request);
        $enrollment = $student->currentEnrollment();
        $session = $this->session();

        $kind = $request->input('kind') === StudyMaterial::CLASSWORK ? StudyMaterial::CLASSWORK : StudyMaterial::MATERIAL;
        $search = trim((string) $request->input('q'));
        $subjectId = $request->integer('subject') ?: null;

        $rows = StudyMaterial::query()
            ->forEnrollment($enrollment, $session?->id)
            ->with(['subject', 'schoolClass', 'section', 'creator', 'teacher', 'lesson'])
            ->where('kind', $kind)
            ->when($subjectId !== null, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%')
                    ->orWhere('description', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%')
            ))
            ->orderByDesc('work_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        // Only subjects the class actually has materials for.
        $subjects = Subject::query()
            ->whereIn('id', StudyMaterial::query()
                ->forEnrollment($enrollment, $session?->id)
                ->where('kind', $kind)
                ->distinct()
                ->pluck('subject_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('student.materials.index', [
            'student' => $student,
            'session' => $session,
            'rows' => $rows,
            'kind' => $kind,
            'subjects' => $subjects,
            'filters' => ['q' => $search, 'subject' => $subjectId],
        ]);
    }

    /**
     * The file is streamed only after the row is re-resolved through the
     * student's own class/section, so a guessed id cannot leak another class's
     * material.
     */
    public function file(Request $request, StudyMaterial $material): StreamedResponse
    {
        $student = $this->student($request);
        $session = $this->session();

        $resolvable = StudyMaterial::query()
            ->forEnrollment($student->currentEnrollment(), $session?->id)
            ->whereKey($material->id)
            ->firstOrFail();

        abort_if($resolvable->file_path === null, 404);

        return $this->uploads->response(
            $resolvable->file_path,
            StudyMaterialService::UPLOAD_PROFILE,
            $resolvable->downloadLabel(),
        );
    }

    private function session(): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();
    }
}
