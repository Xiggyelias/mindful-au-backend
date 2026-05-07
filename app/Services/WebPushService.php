<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function isConfigured(): bool
    {
        if (! config('webpush.enabled')) {
            return false;
        }

        $pub = trim((string) config('webpush.vapid.public_key'));
        $priv = trim((string) config('webpush.vapid.private_key'));

        return $pub !== '' && $priv !== '';
    }

    /**
     * @param  array{tag?: string, urgency?: string, requireInteraction?: bool, silent?: bool}  $options
     */
    public function sendToUser(int $userId, string $title, string $body, string $urlPath = '/', array $options = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user || ! (bool) ($user->web_push_enabled ?? true)) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $absoluteUrl = $this->absoluteAppUrl($urlPath);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $absoluteUrl,
            'path' => $urlPath,
            'icon' => '/assets/icons/notify-192.png',
            'badge' => '/assets/icons/notify-badge-96.png',
            'tag' => $options['tag'] ?? 'cms-'.substr(sha1($title.$body.$urlPath), 0, 16),
            'urgency' => $options['urgency'] ?? 'normal',
            'requireInteraction' => (bool) ($options['requireInteraction'] ?? false),
            'silent' => (bool) ($options['silent'] ?? false),
        ], JSON_THROW_ON_ERROR);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ], [], 15);

        $queueOptions = [];
        if (isset($options['urgency'])) {
            $queueOptions['urgency'] = $options['urgency'];
        }

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->p256dh_key,
                        'auth' => $sub->auth_key,
                    ],
                    'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                ]);
                $webPush->queueNotification($subscription, $payload, $queueOptions);
            } catch (Throwable $e) {
                Log::warning('web-push invalid subscription skipped', [
                    'user_id' => $userId,
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
            } else {
                Log::warning('web-push delivery failed', [
                    'reason' => $report->getReason(),
                    'response' => $report->getResponse() ? $report->getResponse()->getStatusCode() : null,
                ]);
            }
        }
    }

    private function absoluteAppUrl(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $base = rtrim((string) config('app.frontend_url'), '/');
        if ($base !== '') {
            return $base.$path;
        }

        return $path;
    }

    /**
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string}  $subscription
     */
    public function saveSubscription(User $user, array $subscription): PushSubscription
    {
        $endpoint = (string) $subscription['endpoint'];
        $p256dh = (string) ($subscription['keys']['p256dh'] ?? '');
        $auth = (string) ($subscription['keys']['auth'] ?? '');
        $encoding = isset($subscription['contentEncoding']) ? (string) $subscription['contentEncoding'] : null;

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new \InvalidArgumentException('Invalid push subscription payload.');
        }

        return PushSubscription::query()->updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'user_id' => $user->id,
                'p256dh_key' => $p256dh,
                'auth_key' => $auth,
                'content_encoding' => $encoding ?: 'aesgcm',
            ]
        );
    }

    public function deleteByEndpoint(User $user, string $endpoint): int
    {
        return PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->delete();
    }
}
