#!/bin/sh

set -eu

cd /var/www/html

sh /var/www/html/docker/dev/ensure-app-key.sh
sh /var/www/html/docker/dev/ensure-composer-deps.sh
sh /var/www/html/docker/dev/wait-for-mysql.sh

until php artisan migrate:status >/dev/null 2>&1; do
    sleep 2
done

exec php artisan queue:work --tries=3 --timeout=120 --sleep=3