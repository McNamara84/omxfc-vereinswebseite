FROM php:8.5-cli@sha256:58b996c35ce0511cdbaa1fc0476a194fd0221097d721ff7df5af0b6f1a3d0202

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN docker-php-ext-install bcmath \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace