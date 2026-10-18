FROM mariadb:13.0.2@sha256:0e4bb0f339feba125d1154fbacd36c16ec8518f52e44e1f82ecec7d6771caf39 AS mariadb

# Ubuntu includes a standalone Pebble service manager with an outdated Go
# runtime. MariaDB uses docker-entrypoint.sh and gosu instead, so remove the
# unused binary rather than distributing its vulnerable runtime.
RUN apt-get update \
    && apt-get upgrade -y \
    && test "$(dpkg-query -W -f='${Version}' mariadb-server)" = "$MARIADB_VERSION" \
    && rm -f /usr/bin/pebble \
    && rm -rf /var/lib/apt/lists/* \
    && gosu nobody true
