#!/usr/bin/env bash
set -euo pipefail

# Source: 01-RESEARCH.md (Laravel runtime dirs must exist + be writable inside container).
# This entrypoint is idempotent — safe to run on every container start.

if [ -d "/app" ]; then
  mkdir -p \
    /app/storage/app/public \
    /app/storage/framework/cache \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/framework/testing \
    /app/storage/logs \
    /app/bootstrap/cache 2>/dev/null || true

  # Plan 01-06: 0777 on storage + bootstrap/cache so php-fpm (www-data, uid 33)
  # can write blade compiles, sessions, logs, and the package manifest cache to
  # bind-mounted dirs owned by the host developer (uid 1000). Dev-only — production
  # runs in single-user containers where 0775 is sufficient. .gitignore excludes
  # all generated files in these dirs so 0777 doesn't leak via git.
  chmod -R 0777 /app/storage /app/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
