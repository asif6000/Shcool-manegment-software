<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        if ($request->hasSession()) {
            $locale = $request->session()->get('locale');
        }

        $user = $request->user();
        $locale ??= $user?->locale;
        $locale ??= config('app.locale');

        if (! in_array($locale, (array) config('school.locales'), true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
