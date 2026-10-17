FROM mariadb:13.0.2@sha256:fe70467ededa74ba50d127b5822c2d554d56be27a90b3ef23da9624d993b1a74 AS mariadb

# Ubuntu includes a standalone Pebble service manager with an outdated Go
# runtime. MariaDB uses docker-entrypoint.sh and gosu instead, so remove the
# unused binary rather than distributing its vulnerable runtime.
RUN apt-get update \
    && apt-get upgrade -y \
    && test "$(dpkg-query -W -f='${Version}' mariadb-server)" = "$MARIADB_VERSION" \
    && rm -f /usr/bin/pebble \
    && rm -rf /var/lib/apt/lists/* \
    && gosu nobody true
