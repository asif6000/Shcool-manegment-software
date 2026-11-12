<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\MarkSubmission;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The sign-off half of marks entry. A teacher writes the sheet (see
 * ExamService::saveMarks) and hands it over; the office approves or sends it
 * back. Handing over freezes the counts, and any later edit reopens the sheet so
 * a reviewer always decides on the version they were shown.
 */
class MarkSubmissionService
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly TeachingScope $teaching,
        private readonly AuditLogger $audit,
        private readonly SchoolEventNotifier $notifier,
    ) {}

    public function submit(User $actor, Exam $exam, ExamSubject $paper, ?int $sectionId): MarkSubmission
    {
        $session = $exam->academicSession ?? throw ValidationException::withMessages([
            'paper' => __('The exam this paper belongs to is missing its session.'),
        ]);

        $this->teaching->authorize(
            $actor,
            $session,
            (int) $paper->class_id,
            $sectionId,
            (int) $paper->subject_id,
        );

        // A restricted teacher always hands over one of their sections. A
        // class-wide sheet would overlap every per-section sheet they also own,
        // so the same students could be reviewed twice.
        if ($sectionId === null
            && $this->teaching->sectionIdsForSubject($actor, $session, (int) $paper->class_id, (int) $paper->subject_id) !== null) {
            throw ValidationException::withMessages([
                'section_id' => __('Choose one of your sections to submit. A class-wide sheet is for the school office.'),
            ]);
        }

        if ($exam->isPublished()) {
            throw ValidationException::withMessages([
                'paper' => __('A published exam no longer takes a fresh submission. Unpublish it first.'),
            ]);
        }

        $columns = $this->snapshot($actor, $exam, $paper, $sectionId);

        if ($columns['unmarked_count'] > 0) {
            throw ValidationException::withMessages([
                'paper' => trans_choice(
                    ':count student on this sheet still has no mark. Enter a mark or set absent before submitting.|:count students on this sheet still have no mark. Enter a mark or set absent before submitting.',
                    $columns['unmarked_count'],
                    ['count' => $columns['unmarked_count']],
                ),
            ]);
        }

        // The school scope stays on: without it a parallel sheet in another
        // school would be treated as this one's existing submission.
        $submission = MarkSubmission::query()->firstOrNew([
            'exam_subject_id' => $paper->id,
            'section_id' => $sectionId,
        ]);
        $was = $submission->exists ? $submission->status : null;

        $submission->fill($columns + [
            'exam_id' => $exam->id,
            'status' => MarkSubmission::STATUS_SUBMITTED,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'revision_count' => $submission->exists ? (int) $submission->revision_count + 1 : 0,
        ])->save();

        $this->audit->log(
            event: 'marks.submission.submitted',
            auditable: $submission,
            description: __('Marks for :sheet in :exam were submitted by :teacher', [
                'sheet' => $submission->sheetLabel(),
                'exam' => $exam->name,
                'teacher' => $actor->name,
            ]),
            oldValues: $was === null ? [] : ['status' => $was],
            newValues: ['status' => MarkSubmission::STATUS_SUBMITTED] + $columns,
            actor: $actor,
            schoolId: (int) $submission->school_id,
        );

        $this->notifier->marksSubmitted($submission);

        return $submission;
    }

    public function approve(User $actor, MarkSubmission $submission, ?string $note): MarkSubmission
    {
        return $this->decide($actor, $submission, MarkSubmission::STATUS_APPROVED, $note);
    }

    public function reject(User $actor, MarkSubmission $submission, string $note): MarkSubmission
    {
        return $this->decide($actor, $submission, MarkSubmission::STATUS_REJECTED, $note);
    }

    /**
     * Called by the marks sheet after a saved edit. Someone who cannot approve
     * has no business editing a handed-over sheet silently, so their change goes
     * back on the queue; a reviewer's own fix keeps the decision but the counts
     * are refreshed and any drift is recorded.
     *
     * @param  Collection<int, MarkSubmission>  $submissions
     */
    public function afterManualSave(User $actor, Collection $submissions, string $reason, int $changedCount): void
    {
        foreach ($submissions as $submission) {
            $this->afterManualEdit($actor, $submission, $reason, $changedCount);
        }
    }

    public function afterManualEdit(
        User $actor,
        MarkSubmission $submission,
        string $reason,
        int $changedCount,
    ): MarkSubmission {
        $paper = $submission->paper;
        $exam = $submission->exam;

        if ($paper === null || $exam === null) {
            return $submission;
        }

        $columns = $this->snapshot(
            $actor,
            $exam,
            $paper,
            $submission->section_id !== null ? (int) $submission->section_id : null,
        );

        $was = $submission->status;
        $drifted = $this->drifted($submission, $columns);
        $requeue = ! $actor->can('marks.approve');

        $submission->fill($columns + [
            'revision_count' => (int) $submission->revision_count + 1,
            'last_correction_reason' => $reason !== '' ? $reason : $submission->last_correction_reason,
            // Sending a sheet back to the queue has to clear the old decision,
            // so these stay null on purpose rather than being filtered away.
            'status' => $requeue ? MarkSubmission::STATUS_SUBMITTED : $was,
            'reviewed_by' => $requeue ? null : $submission->reviewed_by,
            'reviewed_at' => $requeue ? null : $submission->reviewed_at,
            'review_note' => $requeue ? null : $submission->review_note,
        ])->save();

        $this->audit->log(
            event: 'marks.submission.corrected',
            auditable: $submission,
            description: __('Marks for :sheet in :exam were edited after submission: :reason', [
                'sheet' => $submission->sheetLabel(),
                'exam' => $exam->name,
                'reason' => $reason !== '' ? $reason : __('no reason required for the office'),
            ]),
            oldValues: ['status' => $was],
            newValues: [
                'status' => $submission->status,
                'rows_written' => $changedCount,
                'totals_moved' => $drifted,
            ] + $columns,
            actor: $actor,
            schoolId: (int) $submission->school_id,
        );

        if ($was === MarkSubmission::STATUS_APPROVED) {
            $this->notifier->marksReopened($submission, $drifted);
        }

        return $submission;
    }

    /**
     * The handed-over sheets a write to this paper touches, minus any that were
     * sent back: a rejected sheet is already the teacher's to fix, so it guards
     * nothing and only rejoins the queue on an explicit resubmission.
     *
     * @param  list<int>  $touchedSectionIds
     * @return Collection<int, MarkSubmission>
     */
    public function guardingSheets(int $paperId, array $touchedSectionIds): Collection
    {
        return MarkSubmission::affectedByWrite($paperId, $touchedSectionIds)
            ->reject(fn (MarkSubmission $submission): bool => $submission->status === MarkSubmission::STATUS_REJECTED)
            ->values();
    }

    /**
     * @return array{enrolled_count: int, entered_count: int, absent_count: int, unmarked_count: int}
     */
    private function snapshot(User $actor, Exam $exam, ExamSubject $paper, ?int $sectionId): array
    {
        $rows = $this->roster($actor, $exam, $paper, $sectionId);

        $marks = ExamMark::query()
            ->where('exam_subject_id', $paper->id)
            ->whereIn('student_id', $rows->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $columns = [
            'enrolled_count' => 0,
            'entered_count' => 0,
            'absent_count' => 0,
            'unmarked_count' => 0,
        ];

        foreach ($rows as $enrollment) {
            $columns['enrolled_count']++;
            $mark = $marks->get($enrollment->student_id);

            if ($mark === null || (! $mark->is_absent && $mark->marks_obtained === null)) {
                $columns['unmarked_count']++;

                continue;
            }

            $columns['entered_count']++;

            if ($mark->is_absent) {
                $columns['absent_count']++;
            }
        }

        return $columns;
    }

    /**
     * The students whose marks this sheet covers: the paper's class roster,
     * narrowed to the section on screen and, for a teacher, to the sections of
     * that subject they are actually given on the timetable.
     *
     * @return Collection<int, Enrollment>
     */
    private function roster(User $actor, Exam $exam, ExamSubject $paper, ?int $sectionId): Collection
    {
        $rows = $this->exams->enrollments($exam, (int) $paper->class_id, $sectionId);
        $session = $exam->academicSession;

        if ($session === null) {
            return $rows;
        }

        $allowed = $this->teaching->sectionIdsForSubject(
            $actor,
            $session,
            (int) $paper->class_id,
            (int) $paper->subject_id,
        );

        return $allowed === null
            ? $rows
            : $rows->whereIn('section_id', $allowed)->values();
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function drifted(MarkSubmission $submission, array $columns): bool
    {
        foreach ($columns as $key => $value) {
            if ($key !== 'enrolled_count' && (int) $submission->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }

    private function decide(
        User $actor,
        MarkSubmission $submission,
        string $status,
        ?string $note,
    ): MarkSubmission {
        if (! $submission->isAwaitingReview()) {
            throw ValidationException::withMessages([
                'submission' => __('Only submissions awaiting review can be decided.'),
            ]);
        }

        $paper = $submission->paper;
        $session = $submission->exam?->academicSession;

        if ($paper !== null && $session !== null) {
            $this->teaching->authorize(
                $actor,
                $session,
                (int) $paper->class_id,
                $submission->section_id !== null ? (int) $submission->section_id : null,
                (int) $paper->subject_id,
            );
        }

        $was = $submission->status;

        $submission->fill([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        $this->audit->log(
            event: 'marks.submission.'.$status,
            auditable: $submission,
            description: __('Marks for :sheet in :exam were :decision', [
                'sheet' => $submission->sheetLabel(),
                'exam' => $submission->exam?->name ?? __('the exam'),
                'decision' => $status === MarkSubmission::STATUS_APPROVED ? __('approved') : __('sent back'),
            ]),
            oldValues: ['status' => $was],
            newValues: ['status' => $status, 'review_note' => $note],
            actor: $actor,
            schoolId: (int) $submission->school_id,
        );

        $this->notifier->marksDecided($submission);

        return $submission;
    }
}
