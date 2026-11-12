FROM mariadb:13.0.2@sha256:54f493ea0d60b621a5bff84e5b20100f8b86b6efa6b79158e04756c5194a5e4d AS mariadb

# Ubuntu includes a standalone Pebble service manager with an outdated Go
# runtime. MariaDB uses docker-entrypoint.sh and gosu instead, so remove the
# unused binary rather than distributing its vulnerable runtime.
RUN apt-get update \
    && apt-get upgrade -y \
    && test "$(dpkg-query -W -f='${Version}' mariadb-server)" = "$MARIADB_VERSION" \
    && rm -f /usr/bin/pebble \
    && rm -rf /var/lib/apt/lists/* \
    && gosu nobody true
