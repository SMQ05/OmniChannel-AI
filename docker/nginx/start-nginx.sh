#!/bin/sh
set -eu

port="${PORT:-8080}"
upstream="${APP_UPSTREAM:-app:9000}"

sed \
    -e "s|__PORT__|${port}|g" \
    -e "s|__APP_UPSTREAM__|${upstream}|g" \
    /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

exec nginx -g 'daemon off;'
