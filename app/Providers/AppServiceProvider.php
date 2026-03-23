<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->enforceMysqlDatabaseDriver();
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

    private function enforceMysqlDatabaseDriver(): void
    {
        $driver = Str::lower((string) config('database.default', env('DB_CONNECTION', 'mysql')));

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Unsupported DB_CONNECTION "%s". Mindful AU backend supports MySQL/MariaDB only.',
            $driver !== '' ? $driver : '(empty)'
        ));
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
