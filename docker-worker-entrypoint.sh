#!/bin/sh
set -e

cd /var/www/html

# Create storage structure (volumes may mount over and leave it empty)
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
touch storage/logs/laravel.log 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Cache config (non-fatal - worker will load config at runtime if this fails)
php artisan config:cache 2>/dev/null || true

exec "$@"
