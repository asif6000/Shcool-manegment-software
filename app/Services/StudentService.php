<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentService
{
    private const PROFILE_FIELDS = [
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'email',
        'phone',
        'present_address',
        'permanent_address',
        'status',
        'admission_date',
    ];

    private const GUARDIAN_FIELDS = [
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'guardian_email',
        'guardian_occupation',
        'guardian_address',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
        private readonly NumberSequence $numbers,
        private readonly TeachingScope $teaching,
        private readonly StudentAccountService $accounts,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, ?User $viewer = null): LengthAwarePaginator
    {
        return $this->filteredQuery($filters, $viewer)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters, ?User $viewer = null): Builder
    {
        $query = Student::query()->with([
            'guardians',
            'enrollments' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['schoolClass', 'section', 'academicSession'])
                ->latest('enrolled_on'),
        ]);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $query) use ($like): void {
                $query->where('student_id', 'like', $like)
                    ->orWhere('admission_no', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if (filled($filters['class_id'] ?? null)) {
            $classId = (int) $filters['class_id'];
            $query->whereHas('enrollments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('class_id', $classId));
        }

        if (filled($filters['section_id'] ?? null)) {
            $sectionId = (int) $filters['section_id'];
            $query->whereHas('enrollments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('section_id', $sectionId));
        }

        if (filled($filters['session_id'] ?? null)) {
            $sessionId = (int) $filters['session_id'];
            $query->whereHas('enrollments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('academic_session_id', $sessionId));
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        } else {
            // Archived profiles stay readable through the status filter, but
            // they no longer clutter the working register by default.
            $query->where('status', '!=', Student::STATUS_ARCHIVED);
        }

        if ($viewer !== null) {
            $this->teaching->constrainStudents($viewer, $this->sessionFor($filters), $query);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function sessionFor(array $filters): ?AcademicSession
    {
        return filled($filters['session_id'] ?? null)
            ? AcademicSession::query()->find((int) $filters['session_id'])
            : $this->teaching->currentSession();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, ?UploadedFile $photo = null): Student
    {
        $schoolId = $this->schoolId();
        $photoPath = $photo !== null
            ? $this->uploads->store($photo, 'student_photo', (string) $schoolId)
            : null;

        try {
            return DB::transaction(function () use ($actor, $data, $photoPath, $schoolId): Student {
                // Serialise number generation per school so concurrent creates
                // cannot pick the same student ID or admission number.
                School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

                $year = (int) substr((string) ($data['admission_date'] ?? now()->toDateString()), 0, 4);

                $student = new Student;
                $student->fill(Arr::only($data, self::PROFILE_FIELDS));
                // A register migrated from another system keeps its own numbers;
                // only the admission form leaves them blank and asks for the next
                // value in the school sequence.
                $student->student_id = filled($data['student_id'] ?? null)
                    ? trim((string) $data['student_id'])
                    : $this->numbers->next(Student::class, 'student_id', 'STU', $year, $schoolId);
                $student->admission_no = filled($data['admission_no'] ?? null)
                    ? trim((string) $data['admission_no'])
                    : $this->numbers->next(Student::class, 'admission_no', 'ADM', $year, $schoolId);
                $student->photo_path = $photoPath;
                $student->created_by = $actor->id;
                if (filled($data['phone'] ?? null)) {
                    $student->mobile_number = trim((string) $data['phone']);
                }
                $student->save();

                $this->syncGuardian($student, $data);
                $this->syncUserAccount($student, $data, $actor);

                if (filled($data['academic_session_id'] ?? null)) {
                    $this->createEnrollment($student, $data, $actor);
                }

                $this->audit->log(
                    event: 'student.created',
                    auditable: $student,
                    description: __('Student :name was admitted', ['name' => $student->full_name]),
                    newValues: $student->only([
                        'student_id', 'admission_no', 'first_name', 'last_name',
                        'gender', 'status', 'admission_date',
                    ]),
                    actor: $actor,
                    schoolId: $schoolId,
                );

                return $student;
            });
        } catch (\Throwable $exception) {
            if ($photoPath !== null) {
                $this->uploads->delete($photoPath, 'student_photo');
            }

            throw $exception;
        }
    }

    /**
     * Attach the applicant's own portal account to the student record created on
     * approval. Both sides are unique, so an account that already owns a record
     * keeps using that one instead.
     */
    public function linkAccount(Student $student, User $applicant, User $actor): bool
    {
        if ($student->user_id !== null || $applicant->hasStudentRecord()) {
            return false;
        }

        $student->forceFill(['user_id' => $applicant->id])->save();

        $this->audit->log(
            event: 'student.account_linked',
            auditable: $student,
            description: __('Applicant account :email was linked to :name', [
                'email' => $applicant->email,
                'name' => $student->full_name,
            ]),
            actor: $actor,
            schoolId: (int) $student->school_id,
        );

        return true;
    }

    /**
     * Carry an admission photo into the student photo directory.
     */
    public function adoptPhoto(Student $student, string $sourcePath): void
    {
        if ($student->photo_path !== null) {
            return;
        }

        $path = $this->uploads->copy($sourcePath, 'admission_photo', 'student_photo', (string) $student->school_id);

        if ($path === null) {
            return;
        }

        $student->forceFill(['photo_path' => $path])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Student $student, array $data, ?UploadedFile $photo = null): Student
    {
        $schoolId = (int) $student->school_id;
        $oldPhoto = $student->photo_path;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        $newPhoto = $photo !== null
            ? $this->uploads->store($photo, 'student_photo', (string) $schoolId)
            : null;

        try {
            DB::transaction(function () use ($actor, $student, $data, $newPhoto, $removePhoto): void {
                $old = $student->only(['first_name', 'last_name', 'gender', 'status', 'phone', 'email']);

                $student->fill(Arr::only($data, self::PROFILE_FIELDS));

                if ($newPhoto !== null) {
                    $student->photo_path = $newPhoto;
                } elseif ($removePhoto) {
                    $student->photo_path = null;
                }

                if (filled($data['phone'] ?? null)) {
                    $student->mobile_number = trim((string) $data['phone']);
                }

                $student->save();

                $this->syncGuardian($student, $data);
                $this->syncUserAccount($student, $data, $actor);

                if ($student->status === Student::STATUS_ARCHIVED) {
                    $this->archiveSideEffects($student);
                }

                $this->audit->log(
                    event: 'student.updated',
                    auditable: $student,
                    description: __('Student :name was updated', ['name' => $student->full_name]),
                    oldValues: $old,
                    newValues: $student->only(['first_name', 'last_name', 'gender', 'status', 'phone', 'email']),
                    actor: $actor,
                    schoolId: (int) $student->school_id,
                );
            });
        } catch (\Throwable $exception) {
            if ($newPhoto !== null) {
                $this->uploads->delete($newPhoto, 'student_photo');
            }

            throw $exception;
        }

        if ($newPhoto !== null || $removePhoto) {
            $this->uploads->delete($oldPhoto, 'student_photo');
        }

        return $student->refresh();
    }

    /**
     * Open or refresh the student's mobile-number login from the profile form.
     * No email address is written, verified or asked for on this path.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncUserAccount(Student $student, array $data, User $actor): void
    {
        $mobile = AuthenticationService::normaliseMobile(
            trim((string) ($data['phone'] ?? $data['mobile_number'] ?? ''))
        );
        $password = filled($data['password'] ?? null) ? (string) $data['password'] : null;

        $account = $student->user()->first();

        if ($account !== null) {
            $this->accounts->syncFromProfile($account, $student, $mobile, $password, $actor);

            return;
        }

        if ($student->user_id !== null) {
            // A link to a row that is gone would keep the profile permanently
            // locked out of ever getting an account.
            $student->forceFill(['user_id' => null])->save();
        }

        // An imported register row arrives with a phone number but no consent to a
        // password, and inventing one from the number would make the login
        // guessable. The office opens that login from the student profile instead.
        if ($mobile === '' || $password === null) {
            return;
        }

        $this->accounts->create($actor, $student, [
            'mobile_number' => $mobile,
            'password' => $password,
        ]);
    }

    /**
     * Take a student out of the active register without deleting the profile or
     * a single attendance record, mark, result, fee or audit entry. The row is
     * kept so every historical report still has its student reference.
     */
    public function archive(User $actor, Student $student): Student
    {
        return DB::transaction(function () use ($actor, $student): Student {
            /** @var Student $locked */
            $locked = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $old = $locked->only(['status']);

            if ($locked->status === Student::STATUS_ARCHIVED) {
                return $locked;
            }

            $locked->forceFill(['status' => Student::STATUS_ARCHIVED])->save();
            $this->archiveSideEffects($locked);

            $this->audit->log(
                event: 'student.archived',
                auditable: $locked,
                description: __('Student #:id was archived', ['id' => $locked->id]),
                oldValues: $old,
                newValues: ['status' => Student::STATUS_ARCHIVED],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    /**
     * Permanently remove only the mobile-number login. The student profile,
     * attendance, marks, results and every other academic record stay in place.
     */
    public function deleteLoginAccount(User $actor, Student $student): void
    {
        $account = $student->user()->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'login' => __('This student does not have a portal account.'),
            ]);
        }

        if (! $this->isStudentOnlyLogin($account, $student)) {
            throw ValidationException::withMessages([
                'login' => __('This login is shared with another person. Disable portal access instead of deleting it.'),
            ]);
        }

        DB::transaction(function () use ($actor, $student, $account): void {
            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            /** @var User $lockedAccount */
            $lockedAccount = User::query()->lockForUpdate()->findOrFail($account->getKey());

            if ((int) $lockedStudent->user_id !== (int) $lockedAccount->getKey()) {
                throw ValidationException::withMessages([
                    'login' => __('This login no longer belongs to the selected student.'),
                ]);
            }

            // The permanent-delete service removes the credentials, roles,
            // tokens and sessions; students.user_id is cleared by the foreign key.
            $this->deletes->delete($lockedAccount, [
                'label' => __('student login account'),
                'description' => __('Login account #:id was permanently deleted for student #:student_id.', [
                    'id' => $lockedAccount->getKey(),
                    'student_id' => $lockedStudent->id,
                ]),
                'extra' => ['student_id' => $lockedStudent->id],
                'actor' => $actor,
            ]);

            $lockedStudent->forceFill(['user_id' => null])->save();

            $this->audit->log(
                event: 'student.login_deleted',
                auditable: $lockedStudent,
                description: __('The login account for student #:id was permanently deleted', [
                    'id' => $lockedStudent->id,
                ]),
                oldValues: ['user_id' => $lockedAccount->getKey()],
                actor: $actor,
                schoolId: (int) $lockedStudent->school_id,
            );
        });
    }

    /**
     * The whole pupil leaves the database: the profile, photo, enrolments, QR
     * cards, guardian links, attendance, bills, payments, marks and results, and
     * the login that belongs only to this student. Everything is decided from the
     * live foreign key graph and written in one transaction, so a failure in the
     * middle leaves the register exactly as it was, and every query is filtered by
     * this student's own ids, so no other pupil loses a row.
     */
    public function delete(User $actor, Student $student): void
    {
        $account = $student->user()->first();
        $takeLogin = $account !== null && $this->isStudentOnlyLogin($account, $student);

        // Both halves are decided and written inside one transaction, so a
        // refusal never leaves a pupil without their login or a login without
        // the profile it was issued for.
        DB::transaction(function () use ($actor, $student, $account, $takeLogin): void {
            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->getKey());

            $this->deletes->delete($lockedStudent, [
                'label' => __('student'),
                'consume_history' => true,
                'actor' => $actor,
            ]);

            if (! $takeLogin) {
                return;
            }

            $this->deletes->delete($account, [
                'label' => __('student login account'),
                'description' => __('Login account #:id was permanently deleted with student #:student_id.', [
                    'id' => $account->getKey(),
                    'student_id' => $lockedStudent->getKey(),
                ]),
                'extra' => ['student_id' => $lockedStudent->getKey()],
                'actor' => $actor,
            ]);
        });
    }

    /**
     * An archived profile leaves the active register, keeps its history, and
     * loses portal access even though the login row itself is not deleted.
     */
    private function archiveSideEffects(Student $student): void
    {
        $student->enrollments()
            ->where('status', 'active')
            ->update(['status' => 'withdrawn']);

        $account = $student->user()->first();

        if ($account === null) {
            return;
        }

        $account->forceFill([
            'is_active' => false,
            'remember_token' => null,
        ])->save();

        $account->tokens()->delete();
        DB::table('sessions')->where('user_id', $account->id)->delete();
    }

    /**
     * A login belongs to exactly one student profile and to no staff or guardian
     * record before it can be removed with that profile.
     */
    private function isStudentOnlyLogin(User $account, Student $student): bool
    {
        return ! DB::table('teachers')->where('user_id', $account->id)->exists()
            && ! DB::table('employees')->where('user_id', $account->id)->exists()
            && ! DB::table('guardians')->where('user_id', $account->id)->exists()
            && ! DB::table('students')->where('user_id', $account->id)->where('id', '!=', $student->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEnrollment(Student $student, array $data, ?User $actor = null): Enrollment
    {
        $sessionId = (int) ($data['academic_session_id'] ?? 0);
        $classId = (int) ($data['class_id'] ?? 0);

        if ($sessionId === 0 || $classId === 0) {
            throw ValidationException::withMessages([
                'class_id' => __('Choose a session and class to enroll this student.'),
            ]);
        }

        $class = SchoolClass::query()->findOrFail($classId);

        $section = null;

        if (filled($data['section_id'] ?? null)) {
            $section = Section::query()->findOrFail((int) $data['section_id']);

            if ((int) $section->class_id !== $class->id) {
                throw ValidationException::withMessages([
                    'section_id' => __('The chosen section does not belong to this class.'),
                ]);
            }
        }

        if (Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->exists()) {
            throw ValidationException::withMessages([
                'academic_session_id' => __('This student already has an enrollment for the chosen session.'),
            ]);
        }

        $rollNo = filled($data['roll_no'] ?? null)
            ? (int) $data['roll_no']
            : $this->nextRollNo($sessionId, $class->id, $section?->id);

        $duplicate = Enrollment::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $class->id)
            ->where('section_id', $section?->id)
            ->where('roll_no', $rollNo)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'roll_no' => __('Roll :roll is already taken in this class section for the session.', ['roll' => $rollNo]),
            ]);
        }

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'academic_session_id' => $sessionId,
            'class_id' => $class->id,
            'section_id' => $section?->id,
            'roll_no' => $rollNo,
            'enrolled_on' => $data['enrolled_on'] ?? now()->toDateString(),
            'status' => 'active',
        ]);

        $this->audit->log(
            event: 'student.enrolled',
            auditable: $student,
            description: __('Student :name was enrolled in :class', [
                'name' => $student->full_name,
                'class' => $class->name,
            ]),
            newValues: [
                'academic_session_id' => $sessionId,
                'class_id' => $class->id,
                'section_id' => $section?->id,
                'roll_no' => $rollNo,
            ],
            actor: $actor,
            schoolId: (int) $student->school_id,
        );

        return $enrollment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEnrollment(Student $student, Enrollment $enrollment, array $data, User $actor): Enrollment
    {
        if ((int) $enrollment->student_id !== (int) $student->id) {
            abort(404);
        }

        $status = (string) ($data['status'] ?? $enrollment->status);
        $rollNo = array_key_exists('roll_no', $data) && filled($data['roll_no'])
            ? (int) $data['roll_no']
            : $enrollment->roll_no;

        if ($status === 'active' && $rollNo !== null) {
            $duplicate = Enrollment::query()
                ->whereKeyNot($enrollment->id)
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->where('class_id', $enrollment->class_id)
                ->where('section_id', $enrollment->section_id)
                ->where('roll_no', $rollNo)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'roll_no' => __('That roll number is already in use in this section.'),
                ]);
            }
        }

        $old = $enrollment->only(['roll_no', 'status']);

        $enrollment->forceFill(['roll_no' => $rollNo, 'status' => $status])->save();

        $this->audit->log(
            event: 'student.enrollment_updated',
            auditable: $student,
            description: __('Enrollment for :name was updated', ['name' => $student->full_name]),
            oldValues: $old,
            newValues: $enrollment->only(['roll_no', 'status']),
            actor: $actor,
            schoolId: (int) $student->school_id,
        );

        return $enrollment->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncGuardian(Student $student, array $data): void
    {
        if (blank($data['guardian_name'] ?? null)) {
            return;
        }

        $schoolId = (int) $student->school_id;
        $attributes = [
            'name' => $data['guardian_name'],
            'relation' => $data['guardian_relation'] ?? 'guardian',
            'phone' => $data['guardian_phone'] ?? null,
            'email' => $data['guardian_email'] ?? null,
            'occupation' => $data['guardian_occupation'] ?? null,
            'address' => $data['guardian_address'] ?? null,
        ];

        $guardian = null;

        if (filled($attributes['phone'])) {
            $guardian = Guardian::query()->where('phone', $attributes['phone'])->first();
        }

        if ($guardian === null && filled($attributes['email'])) {
            $guardian = Guardian::query()->where('email', $attributes['email'])->first();
        }

        if ($guardian === null) {
            $guardian = Guardian::query()->create($attributes);
        } else {
            $guardian->fill(array_filter($attributes, fn ($value) => $value !== null && $value !== ''));
            $guardian->save();
        }

        DB::table('student_guardians')
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->update(['is_primary' => false]);

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'school_id' => $schoolId,
                'is_primary' => true,
                'is_emergency_contact' => true,
            ],
        ]);

        $student->unsetRelation('guardians');
    }

    private function nextRollNo(int $sessionId, int $classId, ?int $sectionId): int
    {
        $max = Enrollment::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->when($sectionId !== null, fn (Builder $query) => $query->where('section_id', $sectionId))
            ->max('roll_no');

        return (int) $max + 1;
    }

    private function schoolId(): int
    {
        $schoolId = app(SchoolContext::class)->id();

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school' => __('Select a school before adding students.'),
            ]);
        }

        return $schoolId;
    }
}
