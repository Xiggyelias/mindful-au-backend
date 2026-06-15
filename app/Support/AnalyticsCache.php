<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class AnalyticsCache
{
    public const DASHBOARD_KEY = 'analytics:dashboard:v3';

    public const ADMIN_OVERVIEW_KEY = 'analytics:admin:overview:v2';

    public static function clear(): void
    {
        Cache::forget(self::DASHBOARD_KEY);
        Cache::forget(self::ADMIN_OVERVIEW_KEY);
    }

    /**
     * @template TValue
     *
     * @param callable(): TValue $callback
     * @return TValue
     */
    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        if (app()->runningUnitTests()) {
            return $callback();
        }

        return Cache::remember($key, now()->addSeconds($seconds), $callback);
    }
}
