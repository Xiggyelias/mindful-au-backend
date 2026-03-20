#!/bin/sh
set -e

cd /var/www/html

# Create storage structure (volumes may mount over and leave it empty)
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
# Ensure log file exists and is writable (fixes "Permission denied" with mounted volumes)
touch storage/logs/laravel.log 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
chmod 664 storage/logs/laravel.log 2>/dev/null || true

# Run migrations (skip if DB unreachable at startup; run manually if needed)
php /var/www/html/artisan migrate --force 2>/dev/null || true

# Cache config and routes for production
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan view:clear

# Single-container mode: run app + queue worker + scheduler via supervisord
# Set RUN_WORKER_AND_SCHEDULER=1 when deploying as one container (e.g. docker run without compose)
if [ "${RUN_WORKER_AND_SCHEDULER}" = "1" ]; then
  exec supervisord -c /var/www/html/supervisord.conf
fi

exec "$@"
