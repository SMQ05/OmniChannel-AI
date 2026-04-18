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
    su -s /bin/sh -c "php artisan package:discover --ansi" www-data
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    su -s /bin/sh -c "php artisan migrate --force" www-data
fi

if [ "${CACHE_LARAVEL_BOOTSTRAP:-true}" = "true" ]; then
    su -s /bin/sh -c "php artisan config:cache" www-data
    su -s /bin/sh -c "php artisan view:cache" www-data
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
