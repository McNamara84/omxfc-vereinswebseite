FROM mariadb:13.0.2@sha256:f4054da26e3a9dd6c8b5cc97363e544408b15c0fb369d7359c25dc04826eb6e1 AS mariadb

# Ubuntu includes a standalone Pebble service manager with an outdated Go
# runtime. MariaDB uses docker-entrypoint.sh and gosu instead, so remove the
# unused binary rather than distributing its vulnerable runtime.
RUN apt-get update \
    && apt-get upgrade -y \
    && test "$(dpkg-query -W -f='${Version}' mariadb-server)" = "$MARIADB_VERSION" \
    && rm -f /usr/bin/pebble \
    && rm -rf /var/lib/apt/lists/* \
    && gosu nobody true
