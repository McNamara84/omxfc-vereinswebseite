FROM php:8.5-cli@sha256:9ebdf4c28ab12c02085e171c31e22ac5f7bbb6a9f6927e3bc3dfe7ee23df51e0

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libzip-dev \
	&& docker-php-ext-install bcmath zip \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
