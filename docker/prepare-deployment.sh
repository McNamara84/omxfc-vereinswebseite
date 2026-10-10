#!/usr/bin/env bash
# Sourced by deploy.yml after its exact-revision quality and image gates pass.
set -euo pipefail
umask 077

for image in "$OMXFC_APP_IMAGE" "$OMXFC_TYPESENSE_IMAGE" "$OMXFC_NGINX_IMAGE" "$OMXFC_DATABASE_IMAGE"; do
    [[ "$image" =~ ^ghcr\.io/mcnamara84/omxfc-vereinswebseite@sha256:[a-f0-9]{64}$ ]] || {
        echo 'Deployment requires verified immutable GHCR image digests.' >&2
        exit 1
    }
done

STACK_ROOT="$(pwd -P)"
[[ "$STACK_ROOT" != *[$'\r\n|&:']* ]] || exit 1
IMAGE_OVERRIDE="$STACK_ROOT/.deployment/images.compose.yml"
BACKUP_DIR="$STACK_ROOT/.deployment/backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$STACK_ROOT/.deployment/backups"
mkdir "$BACKUP_DIR"
source "$(dirname "${BASH_SOURCE[0]}")/deployment-recovery.sh"
source "$(dirname "${BASH_SOURCE[0]}")/deployment-retention.sh"
cp .env.production "$BACKUP_DIR/environment"
if [[ -f "$IMAGE_OVERRIDE" ]]; then
    cp "$IMAGE_OVERRIDE" "$BACKUP_DIR/previous-images.compose.yml"
fi

# Docker records the actual Compose inputs, including an existing override.
# Reuse these files and retain the project name to preserve volumes/networks.
REGISTERED_FILES="$(docker inspect maddrax-app --format '{{ index .Config.Labels "com.docker.compose.project.config_files" }}')"
PROJECT_NAME="$(docker inspect maddrax-app --format '{{ index .Config.Labels "com.docker.compose.project" }}')"
[[ "$PROJECT_NAME" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || exit 1
IFS=',' read -r -a FILES <<< "$REGISTERED_FILES"
DEPLOY_COMPOSE_ARGS=(--project-name "$PROJECT_NAME")
BASE_FILES=()
for file in "${FILES[@]}"; do
    [[ "$file" = "$IMAGE_OVERRIDE" ]] && continue
    [[ "$file" = "$STACK_ROOT/"* && "$file" != *[$'\r\n|&:']* && -f "$file" ]] || {
        echo 'The running app uses a Compose file outside this stack or a missing file; reconcile it before deployment.' >&2
        exit 1
    }
    BASE_FILES+=("$file")
    DEPLOY_COMPOSE_ARGS+=(-f "$file")
done
[[ "${#BASE_FILES[@]}" -gt 0 ]] || exit 1

omxfc_compose() {
    docker compose --env-file .env.production "${DEPLOY_COMPOSE_ARGS[@]}" "$@"
}
COMPOSE=omxfc_compose

# This release performs a same-series database patch. Major upgrades require
# their own tested backup/restore migration before this deployment can proceed.
# shellcheck disable=SC2016
DATABASE_VERSION="$($COMPOSE exec -T db sh -c '
    export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
    test -n "$MYSQL_PWD"
    mariadb -uroot --batch --skip-column-names -e "SELECT VERSION()"
')"
[[ "$DATABASE_VERSION" =~ ^13\.0\.[012](-.*)?$ ]] || {
    echo 'Database deployment requires MariaDB 13.0.0–13.0.2. Complete and verify the separate major-version migration first.' >&2
    exit 1
}
echo "Database preflight: $DATABASE_VERSION"

# This private resolved configuration contains secrets; never upload or print it.
$COMPOSE config > "$BACKUP_DIR/compose.yml"

# Give each application image its own code volume. Keep the previous volume
# for rollback instead of deleting a hard-coded volume from a guessed project.
OLD_APP_VOLUME="$(docker inspect maddrax-app --format '{{range .Mounts}}{{if and (eq .Destination "/var/www/html") (eq .Type "volume")}}{{.Name}}{{end}}{{end}}')"
[[ "$OLD_APP_VOLUME" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ ]] || exit 1
[[ "$(docker volume inspect "$OLD_APP_VOLUME" --format '{{ index .Labels "com.docker.compose.volume" }}')" = 'app_data' ]] || exit 1
[[ "$(docker volume inspect "$OLD_APP_VOLUME" --format '{{ index .Labels "com.docker.compose.project" }}')" = "$PROJECT_NAME" ]] || exit 1
# A new code volume must never hide uploads, private novels or sessions that
# were stored in the old code volume. Require an independent writable mount.
APP_STORAGE_MOUNT="$(docker inspect maddrax-app --format '{{range .Mounts}}{{if and (eq .Destination "/var/www/html/storage") (eq .RW true)}}{{.Type}}:{{.Source}}{{end}}{{end}}')"
[[ "$APP_STORAGE_MOUNT" =~ ^(volume|bind):.+$ ]] || {
    echo 'Deployment requires an independent writable /var/www/html/storage mount; migrate and verify storage before replacing the code volume.' >&2
    exit 1
}
export OMXFC_APP_VOLUME="${PROJECT_NAME}_app_data_${OMXFC_APP_IMAGE##*:}"
printf '%s\n' "$PROJECT_NAME" "$OLD_APP_VOLUME" "$OMXFC_APP_VOLUME" "$(date -u +%s)" > "$BACKUP_DIR/retention.meta"
printf 'services:\n' > "$BACKUP_DIR/images.yml"
for service in app queue scheduler db typesense nginx; do
    container="$($COMPOSE ps --all --quiet "$service")"
    [[ -n "$container" ]] || { echo "Missing deployment service: $service" >&2; exit 1; }
    old_image="$(docker inspect "$container" --format '{{.Image}}')"
    rollback_tag="omxfc-rollback:$(basename "$BACKUP_DIR")-$service"
    docker image tag "$old_image" "$rollback_tag"
    printf '  %s:\n    image: %s\n' "$service" "$rollback_tag" >> "$BACKUP_DIR/images.yml"
done
printf 'volumes:\n  app_data:\n    name: %s\n' "$OLD_APP_VOLUME" >> "$BACKUP_DIR/images.yml"

cat > "$IMAGE_OVERRIDE.candidate" <<'COMPOSE_IMAGES'
services:
  app:
    image: ${OMXFC_APP_IMAGE:?OMXFC_APP_IMAGE is required}
  queue:
    image: ${OMXFC_APP_IMAGE:?OMXFC_APP_IMAGE is required}
  scheduler:
    image: ${OMXFC_APP_IMAGE:?OMXFC_APP_IMAGE is required}
  typesense:
    image: ${OMXFC_TYPESENSE_IMAGE:?OMXFC_TYPESENSE_IMAGE is required}
  nginx:
    image: ${OMXFC_NGINX_IMAGE:?OMXFC_NGINX_IMAGE is required}
  db:
    image: ${OMXFC_DATABASE_IMAGE:?OMXFC_DATABASE_IMAGE is required}
volumes:
  app_data:
    name: ${OMXFC_APP_VOLUME:?OMXFC_APP_VOLUME is required}
COMPOSE_IMAGES

DEPLOY_COMPOSE_ARGS+=(-f "$IMAGE_OVERRIDE.candidate")
$COMPOSE config --quiet
DEPLOYMENT_METADATA_CHANGED=1
mv "$IMAGE_OVERRIDE.candidate" "$IMAGE_OVERRIDE"
DEPLOY_COMPOSE_ARGS[${#DEPLOY_COMPOSE_ARGS[@]}-1]="$IMAGE_OVERRIDE"

for service in app queue scheduler; do
    [[ "$($COMPOSE config --images "$service")" = "$OMXFC_APP_IMAGE" ]] || exit 1
done
[[ "$($COMPOSE config --images typesense)" = "$OMXFC_TYPESENSE_IMAGE" ]] || exit 1
[[ "$($COMPOSE config --images nginx)" = "$OMXFC_NGINX_IMAGE" ]] || exit 1
[[ "$($COMPOSE config --images db)" = "$OMXFC_DATABASE_IMAGE" ]] || exit 1

# Persist only non-secret deployment metadata for subsequent manual Compose runs.
COMPOSE_FILE_VALUE="$(IFS=:; echo "${BASE_FILES[*]}:$IMAGE_OVERRIDE")"
for key in OMXFC_APP_IMAGE OMXFC_TYPESENSE_IMAGE OMXFC_NGINX_IMAGE OMXFC_DATABASE_IMAGE OMXFC_APP_VOLUME COMPOSE_PROJECT_NAME COMPOSE_FILE; do
    case "$key" in
        COMPOSE_PROJECT_NAME) value="$PROJECT_NAME" ;;
        COMPOSE_FILE) value="$COMPOSE_FILE_VALUE" ;;
        *) value="${!key}" ;;
    esac
    if grep -q "^$key=" .env.production; then
        sed -i "s|^$key=.*|$key=$value|" .env.production
    else
        printf '\n%s=%s\n' "$key" "$value" >> .env.production
    fi
done

backup_deployment_data() {
    # Call only after maintenance mode, graceful queue stop and scheduler stop.
    # The database credentials expand only inside the database container.
    # shellcheck disable=SC2016
    $COMPOSE exec -T db sh -c '
        export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
        database="${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}"
        test -n "$MYSQL_PWD" && test -n "$database"
        mariadb-dump -uroot --single-transaction --routines --events --databases "$database"
    ' > "$BACKUP_DIR/database.sql"
    test -s "$BACKUP_DIR/database.sql"

    typesense_container="$($COMPOSE ps --all --quiet typesense)"
    [[ "$(docker inspect "$typesense_container" --format '{{range .Mounts}}{{if eq .Destination "/data"}}{{.Destination}}{{end}}{{end}}')" = '/data' ]] || exit 1
    $COMPOSE stop typesense
    docker run --rm --volumes-from "$typesense_container:ro" \
        --mount "type=bind,source=$BACKUP_DIR,target=/backup" \
        --entrypoint tar "$OMXFC_APP_IMAGE" -C /data -czf /backup/typesense-data.tar.gz .
    test -s "$BACKUP_DIR/typesense-data.tar.gz"
    DEPLOYMENT_BACKUP_COMPLETE=1
    echo "Private rollback configuration and data backup: $BACKUP_DIR"
}
