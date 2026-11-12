<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Student;
use Illuminate\Http\Request;

/**
 * Every portal screen resolves the student from the signed-in account, never
 * from a route parameter, so a changed URL id cannot reach another record.
 */
trait ResolvesPortalStudent
{
    private function student(Request $request): Student
    {
        return $request->user()->student()->firstOrFail();
    }
}
