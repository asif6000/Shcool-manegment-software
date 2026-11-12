<?php

namespace App\Http\Middleware;

use App\Services\TeachingScope;
use App\Support\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSchoolContext
{
    /**
     * EduNest serves a single school. The workspace is resolved from the
     * authenticated account on the server; clients can never choose it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(SchoolContext::class);
        $user = $request->user();

        // The teaching scope caches the timetable for one request only.
        app(TeachingScope::class)->reset();

        if ($user === null) {
            $context->clear();

            return $next($request);
        }

        if ($user->school_id === null) {
            abort(403, __('Your account is not linked to a school.'));
        }

        $context->set((int) $user->school_id);

        return $next($request);
    }
}
