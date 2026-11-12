<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NumberSequence
{
    /**
     * Generate the next human-readable document number for a school and year.
     * Callers must run inside a transaction that locks the school row so two
     * concurrent requests cannot reserve the same sequence.
     *
     * @param  class-string<Model>  $model
     */
    public function next(string $model, string $column, string $prefix, int $year, int $schoolId): string
    {
        $base = $prefix.'-'.$year.'-';
        $query = $model::query()->where('school_id', $schoolId)->where($column, 'like', $base.'%');

        if (method_exists($model, 'withTrashed')) {
            /** @var Builder $query */
            $query = $query->withTrashed();
        }

        $last = $query->orderByDesc('id')->value($column);
        $next = 1;

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return $base.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
