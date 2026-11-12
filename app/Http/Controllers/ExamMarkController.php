<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exams\SaveExamMarksRequest;
use App\Http\Requests\Exams\SubmitExamMarksRequest;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\MarkSubmission;
use App\Models\Section;
use App\Models\User;
use App\Services\ExamService;
use App\Services\MarkSubmissionService;
use App\Services\TeachingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ExamMarkController extends Controller
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly TeachingScope $teaching,
        private readonly MarkSubmissionService $submissions,
    ) {}

    public function index(Request $request, Exam $exam): View
    {
        $this->requirePermission('marks.enter');

        $exam->load('academicSession');
        $actor = $request->user();

        $papers = $exam->papers()
            ->with(['subject', 'schoolClass'])
            ->get()
            ->sortBy(fn (ExamSubject $paper): string => sprintf(
                '%03d-%s',
                $paper->schoolClass?->level ?? 0,
                (string) ($paper->subject?->name),
            ))
            ->values();

        $sectionsOf = fn (int $classId, int $subjectId): ?Collection => $this->sectionsFor($actor, $exam, $classId, $subjectId);

        // A teacher only sees the papers of the subjects on their own timetable.
        $papers = $papers->filter(fn (ExamSubject $paper): bool => $this->teaching->allows(
            $actor,
            $this->sessionFor($exam),
            (int) $paper->class_id,
            null,
            (int) $paper->subject_id,
        ))->values();

        $classId = $request->filled('class_id') ? (int) $request->query('class_id') : (int) ($papers->first()?->class_id);
        $classPapers = $papers->where('class_id', $classId)->values();
        $paperId = $request->filled('paper_id') ? (int) $request->query('paper_id') : (int) ($classPapers->first()?->id);
        $paper = $classPapers->firstWhere('id', $paperId) ?? $classPapers->first();

        $sections = Section::query()
            ->where('class_id', $classId)
            ->orderBy('name')
            ->get();

        // A teacher only chooses among the sections they actually teach for
        // this paper; the full class list would open an empty sheet.
        $allowedSections = $paper !== null ? $sectionsOf((int) $paper->class_id, (int) $paper->subject_id) : null;

        if ($allowedSections !== null) {
            $sections = $sections->whereIn('id', $allowedSections)->values();
        }

        $section = $request->filled('section_id')
            ? $sections->firstWhere('id', (int) $request->query('section_id'))
            : null;

        // A teacher's sheet always names one of their own sections. Landing on
        // the class-wide sheet would let the same students be handed over twice,
        // once class-wide and once per section.
        if ($allowedSections !== null && $section === null) {
            $section = $sections->first();
        }

        $sheet = $paper !== null
            ? $this->exams->sheet($exam, $paper, $section?->id)
            : ['paper' => null, 'enrollments' => collect()];

        if ($paper !== null) {
            $allowed = $sectionsOf((int) $paper->class_id, (int) $paper->subject_id);

            if ($allowed !== null) {
                $sheet['enrollments'] = $sheet['enrollments']
                    ->filter(fn (array $row): bool => $allowed->contains((int) ($row['enrollment']->section_id ?? 0)))
                    ->values();
            }
        }

        return $this->pageView($request, 'exams.marks', 'results', [
            'exam' => $exam,
            'papers' => $papers,
            'classPapers' => $classPapers,
            'paper' => $paper,
            'classId' => $classId,
            'sections' => $sections,
            'sectionId' => $section?->id,
            'wholeClassAllowed' => $allowedSections === null,
            'sheet' => $sheet,
            'canSubmit' => $actor->can('marks.submit'),
            'submission' => $paper !== null
                ? MarkSubmission::forSheet((int) $paper->id, $section?->id)
                : null,
            'enteredCount' => $sheet['enrollments']->filter(
                fn (array $row): bool => $row['mark'] !== null
                    && ($row['mark']->is_absent || $row['mark']->marks_obtained !== null),
            )->count(),
        ]);
    }

    public function store(SaveExamMarksRequest $request, Exam $exam): RedirectResponse
    {
        $data = $request->validated();
        $paper = $exam->papers()->with(['subject', 'schoolClass'])->findOrFail((int) ($data['paper_id'] ?? 0));

        $this->teaching->authorize(
            $request->user(),
            $this->sessionFor($exam),
            (int) $paper->class_id,
            null,
            (int) $paper->subject_id,
        );

        $count = $this->exams->saveMarks(
            $request->user(),
            $exam,
            $paper,
            $data,
            $this->sectionsFor($request->user(), $exam, (int) $paper->class_id, (int) $paper->subject_id)?->all(),
        );

        return $this->saved(
            $request,
            'exams.marks.index',
            [
                'exam' => $exam,
                'class_id' => $paper->class_id,
                'paper_id' => $paper->id,
                'section_id' => $data['section_id'] ?? null,
            ],
            trans_choice(
                'Marks were saved for :count student.|Marks were saved for :count students.',
                $count,
                ['count' => $count],
            ),
        );
    }

    /**
     * The sheet always belongs to one exam, and that exam is resolved from the
     * route, so a hand-over can never name a paper from another exam.
     */
    public function submit(SubmitExamMarksRequest $request, Exam $exam): RedirectResponse
    {
        $data = $request->validated();
        $paper = $exam->papers()->findOrFail((int) $data['paper_id']);
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;

        $submission = $this->submissions->submit($request->user(), $exam, $paper, $sectionId);

        return $this->saved(
            $request,
            'exams.marks.index',
            [
                'exam' => $exam,
                'class_id' => $paper->class_id,
                'paper_id' => $paper->id,
                'section_id' => $sectionId,
            ],
            __('Marks for :sheet were submitted for review.', [
                'sheet' => $submission->sheetLabel(),
            ]),
        );
    }

    /**
     * The exam always belongs to one session, and that session owns the timetable
     * the paper has to appear on.
     */
    private function sessionFor(Exam $exam): AcademicSession
    {
        return $exam->academicSession ?? $exam->academicSession()->getResults()
            ?? AcademicSession::query()->where('is_current', true)->firstOrFail();
    }

    /**
     * Sections of one paper the actor is allowed to write, or null for office staff.
     */
    private function sectionsFor(User $actor, Exam $exam, int $classId, int $subjectId): ?Collection
    {
        return $this->teaching->sectionIdsForSubject($actor, $this->sessionFor($exam), $classId, $subjectId);
    }
}
