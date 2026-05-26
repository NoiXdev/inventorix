#!/bin/sh
set -e

# Runs as `www-data` (set by Dockerfile). Storage and bootstrap/cache are
# owned by www-data at build time, so no chown step is needed at runtime.

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/gitdumper \
    bootstrap/cache

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    db_ready=0
    for i in $(seq 1 60); do
        # Credentials passed via env, not argv, so they don't leak in /proc.
        if php -r 'try{new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));exit(0);}catch(Throwable $e){exit(1);}' 2>/dev/null; then
            echo "Database is up."
            db_ready=1
            break
        fi
        sleep 2
    done
    if [ "$db_ready" -ne 1 ]; then
        echo "Database at ${DB_HOST}:${DB_PORT:-3306} never became reachable. Aborting." >&2
        exit 1
    fi
fi

php artisan package:discover --ansi
php artisan filament:upgrade --ansi
php artisan config:cache
php artisan event:cache
php artisan view:cache
# route:cache is intentionally skipped: Route::inertia() and other macros use closures.

# RUN_MIGRATIONS=true is the default; set to "false" on multi-replica
# deployments and run migrations as a one-shot job to avoid races.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
