<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
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

    private function enforceProductionSecurityDefaults(): void
    {
        $appEnv = Str::lower((string) config('app.env', env('APP_ENV', 'production')));
        if ($appEnv !== 'production') {
            return;
        }

        if ((bool) config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG was enabled in production and has been forced off for safety.');
        }
    }
}
