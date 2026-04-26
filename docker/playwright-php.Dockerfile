FROM php:8.5-cli

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN docker-php-ext-install bcmath \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace