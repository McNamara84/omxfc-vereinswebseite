FROM php:8.5-cli

RUN docker-php-ext-install bcmath

WORKDIR /workspace