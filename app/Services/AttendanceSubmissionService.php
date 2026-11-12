<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The sign-off half of student attendance. A teacher marks the register (see
 * StudentAttendanceService) and then submits it; the office approves or sends it
 * back. Submitting freezes a count snapshot, and any later edit reopens the
 * submission so a reviewer always sees the version they decided on.
 */
class AttendanceSubmissionService
{
    public function __construct(
        private readonly StudentAttendanceService $attendance,
        private readonly AttendanceScope $scope,
        private readonly AuditLogger $audit,
        private readonly SchoolEventNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $actor, array $data): AttendanceSubmission
    {
        $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $section = filled($data['section_id'] ?? null)
            ? Section::query()->where('class_id', $class->id)->findOrFail($data['section_id'])
            : null;
        $date = Carbon::parse($data['attendance_date'])->startOfDay();

        $this->scope->authorize($actor, $session, (int) $class->id, $section !== null ? (int) $section->id : null);

        $counts = $this->snapshot($actor, $session, $class, $section, $date);

        $holiday = $this->attendance->holidayOn($date);

        if ($holiday !== null && ! (bool) ($data['override_holiday'] ?? false)) {
            throw ValidationException::withMessages([
                'attendance_date' => __(':date is a holiday (:name). Confirm the override to submit attendance.', [
                    'date' => $date->format('d M Y'),
                    'name' => $holiday->name,
                ]),
            ]);
        }

        if ($counts['unmarked'] > 0) {
            throw ValidationException::withMessages([
                'attendance_date' => trans_choice(
                    ':count student on this register is still unmarked. Mark everyone before submitting.|:count students on this register are still unmarked. Mark everyone before submitting.',
                    $counts['unmarked'],
                    ['count' => $counts['unmarked']],
                ),
            ]);
        }

        $key = [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section?->id,
            'attendance_date' => $date->toDateString(),
        ];

        // The school scope stays on: without it a parallel register in another
        // school would be treated as this one's existing submission.
        $submission = AttendanceSubmission::query()->firstOrNew($key);
        $was = $submission->exists ? $submission->status : null;

        // Approval is the office's decision. It is only reopened by a recorded
        // correction, never by a plain resubmit that shows no reason.
        if ($was === AttendanceSubmission::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'attendance_date' => __('This register was already approved. Change a marked student with a correction reason to send it back for another decision.'),
            ]);
        }

        $submission->fill($counts['columns'] + [
            'status' => AttendanceSubmission::STATUS_SUBMITTED,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'revision_count' => $submission->exists ? (int) $submission->revision_count + 1 : 0,
        ])->save();

        $this->audit->log(
            event: 'attendance.submission.submitted',
            auditable: $submission,
            description: __('Attendance for :register on :date was submitted by :teacher', [
                'register' => $this->registerLabel($class, $section),
                'date' => $date->format('d M Y'),
                'teacher' => $actor->name,
            ]),
            oldValues: $was === null ? [] : ['status' => $was],
            newValues: ['status' => AttendanceSubmission::STATUS_SUBMITTED] + $counts['columns'],
            actor: $actor,
            schoolId: (int) $submission->school_id,
        );

        $this->notifier->attendanceSubmitted($submission);

        return $submission;
    }

    public function approve(User $actor, AttendanceSubmission $submission, ?string $note): AttendanceSubmission
    {
        return $this->decide($actor, $submission, AttendanceSubmission::STATUS_APPROVED, $note);
    }

    public function reject(User $actor, AttendanceSubmission $submission, string $note): AttendanceSubmission
    {
        return $this->decide($actor, $submission, AttendanceSubmission::STATUS_REJECTED, $note);
    }

    /**
     * Called by the register after a saved edit. Someone who cannot approve has
     * no business editing a submitted day silently, so their change goes back on
     * the queue; a reviewer's own fix keeps the decision but the numbers are
     * refreshed and the drift is recorded.
     */
    public function afterManualEdit(
        User $actor,
        AttendanceSubmission $submission,
        string $reason,
        int $changedCount,
    ): AttendanceSubmission {
        $session = $submission->academicSession;
        $class = $submission->schoolClass;
        $section = $submission->section;

        if ($session === null || $class === null) {
            return $submission;
        }

        $counts = $this->snapshot(
            $actor,
            $session,
            $class,
            $section,
            Carbon::parse($submission->attendance_date)->startOfDay(),
        );

        $was = $submission->status;
        $drifted = $this->drifted($submission, $counts['columns']);
        $requeue = ! $actor->can('attendance.approve');

        $submission->fill($counts['columns'] + [
            'revision_count' => (int) $submission->revision_count + 1,
            'last_correction_reason' => $reason !== '' ? $reason : $submission->last_correction_reason,
            // Sending a day back to the queue has to clear the old decision, so
            // these stay null on purpose rather than being filtered away.
            'status' => $requeue ? AttendanceSubmission::STATUS_SUBMITTED : $was,
            'reviewed_by' => $requeue ? null : $submission->reviewed_by,
            'reviewed_at' => $requeue ? null : $submission->reviewed_at,
            'review_note' => $requeue ? null : $submission->review_note,
        ])->save();

        $this->audit->log(
            event: 'attendance.submission.corrected',
            auditable: $submission,
            description: __('Attendance for :register on :date was edited after submission: :reason', [
                'register' => $this->registerLabel($class, $section),
                'date' => $submission->attendance_date->format('d M Y'),
                'reason' => $reason !== '' ? $reason : __('no reason required for the office'),
            ]),
            oldValues: ['status' => $was],
            newValues: [
                'status' => $submission->status,
                'rows_changed' => $changedCount,
                'totals_moved' => $drifted,
            ] + $counts['columns'],
            actor: $actor,
            schoolId: (int) $submission->school_id,
        );

        if ($was === AttendanceSubmission::STATUS_APPROVED) {
            $this->notifier->attendanceReopened($submission, $drifted);
        }

        return $submission;
    }

    /**
     * The submission that stands between a teacher and a silent edit. A day that
     * was sent back is already the teacher's to fix, so it guards nothing.
     */
    public function openSubmissionFor(
        int $sessionId,
        int $classId,
        ?int $sectionId,
        string $date,
    ): ?AttendanceSubmission {
        $submission = AttendanceSubmission::forRegister($sessionId, $classId, $sectionId, $date);

        return $submission !== null && $submission->status !== AttendanceSubmission::STATUS_REJECTED
            ? $submission
            : null;
    }

    /**
     * @return array{columns: array<string, int>, unmarked: int}
     */
    private function snapshot(
        User $actor,
        AcademicSession $session,
        SchoolClass $class,
        ?Section $section,
        Carbon $date,
    ): array {
        /** @var Collection<int, array{enrollment: Enrollment, attendance: ?StudentAttendance}> $sheet */
        $sheet = $this->attendance->sheet($actor, $session, $class, $section, $date);

        $columns = [
            'student_count' => $sheet->count(),
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'leave_count' => 0,
            'excused_count' => 0,
            'unmarked_count' => 0,
        ];

        foreach ($sheet as $row) {
            $status = $row['attendance']?->status;
            $key = $status === null ? 'unmarked_count' : ($status.'_count');

            if (! array_key_exists($key, $columns)) {
                $key = 'unmarked_count';
            }

            $columns[$key]++;
        }

        return ['columns' => $columns, 'unmarked' => $columns['unmarked_count']];
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function drifted(AttendanceSubmission $submission, array $columns): bool
    {
        foreach ($columns as $key => $value) {
            if ($key !== 'student_count' && (int) $submission->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }

    private function registerLabel(SchoolClass $class, ?Section $section): string
    {
        return $class->name.($section !== null ? ' '.$section->name : ' '.__('(all sections)'));
    }

    private function decide(
        User $actor,
        AttendanceSubmission $submission,
        string $status,
        ?string $note,
    ): AttendanceSubmission {
        if (! $submission->isAwaitingReview()) {
            throw ValidationException::withMessages([
                'submission' => __('Only submissions awaiting review can be decided.'),
            ]);
        }

        $session = $submission->academicSession;
        $class = $submission->schoolClass;

        if ($session !== null && $class !== null) {
            $this->scope->authorize(
                $actor,
                $session,
                (int) $class->id,
                $submission->section_id !== null ? (int) $submission->section_id : null,
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
            event: 'attendance.submission.'.$status,
            auditable: $submission,
            description: __('Attendance for :register on :date was :decision', [
                'register' => $this->registerLabel($class, $submission->section),
                'date' => $submission->attendance_date->format('d M Y'),
                'decision' => $status === AttendanceSubmission::STATUS_APPROVED
                    ? __('approved')
                    : __('sent back'),
            ]),
            oldValues: ['status' => $was],
            newValues: ['status' => $status, 'review_note' => $note],
            actor: $actor,
            schoolId: (int) $submission->school_id,
        );

        $this->notifier->attendanceDecided($submission);

        return $submission;
    }
}
