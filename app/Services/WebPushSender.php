<?php

namespace App\Services;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushSender
{
    private ?WebPush $client = null;

    public function enabled(): bool
    {
        return (bool) config('webpush.enabled')
            && filled(config('webpush.public_key'))
            && filled(config('webpush.private_key'))
            && filled(config('webpush.subject'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{skipped: bool, success: bool, expired: bool, status: ?int, reason: string}
     */
    public function send(PushSubscription $subscription, array $payload): array
    {
        if (! $this->enabled()) {
            return [
                'skipped' => true,
                'success' => false,
                'expired' => false,
                'status' => null,
                'reason' => 'VAPID keys are not configured.',
            ];
        }

        $report = $this->client()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
            ]),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            [
                'TTL' => max(0, (int) config('webpush.ttl', 86400)),
                'urgency' => 'normal',
            ],
        );

        return [
            'skipped' => false,
            'success' => $report->isSuccess(),
            'expired' => $report->isSubscriptionExpired(),
            'status' => $report->getResponse()?->getStatusCode(),
            'reason' => $report->isSuccess() ? 'OK' : $report->getReason(),
        ];
    }

    private function client(): WebPush
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return $this->client = new WebPush(
            auth: [
                'VAPID' => [
                    'subject' => (string) config('webpush.subject'),
                    'publicKey' => (string) config('webpush.public_key'),
                    'privateKey' => (string) config('webpush.private_key'),
                ],
            ],
            client: new Client([
                'timeout' => max(5, (int) config('webpush.timeout', 30)),
                'connect_timeout' => 10,
            ]),
        );
    }
}
