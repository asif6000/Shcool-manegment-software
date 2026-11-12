<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExamResultService
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly GradeService $grades,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, SchoolClass>
     */
    public function classes(Exam $exam): Collection
    {
        return SchoolClass::query()
            ->whereIn('id', $exam->papers()->select('class_id'))
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function tabulation(Exam $exam, ?int $classId = null): array
    {
        $classes = $this->classes($exam);
        $class = $classId !== null ? $classes->firstWhere('id', $classId) : $classes->first();

        if ($classId !== null && $class === null) {
            abort(404);
        }

        if ($class === null) {
            return [
                'classes' => $classes,
                'class' => null,
                'papers' => collect(),
                'rows' => collect(),
                'summary' => [
                    'students' => 0,
                    'complete' => 0,
                    'pending' => 0,
                    'passed' => 0,
                    'average_gpa' => null,
                    'highest_gpa' => null,
                ],
            ];
        }

        $papers = $exam->papers()
            ->with('subject')
            ->where('class_id', $class->id)
            ->get()
            ->sortBy(fn (ExamSubject $paper): string => (string) $paper->subject?->name)
            ->values();

        $enrollments = $this->exams->enrollments($exam, $class->id);

        if ($exam->isPublished()) {
            $enrollments = $enrollments->concat($this->historicalEnrollments($exam, $class->id, $enrollments));
        }

        $rows = $this->buildRows($exam, $papers, $enrollments);

        $completeRows = $rows->where('complete', true);
        $gpas = $completeRows->pluck('gpa')->filter(fn ($gpa): bool => $gpa !== null);

        return [
            'classes' => $classes,
            'class' => $class,
            'papers' => $papers,
            'rows' => $rows,
            'summary' => [
                'students' => $rows->count(),
                'complete' => $completeRows->count(),
                'pending' => $rows->count() - $completeRows->count(),
                'passed' => $completeRows->filter(fn (array $row): bool => ! $this->grades->isFailing($row['grade_letter']))->count(),
                'average_gpa' => $gpas->isNotEmpty() ? round((float) $gpas->avg(), 2) : null,
                'highest_gpa' => $gpas->isNotEmpty() ? (float) $gpas->max() : null,
            ],
        ];
    }

    public function publish(User $actor, Exam $exam): int
    {
        if ($exam->isPublished()) {
            throw ValidationException::withMessages([
                'results' => __('Results are already published for this exam.'),
            ]);
        }

        $papers = $exam->papers()->with(['subject', 'schoolClass'])->get();

        if ($papers->isEmpty()) {
            throw ValidationException::withMessages([
                'results' => __('Add at least one subject paper before publishing results.'),
            ]);
        }

        $count = DB::transaction(function () use ($actor, $exam, $papers): int {
            $locked = Exam::query()->whereKey($exam->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPublished()) {
                throw ValidationException::withMessages([
                    'results' => __('Results are already published for this exam.'),
                ]);
            }

            $problems = [];

            foreach ($papers as $paper) {
                $missing = $this->exams->missingMarks($locked, $paper);

                if ($missing['missing'] > 0) {
                    $problems[] = __(':class · :subject: :count students missing.', [
                        'class' => $paper->schoolClass?->name ?? __('Class'),
                        'subject' => $paper->subject?->name ?? __('Subject'),
                        'count' => $missing['missing'],
                    ]);
                }
            }

            if ($problems !== []) {
                throw ValidationException::withMessages([
                    'results' => __('Enter marks for every student before publishing. :problems', [
                        'problems' => implode(' ', array_slice($problems, 0, 6)),
                    ]),
                ]);
            }

            $count = 0;

            foreach ($this->classes($locked) as $class) {
                $classPapers = $papers->where('class_id', $class->id)->values();
                $enrollments = $this->exams->enrollments($locked, $class->id);
                $rows = $this->buildRows($locked, $classPapers, $enrollments);
                $positions = $this->positions($rows);
                $publishedAt = now();

                foreach ($rows as $row) {
                    if (! $row['complete']) {
                        continue;
                    }

                    $position = $positions[$row['student']->id] ?? null;
                    $snapshot = $this->snapshotFromRow($locked, $row, $position);

                    StudentResult::query()->updateOrCreate(
                        [
                            'exam_id' => $locked->id,
                            'student_id' => $row['student']->id,
                        ],
                        [
                            'total_full_marks' => $row['total_full'],
                            'total_obtained' => $row['total_obtained'],
                            'gpa' => $row['gpa'],
                            'grade_letter' => $row['grade_letter'],
                            'position' => $position,
                            'status' => 'published',
                            'snapshot' => $snapshot,
                            'published_at' => $publishedAt,
                        ],
                    );

                    $count++;
                }
            }

            if ($count === 0) {
                throw ValidationException::withMessages([
                    'results' => __('No enrolled students were found for the exam papers.'),
                ]);
            }

            $locked->forceFill([
                'status' => 'published',
                'published_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'exam.results_published',
                auditable: $locked,
                description: __('Results were published for :exam', ['exam' => $locked->name]),
                newValues: ['students' => $count, 'published_at' => now()->toDateTimeString()],
                actor: $actor,
            );

            return $count;
        });

        $exam->refresh();

        try {
            app(SchoolEventNotifier::class)->resultsPublished($exam);
        } catch (Throwable $exception) {
            // Results are published and audited; a notification failure must
            // not roll back or fail the publish response.
            report($exception);
        }

        return $count;
    }

    public function unpublish(User $actor, Exam $exam): void
    {
        if (! $exam->isPublished()) {
            throw ValidationException::withMessages([
                'results' => __('This exam is not published.'),
            ]);
        }

        DB::transaction(function () use ($actor, $exam): void {
            $locked = Exam::query()->whereKey($exam->id)->lockForUpdate()->firstOrFail();

            $locked->results()->update([
                'status' => 'draft',
                'published_at' => null,
            ]);

            $locked->forceFill([
                'status' => 'draft',
                'published_at' => null,
            ])->save();

            $this->audit->log(
                event: 'exam.results_unpublished',
                auditable: $locked,
                description: __('Results were unpublished for :exam', ['exam' => $locked->name]),
                actor: $actor,
            );
        });
    }

    /**
     * @return array{result: StudentResult, snapshot: array<string, mixed>, published: bool}
     */
    public function resultForStudent(Exam $exam, Student $student): array
    {
        abort_unless($exam->isPublished(), 404);

        $result = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('status', 'published')
            ->firstOrFail();

        return [
            'result' => $result,
            'snapshot' => $result->snapshot ?? $this->buildFallbackSnapshot($exam, $student),
            'published' => true,
        ];
    }

    /**
     * Staff preview that works before publication.
     *
     * @return array{result: ?StudentResult, snapshot: array<string, mixed>, published: bool, complete: bool}
     */
    public function staffReport(Exam $exam, Student $student): array
    {
        $enrollment = $student->enrollments()
            ->with(['schoolClass', 'section', 'academicSession'])
            ->where('academic_session_id', $exam->academic_session_id)
            ->when(! $exam->isPublished(), fn ($query) => $query->where('status', 'active'))
            ->first();

        abort_if($enrollment === null, 404, __('This student is not enrolled in the exam session.'));

        if ($exam->isPublished()) {
            $published = $this->resultForStudent($exam, $student);

            return [
                'result' => $published['result'],
                'snapshot' => $published['snapshot'],
                'published' => true,
                'complete' => true,
            ];
        }

        $papers = $exam->papers()
            ->with('subject')
            ->where('class_id', $enrollment->class_id)
            ->get()
            ->sortBy(fn (ExamSubject $paper): string => (string) $paper->subject?->name)
            ->values();

        abort_if($papers->isEmpty(), 404, __('No papers are scheduled for this class.'));

        $enrollments = $this->exams->enrollments($exam, $enrollment->class_id);
        $rows = $this->buildRows($exam, $papers, $enrollments);
        $row = $rows->firstWhere(fn (array $row): bool => $row['student']->id === $student->id);

        abort_if($row === null, 404);

        return [
            'result' => null,
            'snapshot' => $this->snapshotFromRow($exam, $row, $this->positions($rows)[$student->id] ?? null),
            'published' => false,
            'complete' => $row['complete'],
        ];
    }

    /**
     * Published results stay readable after the enrollment leaves the active
     * register, so archived students remain in the historical tabulation.
     *
     * @param  Collection<int, Enrollment>  $active
     * @return Collection<int, Enrollment>
     */
    private function historicalEnrollments(Exam $exam, int $classId, Collection $active): Collection
    {
        $studentIds = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'published')
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return Enrollment::query()
            ->with(['student', 'section'])
            ->where('academic_session_id', $exam->academic_session_id)
            ->where('class_id', $classId)
            ->whereIn('student_id', $studentIds)
            ->whereNotIn('student_id', $active->pluck('student_id'))
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null)
            ->values();
    }

    /**
     * @param  Collection<int, ExamSubject>  $papers
     * @param  Collection<int, Enrollment>  $enrollments
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(Exam $exam, Collection $papers, Collection $enrollments): Collection
    {
        $marks = ExamMark::query()
            ->whereIn('exam_subject_id', $papers->pluck('id'))
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $group): Collection => $group->keyBy('exam_subject_id'));

        $stored = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($papers, $marks, $stored, $exam): array {
            $studentMarks = $marks->get($enrollment->student_id) ?? collect();
            $cells = [];
            $totalFull = 0.0;
            $totalObtained = 0.0;
            $complete = true;
            $points = [];
            $hasFail = false;

            foreach ($papers as $paper) {
                $mark = $studentMarks->get($paper->id);
                $full = (float) $paper->full_marks;
                $absent = $mark?->is_absent ?? false;
                $recorded = $mark !== null && ($absent || $mark->marks_obtained !== null);
                $obtained = $recorded && ! $absent ? (float) $mark->marks_obtained : null;
                $point = $recorded ? (float) ($mark->grade_point ?? 0) : null;

                $totalFull += $full;

                if ($recorded) {
                    $totalObtained += $obtained ?? 0;
                    $points[] = $point;
                    $hasFail = $hasFail || ($point ?? 0) <= 0;
                } else {
                    $complete = false;
                }

                $cells[$paper->id] = [
                    'mark' => $mark,
                    'recorded' => $recorded,
                    'absent' => $absent,
                    'obtained' => $obtained,
                    'grade_letter' => $mark?->grade_letter,
                    'grade_point' => $point,
                ];
            }

            $gpa = null;
            $letter = null;

            if ($complete) {
                $gpa = $hasFail || $points === []
                    ? 0.0
                    : round(array_sum($points) / count($points), 2);
                $letter = $this->grades->overallLetter($gpa);
            }

            $result = $stored->get($enrollment->student_id);

            if ($exam->isPublished() && $result?->status === 'published') {
                $gpa = (float) $result->gpa;
                $letter = $result->grade_letter;
            }

            return [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'cells' => $cells,
                'total_full' => round($totalFull, 2),
                'total_obtained' => round($totalObtained, 2),
                'gpa' => $gpa,
                'grade_letter' => $letter,
                'complete' => $complete,
                'position' => $exam->isPublished() && $result?->status === 'published' ? $result->position : null,
                'result' => $result,
            ];
        })->values();

        if (! $exam->isPublished()) {
            $positions = $this->positions($rows);

            $rows = $rows->map(function (array $row) use ($positions): array {
                $row['position'] = $positions[$row['student']->id] ?? null;

                return $row;
            });
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function positions(Collection $rows): array
    {
        $ranked = $rows
            ->filter(fn (array $row): bool => $row['complete'] && $row['gpa'] !== null)
            ->sort(function (array $a, array $b): int {
                $gpa = (float) $b['gpa'] <=> (float) $a['gpa'];

                if ($gpa !== 0) {
                    return $gpa;
                }

                $total = (float) $b['total_obtained'] <=> (float) $a['total_obtained'];

                return $total !== 0 ? $total : $a['student']->id <=> $b['student']->id;
            })
            ->values();

        $positions = [];
        $lastKey = null;
        $lastPosition = 0;

        foreach ($ranked as $index => $row) {
            $key = number_format((float) $row['gpa'], 2, '.', '').'|'.number_format((float) $row['total_obtained'], 2, '.', '');
            $position = $lastKey !== null && $key === $lastKey ? $lastPosition : $index + 1;

            $positions[$row['student']->id] = $position;
            $lastKey = $key;
            $lastPosition = $position;
        }

        return $positions;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function snapshotFromRow(Exam $exam, array $row, ?int $position): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $row['enrollment'];
        $subjects = [];

        foreach ($row['cells'] as $paperId => $cell) {
            /** @var ExamSubject $paper */
            $paper = $exam->papers->firstWhere('id', $paperId)
                ?? ExamSubject::query()->with('subject')->find($paperId);

            if ($paper === null) {
                continue;
            }

            $subjects[] = [
                'name' => $paper->subject?->name,
                'code' => $paper->subject?->code,
                'full_marks' => (float) $paper->full_marks,
                'pass_marks' => (float) $paper->pass_marks,
                'obtained' => $cell['obtained'],
                'is_absent' => $cell['absent'],
                'grade_letter' => $cell['grade_letter'],
                'grade_point' => $cell['grade_point'],
                'remarks' => $cell['mark']?->remarks,
            ];
        }

        return [
            'student' => [
                'id' => $row['student']->id,
                'student_id' => $row['student']->student_id,
                'admission_no' => $row['student']->admission_no,
                'name' => $row['student']->full_name,
            ],
            'enrollment' => [
                'session' => $exam->academicSession?->name,
                'class' => $enrollment->schoolClass?->name,
                'section' => $enrollment->section?->name,
                'roll_no' => $enrollment->roll_no,
            ],
            'subjects' => $subjects,
            'total_full_marks' => $row['total_full'],
            'total_obtained' => $row['total_obtained'],
            'gpa' => $row['gpa'],
            'grade_letter' => $row['grade_letter'],
            'position' => $position,
            'complete' => $row['complete'],
            'outcome' => $this->grades->isFailing($row['grade_letter']) ? 'fail' : 'pass',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFallbackSnapshot(Exam $exam, Student $student): array
    {
        return [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'admission_no' => $student->admission_no,
                'name' => $student->full_name,
            ],
            'enrollment' => [],
            'subjects' => [],
            'total_full_marks' => 0,
            'total_obtained' => 0,
            'gpa' => null,
            'grade_letter' => null,
            'position' => null,
            'complete' => false,
            'outcome' => null,
        ];
    }
}
