<?php

namespace App\Services;

use App\Models\SalaryStructure;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryService
{
    private const MONEY_FIELDS = [
        'basic_salary',
        'house_allowance',
        'transport_allowance',
        'medical_allowance',
        'other_allowance',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStructure(User $actor, array $data): SalaryStructure
    {
        $this->guardSingleOwner($data);

        return DB::transaction(function () use ($actor, $data): SalaryStructure {
            $attributes = $this->attributes($data);
            $structure = SalaryStructure::query()->create($attributes);

            if ($structure->is_active) {
                $this->deactivateOthers($structure);
            }

            $this->audit->log(
                event: 'salary_structure.created',
                auditable: $structure,
                description: __('Salary structure for :name was created', ['name' => $structure->staffName()]),
                newValues: [
                    'basic_salary' => $structure->basic_salary,
                    'gross_salary' => $structure->gross_salary,
                    'effective_from' => $structure->effective_from->toDateString(),
                ],
                actor: $actor,
                schoolId: (int) $structure->school_id,
            );

            return $structure;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStructure(User $actor, SalaryStructure $structure, array $data): SalaryStructure
    {
        $this->guardSingleOwner($data);

        return DB::transaction(function () use ($actor, $structure, $data): SalaryStructure {
            $old = $structure->only(['basic_salary', 'gross_salary', 'effective_from', 'is_active']);

            $structure->update($this->attributes($data));

            if ($structure->is_active) {
                $this->deactivateOthers($structure);
            }

            $this->audit->log(
                event: 'salary_structure.updated',
                auditable: $structure,
                description: __('Salary structure for :name was updated', ['name' => $structure->staffName()]),
                oldValues: $old,
                newValues: $structure->only(['basic_salary', 'gross_salary', 'effective_from', 'is_active']),
                actor: $actor,
                schoolId: (int) $structure->school_id,
            );

            return $structure->refresh();
        });
    }

    public function deleteStructure(User $actor, SalaryStructure $structure): void
    {
        $used = DB::table('salary_payments')
            ->where('salary_structure_id', $structure->id)
            ->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'structure' => __('This structure is referenced by salary payments and cannot be deleted.'),
            ]);
        }

        // Payroll that has already been paid keeps its own copy of the amounts,
        // but the structure behind a live payment stays too, so the reference is
        // never quietly dropped.
        $this->deletes->delete($structure, [
            'label' => __('salary structure'),
            'description' => __('Salary structure #:id was permanently deleted.', ['id' => $structure->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, [
            'teacher_id', 'employee_id', 'effective_from', 'effective_to', 'is_active',
            ...self::MONEY_FIELDS,
        ]);

        foreach (self::MONEY_FIELDS as $field) {
            $attributes[$field] = round((float) ($attributes[$field] ?? 0), 2);
        }

        $attributes['gross_salary'] = round(array_sum(array_map(
            fn (string $field): float => (float) $attributes[$field],
            self::MONEY_FIELDS,
        )), 2);

        $attributes['is_active'] = (bool) ($attributes['is_active'] ?? true);
        $attributes['effective_to'] = filled($attributes['effective_to'] ?? null)
            ? $attributes['effective_to']
            : null;

        return $attributes;
    }

    private function deactivateOthers(SalaryStructure $structure): void
    {
        $cutoff = Carbon::parse($structure->effective_from)->subDay()->toDateString();

        SalaryStructure::query()
            ->whereKeyNot($structure->id)
            ->where('is_active', true)
            ->when(
                $structure->teacher_id !== null,
                fn ($query) => $query->where('teacher_id', $structure->teacher_id),
                fn ($query) => $query->where('employee_id', $structure->employee_id),
            )
            ->update([
                'is_active' => false,
                'effective_to' => $cutoff,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardSingleOwner(array $data): void
    {
        $hasTeacher = filled($data['teacher_id'] ?? null);
        $hasEmployee = filled($data['employee_id'] ?? null);

        if ($hasTeacher === $hasEmployee) {
            throw ValidationException::withMessages([
                'teacher_id' => __('Choose exactly one teacher or staff member.'),
            ]);
        }
    }
}
