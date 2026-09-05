<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function __construct(
        private array $config = []
    ) {
        $this->config = config('services.webpush');
    }

    public function webPush(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => $this->config['subject'],
                'publicKey' => $this->config['public_key'],
                'privateKey' => $this->config['private_key'],
            ],
            'timeout' => 30,
        ]);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $subscriptions = $user->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $this->dispatch($subscriptions, $title, $body, $data);
        } catch (\Throwable $e) {
            // Push is best-effort: never let it fail the business action
            // (payment verify, device binding, ...) that triggered it.
            Log::error('Web push delivery crashed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatch($subscriptions, string $title, string $body, array $data): void
    {
        $webPush = $this->webPush();
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'url' => $data['url'] ?? null,
            'icon' => '/icons/icon.png',
            'badge' => '/icons/icon.png',
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh,
                        'auth' => $subscription->auth,
                    ],
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('Web push delivery failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }
}
