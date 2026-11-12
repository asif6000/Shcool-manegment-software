<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\PushDeliveryLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendWebPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $notificationId,
        public readonly int $userId,
    ) {
        $this->onQueue((string) config('webpush.queue', 'default'));
    }

    public function handle(NotificationService $notifications, WebPushSender $sender): void
    {
        if (! (bool) config('webpush.enabled')) {
            return;
        }

        /** @var Notification|null $notification */
        $notification = Notification::query()->whereKey($this->notificationId)->first();

        if ($notification === null) {
            Log::info('Web push skipped: notification no longer exists.', [
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        if ((int) $notification->notifiable_id !== $this->userId
            || $notification->notifiable_type !== User::class) {
            Log::warning('Web push skipped: notification does not belong to the job user.', [
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null || ! $user->is_active) {
            return;
        }

        $category = (string) ($notification->category ?: 'general');

        if (! $notifications->webPushEnabled($user, $category)) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()
            ->active()
            ->orderBy('id')
            ->limit(max(1, (int) config('webpush.max_subscriptions_per_user', 10)))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = $notification->webPushPayload();
        $senderEnabled = $sender->enabled();

        foreach ($subscriptions as $subscription) {
            $log = PushDeliveryLog::query()->firstOrCreate(
                [
                    'notification_id' => $notification->id,
                    'push_subscription_id' => $subscription->id,
                    'channel' => 'webpush',
                ],
                [
                    'user_id' => $this->userId,
                    'status' => PushDeliveryLog::STATUS_QUEUED,
                    'attempts' => 0,
                ],
            );

            if (in_array($log->status, [PushDeliveryLog::STATUS_SENT, PushDeliveryLog::STATUS_INVALID], true)) {
                continue;
            }

            $log->increment('attempts');

            if (! $senderEnabled) {
                $log->update([
                    'status' => PushDeliveryLog::STATUS_SKIPPED,
                    'error_code' => 'not_configured',
                    'error_message' => 'VAPID keys are not configured.',
                ]);

                continue;
            }

            try {
                $result = $sender->send($subscription, $payload);
            } catch (Throwable $exception) {
                $log->update([
                    'status' => PushDeliveryLog::STATUS_FAILED,
                    'error_code' => 'transport_error',
                    'error_message' => $this->safeMessage($exception->getMessage()),
                ]);

                Log::warning('Web push transport failed; the job will retry.', [
                    'notification_id' => $notification->id,
                    'user_id' => $this->userId,
                    'subscription_id' => $subscription->id,
                    'host' => $subscription->maskedEndpoint(),
                    'exception' => class_basename($exception),
                ]);

                throw $exception;
            }

            if ($result['skipped']) {
                $log->update([
                    'status' => PushDeliveryLog::STATUS_SKIPPED,
                    'error_code' => 'not_configured',
                    'error_message' => $result['reason'],
                ]);

                continue;
            }

            if ($result['success']) {
                $log->update([
                    'status' => PushDeliveryLog::STATUS_SENT,
                    'response_code' => $result['status'],
                    'error_code' => null,
                    'error_message' => null,
                    'sent_at' => now(),
                ]);
                $subscription->markUsed();

                continue;
            }

            if ($result['expired']) {
                $log->update([
                    'status' => PushDeliveryLog::STATUS_INVALID,
                    'response_code' => $result['status'],
                    'error_code' => 'subscription_expired',
                    'error_message' => 'The push service reported the subscription as gone.',
                ]);
                $subscription->deactivate();

                continue;
            }

            $log->update([
                'status' => PushDeliveryLog::STATUS_FAILED,
                'response_code' => $result['status'],
                'error_code' => 'push_rejected',
                'error_message' => $this->safeMessage($result['reason']),
            ]);
            $subscription->markFailure();

            Log::warning('Web push delivery was rejected by the push service.', [
                'notification_id' => $notification->id,
                'user_id' => $this->userId,
                'subscription_id' => $subscription->id,
                'host' => $subscription->maskedEndpoint(),
                'status' => $result['status'],
            ]);

            if ($this->isTransient($result['status'])) {
                throw new \RuntimeException('Transient web push failure; retrying.');
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Web push job failed permanently.', [
            'notification_id' => $this->notificationId,
            'user_id' => $this->userId,
            'exception' => class_basename($exception),
            'message' => $this->safeMessage($exception->getMessage()),
        ]);
    }

    private function isTransient(?int $status): bool
    {
        return $status === null || $status === 0 || $status === 429 || $status >= 500;
    }

    private function safeMessage(string $message): string
    {
        $message = preg_replace('#https?://\S+#i', '[url]', $message) ?? $message;

        return Str::limit($message, 240);
    }
}
