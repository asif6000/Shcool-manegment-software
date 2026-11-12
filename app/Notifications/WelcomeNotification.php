<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ?string $schoolName = null) {}

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
            'title' => __('Welcome to :app', ['app' => config('app.name')]),
            'message' => $this->schoolName !== null
                ? __('Your account for :school is ready. Please review your profile and change your temporary password.', ['school' => $this->schoolName])
                : __('Your administrator account is ready. Please review your security settings.'),
            'icon' => 'bi-shield-check',
            'url' => route('profile.edit'),
        ];
    }
}
