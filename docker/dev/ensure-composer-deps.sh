#!/bin/sh

set -eu

cd /var/www/html

if [ ! -f composer.lock ]; then
    exit 0
fi

lock_hash_file="vendor/.composer-lock.sha1"
current_lock_hash="$(sha1sum composer.lock | awk '{print $1}')"
stored_lock_hash=""

if [ -f "$lock_hash_file" ]; then
    stored_lock_hash="$(cat "$lock_hash_file")"
fi

if [ ! -f vendor/autoload.php ] || [ "$stored_lock_hash" != "$current_lock_hash" ]; then
    composer install --optimize-autoloader --no-interaction
    mkdir -p vendor
    printf '%s' "$current_lock_hash" > "$lock_hash_file"
fi

if [ -d vendor/livewire/livewire/dist ]; then
    mkdir -p public/vendor/livewire
    cp -R vendor/livewire/livewire/dist/. public/vendor/livewire/
fi