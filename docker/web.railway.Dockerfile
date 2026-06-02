# Railway production image for the Laravel app and its PHP siblings.
#
# One image, three runtime roles selected by the SERVICE_ROLE env var:
#   web        → nginx (on $PORT) + php-fpm   (runs migrations first)
#   worker     → php artisan horizon
#   scheduler  → php artisan schedule:work
#   ssr        → php artisan inertia:start-ssr  (requires Node in the image — disabled for now)
#
# Selected per Railway service via the RAILWAY_DOCKERFILE_PATH variable, which also
# forces Railway's Docker builder. Build context = repo root (service Root Directory
# must be empty), so the pnpm workspace is fully available.

# ---- Stage 1: front-end assets (pnpm workspace + Vite) ----
FROM node:22-bookworm-slim AS assets
RUN corepack enable && corepack prepare pnpm@9.15.0 --activate
WORKDIR /repo
COPY pnpm-workspace.yaml package.json pnpm-lock.yaml tsconfig.base.json ./
COPY packages ./packages
COPY apps/web ./apps/web
RUN pnpm install --no-frozen-lockfile --filter @trenchwars/web...
RUN pnpm --filter @trenchwars/web run build

# ---- Stage 2: PHP runtime (php-fpm 8.4 + nginx) ----
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
      git curl unzip ca-certificates nginx gettext-base \
      libicu-dev libpq-dev libzip-dev libonig-dev \
      libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libxml2-dev libmagickwand-dev \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure intl \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pgsql gd bcmath zip mbstring pcntl exif opcache
RUN pecl install redis-6.1.0 && docker-php-ext-enable redis
RUN pecl install imagick-3.7.0 && docker-php-ext-enable imagick

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/web/php.ini /usr/local/etc/php/conf.d/zz-trenchwars.ini

WORKDIR /app

# App source (Laravel lives in apps/web). vendor/ is installed below with the
# extensions present so platform checks pass; --no-scripts skips artisan during build
# (no env yet) — Laravel rebuilds the package manifest on first boot.
COPY apps/web /app
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# Built front-end assets (public/build + Filament theme) from stage 1.
COPY --from=assets /repo/apps/web/public /app/public

COPY docker/web.railway.nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/web.railway.entrypoint.sh /usr/local/bin/railway-entrypoint.sh
RUN chmod +x /usr/local/bin/railway-entrypoint.sh

ENV SERVICE_ROLE=web
ENTRYPOINT ["/usr/local/bin/railway-entrypoint.sh"]
