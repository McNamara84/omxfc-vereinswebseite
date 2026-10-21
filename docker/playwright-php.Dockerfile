FROM php:8.5.12-cli-trixie@sha256:ce5d49625b9c50020dc1ce3c9cf4cdfa31cba31b6651e72daa3e6f5a010d234a AS playwright-php

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get upgrade -y \
	&& apt-get install -y --no-install-recommends libzip-dev libicu-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
	&& docker-php-ext-configure gd --with-jpeg --with-webp \
	&& docker-php-ext-install bcmath zip sockets intl gd pdo_mysql exif pcntl \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
