<?php

namespace App\Services;

use App\Models\GradeScale;
use App\Models\GradeScaleItem;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeService
{
    public const STANDARD_SCALE = 'Standard GPA-5';

    /**
     * @var list<array{grade_letter: string, min_percentage: float, max_percentage: float, grade_point: float, remarks: string}>
     */
    public const STANDARD_ITEMS = [
        ['grade_letter' => 'A+', 'min_percentage' => 80, 'max_percentage' => 100, 'grade_point' => 5.00, 'remarks' => 'Outstanding'],
        ['grade_letter' => 'A', 'min_percentage' => 70, 'max_percentage' => 79.99, 'grade_point' => 4.00, 'remarks' => 'Excellent'],
        ['grade_letter' => 'A-', 'min_percentage' => 60, 'max_percentage' => 69.99, 'grade_point' => 3.50, 'remarks' => 'Very good'],
        ['grade_letter' => 'B', 'min_percentage' => 50, 'max_percentage' => 59.99, 'grade_point' => 3.00, 'remarks' => 'Good'],
        ['grade_letter' => 'C', 'min_percentage' => 40, 'max_percentage' => 49.99, 'grade_point' => 2.00, 'remarks' => 'Satisfactory'],
        ['grade_letter' => 'D', 'min_percentage' => 33, 'max_percentage' => 39.99, 'grade_point' => 1.00, 'remarks' => 'Pass'],
        ['grade_letter' => 'F', 'min_percentage' => 0, 'max_percentage' => 32.99, 'grade_point' => 0.00, 'remarks' => 'Fail'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @var Collection<int, GradeScaleItem>|null */
    private ?Collection $cachedItems = null;

    public function ensureDefaultScale(): GradeScale
    {
        $scale = GradeScale::query()->default()->first()
            ?? GradeScale::query()->orderBy('id')->first();

        if ($scale === null) {
            $scale = GradeScale::query()->create([
                'name' => self::STANDARD_SCALE,
                'is_default' => true,
            ]);
        }

        if (! $scale->items()->exists()) {
            foreach (self::STANDARD_ITEMS as $item) {
                $scale->items()->create($item);
            }
        }

        if (! $scale->is_default) {
            GradeScale::query()->whereKeyNot($scale->id)->update(['is_default' => false]);
            $scale->forceFill(['is_default' => true])->save();
        }

        return $scale->load('items');
    }

    /**
     * @return Collection<int, GradeScaleItem>
     */
    public function items(): Collection
    {
        return $this->cachedItems ??= $this->ensureDefaultScale()->items;
    }

    /** The rows written by updateScale are the ones the next grade must use. */
    public function forgetItems(): void
    {
        $this->cachedItems = null;
    }

    /**
     * @param  Collection<int, GradeScaleItem>|null  $items
     * @return array{letter: string, point: float, remarks: ?string}
     */
    public function gradeFor(float $percentage, ?Collection $items = null): array
    {
        $items ??= $this->items();
        $percentage = round(max(0, min(100, $percentage)), 2);

        $match = $items->first(fn (GradeScaleItem $item): bool => $percentage >= (float) $item->min_percentage
            && $percentage <= (float) $item->max_percentage);

        if ($match === null) {
            $lowest = $items->sortBy('min_percentage')->first();

            return ['letter' => (string) $lowest?->grade_letter, 'point' => (float) ($lowest?->grade_point ?? 0), 'remarks' => $lowest?->remarks];
        }

        return [
            'letter' => $match->grade_letter,
            'point' => (float) $match->grade_point,
            'remarks' => $match->remarks,
        ];
    }

    public function percentage(float $obtained, float $full): float
    {
        return $full > 0 ? round(($obtained / $full) * 100, 2) : 0.0;
    }

    /**
     * The overall letter for a GPA is read off the configured scale: the
     * highest band whose grade point the student has reached. Nothing here
     * assumes the standard GPA-5 numbers; if the school renumbers the bands,
     * the overall letter follows.
     *
     * @param  Collection<int, GradeScaleItem>|null  $items
     */
    public function overallLetter(float $gpa, ?Collection $items = null): string
    {
        $items ??= $this->items();

        $match = $items
            ->sortByDesc('grade_point')
            ->first(fn (GradeScaleItem $item): bool => $gpa >= (float) $item->grade_point);

        return $match?->grade_letter ?? (string) $items->sortBy('grade_point')->first()?->grade_letter;
    }

    /**
     * A band that carries no grade point is a fail. The letter itself is never
     * the rule, because the school names its own bands.
     *
     * @param  Collection<int, GradeScaleItem>|null  $items
     */
    public function isFailing(?string $letter, ?Collection $items = null): bool
    {
        if ($letter === null || $letter === '') {
            return false;
        }

        $items ??= $this->items();

        $band = $items->first(fn (GradeScaleItem $item): bool => $item->grade_letter === $letter);

        return $band !== null && (float) $band->grade_point <= 0;
    }

    /**
     * @param  list<array{grade_letter?: mixed, min_percentage?: mixed, max_percentage?: mixed, grade_point?: mixed, remarks?: mixed}>  $rows
     * @return Collection<int, GradeScaleItem>
     */
    public function updateScale(User $actor, array $rows): Collection
    {
        $normalized = collect($rows)
            ->map(fn (array $row): array => [
                'grade_letter' => strtoupper(trim((string) ($row['grade_letter'] ?? ''))),
                'min_percentage' => round((float) ($row['min_percentage'] ?? 0), 2),
                'max_percentage' => round((float) ($row['max_percentage'] ?? 0), 2),
                'grade_point' => round((float) ($row['grade_point'] ?? 0), 2),
                'remarks' => filled($row['remarks'] ?? null) ? trim((string) $row['remarks']) : null,
            ])
            ->sortBy('min_percentage')
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one grade range.'),
            ]);
        }

        $letters = $normalized->pluck('grade_letter');
        if ($letters->contains('') || $letters->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('Every grade needs a unique letter.'),
            ]);
        }

        $previousMax = null;

        foreach ($normalized as $index => $row) {
            if ($row['max_percentage'] < $row['min_percentage']) {
                throw ValidationException::withMessages([
                    'items' => __('The maximum percentage must be greater than the minimum for :grade.', ['grade' => $row['grade_letter']]),
                ]);
            }

            if ($row['min_percentage'] < 0 || $row['max_percentage'] > 100) {
                throw ValidationException::withMessages([
                    'items' => __('Grade ranges must stay between 0 and 100 percent.'),
                ]);
            }

            if ($row['grade_point'] < 0 || $row['grade_point'] > 5) {
                throw ValidationException::withMessages([
                    'items' => __('Grade points must stay between 0.00 and 5.00.'),
                ]);
            }

            if ($index === 0 && $row['min_percentage'] != 0.0) {
                throw ValidationException::withMessages([
                    'items' => __('The lowest grade range must start at 0 percent.'),
                ]);
            }

            if ($previousMax !== null && abs($row['min_percentage'] - ($previousMax + 0.01)) > 0.001) {
                throw ValidationException::withMessages([
                    'items' => __('Grade ranges must be continuous and must not overlap.'),
                ]);
            }

            $previousMax = $row['max_percentage'];
        }

        if ($previousMax === null || abs($previousMax - 100.0) > 0.001) {
            throw ValidationException::withMessages([
                'items' => __('The highest grade range must end at 100 percent.'),
            ]);
        }

        $updated = DB::transaction(function () use ($actor, $normalized): Collection {
            $scale = $this->ensureDefaultScale();
            $old = $scale->items()->get(['grade_letter', 'min_percentage', 'max_percentage', 'grade_point'])->toArray();

            $scale->items()->delete();

            foreach ($normalized as $row) {
                $scale->items()->create($row);
            }

            $this->audit->log(
                event: 'grade_scale.updated',
                auditable: $scale,
                description: __('Grading scale was updated'),
                oldValues: ['items' => $old],
                newValues: ['items' => $normalized->all()],
                actor: $actor,
            );

            return $scale->items()->get();
        });

        $this->forgetItems();

        return $updated;
    }
}
