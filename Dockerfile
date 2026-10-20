# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.5 AS php

RUN install-php-extensions gd intl pdo_mysql zip exif opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/99-app.ini

WORKDIR /app

COPY composer.json composer.lock ./
COPY mate ./mate
RUN --mount=type=cache,target=/root/.composer \
    composer install --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative


FROM node:24-bookworm-slim AS assets

WORKDIR /app
COPY --from=php /app /app
WORKDIR /app/vendor/sylius/test-application
RUN yarn install --frozen-lockfile --network-timeout 600000 && yarn build:prod


FROM php AS app

COPY --from=assets /app/vendor/sylius/test-application/public/build /app/vendor/sylius/test-application/public/build
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=localhost

RUN php vendor/bin/console assets:install vendor/sylius/test-application/public --no-debug

EXPOSE 8000
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["init", "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
