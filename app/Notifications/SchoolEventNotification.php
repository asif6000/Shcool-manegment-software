<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class SchoolEventNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly string $icon = 'bi-bell',
        public readonly string $category = 'general',
        public readonly ?string $dedupeKey = null,
        public readonly ?string $pushTitle = null,
        public readonly ?string $pushBody = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'url' => $this->url,
            'category' => $this->category,
            // Kept separately so the queued push job can use a lock-screen-safe
            // variant without changing what the in-app inbox shows.
            'push_title' => $this->pushTitle,
            'push_body' => $this->pushBody,
        ];
    }

    /**
     * Lock-screen safe payload: no student names, marks or financial details.
     *
     * @return array<string, mixed>
     */
    public function toWebPush(): array
    {
        return [
            'title' => $this->pushTitle ?? $this->title,
            'body' => $this->pushBody ?? Str::limit($this->message, 180),
            'url' => $this->url ?? route('notifications.index'),
            'icon' => asset('icons/drms-192.png'),
            'tag' => $this->dedupeKey ?? $this->id,
            'category' => $this->category,
        ];
    }
}
