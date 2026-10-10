#!/usr/bin/env bash
# Exercise the real entrypoint and persistent data without ports or host mounts.
set -euo pipefail

image="${1:?Pass the MariaDB image to verify}"
container="omxfc-mariadb-smoke-$(date -u +%s)-$$"
volume="$container-data"
cleanup() {
    docker rm --force "$container" >/dev/null 2>&1 || true
    docker volume rm "$volume" >/dev/null 2>&1 || true
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

docker volume create "$volume" >/dev/null
docker run --detach --name "$container" --network none \
    --mount "type=volume,source=$volume,target=/var/lib/mysql" \
    --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
    --env MARIADB_DATABASE=omxfc_image_test "$image" >/dev/null

wait_for_database() {
    for ((attempt = 0; attempt < 60; attempt++)); do
        # TCP avoids treating the entrypoint's temporary initialization server
        # as the final server, which would make the immediate restart unsafe.
        if docker exec "$container" mariadb --protocol=tcp -h 127.0.0.1 -uroot \
            --batch --skip-column-names -e 'SELECT 1' >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    docker logs --tail 40 "$container" >&2
    return 1
}

wait_for_database
docker exec "$container" sh -c '
    test ! -e /usr/bin/pebble
    test "$(gosu mysql id -u)" = "$(id -u mysql)"
'
expected="$(docker exec "$container" printenv MARIADB_VERSION)"
expected="${expected#*:}"
expected="${expected%%+*}"
actual="$(docker exec "$container" mariadb -uroot --batch --skip-column-names -e 'SELECT VERSION()')"
[[ "$actual" = "$expected"-* ]]
docker exec "$container" mariadb -uroot -e '
    CREATE TABLE omxfc_image_test.security_smoke (id INT PRIMARY KEY);
    INSERT INTO omxfc_image_test.security_smoke VALUES (1);
'
docker restart --timeout 30 "$container" >/dev/null
wait_for_database
test "$(docker exec "$container" mariadb -uroot --batch --skip-column-names \
    -e 'SELECT COUNT(*) FROM omxfc_image_test.security_smoke WHERE id = 1')" = 1
printf 'MariaDB %s: initialization, privilege drop and data after restart verified.\n' "$actual"
