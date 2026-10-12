#!/bin/sh
set -eu

source_public="${1:-/source/public}"
target_public="${2:-/var/www/html/public}"
test -d "$source_public"
mkdir -p "$target_public"

# Leave mounted Vite builds and generated dependency assets in place.
find "$target_public" -mindepth 1 -maxdepth 1 ! -name build ! -name vendor -exec rm -rf -- {} +
find "$source_public" -mindepth 1 -maxdepth 1 ! -name build ! -name vendor -exec cp -a -t "$target_public" -- {} +
