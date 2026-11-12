<?php

namespace App\Http\Controllers\Concerns;

use App\Models\School;
use App\Support\SchoolContext;
use Closure;

trait FindsPublicSchool
{
    /**
     * The public pages serve the single active school installation.
     */
    protected function publicSchool(): ?School
    {
        return School::query()->where('is_active', true)->first()
            ?? School::query()->first();
    }

    /**
     * Run a callback against the public school's scoped records and always
     * restore the previous context.
     */
    protected function inPublicSchool(?School $school, Closure $callback): mixed
    {
        if ($school === null) {
            return $callback();
        }

        return app(SchoolContext::class)->run($school->id, $callback);
    }
}
