<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($auditLogger): void {
                DB::transaction(function () use ($user, $password, $auditLogger): void {
                    $user->forceFill([
                        'password' => $password,
                        'must_change_password' => false,
                        'remember_token' => Str::random(60),
                    ])->save();

                    $user->tokens()->delete();
                    DB::table('sessions')->where('user_id', $user->id)->delete();

                    $auditLogger->log(
                        event: 'password_reset',
                        auditable: $user,
                        description: __(':name reset their password using a reset link', ['name' => $user->name]),
                        actor: $user,
                        schoolId: $user->school_id,
                    );
                });
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()
            ->route('login')
            ->with('success', __('Your password has been reset. You can now sign in.'));
    }
}
