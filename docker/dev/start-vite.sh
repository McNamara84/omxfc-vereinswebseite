#!/bin/sh

set -eu

cd /workspace

cleanup() {
    rm -f public/hot
}

lock_hash_file="node_modules/.package-lock.sha1"
current_lock_hash="$(sha1sum package-lock.json | awk '{print $1}')"
stored_lock_hash=""

if [ -f "$lock_hash_file" ]; then
    stored_lock_hash="$(cat "$lock_hash_file")"
fi

if [ ! -d node_modules/.bin ] || [ "$stored_lock_hash" != "$current_lock_hash" ]; then
    npm ci
    mkdir -p node_modules
    printf '%s' "$current_lock_hash" > "$lock_hash_file"
fi

trap cleanup EXIT INT TERM

npm run dev -- --host 0.0.0.0 --port "${VITE_PORT:-5173}" --strictPort