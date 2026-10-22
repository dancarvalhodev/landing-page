FROM ubuntu:24.04 AS base

ARG DEBIAN_FRONTEND=noninteractive

WORKDIR /app

# Um RUN só: o catálogo do apt e o software-properties-common somem na mesma camada em que nasceram.
# O nome do mantenedor (Ondřej) quebra o add-apt-repository fora de um locale UTF-8.
RUN apt-get update \
    && apt-get install -y --no-install-recommends software-properties-common ca-certificates curl wget git nano zip unzip \
    && LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php \
    && apt-get install -y --no-install-recommends php8.5-fpm php8.5-cli php8.5-mysql php8.5-zip php8.5-intl php8.5-curl php8.5-xml php8.5-mbstring php8.5-gd php8.5-redis \
    && apt-get purge -y --auto-remove software-properties-common \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY config/php/fpm.conf /etc/php/8.5/fpm/pool.d/zz-docker.conf

# SIGQUIT deixa o PHP-FPM terminar as requisições em andamento antes de parar.
STOPSIGNAL SIGQUIT
EXPOSE 9000
CMD ["php-fpm8.5", "-F"]

FROM base AS dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.5-xdebug \
    && rm -rf /var/lib/apt/lists/*

FROM base AS prod

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY config/php/php.prod.ini /etc/php/8.5/fpm/conf.d/99-app.ini
COPY config/php/php.prod.ini /etc/php/8.5/cli/conf.d/99-app.ini

COPY www/ ./
RUN composer install --no-dev --classmap-authoritative --no-interaction

# O PHP-FPM atende como www-data e precisa gravar o cache do Twig.
RUN mkdir -p var/cache/twig && chown -R www-data:www-data var

# Sem a versão, o navegador guardaria o CSS antigo por um ano; por isso o build falha sem ela.
ARG ASSET_VERSION
RUN test -n "$ASSET_VERSION"
ENV ASSET_VERSION=$ASSET_VERSION
