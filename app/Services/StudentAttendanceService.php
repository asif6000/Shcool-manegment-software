<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The single write path for student attendance. Manual registers, QR card scans
 * and biometric device events all pass through this service so that validation,
 * duplicate handling, locking and auditing behave identically.
 */
class StudentAttendanceService
{
    public const STATUSES = ['present', 'absent', 'late', 'leave', 'excused'];

    public const SOURCE_MANUAL = 'manual';

    public const AUTOMATED_SOURCES = ['qr', 'fingerprint', 'face'];

    /** Automated events may only move a record towards "attended". */
    private const ATTENDED_STATUSES = ['present', 'late'];

    /** A leave or an excused day is a staff decision, not something a scan undoes. */
    private const PROTECTED_STATUSES = ['leave', 'excused'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HolidayService $holidays,
        private readonly AttendanceScope $scope,
        private readonly AttendancePolicy $policy,
    ) {}

    public function holidayOn(Carbon $date): ?Holiday
    {
        return $this->holidays->on($date);
    }

    /**
     * @return Collection<int, array{enrollment: Enrollment, attendance: ?StudentAttendance}>
     */
    public function sheet(
        User $actor,
        AcademicSession $session,
        SchoolClass $class,
        ?Section $section,
        Carbon $date,
    ): Collection {
        $enrollments = $this->enrollments(
            $session,
            $class,
            $section,
            $this->scope->sectionIds($actor, $session, (int) $class->id),
        );

        $attendance = StudentAttendance::query()
            ->where('attendance_date', $date->toDateString())
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        return $enrollments->map(fn (Enrollment $enrollment): array => [
            'enrollment' => $enrollment,
            'attendance' => $attendance->get($enrollment->student_id),
        ]);
    }

    /**
     * Save a manual register. Re-saving the same date updates the existing rows
     * instead of duplicating them; changing a row produced by QR or a device
     * (or one already corrected) needs a written reason and locks it against
     * automated overwrites.
     *
     * @param  array<string, mixed>  $data
     */
    public function mark(User $actor, array $data): int
    {
        $schoolId = app(SchoolContext::class)->id();
        $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $section = filled($data['section_id'] ?? null)
            ? Section::query()->where('class_id', $class->id)->findOrFail($data['section_id'])
            : null;
        $date = Carbon::parse($data['attendance_date'])->startOfDay();

        $this->scope->authorize($actor, $session, (int) $class->id, $section !== null ? (int) $section->id : null);
        $this->assertInsideSession($session, $date);

        $holiday = $this->holidayOn($date);

        if ($holiday !== null && ! (bool) ($data['override_holiday'] ?? false)) {
            throw ValidationException::withMessages([
                'attendance_date' => __(':date is a holiday (:name). Confirm the override to mark attendance.', [
                    'date' => $date->format('d M Y'),
                    'name' => $holiday->name,
                ]),
            ]);
        }

        $enrollments = $this->enrollments(
            $session,
            $class,
            $section,
            $this->scope->sectionIds($actor, $session, (int) $class->id),
        )->keyBy('student_id');
        $statuses = (array) ($data['statuses'] ?? []);
        $reason = trim((string) ($data['correction_reason'] ?? ''));

        // Resolved from the container rather than injected: this service owns the
        // sheet the submission snapshot reads, so a constructor dependency would
        // be a cycle.
        $submissions = app(AttendanceSubmissionService::class);
        $submission = $submissions->openSubmissionFor(
            (int) $session->id,
            (int) $class->id,
            $section !== null ? (int) $section->id : null,
            $date->toDateString(),
        );

        $submitted = [];

        foreach ($enrollments as $studentId => $enrollment) {
            if (! array_key_exists($studentId, $statuses)) {
                continue;
            }

            $status = (string) $statuses[$studentId];

            if (in_array($status, self::STATUSES, true)) {
                $submitted[$studentId] = ['status' => $status, 'enrollment' => $enrollment];
            }
        }

        if ($submitted === []) {
            throw ValidationException::withMessages([
                'statuses' => __('No enrolled students matched the submitted attendance.'),
            ]);
        }

        $newlyAbsent = [];

        DB::transaction(function () use ($submitted, $actor, $date, $class, $section, $holiday, $schoolId, $reason, $submission, $submissions, &$newlyAbsent): void {
            $existing = StudentAttendance::query()
                ->where('attendance_date', $date->toDateString())
                ->whereIn('student_id', array_keys($submitted))
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            $lockedChanges = $existing->filter(
                fn (StudentAttendance $record, int $studentId): bool => $record->status !== $submitted[$studentId]['status']
                    && ($record->isLocked() || ! $record->isManual()),
            );

            if ($lockedChanges->isNotEmpty() && mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'correction_reason' => __('This attendance was already corrected. Give a reason of at least 5 characters to change it again.'),
                ]);
            }

            // Once a register has been submitted it is a reviewed document, not a
            // draft: only the office may touch it without a written reason.
            if ($submission !== null && ! $actor->can('attendance.approve') && mb_strlen($reason) < 5) {
                $altersRecorded = collect(array_keys($submitted))->contains(
                    fn (int $studentId): bool => $existing->get($studentId)?->status !== $submitted[$studentId]['status'],
                );

                if ($altersRecorded) {
                    throw ValidationException::withMessages([
                        'correction_reason' => __('This register was submitted on :date. Give a reason of at least 5 characters to change it — the day goes back for review.', [
                            'date' => $date->format('d M Y'),
                        ]),
                    ]);
                }
            }

            $counts = array_fill_keys(self::STATUSES, 0);
            $created = [];
            $changed = 0;
            $corrected = 0;

            foreach ($submitted as $studentId => $row) {
                /** @var Enrollment $enrollment */
                $enrollment = $row['enrollment'];
                $status = $row['status'];
                $counts[$status]++;
                $current = $existing->get($studentId);

                if ($current === null) {
                    if ($status === 'absent') {
                        $newlyAbsent[] = $studentId;
                    }

                    $created[] = [
                        'school_id' => $schoolId,
                        'student_id' => $studentId,
                        'enrollment_id' => $enrollment->id,
                        'academic_session_id' => $enrollment->academic_session_id,
                        'class_id' => $enrollment->class_id,
                        'section_id' => $enrollment->section_id,
                        'attendance_date' => $date->toDateString(),
                        'status' => $status,
                        'source' => self::SOURCE_MANUAL,
                        'source_ref' => null,
                        'marked_by' => $actor->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    continue;
                }

                if ($current->status === $status) {
                    continue;
                }

                if ($status === 'absent' && $current->status !== 'absent') {
                    $newlyAbsent[] = $studentId;
                }

                // Changing an automated mark (or one already corrected) is a
                // correction: it needs the written reason checked above and is
                // locked so a delayed QR or device punch cannot undo it.
                $wasLocked = $current->isLocked();
                $isCorrection = $wasLocked || ! $current->isManual();
                $previousStatus = $current->status;

                $current->fill([
                    'enrollment_id' => $enrollment->id,
                    'academic_session_id' => $enrollment->academic_session_id,
                    'class_id' => $enrollment->class_id,
                    'section_id' => $enrollment->section_id,
                    'status' => $status,
                    'source' => self::SOURCE_MANUAL,
                    'marked_by' => $actor->id,
                ]);

                if ($isCorrection) {
                    $current->fill([
                        'locked_at' => $current->locked_at ?? now(),
                        'correction_reason' => $reason,
                        'corrected_by' => $actor->id,
                        'corrected_at' => now(),
                    ]);
                }

                $current->save();

                $changed++;

                if ($isCorrection) {
                    $corrected++;

                    $this->audit->log(
                        event: 'student_attendance.corrected',
                        auditable: $current,
                        description: __('Attendance for :student on :date was corrected', [
                            'student' => $enrollment->student?->full_name ?? '#'.$studentId,
                            'date' => $date->format('d M Y'),
                        ]),
                        oldValues: ['status' => $previousStatus],
                        newValues: ['status' => $status, 'reason' => $reason],
                        actor: $actor,
                        schoolId: $schoolId,
                    );

                    continue;
                }

                $this->audit->log(
                    event: 'student_attendance.updated',
                    auditable: $current,
                    description: __('Attendance for :student on :date changed from :old to :new', [
                        'student' => $enrollment->student?->full_name ?? '#'.$studentId,
                        'date' => $date->format('d M Y'),
                        'old' => __(ucfirst($previousStatus)),
                        'new' => __(ucfirst($status)),
                    ]),
                    oldValues: ['status' => $previousStatus],
                    newValues: ['status' => $status],
                    actor: $actor,
                    schoolId: $schoolId,
                );
            }

            if ($created !== []) {
                StudentAttendance::query()->insert($created);
            }

            $this->audit->log(
                event: 'student_attendance.marked',
                auditable: null,
                description: __('Attendance was marked for :class on :date', [
                    'class' => $class->name.($section ? ' · '.$section->name : ''),
                    'date' => $date->format('d M Y'),
                ]),
                newValues: [
                    'date' => $date->toDateString(),
                    'class_id' => $class->id,
                    'section_id' => $section?->id,
                    'counts' => $counts,
                    'holiday_override' => $holiday !== null,
                    'source' => self::SOURCE_MANUAL,
                    'created' => count($created),
                    'changed' => $changed,
                    'corrections' => $corrected,
                    'correction_reason' => $reason !== '' ? $reason : null,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            if ($submission !== null && ($changed > 0 || $created !== [])) {
                $submissions->afterManualEdit($actor, $submission, $reason, $changed + count($created));
            }
        });

        if ($newlyAbsent !== []) {
            try {
                $students = Student::query()
                    ->whereIn('id', $newlyAbsent)
                    ->with(['user', 'guardians.user'])
                    ->get();

                $notifier = app(SchoolEventNotifier::class);

                foreach ($students as $student) {
                    $notifier->studentAbsent($student, $date);
                }
            } catch (Throwable $exception) {
                // Attendance is already committed; a notification failure must
                // not make the marking request look unsuccessful.
                report($exception);
            }
        }

        return count($submitted);
    }

    /**
     * Apply one QR scan or device punch. The caller (AttendanceEventService)
     * owns the idempotency log; this method only decides what the mark should
     * become and records it.
     *
     * @return array{
     *     status: string,
     *     reason: ?string,
     *     attendance: ?StudentAttendance,
     *     session: ?AcademicSession,
     *     enrollment: ?Enrollment
     * }
     */
    public function applyAutomated(
        ?User $actor,
        Student $student,
        string $source,
        Carbon $occurredAt,
        ?string $sourceRef = null,
        ?AttendanceDevice $device = null,
        ?string $deviceUserId = null,
    ): array {
        $date = $occurredAt->copy()->startOfDay();

        if (! in_array($source, self::AUTOMATED_SOURCES, true)) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'unknown_source');
        }

        if ($date->gt(now()->startOfDay())) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'future_date');
        }

        $session = AcademicSession::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($session === null) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'outside_session');
        }

        if ($this->holidayOn($date) !== null) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'holiday', session: $session);
        }

        if (! $this->policy->isWorkingDay($date)) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'non_working_day', session: $session);
        }

        $enrollment = Enrollment::query()
            ->with('section')
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($enrollment === null) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'not_enrolled', session: $session);
        }

        if ($actor !== null && ! $this->scope->allows(
            $actor,
            $session,
            (int) $enrollment->class_id,
            $enrollment->section_id !== null ? (int) $enrollment->section_id : null,
        )) {
            return $this->outcome(AttendanceEvent::STATUS_REJECTED, 'out_of_scope', session: $session, enrollment: $enrollment);
        }

        $schoolId = app(SchoolContext::class)->id();

        return DB::transaction(function () use ($actor, $student, $source, $sourceRef, $device, $deviceUserId, $date, $session, $enrollment, $schoolId): array {
            $attendance = StudentAttendance::query()
                ->where('student_id', $student->id)
                ->where('attendance_date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            if ($attendance !== null && $attendance->isLocked()) {
                return $this->outcome(AttendanceEvent::STATUS_SKIPPED, 'locked', $attendance, $session, $enrollment);
            }

            if ($attendance !== null && in_array($attendance->status, self::ATTENDED_STATUSES, true)) {
                return $this->outcome(AttendanceEvent::STATUS_DUPLICATE, 'already_marked', $attendance, $session, $enrollment);
            }

            if ($attendance !== null && in_array($attendance->status, self::PROTECTED_STATUSES, true)) {
                return $this->outcome(AttendanceEvent::STATUS_SKIPPED, 'protected_status', $attendance, $session, $enrollment);
            }

            $previousStatus = $attendance?->status;

            if ($attendance === null) {
                $attendance = StudentAttendance::query()->create([
                    'student_id' => $student->id,
                    'enrollment_id' => $enrollment->id,
                    'academic_session_id' => $session->id,
                    'class_id' => $enrollment->class_id,
                    'section_id' => $enrollment->section_id,
                    'attendance_date' => $date->toDateString(),
                    'status' => 'present',
                    'source' => $source,
                    'source_ref' => $sourceRef,
                    'marked_by' => $actor?->id,
                ]);
            } else {
                $attendance->fill([
                    'enrollment_id' => $enrollment->id,
                    'academic_session_id' => $session->id,
                    'class_id' => $enrollment->class_id,
                    'section_id' => $enrollment->section_id,
                    'status' => 'present',
                    'source' => $source,
                    'source_ref' => $sourceRef,
                    'marked_by' => $actor?->id ?? $attendance->marked_by,
                ])->save();
            }

            $this->audit->log(
                event: $source === 'qr' ? 'student_attendance.scanned' : 'student_attendance.device_event',
                auditable: $attendance,
                description: __('Attendance for :student on :date was recorded by :method', [
                    'student' => $student->full_name,
                    'date' => $date->format('d M Y'),
                    'method' => __(config('attendance.sources.'.$source, ucfirst($source))),
                ]),
                oldValues: $previousStatus !== null ? ['status' => $previousStatus] : [],
                newValues: [
                    'status' => 'present',
                    'source' => $source,
                    'source_ref' => $sourceRef,
                    'device_id' => $device?->id,
                    'device_user_id' => $deviceUserId,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $this->outcome(AttendanceEvent::STATUS_APPLIED, null, $attendance, $session, $enrollment);
        });
    }

    /**
     * @return array{
     *     rows: Collection<int, array{enrollment: Enrollment, counts: array<string, int>, marked: int, rate: ?float}>,
     *     totals: array<string, int>,
     *     working_days: int,
     *     average_rate: ?float,
     *     excluded: int
     * }
     */
    public function report(
        User $actor,
        AcademicSession $session,
        SchoolClass $class,
        ?Section $section,
        Carbon $month,
    ): array {
        $this->scope->authorize($actor, $session, (int) $class->id, $section !== null ? (int) $section->id : null);

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $sessionStart = Carbon::parse($session->start_date)->startOfDay();
        $sessionEnd = Carbon::parse($session->end_date)->startOfDay();

        if ($start->lt($sessionStart)) {
            $start = $sessionStart;
        }

        if ($end->gt($sessionEnd)) {
            $end = $sessionEnd;
        }

        $enrollments = $this->enrollments(
            $session,
            $class,
            $section,
            $this->scope->sectionIds($actor, $session, (int) $class->id),
        );
        $studentIds = $enrollments->pluck('student_id');

        $records = $start->lte($end)
            ? StudentAttendance::query()
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('student_id', $studentIds)
                ->get(['student_id', 'attendance_date', 'status'])
            : collect();

        $countable = [];
        $aggregates = [];
        $countableDates = [];
        $excluded = 0;

        foreach ($records as $record) {
            $recordDate = Carbon::parse($record->attendance_date)->startOfDay();
            $key = $recordDate->toDateString();

            $countable[$key] ??= $this->policy->isCountable($recordDate);

            if (! $countable[$key]) {
                $excluded++;

                continue;
            }

            $countableDates[$key] = true;
            $aggregates[$record->student_id][$record->status] = ($aggregates[$record->student_id][$record->status] ?? 0) + 1;
        }

        $totals = array_fill_keys(self::STATUSES, 0);

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($aggregates, &$totals): array {
            $counts = array_fill_keys(self::STATUSES, 0);

            foreach ($aggregates[$enrollment->student_id] ?? [] as $status => $count) {
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = $count;
                }
            }

            foreach ($counts as $status => $count) {
                $totals[$status] += $count;
            }

            $percentage = $this->policy->percentage($counts);

            return [
                'enrollment' => $enrollment,
                'counts' => $counts,
                'marked' => array_sum($counts),
                'rate' => $percentage['rate'],
            ];
        });

        return [
            'rows' => $rows,
            'totals' => $totals,
            'working_days' => count($countableDates),
            'average_rate' => $this->policy->percentage($totals)['rate'],
            'excluded' => $excluded,
        ];
    }

    /**
     * @return array{status: string, reason: ?string, attendance: ?StudentAttendance, session: ?AcademicSession, enrollment: ?Enrollment}
     */
    private function outcome(
        string $status,
        ?string $reason,
        ?StudentAttendance $attendance = null,
        ?AcademicSession $session = null,
        ?Enrollment $enrollment = null,
    ): array {
        return compact('status', 'reason', 'attendance', 'session', 'enrollment');
    }

    private function assertInsideSession(AcademicSession $session, Carbon $date): void
    {
        if ($date->lt($session->start_date) || $date->gt($session->end_date)) {
            throw ValidationException::withMessages([
                'attendance_date' => __('The attendance date must fall inside the selected academic session.'),
            ]);
        }
    }

    /**
     * @param  Collection<int, int>|null  $sectionIds  Null means every section.
     * @return Collection<int, Enrollment>
     */
    private function enrollments(
        AcademicSession $session,
        SchoolClass $class,
        ?Section $section,
        ?Collection $sectionIds = null,
    ): Collection {
        return Enrollment::query()
            ->with(['student', 'section'])
            ->where('academic_session_id', $session->id)
            ->where('class_id', $class->id)
            ->when($section !== null, fn ($query) => $query->where('section_id', $section->id))
            ->when($sectionIds !== null, fn ($query) => $query->whereIn('section_id', $sectionIds))
            ->where('status', 'active')
            ->whereHas('student')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->orderBy('id')
            ->get();
    }
}
