#!/usr/bin/env bash
set -euo pipefail

cd /var/www

if [ "${APP_BOOTSTRAP:-1}" = "1" ]; then
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] vendor/ missing, running composer install"
        composer install --no-interaction --prefer-dist --no-progress
    fi

    if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] generating APP_KEY"
        php artisan key:generate --force
    fi

    if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
        echo "[entrypoint] running migrations"
        php artisan migrate --force --no-interaction || echo "[entrypoint] migrate failed, continuing"
    fi
fi

exec "$@"
