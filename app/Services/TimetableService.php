<?php

namespace App\Services;

use App\Models\ClassRoutine;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TimetableService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SchoolEventNotifier $notifier,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ClassRoutine
    {
        $this->assertMayAssign($actor);

        $attributes = $this->attributes($data);

        try {
            $routine = DB::transaction(function () use ($actor, $attributes): ClassRoutine {
                $routine = ClassRoutine::query()->create($attributes);

                $this->audit->log(
                    event: 'timetable.slot.created',
                    auditable: $routine,
                    description: __('Routine slot for :class was added', ['class' => $routine->schoolClass?->name ?? __('Class')]),
                    newValues: $attributes,
                    actor: $actor,
                );

                return $routine;
            });
        } catch (QueryException $exception) {
            throw $this->conflict($exception);
        }

        $this->announce($routine, $actor, 'created');

        return $routine;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ClassRoutine $routine, array $data): ClassRoutine
    {
        $this->assertMayAssign($actor);

        $attributes = $this->attributes($data);
        $old = $routine->only(['day_of_week', 'start_time', 'end_time', 'subject_id', 'teacher_id', 'room']);

        try {
            DB::transaction(function () use ($actor, $routine, $attributes, $old): void {
                $routine->update($attributes);

                $this->audit->log(
                    event: 'timetable.slot.updated',
                    auditable: $routine,
                    description: __('Routine slot for :class was updated', ['class' => $routine->schoolClass?->name ?? __('Class')]),
                    oldValues: $old,
                    newValues: $attributes,
                    actor: $actor,
                );
            });
        } catch (QueryException $exception) {
            throw $this->conflict($exception);
        }

        $routine->refresh();
        $this->announce($routine, $actor, 'updated');

        return $routine;
    }

    public function delete(User $actor, ClassRoutine $routine): void
    {
        $this->assertMayAssign($actor);

        $this->deletes->delete($routine, [
            'label' => __('timetable period'),
            'description' => __('A timetable period on day :day was permanently deleted.', ['day' => $routine->day_of_week]),
            'actor' => $actor,
        ]);

        $this->announce($routine, $actor, 'deleted');
    }

    /**
     * @throws AuthorizationException
     */
    private function assertMayAssign(User $actor): void
    {
        if (! $actor->hasRole('school_admin')) {
            throw new AuthorizationException(__('Only the school administrator can assign subjects to teachers.'));
        }
    }

    private function announce(ClassRoutine $routine, User $actor, string $change): void
    {
        try {
            $this->notifier->routineChanged($routine, $actor, $change);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'academic_session_id' => (int) $data['academic_session_id'],
            'class_id' => (int) $data['class_id'],
            'section_id' => (int) $data['section_id'],
            'subject_id' => (int) $data['subject_id'],
            'teacher_id' => Arr::get($data, 'teacher_id') !== null ? (int) $data['teacher_id'] : null,
            'day_of_week' => (int) $data['day_of_week'],
            'start_time' => (string) $data['start_time'],
            'end_time' => (string) $data['end_time'],
            'room' => filled($data['room'] ?? null) ? trim((string) $data['room']) : null,
        ];
    }

    /**
     * The unique slot index is the authority on clashes; translate it into a
     * field error instead of leaking the database exception.
     */
    private function conflict(QueryException $exception): ValidationException
    {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        return ValidationException::withMessages([
            'start_time' => __('That slot is already taken for this section on the same day.'),
        ]);
    }
}
