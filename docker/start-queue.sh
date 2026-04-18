#!/bin/sh
set -eu

/usr/local/bin/bootstrap-runtime

exec su-exec www-data php artisan queue:work "${QUEUE_CONNECTION:-database}" \
    --queue="${QUEUE_NAMES:-webhooks,integrations,reminders}" \
    --sleep=1 \
    --tries=3 \
    --timeout="${QUEUE_TIMEOUT:-60}" \
    --max-time="${QUEUE_MAX_TIME:-3600}"
