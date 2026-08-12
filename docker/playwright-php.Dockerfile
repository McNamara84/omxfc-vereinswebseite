FROM php:8.5-cli@sha256:54d82ff9be6bd198145e90c917fc9b2e24230b42e52def8deb3554baf61c451a

# php:8.5-cli ships with sqlite3 and pdo_sqlite already enabled.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libzip-dev \
	&& docker-php-ext-install bcmath zip \
	&& rm -rf /var/lib/apt/lists/* \
	&& php -m | grep -q '^pdo_sqlite$' \
	&& php -m | grep -q '^sqlite3$'

WORKDIR /workspace
