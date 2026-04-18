#!/bin/sh
set -eu

/usr/local/bin/bootstrap-runtime

exec su-exec www-data php artisan schedule:work --no-interaction
