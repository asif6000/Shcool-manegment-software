<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return ApiResponse::error(
                __('Please choose a new password before continuing.'),
                403,
                [],
                'password_change_required'
            );
        }

        $passwordRoute = $user->passwordChangeRouteName();

        $allowed = $request->routeIs('logout')
            || $request->routeIs('locale.*')
            || $request->routeIs('student.locale.*')
            || $request->routeIs('applicant.locale.*')
            || $request->routeIs(Str::beforeLast($passwordRoute, '.').'.*');

        if ($allowed) {
            return $next($request);
        }

        return redirect()
            ->route($passwordRoute)
            ->with('warning', __('Please choose a new password before continuing.'));
    }
}
