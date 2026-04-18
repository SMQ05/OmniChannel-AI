#!/bin/sh
set -eu

cd /var/www/html

mkdir -p bootstrap/cache
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

chown -R www-data:www-data bootstrap/cache storage

if [ "${RUN_PACKAGE_DISCOVER:-true}" = "true" ]; then
    su-exec www-data php artisan package:discover --ansi
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    su-exec www-data php artisan migrate --force
fi

if [ "${CACHE_LARAVEL_BOOTSTRAP:-true}" = "true" ]; then
    su-exec www-data php artisan config:cache
    su-exec www-data php artisan view:cache
fi
