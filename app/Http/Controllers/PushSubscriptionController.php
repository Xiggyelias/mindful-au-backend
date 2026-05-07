<?php

namespace App\Http\Controllers;

use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPush,
    ) {}

    public function vapidPublicKey(): JsonResponse
    {
        if (! $this->webPush->isConfigured()) {
            return response()->json([
                'enabled' => false,
                'publicKey' => null,
            ]);
        }

        return response()->json([
            'enabled' => true,
            'publicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        if (! $this->webPush->isConfigured()) {
            return response()->json(['message' => 'Web push is not configured on this server.'], 503);
        }

        $user = $request->user();
        if (! (bool) ($user->web_push_enabled ?? true)) {
            return response()->json(['message' => 'Push notifications are disabled for your account.'], 422);
        }

        $validated = $request->validate([
            'endpoint' => 'required|string|max:2048',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:2048',
            'keys.auth' => 'required|string|max:512',
            'contentEncoding' => 'nullable|string|max:32',
        ]);

        $subscription = $this->webPush->saveSubscription(
            $user,
            [
                'endpoint' => $validated['endpoint'],
                'keys' => [
                    'p256dh' => $validated['keys']['p256dh'],
                    'auth' => $validated['keys']['auth'],
                ],
                'contentEncoding' => $validated['contentEncoding'] ?? null,
            ]
        );

        return response()->json([
            'ok' => true,
            'id' => $subscription->id,
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|max:2048',
        ]);

        $deleted = $this->webPush->deleteByEndpoint($request->user(), $validated['endpoint']);

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->forceFill(['web_push_enabled' => $validated['enabled']])->save();

        if (! $validated['enabled']) {
            $user->pushSubscriptions()->delete();
        }

        return response()->json([
            'ok' => true,
            'web_push_enabled' => (bool) $user->web_push_enabled,
        ]);
    }
}
