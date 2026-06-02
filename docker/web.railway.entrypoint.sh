#!/usr/bin/env bash
# Railway entrypoint — one image, role chosen by SERVICE_ROLE (web|worker|scheduler|ssr).
set -e
cd /app

# Laravel runtime dirs must exist + be writable by www-data (php-fpm / artisan).
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 0775 storage bootstrap/cache 2>/dev/null || true

role="${SERVICE_ROLE:-web}"
echo "[railway-entrypoint] starting role=${role}"

case "${role}" in
  web)
    # Only the web role owns schema migration + the public storage symlink.
    php artisan migrate --force || echo "[railway-entrypoint] migrate failed (continuing to serve)"
    php artisan storage:link 2>/dev/null || true
    php-fpm -D
    export PORT="${PORT:-8080}"
    # Substitute ONLY ${PORT}; leave nginx's own $uri/$document_root vars intact.
    envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
    echo "[railway-entrypoint] nginx listening on ${PORT}"
    exec nginx -g 'daemon off;'
    ;;
  worker)
    exec php artisan horizon
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  ssr)
    exec php artisan inertia:start-ssr
    ;;
  *)
    echo "[railway-entrypoint] unknown SERVICE_ROLE='${role}'" >&2
    exit 1
    ;;
esac
