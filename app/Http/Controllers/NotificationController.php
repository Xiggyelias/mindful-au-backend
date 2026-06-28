<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Services\TipOfDayService;
use App\Support\PaginationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly TipOfDayService $tipOfDayService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            try {
                $this->tipOfDayService->resolveForUser($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'unread_only' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $limit = $validated['limit'] ?? 30;
        $unreadOnly = (bool) ($validated['unread_only'] ?? false);

        $baseQuery = $request->user()->notifications();

        $unreadCount = (clone $baseQuery)
            ->where('read', false)
            ->count();

        if ($unreadOnly) {
            $baseQuery->where('read', false);
        }

        $baseQuery->latest();

        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        if ($usePagination) {
            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(200, (int) ($validated['per_page'] ?? $limit)));
            $paginator = $baseQuery
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            $payload = PaginationPayload::fromPaginator(
                $paginator,
                $request,
                ['unread_only']
            );
            $payload['unread_count'] = $unreadCount;

            return response()->json($payload);
        }

        $notifications = $baseQuery
            ->limit($limit)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        if (! $notification->read) {
            $notification->update(['read' => true]);
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
        if (! $user || ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'sometimes|in:info,warning,success,error,panic',
            'meta' => 'sometimes|array',
        ]);

        $notification = Notification::create($validated);

        // Broadcast notification event
        event(new NotificationCreated($notification));

        return response()->json($notification, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        $unreadCount = $request->user()
            ->notifications()
            ->where('read', false)
            ->count();

        return response()->json([
            'ok' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()
            ->notifications()
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $updated,
            'unread_count' => 0,
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_enabled' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->forceFill([
            'email_notifications_enabled' => $validated['email_enabled'],
        ])->save();

        return response()->json([
            'ok' => true,
            'email_notifications_enabled' => (bool) $user->email_notifications_enabled,
        ]);
    }
}
