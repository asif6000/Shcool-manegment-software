<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeStructureService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function toggle(User $actor, FeeStructure $structure): FeeStructure
    {
        $structure->update(['is_active' => ! $structure->is_active]);

        $this->audit->log(
            event: 'fee_structure.toggled',
            auditable: $structure,
            description: __('Fee :fee for class :class was :state', [
                'fee' => $structure->feeType?->name ?? __('Fee'),
                'class' => $structure->schoolClass?->name ?? (string) $structure->class_id,
                'state' => $structure->is_active ? __('activated') : __('deactivated'),
            ]),
            oldValues: ['is_active' => ! $structure->is_active],
            newValues: ['is_active' => $structure->is_active],
            actor: $actor,
        );

        return $structure;
    }

    /**
     * @return array{copied: int, skipped: int}
     */
    public function copySession(User $actor, int $fromSessionId, int $toSessionId): array
    {
        $schoolId = app(SchoolContext::class)->id();

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'from_session_id' => __('A school context is required.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $schoolId, $fromSessionId, $toSessionId): array {
            $source = FeeStructure::query()
                ->where('academic_session_id', $fromSessionId)
                ->get();

            if ($source->isEmpty()) {
                throw ValidationException::withMessages([
                    'from_session_id' => __('There are no class fees set in that session yet.'),
                ]);
            }

            $existing = FeeStructure::query()
                ->where('academic_session_id', $toSessionId)
                ->get(['class_id', 'fee_type_id'])
                ->map(fn (FeeStructure $row): string => $row->class_id.':'.$row->fee_type_id)
                ->all();

            $copied = 0;
            $skipped = 0;

            foreach ($source as $structure) {
                $key = $structure->class_id.':'.$structure->fee_type_id;

                if (in_array($key, $existing, true)) {
                    $skipped++;

                    continue;
                }

                FeeStructure::query()->create([
                    'academic_session_id' => $toSessionId,
                    'class_id' => $structure->class_id,
                    'fee_type_id' => $structure->fee_type_id,
                    'name' => $structure->name,
                    'amount' => $structure->amount,
                    'due_day' => $structure->due_day,
                    'starts_on' => $structure->starts_on,
                    'ends_on' => $structure->ends_on,
                    'is_active' => $structure->is_active,
                ]);

                $existing[] = $key;
                $copied++;
            }

            $this->audit->log(
                event: 'fee_structure.copied',
                auditable: null,
                description: __(':copied class fees were copied into session :to', [
                    'copied' => $copied,
                    'to' => $toSessionId,
                ]),
                newValues: [
                    'from_session_id' => $fromSessionId,
                    'to_session_id' => $toSessionId,
                    'copied' => $copied,
                    'skipped' => $skipped,
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return ['copied' => $copied, 'skipped' => $skipped];
        });
    }
}
