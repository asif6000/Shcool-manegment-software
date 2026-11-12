<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\StudentResult;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPromotionService
{
    private const PASS_GPA = 2.00;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Everything the promotion workspace needs for the selected session.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function workspace(array $filters): array
    {
        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $current = $sessions->firstWhere('is_current', true);
        $fromSession = filled($filters['from_session_id'] ?? null)
            ? $sessions->firstWhere('id', (int) $filters['from_session_id'])
            : ($current ?? $sessions->first());

        $classes = SchoolClass::query()
            ->with(['sections' => fn ($query) => $query->orderBy('name')])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $sourceSections = filled($filters['class_id'] ?? null)
            ? Section::query()->where('class_id', (int) $filters['class_id'])->orderBy('name')->get(['id', 'name', 'class_id'])
            : collect();

        if (filled($filters['section_id'] ?? null) && ! $sourceSections->contains('id', (int) $filters['section_id'])) {
            unset($filters['section_id']);
        }

        $students = $fromSession === null
            ? $this->emptyPage($filters)
            : $this->candidateQuery($filters, (int) $fromSession->id)
                ->paginate(25)
                ->withQueryString();

        $context = $this->decoratePage($students, $fromSession?->id);

        return [
            'sessions' => $sessions,
            'currentSession' => $current,
            'fromSession' => $fromSession,
            'targetSession' => $this->defaultTargetSession($sessions, $fromSession),
            'sessionsForTarget' => $sessions->reject(fn (AcademicSession $session): bool => $fromSession !== null && $session->id === $fromSession->id)->values(),
            'classes' => $classes,
            'sourceSections' => $sourceSections,
            'students' => $students,
            'context' => $context,
            'filters' => $filters,
            'summary' => $this->pageSummary($students, $context),
        ];
    }

    /**
     * Build a read-only preview. No database rows are changed here.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(array $data): array
    {
        return $this->buildPlan($data, false);
    }

    /**
     * Apply the plan as one transaction. Any conflict or unexpected database
     * failure rolls the whole batch back, leaving old enrollments untouched.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function process(array $data, User $actor): array
    {
        $schoolId = (int) app(SchoolContext::class)->id();

        try {
            return DB::transaction(function () use ($data, $actor, $schoolId): array {
                // Promotion allocates roll numbers, so serialise the batch per
                // school before reading or writing any target enrollment.
                School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

                $plan = $this->buildPlan($data, true);

                if ($plan['conflict_count'] > 0) {
                    $details = collect($plan['rows'])
                        ->flatMap(fn (array $row): array => $row['errors'])
                        ->unique()
                        ->take(4)
                        ->implode(' ');

                    throw ValidationException::withMessages([
                        'students' => __(':count student(s) could not be processed. :details', [
                            'count' => $plan['conflict_count'],
                            'details' => $details,
                        ]),
                    ]);
                }

                $action = (string) $plan['action'];
                $fromSession = AcademicSession::query()->findOrFail($plan['from_session_id']);
                $toSession = $plan['to_session_id'] !== null
                    ? AcademicSession::query()->findOrFail($plan['to_session_id'])
                    : null;

                $counts = array_fill_keys(StudentPromotion::ACTIONS, 0);
                $processedNames = [];

                foreach ($plan['rows'] as $row) {
                    $student = Student::query()->whereKey($row['student_id'])->lockForUpdate()->firstOrFail();
                    $enrollment = Enrollment::query()->whereKey($row['from_enrollment_id'])->lockForUpdate()->firstOrFail();

                    if ($enrollment->status !== 'active') {
                        throw ValidationException::withMessages([
                            'students' => __('The current enrollment for :name is no longer active. Nothing was changed.', [
                                'name' => $row['student_name'],
                            ]),
                        ]);
                    }

                    if (StudentPromotion::query()
                        ->where('student_id', $student->id)
                        ->where('from_academic_session_id', $fromSession->id)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'students' => __(':name has already been processed for :session. Nothing was changed.', [
                                'name' => $row['student_name'],
                                'session' => $fromSession->name,
                            ]),
                        ]);
                    }

                    $toEnrollment = null;
                    $recordStatus = 'completed';

                    if (in_array($action, ['promote', 'repeat'], true)) {
                        $enrollment->forceFill(['status' => 'completed'])->save();

                        $toEnrollment = Enrollment::query()->create([
                            'student_id' => $student->id,
                            'academic_session_id' => $toSession->id,
                            'class_id' => $row['to_class_id'],
                            'section_id' => $row['to_section_id'],
                            'roll_no' => $row['to_roll_no'],
                            'enrolled_on' => $toSession->start_date?->toDateString() ?? now()->toDateString(),
                            'status' => 'active',
                        ]);

                        if ($student->status !== 'active') {
                            $student->forceFill(['status' => 'active'])->save();
                        }
                    } elseif ($action === 'transfer_out') {
                        $enrollment->forceFill(['status' => 'transferred'])->save();
                        $student->forceFill(['status' => 'transferred'])->save();
                        $recordStatus = 'transferred';
                    } else {
                        // Hold keeps the current enrollment exactly as it is.
                        // The student is not enrolled in the target session.
                        $recordStatus = 'held';
                    }

                    $promotion = StudentPromotion::query()->create([
                        'student_id' => $student->id,
                        'from_academic_session_id' => $fromSession->id,
                        'to_academic_session_id' => $toEnrollment?->academic_session_id,
                        'from_enrollment_id' => $enrollment->id,
                        'to_enrollment_id' => $toEnrollment?->id,
                        'from_class_id' => $row['from_class_id'],
                        'from_section_id' => $row['from_section_id'],
                        'from_roll_no' => $row['from_roll_no'],
                        'to_class_id' => $toEnrollment?->class_id,
                        'to_section_id' => $toEnrollment?->section_id,
                        'to_roll_no' => $toEnrollment?->roll_no,
                        'action' => $action,
                        'status' => $recordStatus,
                        'eligibility' => $row['eligibility']['key'],
                        'result_exam_id' => $row['result']['exam_id'] ?? null,
                        'result_snapshot' => $row['result_snapshot'],
                        'notes' => $data['notes'] ?? null,
                        'promoted_by' => $actor->id,
                        'promoted_at' => now(),
                    ]);

                    $event = match ($action) {
                        'promote' => 'student.promoted',
                        'repeat' => 'student.repeated',
                        'hold' => 'student.held',
                        'transfer_out' => 'student.transferred',
                    };

                    $description = match ($action) {
                        'promote' => __(':name was promoted from :from to :to', [
                            'name' => $student->full_name,
                            'from' => $row['from_class_name'],
                            'to' => $row['to_class_name'] ?? '',
                        ]),
                        'repeat' => __(':name will repeat in :to', [
                            'name' => $student->full_name,
                            'to' => $row['to_class_name'] ?? '',
                        ]),
                        'hold' => __(':name was held in :class', [
                            'name' => $student->full_name,
                            'class' => $row['from_class_name'],
                        ]),
                        'transfer_out' => __(':name was marked as transferred out', [
                            'name' => $student->full_name,
                        ]),
                    };

                    $this->audit->log(
                        event: $event,
                        auditable: $student,
                        description: $description,
                        oldValues: [
                            'academic_session_id' => $fromSession->id,
                            'class_id' => $row['from_class_id'],
                            'section_id' => $row['from_section_id'],
                            'roll_no' => $row['from_roll_no'],
                            'enrollment_status' => 'active',
                        ],
                        newValues: [
                            'promotion_id' => $promotion->id,
                            'action' => $action,
                            'academic_session_id' => $toEnrollment?->academic_session_id,
                            'class_id' => $toEnrollment?->class_id,
                            'section_id' => $toEnrollment?->section_id,
                            'roll_no' => $toEnrollment?->roll_no,
                            'eligibility' => $row['eligibility']['key'],
                        ],
                        actor: $actor,
                        schoolId: $schoolId,
                    );

                    $counts[$action]++;
                    $processedNames[] = $student->full_name;
                }

                $this->audit->log(
                    event: 'student.promotion_batch',
                    auditable: null,
                    description: __('A :action batch was completed for :count student(s) in :session', [
                        'action' => StudentPromotion::actionLabels()[$action],
                        'count' => array_sum($counts),
                        'session' => $fromSession->name,
                    ]),
                    newValues: [
                        'action' => $action,
                        'from_academic_session_id' => $fromSession->id,
                        'to_academic_session_id' => $toSession?->id,
                        'class_id' => $plan['target_class_id'],
                        'section_id' => $plan['target_section_id'],
                        'count' => array_sum($counts),
                    ],
                    actor: $actor,
                    schoolId: $schoolId,
                );

                return [
                    'action' => $action,
                    'action_label' => StudentPromotion::actionLabels()[$action],
                    'processed' => array_sum($counts),
                    'counts' => $counts,
                    'from_session' => $fromSession->name,
                    'to_session' => $toSession?->name,
                    'target_class' => $plan['target_class_name'],
                    'target_section' => $plan['target_section_name'],
                    'students' => $processedNames,
                ];
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'promotion' => __('The promotion could not be completed. All changes were rolled back.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters): array
    {
        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $current = $sessions->firstWhere('is_current', true);
        $fromSession = filled($filters['from_session_id'] ?? null)
            ? $sessions->firstWhere('id', (int) $filters['from_session_id'])
            : ($current ?? $sessions->first());

        $base = StudentPromotion::query()
            ->when($fromSession !== null, fn (Builder $query) => $query->where('from_academic_session_id', $fromSession->id))
            ->when(filled($filters['to_session_id'] ?? null), fn (Builder $query) => $query->where('to_academic_session_id', (int) $filters['to_session_id']))
            ->when(filled($filters['class_id'] ?? null), function (Builder $query) use ($filters): void {
                $classId = (int) $filters['class_id'];
                $query->where(fn (Builder $query) => $query
                    ->where('from_class_id', $classId)
                    ->orWhere('to_class_id', $classId));
            })
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->whereHas('student', fn (Builder $query) => $query
                    ->where('student_id', 'like', $term)
                    ->orWhere('admission_no', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            });

        $summary = (clone $base)
            ->reorder()
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $promotions = (clone $base)
            ->when(filled($filters['action'] ?? null) && in_array($filters['action'], StudentPromotion::ACTIONS, true), fn (Builder $query) => $query->where('action', $filters['action']))
            ->with([
                'student',
                'fromSession',
                'toSession',
                'fromClass',
                'fromSection',
                'toClass',
                'toSection',
                'resultExam',
                'promoter',
            ])
            ->orderByDesc('promoted_at')
            ->paginate(25)
            ->withQueryString();

        return [
            'sessions' => $sessions,
            'currentSession' => $current,
            'fromSession' => $fromSession,
            'classes' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(),
            'filters' => $filters,
            'promotions' => $promotions,
            'summary' => array_merge(array_fill_keys(StudentPromotion::ACTIONS, 0), $summary),
            'total' => array_sum($summary),
            'actions' => StudentPromotion::actionLabels(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportRows(array $filters): Collection
    {
        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $current = $sessions->firstWhere('is_current', true);
        $fromSession = filled($filters['from_session_id'] ?? null)
            ? $sessions->firstWhere('id', (int) $filters['from_session_id'])
            : ($current ?? $sessions->first());

        return StudentPromotion::query()
            ->when($fromSession !== null, fn (Builder $query) => $query->where('from_academic_session_id', $fromSession->id))
            ->when(filled($filters['to_session_id'] ?? null), fn (Builder $query) => $query->where('to_academic_session_id', (int) $filters['to_session_id']))
            ->when(filled($filters['action'] ?? null) && in_array($filters['action'], StudentPromotion::ACTIONS, true), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(filled($filters['class_id'] ?? null), function (Builder $query) use ($filters): void {
                $classId = (int) $filters['class_id'];
                $query->where(fn (Builder $query) => $query
                    ->where('from_class_id', $classId)
                    ->orWhere('to_class_id', $classId));
            })
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->whereHas('student', fn (Builder $query) => $query
                    ->where('student_id', 'like', $term)
                    ->orWhere('admission_no', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->with([
                'student',
                'fromSession',
                'toSession',
                'fromClass',
                'fromSection',
                'toClass',
                'toSection',
                'resultExam',
                'promoter',
            ])
            ->orderByDesc('promoted_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function candidateQuery(array $filters, int $fromSessionId): Builder
    {
        $schoolId = (int) app(SchoolContext::class)->id();

        return Enrollment::query()
            ->with(['student', 'schoolClass', 'section', 'academicSession'])
            ->where('academic_session_id', $fromSessionId)
            ->where('status', 'active')
            ->when(filled($filters['class_id'] ?? null), fn (Builder $query) => $query->where('class_id', (int) $filters['class_id']))
            ->when(filled($filters['section_id'] ?? null), fn (Builder $query) => $query->where('section_id', (int) $filters['section_id']))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->whereHas('student', fn (Builder $query) => $query
                    ->where('student_id', 'like', $term)
                    ->orWhere('admission_no', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when(($filters['state'] ?? null) === 'processed', function (Builder $query) use ($fromSessionId, $schoolId): void {
                $query->whereIn('student_id', StudentPromotion::query()
                    ->select('student_id')
                    ->where('school_id', $schoolId)
                    ->where('from_academic_session_id', $fromSessionId));
            })
            ->when(($filters['state'] ?? null) === 'pending', function (Builder $query) use ($fromSessionId, $schoolId): void {
                $query->whereNotIn('student_id', StudentPromotion::query()
                    ->select('student_id')
                    ->where('school_id', $schoolId)
                    ->where('from_academic_session_id', $fromSessionId));
            })
            ->orderBy('class_id')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->orderBy('student_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function emptyPage(array $filters): LengthAwarePaginator
    {
        return Enrollment::query()
            ->whereRaw('1 = 0')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function decoratePage(LengthAwarePaginator $students, ?int $fromSessionId): array
    {
        $studentIds = $students->getCollection()
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($studentIds === [] || $fromSessionId === null) {
            return [];
        }

        $results = $this->resultSummaries($studentIds, $fromSessionId);
        $promotions = StudentPromotion::query()
            ->where('from_academic_session_id', $fromSessionId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');
        $history = $this->historySummaries($studentIds, $fromSessionId);

        $context = [];

        foreach ($studentIds as $studentId) {
            $result = $results[$studentId] ?? null;

            $context[$studentId] = [
                'result' => $result,
                'eligibility' => $this->eligibility($result),
                'promotion' => $promotions->get($studentId),
                'history' => $history[$studentId] ?? [
                    'attendance_count' => 0,
                    'fee_invoice_count' => 0,
                    'fee_paid_total' => 0.0,
                ],
            ];
        }

        return $context;
    }

    /**
     * The latest published exam result in the source session.
     *
     * @param  list<int>  $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function resultSummaries(array $studentIds, int $fromSessionId): array
    {
        if ($studentIds === []) {
            return [];
        }

        $exams = Exam::query()
            ->where('academic_session_id', $fromSessionId)
            ->where('status', 'published')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'end_date']);

        if ($exams->isEmpty()) {
            return [];
        }

        $order = $exams->pluck('id')->flip();

        $results = StudentResult::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->where('status', 'published')
            ->get()
            ->sortBy(fn (StudentResult $result): int => (int) ($order[$result->exam_id] ?? 999999))
            ->unique('student_id');

        $examNames = $exams->keyBy('id');

        return $results
            ->mapWithKeys(function (StudentResult $result) use ($examNames): array {
                $exam = $examNames->get($result->exam_id);

                return [
                    (int) $result->student_id => [
                        'exam_id' => (int) $result->exam_id,
                        'exam_name' => $exam?->name,
                        'gpa' => $result->gpa !== null ? (float) $result->gpa : null,
                        'grade_letter' => $result->grade_letter,
                        'total_obtained' => (float) $result->total_obtained,
                        'total_full_marks' => (float) $result->total_full_marks,
                        'position' => $result->position,
                        'published_at' => $result->published_at?->toDateTimeString(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function eligibility(?array $result): array
    {
        if ($result === null || $result['gpa'] === null) {
            return [
                'key' => 'unavailable',
                'label' => __('Not available'),
                'tone' => 'muted',
                'reason' => __('No published result was found for this session.'),
            ];
        }

        $eligible = (float) $result['gpa'] >= self::PASS_GPA;

        return [
            'key' => $eligible ? 'eligible' : 'ineligible',
            'label' => $eligible ? __('Eligible') : __('Needs review'),
            'tone' => $eligible ? 'success' : 'danger',
            'reason' => $eligible
                ? __('Published GPA meets the promotion threshold.')
                : __('Published GPA is below the :gpa promotion threshold. Admin confirmation is required.', ['gpa' => number_format(self::PASS_GPA, 2)]),
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function historySummaries(array $studentIds, int $fromSessionId): array
    {
        $schoolId = (int) app(SchoolContext::class)->id();

        $attendance = DB::table('student_attendances')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $fromSessionId)
            ->whereIn('student_id', $studentIds)
            ->select('student_id', DB::raw('COUNT(*) as total'))
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $fees = DB::table('fee_invoices')
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $fromSessionId)
            ->whereIn('student_id', $studentIds)
            ->select(
                'student_id',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(paid_total), 0) as paid_total'),
            )
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $summary = [];

        foreach ($studentIds as $studentId) {
            $fee = $fees->get($studentId);

            $summary[$studentId] = [
                'attendance_count' => (int) ($attendance[$studentId] ?? 0),
                'fee_invoice_count' => (int) ($fee?->invoice_count ?? 0),
                'fee_paid_total' => (float) ($fee?->paid_total ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPlan(array $data, bool $lock): array
    {
        $schoolId = (int) app(SchoolContext::class)->id();
        $fromId = (int) $data['from_session_id'];
        $action = (string) $data['action'];
        $toId = filled($data['target_session_id'] ?? null) ? (int) $data['target_session_id'] : null;
        $targetClassId = filled($data['target_class_id'] ?? null) ? (int) $data['target_class_id'] : null;
        $targetSectionId = filled($data['target_section_id'] ?? null) ? (int) $data['target_section_id'] : null;
        $rollStrategy = (string) ($data['roll_strategy'] ?? 'auto');
        $manualRoll = filled($data['roll_no'] ?? null) ? (int) $data['roll_no'] : null;
        $studentIds = array_values(array_unique(array_map('intval', (array) ($data['student_ids'] ?? []))));

        $fromSession = AcademicSession::query()->find($fromId);
        $toSession = $toId !== null ? AcademicSession::query()->find($toId) : null;
        $targetClass = $targetClassId !== null ? SchoolClass::query()->find($targetClassId) : null;
        $targetSection = $targetSectionId !== null ? Section::query()->find($targetSectionId) : null;

        $globalErrors = [];

        if ($fromSession === null) {
            $globalErrors[] = __('Source academic session was not found.');
        }

        $needsTarget = in_array($action, ['promote', 'repeat'], true);

        if ($needsTarget) {
            if ($toId === null) {
                $globalErrors[] = __('Choose a target academic session.');
            } elseif ($toSession === null) {
                $globalErrors[] = __('Target academic session was not found.');
            } elseif ($toId === $fromId) {
                $globalErrors[] = __('Target session must be different from the current session.');
            }

            if ($targetClass === null) {
                $globalErrors[] = __('Choose a target class.');
            } elseif (! $targetClass->is_active) {
                $globalErrors[] = __('Target class is inactive.');
            }

            if ($targetSection === null) {
                $globalErrors[] = __('Choose a target section.');
            } elseif ($targetClass !== null && (int) $targetSection->class_id !== (int) $targetClass->id) {
                $globalErrors[] = __('Target section does not belong to the target class.');
            }
        }

        $enrollments = Enrollment::query()
            ->with(['student', 'schoolClass', 'section'])
            ->where('academic_session_id', $fromId)
            ->whereIn('student_id', $studentIds)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get()
            ->keyBy('student_id');

        $promotions = StudentPromotion::query()
            ->where('from_academic_session_id', $fromId)
            ->whereIn('student_id', $studentIds)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get()
            ->keyBy('student_id');

        $targetEnrollments = $needsTarget && $toId !== null && $toSession !== null
            ? Enrollment::query()
                ->where('academic_session_id', $toId)
                ->whereIn('student_id', $studentIds)
                ->when($lock, fn (Builder $query) => $query->lockForUpdate())
                ->get()
                ->keyBy('student_id')
            : collect();

        $results = $this->resultSummaries($studentIds, $fromId);
        $history = $this->historySummaries($studentIds, $fromId);

        $rollState = $needsTarget && $toId !== null && $toSession !== null && $targetClass !== null && $targetSection !== null
            && (int) $targetSection->class_id === (int) $targetClass->id
            ? $this->rollState($toId, (int) $targetClass->id, (int) $targetSection->id)
            : null;

        $rows = [];

        foreach ($studentIds as $studentId) {
            $enrollment = $enrollments->get($studentId);
            $student = $enrollment?->student;
            $errors = [];
            $warnings = [];
            $result = $results[$studentId] ?? null;
            $eligibility = $this->eligibility($result);

            if ($enrollment === null || $student === null) {
                $errors[] = __('No active enrollment was found in the source session.');
            }

            if ($promotions->has($studentId)) {
                $errors[] = __('This student has already been processed for the source session.');
            }

            if ($needsTarget && $targetEnrollments->has($studentId)) {
                $errors[] = __('This student already has an enrollment in the target session.');
            }

            if ($needsTarget && $enrollment !== null && $targetClass !== null
                && $action === 'promote'
                && (int) $targetClass->level < (int) ($enrollment->schoolClass?->level ?? 0)) {
                $errors[] = __('The target class is below the current class level.');
            }

            if ($needsTarget) {
                $errors = array_merge($errors, $globalErrors);
            }

            if ($eligibility['key'] === 'ineligible') {
                $warnings[] = $eligibility['reason'];
            } elseif ($eligibility['key'] === 'unavailable') {
                $warnings[] = __('Eligibility could not be verified because no published result exists.');
            }

            if ($action === 'hold') {
                $warnings[] = __('The current enrollment will remain active and no target enrollment will be created.');
            } elseif ($action === 'transfer_out') {
                $warnings[] = __('The current enrollment will be marked transferred and the student status will be set to transferred.');
            }

            $toRoll = null;

            if ($errors === [] && $rollState !== null) {
                $reservation = $this->reserveRoll(
                    $rollState,
                    $rollStrategy,
                    $enrollment?->roll_no !== null ? (int) $enrollment->roll_no : null,
                    $manualRoll,
                    $warnings,
                );

                if ($reservation['error'] !== null) {
                    $errors[] = $reservation['error'];
                } else {
                    $toRoll = $reservation['roll'];
                }
            }

            $resultSnapshot = $result === null ? null : array_merge($result, [
                'eligibility' => $eligibility['key'],
                'captured_at' => now()->toIso8601String(),
            ]);

            $rows[] = [
                'student_id' => $studentId,
                'student_name' => $student?->full_name ?? __('Unknown student'),
                'student_code' => $student?->student_id,
                'admission_no' => $student?->admission_no,
                'from_enrollment_id' => $enrollment?->id,
                'from_class_id' => $enrollment?->class_id,
                'from_class_name' => $enrollment?->schoolClass?->name,
                'from_class_level' => $enrollment?->schoolClass?->level,
                'from_section_id' => $enrollment?->section_id,
                'from_section_name' => $enrollment?->section?->name,
                'from_roll_no' => $enrollment?->roll_no,
                'to_class_id' => $needsTarget ? $targetClass?->id : null,
                'to_class_name' => $needsTarget ? $targetClass?->name : null,
                'to_section_id' => $needsTarget ? $targetSection?->id : null,
                'to_section_name' => $needsTarget ? $targetSection?->name : null,
                'to_roll_no' => $toRoll,
                'result' => $result,
                'result_snapshot' => $resultSnapshot,
                'eligibility' => $eligibility,
                'history' => $history[$studentId] ?? [
                    'attendance_count' => 0,
                    'fee_invoice_count' => 0,
                    'fee_paid_total' => 0.0,
                ],
                'status' => $errors === [] ? 'ready' : 'conflict',
                'errors' => array_values(array_unique($errors)),
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        $ready = collect($rows)->where('status', 'ready')->count();
        $conflicts = count($rows) - $ready;

        return [
            'action' => $action,
            'action_label' => StudentPromotion::actionLabels()[$action] ?? $action,
            'from_session_id' => $fromSession?->id,
            'from_session_name' => $fromSession?->name,
            'to_session_id' => $needsTarget ? $toSession?->id : null,
            'to_session_name' => $needsTarget ? $toSession?->name : null,
            'target_class_id' => $needsTarget ? $targetClass?->id : null,
            'target_class_name' => $needsTarget ? $targetClass?->name : null,
            'target_section_id' => $needsTarget ? $targetSection?->id : null,
            'target_section_name' => $needsTarget ? $targetSection?->name : null,
            'roll_strategy' => $rollStrategy,
            'summary' => [
                'total' => count($rows),
                'ready' => $ready,
                'conflicts' => $conflicts,
                'eligible' => collect($rows)->where('eligibility.key', 'eligible')->count(),
                'ineligible' => collect($rows)->where('eligibility.key', 'ineligible')->count(),
                'unavailable' => collect($rows)->where('eligibility.key', 'unavailable')->count(),
            ],
            'rows' => $rows,
            'conflict_count' => $conflicts,
            'ready_count' => $ready,
        ];
    }

    /**
     * @return array{used: array<int, bool>, next: int}
     */
    private function rollState(int $sessionId, int $classId, int $sectionId): array
    {
        $rolls = Enrollment::query()
            ->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->whereNotNull('roll_no')
            ->pluck('roll_no')
            ->map(fn ($roll): int => (int) $roll)
            ->filter(fn (int $roll): bool => $roll > 0)
            ->values();

        $used = [];

        foreach ($rolls as $roll) {
            $used[$roll] = true;
        }

        return [
            'used' => $used,
            'next' => ($rolls->max() ?? 0) + 1,
        ];
    }

    /**
     * @param  array{used: array<int, bool>, next: int}  $state
     * @param  list<string>  $warnings
     * @return array{roll: int|null, error: string|null}
     */
    private function reserveRoll(array &$state, string $strategy, ?int $fromRoll, ?int $manualRoll, array &$warnings): array
    {
        if ($strategy === 'manual') {
            if ($manualRoll === null || $manualRoll < 1) {
                return ['roll' => null, 'error' => __('Enter a valid roll number.')];
            }

            if (isset($state['used'][$manualRoll])) {
                return ['roll' => null, 'error' => __('Roll :roll is already used in the target class section.', ['roll' => $manualRoll])];
            }

            $state['used'][$manualRoll] = true;

            return ['roll' => $manualRoll, 'error' => null];
        }

        if ($strategy === 'keep' && $fromRoll !== null && $fromRoll > 0) {
            if (! isset($state['used'][$fromRoll])) {
                $state['used'][$fromRoll] = true;

                return ['roll' => $fromRoll, 'error' => null];
            }

            $warnings[] = __('Roll :roll is already used in the target section, so the next free roll was assigned.', ['roll' => $fromRoll]);
        }

        while (isset($state['used'][$state['next']])) {
            $state['next']++;
        }

        $roll = $state['next'];
        $state['used'][$roll] = true;
        $state['next'] = $roll + 1;

        return ['roll' => $roll, 'error' => null];
    }

    private function defaultTargetSession(Collection $sessions, ?AcademicSession $fromSession): ?AcademicSession
    {
        if ($fromSession === null) {
            return $sessions->firstWhere('is_current', false);
        }

        return $sessions
            ->reject(fn (AcademicSession $session): bool => $session->id === $fromSession->id)
            ->filter(fn (AcademicSession $session): bool => $session->start_date->greaterThan($fromSession->start_date))
            ->sortBy('start_date')
            ->first()
            ?? $sessions->first(fn (AcademicSession $session): bool => $session->id !== $fromSession->id);
    }

    /**
     * @param  LengthAwarePaginator<int, Enrollment>  $students
     * @param  array<string, array<string, mixed>>  $context
     * @return array<string, int>
     */
    private function pageSummary(LengthAwarePaginator $students, array $context): array
    {
        $summary = [
            'total' => $students->total(),
            'page' => $students->count(),
            'eligible' => 0,
            'ineligible' => 0,
            'unavailable' => 0,
            'processed' => 0,
        ];

        foreach ($students->getCollection() as $enrollment) {
            $row = $context[(int) $enrollment->student_id] ?? null;

            if ($row === null) {
                continue;
            }

            $summary[$row['eligibility']['key']] = ($summary[$row['eligibility']['key']] ?? 0) + 1;

            if ($row['promotion'] !== null) {
                $summary['processed']++;
            }
        }

        return $summary;
    }
}
