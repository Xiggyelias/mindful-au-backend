#!/usr/bin/env sh
set -eu

cd /var/www/html
APP_USER="${APP_USER:-www-data}"
DB_CONNECTION="${DB_CONNECTION:-sqlite}"
DB_WAIT_FOR_CONNECTION="${DB_WAIT_FOR_CONNECTION:-true}"
DB_WAIT_MAX_ATTEMPTS="${DB_WAIT_MAX_ATTEMPTS:-30}"
DB_WAIT_SLEEP_SECONDS="${DB_WAIT_SLEEP_SECONDS:-2}"

run_as_app_user() {
  if [ "$(id -u)" = "0" ]; then
    su-exec "$APP_USER" "$@"
    return
  fi

  "$@"
}

sqlite_database_path() {
  configured_db_path="${DB_DATABASE:-}"

  if [ -z "$configured_db_path" ]; then
    echo "/var/www/html/database/database.sqlite"
    return
  fi

  if [ "$configured_db_path" = ":memory:" ]; then
    echo "$configured_db_path"
    return
  fi

  case "$configured_db_path" in
    /*)
      echo "$configured_db_path"
      ;;
    *)
      echo "/var/www/html/$configured_db_path"
      ;;
  esac
}

wait_for_database() {
  if [ "$DB_WAIT_FOR_CONNECTION" != "true" ]; then
    return
  fi

  if [ "$DB_CONNECTION" = "sqlite" ]; then
    sqlite_path="$(sqlite_database_path)"
    if [ "$sqlite_path" != ":memory:" ]; then
      mkdir -p "$(dirname "$sqlite_path")"
      touch "$sqlite_path"
      if [ "$(id -u)" = "0" ]; then
        chown "$APP_USER":"$APP_USER" "$sqlite_path" || true
      fi
      chmod ug+rw "$sqlite_path" || true
    fi
    return
  fi

  if [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "pgsql" ]; then
    attempt=1
    while true; do
      if php -r '
        $driver = getenv("DB_CONNECTION") ?: "mysql";
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $port = getenv("DB_PORT") ?: ($driver === "pgsql" ? "5432" : "3306");
        $database = getenv("DB_DATABASE") ?: "";
        $username = getenv("DB_USERNAME") ?: "";
        $password = getenv("DB_PASSWORD") ?: "";

        try {
          if ($driver === "mysql") {
            new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password, [PDO::ATTR_TIMEOUT => 5]);
            exit(0);
          }
          if ($driver === "pgsql") {
            new PDO("pgsql:host={$host};port={$port};dbname={$database}", $username, $password, [PDO::ATTR_TIMEOUT => 5]);
            exit(0);
          }
          exit(0);
        } catch (Throwable $e) {
          exit(1);
        }
      ' >/dev/null 2>&1; then
        break
      fi

      if [ "$attempt" -ge "$DB_WAIT_MAX_ATTEMPTS" ]; then
        echo "Database is not reachable after ${DB_WAIT_MAX_ATTEMPTS} attempts (connection: ${DB_CONNECTION})." >&2
        exit 1
      fi

      echo "Waiting for database connection (${attempt}/${DB_WAIT_MAX_ATTEMPTS})..."
      attempt=$((attempt + 1))
      sleep "$DB_WAIT_SLEEP_SECONDS"
    done
  fi
}

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache \
  database

if [ "$(id -u)" = "0" ]; then
  chown -R "$APP_USER":"$APP_USER" storage bootstrap/cache database || true
fi
chmod -R ug+rwx storage bootstrap/cache database || true

if [ ! -L public/storage ]; then
  run_as_app_user php artisan storage:link --relative >/dev/null 2>&1 || true
fi

wait_for_database

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  run_as_app_user php artisan migrate --force --no-interaction
fi

if [ "${APP_ENV:-production}" = "production" ] && [ "${LARAVEL_OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
  run_as_app_user php artisan config:cache --no-interaction
  run_as_app_user php artisan route:cache --no-interaction
  run_as_app_user php artisan view:cache --no-interaction
  run_as_app_user php artisan event:cache --no-interaction
fi

if [ "${1:-}" = "php-fpm" ] || [ "${1:-}" = "php-fpm8" ] || [ "${1:-}" = "supervisord" ]; then
  exec "$@"
fi

if [ "$(id -u)" = "0" ]; then
  exec su-exec "$APP_USER" "$@"
fi

exec "$@"
