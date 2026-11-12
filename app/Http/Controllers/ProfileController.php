<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->loadMissing('school'),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->users->updateProfile(
            $request->user(),
            $request->validated(),
            $request->file('avatar'),
        );

        return back()->with('success', __('Your profile has been updated.'));
    }

    public function password(Request $request): View
    {
        return view('profile.password', [
            'user' => $request->user(),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->users->updatePassword($request->user(), $request->string('password')->toString());

        return redirect()
            ->route('dashboard')
            ->with('success', __('Your password has been changed. Other devices have been signed out.'));
    }
}
