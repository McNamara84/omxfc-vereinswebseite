FROM composer:2.8 AS vendor

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --no-scripts --no-interaction

FROM nginx:1.27-alpine AS app

RUN apk add --no-cache \
      php83 php83-fpm php83-opcache \
      php83-pdo php83-pdo_mysql php83-mbstring php83-tokenizer \
      php83-fileinfo php83-gd php83-curl php83-zip php83-dom \
      php83-session php83-simplexml \
  # fehlendes Log-Verzeichnis anlegen + Rechte geben
  && mkdir -p /var/log/php83 \
  && chown -R nginx:nginx /var/log/php83 \
  # Original-Pool-Datei anpassen (User/Group/Port)
  && sed -i 's|^user = .*|user = nginx|'   /etc/php83/php-fpm.d/www.conf \
  && sed -i 's|^group = .*|group = nginx|' /etc/php83/php-fpm.d/www.conf \
  && sed -i 's|^listen = .*|listen = 0.0.0.0:9000|' /etc/php83/php-fpm.d/www.conf \
  # PHP-Limits ergänzen
  && printf "\nphp_admin_value[memory_limit] = 256M"        >> /etc/php83/php-fpm.d/www.conf \
  && printf "\nphp_admin_value[post_max_size] = 64M"        >> /etc/php83/php-fpm.d/www.conf \
  && printf "\nphp_admin_value[upload_max_filesize] = 64M"  >> /etc/php83/php-fpm.d/www.conf \
  && printf "\nphp_admin_value[max_execution_time] = 120\n" >> /etc/php83/php-fpm.d/www.conf

COPY --from=vendor /var/www/html /var/www/html
COPY .                     /var/www/html

COPY deploy/nginx/laravel.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html
RUN chown -R nginx:nginx storage bootstrap/cache \
 && chmod -R ug+rwx storage bootstrap/cache \
 && php artisan optimize

EXPOSE 80
CMD ["sh", "-c", "php-fpm83 -D && nginx -g 'daemon off;'"]
