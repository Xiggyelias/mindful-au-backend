<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogStatsService;
use App\Support\PaginationPayload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogStatsService $activityLogStatsService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'type' => 'sometimes|string|max:50',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'search' => 'sometimes|string|max:200',
            'limit' => 'sometimes|integer|min:1|max:500',
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $query = ActivityLog::with('user.profile')
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        // Filter by date range if provided
        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        // Search by action or description
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $transformLogs = function ($logs) {
            return collect($logs)->map(function($log) {
                return [
                    'id' => $log->id,
                    'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user?->profile?->full_name ?? $log->user?->email ?? 'System',
                    'type' => $log->type,
                    'ip_address' => $log->ip_address,
                    'metadata' => $log->metadata,
                ];
            })->values()->all();
        };

        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        if ($usePagination) {
            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(200, (int) ($validated['per_page'] ?? 50)));
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            $payload = PaginationPayload::fromPaginator($paginator, $request, ['type', 'from', 'to', 'search']);
            $payload['data'] = $transformLogs($paginator->items());
            return response()->json($payload);
        }

        $limit = (int) ($validated['limit'] ?? 100);
        $logs = $query->limit($limit)->get();

        return response()->json($transformLogs($logs));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $stats = $this->activityLogStatsService->getStats();

        return response()->json($stats);
    }

    /**
     * Live tail endpoint: returns logs newer than `since_id` (or last `limit`
     * when not provided). Designed to be polled by the admin UI.
     */
    public function stream(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'since_id' => 'sometimes|integer|min:0',
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 50);

        $query = ActivityLog::with('user.profile')
            ->orderBy('id', 'desc')
            ->limit($limit);

        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        $logs = $query->get()->reverse()->values();

        $transformed = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->profile?->full_name ?? $log->user?->email ?? 'System',
                'type' => $log->type,
                'ip_address' => $log->ip_address,
                'metadata' => $log->metadata,
            ];
        })->values()->all();

        $latestId = $logs->isNotEmpty() ? (int) $logs->last()['id'] : $sinceId;

        return response()->json([
            'logs' => $transformed,
            'last_id' => $latestId,
            'count' => count($transformed),
        ]);
    }
}
