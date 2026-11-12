<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account that lands in the wrong workspace is sent to its own home rather
 * than a bare 403 page, which dead-ends bookmarks and stale sign-in redirects.
 * Programmatic callers still get the explicit 403.
 */
trait RedirectsToOwnWorkspace
{
    protected function denyWorkspace(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()
            ->route($request->user()->homeRouteName())
            ->with('warning', $message);
    }
}
