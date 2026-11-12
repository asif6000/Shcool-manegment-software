<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentLeaveService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(Student $student, User $actor, array $data): StudentLeave
    {
        $from = Carbon::parse($data['from_date'])->startOfDay();
        $to = Carbon::parse($data['to_date'])->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => __('The return date cannot be before the first day of leave.'),
            ]);
        }

        if ($from->gt(now()->addDays(30))) {
            throw ValidationException::withMessages([
                'from_date' => __('Leave can be requested up to one month ahead.'),
            ]);
        }

        $overlaps = StudentLeave::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('from_date', '<=', $to->toDateString())
            ->where('to_date', '>=', $from->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'from_date' => __('You already have a leave request covering those dates.'),
            ]);
        }

        $leave = DB::transaction(function () use ($student, $actor, $data, $from, $to): StudentLeave {
            $leave = StudentLeave::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $student->currentEnrollment()?->academic_session_id,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'leave_type' => (string) $data['leave_type'],
                'reason' => trim((string) $data['reason']),
                'contact_phone' => filled($data['contact_phone'] ?? null) ? trim((string) $data['contact_phone']) : null,
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                event: 'student_leave.applied',
                auditable: $leave,
                description: __('A leave request was submitted'),
                newValues: ['from_date' => $leave->from_date?->toDateString(), 'to_date' => $leave->to_date?->toDateString(), 'leave_type' => $leave->leave_type],
                actor: $actor,
            );

            return $leave;
        });

        $this->notifyOffice($leave);

        return $leave;
    }

    public function cancel(Student $student, User $actor, StudentLeave $leave): StudentLeave
    {
        if ($leave->student_id !== $student->id) {
            abort(404);
        }

        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages([
                'leave' => __('Only a pending request can be withdrawn.'),
            ]);
        }

        DB::transaction(function () use ($actor, $leave): void {
            $leave->forceFill(['status' => 'cancelled'])->save();

            $this->audit->log(
                event: 'student_leave.cancelled',
                auditable: $leave,
                description: __('A leave request was withdrawn'),
                actor: $actor,
            );
        });

        return $leave->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function decide(User $actor, StudentLeave $leave, string $decision, array $data): StudentLeave
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            abort(404);
        }

        DB::transaction(function () use ($actor, $leave, $decision, $data): void {
            $old = $leave->only(['status']);

            $leave->forceFill([
                'status' => $decision,
                'decision_notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'student_leave.'.$decision,
                auditable: $leave,
                description: __('A leave request was :decision', ['decision' => $decision]),
                oldValues: $old,
                newValues: ['status' => $leave->status],
                actor: $actor,
            );
        });

        $this->announce($leave->refresh());

        return $leave;
    }

    /**
     * Tell the student the outcome. The decision itself is only readable inside
     * the authenticated portal, so the push text stays generic.
     */
    private function announce(StudentLeave $leave): void
    {
        $user = $leave->student?->user;

        if ($user === null) {
            return;
        }

        try {
            app(SchoolEventNotifier::class)->studentLeaveDecided($leave, $user);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Ask the office to review a new request. Staff with no account are simply
     * skipped, so this is a silent no-op when nobody can be notified.
     */
    private function notifyOffice(StudentLeave $leave): void
    {
        try {
            app(SchoolEventNotifier::class)->studentLeaveSubmitted($leave);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
