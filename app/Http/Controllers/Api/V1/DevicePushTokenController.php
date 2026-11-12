<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreDeviceTokenRequest;
use App\Models\DevicePushToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $token = trim((string) $validated['token']);
        $hash = DevicePushToken::hashToken($token);
        $user = $request->user();

        $existing = DevicePushToken::query()->where('token_hash', $hash)->first();

        if ($existing === null
            && $user->devicePushTokens()->where('is_active', true)->count() >= 10) {
            return ApiResponse::error(
                __('You have reached the maximum number of registered devices.'),
                422,
            );
        }

        // A device token identifies one installation. If it was previously
        // registered to another account on the same device, move it to the
        // authenticated account instead of creating a duplicate row.
        $device = DevicePushToken::query()->updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'token' => $token,
                'device_name' => $validated['device_name'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
                'revoked_at' => null,
            ],
        );

        return ApiResponse::success([
            'id' => $device->id,
            'platform' => $device->platform,
            'is_active' => $device->is_active,
        ], __('Device registered for notifications.'), $existing === null ? 201 : 200);
    }

    public function destroy(Request $request, string $device): JsonResponse
    {
        $record = $request->user()->devicePushTokens()
            ->whereKey((int) $device)
            ->firstOrFail();

        $record->forceFill([
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();

        return ApiResponse::success([
            'id' => $record->id,
            'is_active' => false,
        ], __('Device unregistered.'));
    }
}
