<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notifications\WebUpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $isStudent = $user->hasRole('student');
        $unread = $user->unreadNotifications()->count();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(15)->withQueryString(),
            'unreadCount' => $unread,
            'preferences' => $this->notifications->preferencesFor($user),
            'categories' => NotificationPreference::CATEGORIES,
            'layout' => $isStudent ? 'layouts.student' : 'layouts.app',
            'shellUnreadCount' => $isStudent ? $unread : 0,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->title(),
                'message' => $notification->message(),
                'icon' => $notification->icon(),
                'url' => $notification->url(),
                'category' => $notification->category ?? 'general',
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
                'created_at_human' => $notification->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        $url = $this->safeUrl($record->data['url'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'url' => $url,
            ]);
        }

        return $this->notificationRedirect($url, route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'unread_count' => 0,
                'marked' => $count,
            ]);
        }

        return back()->with('success', __('All notifications have been marked as read.'));
    }

    public function updatePreferences(
        WebUpdateNotificationPreferencesRequest $request,
    ): RedirectResponse {
        $items = [];

        foreach ((array) $request->validated('preferences') as $category => $channels) {
            if (! in_array($category, NotificationPreference::CATEGORIES, true) || ! is_array($channels)) {
                continue;
            }

            foreach ($channels as $channel => $enabled) {
                if (! in_array($channel, NotificationPreference::CHANNELS, true)) {
                    continue;
                }

                $items[] = [
                    'category' => $category,
                    'channel' => $channel,
                    'enabled' => (bool) $enabled,
                ];
            }
        }

        $this->notifications->updatePreferences($request->user(), $items);

        return back()->with('success', __('Notification preferences updated.'));
    }

    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $base = url('/');

        if (str_starts_with($url, '/') || str_starts_with($url, $base)) {
            return $url;
        }

        return null;
    }

    private function notificationRedirect(?string $url, string $fallback): RedirectResponse
    {
        return redirect()->to($url ?? $fallback);
    }
}
