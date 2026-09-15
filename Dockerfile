# syntax=docker/dockerfile:1.7
FROM dunglas/frankenphp:1-php8.5 AS base
WORKDIR /app
RUN IPE_PROCESSOR_COUNT=2 install-php-extensions intl pdo_pgsql zip apcu
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/app.ini
COPY Caddyfile /etc/caddy/Caddyfile

FROM base AS build
RUN install-php-extensions @composer
ENV COMPOSER_ALLOW_SUPERUSER=1 APP_ENV=prod APP_DEBUG=0
COPY . .
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php bin/console cache:clear --env=prod --no-debug \
    && php bin/console assets:install public --env=prod \
    && php bin/console importmap:install --env=prod \
    && php bin/console asset-map:compile --env=prod

FROM base AS app
ENV APP_ENV=prod APP_DEBUG=0
COPY --from=build /app /app
EXPOSE 80
