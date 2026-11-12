<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browser push (Web Push / VAPID)
    |--------------------------------------------------------------------------
    |
    | Disabled by default so installing the application never starts sending
    | push messages. Enable it after generating VAPID keys with
    | `php artisan webpush:vapid` and setting the environment variables.
    |
    */

    'enabled' => (bool) env('WEBPUSH_ENABLED', false),

    'subject' => env('VAPID_SUBJECT', 'mailto:'.(env('MAIL_FROM_ADDRESS') ?: 'no-reply@example.com')),

    'public_key' => env('VAPID_PUBLIC_KEY'),

    'private_key' => env('VAPID_PRIVATE_KEY'),

    // How long the push service keeps an undelivered message, in seconds.
    'ttl' => (int) env('WEBPUSH_TTL', 86400),

    'timeout' => (int) env('WEBPUSH_TIMEOUT', 30),

    // Queue used by SendWebPushNotification. Keep it on a worker that is
    // always running; push retries rely on the queued job.
    'queue' => env('WEBPUSH_QUEUE', 'default'),

    'max_subscriptions_per_user' => (int) env('WEBPUSH_MAX_SUBSCRIPTIONS', 10),

];
