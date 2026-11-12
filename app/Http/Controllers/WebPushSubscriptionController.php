<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notifications\SubscribePushRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebPushSubscriptionController extends Controller
{
    public function store(SubscribePushRequest $request): JsonResponse
    {
        $user = $request->user();
        $endpoint = trim((string) $request->validated('endpoint'));
        $hash = PushSubscription::endpointHash($endpoint);

        $existing = PushSubscription::query()->where('endpoint_hash', $hash)->first();
        $max = max(1, (int) config('webpush.max_subscriptions_per_user', 10));

        // A browser endpoint belongs to whoever registered it. Another account
        // may only take it over when the same browser re-subscribes with the
        // same key material, never by guessing the endpoint alone.
        if ($existing !== null
            && (int) $existing->user_id !== (int) $user->id
            && ($existing->public_key !== (string) $request->validated('keys.p256dh')
                || $existing->auth_token !== (string) $request->validated('keys.auth'))) {
            return response()->json([
                'message' => __('This browser is already registered to another account.'),
            ], 409);
        }

        if ($existing === null
            && $user->pushSubscriptions()->where('is_active', true)->count() >= $max) {
            return response()->json([
                'message' => __('You have reached the maximum number of push-enabled browsers. Remove one before adding another.'),
            ], 422);
        }

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'public_key' => (string) $request->validated('keys.p256dh'),
                'auth_token' => (string) $request->validated('keys.auth'),
                'content_encoding' => (string) ($request->validated('content_encoding') ?: 'aes128gcm'),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'is_active' => true,
            ],
        );

        return response()->json([
            'subscribed' => true,
            'id' => $subscription->id,
        ], $existing === null ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $subscription = $request->user()->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::endpointHash(trim($validated['endpoint'])))
            ->first();

        // Return success regardless of ownership so the endpoint cannot be
        // probed. Another user's subscription is never changed.
        $subscription?->deactivate();

        return response()->json(['subscribed' => false]);
    }
}
