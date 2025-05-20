# 1. Composer Stage: nur Dependencies ohne dev, keine Scripts
FROM composer:2.8 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --no-scripts --no-interaction

# 2. Node Build Stage: Assets bauen
FROM node:20 AS nodebuild
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
# falls noch weitere Dateien für das Frontend gebraucht werden, z.B. tailwind.config.js, diese ebenfalls kopieren!
# COPY tailwind.config.js ./
RUN npm run build

# 3. PHP-FPM Runtime Stage
FROM php:8.3-fpm-alpine AS app

# Alpine dependencies für Composer etc.
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    git

# PHP Extensions installieren (!!!)
RUN docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl

# (Optional, falls du z.B. gd brauchst)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

WORKDIR /var/www/html

# Kopiere composer dependencies
COPY --from=composer /app/vendor ./vendor
# Kopiere gebaute Vite-Assets
COPY --from=nodebuild /app/public/build ./public/build
# Kopiere den kompletten Quellcode (außer node_modules, außer vendor)
COPY . .

# Rechte setzen
RUN chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R ug+rwx storage bootstrap/cache

# Optional: Laravel optimieren (je nach Deployment-Prozess)
RUN php artisan optimize

# Expose PHP-FPM Port (für Reverse Proxy/Nginx)
EXPOSE 9000

CMD ["php-fpm"]

