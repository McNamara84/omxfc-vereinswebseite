FROM php:8.6.0-cli-trixie@sha256:d84acf3155d907afaa5afa9b9457c5f12016b6dc7ade84659f40cdc505cc087d AS playwright-php

# php:8.6-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get upgrade -y \
	&& apt-get install -y --no-install-recommends libzip-dev libicu-dev libjpeg62-turbo-dev libpng-dev libwebp-dev sqlite3 \
	&& docker-php-ext-configure gd --with-jpeg --with-webp \
	&& docker-php-ext-install bcmath zip sockets intl gd pdo_mysql exif pcntl \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
