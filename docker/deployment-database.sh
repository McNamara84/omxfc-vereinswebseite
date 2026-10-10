#!/usr/bin/env bash
# Sourced by prepare-deployment.sh; also used by the real-image upgrade test.
copy_mariadb_volume() {
    local source_volume="$1" target_volume="$2" utility_image="$3"
    [[ "$source_volume" != "$target_volume" ]] || return 1
    # Cold copy only: the caller must cleanly stop the source server first.
    # Never overwrite an existing target, or let the utility access the network.
    docker run --rm --network none --read-only \
        --mount "type=volume,source=$source_volume,target=/source,readonly" \
        --mount "type=volume,source=$target_volume,target=/target" \
        --entrypoint bash "$utility_image" -c '
            set -euo pipefail
            test -d /source/mysql
            test -z "$(find /target -mindepth 1 -maxdepth 1 -print -quit)"
            tar --numeric-owner -C /source -cpf - . | tar --numeric-owner -C /target -xpf -
            test -d /target/mysql
        '
}

prepare_deployment_database() {
    local container role
    container="$($COMPOSE ps --all --quiet db)"
    OLD_DATABASE_VOLUME="$(docker inspect "$container" --format '{{range .Mounts}}{{if and (eq .Destination "/var/lib/mysql") (eq .Type "volume")}}{{.Name}}{{end}}{{end}}')"
    [[ "$OLD_DATABASE_VOLUME" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ ]] || {
        echo 'Database deployment requires a named /var/lib/mysql volume.' >&2
        return 1
    }
    [[ "$(docker volume inspect "$OLD_DATABASE_VOLUME" --format '{{ index .Labels "com.docker.compose.project" }}')" = "$PROJECT_NAME" ]] || return 1
    role="$(docker volume inspect "$OLD_DATABASE_VOLUME" --format '{{ index .Labels "com.docker.compose.volume" }}')"
    [[ "$role" = db_data || "$role" = deployment_db ]] || {
        echo 'Database volume ownership could not be verified.' >&2
        return 1
    }
    NEW_DATABASE_VOLUME="$OLD_DATABASE_VOLUME"
    if [[ "$DATABASE_MIGRATION_REQUIRED" = 1 ]]; then
        # A custom datadir or nested mount would make a one-volume copy incomplete.
        # shellcheck disable=SC2016
        [[ "$($COMPOSE exec -T db sh -c '
            export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
            mariadb -uroot --batch --skip-column-names -e "SELECT @@datadir"
        ')" = /var/lib/mysql/ ]] || return 1
        if docker inspect "$container" --format '{{range .Mounts}}{{println .Destination}}{{end}}' | grep -q '^/var/lib/mysql/'; then
            echo 'Nested database mounts need a separate migration.' >&2
            return 1
        fi
        NEW_DATABASE_VOLUME="${PROJECT_NAME}_database_$(basename "$BACKUP_DIR")"
        if docker volume inspect "$NEW_DATABASE_VOLUME" >/dev/null 2>&1; then
            echo 'The database upgrade target already exists; refusing to overwrite it.' >&2
            return 1
        fi
    fi
    printf '%s\n' "$PROJECT_NAME" "$OLD_DATABASE_VOLUME" "$NEW_DATABASE_VOLUME" > "$BACKUP_DIR/database-volumes.meta"
}

stage_deployment_database() {
    [[ "$DATABASE_MIGRATION_REQUIRED" = 1 ]] || return 0
    local container
    echo 'Preparing the MariaDB 12.3 -> 13.0 upgrade on a separate data volume...'
    # Crash recovery must use the old server version. Flush all InnoDB data.
    # shellcheck disable=SC2016
    $COMPOSE exec -T db sh -c '
        export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
        mariadb -uroot -e "SET GLOBAL innodb_fast_shutdown=0"
    '
    $COMPOSE stop --timeout 360 db
    container="$($COMPOSE ps --all --quiet db)"
    [[ "$(docker inspect "$container" --format '{{.State.Running}}|{{.State.ExitCode}}|{{.State.OOMKilled}}')" = 'false|0|false' ]] || {
        echo 'MariaDB did not shut down cleanly; refusing the major upgrade.' >&2
        return 1
    }
    docker volume create --label "com.docker.compose.project=$PROJECT_NAME" \
        --label com.docker.compose.volume=deployment_db "$NEW_DATABASE_VOLUME" >/dev/null
    copy_mariadb_volume "$OLD_DATABASE_VOLUME" "$NEW_DATABASE_VOLUME" "$OMXFC_DATABASE_IMAGE"
    echo "Original database retained for rollback: $OLD_DATABASE_VOLUME"
}

wait_for_deployment_database() {
    local attempt version
    for attempt in {1..60}; do
        # TCP avoids the entrypoint's temporary server during mariadb-upgrade.
        # shellcheck disable=SC2016
        if version="$($COMPOSE exec -T db sh -c '
            export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
            test -n "$MYSQL_PWD"
            mariadb --protocol=tcp -h 127.0.0.1 -uroot --batch --skip-column-names -e "SELECT VERSION()"
        ' 2>/dev/null)"; then
            [[ "$version" = 13.0.2-MariaDB* ]] || {
                echo "Unexpected deployed MariaDB version: $version" >&2
                return 1
            }
            echo "Database ready after upgrade: $version"
            return 0
        fi
        echo "Waiting for MariaDB initialization/upgrade... ($attempt/60)"
        sleep 5
    done
    echo 'Database did not become ready after initialization/upgrade.' >&2
    $COMPOSE logs --tail 40 db >&2
    return 1
}
