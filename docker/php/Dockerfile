# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4

FROM composer:2 AS composer

# ------------------------------------------------------------
# Base PHP image: shared layer for app, worker, scheduler
# ------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN apk add --no-cache \
        bash \
        git \
        icu-data-full \
        icu-libs \
        libpq \
        libzip \
        linux-headers \
        postgresql-client \
        su-exec \
        tini \
        tzdata

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        openssl-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ARG APP_UID=1000
ARG APP_GID=1000
RUN addgroup -g ${APP_GID} app \
    && adduser -D -u ${APP_UID} -G app -s /bin/bash app \
    && mkdir -p /var/www \
    && chown -R app:app /var/www

WORKDIR /var/www

# ------------------------------------------------------------
# Vendor stage: install composer deps in isolation for cache
# ------------------------------------------------------------
FROM base AS vendor

COPY --chown=app:app composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# ------------------------------------------------------------
# Final app image
# ------------------------------------------------------------
FROM base AS app

COPY --from=vendor --chown=app:app /var/www/vendor /var/www/vendor
COPY --chown=app:app . /var/www

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache

USER app

EXPOSE 9000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
