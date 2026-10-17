# Build Laravel's PHP dependencies.
FROM composer:2 AS vendor

WORKDIR /app

COPY barbershop-laravel/composer.json barbershop-laravel/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader


# Test dependencies are isolated from the production image.
FROM vendor AS vendor-test

RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader


# Compile Tailwind CSS and the small plain JavaScript file.
FROM node:22-alpine AS assets

WORKDIR /app

COPY barbershop-laravel/package.json barbershop-laravel/package-lock.json ./
RUN npm ci

COPY barbershop-laravel/resources ./resources
COPY barbershop-laravel/public ./public
COPY barbershop-laravel/vite.config.js ./vite.config.js

RUN npm run build


# Run the Laravel application.
FROM php:8.4-cli-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-install \
        bcmath \
        dom \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --chown=www-data:www-data barbershop-laravel ./
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && rm -rf public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi \
    && php artisan storage:link

USER www-data

EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8000/ > /dev/null || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]


# Run PHPUnit without depending on a host-installed vendor directory.
FROM runtime AS testing

USER root
COPY --from=vendor-test --chown=www-data:www-data /app/vendor ./vendor
RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data bootstrap/cache
USER www-data
CMD ["php", "vendor/bin/phpunit", "--do-not-cache-result"]


# Keep the default image free of development dependencies.
FROM runtime AS app
