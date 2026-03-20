<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    public function root(): JsonResponse
    {
        return response()->json([
            'message' => 'Africa University Counseling API',
            'service' => config('app.name', 'backend'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name', 'backend'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk' => $this->checkDisk(),
        ];

        $components = [];
        foreach ($checks as $key => $check) {
            $components[$key] = (bool) ($check['ok'] ?? false);
        }

        $isReady = !in_array(false, $components, true);

        return response()->json([
            'status' => $isReady ? 'ok' : 'degraded',
            'service' => config('app.name', 'backend'),
            'time' => now()->toIso8601String(),
            'components' => $components,
            'details' => $checks,
        ], $isReady ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return [
                'ok' => true,
                'connection' => (string) config('database.default', 'unknown'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'connection' => (string) config('database.default', 'unknown'),
                'error' => $this->sanitizeError($exception),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $cacheKey = 'health:ready:' . Str::uuid()->toString();
            Cache::put($cacheKey, 'ok', now()->addSeconds(5));
            $ok = Cache::get($cacheKey) === 'ok';
            Cache::forget($cacheKey);

            return [
                'ok' => $ok,
                'store' => (string) config('cache.default', 'unknown'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'store' => (string) config('cache.default', 'unknown'),
                'error' => $this->sanitizeError($exception),
            ];
        }
    }

    private function checkQueue(): array
    {
        $driver = (string) config('queue.default', 'sync');
        if ($driver === '' || $driver === 'sync') {
            return [
                'ok' => true,
                'driver' => $driver !== '' ? $driver : 'sync',
            ];
        }

        try {
            if ($driver === 'redis') {
                $connection = (string) config('queue.connections.redis.connection', 'default');
                Redis::connection($connection)->ping();

                return [
                    'ok' => true,
                    'driver' => 'redis',
                    'connection' => $connection,
                ];
            }

            if ($driver === 'database') {
                $table = (string) config('queue.connections.database.table', 'jobs');
                if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                    return [
                        'ok' => false,
                        'driver' => 'database',
                        'table' => $table,
                        'error' => 'invalid_queue_table_config',
                    ];
                }
                DB::table($table)->limit(1)->get();

                return [
                    'ok' => true,
                    'driver' => 'database',
                    'table' => $table,
                ];
            }

            return [
                'ok' => true,
                'driver' => $driver,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'driver' => $driver,
                'error' => $this->sanitizeError($exception),
            ];
        }
    }

    private function checkDisk(): array
    {
        $path = storage_path();
        $requiredPercent = max(0, (float) env('HEALTH_MIN_DISK_FREE_PERCENT', 5));
        $cacheSeconds = max(0, (int) env('HEALTH_DISK_CACHE_SECONDS', 15));

        if ($cacheSeconds > 0) {
            $cacheKey = 'health:disk:status:' . md5($path . '|' . $requiredPercent);
            try {
                return Cache::remember(
                    $cacheKey,
                    now()->addSeconds($cacheSeconds),
                    fn () => $this->computeDiskCheck($path, $requiredPercent)
                );
            } catch (\Throwable) {
                return $this->computeDiskCheck($path, $requiredPercent);
            }
        }

        return $this->computeDiskCheck($path, $requiredPercent);
    }

    private function computeDiskCheck(string $path, float $requiredPercent): array
    {
        $freeSpaceResult = $this->readDiskSpaceMetric($path, 'free');
        $totalSpaceResult = $this->readDiskSpaceMetric($path, 'total');
        $freeBytes = $freeSpaceResult['value'];
        $totalBytes = $totalSpaceResult['value'];
        $warningCacheKey = 'health:disk-warning:' . md5($path);

        if ($freeBytes === false || $totalBytes === false || $totalBytes <= 0) {
            $warningMessage = $freeSpaceResult['error'] ?? $totalSpaceResult['error'];
            if (!Cache::has($warningCacheKey)) {
                Log::warning('Disk health probe could not read disk usage metrics.', [
                    'path' => $path,
                    'warning' => $warningMessage,
                ]);
                Cache::put($warningCacheKey, 'unavailable', now()->addMinutes(10));
            }

            return [
                'ok' => false,
                'path' => $path,
                'error' => 'disk_space_unavailable',
                'warning' => $warningMessage,
            ];
        }

        $freePercent = round(($freeBytes / $totalBytes) * 100, 2);
        if ($freePercent < $requiredPercent && !Cache::has($warningCacheKey)) {
            Log::warning('Disk free space below configured readiness threshold.', [
                'path' => $path,
                'free_percent' => $freePercent,
                'required_min_percent' => $requiredPercent,
                'free_bytes' => (int) $freeBytes,
                'total_bytes' => (int) $totalBytes,
            ]);
            Cache::put($warningCacheKey, 'low', now()->addMinutes(10));
        }

        return [
            'ok' => $freePercent >= $requiredPercent,
            'path' => $path,
            'free_percent' => $freePercent,
            'required_min_percent' => $requiredPercent,
            'free_bytes' => (int) $freeBytes,
            'total_bytes' => (int) $totalBytes,
        ];
    }

    /**
     * @return array{value: int|float|false, error: string|null}
     */
    private function readDiskSpaceMetric(string $path, string $kind): array
    {
        $warning = null;
        set_error_handler(function (int $severity, string $message) use (&$warning): bool {
            $warning = trim($message) !== '' ? $message : "disk_space_warning_{$severity}";
            return true;
        });

        try {
            $value = $kind === 'total'
                ? disk_total_space($path)
                : disk_free_space($path);
        } catch (\Throwable $exception) {
            $warning = $this->sanitizeError($exception);
            $value = false;
        } finally {
            restore_error_handler();
        }

        if (!is_int($value) && !is_float($value)) {
            $value = false;
        }

        return [
            'value' => $value,
            'error' => $warning,
        ];
    }

    private function sanitizeError(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'check_failed';
        }

        return Str::limit($message, 160, '...');
    }
}
