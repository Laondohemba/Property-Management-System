# syntax=docker/dockerfile:1.7
#
# PMS container image (spec §3.1). One image, four pod roles (§3.2): the default command
# starts Supervisor (Nginx + PHP-FPM) for the web role; worker, scheduler and release pods
# override the command in their manifests.
#
# Build:  docker build --build-arg APP_VERSION=$(git rev-parse --short HEAD) -t pms:<git-sha> .
#
# Base images and Composer are pinned (DEP-10). Bump them deliberately, never to a moving tag.

ARG NODE_VERSION=24.22.3-alpine3.24
ARG PHP_VERSION=8.3.36-fpm-alpine3.24
ARG COMPOSER_VERSION=2.11.2

# ---------------------------------------------------------------------------------------
# Stage 1: compile the Angular SPA. Node and its packages never reach the runtime image.
# ---------------------------------------------------------------------------------------
FROM node:${NODE_VERSION} AS angular-compiler
WORKDIR /build
COPY resources/angular/package.json resources/angular/package-lock.json ./
# The lock file is written by the npm pinned in package.json ("packageManager"); older npm
# releases reject its bundled optional WebAssembly dependencies.
RUN npm install -g npm@11.6.1 --no-audit --no-fund && npm ci --no-audit --no-fund
COPY resources/angular/ .
RUN npm run build -- --configuration=production

# ---------------------------------------------------------------------------------------
# Stage 2: Composer dependencies (production only, from the committed composer.lock).
# ---------------------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION} AS composer-binary

FROM php:${PHP_VERSION} AS production-image

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION} \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_STDERR_FORMATTER="Monolog\\Formatter\\JsonFormatter" \
    COMPOSER_ALLOW_SUPERUSER=1

# Runtime packages from the reference build, plus the libraries our extensions link against.
# Build headers are installed as a virtual package and removed in the same layer.
RUN apk add --no-cache nginx supervisor mariadb-client redis libpng libzip icu-libs zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers libpng-dev libzip-dev icu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip opcache intl bcmath pcntl \
    && apk del .build-deps \
    && rm -rf /tmp/* /usr/src/php*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-pms.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-pms.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/pms-entrypoint

WORKDIR /var/www/html
COPY --from=composer-binary /usr/bin/composer /usr/bin/composer

# Dependencies first so this layer is reused when only application code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && rm /usr/bin/composer

# The compiled SPA is served by Nginx straight from public/ (spec §11.2).
COPY --from=angular-compiler /build/dist/pms/browser/ ./public/

# Non-root runtime (DEP-14): www-data owns only the directories Laravel writes to.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data

# Unprivileged port (hardening note in §3.1). The Service maps port 80 to it.
EXPOSE 8080

ENTRYPOINT ["pms-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
