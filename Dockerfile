# syntax=docker/dockerfile:1
#
# Render has no native PHP runtime, so the API ships as a container.
# Two stages: build the Vite assets with Node, then a slim PHP CLI image that
# serves Laravel on $PORT (Render injects PORT, default 10000).

# ---------------------------------------------------------------- assets ----
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json vite.config.js ./
RUN npm install
# resources/views/welcome.blade.php is the only consumer of @vite, so a failure
# here would cost the landing page, never the API -- but it has always built.
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------- runtime ----
FROM php:8.3-cli-bookworm

# pdo_mysql is required for TiDB; gd/zip/dom back dompdf's PDF vouchers.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libxml2-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip dom \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Everything must be present before `composer install`: the post-autoload-dump
# script runs `php artisan package:discover`, which boots the application.
COPY . .

# FcmService reads storage/app/firebase-credentials.json, but storage/app/.gitignore
# ("*") keeps that file out of git -- so the Render image, built from the repo, has no
# key at that path and EVERY push fails silently with "credentials file not found".
# The service-account key is already committed at repo root, so copy it to the path the
# code expects. If the key is rotated, the downloaded filename changes: update this line.
COPY pos-for-small-business-firebase-adminsdk-fbsvc-85bed3502b.json storage/app/firebase-credentials.json
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && mkdir -p storage/framework/cache storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

COPY --from=assets /app/public/build public/build

EXPOSE 10000

# --no-reload is what makes PHP_CLI_SERVER_WORKERS take effect (ServeCommand
# only honours it without the file watcher); without it every request would
# queue behind a single process.
CMD ["sh", "-c", "php artisan serve --host 0.0.0.0 --port ${PORT:-10000} --no-reload"]
