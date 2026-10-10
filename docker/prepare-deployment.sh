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
source "$(dirname "${BASH_SOURCE[0]}")/deployment-database.sh"
source "$(dirname "${BASH_SOURCE[0]}")/deployment-images.sh"
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

# Only the tested 12.3 -> 13.0 migration and forward 13.0 patch updates are allowed.
# Never start the new major version on the original database volume.
DEPLOYMENT_PHASE='database preflight'
# shellcheck disable=SC2016
DATABASE_VERSION="$($COMPOSE exec -T db sh -c '
    export MYSQL_PWD="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
    test -n "$MYSQL_PWD"
    mariadb -uroot --batch --skip-column-names -e "SELECT VERSION()"
')"
echo "Database preflight: $DATABASE_VERSION"
DATABASE_MIGRATION_REQUIRED=0
if [[ "$DATABASE_VERSION" =~ ^12\.3\.[0-9]+-MariaDB ]]; then
    DATABASE_MIGRATION_REQUIRED=1
elif [[ ! "$DATABASE_VERSION" =~ ^13\.0\.[012]-MariaDB ]]; then
    echo 'Supported source versions: MariaDB 12.3.x or 13.0.0-13.0.2; other upgrades and downgrades require a separate migration.' >&2
    exit 1
fi
prepare_deployment_database

# This private resolved configuration contains secrets; never upload or print it.
$COMPOSE config > "$BACKUP_DIR/compose.yml"

# Give each application image its own code volume. Keep the previous volume
# for rollback instead of deleting a hard-coded volume from a guessed project.
DEPLOYMENT_PHASE='application volume and storage preflight'
OLD_APP_VOLUME="$(docker inspect maddrax-app --format '{{range .Mounts}}{{if and (eq .Destination "/var/www/html") (eq .Type "volume")}}{{.Name}}{{end}}{{end}}')"
[[ "$OLD_APP_VOLUME" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ ]] || exit 1
[[ "$(docker volume inspect "$OLD_APP_VOLUME" --format '{{ index .Labels "com.docker.compose.volume" }}')" = 'app_data' ]] || exit 1
[[ "$(docker volume inspect "$OLD_APP_VOLUME" --format '{{ index .Labels "com.docker.compose.project" }}')" = "$PROJECT_NAME" ]] || exit 1
# A new code volume must never hide uploads, private novels or sessions that
# were stored in the old code volume. Require an independent writable mount.
APP_STORAGE_MOUNT="$(docker inspect maddrax-app --format '{{range .Mounts}}{{if and (eq .Destination "/var/www/html/storage") (eq .RW true)}}{{.Type}}:{{.Source}}{{end}}{{end}}')"
if [[ ! "$APP_STORAGE_MOUNT" =~ ^(volume|bind):.+$ ]]; then
    # Older production stacks persist app/framework/logs individually.
    for storage_target in /var/www/html/storage/{app,framework,logs}; do
        storage_mount="$(docker inspect maddrax-app --format "{{range .Mounts}}{{if and (eq .Destination \"$storage_target\") (eq .RW true)}}{{.Type}}:{{.Source}}{{end}}{{end}}")"
        [[ "$storage_mount" =~ ^(volume|bind):.+$ ]] || {
            echo 'Deployment requires an independent writable storage mount (either all storage, or app/framework/logs individually); migrate and verify storage before replacing the code volume.' >&2
            exit 1
        }
    done
fi
typesense_container="$($COMPOSE ps --all --quiet typesense)"
DEPLOYMENT_PHASE='Typesense storage preflight'
TYPESENSE_DATA_TARGET="$(docker inspect "$typesense_container" --format '{{range .Mounts}}{{if and .RW (or (eq .Destination "/data") (eq .Destination "/typesense-data"))}}{{.Destination}}{{end}}{{end}}')"
[[ "$TYPESENSE_DATA_TARGET" = /data || "$TYPESENSE_DATA_TARGET" = /typesense-data ]] || {
    echo 'Deployment requires one writable Typesense data mount at /data or /typesense-data.' >&2
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

# Preserve the actual database volume on every release, including the release
# after the major upgrade. Never fall back to the original 12.3 volume then.
cat >> "$IMAGE_OVERRIDE.candidate" <<DATABASE_VOLUME
  deployment_db:
    external: true
    name: $NEW_DATABASE_VOLUME
DATABASE_VOLUME
# Insert a db override separately, avoiding duplicate YAML mapping keys.
sed -i '/^  db:$/a\    environment:\n      MARIADB_AUTO_UPGRADE: "1"\n      MARIADB_DISABLE_UPGRADE_BACKUP: ""\n    volumes:\n      - type: volume\n        source: deployment_db\n        target: /var/lib/mysql' "$IMAGE_OVERRIDE.candidate"

DEPLOY_COMPOSE_ARGS+=(-f "$IMAGE_OVERRIDE.candidate")
DEPLOYMENT_PHASE='service image verification'
$COMPOSE config --quiet
verify_deployment_images
DEPLOYMENT_METADATA_CHANGED=1
mv "$IMAGE_OVERRIDE.candidate" "$IMAGE_OVERRIDE"
DEPLOY_COMPOSE_ARGS[${#DEPLOY_COMPOSE_ARGS[@]}-1]="$IMAGE_OVERRIDE"

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
    $COMPOSE stop typesense
    docker run --rm --volumes-from "$typesense_container:ro" \
        --mount "type=bind,source=$BACKUP_DIR,target=/backup" \
        --entrypoint tar "$OMXFC_APP_IMAGE" -C "$TYPESENSE_DATA_TARGET" -czf /backup/typesense-data.tar.gz .
    test -s "$BACKUP_DIR/typesense-data.tar.gz"
    DEPLOYMENT_BACKUP_COMPLETE=1
    echo "Private rollback configuration and data backup: $BACKUP_DIR"
}
