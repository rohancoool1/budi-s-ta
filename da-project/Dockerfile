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
FROM php:8.4-cli-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libonig-dev \
        libsqlite3-dev \
    && docker-php-ext-install \
        bcmath \
        mbstring \
        pcntl \
        pdo_sqlite \
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
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

USER www-data

EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8000/ > /dev/null || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
