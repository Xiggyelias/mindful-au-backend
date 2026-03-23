<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
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

    public function ready(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk' => $this->checkDisk(),
            'ai' => $this->checkAi(),
        ];
        $integrations = $this->checkIntegrations();

        $components = [];
        foreach ($checks as $key => $check) {
            $components[$key] = (bool) ($check['ok'] ?? false);
        }

        $isReady = !in_array(false, $components, true);

        $payload = [
            'status' => $isReady ? 'ok' : 'degraded',
            'service' => config('app.name', 'backend'),
            'time' => now()->toIso8601String(),
            'components' => $components,
        ];

        if ($this->shouldExposeReadyDetails($request)) {
            $payload['details'] = [
                ...$checks,
                'integrations' => $integrations,
                'scaling' => $this->checkScalingProfile(),
            ];
        }

        return response()->json($payload, $isReady ? 200 : 503);
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

    private function checkAi(): array
    {
        $probeEnabled = $this->shouldProbeExternalAiProviders();
        $configuredProviders = [];
        $providerStatuses = [];
        $activeProvider = null;
        $externalReady = false;
        $validation = 'not_configured';

        foreach ($this->aiProviderDefinitions() as $providerName => $definition) {
            $configured = trim((string) config($definition['config_key'], '')) !== '';
            $providerStatuses[$providerName] = [
                'configured' => $configured,
                'status' => $configured ? 'configured' : 'not_configured',
                'reachable' => $configured ? null : false,
                'probe_supported' => (bool) ($definition['probe_supported'] ?? false),
            ];

            if (!$configured) {
                continue;
            }

            $configuredProviders[] = $providerName;

            if (!$probeEnabled || !(bool) ($definition['probe_supported'] ?? false)) {
                if ($activeProvider === null) {
                    $activeProvider = $providerName;
                }
                $externalReady = true;
                if ($validation === 'not_configured') {
                    $validation = 'configuration_only';
                }
                continue;
            }

            $probe = $this->probeAiProvider($providerName, $definition);
            $providerStatuses[$providerName] = array_merge($providerStatuses[$providerName], $probe);

            if (($probe['status'] ?? null) === 'ready') {
                $externalReady = true;
                $validation = 'verified';
                if ($activeProvider === null) {
                    $activeProvider = $providerName;
                }
            }
        }

        $externalConfigured = $configuredProviders !== [];
        $mode = $externalConfigured && $externalReady ? 'external' : 'local_fallback';
        $warning = null;

        if (!$externalConfigured) {
            $warning = 'No external AI provider key is configured; AI chat will use local fallback mode.';
        } elseif (!$externalReady) {
            $warning = 'External AI providers are configured but not currently reachable; AI chat will use local fallback mode.';
        } elseif ($validation === 'configuration_only') {
            $warning = 'External AI provider readiness is based on configuration only.';
        }

        return [
            'ok' => true,
            'mode' => $mode,
            'validation' => $validation,
            'external_provider_configured' => $externalConfigured,
            'external_provider_ready' => $externalReady,
            'configured_providers' => $configuredProviders,
            'active_provider' => $activeProvider,
            'providers' => $providerStatuses,
            'local_fallback_available' => true,
            'chat_endpoint' => '/api/ai/wellness-chat',
            'warning' => $warning,
        ];
    }

    private function checkIntegrations(): array
    {
        return [
            'google_oauth' => $this->checkGoogleOAuthIntegration(),
            'academic_risk_webhook' => $this->checkAcademicRiskIntegration(),
        ];
    }

    /**
     * @return array<string, array{config_key: string, probe_supported: bool}>
     */
    private function aiProviderDefinitions(): array
    {
        return [
            'kwaipilot' => [
                'config_key' => 'services.kwaipilot.api_key',
                'probe_supported' => false,
            ],
            'openrouter' => [
                'config_key' => 'services.openrouter.api_key',
                'probe_supported' => true,
            ],
            'gemini' => [
                'config_key' => 'services.gemini.api_key',
                'probe_supported' => true,
            ],
            'openai' => [
                'config_key' => 'services.openai.api_key',
                'probe_supported' => true,
            ],
        ];
    }

    /**
     * @param array{config_key: string, probe_supported: bool} $definition
     * @return array{status: string, reachable: bool|null, checked_at?: string, http_status?: int, error?: string}
     */
    private function probeAiProvider(string $providerName, array $definition): array
    {
        $cacheSeconds = $this->externalAiProbeCacheSeconds();
        $cacheSeed = trim((string) config($definition['config_key'], ''));
        $cacheKey = 'health:ai-provider:' . $providerName . ':' . md5($cacheSeed);

        if ($cacheSeconds > 0) {
            return Cache::remember(
                $cacheKey,
                now()->addSeconds($cacheSeconds),
                fn () => $this->executeAiProviderProbe($providerName)
            );
        }

        return $this->executeAiProviderProbe($providerName);
    }

    /**
     * @return array{status: string, reachable: bool|null, checked_at?: string, http_status?: int, error?: string}
     */
    private function executeAiProviderProbe(string $providerName): array
    {
        try {
            return match ($providerName) {
                'openrouter' => $this->probeOpenRouter(),
                'gemini' => $this->probeGemini(),
                'openai' => $this->probeOpenAi(),
                default => [
                    'status' => 'configured',
                    'reachable' => null,
                    'checked_at' => now()->toIso8601String(),
                ],
            };
        } catch (\Throwable $exception) {
            return [
                'status' => 'degraded',
                'reachable' => false,
                'checked_at' => now()->toIso8601String(),
                'error' => $this->sanitizeError($exception),
            ];
        }
    }

    /**
     * @return array{status: string, reachable: bool, checked_at: string, http_status?: int, error?: string}
     */
    private function probeOpenRouter(): array
    {
        $response = Http::timeout($this->externalAiProbeTimeoutSeconds())
            ->withHeaders([
                'Authorization' => 'Bearer ' . (string) config('services.openrouter.api_key', ''),
                'HTTP-Referer' => (string) config('services.openrouter.site_url', 'https://mindful-au.local'),
                'X-Title' => (string) config('services.openrouter.site_name', 'Mindful AU'),
            ])
            ->get(rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/') . '/models');

        return $this->probeResultFromResponse($response);
    }

    /**
     * @return array{status: string, reachable: bool, checked_at: string, http_status?: int, error?: string}
     */
    private function probeGemini(): array
    {
        $response = Http::timeout($this->externalAiProbeTimeoutSeconds())
            ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                'key' => (string) config('services.gemini.api_key', ''),
            ]);

        return $this->probeResultFromResponse($response);
    }

    /**
     * @return array{status: string, reachable: bool, checked_at: string, http_status?: int, error?: string}
     */
    private function probeOpenAi(): array
    {
        $response = Http::timeout($this->externalAiProbeTimeoutSeconds())
            ->withToken((string) config('services.openai.api_key', ''))
            ->get('https://api.openai.com/v1/models');

        return $this->probeResultFromResponse($response);
    }

    /**
     * @return array{status: string, reachable: bool, checked_at: string, http_status?: int, error?: string}
     */
    private function probeResultFromResponse(\Illuminate\Http\Client\Response $response): array
    {
        if ($response->successful()) {
            return [
                'status' => 'ready',
                'reachable' => true,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return [
            'status' => 'degraded',
            'reachable' => false,
            'checked_at' => now()->toIso8601String(),
            'http_status' => $response->status(),
            'error' => $this->summarizeProbeResponse($response),
        ];
    }

    private function shouldProbeExternalAiProviders(): bool
    {
        $configured = env('HEALTH_PROBE_EXTERNAL_AI');
        if ($configured === null) {
            return !app()->environment('testing');
        }

        return filter_var($configured, FILTER_VALIDATE_BOOL);
    }

    private function externalAiProbeTimeoutSeconds(): int
    {
        return max(2, min(10, (int) env('HEALTH_EXTERNAL_AI_TIMEOUT_SECONDS', 5)));
    }

    private function externalAiProbeCacheSeconds(): int
    {
        if (app()->environment('testing')) {
            return 0;
        }

        return max(0, (int) env('HEALTH_EXTERNAL_AI_CACHE_SECONDS', 60));
    }

    private function summarizeProbeResponse(\Illuminate\Http\Client\Response $response): string
    {
        $body = trim((string) $response->body());
        if ($body === '') {
            return 'provider_probe_failed';
        }

        return Str::limit(preg_replace('/\s+/', ' ', $body) ?: $body, 160, '...');
    }

    private function checkGoogleOAuthIntegration(): array
    {
        $clientIdConfigured = trim((string) config('services.google.client_id', '')) !== '';
        $clientSecretConfigured = trim((string) config('services.google.client_secret', '')) !== '';
        $redirectConfigured = trim((string) config('services.google.redirect', '')) !== '';
        $configured = $clientIdConfigured && $clientSecretConfigured && $redirectConfigured;

        return [
            'configured' => $configured,
            'status' => $configured ? 'ready' : 'not_configured',
            'client_id_configured' => $clientIdConfigured,
            'client_secret_configured' => $clientSecretConfigured,
            'redirect_configured' => $redirectConfigured,
            'warning' => $configured
                ? null
                : 'Google OAuth requires GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URL.',
        ];
    }

    private function checkAcademicRiskIntegration(): array
    {
        $configured = trim((string) config('services.academic_risk.webhook_secret', '')) !== '';
        $details = [
            'configured' => $configured,
            'status' => $configured ? 'secured' : 'not_configured',
            'signature_header' => 'X-AUCMS-Signature',
            'warning' => $configured
                ? null
                : 'Academic risk webhook signing secret is not configured.',
        ];

        try {
            if (!Schema::hasTable('sync_runs')) {
                return $details;
            }

            $latestRun = DB::table('sync_runs')
                ->where('source', 'academic_risk_webhook')
                ->orderByDesc('created_at')
                ->first(['status', 'started_at', 'finished_at']);

            if (!$latestRun) {
                return $details;
            }

            return [
                ...$details,
                'latest_run_status' => $latestRun->status,
                'latest_run_started_at' => $latestRun->started_at,
                'latest_run_finished_at' => $latestRun->finished_at,
            ];
        } catch (\Throwable $exception) {
            return [
                ...$details,
                'latest_run_error' => $this->sanitizeError($exception),
            ];
        }
    }

    private function checkScalingProfile(): array
    {
        $cacheDriver = (string) config('cache.default', 'unknown');
        $queueDriver = (string) config('queue.default', 'unknown');
        $sessionDriver = (string) config('session.driver', 'unknown');

        $warnings = [];
        if (in_array($cacheDriver, ['file', 'array', 'null'], true)) {
            $warnings[] = 'cache_driver_not_recommended_for_multi_instance_scale';
        }
        if ($queueDriver === '' || $queueDriver === 'sync') {
            $warnings[] = 'queue_driver_sync_will_block_request_latency_under_load';
        }
        if (in_array($sessionDriver, ['file', 'array'], true)) {
            $warnings[] = 'session_driver_not_recommended_for_multi_instance_scale';
        }

        return [
            'recommended_for_2k_plus' => $warnings === [],
            'cache_driver' => $cacheDriver,
            'queue_driver' => $queueDriver !== '' ? $queueDriver : 'sync',
            'session_driver' => $sessionDriver,
            'warnings' => $warnings,
            'recommendation' => 'Use Redis-backed cache and sessions, plus database or Redis queues with dedicated workers for 2,000 to 4,000 active users.',
        ];
    }

    private function shouldExposeReadyDetails(Request $request): bool
    {
        if (filter_var(env('HEALTH_EXPOSE_DETAILS', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $user = $request->user();
        if (!$user) {
            try {
                $user = Auth::guard('sanctum')->user();
            } catch (\Throwable) {
                $user = null;
            }
        }

        return $user !== null
            && method_exists($user, 'hasRole')
            && $user->hasRole('admin');
    }
}
