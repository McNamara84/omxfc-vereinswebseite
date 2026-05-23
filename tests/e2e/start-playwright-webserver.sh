#!/bin/sh
set -eu

cd /var/www/html

sh /var/www/html/docker/dev/ensure-composer-deps.sh

cleanup() {
    rm -f /var/www/html/public/playwright.hot
}

trap cleanup EXIT INT TERM

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache
touch storage/logs/laravel.log

printf '%s' "${VITE_DEV_SERVER_URL:-http://localhost:${VITE_PORT:-5173}}" > /var/www/html/public/playwright.hot

RUN_TOKEN="${PLAYWRIGHT_RUN_TOKEN:-local-default}"
READY_FILE="/var/www/html/storage/framework/testing/playwright-ready-${RUN_TOKEN}.flag"

while [ ! -f "$READY_FILE" ]; do
    sleep 1
done

php -S "0.0.0.0:${PLAYWRIGHT_PORT:-8001}" -t public server.php