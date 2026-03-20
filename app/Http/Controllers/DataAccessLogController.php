<?php

namespace App\Http\Controllers;

use App\Models\DataAccessLog;
use App\Support\PaginationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataAccessLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'method' => 'sometimes|string|max:10',
            'path' => 'sometimes|string|max:255',
            'status_code' => 'sometimes|integer|min:100|max:599',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:500',
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $query = DataAccessLog::query()
            ->with('user.profile')
            ->orderByDesc('created_at');

        if (!empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }
        if (!empty($validated['method'])) {
            $query->where('method', strtoupper((string) $validated['method']));
        }
        if (!empty($validated['path'])) {
            $query->where('path', 'like', '%' . $validated['path'] . '%');
        }
        if (!empty($validated['status_code'])) {
            $query->where('status_code', (int) $validated['status_code']);
        }
        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $transformLog = static function (DataAccessLog $log): array {
            return [
                'id' => $log->id,
                'timestamp' => optional($log->created_at)->toIso8601String(),
                'user_id' => $log->user_id,
                'user' => $log->user
                    ? ($log->user->profile->full_name ?? $log->user->email)
                    : null,
                'method' => $log->method,
                'path' => $log->path,
                'status_code' => $log->status_code,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'ip_address' => $log->ip_address,
                'metadata' => $log->metadata,
            ];
        };

        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        if ($usePagination) {
            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(200, (int) ($validated['per_page'] ?? 50)));
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            $payload = PaginationPayload::fromPaginator(
                $paginator,
                $request,
                ['user_id', 'method', 'path', 'status_code', 'from', 'to']
            );
            $payload['data'] = collect($paginator->items())
                ->map($transformLog)
                ->values()
                ->all();

            return response()->json($payload);
        }

        $limit = (int) ($validated['limit'] ?? 100);
        $logs = $query->limit($limit)->get();

        return response()->json(
            $logs
                ->map($transformLog)
                ->values()
                ->all()
        );
    }
}
