# syntax=docker/dockerfile:1

# ---- Dependencies (production only) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs

# ---- Runtime ----
FROM php:8.4-fpm-alpine AS runtime
WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
# Never trust host-built manifests: they may list dev-only providers that are not installed here.
RUN rm -f bootstrap/cache/*.php

# /data holds the SQLite file on a volume; replace with a managed DB (RDS) in AWS.
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && mkdir -p /data storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache \
    && rm /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && echo "expose_php=0" > /usr/local/etc/php/conf.d/zz-hardening.ini

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_DATABASE=/data/database.sqlite

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
