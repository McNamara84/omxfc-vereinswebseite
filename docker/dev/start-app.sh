#!/bin/sh

set -eu

cd /var/www/html

sh /var/www/html/docker/dev/ensure-app-key.sh
sh /var/www/html/docker/dev/ensure-composer-deps.sh
sh /var/www/html/docker/dev/wait-for-mysql.sh

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache
touch storage/logs/laravel.log

if [ "${DOCKER_DEV_AUTO_MIGRATE:-1}" = "1" ]; then
    php artisan migrate --force --graceful
fi

php artisan storage:link >/dev/null 2>&1 || true

exec php-fpm