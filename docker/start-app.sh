#!/bin/sh
set -eu

/usr/local/bin/bootstrap-runtime

exec php-fpm -F
