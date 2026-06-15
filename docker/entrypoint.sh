#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "[entrypoint] Starting balaidiklat container..."

# Ensure writable runtime directories exist (named volumes may mount empty)
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Generate APP_KEY if it is missing (first boot without one configured)
if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] APP_KEY not set in environment; generating an ephemeral key."
    php artisan key:generate --force --no-interaction || true
fi

# Link public storage if not already linked
if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction || true
fi

# Wait for the database to accept connections before migrating
if [ "${DB_CONNECTION:-mysql}" != "sqlite" ]; then
    echo "[entrypoint] Waiting for database ${DB_HOST}:${DB_PORT}..."
    ATTEMPTS=0
    MAX_ATTEMPTS="${DB_WAIT_RETRIES:-30}"
    until php -r '
        $host=getenv("DB_HOST"); $port=getenv("DB_PORT") ?: 3306;
        $c=@fsockopen($host,(int)$port,$e,$s,2);
        if($c){fclose($c);exit(0);} exit(1);
    ' 2>/dev/null; do
        ATTEMPTS=$((ATTEMPTS + 1))
        if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
            echo "[entrypoint] Database not reachable after ${MAX_ATTEMPTS} attempts; continuing anyway."
            break
        fi
        sleep 2
    done
fi

# Run migrations automatically unless disabled
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running database migrations..."
    php artisan migrate --force --no-interaction || echo "[entrypoint] Migration step failed (continuing)."
fi

# Cache framework configuration for production performance
echo "[entrypoint] Optimizing framework caches..."
php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true

echo "[entrypoint] Boot complete. Handing over to: $*"
exec "$@"
