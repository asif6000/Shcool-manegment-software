<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationResource;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['sometimes', 'boolean'],
            'category' => ['sometimes', 'string', Rule::in(NotificationPreference::CATEGORIES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $request->user()->notifications();

        if ((bool) ($validated['unread'] ?? false)) {
            $query->whereNull('read_at');
        }

        if (isset($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $notifications = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return ApiResponse::paginated(
            NotificationResource::collection($notifications),
            __('Notifications retrieved.'),
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'count' => $request->user()->unreadNotifications()->count(),
        ], __('Unread notification count.'));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return ApiResponse::success(
            new NotificationResource($record),
            __('Notification marked as read.'),
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success([
            'marked' => $count,
            'count' => 0,
        ], __('All notifications marked as read.'));
    }

    public function preferences(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'categories' => NotificationPreference::CATEGORIES,
            'channels' => NotificationPreference::CHANNELS,
            'preferences' => $this->notifications->preferencesFor($request->user()),
        ], __('Notification preferences.'));
    }

    public function updatePreferences(
        UpdateNotificationPreferencesRequest $request,
    ): JsonResponse {
        $this->notifications->updatePreferences(
            $request->user(),
            array_values($request->validated('preferences')),
        );

        return ApiResponse::success([
            'preferences' => $this->notifications->preferencesFor($request->user()),
        ], __('Notification preferences updated.'));
    }
}
