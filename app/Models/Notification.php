<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;

/**
 * The application's database notification model.
 *
 * Extends Laravel's model so `$user->notifications()` keeps returning the
 * framework relation while exposing the notification metadata columns.
 */
class Notification extends DatabaseNotification
{
    protected static string $collectionClass = DatabaseNotificationCollection::class;

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'category' => 'string',
    ];

    public function pushDeliveryLogs()
    {
        return $this->hasMany(PushDeliveryLog::class, 'notification_id');
    }

    public function title(): string
    {
        return (string) ($this->data['title'] ?? __('Notification'));
    }

    public function message(): string
    {
        return (string) ($this->data['message'] ?? '');
    }

    public function url(): ?string
    {
        $url = $this->data['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function icon(): string
    {
        return (string) ($this->data['icon'] ?? 'bi-bell');
    }

    /**
     * @return array<string, mixed>
     */
    public function webPushPayload(): array
    {
        $pushTitle = $this->data['push_title'] ?? null;
        $pushBody = $this->data['push_body'] ?? null;

        return [
            'title' => is_string($pushTitle) && $pushTitle !== '' ? $pushTitle : $this->title(),
            'body' => is_string($pushBody) && $pushBody !== '' ? $pushBody : $this->message(),
            'url' => $this->url() ?? url('/notifications'),
            'icon' => $this->data['push_icon'] ?? asset('icons/drms-192.png'),
            'tag' => $this->id,
            'category' => $this->category,
        ];
    }
}
