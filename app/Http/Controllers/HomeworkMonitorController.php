<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\FileUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The office reads homework and classwork; it never writes them. Every screen
 * here is a plain read of the school's own rows behind the homework.monitor
 * permission, and there is deliberately no store, update, publish or destroy
 * route for this area - writing belongs to TeacherHomeworkController, whose
 * policy refuses an office account with a 403.
 */
class HomeworkMonitorController extends Controller
{
    public function __construct(
        private readonly FileUploadService $uploads,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('homework.monitor');

        $records = $this->base($request)
            ->with(['schoolClass', 'section', 'subject', 'teacher', 'academicSession', 'creator'])
            ->withCount('submissions')
            ->orderByDesc('assigned_date')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('homework-monitor.index', [
            'records' => $records,
            'filters' => $request->only(['type', 'class', 'section', 'subject', 'status', 'teacher', 'due', 'q']),
            'counts' => $this->counts(),
            'classes' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level']),
            'sections' => Section::query()->orderBy('class_id')->orderBy('name')->get(['id', 'class_id', 'name']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function show(Request $request, Homework $homework): View
    {
        $this->requirePermission('homework.monitor');

        $submissions = $homework->submissions()
            ->with(['student', 'reviewer'])
            ->orderBy('status')
            ->orderByDesc('submitted_at')
            ->paginate((int) config('school.pagination.per_page', 15));

        return view('homework-monitor.show', [
            'homework' => $homework->load(['schoolClass', 'section', 'subject', 'teacher', 'academicSession', 'creator']),
            'submissions' => $submissions,
            'expected' => $this->expectedCount($homework),
            'reviewed' => $homework->submissions()->where('status', 'reviewed')->count(),
            'late' => $homework->submissions()->where('status', 'late')->count(),
        ]);
    }

    public function attachment(Homework $homework): StreamedResponse
    {
        $this->requirePermission('homework.monitor');

        abort_unless($homework->attachment_path !== null, 404);

        return $this->uploads->response($homework->attachment_path, 'homework_attachment', $homework->title);
    }

    public function submissionFile(HomeworkSubmission $submission): StreamedResponse
    {
        $this->requirePermission('homework.monitor');

        abort_unless($submission->attachment_path !== null, 404);

        return $this->uploads->response(
            $submission->attachment_path,
            'homework_submission',
            $submission->attachment_name ?? 'submission',
        );
    }

    private function base(Request $request): Builder
    {
        $type = (string) $request->input('type');
        $status = (string) $request->input('status');

        return Homework::query()
            ->when(in_array($type, Homework::CATEGORIES, true), fn (Builder $query): Builder => $query->where('category', $type))
            ->when(in_array($status, Homework::STATUSES, true), fn (Builder $query): Builder => $query->where('status', $status))
            ->when($request->integer('class') ?: null, fn (Builder $query): Builder => $query->where('class_id', $request->integer('class')))
            ->when($request->integer('section') ?: null, fn (Builder $query): Builder => $query->where('section_id', $request->integer('section')))
            ->when($request->integer('subject') ?: null, fn (Builder $query): Builder => $query->where('subject_id', $request->integer('subject')))
            ->when($request->integer('teacher') ?: null, fn (Builder $query): Builder => $query->where('teacher_id', $request->integer('teacher')))
            ->when($request->input('due') === 'open', fn (Builder $query): Builder => $query->where('due_date', '>=', now()->toDateString()))
            ->when($request->input('due') === 'past', fn (Builder $query): Builder => $query->where('due_date', '<', now()->toDateString()))
            ->when(filled($request->input('q')), function (Builder $query) use ($request): Builder {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('q')).'%';

                return $query->where(fn (Builder $inner): Builder => $inner
                    ->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term));
            });
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $rows = Homework::query()
            ->selectRaw("category, count(*) as total, sum(status = 'published') as published, sum(status = 'draft') as drafts")
            ->groupBy('category')
            ->get();

        $counts = [
            'all' => 0,
            'homework' => 0,
            'classwork' => 0,
            'published' => 0,
            'drafts' => 0,
        ];

        foreach ($rows as $row) {
            $counts['all'] += (int) $row->total;
            $counts[$row->category] = (int) $row->total;
            $counts['published'] += (int) $row->published;
            $counts['drafts'] += (int) $row->drafts;
        }

        return $counts;
    }

    /**
     * Pupils on the roll for the class the work was set for.
     */
    private function expectedCount(Homework $homework): int
    {
        return Enrollment::query()
            ->where('academic_session_id', $homework->academic_session_id)
            ->where('class_id', $homework->class_id)
            ->when($homework->section_id !== null, fn (Builder $query): Builder => $query->where('section_id', $homework->section_id))
            ->where('status', 'active')
            ->count();
    }
}
