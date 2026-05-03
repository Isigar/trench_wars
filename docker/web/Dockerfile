# Source: research 01-RESEARCH.md Common Pitfalls 5 (postgres extensions) + Standard Stack
# PHP 8.4-fpm with extensions Laravel 12 + Filament v3 require.

FROM composer:2 AS composer-bin

FROM php:8.4-fpm-bookworm

# System deps for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip ca-certificates \
    libicu-dev libpq-dev libzip-dev libonig-dev \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    libxml2-dev \
  && rm -rf /var/lib/apt/lists/*

# Configure & install PHP extensions
RUN docker-php-ext-configure intl \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j$(nproc) \
    intl pdo_pgsql pgsql gd bcmath zip mbstring pcntl exif opcache

# Redis extension via pecl
RUN pecl install redis-6.1.0 \
  && docker-php-ext-enable redis

# Composer (copy from composer:2 stage)
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# Node 22 + pnpm (for `php artisan typescript:transform` + Vite builds invoked from web container)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
  && apt-get install -y --no-install-recommends nodejs \
  && rm -rf /var/lib/apt/lists/* \
  && corepack enable \
  && corepack prepare pnpm@9.15.0 --activate

# Custom php.ini overrides
COPY docker/web/php.ini /usr/local/etc/php/conf.d/zz-trenchwars.ini

# Entrypoint ensures Laravel runtime dirs exist + writable
COPY docker/web/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
