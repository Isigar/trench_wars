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

  chmod -R 0775 /app/storage /app/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
