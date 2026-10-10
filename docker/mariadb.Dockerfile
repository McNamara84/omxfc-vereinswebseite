FROM mariadb:13.0.2@sha256:f1bba652ba57bea3099ca2fe1af692af537c27d96e0bcde39dce29e2ba1ec4f3 AS mariadb

# Ubuntu includes a standalone Pebble service manager with an outdated Go
# runtime. MariaDB uses docker-entrypoint.sh and gosu instead, so remove the
# unused binary rather than distributing its vulnerable runtime.
RUN apt-get update \
    && apt-get upgrade -y \
    && test "$(dpkg-query -W -f='${Version}' mariadb-server)" = "$MARIADB_VERSION" \
    && rm -f /usr/bin/pebble \
    && rm -rf /var/lib/apt/lists/* \
    && gosu nobody true
