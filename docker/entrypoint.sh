#!/bin/sh
set -e

# Stay in whatever WORKDIR the Dockerfile set (/app for the FrankenPHP image).

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/gitdumper \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    for i in $(seq 1 60); do
        if php -r "try{new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306}','${DB_USERNAME}','${DB_PASSWORD}');exit(0);}catch(Throwable \$e){exit(1);}" 2>/dev/null; then
            echo "Database is up."
            break
        fi
        sleep 2
    done
fi

php artisan package:discover --ansi
php artisan config:cache
php artisan view:cache
# route:cache is intentionally skipped: Route::inertia() and other macros use closures.

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
