<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\PaginationPayload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'unread_only' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $limit = $validated['limit'] ?? 30;
        $unreadOnly = (bool) ($validated['unread_only'] ?? false);

        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? $limit)));
        $userId = (int) $request->user()->id;
        $cacheTtlSeconds = max(0, (int) env('NOTIFICATIONS_CACHE_SECONDS', 10));
        $version = $this->notificationVersion($userId);
        $etag = $this->notificationEtag($userId, $version);

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()
                ->json(null, 304)
                ->header('ETag', $etag)
                ->header('X-Notification-Version', (string) $version);
        }

        $payloadBuilder = function () use ($limit, $page, $perPage, $request, $unreadOnly, $usePagination) {
            $baseQuery = $request->user()->notifications();

            $unreadCount = (clone $baseQuery)
                ->where('read', false)
                ->count();

            if ($unreadOnly) {
                $baseQuery->where('read', false);
            }

            $baseQuery->latest();

            if ($usePagination) {
                $paginator = $baseQuery
                    ->paginate($perPage, ['*'], 'page', $page)
                    ->appends($request->query());

                $payload = PaginationPayload::fromPaginator(
                    $paginator,
                    $request,
                    ['unread_only']
                );
                $payload['unread_count'] = $unreadCount;

                return $payload;
            }

            return [
                'notifications' => $baseQuery
                    ->limit($limit)
                    ->get(),
                'unread_count' => $unreadCount,
            ];
        };

        $payload = $cacheTtlSeconds > 0
            ? Cache::remember(
                $this->notificationCacheKey($userId, $limit, $unreadOnly, $page, $perPage, $usePagination),
                now()->addSeconds($cacheTtlSeconds),
                $payloadBuilder
            )
            : $payloadBuilder();

        return response()
            ->json($payload)
            ->header('ETag', $etag)
            ->header('X-Notification-Version', (string) $version);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        if (!$notification->read) {
            $notification->update(['read' => true]);
            $this->bumpNotificationCacheVersion((int) $request->user()->id);
        }

        $unreadCount = $request->user()
            ->notifications()
            ->where('read', false)
            ->count();

        return response()->json([
            'notification' => $notification->fresh(),
            'unread_count' => $unreadCount,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'sometimes|in:info,warning,success,error,panic',
        ]);

        $notification = Notification::create($validated);
        $this->bumpNotificationCacheVersion((int) $validated['user_id']);

        // Broadcast notification event
        event(new \App\Events\NotificationCreated($notification));

        return response()->json($notification, 201);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()
            ->notifications()
            ->where('read', false)
            ->update(['read' => true]);

        if ($updated > 0) {
            $this->bumpNotificationCacheVersion((int) $request->user()->id);
        }

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $updated,
            'unread_count' => 0,
        ]);
    }

    private function notificationCacheKey(
        int $userId,
        int $limit,
        bool $unreadOnly,
        int $page,
        int $perPage,
        bool $usePagination
    ): string {
        $version = $this->notificationVersion($userId);

        return implode(':', [
            'notifications',
            'index',
            'v2',
            "user-{$userId}",
            "ver-{$version}",
            "limit-{$limit}",
            'unread-' . ($unreadOnly ? '1' : '0'),
            'page-' . ($usePagination ? $page : 1),
            'per-' . ($usePagination ? $perPage : $limit),
        ]);
    }

    private function notificationVersionKey(int $userId): string
    {
        return "notifications:version:user:{$userId}";
    }

    private function notificationVersion(int $userId): int
    {
        $version = (int) Cache::get($this->notificationVersionKey($userId), 1);
        return max(1, $version);
    }

    private function notificationEtag(int $userId, int $version): string
    {
        return sprintf('W/"notifications-%d-%d"', $userId, $version);
    }

    private function bumpNotificationCacheVersion(int $userId): void
    {
        $versionKey = $this->notificationVersionKey($userId);
        if (!Cache::add($versionKey, 1, now()->addDays(7))) {
            Cache::increment($versionKey);
        }
    }
}
