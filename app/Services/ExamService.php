<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GradeService $grades,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Exam
    {
        $session = $this->session((int) $data['academic_session_id']);
        [$start, $end] = $this->dates((string) $data['start_date'], (string) $data['end_date'], $session);

        $this->assertUniqueName($session->id, (string) $data['name']);

        $exam = Exam::query()->create([
            'academic_session_id' => $session->id,
            'name' => trim((string) $data['name']),
            'exam_type' => (string) $data['exam_type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'draft',
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            event: 'exam.created',
            auditable: $exam,
            description: __('Exam :name was created', ['name' => $exam->name]),
            newValues: [
                'academic_session_id' => $session->id,
                'name' => $exam->name,
                'exam_type' => $exam->exam_type,
                'start_date' => $exam->start_date->toDateString(),
                'end_date' => $exam->end_date->toDateString(),
            ],
            actor: $actor,
        );

        return $exam;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Exam $exam, array $data): Exam
    {
        $this->assertDraft($exam, __('Unpublish the exam before changing its details.'));

        $session = $this->session((int) $data['academic_session_id']);
        [$start, $end] = $this->dates((string) $data['start_date'], (string) $data['end_date'], $session);

        $this->assertUniqueName($session->id, (string) $data['name'], $exam->id);

        if ($exam->papers()->whereNotNull('exam_date')->where(function ($query) use ($start, $end): void {
            $query->where('exam_date', '<', $start->toDateString())
                ->orWhere('exam_date', '>', $end->toDateString());
        })->exists()) {
            throw ValidationException::withMessages([
                'start_date' => __('Paper schedules must stay inside the exam dates.'),
            ]);
        }

        $old = $exam->only(['academic_session_id', 'name', 'exam_type', 'start_date', 'end_date', 'description']);

        $exam->update([
            'academic_session_id' => $session->id,
            'name' => trim((string) $data['name']),
            'exam_type' => (string) $data['exam_type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
        ]);

        $this->audit->log(
            event: 'exam.updated',
            auditable: $exam,
            description: __('Exam :name was updated', ['name' => $exam->name]),
            oldValues: $old,
            newValues: $exam->only(['academic_session_id', 'name', 'exam_type', 'start_date', 'end_date', 'description']),
            actor: $actor,
        );

        return $exam;
    }

    public function delete(User $actor, Exam $exam): void
    {
        $this->assertDraft($exam, __('Unpublish the exam before deleting it.'));

        // The papers belong to the exam and go with it; marks, submitted sheets
        // and published results refuse the delete, because they are the record
        // of what the students actually sat.
        $this->deletes->delete($exam, [
            'label' => __('exam'),
            'description' => __('Exam #:id was permanently deleted.', ['id' => $exam->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addPaper(User $actor, Exam $exam, array $data): ExamSubject
    {
        $this->assertDraft($exam, __('Unpublish the exam before changing its papers.'));

        $class = SchoolClass::query()->findOrFail((int) $data['class_id']);
        $subject = Subject::query()->findOrFail((int) $data['subject_id']);

        if ($exam->papers()->where('class_id', $class->id)->where('subject_id', $subject->id)->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => __('This subject is already scheduled for the selected class.'),
            ]);
        }

        $fullMarks = round((float) $data['full_marks'], 2);
        $passMarks = round((float) $data['pass_marks'], 2);

        if ($fullMarks <= 0) {
            throw ValidationException::withMessages([
                'full_marks' => __('Full marks must be greater than zero.'),
            ]);
        }

        if ($passMarks < 0 || $passMarks > $fullMarks) {
            throw ValidationException::withMessages([
                'pass_marks' => __('Pass marks must be between zero and the full marks.'),
            ]);
        }

        $examDate = filled($data['exam_date'] ?? null) ? Carbon::parse((string) $data['exam_date'])->startOfDay() : null;

        if ($examDate !== null && ($examDate->lt($exam->start_date) || $examDate->gt($exam->end_date))) {
            throw ValidationException::withMessages([
                'exam_date' => __('The paper date must fall inside the exam dates.'),
            ]);
        }

        $startTime = filled($data['start_time'] ?? null) ? (string) $data['start_time'] : null;
        $endTime = filled($data['end_time'] ?? null) ? (string) $data['end_time'] : null;

        if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
            throw ValidationException::withMessages([
                'end_time' => __('The end time must be after the start time.'),
            ]);
        }

        $paper = $exam->papers()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'exam_date' => $examDate?->toDateString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'full_marks' => $fullMarks,
            'pass_marks' => $passMarks,
        ]);

        $this->audit->log(
            event: 'exam_subject.created',
            auditable: $paper,
            description: __(':subject paper was added to :exam for :class', [
                'subject' => $subject->name,
                'exam' => $exam->name,
                'class' => $class->name,
            ]),
            newValues: [
                'exam_id' => $exam->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'full_marks' => $fullMarks,
                'pass_marks' => $passMarks,
            ],
            actor: $actor,
        );

        return $paper;
    }

    public function removePaper(User $actor, Exam $exam, ExamSubject $paper): void
    {
        $this->assertDraft($exam, __('Unpublish the exam before changing its papers.'));
        abort_unless($paper->exam_id === $exam->id, 404);

        // Marks already entered for this paper block the removal; an empty paper
        // and any submitted sheet for it are what the delete takes away.
        $this->deletes->delete($paper, [
            'label' => __('exam paper'),
            'description' => __('A paper was permanently removed from exam #:id.', ['id' => $exam->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * @return Collection<int, Enrollment>
     */
    public function enrollments(Exam $exam, int $classId, ?int $sectionId = null): Collection
    {
        return Enrollment::query()
            ->with(['student', 'section'])
            ->where('academic_session_id', $exam->academic_session_id)
            ->where('class_id', $classId)
            ->when($sectionId !== null, fn ($query) => $query->where('section_id', $sectionId))
            ->where('status', 'active')
            ->orderByRaw('roll_no IS NULL')
            ->orderBy('roll_no')
            ->orderBy('student_id')
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null)
            ->values();
    }

    /**
     * @return array{paper: ExamSubject, enrollments: Collection<int, array{enrollment: Enrollment, mark: ?ExamMark}>}
     */
    public function sheet(Exam $exam, ExamSubject $paper, ?int $sectionId = null): array
    {
        abort_unless($paper->exam_id === $exam->id, 404);

        $enrollments = $this->enrollments($exam, $paper->class_id, $sectionId);

        $marks = ExamMark::query()
            ->where('exam_subject_id', $paper->id)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        return [
            'paper' => $paper,
            'enrollments' => $enrollments->map(fn (Enrollment $enrollment): array => [
                'enrollment' => $enrollment,
                'mark' => $marks->get($enrollment->student_id),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveMarks(User $actor, Exam $exam, ExamSubject $paper, array $data, ?array $sectionIds = null): int
    {
        $this->assertDraft($exam, __('Unpublish the exam before changing marks.'));
        abort_unless($paper->exam_id === $exam->id, 404);

        // A subject teacher may only write the sections they are given on the
        // timetable; anything else is silently dropped by the roster filter below.
        $enrollments = $this->enrollments($exam, $paper->class_id)
            ->when($sectionIds !== null, fn (Collection $rows): Collection => $rows->whereIn('section_id', $sectionIds))
            ->keyBy('student_id');
        $submitted = (array) ($data['marks'] ?? []);
        $reason = trim((string) ($data['correction_reason'] ?? ''));
        $fullMarks = (float) $paper->full_marks;
        $gradeItems = $this->grades->items();
        $rows = [];
        $schoolId = app(SchoolContext::class)->id();

        foreach ($submitted as $studentId => $values) {
            $studentId = (int) $studentId;

            if (! $enrollments->has($studentId)) {
                continue;
            }

            $absent = filter_var($values['is_absent'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $raw = $values['marks_obtained'] ?? null;

            if (! $absent && ($raw === null || $raw === '')) {
                continue;
            }

            $obtained = $absent ? null : round((float) $raw, 2);

            if (! $absent && ($obtained < 0 || $obtained > $fullMarks)) {
                throw ValidationException::withMessages([
                    'marks' => __('Marks for :student must be between 0 and :full.', [
                        'student' => $enrollments->get($studentId)->student?->full_name ?? __('student'),
                        'full' => number_format($fullMarks, 2),
                    ]),
                ]);
            }

            $grade = $this->grades->gradeFor(
                $absent ? 0 : $this->grades->percentage($obtained ?? 0, $fullMarks),
                $gradeItems,
            );

            $rows[] = [
                'school_id' => $schoolId,
                'exam_subject_id' => $paper->id,
                'student_id' => $studentId,
                'marks_obtained' => $absent ? null : $obtained,
                'is_absent' => $absent,
                'grade_letter' => $grade['letter'],
                'grade_point' => $grade['point'],
                'remarks' => filled($values['remarks'] ?? null) ? trim((string) $values['remarks']) : null,
                'entered_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'marks' => __('Enter marks or mark at least one student absent.'),
            ]);
        }

        // Resolved from the container rather than injected: this service owns the
        // sheet the submission snapshot reads, so a constructor dependency would
        // be a cycle.
        $submissions = app(MarkSubmissionService::class);
        $guarded = $submissions->guardingSheets((int) $paper->id, $this->writtenSections($enrollments, $rows));
        $altersRecorded = false;

        if ($guarded->isNotEmpty()) {
            $existing = ExamMark::query()
                ->where('exam_subject_id', $paper->id)
                ->whereIn('student_id', array_column($rows, 'student_id'))
                ->get()
                ->keyBy('student_id');

            $altersRecorded = false;

            foreach ($rows as $row) {
                if ($this->matchesStored($existing->get($row['student_id']), $row)) {
                    continue;
                }

                $altersRecorded = true;

                break;
            }

            // Once a sheet has been handed over it is a reviewed document, not a
            // draft: only the office may touch it without a written reason.
            if ($altersRecorded && ! $actor->can('marks.approve') && mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'correction_reason' => __('This sheet was submitted on :date. Give a reason of at least 5 characters to change it — the sheet goes back for review.', [
                        'date' => $guarded->max('submitted_at')?->format('d M Y') ?? __('an earlier date'),
                    ]),
                ]);
            }
        }

        DB::transaction(function () use ($exam, $paper, $rows, $actor, $guarded, $altersRecorded, $reason, $submissions): void {
            $locked = Exam::query()->whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked, __('Unpublish the exam before changing marks.'));

            ExamMark::query()->upsert(
                $rows,
                ['exam_subject_id', 'student_id'],
                ['marks_obtained', 'is_absent', 'grade_letter', 'grade_point', 'remarks', 'entered_by', 'updated_at'],
            );

            $this->audit->log(
                event: 'exam_marks.saved',
                auditable: $paper,
                description: __('Marks were saved for :subject (:class)', [
                    'subject' => $paper->subject?->name ?? __('subject'),
                    'class' => $paper->schoolClass?->name ?? __('class'),
                ]),
                newValues: [
                    'exam_id' => $exam->id,
                    'exam_subject_id' => $paper->id,
                    'students' => count($rows),
                    'absent' => collect($rows)->where('is_absent', true)->count(),
                ],
                actor: $actor,
            );

            if ($altersRecorded) {
                $submissions->afterManualSave($actor, $guarded, $reason, count($rows));
            }
        });

        return count($rows);
    }

    /**
     * The sections a written set of rows actually belongs to.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function writtenSections(Collection $enrollments, array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): ?int => $enrollments->get($row['student_id'])?->section_id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether a row about to be written repeats what is already stored, so an
     * unchanged re-save does not reopen a sheet that has been handed over.
     *
     * @param  array<string, mixed>  $row
     */
    private function matchesStored(?ExamMark $mark, array $row): bool
    {
        return $mark !== null
            && (bool) $mark->is_absent === (bool) $row['is_absent']
            && (float) $mark->marks_obtained === (float) $row['marks_obtained'];
    }

    /**
     * @return array{missing: int, students: Collection<int, string>}
     */
    public function missingMarks(Exam $exam, ExamSubject $paper): array
    {
        $enrollments = $this->enrollments($exam, $paper->class_id);
        $studentIds = $enrollments->pluck('student_id');

        $recorded = ExamMark::query()
            ->where('exam_subject_id', $paper->id)
            ->whereIn('student_id', $studentIds)
            ->where(function ($query): void {
                $query->whereNotNull('marks_obtained')->orWhere('is_absent', true);
            })
            ->pluck('student_id');

        $missing = $studentIds->diff($recorded)->values();

        return [
            'missing' => $missing->count(),
            'students' => $enrollments
                ->filter(fn (Enrollment $enrollment): bool => $missing->contains($enrollment->student_id))
                ->map(fn (Enrollment $enrollment): string => (string) $enrollment->student?->full_name)
                ->filter()
                ->values(),
        ];
    }

    private function session(int $sessionId): AcademicSession
    {
        return AcademicSession::query()->findOrFail($sessionId);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dates(string $start, string $end, AcademicSession $session): array
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date must be on or after the start date.'),
            ]);
        }

        if ($startDate->lt($session->start_date) || $endDate->gt($session->end_date)) {
            throw ValidationException::withMessages([
                'start_date' => __('The exam dates must fall inside the academic session.'),
            ]);
        }

        return [$startDate, $endDate];
    }

    private function assertUniqueName(int $sessionId, string $name, ?int $ignoreId = null): void
    {
        $exists = Exam::query()
            ->where('academic_session_id', $sessionId)
            ->where('name', trim($name))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('An exam with this name already exists in the selected session.'),
            ]);
        }
    }

    private function assertDraft(Exam $exam, string $message): void
    {
        if ($exam->isPublished()) {
            throw ValidationException::withMessages([
                'exam' => $message,
            ]);
        }
    }
}
