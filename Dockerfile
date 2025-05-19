FROM composer:2.8 AS vendor
WORKDIR /var/www/html

# Composer-Abhängigkeiten (ohne dev)
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --no-scripts --no-interaction

# ---------- Runtime-Stage ----------
FROM nginx:1.27-alpine AS app
# PHP-FPM installieren
RUN apk add --no-cache \
        php83 php83-fpm php83-opcache \
        php83-pdo php83-pdo_mysql \
        php83-mbstring php83-tokenizer \
        php83-fileinfo php83-gd php83-curl php83-zip \
        php83-dom php83-xmlwriter php83-xmlreader

# Code & vendor aus erster Stage
COPY --from=vendor /var/www/html /var/www/html
COPY . /var/www/html

# Nginx-VHost für Laravel
COPY ./deploy/nginx/laravel.conf /etc/nginx/conf.d/default.conf

# PHP-FPM Config
COPY ./deploy/php/conf/www.conf /etc/php83/php-fpm.d/www.conf

WORKDIR /var/www/html
RUN chmod -R ug+rwx storage bootstrap/cache \
 && php artisan optimize

EXPOSE 80
CMD ["/bin/sh", "-c", "php-fpm83 -D && nginx -g 'daemon off;'"]