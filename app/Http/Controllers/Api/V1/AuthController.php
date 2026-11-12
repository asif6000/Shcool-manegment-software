<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(ApiLoginRequest $request, AuthenticationService $authentication): JsonResponse
    {
        $email = $request->string('email')->toString();

        $authentication->ensureNotRateLimited($email, $request->ip());

        $user = $authentication->verifyCredentials($email, $request->string('password')->toString());

        if ($user === null) {
            $authentication->recordFailure($email, $request->ip());

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $authentication->clearFailures($email, $request->ip());
        $authentication->markLoggedIn($user);

        $token = $user->createToken(
            $request->string('device_name', 'api-client')->toString(),
            ['*'],
        );

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load(['roles', 'school'])),
        ], __('Signed in successfully.'));
    }

    public function logout(Request $request, AuthenticationService $authentication): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        // A cookie session resolves to a TransientToken, which has no row to
        // delete; only a personal access token is revoked here.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $authentication->markLoggedOut($user);

        Auth::forgetGuards();

        return ApiResponse::success(null, __('Signed out successfully.'));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load(['roles', 'school'])),
            __('Authenticated user.'),
        );
    }
}
