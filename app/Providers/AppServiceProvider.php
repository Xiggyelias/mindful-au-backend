<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        $this->enforceProductionSecurityDefaults();
        $this->registerSlowQueryLogging();

        $shouldPreventLazyLoading = filter_var(
            env('PREVENT_LAZY_LOADING', false),
            FILTER_VALIDATE_BOOL
        );

        if (!$shouldPreventLazyLoading) {
            return;
        }

        Model::preventLazyLoading(true);
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('Lazy loading detected', [
                'model' => $model::class,
                'relation' => $relation,
            ]);
        });
    }

    private function registerSlowQueryLogging(): void
    {
        $thresholdMs = max(0, (int) env('DB_SLOW_QUERY_MS', 1000));
        if ($thresholdMs <= 0) {
            return;
        }

        DB::listen(function ($query) use ($thresholdMs): void {
            $duration = (float) ($query->time ?? 0);
            if ($duration < $thresholdMs) {
                return;
            }

            $sql = $query->sql;
            if (strlen($sql) > 2000) {
                $sql = substr($sql, 0, 2000) . '…';
            }

            Log::warning('Slow database query', [
                'duration_ms' => $duration,
                'sql' => $sql,
            ]);
        });
    }

    private function enforceProductionSecurityDefaults(): void
    {
        $appEnv = Str::lower((string) config('app.env', env('APP_ENV', 'production')));
        if ($appEnv !== 'production') {
            return;
        }

        // Force HTTPS for all generated URLs (signed file downloads, redirects, etc.)
        // Prevents ERR_FAILED caused by http:// URLs being served over an https:// origin.
        \Illuminate\Support\Facades\URL::forceScheme('https');

        if ((bool) config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG was enabled in production and has been forced off for safety.');
        }
    }
}
