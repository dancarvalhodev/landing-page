# O estágio base é cópia de dancarvalhodev/dockerfile-php-alpine (8.5/Dockerfile); só o WORKDIR muda.
FROM php:8.5-fpm-alpine AS base

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

FROM base AS dev

FROM base AS prod

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY config/php/php.prod.ini /usr/local/etc/php/php.ini

COPY www/ ./
RUN composer install --no-dev --classmap-authoritative --no-interaction

# O PHP-FPM atende como www-data e precisa gravar o cache do Twig.
RUN mkdir -p var/cache/twig && chown -R www-data:www-data var

# Sem a versão, o navegador guardaria o CSS antigo por um ano; por isso o build falha sem ela.
ARG ASSET_VERSION
RUN test -n "$ASSET_VERSION"
ENV ASSET_VERSION=$ASSET_VERSION
