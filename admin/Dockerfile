FROM liushoukun/php:8.2-fpm-nginx AS base

FROM base AS deploy
COPY --chown=www-data:www-data . /var/www/html
USER www-data


WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader
