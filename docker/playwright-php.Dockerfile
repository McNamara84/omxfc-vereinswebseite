FROM php:8.5.13-cli-trixie@sha256:994d78db9de7808cfe1a077e4f266b333570393918832dcd7427c71c7c618e56 AS playwright-php

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get upgrade -y \
	&& apt-get install -y --no-install-recommends libzip-dev libicu-dev libjpeg62-turbo-dev libpng-dev libwebp-dev sqlite3 \
	&& docker-php-ext-configure gd --with-jpeg --with-webp \
	&& docker-php-ext-install bcmath zip sockets intl gd pdo_mysql exif pcntl \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
