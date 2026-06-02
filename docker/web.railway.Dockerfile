# syntax=docker/dockerfile:1
# Railway production image for the Laravel app and its PHP siblings.
#
# One image, runtime role selected by SERVICE_ROLE:
#   web        → nginx (on $PORT) + php-fpm   (runs migrations first)
#   worker     → php artisan horizon
#   scheduler  → php artisan schedule:work
#   ssr        → php artisan inertia:start-ssr  (Node is present; enable later)
#
# Selected per Railway service via RAILWAY_DOCKERFILE_PATH (forces the Docker builder).
# Build context = repo root (service Root Directory empty) so the pnpm workspace AND
# Composer vendor/ are available — the Filament theme build needs vendor/filament/support.

FROM php:8.4-fpm-bookworm

# System deps + nginx + Node 22 (Node needed: Vite build + future SSR role).
RUN apt-get update && apt-get install -y --no-install-recommends \
      git curl unzip ca-certificates nginx gettext-base \
      libicu-dev libpq-dev libzip-dev libonig-dev \
      libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libxml2-dev libmagickwand-dev \
  && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
  && apt-get install -y --no-install-recommends nodejs \
  && rm -rf /var/lib/apt/lists/* \
  && corepack enable && corepack prepare pnpm@9.15.0 --activate

# PHP extensions Laravel 12 + Filament v3 + media-library require.
RUN docker-php-ext-configure intl \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pgsql gd bcmath zip mbstring pcntl exif opcache
RUN pecl install redis-6.1.0 && docker-php-ext-enable redis
RUN pecl install imagick-3.7.0 && docker-php-ext-enable imagick

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Runtime config (inlined to avoid Railway build-context COPY quirks) ---
COPY <<'INI' /usr/local/etc/php/conf.d/zz-trenchwars.ini
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 60
date.timezone = UTC
opcache.enable = 1
opcache.validate_timestamps = 0
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
INI

COPY <<'NGINX' /etc/nginx/nginx.conf.template
worker_processes auto;
events { worker_connections 1024; }

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;
    sendfile on;
    keepalive_timeout 65;
    client_max_body_size 20M;

    server {
        listen ${PORT} default_server;
        server_name _;
        root /app/public;
        index index.php index.html;

        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 60s;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
NGINX

COPY <<'SH' /usr/local/bin/railway-entrypoint.sh
#!/usr/bin/env bash
set -e
cd /app

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 0775 storage bootstrap/cache 2>/dev/null || true

role="${SERVICE_ROLE:-web}"
echo "[railway-entrypoint] starting role=${role}"

case "${role}" in
  web)
    php artisan migrate --force || echo "[railway-entrypoint] migrate failed (continuing to serve)"
    php artisan storage:link 2>/dev/null || true
    php-fpm -D
    export PORT="${PORT:-8080}"
    envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
    echo "[railway-entrypoint] nginx listening on ${PORT}"
    exec nginx -g 'daemon off;'
    ;;
  worker)    exec php artisan horizon ;;
  scheduler) exec php artisan schedule:work ;;
  ssr)       exec php artisan inertia:start-ssr ;;
  *)         echo "[railway-entrypoint] unknown SERVICE_ROLE='${role}'" >&2; exit 1 ;;
esac
SH
RUN chmod +x /usr/local/bin/railway-entrypoint.sh

# --- Build: workspace at /build so Composer vendor + pnpm assets coexist ---
WORKDIR /build
COPY pnpm-workspace.yaml package.json pnpm-lock.yaml tsconfig.base.json ./
COPY packages ./packages
COPY apps/web ./apps/web
# Composer first → apps/web/vendor (Filament Tailwind preset lives here).
RUN cd apps/web && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
# Then the front-end build (Vite client + Filament theme; vendor preset now resolvable).
RUN pnpm install --no-frozen-lockfile --filter @trenchwars/web... \
  && pnpm --filter @trenchwars/web run build
# Relocate the built app to /app and drop node_modules (not needed by web/worker/scheduler).
RUN rm -rf apps/web/node_modules node_modules \
  && mkdir -p /app && cp -a apps/web/. /app/ \
  && rm -rf /build

WORKDIR /app
ENV SERVICE_ROLE=web
ENTRYPOINT ["/usr/local/bin/railway-entrypoint.sh"]
