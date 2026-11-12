<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\NumberSequence;
use App\Support\SchoolContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public const TYPES = [
        'teachers' => Teacher::class,
        'employees' => Employee::class,
    ];

    /**
     * Accounts that run the school keep their login whatever happens to a staff
     * row, so a delete here can never take an admin's access away.
     *
     * @var list<string>
     */
    private const MANAGEMENT_ROLES = ['school_admin', 'principal', 'accountant'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
        private readonly NumberSequence $numbers,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $type, array $filters): LengthAwarePaginator
    {
        $model = $this->model($type);

        $query = $model::query();

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $query) use ($like): void {
                $query->where('employee_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('designation', 'like', $like);
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $type, array $data, ?UploadedFile $photo, User $actor): Model
    {
        $model = $this->model($type);
        $schoolId = $this->schoolId();
        $photoPath = $photo !== null
            ? $this->uploads->store($photo, 'teacher_photo', (string) $schoolId)
            : null;

        try {
            return DB::transaction(function () use ($type, $data, $photoPath, $actor, $model, $schoolId): Model {
                School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

                $attributes = $this->attributes($data);
                $attributes['photo_path'] = $photoPath;

                if (blank($attributes['employee_code'] ?? null)) {
                    $attributes['employee_code'] = $this->numbers->next(
                        $model,
                        'employee_code',
                        $type === 'teachers' ? 'TCH' : 'EMP',
                        (int) now()->year,
                        $schoolId,
                    );
                }

                $staff = $model::query()->create($attributes);

                if ($type === 'teachers' && filled($data['password'] ?? null)) {
                    $this->createTeacherLogin($staff, $data, $actor, $schoolId);
                }

                $newValues = [
                    'employee_code' => $staff->employee_code,
                    'first_name' => $staff->first_name,
                    'last_name' => $staff->last_name,
                    'designation' => $staff->designation,
                    'status' => $staff->status,
                ];

                if ($type === 'teachers') {
                    $newValues['classes'] = $this->syncClasses($staff, $data, $schoolId);
                }

                $this->audit->log(
                    event: 'staff.created',
                    auditable: $staff,
                    description: __(':type :name was added', [
                        'type' => $this->typeLabel($type),
                        'name' => $staff->full_name,
                    ]),
                    newValues: $newValues,
                    actor: $actor,
                    schoolId: $schoolId,
                );

                return $staff;
            });
        } catch (\Throwable $exception) {
            if ($photoPath !== null) {
                $this->uploads->delete($photoPath, 'teacher_photo');
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $type, Model $staff, array $data, ?UploadedFile $photo, User $actor): Model
    {
        $oldPhoto = $staff->photo_path;
        $newPhoto = $photo !== null
            ? $this->uploads->store($photo, 'teacher_photo', (string) $staff->school_id)
            : null;

        try {
            DB::transaction(function () use ($type, $staff, $data, $newPhoto, $actor): void {
                $old = $staff->only(['first_name', 'last_name', 'designation', 'phone', 'status']);
                $isTeacher = $type === 'teachers' && $staff instanceof Teacher;

                if ($isTeacher) {
                    $old['classes'] = $staff->classes->pluck('name')->implode(', ');
                }

                $staff->fill($this->attributes($data));

                if ($newPhoto !== null) {
                    $staff->photo_path = $newPhoto;
                }

                $staff->save();

                $newValues = $staff->only(['first_name', 'last_name', 'designation', 'phone', 'status']);

                if ($isTeacher) {
                    $newValues['classes'] = $this->syncClasses($staff, $data, (int) $staff->school_id);
                }

                $this->audit->log(
                    event: 'staff.updated',
                    auditable: $staff,
                    description: __(':type :name was updated', [
                        'type' => $this->typeLabel($type),
                        'name' => $staff->full_name,
                    ]),
                    oldValues: $old,
                    newValues: $newValues,
                    actor: $actor,
                    schoolId: (int) $staff->school_id,
                );
            });
        } catch (\Throwable $exception) {
            if ($newPhoto !== null) {
                $this->uploads->delete($newPhoto, 'teacher_photo');
            }

            throw $exception;
        }

        if ($newPhoto !== null) {
            $this->uploads->delete($oldPhoto, 'teacher_photo');
        }

        return $staff->refresh();
    }

    public function delete(string $type, Model $staff, User $actor): void
    {
        $account = $staff->user_id !== null ? User::query()->find($staff->user_id) : null;
        $removableAccount = $account !== null && ! $account->is($actor) && $this->isRemovableLogin($account, $staff);
        $label = $this->typeLabel($type);

        // Attendance, leave and salary payments stay: they are the record of work
        // this person did. The timetable periods and the pay configuration that
        // existed only for them go with the row, and a person with any history
        // on record cannot be erased at all.
        $this->deletes->delete($staff, [
            'label' => $label,
            'description' => __(':type #:id was permanently deleted.', ['type' => $label, 'id' => $staff->getKey()]),
            'actor' => $actor,
            'extra' => ['login_removed' => $removableAccount],
        ]);

        if ($account === null) {
            return;
        }

        if ($removableAccount) {
            // Nobody else signs in with this account and the profile it served is
            // gone, so it cannot be left able to reach the staff workspace.
            $this->deletes->delete($account, [
                'label' => __('login'),
                'description' => __('Login #:id was permanently deleted with the :type it belonged to.', [
                    'id' => $account->getKey(),
                    'type' => Str::lower((string) $label),
                ]),
                'actor' => $actor,
                'acknowledged' => true,
            ]);
        }
    }

    /**
     * A login may only be erased with the staff row when no other profile points
     * at it and it is not one of the accounts that manage the school.
     */
    private function isRemovableLogin(User $account, Model $staff): bool
    {
        if ($account->roles->pluck('name')->intersect(self::MANAGEMENT_ROLES)->isNotEmpty()) {
            return false;
        }

        foreach (['teachers', 'employees', 'guardians', 'students'] as $table) {
            $others = DB::table($table)->where('user_id', $account->getKey());

            if ($table === $staff->getTable()) {
                $others->where('id', '!=', $staff->getKey());
            }

            if ($others->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return class-string<Model>
     */
    public function model(string $type): string
    {
        if (! isset(self::TYPES[$type])) {
            abort(404);
        }

        return self::TYPES[$type];
    }

    public function typeLabel(string $type): string
    {
        return $type === 'teachers' ? __('Teacher') : __('Staff member');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTeacherLogin(Teacher $teacher, array $data, User $actor, int $schoolId): void
    {
        $user = new User;
        $user->fill([
            'name' => $teacher->full_name,
            'mobile_number' => preg_replace('/[\s\-()]/', '', (string) $data['phone']),
            'password' => (string) $data['password'],
            'is_active' => $teacher->status === 'active',
        ]);
        $user->school_id = $schoolId;
        $user->must_change_password = true;
        $user->save();

        $user->syncRoles(['teacher']);

        $teacher->forceFill(['user_id' => $user->id])->save();

        $this->audit->log(
            event: 'staff.login_created',
            auditable: $teacher,
            description: __('A portal login was created for :name', ['name' => $teacher->full_name]),
            newValues: ['user_id' => $user->id, 'mobile_number' => $user->mobile_number],
            actor: $actor,
            schoolId: $schoolId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        unset($data['photo'], $data['password'], $data['password_confirmation'], $data['class_ids']);

        return $data;
    }

    /**
     * Replace the classes a teacher looks after and name them for the audit
     * trail, so a reviewer can read the change without joining the pivot.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncClasses(Teacher $teacher, array $data, int $schoolId): string
    {
        $ids = collect($data['class_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->mapWithKeys(fn (int $id): array => [$id => ['school_id' => $schoolId]])
            ->all();

        $teacher->classes()->sync($ids);
        $teacher->unsetRelation('classes');

        return $teacher->classes->pluck('name')->implode(', ');
    }

    private function schoolId(): int
    {
        $schoolId = app(SchoolContext::class)->id();

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school' => __('Select a school before adding staff.'),
            ]);
        }

        return $schoolId;
    }
}
