#!/bin/sh

set -eu

if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:CHANGE_ME" ]; then
    cat >&2 <<'EOF'
DOCKER_DEV_APP_KEY fehlt oder ist noch auf base64:CHANGE_ME gesetzt.
Trage in .env.docker.dev.local einen lokal generierten Schlüssel ein,
zum Beispiel über: npm run docker:dev:key:generate
EOF
    exit 1
fi