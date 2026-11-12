<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\NumberSequence;
use App\Support\SchoolContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    private const FIELDS = [
        'academic_session_id',
        'applied_class_id',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'address',
        'previous_school',
        'notes',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberSequence $numbers,
        private readonly StudentService $students,
        private readonly FileUploadService $uploads,
        private readonly SchoolEventNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Admission::query()
            ->with([
                'academicSession',
                'appliedClass.sections' => fn ($query) => $query->orderBy('name'),
                'student',
                'user',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $query) use ($like): void {
                $query->where('application_no', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like);
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($filters['session_id'] ?? null)) {
            $query->where('academic_session_id', (int) $filters['session_id']);
        }

        return $query
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'waitlisted' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();
    }

    /**
     * Applications belonging to one portal account, newest first.
     *
     * @return Collection<int, Admission>
     */
    public function forApplicant(User $applicant): Collection
    {
        return Admission::query()
            ->forApplicant($applicant)
            ->with(['academicSession', 'appliedClass', 'student'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Record an application at the counter on behalf of a walk-in family.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(?User $actor, array $data): Admission
    {
        return $this->insert($actor, $data, null, null);
    }

    /**
     * Submit an application from the signed-in applicant account. The account is
     * stored on the row, which is what makes duplicates detectable and lets the
     * applicant track the decision from their dashboard.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitForApplicant(User $applicant, array $data, ?UploadedFile $photo = null): Admission
    {
        if ($applicant->hasStudentRecord()) {
            throw ValidationException::withMessages([
                'admission' => __('Your admission is already complete. Open the student portal instead.'),
            ]);
        }

        $photoPath = $photo !== null
            ? $this->uploads->store($photo, 'admission_photo', (string) $this->schoolId())
            : null;

        try {
            return $this->insert($applicant, $data, $applicant, $photoPath);
        } catch (\Throwable $exception) {
            if ($photoPath !== null) {
                $this->uploads->delete($photoPath, 'admission_photo');
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insert(?User $actor, array $data, ?User $applicant, ?string $photoPath): Admission
    {
        $schoolId = $this->schoolId();

        return DB::transaction(function () use ($actor, $data, $applicant, $photoPath, $schoolId): Admission {
            // Locking the school row serialises both the number sequence and the
            // duplicate check, so two tabs cannot create the same application.
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            if ($applicant !== null) {
                $this->guardDuplicates($applicant, (int) $data['academic_session_id']);
            }

            $admission = new Admission;
            $admission->fill(Arr::only($data, self::FIELDS));
            $admission->application_no = $this->numbers->next(Admission::class, 'application_no', 'APP', now()->year, $schoolId);
            $admission->status = Admission::PENDING;
            $admission->photo_path = $photoPath;

            if ($applicant !== null) {
                $admission->forceFill(['user_id' => $applicant->id]);
            }

            $admission->save();

            $this->audit->log(
                event: 'admission.created',
                auditable: $admission,
                description: __('Admission application :no was received', ['no' => $admission->application_no]),
                newValues: $admission->only(['application_no', 'first_name', 'last_name', 'applied_class_id']),
                actor: $actor,
                schoolId: $schoolId,
            );

            return $admission;
        });
    }

    private function guardDuplicates(User $applicant, int $sessionId): void
    {
        /** @var Admission|null $blocking */
        $blocking = Admission::query()
            ->forApplicant($applicant)
            ->open()
            ->latest('created_at')
            ->first();

        if ($blocking !== null) {
            throw ValidationException::withMessages([
                'academic_session_id' => __(
                    'Application :no is still under review. Wait for the decision before submitting another one.',
                    ['no' => $blocking->application_no],
                ),
            ]);
        }

        $alreadyApplied = Admission::query()
            ->forApplicant($applicant)
            ->where('academic_session_id', $sessionId)
            ->where('status', Admission::APPROVED)
            ->exists();

        if ($alreadyApplied) {
            throw ValidationException::withMessages([
                'academic_session_id' => __('You were already admitted through an earlier application for this session.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Admission $admission, array $data): Admission
    {
        if ($admission->status === Admission::APPROVED) {
            throw ValidationException::withMessages([
                'admission' => __('Approved applications are locked because a student record already exists.'),
            ]);
        }

        $old = $admission->only(['first_name', 'last_name', 'applied_class_id', 'guardian_phone', 'status']);

        $admission->fill(Arr::only($data, self::FIELDS));

        if (filled($data['status'] ?? null)) {
            $admission->status = (string) $data['status'];
        }

        $admission->save();

        $this->audit->log(
            event: 'admission.updated',
            auditable: $admission,
            description: __('Admission application :no was updated', ['no' => $admission->application_no]),
            oldValues: $old,
            newValues: $admission->only(['first_name', 'last_name', 'applied_class_id', 'guardian_phone', 'status']),
            actor: $actor,
            schoolId: (int) $admission->school_id,
        );

        return $admission->refresh();
    }

    /**
     * Move an application between the review states without touching its data.
     * Approval has its own flow because it creates a student record.
     */
    public function changeStatus(User $actor, Admission $admission, string $status, ?string $reason = null): Admission
    {
        if ($admission->status === Admission::APPROVED || $admission->student_id !== null) {
            throw ValidationException::withMessages([
                'status' => __('Approved applications are locked because a student record already exists.'),
            ]);
        }

        if ($status === Admission::APPROVED) {
            throw ValidationException::withMessages([
                'status' => __('Use the approve action to enroll an applicant.'),
            ]);
        }

        if ($admission->status === $status) {
            return $admission;
        }

        if ($status === Admission::REJECTED) {
            return $this->reject($actor, $admission, $reason);
        }

        return DB::transaction(function () use ($actor, $admission, $status): Admission {
            /** @var Admission $admission */
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            $old = $admission->status;

            $admission->forceFill([
                'status' => $status,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            $this->audit->log(
                event: 'admission.status_changed',
                auditable: $admission,
                description: __('Admission :no status changed from :from to :to', [
                    'no' => $admission->application_no,
                    'from' => __(ucfirst((string) $old)),
                    'to' => __(ucfirst($status)),
                ]),
                oldValues: ['status' => $old],
                newValues: ['status' => $status],
                actor: $actor,
                schoolId: (int) $admission->school_id,
            );

            return $admission;
        });
    }

    /**
     * Approve an application and create the student plus the first enrollment.
     * A portal application also links the new student to the account that
     * submitted it, so the applicant lands in the student portal.
     *
     * @param  array{section_id: int, roll_no?: int|null}  $data
     */
    public function approve(User $actor, Admission $admission, array $data): Student
    {
        if ($admission->status === Admission::APPROVED || $admission->student_id !== null) {
            throw ValidationException::withMessages([
                'admission' => __('This application has already been approved.'),
            ]);
        }

        if ($admission->status === Admission::REJECTED) {
            throw ValidationException::withMessages([
                'admission' => __('A rejected application cannot be approved. Re-open it first.'),
            ]);
        }

        $student = DB::transaction(function () use ($actor, $admission, $data): Student {
            /** @var Admission $admission */
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            $student = $this->students->create($actor, [
                'first_name' => $admission->first_name,
                'last_name' => $admission->last_name,
                'gender' => $admission->gender,
                'date_of_birth' => $admission->date_of_birth->toDateString(),
                'nationality' => 'Bangladeshi',
                'present_address' => $admission->address,
                'permanent_address' => $admission->address,
                'status' => 'active',
                'admission_date' => now()->toDateString(),
                'guardian_name' => $admission->guardian_name,
                'guardian_relation' => 'guardian',
                'guardian_phone' => $admission->guardian_phone,
                'guardian_email' => $admission->guardian_email,
                'guardian_address' => $admission->address,
                'academic_session_id' => $admission->academic_session_id,
                'class_id' => $admission->applied_class_id,
                'section_id' => $data['section_id'],
                'roll_no' => $data['roll_no'] ?? null,
                'enrolled_on' => now()->toDateString(),
            ]);

            if ($admission->hasPhoto()) {
                $this->students->adoptPhoto($student, (string) $admission->photo_path);
            }

            $applicant = $admission->user;

            if ($applicant !== null) {
                $this->students->linkAccount($student, $applicant, $actor);
            }

            $admission->forceFill([
                'status' => Admission::APPROVED,
                'student_id' => $student->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'admission.approved',
                auditable: $admission,
                description: __('Admission :no was approved for :name', [
                    'no' => $admission->application_no,
                    'name' => $student->full_name,
                ]),
                newValues: ['student_id' => $student->id, 'student_code' => $student->student_id],
                actor: $actor,
                schoolId: (int) $admission->school_id,
            );

            return $student;
        });

        $this->notifier->admissionApproved($admission->fresh());

        return $student;
    }

    public function reject(User $actor, Admission $admission, ?string $reason): Admission
    {
        if ($admission->status === Admission::APPROVED) {
            throw ValidationException::withMessages([
                'admission' => __('An approved application cannot be rejected.'),
            ]);
        }

        $admission = DB::transaction(function () use ($actor, $admission, $reason): Admission {
            /** @var Admission $admission */
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);

            if ($admission->status === Admission::APPROVED) {
                throw ValidationException::withMessages([
                    'admission' => __('An approved application cannot be rejected.'),
                ]);
            }

            $admission->forceFill([
                'status' => Admission::REJECTED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                // Only this field is shown to the applicant; internal notes stay private.
                'rejection_reason' => blank($reason) ? null : trim((string) $reason),
            ])->save();

            $this->audit->log(
                event: 'admission.rejected',
                auditable: $admission,
                description: __('Admission :no was rejected', ['no' => $admission->application_no]),
                newValues: ['status' => Admission::REJECTED, 'reason' => $reason],
                actor: $actor,
                schoolId: (int) $admission->school_id,
            );

            return $admission;
        });

        $this->notifier->admissionRejected($admission);

        return $admission;
    }

    private function schoolId(): int
    {
        $schoolId = app(SchoolContext::class)->id();

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school' => __('Select a school before recording admissions.'),
            ]);
        }

        return $schoolId;
    }
}
