FROM php:8.5.10-cli-bookworm@sha256:97c41462ce985a92b3fcb29dc02d5da568c7865ccb9fb9b0f712e192ad09e1bf

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libzip-dev \
	&& docker-php-ext-install bcmath zip \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
