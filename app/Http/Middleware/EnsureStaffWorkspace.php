<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsToOwnWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffWorkspace
{
    use RedirectsToOwnWorkspace;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasAnyRole(config('school.workspace_roles'))) {
            return $this->denyWorkspace($request, __('This area is for school staff accounts.'));
        }

        return $next($request);
    }
}
