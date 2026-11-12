<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StudentMobileLoginRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Handles student login exclusively via mobile number + password. Registration
 * lives on the public register form, which creates the same student account.
 */
class StudentMobileAuthController extends Controller
{
    // ─── Login ───────────────────────────────────────────────────────────────

    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRouteName());
        }

        return view('student.auth.login');
    }

    public function login(
        StudentMobileLoginRequest $request,
        AuthenticationService $authentication,
    ): RedirectResponse {
        $mobile = AuthenticationService::normaliseMobile($request->string('mobile_number')->toString());

        $authentication->ensureNotRateLimitedByMobile($mobile, $request->ip());

        $user = $authentication->verifyCredentialsByMobile(
            $mobile,
            $request->string('password')->toString(),
        );

        if ($user === null) {
            $authentication->recordFailureByMobile($mobile, $request->ip());

            throw ValidationException::withMessages([
                'mobile_number' => __('The mobile number or password is incorrect.'),
            ]);
        }

        // Ensure this account has the student role
        if (! $user->hasRole('student')) {
            throw ValidationException::withMessages([
                'mobile_number' => __('This portal is for students only. Use the main sign-in page.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $authentication->clearFailuresByMobile($mobile, $request->ip());
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
}
