#!/bin/sh
set -eu

cd /var/www/html

while true; do
    php artisan schedule:run --no-interaction
    sleep 60
done
