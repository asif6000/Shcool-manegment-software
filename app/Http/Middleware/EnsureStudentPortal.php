<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsToOwnWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPortal
{
    use RedirectsToOwnWorkspace;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasRole('student')) {
            return $this->denyWorkspace($request, __('This area is for student accounts.'));
        }

        if (! $user->hasStudentRecord()) {
            // Admission applicants hold the student role before a student record
            // exists, so send them to their application workspace instead of
            // signing them out of the session they just created.
            return redirect()
                ->route('applicant.dashboard')
                ->with('warning', __('Your admission is still pending, so the student portal is not open yet.'));
        }

        return $next($request);
    }
}
