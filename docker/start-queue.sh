#!/bin/sh
set -eu

cd /var/www/html

exec php artisan queue:work "${QUEUE_CONNECTION:-database}" \
    --queue="${QUEUE_NAMES:-webhooks,integrations,reminders}" \
    --sleep=1 \
    --tries=3 \
    --timeout=120 \
    --max-time=3600
