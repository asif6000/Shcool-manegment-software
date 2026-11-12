<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function requirePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403, __('This action is unauthorized.'));
    }

    /**
     * Used where a screen belongs to one area of the system and several role
     * holders may reach it, so a stale permission row cannot open it.
     *
     * @param  list<string>  $permissions
     */
    protected function requireAnyPermission(array $permissions): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && collect($permissions)->contains(fn (string $permission): bool => $user->can($permission)),
            403,
            __('This action is unauthorized.'),
        );
    }

    protected function dateOrToday(?string $value): Carbon
    {
        return is_string($value) && Carbon::hasFormat($value, 'Y-m-d')
            ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay()
            : now()->startOfDay();
    }

    /**
     * A teacher's dashboard asks for one screen of a module at a time instead of
     * the whole page, so the same controller can answer both. The permission and
     * scope checks run either way; only the template differs.
     */
    protected function wantsFragment(Request $request): bool
    {
        return $request->boolean('fragment');
    }

    /**
     * Renders either the standalone module page or, for a dashboard request, the
     * same screen inside its panel.
     *
     * @param  array<string, mixed>  $data
     */
    protected function pageView(Request $request, string $page, string $panel, array $data): View
    {
        return $this->wantsFragment($request)
            ? view('teacher.panels.'.$panel, $data + ['panel' => true])
            : view($page, $data);
    }

    /**
     * Writes that come from the teacher dashboard return to the panel that made
     * them instead of opening the module page. The panel name and the reopen URL
     * only ever land in a query string; the dashboard whitelists the panel and
     * fetches the reopen path through the same permission-checked routes.
     *
     * @param  array<string, mixed>  $params
     */
    protected function saved(Request $request, string $route, array $params = [], ?string $message = null): RedirectResponse
    {
        $target = route($route, $params);

        $redirect = $request->input('workspace') === 'teacher'
            ? redirect()->route('dashboard', ['panel' => $request->input('panel'), 'reopen' => $target])
            : redirect()->to($target);

        return $message === null ? $redirect : $redirect->with('success', $message);
    }
}
