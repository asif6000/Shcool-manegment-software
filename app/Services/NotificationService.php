<?php

namespace App\Services;

use App\Jobs\SendWebPushNotification;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\SchoolEventNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Persist an in-app notification and, when browser push is enabled, queue
     * the push delivery after the surrounding database transaction commits.
     */
    public function send(User $user, SchoolEventNotification $notification): ?Notification
    {
        if (! $user->is_active) {
            return null;
        }

        if ($notification->dedupeKey !== null
            && Notification::query()->where('dedupe_key', $notification->dedupeKey)->exists()) {
            return null;
        }

        try {
            /** @var Notification $record */
            $record = $user->notifications()->create([
                'id' => $notification->id ?? (string) Str::orderedUuid(),
                'type' => SchoolEventNotification::class,
                'data' => $notification->toDatabase($user),
                'category' => $notification->category,
                'dedupe_key' => $notification->dedupeKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request created the same deduplicated event first.
            return null;
        }

        if ((bool) config('webpush.enabled')) {
            SendWebPushNotification::dispatch($record->id, (int) $user->getKey())
                ->afterCommit()
                ->onQueue((string) config('webpush.queue', 'default'));
        }

        return $record;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function preferencesFor(User $user): array
    {
        $stored = $user->notificationPreferences()->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->category.'|'.$preference->channel);

        $matrix = [];

        foreach (NotificationPreference::CATEGORIES as $category) {
            foreach (NotificationPreference::CHANNELS as $channel) {
                $preference = $stored->get($category.'|'.$channel);
                $matrix[$category][$channel] = $preference?->enabled ?? true;
            }
        }

        return $matrix;
    }

    /**
     * @param  array<int, array{category: string, channel: string, enabled: bool}>  $items
     */
    public function updatePreferences(User $user, array $items): void
    {
        foreach ($items as $item) {
            $user->notificationPreferences()->updateOrCreate(
                [
                    'category' => $item['category'],
                    'channel' => $item['channel'],
                ],
                ['enabled' => (bool) $item['enabled']],
            );
        }
    }

    public function webPushEnabled(User $user, string $category): bool
    {
        $preference = $user->notificationPreferences()
            ->where('category', $category)
            ->where('channel', 'webpush')
            ->first();

        return $preference?->enabled ?? true;
    }
}
