#!/bin/sh
set -eu

# Temporary PHP 8.6 test-only exception until a stable Xdebug supports it.
# This is the official 3.6.0alpha1 release, pinned by commit and archive hash.
reference=653e0443683790877b99e4e7a8adedf84b8e298b
checksum=526696b80adfbbb9908df90710a1735807bc52eb01c607bff8ba5473059fd687
destination="${1:?Usage: install-xdebug.sh <absolute coverage directory>}"
case "$destination" in
    /*) ;;
    *) printf '%s\n' 'Coverage directory must be absolute.' >&2; exit 1 ;;
esac

php tools/ci/verify-php86.php
php_config_binary="$(command -v php-config8.6 || command -v php-config || true)"
if [ -z "$php_config_binary" ] || ! "$php_config_binary" --version | grep -q '^8\.6\.' \
    || ! command -v phpize >/dev/null 2>&1; then
    # setup-php's Ubuntu runners may not have the matching development tools yet.
    sudo apt-get update
    sudo apt-get install -y --no-install-recommends php8.6-dev autoconf make gcc pkg-config
fi
phpize_binary="$(command -v phpize8.6 || command -v phpize)"
php_config_binary="$(command -v php-config8.6 || command -v php-config)"
case "$("$php_config_binary" --version)" in
    8.6.*) ;;
    *) printf '%s\n' 'Coverage must be compiled against PHP 8.6.' >&2; exit 1 ;;
esac
build_directory="$(mktemp -d /tmp/omxfc-xdebug.XXXXXXXX)"
trap 'rm -rf "$build_directory"' EXIT HUP INT TERM
curl --fail --silent --show-error --location \
    "https://codeload.github.com/xdebug/xdebug/tar.gz/$reference" \
    --output "$build_directory/source.tgz"
printf '%s  %s\n' "$checksum" "$build_directory/source.tgz" | sha256sum --check --strict
mkdir "$build_directory/source"
tar -xzf "$build_directory/source.tgz" -C "$build_directory/source" --strip-components=1
(
    cd "$build_directory/source"
    "$phpize_binary"
    ./configure --enable-xdebug --with-php-config="$php_config_binary"
    make -j2
)
mkdir -p "$destination"
cp "$build_directory/source/modules/xdebug.so" "$destination/xdebug.so"
printf 'zend_extension="%s/xdebug.so"\nxdebug.mode=coverage\n' "$destination" > "$destination/xdebug.ini"

XDEBUG_MODE=coverage PHP_INI_SCAN_DIR=":$destination" php tools/ci/verify-php-coverage.php
