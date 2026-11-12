<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\StaffLeave;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaveService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createType(User $actor, array $data): LeaveType
    {
        $type = LeaveType::query()->create(Arr::only($data, [
            'name', 'code', 'max_days_per_year', 'is_paid', 'is_active',
        ]));

        $this->audit->log(
            event: 'leave_type.created',
            auditable: $type,
            description: __('Leave type :name was created', ['name' => $type->name]),
            newValues: $type->only(['name', 'code', 'max_days_per_year', 'is_paid']),
            actor: $actor,
            schoolId: (int) $type->school_id,
        );

        return $type;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateType(User $actor, LeaveType $type, array $data): LeaveType
    {
        $old = $type->only(['name', 'code', 'max_days_per_year', 'is_paid', 'is_active']);
        $type->update(Arr::only($data, ['name', 'code', 'max_days_per_year', 'is_paid', 'is_active']));

        $this->audit->log(
            event: 'leave_type.updated',
            auditable: $type,
            description: __('Leave type :name was updated', ['name' => $type->name]),
            oldValues: $old,
            newValues: $type->only(['name', 'code', 'max_days_per_year', 'is_paid', 'is_active']),
            actor: $actor,
            schoolId: (int) $type->school_id,
        );

        return $type->refresh();
    }

    public function deleteType(User $actor, LeaveType $type): void
    {
        // Leave requests keep their type, so a type already used by a request
        // cannot be erased; an unused one leaves no trace behind.
        $this->deletes->delete($type, [
            'label' => __('leave type'),
            'description' => __('Leave type #:id was permanently deleted.', ['id' => $type->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(User $actor, array $data): StaffLeave
    {
        $hasTeacher = filled($data['teacher_id'] ?? null);
        $hasEmployee = filled($data['employee_id'] ?? null);

        if ($hasTeacher === $hasEmployee) {
            throw ValidationException::withMessages([
                'teacher_id' => __('Choose exactly one teacher or staff member.'),
            ]);
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        $overlap = StaffLeave::query()
            ->where('status', '!=', 'rejected')
            ->when($hasTeacher, fn ($query) => $query->where('teacher_id', $data['teacher_id']))
            ->when($hasEmployee, fn ($query) => $query->where('employee_id', $data['employee_id']))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => __('This staff member already has a pending or approved leave in that period.'),
            ]);
        }

        $leave = StaffLeave::query()->create([
            'leave_type_id' => $data['leave_type_id'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $start->diffInDays($end) + 1,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        $this->audit->log(
            event: 'staff_leave.requested',
            auditable: $leave,
            description: __('Leave was requested for :name', ['name' => $leave->staffName()]),
            newValues: [
                'start_date' => $leave->start_date->toDateString(),
                'end_date' => $leave->end_date->toDateString(),
                'total_days' => $leave->total_days,
            ],
            actor: $actor,
            schoolId: (int) $leave->school_id,
        );

        return $leave;
    }

    /**
     * A teacher asking for their own leave. The staff profile is read from the
     * signed-in account and the posted applicant ids are dropped, so the form
     * cannot be pointed at a colleague.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyFor(User $actor, array $data): StaffLeave
    {
        $teacherId = Teacher::query()->where('user_id', $actor->id)->value('id');

        if ($teacherId === null) {
            throw ValidationException::withMessages([
                'reason' => __('This login is not linked to a staff profile, so the office has to record leave for you.'),
            ]);
        }

        $leave = $this->request($actor, array_merge(
            Arr::except($data, ['teacher_id', 'employee_id']),
            ['teacher_id' => $teacherId, 'employee_id' => null],
        ));

        $this->notifyOffice($leave);

        return $leave;
    }

    /**
     * Ask the office to decide a new request. A school with no staff accounts
     * simply gets no notifications, so this never breaks the apply itself.
     */
    private function notifyOffice(StaffLeave $leave): void
    {
        try {
            app(SchoolEventNotifier::class)->staffLeaveSubmitted($leave);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function decide(User $actor, StaffLeave $leave, string $decision, ?string $reason = null): StaffLeave
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => __('Unknown decision.')]);
        }

        return DB::transaction(function () use ($actor, $leave, $decision, $reason): StaffLeave {
            /** @var StaffLeave $leave */
            $leave = StaffLeave::query()->lockForUpdate()->findOrFail($leave->id);

            if ($leave->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => __('This leave request has already been decided.'),
                ]);
            }

            $leave->forceFill([
                'status' => $decision,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => $decision === 'rejected' ? $reason : null,
            ])->save();

            $this->audit->log(
                event: 'staff_leave.'.$decision,
                auditable: $leave,
                description: $decision === 'approved'
                    ? __('Leave for :name was approved', ['name' => $leave->staffName()])
                    : __('Leave for :name was rejected', ['name' => $leave->staffName()]),
                newValues: ['status' => $decision, 'reason' => $reason],
                actor: $actor,
                schoolId: (int) $leave->school_id,
            );

            return $leave;
        });
    }
}
