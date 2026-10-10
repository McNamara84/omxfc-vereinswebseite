FROM php:8.5.11-cli-trixie@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3

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
