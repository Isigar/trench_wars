# syntax=docker/dockerfile:1
# Railway production image for the Laravel app and its PHP siblings.
#
# One image, runtime role selected by SERVICE_ROLE:
#   web        → nginx (on $PORT) + php-fpm   (runs migrations first)
#   worker     → php artisan horizon
#   scheduler  → php artisan schedule:work
#   ssr        → php artisan inertia:start-ssr  (needs Node in the image — disabled for now)
#
# Selected per Railway service via RAILWAY_DOCKERFILE_PATH (also forces the Docker
# builder). Build context = repo root (service Root Directory empty) so the pnpm
# workspace is available. Runtime config (php.ini / nginx / entrypoint) is inlined
# via heredocs to avoid Railway build-context COPY quirks.

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

# php.ini overrides (inlined)
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

# nginx config template — ${PORT} substituted at boot; other $-vars stay literal.
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

# Runtime entrypoint — role chosen by SERVICE_ROLE (inlined).
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

WORKDIR /app
# App source (Laravel lives in apps/web). vendor installed with extensions present.
COPY apps/web /app
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
# Built front-end assets from stage 1.
COPY --from=assets /repo/apps/web/public /app/public

ENV SERVICE_ROLE=web
ENTRYPOINT ["/usr/local/bin/railway-entrypoint.sh"]
