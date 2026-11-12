<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsToOwnWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The applicant workspace is for student-role accounts only. Staff must review
 * applications in the admin panel, never through the applicant screens.
 */
class EnsureAdmissionApplicant
{
    use RedirectsToOwnWorkspace;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasRole('student')) {
            return $this->denyWorkspace($request, __('This area is for student accounts.'));
        }

        return $next($request);
    }
}
