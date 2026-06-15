<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Cache;

class ActivityLogStatsService
{
    public function getStats(): array
    {
        return Cache::remember('activity_logs:stats:v1', now()->addSeconds(20), function () {
            return [
                'total_logs' => ActivityLog::count(),
                'today_logs' => ActivityLog::whereDate('created_at', today())->count(),
                'by_type' => ActivityLog::selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->get()
                    ->pluck('count', 'type')
                    ->toArray(),
                'recent_actions' => ActivityLog::selectRaw('action, count(*) as count')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('action')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
                    ->pluck('count', 'action')
                    ->toArray(),
            ];
        });
    }
}
