# syntax=docker/dockerfile:1

# =============================================================================
# Production image for DKM Erdigma
# =============================================================================
#
# Three stages, so the shipped image carries neither Composer nor Node:
#
#   1. vendor  — PHP dependencies only (cached until composer.lock changes)
#   2. assets  — compiled CSS/JS via Vite (cached until package-lock.json changes)
#   3. runtime — PHP-FPM + Nginx, with only the built artefacts copied in
#
# Dependency manifests are copied before the source so a code-only change does
# not invalidate the dependency layers.
# =============================================================================


# -----------------------------------------------------------------------------
# Stage 1 — PHP dependencies
# -----------------------------------------------------------------------------
FROM composer:2.10 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# `--no-scripts` because artisan is not available yet; scripts run in the
# runtime stage once the full source is present.
#
# The extensions below are ignored here but genuinely present in the runtime
# stage — the official composer image simply does not ship them, and resolving
# dependencies does not need them. They are listed one by one rather than using
# a blanket `--ignore-platform-reqs` so that a requirement we have *not*
# installed still fails the build instead of surfacing at runtime.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --ignore-platform-req=ext-gd \
        --ignore-platform-req=ext-intl \
        --ignore-platform-req=ext-exif \
        --ignore-platform-req=ext-bcmath \
        --ignore-platform-req=ext-pcntl \
        --ignore-platform-req=ext-redis \
        --ignore-platform-req=ext-pdo_mysql

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# -----------------------------------------------------------------------------
# Stage 2 — Frontend assets
# -----------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

# Vite needs the vendor directory for the Laravel plugin's manifest handling.
COPY --from=vendor /app/vendor ./vendor

RUN npm run build


# -----------------------------------------------------------------------------
# Stage 3 — Runtime
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime

# `install-php-extensions` resolves the Alpine build dependencies for us, which
# is considerably less brittle than hand-rolling docker-php-ext-install.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip

RUN apk add --no-cache nginx supervisor tzdata \
    && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime \
    && echo "Asia/Jakarta" > /etc/timezone

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-dkm.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-dkm.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# PHP-FPM and Nginx both run as `www-data`, so only the two directories Laravel
# writes to are handed over — the rest of the source stays read-only.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
