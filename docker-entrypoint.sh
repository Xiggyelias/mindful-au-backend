#!/bin/sh
set -e

cd /app

# Create storage structure (volumes may mount over and leave it empty)
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
# Ensure log file exists and is writable (fixes "Permission denied" with mounted volumes)
touch storage/logs/laravel.log 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
chmod 664 storage/logs/laravel.log 2>/dev/null || true

# Run migrations (skip if DB unreachable at startup; run manually if needed)
php /app/artisan migrate --force 2>/dev/null || true

# Discover packages (normally runs in composer post-autoload-dump; skipped at image build)
php /app/artisan package:discover --ansi 2>/dev/null || true

# Cache config and routes for production
php /app/artisan config:cache
php /app/artisan route:cache
php /app/artisan view:clear

# Single-container mode: run app + queue worker + scheduler via supervisord
# Set RUN_WORKER_AND_SCHEDULER=1 when deploying as one container (e.g. docker run without compose)
if [ "${RUN_WORKER_AND_SCHEDULER}" = "1" ]; then
  exec supervisord -c /app/supervisord.conf
fi

exec "$@"
