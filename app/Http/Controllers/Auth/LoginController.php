<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRouteName());
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticationService $authentication): RedirectResponse
    {
        $role = $request->string('role')->toString();
        $password = $request->string('password')->toString();

        // Role-based authentication: Admin uses email, others use mobile
        if ($role === 'admin') {
            $identifier = $request->string('email')->toString();
            $authentication->ensureNotRateLimited($identifier, $request->ip());

            $user = $authentication->verifyCredentials($identifier, $password);

            if ($user === null || ! $user->hasRole('school_admin')) {
                $authentication->recordFailure($identifier, $request->ip());

                throw ValidationException::withMessages([
                    'email' => __('Invalid credentials or account not authorized for admin access.'),
                ]);
            }

            $authentication->clearFailures($identifier, $request->ip());
        } else {
            $mobile = AuthenticationService::normaliseMobile($request->string('mobile_number')->toString());
            $authentication->ensureNotRateLimitedByMobile($mobile, $request->ip());

            $user = $authentication->verifyCredentialsByMobile($mobile, $password);

            if ($user === null) {
                $authentication->recordFailureByMobile($mobile, $request->ip());

                throw ValidationException::withMessages([
                    'mobile_number' => __('The mobile number or password is incorrect.'),
                ]);
            }

            // Verify role matches selected role
            if (! $user->hasRole($role)) {
                $authentication->recordFailureByMobile($mobile, $request->ip());

                throw ValidationException::withMessages([
                    'mobile_number' => __('This account is not authorized for the selected role.'),
                ]);
            }

            $authentication->clearFailuresByMobile($mobile, $request->ip());
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $authentication->markLoggedIn($user);

        if ($user->must_change_password) {
            return redirect()
                ->route($user->passwordChangeRouteName())
                ->with('warning', __('Please choose a new password before continuing.'));
        }

        return redirect()
            ->intended(route($user->homeRouteName()))
            ->with('success', __('Welcome back, :name.', ['name' => $user->name]));
    }

    public function destroy(Request $request, AuthenticationService $authentication): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $authentication->markLoggedOut($user);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', __('You have signed out safely.'));
    }
}
