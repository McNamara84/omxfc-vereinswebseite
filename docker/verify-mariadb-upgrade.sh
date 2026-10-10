#!/usr/bin/env bash
# Real 12.3 -> 13.0 upgrade and rollback, with isolated throwaway data only.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/deployment-database.sh"
image="${1:?Pass the verified MariaDB 13.0.2 image}"
old_image='mariadb:12.3.3@sha256:2bdff1534a7e569fecaf4b10b66ad0d410806e382d9db29ba305448addda97c4'
id="omxfc-mariadb-upgrade-$(date -u +%s)-$$"
old_container="$id-old"
new_container="$id-new"
original="$id-original"
copy="$id-copy"
cleanup() {
    docker rm --force "$old_container" "$new_container" >/dev/null 2>&1 || true
    docker volume rm "$original" "$copy" >/dev/null 2>&1 || true
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

wait_for_database() {
    local container="$1" attempt
    for attempt in {1..90}; do
        if docker exec "$container" mariadb --protocol=tcp -h 127.0.0.1 -uroot \
            --batch --skip-column-names -e 'SELECT 1' >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    docker logs --tail 40 "$container" >&2
    return 1
}
query() {
    docker exec "$1" mariadb -uroot --batch --skip-column-names -e "$2"
}

docker volume create "$original" >/dev/null
docker volume create "$copy" >/dev/null
docker run --detach --name "$old_container" --network none \
    --mount "type=volume,source=$original,target=/var/lib/mysql" \
    --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 --env MARIADB_INITDB_SKIP_TZINFO=1 \
    --env MARIADB_DATABASE=upgrade_test --env MARIADB_USER=application \
    --env MARIADB_PASSWORD=fixture-only "$old_image" >/dev/null
wait_for_database "$old_container"
[[ "$(query "$old_container" 'SELECT VERSION()')" = 12.3.3-MariaDB* ]]
docker exec -i "$old_container" mariadb -uroot <<'SQL'
CREATE TABLE upgrade_test.stories (id INT PRIMARY KEY, title VARCHAR(100)) CHARACTER SET utf8mb4;
INSERT INTO upgrade_test.stories VALUES (1, 'Maddrax – Grüße 🐉');
CREATE TABLE upgrade_test.reviews (id INT PRIMARY KEY, story_id INT,
    FOREIGN KEY (story_id) REFERENCES stories(id));
INSERT INTO upgrade_test.reviews VALUES (1, 1);
CREATE DEFINER='application'@'%' VIEW upgrade_test.titles AS SELECT title FROM upgrade_test.stories;
CREATE DEFINER='application'@'%' FUNCTION upgrade_test.story_count() RETURNS INT READS SQL DATA
    RETURN (SELECT COUNT(*) FROM upgrade_test.stories);
CREATE DEFINER='application'@'%' EVENT upgrade_test.disabled_event
    ON SCHEDULE EVERY 1 HOUR DISABLE DO INSERT INTO upgrade_test.stories VALUES (99, 'event');
SET GLOBAL innodb_fast_shutdown=0;
SQL
docker stop --timeout 360 "$old_container" >/dev/null
[[ "$(docker inspect "$old_container" --format '{{.State.Running}}|{{.State.ExitCode}}|{{.State.OOMKilled}}')" = 'false|0|false' ]]
copy_mariadb_volume "$original" "$copy" "$image"
# The production copy helper must refuse an already populated target.
if copy_mariadb_volume "$original" "$copy" "$image" >/dev/null 2>&1; then
    echo 'Copy unexpectedly overwrote the populated target.' >&2
    exit 1
fi
docker run --detach --name "$new_container" --network none \
    --mount "type=volume,source=$copy,target=/var/lib/mysql" \
    --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 --env MARIADB_AUTO_UPGRADE=1 "$image" >/dev/null
wait_for_database "$new_container"
[[ "$(query "$new_container" 'SELECT VERSION()')" = 13.0.2-MariaDB* ]]
test "$(query "$new_container" 'SELECT title FROM upgrade_test.titles')" = 'Maddrax – Grüße 🐉'
test "$(query "$new_container" 'SELECT upgrade_test.story_count()')" = 1
test "$(query "$new_container" "SELECT STATUS FROM information_schema.EVENTS WHERE EVENT_SCHEMA='upgrade_test'")" = DISABLED
docker exec "$new_container" sh -c '
    MYSQL_PWD=fixture-only mariadb --protocol=tcp -h 127.0.0.1 -uapplication \
        -e "SELECT title FROM upgrade_test.titles; SELECT upgrade_test.story_count()" >/dev/null
'
if query "$new_container" 'INSERT INTO upgrade_test.reviews VALUES (2, 999)' >/dev/null 2>&1; then
    echo 'Foreign key constraint was lost during upgrade.' >&2
    exit 1
fi
docker exec "$new_container" mariadb-check -uroot --all-databases --check-upgrade >/dev/null
query "$new_container" "INSERT INTO upgrade_test.stories VALUES (2, '13.0 only')"
docker stop --timeout 360 "$new_container" >/dev/null
# Roll back to 12.3 without allowing that image to open the upgraded volume.
docker start "$old_container" >/dev/null
wait_for_database "$old_container"
[[ "$(query "$old_container" 'SELECT VERSION()')" = 12.3.3-MariaDB* ]]
test "$(query "$old_container" 'SELECT upgrade_test.story_count()')" = 1
test "$(query "$old_container" 'SELECT title FROM upgrade_test.titles')" = 'Maddrax – Grüße 🐉'
docker exec "$old_container" sh -c '
    MYSQL_PWD=fixture-only mariadb --protocol=tcp -h 127.0.0.1 -uapplication \
        -e "SELECT upgrade_test.story_count()" >/dev/null
'
echo 'MariaDB 12.3 -> 13.0.2: data, UTF-8, grants, views, routines, events, constraints and rollback verified.'
