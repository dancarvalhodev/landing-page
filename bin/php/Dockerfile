# Cópia de dancarvalhodev/dockerfile-php-alpine (8.5/Dockerfile); só o WORKDIR muda.
FROM php:8.5-fpm-alpine

WORKDIR /app

RUN apk update && apk upgrade
RUN apk add build-base linux-headers wget curl git nano zip bash
RUN apk add libzip-dev icu-dev libcurl curl-dev libxml2 libxml2-dev oniguruma-dev libpng-dev autoconf

RUN docker-php-ext-install mysqli pdo pdo_mysql zip intl sockets curl xml mbstring gd
RUN docker-php-ext-enable pdo_mysql zip intl

RUN pecl install xdebug redis
RUN docker-php-ext-enable xdebug redis
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apk cache clean

CMD ["php-fpm"]
