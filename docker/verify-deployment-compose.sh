#!/usr/bin/env bash
# Exercise the real Compose dependency model and PHP validator with test data only.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/deployment-images.sh"
image="${1:?Pass the PHP application image to use for verification}"
container="omxfc-compose-smoke-$(date -u +%s)-$$"
temp_root="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
directory="$(mktemp -d "$temp_root/omxfc-compose-smoke.XXXXXX")"
cleanup() {
    docker rm --force "$container" >/dev/null 2>&1 || true
    if [[ "$directory" = "$temp_root/omxfc-compose-smoke."* && ! -L "$directory" && "$(realpath -- "$directory")" = "$directory" ]]; then
        rm -rf -- "$directory"
    fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

export OMXFC_APP_IMAGE="$image"
export OMXFC_TYPESENSE_IMAGE='ghcr.io/mcnamara84/omxfc-vereinswebseite@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'
export OMXFC_NGINX_IMAGE='ghcr.io/mcnamara84/omxfc-vereinswebseite@sha256:eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'
export OMXFC_DATABASE_IMAGE='ghcr.io/mcnamara84/omxfc-vereinswebseite@sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd'
printf '%s\n' 'PUBLIC_TEST_VALUE=fixture-config-must-stay-private' > "$directory/fixture.env"
cat > "$directory/base.yml" <<'COMPOSE_BASE'
services:
  init-app-data:
    image: ${OMXFC_APP_IMAGE}
  app:
    image: fixture:old-app
    environment:
      PUBLIC_TEST_VALUE: ${PUBLIC_TEST_VALUE}
    depends_on:
      init-app-data:
        condition: service_completed_successfully
      db:
        condition: service_started
      typesense:
        condition: service_started
  queue:
    image: fixture:old-app
    depends_on: [db, typesense]
  scheduler:
    image: fixture:old-app
    depends_on: [db, typesense]
  nginx:
    image: fixture:old-nginx
    depends_on: [app]
  db:
    image: fixture:old-db
  typesense:
    image: fixture:old-typesense
COMPOSE_BASE
cat > "$directory/images.yml" <<'COMPOSE_IMAGES'
services:
  app:
    image: ${OMXFC_APP_IMAGE}
  queue:
    image: ${OMXFC_APP_IMAGE}
  scheduler:
    image: ${OMXFC_APP_IMAGE}
  nginx:
    image: ${OMXFC_NGINX_IMAGE}
  db:
    image: ${OMXFC_DATABASE_IMAGE}
  typesense:
    image: ${OMXFC_TYPESENSE_IMAGE}
COMPOSE_IMAGES

# Git Bash on Windows needs Windows filenames for the Docker CLI.
compose_path() {
    if command -v cygpath >/dev/null 2>&1; then cygpath -w "$1"; else printf '%s\n' "$1"; fi
}
environment="$(compose_path "$directory/fixture.env")"
base="$(compose_path "$directory/base.yml")"
overlay="$(compose_path "$directory/images.yml")"
bad_overlay="$(compose_path "$directory/bad-image.yml")"
compose_args=(-f "$base" -f "$overlay")
check_compose() {
    docker compose --project-name "$container" --env-file "$environment" "${compose_args[@]}" "$@"
}
COMPOSE=check_compose

# No services from the model are started. Only a temporary PHP process runs.
docker run --detach --name "$container" --network none --read-only \
    --entrypoint php "$image" -r 'sleep(600);' >/dev/null
if [[ "$($COMPOSE config --images app)" = "$OMXFC_APP_IMAGE" ]]; then
    echo 'Fixture did not reproduce dependency images in compose config.' >&2
    exit 1
fi
output="$(verify_deployment_images "$container")"
[[ "$output" != *fixture-config-must-stay-private* ]]
echo "$output"
for service in app queue scheduler typesense nginx db; do
    printf 'services:\n  %s:\n    image: fixture:unscanned\n' "$service" > "$directory/bad-image.yml"
    compose_args=(-f "$base" -f "$overlay" -f "$bad_overlay")
    if verify_deployment_images "$container" > "$directory/result" 2>&1; then
        echo "Unscanned image was accepted for $service." >&2
        exit 1
    fi
    grep -Fq "Deployment image mismatch for service: $service." "$directory/result"
    if grep -Fq 'fixture-config-must-stay-private' "$directory/result"; then exit 1; fi
done
echo 'Real Docker Compose: dependency images accepted; all six service images individually enforced; private configuration stays out of logs.'
