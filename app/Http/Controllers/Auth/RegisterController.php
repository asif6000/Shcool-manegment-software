<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StudentMobileRegisterRequest;
use App\Services\AuthenticationService;
use App\Services\StudentMobileAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRouteName());
        }

        return view('auth.register');
    }

    /**
     * Create the student account and sign it straight in, so the admission
     * form is one click away. The account has the existing student role and
     * is created from a mobile number and password — no email required.
     */
    public function store(
        StudentMobileRegisterRequest $request,
        StudentMobileAccountService $accounts,
        AuthenticationService $authentication,
    ): RedirectResponse {
        $validated = $request->validated();

        $user = $accounts->register($validated);

        Auth::login($user);
        $request->session()->regenerate();
        $authentication->markLoggedIn($user);

        return redirect()
            ->intended(route('applicant.apply'))
            ->with('success', __('Your account is ready. Now fill in the admission form.'));
    }
}
