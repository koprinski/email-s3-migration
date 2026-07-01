FROM php:8.2-fpm

# Align the worker user with the host so bind-mounted storage/ is writable in dev.
ARG UID=1000
ARG GID=1000

# System libs + PHP extensions needed by the app (pgsql, intl, gd, zip, redis, ...).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip gd bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" www-data \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer (copied from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# The project is bind-mounted in development (see docker-compose.yml), so no COPY here.
