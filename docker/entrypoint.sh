#!/bin/sh
# Shared entrypoint for every pod role (web, worker, scheduler, release job).
#
# Warms Laravel's caches so pods start fast (spec §3.1). This happens at start-up rather
# than at build time because the cached config must hold the pod's ConfigMap and Secret
# values, which are never baked into the image (DEP-09). It never runs migrations:
# only the release job does that (DEP-08).
set -eu

cd /var/www/html

# storage/ and bootstrap/cache may be emptyDir volumes when the root filesystem is read-only.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs storage/app bootstrap/cache

if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan package:discover --quiet
    php artisan optimize --quiet
fi

exec "$@"
