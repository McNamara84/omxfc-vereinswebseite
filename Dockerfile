# Build Stage für Node/Vite
FROM node:26.4.0-alpine3.24 AS node-builder
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

# Gemeinsame PHP-Basis für Production und Development
FROM php:8.5.7-fpm AS php-base

# Install required system packages
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libsqlite3-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd sockets \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest@sha256:7725eb4545c438629ae8bde3ef0bb9a5038ef566126ad878442a69007242d267 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# PHP upload limits (3D-Modelle bis 100 MB + Thumbnail bis 2 MB)
RUN echo 'upload_max_filesize = 110M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 120M' >> /usr/local/etc/php/conf.d/uploads.ini

# Ensure PHP-FPM runs as www-data
RUN sed -i 's/^user = .*/user = www-data/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = .*/group = www-data/' /usr/local/etc/php-fpm.d/www.conf

# PHP Production Stage
FROM php-base AS production

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

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