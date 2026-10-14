# Control de Pedidos · imagen para Render (o cualquier servicio con Docker)
#
#  1. vendor  : dependencias de PHP sin las de desarrollo
#  2. assets  : compila Tailwind/Vite (necesita las vistas de paginacion de vendor)
#  3. final   : PHP 8.4 con pdo_pgsql/pdo_mysql, servido con `artisan serve`

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY app ./app
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM php:8.4-cli-bookworm
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pdo_mysql intl zip opcache \
    && printf "upload_max_filesize=60M\npost_max_size=60M\nmemory_limit=512M\nopcache.enable_cli=1\ndate.timezone=America/La_Paz\n" > /usr/local/etc/php/conf.d/control-pedidos.ini

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && php artisan package:discover --ansi \
    && chmod +x docker/iniciar.sh

ENV PORT=10000 \
    PHP_CLI_SERVER_WORKERS=4 \
    LOG_CHANNEL=stderr

EXPOSE 10000
CMD ["sh", "docker/iniciar.sh"]
