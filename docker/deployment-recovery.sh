#!/usr/bin/env bash
# Sourced by prepare-deployment.sh. Never downgrade an existing data volume.
DEPLOYMENT_METADATA_CHANGED=0
DEPLOYMENT_SERVICES_TOUCHED=0
DEPLOYMENT_INFRASTRUCTURE_CHANGED=0
DEPLOYMENT_BACKUP_COMPLETE=0
QUEUES_PAUSED=0
RECOVERY_ENVIRONMENT="$BACKUP_DIR/environment"

recovery_compose() {
    docker compose --env-file "$RECOVERY_ENVIRONMENT" "${RECOVERY_COMPOSE_ARGS[@]}" "$@"
}

wait_for_recovery_database() {
    local attempt
    for attempt in {1..60}; do
        # Credentials expand inside the database container, never in host logs.
        # shellcheck disable=SC2016
        if recovery_compose exec -T db sh -c '
            export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
            test -n "$MYSQL_PWD"
            mariadb -uroot --batch --skip-column-names -e "SELECT 1"
        ' >/dev/null 2>&1; then
            return 0
        fi
        sleep 5
    done
    echo 'Recovery database did not become ready.' >&2
    return 1
}

recover_deployment() {
    local recovery_id recovery_db recovery_typesense key value service
    echo 'Deployment failed; restoring the previous application.' >&2
    RECOVERY_COMPOSE_ARGS=(--project-name "$PROJECT_NAME" -f "$BACKUP_DIR/compose.yml" -f "$BACKUP_DIR/images.yml")

    if [[ "$DEPLOYMENT_INFRASTRUCTURE_CHANGED" = 1 ]]; then
        [[ "$DEPLOYMENT_BACKUP_COMPLETE" = 1 ]] || return 1
        # Stop every writer before restoring the pre-deployment data. Keep the
        # failed deployment's original volumes intact for diagnosis.
        $COMPOSE stop --timeout 360 queue scheduler app nginx typesense db || return 1
        recovery_id="$(basename "$BACKUP_DIR")"
        recovery_db="${PROJECT_NAME}_recovery_db_${recovery_id}"
        recovery_typesense="${PROJECT_NAME}_recovery_typesense_${recovery_id}"
        docker volume create --label "com.docker.compose.project=$PROJECT_NAME" "$recovery_db" >/dev/null || return 1
        docker volume create --label "com.docker.compose.project=$PROJECT_NAME" "$recovery_typesense" >/dev/null || return 1
        docker run --rm --mount "type=volume,source=$recovery_typesense,target=/restore" \
            --mount "type=bind,source=$BACKUP_DIR,target=/backup,readonly" \
            --entrypoint tar "$OMXFC_APP_IMAGE" -C /restore -xzf /backup/typesense-data.tar.gz || return 1

        # Compose merges volumes by container target, preserving other mounts.
        # Old MariaDB runs against a fresh volume populated from its own dump.
        cat > "$BACKUP_DIR/recovery.yml" <<RECOVERY_VOLUMES
services:
  db:
    volumes:
      - type: volume
        source: recovery_db
        target: /var/lib/mysql
  typesense:
    volumes:
      - type: volume
        source: recovery_typesense
        target: /data
volumes:
  recovery_db:
    external: true
    name: $recovery_db
  recovery_typesense:
    external: true
    name: $recovery_typesense
RECOVERY_VOLUMES
        # Start containers with durable Compose paths, so their recorded
        # config_files labels never refer to an expiring backup directory.
        cp "$BACKUP_DIR/compose.yml" "$STACK_ROOT/.deployment/recovered.compose.yml" || return 1
        cp "$BACKUP_DIR/images.yml" "$STACK_ROOT/.deployment/recovered.images.yml" || return 1
        cp "$BACKUP_DIR/recovery.yml" "$STACK_ROOT/.deployment/recovered.volumes.yml" || return 1
        cp "$BACKUP_DIR/environment" "$STACK_ROOT/.deployment/recovered.environment" || return 1
        RECOVERY_ENVIRONMENT="$STACK_ROOT/.deployment/recovered.environment"
        RECOVERY_COMPOSE_ARGS=(--project-name "$PROJECT_NAME" \
            -f "$STACK_ROOT/.deployment/recovered.compose.yml" \
            -f "$STACK_ROOT/.deployment/recovered.images.yml" \
            -f "$STACK_ROOT/.deployment/recovered.volumes.yml")
        recovery_compose config --quiet || return 1
        recovery_compose up -d --force-recreate --no-deps db || return 1
        wait_for_recovery_database || return 1
        # shellcheck disable=SC2016
        recovery_compose exec -T db sh -c '
            export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
            test -n "$MYSQL_PWD"
            mariadb -uroot
        ' < "$BACKUP_DIR/database.sql" || return 1
        recovery_compose up -d --force-recreate --no-deps typesense app nginx || return 1
    elif [[ "$DEPLOYMENT_SERVICES_TOUCHED" = 1 ]]; then
        # Backup failures precede replacement: the original containers and
        # data are still present. Do not recreate or restore incomplete dumps.
        recovery_compose start db typesense app nginx || return 1
        wait_for_recovery_database || return 1
    fi

    cp "$BACKUP_DIR/environment" "$STACK_ROOT/.env.production" || return 1
    if [[ -f "$BACKUP_DIR/previous-images.compose.yml" ]]; then
        cp "$BACKUP_DIR/previous-images.compose.yml" "$IMAGE_OVERRIDE" || return 1
    else
        rm -f -- "$IMAGE_OVERRIDE" || return 1
    fi
    if [[ "$DEPLOYMENT_INFRASTRUCTURE_CHANGED" = 1 ]]; then
        # Persist those same inputs for subsequent manual Compose operations.
        value="$STACK_ROOT/.deployment/recovered.compose.yml:$STACK_ROOT/.deployment/recovered.images.yml:$STACK_ROOT/.deployment/recovered.volumes.yml"
        key=COMPOSE_FILE
        if grep -q "^$key=" "$STACK_ROOT/.env.production"; then
            sed -i "s|^$key=.*|$key=$value|" "$STACK_ROOT/.env.production" || return 1
        else
            printf '\n%s=%s\n' "$key" "$value" >> "$STACK_ROOT/.env.production" || return 1
        fi
    fi

    if [[ "$DEPLOYMENT_SERVICES_TOUCHED" = 1 ]]; then
        # Compiled views and caches may have been written by the failed code.
        recovery_compose exec -T app php artisan optimize:clear || return 1
        recovery_compose exec -T app php artisan config:cache || return 1
        recovery_compose exec -T app php artisan route:cache || return 1
        recovery_compose exec -T app php artisan view:cache || return 1
        if recovery_compose exec -T app php artisan queue:resume --help --no-ansi 2>/dev/null | grep -Fq -- '--all'; then
            recovery_compose exec -T app php artisan queue:resume --all || return 1
        fi
        recovery_compose up -d --no-deps queue scheduler || return 1
        for service in app queue scheduler db typesense nginx; do
            [[ "$(recovery_compose ps --status running --services "$service")" = "$service" ]] || return 1
        done
        recovery_compose exec -T app php artisan up || return 1
    fi
    echo 'Previous application restored; the deployment still reports failure.' >&2
}

deployment_exit() {
    local status="$1"
    trap - EXIT INT TERM
    if [[ "$status" != 0 && "$DEPLOYMENT_METADATA_CHANGED" = 1 ]]; then
        # Recovery has explicit checks: do not let one failed command silently
        # continue, or mask the original deployment failure with a zero exit.
        if ! recover_deployment; then
            echo "AUTOMATIC RECOVERY FAILED. Keep maintenance enabled and recover using $BACKUP_DIR." >&2
        fi
    fi
    exit "$status"
}

trap 'deployment_exit "$?"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
