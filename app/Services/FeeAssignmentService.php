<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\FeeStudentAssignment;
use App\Models\FeeType;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns "this fee applies to these learners" into one row per student, so the
 * school can see the list before it is applied and so the database can refuse
 * a duplicate for the same student, fee type and session.
 */
class FeeAssignmentService
{
    public const SCOPES = ['all', 'class', 'students'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{rows: Collection<int, array<string, mixed>>, billable: int, duplicates: int, unpriced: int, total: float}
     */
    public function preview(User $actor, array $data): array
    {
        [$feeType, $sessionId, $enrollments] = $this->resolve($data);

        $structures = $this->structuresFor($feeType->id, $sessionId);
        $assigned = $this->alreadyAssigned($feeType->id, $sessionId, $enrollments->pluck('student_id')->all());

        $override = array_key_exists('amount', $data) && $data['amount'] !== null && $data['amount'] !== ''
            ? round((float) $data['amount'], 2)
            : null;

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($feeType, $structures, $assigned, $override, $data): array {
            $structure = $structures->get((int) $enrollment->class_id);
            $amount = $override ?? ($structure !== null ? round((float) $structure->amount, 2) : null);

            return [
                'student_id' => $enrollment->student_id,
                'name' => $enrollment->student?->full_name ?? __('Student'),
                'code' => $enrollment->student?->student_id,
                'class' => $enrollment->schoolClass?->name,
                'section' => $enrollment->section?->name,
                'roll' => $enrollment->roll_no,
                'enrollment_id' => $enrollment->id,
                'class_id' => $enrollment->class_id,
                'section_id' => $enrollment->section_id,
                'amount' => $amount,
                'source' => $override !== null ? __('Authorised amount') : ($structure !== null ? __('Class fee') : null),
                'duplicate' => in_array($enrollment->student_id, $assigned, true),
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'fee_type' => $feeType->name,
            ];
        })->values();

        $billable = $rows->filter(fn (array $row): bool => $row['amount'] !== null && ! $row['duplicate']);

        return [
            'rows' => $rows,
            'billable' => $billable->count(),
            'duplicates' => $rows->where('duplicate', true)->count(),
            'unpriced' => $rows->filter(fn (array $row): bool => $row['amount'] === null && ! $row['duplicate'])->count(),
            'total' => round((float) $billable->sum('amount'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{created: int, skipped: int, batch: string}
     */
    public function apply(User $actor, array $data): array
    {
        $preview = $this->preview($actor, $data);

        if ($preview['billable'] === 0) {
            throw ValidationException::withMessages([
                'scope' => __('There is no student left to assign. They are already on this fee, or no amount is configured for their class.'),
            ]);
        }

        if ($preview['unpriced'] > 0) {
            throw ValidationException::withMessages([
                'amount' => __(':count student(s) have no class fee for this type. Set a class fee or enter an authorised amount.', [
                    'count' => $preview['unpriced'],
                ]),
            ]);
        }

        $batch = (string) Str::uuid();
        $schoolId = app(SchoolContext::class)->id();
        [$feeType, $sessionId] = $this->resolveIds($data);

        $rows = $preview['rows']->filter(fn (array $row): bool => ! $row['duplicate'] && $row['amount'] !== null);

        $created = DB::transaction(function () use ($actor, $rows, $feeType, $sessionId, $batch, $data, $schoolId, $preview): int {
            $inserted = 0;

            foreach ($rows as $row) {
                try {
                    FeeStudentAssignment::query()->create([
                        'fee_type_id' => $feeType->id,
                        'academic_session_id' => $sessionId,
                        'student_id' => $row['student_id'],
                        'enrollment_id' => $row['enrollment_id'],
                        'class_id' => $row['class_id'],
                        'section_id' => $row['section_id'],
                        'amount' => $row['amount'],
                        'starts_on' => $row['starts_on'] ?: null,
                        'ends_on' => $row['ends_on'] ?: null,
                        'batch' => $batch,
                        'is_active' => true,
                        'created_by' => $actor->id,
                    ]);

                    $inserted++;
                } catch (UniqueConstraintViolationException) {
                    // Another administrator assigned the same fee to the same
                    // student while this form was open. Skipping is the correct
                    // answer; the fee must not be charged twice.
                }
            }

            $this->audit->log(
                event: 'fee_assignment.applied',
                auditable: null,
                description: __('Fee :fee was assigned to :count students', [
                    'fee' => $feeType->name,
                    'count' => $inserted,
                ]),
                newValues: [
                    'fee_type_id' => $feeType->id,
                    'academic_session_id' => $sessionId,
                    'scope' => $data['scope'],
                    'batch' => $batch,
                    'created' => $inserted,
                    'already_assigned' => $preview['duplicates'],
                    'amount_source' => isset($data['amount']) && $data['amount'] !== '' && $data['amount'] !== null
                        ? 'authorised_override'
                        : 'class_structure',
                    'total_billed_value' => round((float) $rows->sum('amount'), 2),
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $inserted;
        });

        return ['created' => $created, 'skipped' => $preview['duplicates'], 'batch' => $batch];
    }

    public function deactivate(User $actor, FeeStudentAssignment $assignment): FeeStudentAssignment
    {
        $assignment->update(['is_active' => false]);

        $this->audit->log(
            event: 'fee_assignment.deactivated',
            auditable: $assignment,
            description: __(':fee was removed from :student', [
                'fee' => $assignment->feeType?->name ?? __('Fee'),
                'student' => $assignment->student?->full_name ?? __('Student'),
            ]),
            oldValues: ['is_active' => true],
            newValues: ['is_active' => false],
            actor: $actor,
        );

        return $assignment;
    }

    /**
     * The active assignments that bill a student, keyed by fee type.
     *
     * @return Collection<int, FeeStudentAssignment>
     */
    public function forStudent(int $studentId, int $sessionId, ?Carbon $on = null): Collection
    {
        $on = $on ?? Carbon::today();

        return FeeStudentAssignment::query()
            ->with('feeType')
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->active()
            ->get()
            ->filter(fn (FeeStudentAssignment $assignment): bool => $assignment->coversDate($on)
                && $assignment->feeType?->is_active === true
                && $assignment->feeType?->coversDate($on));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: FeeType, 1: int, 2: Collection<int, Enrollment>}
     */
    private function resolve(array $data): array
    {
        [$feeType, $sessionId] = $this->resolveIds($data);
        $scope = (string) ($data['scope'] ?? 'class');

        $enrollments = Enrollment::query()
            ->with(['student', 'schoolClass', 'section'])
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->when($scope === 'class', function ($query) use ($data): void {
                $query->where('class_id', (int) $data['class_id'])
                    ->when(filled($data['section_id'] ?? null), fn ($inner) => $inner->where('section_id', (int) $data['section_id']));
            })
            ->when($scope === 'students', fn ($query) => $query->whereIn('student_id', array_map('intval', (array) $data['student_ids'])))
            ->orderBy('class_id')
            ->orderBy('roll_no')
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null)
            ->values();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'scope' => __('No active student matches that selection.'),
            ]);
        }

        return [$feeType, $sessionId, $enrollments];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: FeeType, 1: int}
     */
    private function resolveIds(array $data): array
    {
        $feeType = FeeType::query()->findOrFail((int) $data['fee_type_id']);

        if (! $feeType->is_active) {
            throw ValidationException::withMessages([
                'fee_type_id' => __('That fee type is deactivated. Activate it before assigning it.'),
            ]);
        }

        return [$feeType, (int) $data['academic_session_id']];
    }

    /**
     * @return Collection<int, float>
     */
    private function structuresFor(int $feeTypeId, int $sessionId): Collection
    {
        return FeeStructure::query()
            ->where('fee_type_id', $feeTypeId)
            ->where('academic_session_id', $sessionId)
            ->where('is_active', true)
            ->get(['class_id', 'amount'])
            ->mapWithKeys(fn (FeeStructure $structure): array => [(int) $structure->class_id => (float) $structure->amount]);
    }

    /**
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    private function alreadyAssigned(int $feeTypeId, int $sessionId, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return FeeStudentAssignment::query()
            ->where('fee_type_id', $feeTypeId)
            ->where('academic_session_id', $sessionId)
            ->whereIn('student_id', $studentIds)
            ->active()
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
