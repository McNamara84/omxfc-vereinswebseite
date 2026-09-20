# Composer wird als eigener, unveränderlich gepinnter Build-Stage eingebunden.
FROM composer:2.10.3@sha256:a5f59b9fd2faf31218632be4809dc6491761085e8064c31dc3b84378c48c248b AS composer-bin

# Gemeinsame PHP-Basis für Production und Development
FROM php:8.5.10-fpm-bookworm@sha256:8e780a6e59508f418c7729681468322a2ce7d7cfe4266025054f41bbe85e3928 AS php-base

# Install available security updates before adding required system packages.
# The base image digest stays pinned, while rebuilt images still receive fixes
# published by Debian between upstream PHP image releases.
RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
    git \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libicu-dev \
    libzip-dev \
    libsqlite3-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd sockets intl zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# PHP upload limits (3D-Modelle bis 100 MB + Thumbnail bis 2 MB)
RUN echo 'upload_max_filesize = 110M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 120M' >> /usr/local/etc/php/conf.d/uploads.ini

# Ensure PHP-FPM runs as www-data
RUN sed -i 's/^user = .*/user = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = .*/group = www-data/' /usr/local/etc/php-fpm.d/www.conf

# Production-Abhängigkeiten werden einmal reproduzierbar aus dem Lockfile
# installiert und sowohl vom Asset-Builder als auch vom finalen Image genutzt.
FROM php-base AS production-vendor

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Build Stage für Node/Vite. Die Tailwind-Quellen aus Composer-Paketen stammen
# aus demselben frischen Vendor-Baum, der später im Production-Image landet.
FROM node:26.9.0-alpine3.24@sha256:dbaa92e5758cbbcf85d65d5403fdb530fe3442cbe8c6dbfb7ef23365450d5070 AS node-base

RUN npm install --global npm@12.0.2 --ignore-scripts \
    && test "$(npm --version)" = "12.0.2"

FROM node-base AS node-builder

WORKDIR /app

COPY package.json package-lock.json* .npmrc ./

RUN npm ci

COPY . .
COPY --from=production-vendor /var/www/html/vendor ./vendor

RUN npm run build

# Development target used by docker-compose for the Vite process. This keeps
# local containers on the same reviewed npm version and install-script policy.
FROM node-base AS node-development

WORKDIR /workspace

# PHP Production Stage
FROM production-vendor AS production

# Copy project files
COPY . .

# Copy built assets from node stage
COPY --from=node-builder /app/public/build /var/www/html/public/build

# CRITICAL: Remove any cached config files that might contain old provider references
RUN rm -rf bootstrap/cache/*.php \
    && rm -rf storage/framework/cache/* \
    && rm -rf storage/framework/sessions/* \
    && rm -rf storage/framework/views/*

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Copy Livewire assets manually to avoid symlink issues
RUN mkdir -p public/vendor/livewire \
    && cp -r vendor/livewire/livewire/dist/* public/vendor/livewire/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Create storage directories if they don't exist
RUN mkdir -p storage/app/public \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/testing \
    && mkdir -p storage/logs \
    && touch storage/logs/laravel.log \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

# PHP Development Stage
FROM php-base AS development

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install development dependencies, but defer project scripts until the code is present.
RUN composer install --optimize-autoloader --no-scripts --no-interaction

# Copy project files
COPY . .

# Generate autoload files once the full application is available.
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p public/vendor/livewire \
    && cp -r vendor/livewire/livewire/dist/* public/vendor/livewire/ \
    && mkdir -p storage/app/public \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/testing \
    && mkdir -p storage/logs \
    && touch storage/logs/laravel.log \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/vendor/livewire \
    && chmod -R 775 storage bootstrap/cache public/vendor/livewire

EXPOSE 9000
