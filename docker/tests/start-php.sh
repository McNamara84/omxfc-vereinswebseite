#!/bin/sh
set -eu
cd /var/www/html

# Copy source into this disposable container while keeping native Linux vendor
# files. The host workspace (including development storage/DBs) is read-only.
for directory in app config lang resources routes tests tools docker .github; do
    # Remove deleted/renamed sources from the image as well as updating files.
    rm -rf "$directory"
    cp -a "/source/$directory" .
done
mkdir -p deploy/nginx storage/app/private/protokolle
cp /source/deploy/nginx/laravel.conf deploy/nginx/
cp /source/storage/app/private/maddrax.json /source/storage/app/private/hardcovers.json storage/app/private/
cp /source/storage/app/private/protokolle/2026-07-15-jhv.pdf storage/app/private/protokolle/
# Windows checkouts can contain CRLF; shell scripts execute in Linux here.
find docker -name '*.sh' -exec sed -i 's/\r$//' {} +
for directory in factories migrations schema seeders; do
    rm -rf "database/$directory"
    cp -a "/source/database/$directory" database/
done
cp /source/bootstrap/app.php /source/bootstrap/providers.php bootstrap/
cp /source/artisan /source/server.php /source/composer.json /source/composer.lock .
cp /source/package.json /source/package-lock.json /source/vite.config.js /source/vitest.config.js /source/playwright.config.js /source/playwright.benchmark.config.js .
cp /source/phpunit*.xml .
cp /source/.env.testing .env
sh docker/tests/sync-public.sh
if [ "${TEST_USE_SHARED_ASSETS:-0}" != '1' ] && [ -d /source/public/build ]; then
    mkdir -p public/build
    cp -a /source/public/build/. public/build/
fi
if [ -d tests/Browser ]; then
    mkdir -p tests/Browser/Screenshots/Sliders tests/Browser/Screenshots/ImageDiffView tests/Browser/Traces
fi
mkdir -p storage/app/public storage/framework/cache storage/framework/views storage/framework/sessions storage/framework/testing storage/logs bootstrap/cache

# Keep one ephemeral key for every process in this disposable test container.
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    export APP_KEY
fi

if [ "$#" -eq 0 ]; then
    set -- php vendor/bin/pest --compact --timeout=1800
fi
exec "$@"
