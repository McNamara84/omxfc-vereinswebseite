FROM php:8.5.11-cli-trixie@sha256:19642e172d3a542225225e202ddc2c11f67bdcbddf147b676c49338609b9290f

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get upgrade -y \
	&& apt-get install -y --no-install-recommends libzip-dev \
	&& docker-php-ext-install bcmath zip \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
