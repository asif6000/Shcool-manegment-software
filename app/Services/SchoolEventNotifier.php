<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AttendanceSubmission;
use App\Models\ClassRoutine;
use App\Models\Exam;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Homework;
use App\Models\MarkSubmission;
use App\Models\Notice;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\StudentResult;
use App\Models\StudyMaterial;
use App\Models\User;
use App\Notifications\SchoolEventNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SchoolEventNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function studentAbsent(Student $student, Carbon $date): int
    {
        $student->loadMissing(['user', 'guardians.user']);

        $sent = 0;
        $notifiedUserIds = [];
        $dateLabel = $date->format('d M Y');
        $dateKey = $date->toDateString();

        $studentUser = $student->user;

        if ($studentUser !== null && $studentUser->is_active) {
            $record = $this->notifications->send($studentUser, new SchoolEventNotification(
                title: __('Attendance update'),
                message: __('You were marked absent on :date. If this is unexpected, contact the school office.', ['date' => $dateLabel]),
                url: route('student.attendance'),
                icon: 'bi-calendar2-x',
                category: 'attendance',
                dedupeKey: 'attendance.absent:'.$student->id.':'.$dateKey.':'.$studentUser->id,
                pushTitle: __('Attendance update'),
                pushBody: __('You were marked absent on :date.', ['date' => $dateLabel]),
            ));

            if ($record !== null) {
                $sent++;
                $notifiedUserIds[] = $studentUser->id;
            }
        }

        foreach ($student->guardians as $guardian) {
            $guardianUser = $guardian->user;

            if ($guardianUser === null
                || ! $guardianUser->is_active
                || $guardianUser->school_id !== $student->school_id
                || in_array($guardianUser->id, $notifiedUserIds, true)) {
                continue;
            }

            // Guardian accounts have no child record view in this build, so the
            // message stays generic and links to the authenticated inbox.
            $record = $this->notifications->send($guardianUser, new SchoolEventNotification(
                title: __('Attendance alert'),
                message: __('A student in your care was marked absent on :date. Please contact the school office if this is unexpected.', ['date' => $dateLabel]),
                url: route('notifications.index'),
                icon: 'bi-calendar2-x',
                category: 'attendance',
                dedupeKey: 'attendance.absent.guardian:'.$student->id.':'.$dateKey.':'.$guardianUser->id,
                pushTitle: __('Attendance alert'),
                pushBody: __('A student in your care was marked absent on :date.', ['date' => $dateLabel]),
            ));

            if ($record !== null) {
                $sent++;
                $notifiedUserIds[] = $guardianUser->id;
            }
        }

        return $sent;
    }

    public function feePaymentRecorded(FeePayment $payment): int
    {
        $payment->loadMissing(['student.user', 'allocations']);

        $student = $payment->student;
        $studentUser = $student?->user;

        if ($student === null || $studentUser === null || ! $studentUser->is_active) {
            return 0;
        }

        $invoiceId = $payment->allocations->first()?->fee_invoice_id;
        $url = $invoiceId !== null
            ? route('student.fees.show', $invoiceId)
            : route('student.fees.index');

        $record = $this->notifications->send($studentUser, new SchoolEventNotification(
            title: __('Fee payment received'),
            message: __('A payment has been recorded for your fees. Open the portal to view the receipt.'),
            url: $url,
            icon: 'bi-receipt',
            category: 'fees',
            dedupeKey: 'fee.payment:'.$payment->id.':'.$studentUser->id,
            pushTitle: __('Fee payment received'),
            pushBody: __('A fee payment has been recorded. Sign in to view the receipt.'),
        ));

        return $record !== null ? 1 : 0;
    }

    /**
     * Send the published notice to its audience. The lock-screen payload stays
     * generic: the title is already website-visible, the body is not included.
     */
    public function noticePublished(Notice $notice, User $actor): int
    {
        // Internal audiences have no public page, so their link goes to the staff
        // notice list instead of a URL that would 404.
        $url = $notice->isVisibleOnWebsite()
            ? route('public.notices.show', $notice->id)
            : route('notices.index');
        $sent = 0;

        $this->audienceQuery($notice, $actor)->chunkById(100, function ($users) use (&$sent, $notice, $url): void {
            foreach ($users as $user) {
                $record = $this->notifications->send($user, new SchoolEventNotification(
                    title: __('New notice: :title', ['title' => $notice->title]),
                    message: Str::limit($notice->body, 180),
                    url: $url,
                    icon: 'bi-megaphone',
                    category: 'notices',
                    dedupeKey: 'notice.published:'.$notice->id.':'.$user->id,
                    pushTitle: __('New notice'),
                    pushBody: __('A new notice has been published. Open it to read the details.'),
                ));

                if ($record !== null) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    /**
     * Active accounts for the notice audience, excluding the publisher.
     */
    private function audienceQuery(Notice $notice, User $actor): Builder
    {
        $query = User::query()
            ->where('school_id', $notice->school_id)
            ->where('is_active', true)
            ->whereKeyNot($actor->id);

        return match ($notice->audience) {
            'students' => $query->whereHas('student'),
            'guardians' => $query->whereHas('guardian'),
            'teachers' => $query->whereHas('teacher'),
            'staff' => $query->whereHas('employee'),
            default => $query,
        };
    }

    /**
     * Tell the applicant that the school approved their application. The portal
     * link is authenticated, so the decision itself never travels in the push.
     */
    public function admissionApproved(Admission $admission): int
    {
        return $this->notifyApplicant(
            $admission,
            title: __('Application approved'),
            message: __('Your admission application :no was approved. Open it to see your student and admission numbers.', ['no' => $admission->application_no]),
            icon: 'bi-check2-circle',
            dedupePrefix: 'admission.approved',
            pushBody: __('Your admission application has been approved. Sign in to see the details.'),
        );
    }

    public function admissionRejected(Admission $admission): int
    {
        return $this->notifyApplicant(
            $admission,
            title: __('Application decision'),
            message: __('The school has reviewed your application :no. Open it to read the decision.', ['no' => $admission->application_no]),
            icon: 'bi-x-circle',
            dedupePrefix: 'admission.rejected',
            pushBody: __('Your admission application has been reviewed. Sign in to read the decision.'),
        );
    }

    private function notifyApplicant(
        Admission $admission,
        string $title,
        string $message,
        string $icon,
        string $dedupePrefix,
        string $pushBody,
    ): int {
        // Counter applications have no portal account to notify.
        $applicant = $admission->user;

        if ($applicant === null) {
            return 0;
        }

        $record = $this->notifications->send($applicant, new SchoolEventNotification(
            title: $title,
            message: $message,
            url: route('applicant.show', $admission->id),
            icon: $icon,
            category: 'admission',
            dedupeKey: $dedupePrefix.':'.$admission->id.':'.$applicant->id,
            pushTitle: $title,
            pushBody: $pushBody,
        ));

        return $record !== null ? 1 : 0;
    }

    /**
     * New homework reaches the students enrolled in that class and section.
     * The lock-screen text names no subject or title, only the fact that there
     * is new homework waiting in the portal.
     */
    public function homeworkAssigned(Homework $homework): int
    {
        $sent = 0;

        $users = User::query()
            ->where('school_id', $homework->school_id)
            ->where('is_active', true)
            ->whereHas('student.enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->where('class_id', $homework->class_id)
                ->where('academic_session_id', $homework->academic_session_id)
                ->when($homework->section_id !== null, fn ($inner) => $inner->where('section_id', $homework->section_id)));

        $users->chunkById(100, function ($chunk) use (&$sent, $homework): void {
            $type = $homework->typeLabel();

            foreach ($chunk as $user) {
                $record = $this->notifications->send($user, new SchoolEventNotification(
                    title: __('New :type', ['type' => $type]),
                    message: __('New :type has been assigned. Open the student portal to read it.', ['type' => $type]),
                    url: route('student.homework.show', $homework->id),
                    icon: 'bi-pencil-square',
                    category: 'homework',
                    dedupeKey: 'homework.assigned:'.$homework->id.':'.$user->id,
                    pushTitle: __('New :type', ['type' => $type]),
                    pushBody: __('New :type has been assigned. Sign in to read it.', ['type' => $type]),
                ));

                if ($record !== null) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    /**
     * A leave request is an administrative queue item, so only the roles that
     * decide leave are told, and the push text stays generic.
     */
    public function studentLeaveSubmitted(StudentLeave $leave): int
    {
        $student = $leave->student;

        if ($student === null) {
            return 0;
        }

        $sent = 0;

        User::query()
            ->where('school_id', $leave->school_id)
            ->where('is_active', true)
            ->role(['school_admin', 'principal'])
            ->chunkById(100, function ($users) use (&$sent, $leave): void {
                foreach ($users as $user) {
                    $record = $this->notifications->send($user, new SchoolEventNotification(
                        title: __('Leave request to review'),
                        message: __('A student has applied for leave. Open the leave list to review it.'),
                        url: route('student_leaves.index'),
                        icon: 'bi-envelope-open',
                        category: 'attendance',
                        dedupeKey: 'student_leave.submitted:'.$leave->id.':'.$user->id,
                        pushTitle: __('Leave request to review'),
                        pushBody: __('A student leave request is waiting for a decision.'),
                    ));

                    if ($record !== null) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function studentLeaveDecided(StudentLeave $leave, User $studentUser): int
    {
        $approved = $leave->status === 'approved';

        $record = $this->notifications->send($studentUser, new SchoolEventNotification(
            title: $approved ? __('Leave approved') : __('Leave decision'),
            message: $approved
                ? __('Your leave request has been approved. Open the portal to see it.')
                : __('Your leave request was not approved. Open the portal to read the school\'s note.'),
            url: route('student.leave.index'),
            icon: $approved ? 'bi-check2-circle' : 'bi-x-circle',
            category: 'attendance',
            dedupeKey: 'student_leave.decided:'.$leave->id.':'.$studentUser->id.':'.$leave->status,
            pushTitle: __('Leave decision'),
            pushBody: __('Your leave request has been reviewed. Sign in to see the outcome.'),
        ));

        return $record !== null ? 1 : 0;
    }

    /**
     * A staff leave request waits for a decision in the leave register, so only
     * the roles that decide leave are told. The applicant is skipped, and the
     * lock-screen text gives no reason: that is readable in the register.
     */
    public function staffLeaveSubmitted(StaffLeave $leave): int
    {
        return $this->notifyReviewers(
            $this->reviewers((int) $leave->school_id, $leave->teacher?->user_id ?? $leave->employee?->user_id),
            title: __('Staff leave to review'),
            message: __(':name has applied for leave: :dates', [
                'name' => $leave->staffName(),
                'dates' => $leave->start_date?->format('d M Y').' – '.$leave->end_date?->format('d M Y'),
            ]),
            url: route('staff.leaves.index'),
            icon: 'bi-calendar2-x',
            category: 'attendance',
            dedupe: 'staff_leave.submitted.'.$leave->id,
            pushBody: __('A staff leave request is waiting for a decision. Sign in to open it.'),
        );
    }

    /**
     * A submitted register is a queue item for the office, so the reviewers are
     * told and the push text names no student: the numbers are on the page
     * behind the login, not in the notification.
     */
    public function attendanceSubmitted(AttendanceSubmission $submission): int
    {
        return $this->notifyReviewers(
            $this->reviewers((int) $submission->school_id, $submission->submitted_by),
            title: __('Attendance register to review'),
            message: __(':register submitted their attendance for :date. Open the approval queue.', [
                'register' => $submission->submittedBy?->name ?? __('A teacher'),
                'date' => $submission->attendance_date->format('d M Y'),
            ]),
            url: route('attendance.submissions.index'),
            icon: 'bi-clipboard-check',
            category: 'attendance',
            dedupe: 'attendance.submission.'.$submission->id.'.submitted',
            pushBody: __('An attendance register is waiting for review. Sign in to open it.'),
        );
    }

    public function attendanceDecided(AttendanceSubmission $submission): int
    {
        $approved = $submission->status === AttendanceSubmission::STATUS_APPROVED;
        $author = $submission->submittedBy;

        if ($author === null) {
            return 0;
        }

        $record = $this->notifications->send($author, new SchoolEventNotification(
            title: $approved ? __('Attendance approved') : __('Attendance sent back'),
            message: $approved
                ? __('Your attendance for :date was approved.', ['date' => $submission->attendance_date->format('d M Y')])
                : __('Your attendance for :date needs another look. Open the submission to read the note.', [
                    'date' => $submission->attendance_date->format('d M Y'),
                ]),
            url: route('attendance.submissions.index'),
            icon: $approved ? 'bi-check2-circle' : 'bi-arrow-repeat',
            category: 'attendance',
            dedupeKey: 'attendance.submission.decided:'.$submission->id.':'.$author->id.':'.$submission->status,
            pushTitle: $approved ? __('Attendance approved') : __('Attendance sent back'),
            pushBody: __('Attendance you submitted has been reviewed. Sign in to see the outcome.'),
        ));

        return $record !== null ? 1 : 0;
    }

    /**
     * A corrected register that had already been approved goes back on the
     * queue, and the reviewer is told whether the headline numbers moved.
     */
    public function attendanceReopened(AttendanceSubmission $submission, bool $drifted): int
    {
        return $this->notifyReviewers(
            $this->reviewers((int) $submission->school_id, $submission->submitted_by),
            title: __('Approved attendance was corrected'),
            message: $drifted
                ? __(':register changed attendance for :date after it was approved, and the totals moved.', [
                    'register' => $submission->submittedBy?->name ?? __('A teacher'),
                    'date' => $submission->attendance_date->format('d M Y'),
                ])
                : __(':register corrected attendance for :date after it was approved.', [
                    'register' => $submission->submittedBy?->name ?? __('A teacher'),
                    'date' => $submission->attendance_date->format('d M Y'),
                ]),
            url: route('attendance.submissions.index'),
            icon: 'bi-clipboard-check',
            category: 'attendance',
            dedupe: 'attendance.submission.'.$submission->id.'.reopened.'.$submission->revision_count,
            pushBody: __('An attendance register is waiting for review. Sign in to open it.'),
        );
    }

    /**
     * A marks sheet that has been handed over goes to the office queue. The push
     * text names no students and no marks: it only points at the review page.
     */
    public function marksSubmitted(MarkSubmission $submission): int
    {
        return $this->notifyReviewers(
            $this->reviewers((int) $submission->school_id, $submission->submitted_by),
            title: __('Marks sheet to review'),
            message: __(':teacher submitted marks for :sheet in :exam. Open the approval queue.', [
                'teacher' => $submission->submittedBy?->name ?? __('A teacher'),
                'sheet' => $submission->sheetLabel(),
                'exam' => $submission->exam?->name ?? __('the exam'),
            ]),
            url: route('marks.submissions.index'),
            icon: 'bi-journal-check',
            category: 'exams',
            dedupe: 'marks.submission.'.$submission->id.'.submitted',
            pushBody: __('A marks sheet is waiting for review. Sign in to open it.'),
        );
    }

    public function marksDecided(MarkSubmission $submission): int
    {
        $approved = $submission->status === MarkSubmission::STATUS_APPROVED;
        $author = $submission->submittedBy;

        if ($author === null) {
            return 0;
        }

        $sheet = $submission->sheetLabel();

        $record = $this->notifications->send($author, new SchoolEventNotification(
            title: $approved ? __('Marks approved') : __('Marks sent back'),
            message: $approved
                ? __('Your marks for :sheet was approved.', ['sheet' => $sheet])
                : __('Your marks for :sheet needs another look. Open the submission to read the note.', ['sheet' => $sheet]),
            url: route('marks.submissions.index'),
            icon: $approved ? 'bi-check2-circle' : 'bi-arrow-repeat',
            category: 'exams',
            dedupeKey: 'marks.submission.decided:'.$submission->id.':'.$author->id.':'.$submission->status,
            pushTitle: $approved ? __('Marks approved') : __('Marks sent back'),
            pushBody: __('Marks you submitted have been reviewed. Sign in to see the outcome.'),
        ));

        return $record !== null ? 1 : 0;
    }

    /**
     * A corrected sheet that had already been approved goes back on the queue,
     * and the reviewer is told whether the headline counts moved.
     */
    public function marksReopened(MarkSubmission $submission, bool $drifted): int
    {
        return $this->notifyReviewers(
            $this->reviewers((int) $submission->school_id, $submission->submitted_by),
            title: __('Approved marks were corrected'),
            message: $drifted
                ? __(':teacher changed marks for :sheet after they were approved, and the totals moved.', [
                    'teacher' => $submission->submittedBy?->name ?? __('A teacher'),
                    'sheet' => $submission->sheetLabel(),
                ])
                : __(':teacher corrected marks for :sheet after they were approved.', [
                    'teacher' => $submission->submittedBy?->name ?? __('A teacher'),
                    'sheet' => $submission->sheetLabel(),
                ]),
            url: route('marks.submissions.index'),
            icon: 'bi-journal-check',
            category: 'exams',
            dedupe: 'marks.submission.'.$submission->id.'.reopened.'.$submission->revision_count,
            pushBody: __('A marks sheet is waiting for review. Sign in to open it.'),
        );
    }

    /**
     * Everyone in the school who can decide a handed-over sheet, minus the
     * person who filed it.
     *
     * @return Builder<User>
     */
    private function reviewers(int $schoolId, ?int $excludeUserId): Builder
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('id', '!=', $excludeUserId)
            ->role(['school_admin', 'principal']);
    }

    /**
     * @param  Builder<User>  $candidates
     */
    private function notifyReviewers(
        Builder $candidates,
        string $title,
        string $message,
        string $url,
        string $icon,
        string $category,
        string $dedupe,
        string $pushBody,
    ): int {
        $sent = 0;

        $candidates->chunkById(100, function ($users) use (&$sent, $title, $message, $url, $icon, $category, $dedupe, $pushBody): void {
            foreach ($users as $user) {
                $record = $this->notifications->send($user, new SchoolEventNotification(
                    title: $title,
                    message: $message,
                    url: $url,
                    icon: $icon,
                    category: $category,
                    dedupeKey: $dedupe.':'.$user->id,
                    pushTitle: $title,
                    pushBody: $pushBody,
                ));

                if ($record !== null) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    /**
     * A fee reminder is actionable but carries no amount or student identity in
     * the lock-screen copy. The stage and due date are part of the dedupe key,
     * so the daily command can run repeatedly without repeating a reminder.
     */
    public function feeReminder(FeeInvoice $invoice, ?Carbon $today = null): int
    {
        $invoice->loadMissing(['student.user', 'student.guardians.user']);

        $student = $invoice->student;

        if ($student === null) {
            return 0;
        }

        $today ??= now()->startOfDay();
        $due = $invoice->due_date?->copy()->startOfDay();
        $overdue = $due !== null && $due->lt($today);
        $stage = $overdue ? 'overdue' : 'due';
        $dueKey = $due?->toDateString() ?? 'none';
        $dateLabel = $due?->format('d M Y') ?? __('the due date');
        $sent = 0;
        $notifiedUserIds = [];

        $studentUser = $student->user;

        if ($studentUser !== null
            && $studentUser->is_active
            && $studentUser->school_id === $invoice->school_id) {
            $record = $this->notifications->send($studentUser, new SchoolEventNotification(
                title: $overdue ? __('Fee payment overdue') : __('Fee payment reminder'),
                message: $overdue
                    ? __('Invoice :invoice was due on :date and is still open. Open the fee page to review it.', [
                        'invoice' => $invoice->invoice_no,
                        'date' => $dateLabel,
                    ])
                    : __('Invoice :invoice is due on :date. Open the fee page to review it.', [
                        'invoice' => $invoice->invoice_no,
                        'date' => $dateLabel,
                    ]),
                url: route('student.fees.show', $invoice->id),
                icon: $overdue ? 'bi-exclamation-circle' : 'bi-receipt',
                category: 'fees',
                dedupeKey: 'fee.reminder.student:'.$invoice->id.':'.$stage.':'.$dueKey.':'.$studentUser->id,
                pushTitle: __('Fee payment reminder'),
                pushBody: $overdue
                    ? __('A fee invoice is past due. Sign in to review it.')
                    : __('A fee invoice is due soon. Sign in to review it.'),
            ));

            if ($record !== null) {
                $sent++;
                $notifiedUserIds[] = $studentUser->id;
            }
        }

        foreach ($student->guardians as $guardian) {
            $guardianUser = $guardian->user;

            if ($guardianUser === null
                || ! $guardianUser->is_active
                || $guardianUser->school_id !== $invoice->school_id
                || in_array($guardianUser->id, $notifiedUserIds, true)) {
                continue;
            }

            $record = $this->notifications->send($guardianUser, new SchoolEventNotification(
                title: $overdue ? __('Fee payment overdue') : __('Fee payment reminder'),
                message: $overdue
                    ? __('A fee invoice for a student in your care was due on :date and is still open. Sign in to review the school message.', ['date' => $dateLabel])
                    : __('A fee invoice for a student in your care is due on :date. Sign in to review the school message.', ['date' => $dateLabel]),
                url: route('notifications.index'),
                icon: $overdue ? 'bi-exclamation-circle' : 'bi-receipt',
                category: 'fees',
                dedupeKey: 'fee.reminder.guardian:'.$invoice->id.':'.$stage.':'.$dueKey.':'.$guardianUser->id,
                pushTitle: __('Fee payment reminder'),
                pushBody: $overdue
                    ? __('A fee invoice is past due. Sign in to review it.')
                    : __('A fee invoice is due soon. Sign in to review it.'),
            ));

            if ($record !== null) {
                $sent++;
                $notifiedUserIds[] = $guardianUser->id;
            }
        }

        return $sent;
    }

    /**
     * Routine changes reach the students enrolled in that class and section,
     * plus the assigned teacher. The push text names no subject or room.
     */
    public function routineChanged(ClassRoutine $routine, User $actor, string $change): int
    {
        $routine->loadMissing(['schoolClass', 'section', 'subject', 'teacher.user']);

        $change = in_array($change, ['created', 'updated', 'deleted'], true) ? $change : 'updated';
        $classLabel = $routine->schoolClass?->name ?? __('the class');
        $signature = substr(hash('sha256', (string) json_encode([
            $routine->day_of_week,
            $routine->start_time,
            $routine->end_time,
            $routine->subject_id,
            $routine->teacher_id,
            $routine->room,
            $routine->updated_at?->toDateTimeString(),
        ])), 0, 16);

        $sent = 0;
        $notifiedUserIds = [];
        $title = __('Class routine updated');
        $message = __('The routine for :class was :change. Open your routine to see the latest schedule.', [
            'class' => $classLabel,
            'change' => match ($change) {
                'created' => __('published'),
                'deleted' => __('removed'),
                default => __('updated'),
            },
        ]);
        $pushBody = __('The class routine has changed. Sign in to see the latest schedule.');

        User::query()
            ->where('school_id', $routine->school_id)
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->whereHas('student.enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->where('class_id', $routine->class_id)
                ->where('section_id', $routine->section_id)
                ->where('academic_session_id', $routine->academic_session_id))
            ->chunkById(100, function ($users) use (&$sent, &$notifiedUserIds, $routine, $change, $signature, $title, $message, $pushBody): void {
                foreach ($users as $user) {
                    $record = $this->notifications->send($user, new SchoolEventNotification(
                        title: $title,
                        message: $message,
                        url: route('student.routine'),
                        icon: 'bi-calendar2-week',
                        category: 'routine',
                        dedupeKey: 'routine.'.$change.':'.$routine->id.':'.$signature.':'.$user->id,
                        pushTitle: __('Class routine updated'),
                        pushBody: $pushBody,
                    ));

                    if ($record !== null) {
                        $sent++;
                        $notifiedUserIds[] = $user->id;
                    }
                }
            });

        $teacherUser = $routine->teacher?->user;

        if ($teacherUser !== null
            && $teacherUser->is_active
            && $teacherUser->school_id === $routine->school_id
            && $teacherUser->id !== $actor->id
            && ! in_array($teacherUser->id, $notifiedUserIds, true)) {
            $record = $this->notifications->send($teacherUser, new SchoolEventNotification(
                title: $title,
                message: $message,
                url: route('teacher.routine'),
                icon: 'bi-calendar2-week',
                category: 'routine',
                dedupeKey: 'routine.'.$change.':'.$routine->id.':'.$signature.':'.$teacherUser->id,
                pushTitle: __('Class routine updated'),
                pushBody: $pushBody,
            ));

            if ($record !== null) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Classwork is the daily work of a lesson, so the students in the class are
     * told once per shared item. The push names neither the subject nor title.
     */
    public function classworkShared(StudyMaterial $material): int
    {
        $material->loadMissing('subject');

        $sent = 0;
        $subject = $material->subject?->name ?? __('your class');

        User::query()
            ->where('school_id', $material->school_id)
            ->where('is_active', true)
            ->whereHas('student.enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->where('class_id', $material->class_id)
                ->where('academic_session_id', $material->academic_session_id)
                ->when($material->section_id !== null, fn ($inner) => $inner->where('section_id', $material->section_id)))
            ->chunkById(100, function ($users) use (&$sent, $material, $subject): void {
                foreach ($users as $user) {
                    $record = $this->notifications->send($user, new SchoolEventNotification(
                        title: __('New classwork'),
                        message: __('New classwork has been shared for :subject. Open Study materials to view it.', ['subject' => $subject]),
                        url: route('student.materials.index'),
                        icon: 'bi-journal-text',
                        category: 'classwork',
                        dedupeKey: 'classwork.shared:'.$material->id.':'.$user->id,
                        pushTitle: __('New classwork'),
                        pushBody: __('New classwork has been shared. Sign in to view it.'),
                    ));

                    if ($record !== null) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function resultsPublished(Exam $exam): int
    {
        $sent = 0;
        $publishedAt = $exam->published_at?->getTimestamp() ?? now()->getTimestamp();

        $results = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'published')
            ->with('student.user')
            ->get();

        foreach ($results as $result) {
            $student = $result->student;
            $studentUser = $student?->user;

            if ($student === null || $studentUser === null || ! $studentUser->is_active) {
                continue;
            }

            $record = $this->notifications->send($studentUser, new SchoolEventNotification(
                title: __('Results published'),
                message: __('Results for :exam are now available. Open the student portal to view them.', ['exam' => $exam->name]),
                url: route('student.results.show', $exam->id),
                icon: 'bi-journal-check',
                category: 'exams',
                dedupeKey: 'exam.published:'.$exam->id.':'.$student->id.':'.$publishedAt.':'.$studentUser->id,
                pushTitle: __('Results published'),
                pushBody: __('Results for :exam are now available. Sign in to view them.', ['exam' => $exam->name]),
            ));

            if ($record !== null) {
                $sent++;
            }
        }

        return $sent;
    }
}
