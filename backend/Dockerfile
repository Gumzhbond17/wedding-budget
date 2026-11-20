# syntax=docker/dockerfile:1.7
#
# Laravel API on PHP 8.5-FPM. Built by docker-compose.yml, which also provides the
# "php-config" build context (../docker/php). Stand-alone:
#   docker build --build-context php-config=../docker/php -t wedding-budget-app .
#
# The image holds code and dependencies only: no .env, no APP_KEY, no credentials.

ARG PHP_VERSION=8.5

# --- base: PHP with the extensions the app needs ---------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

# install-php-extensions picks the right build deps per extension and removes them again.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# bcmath: BcMath\Number for all money math; pdo_mysql: MySQL; intl: Laravel's Number/locale
# helpers; opcache: compiled code cache. fcgi: cgi-fcgi for the php-fpm healthcheck.
RUN install-php-extensions bcmath intl pdo_mysql opcache \
    && apk add --no-cache fcgi \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=php-config app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --from=php-config zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# --- vendor: production dependencies and an optimized autoloader ----------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Lock files first: this layer is reused until the dependencies change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY . .
# Runs post-autoload-dump (package:discover), which only sees the production packages.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# --- app: runtime image -----------------------------------------------------------------
FROM base AS app

# Code is owned by root and read-only for the server user; only storage/ and bootstrap/cache
# are writable. A compromised PHP process cannot rewrite the application.
COPY --from=vendor /var/www/html /var/www/html
RUN rm -f .env .env.* \
    && mkdir -p storage/app/private/receipts \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=php-config --chmod=0755 entrypoint.sh /usr/local/bin/app-entrypoint

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=5 \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
