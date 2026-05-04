#!/usr/bin/env bash
# Source: D-020 LOCKED + plan 01-15.
#
# Host-side fallback for syncing the generated api.d.ts into packages/shared-types/src/.
# Normally `make artisan ARGS="trenchwars:typescript-generate"` does this in-container
# (the docker-compose.yml mount of packages/shared-types into /repo/packages/shared-types
# makes it a one-shot). This script is the no-docker-needed alternative for environments
# where the volume mount is unavailable (CI runners that build images independently, etc.).
#
# Usage (from the repo root or anywhere — script resolves paths relative to itself):
#     bash packages/shared-types/scripts/sync-types.sh
#
# Exit codes:
#     0  — sync succeeded (or sources were identical, nothing to do)
#     1  — source api.d.ts missing (run `make artisan ARGS="typescript:transform"` first)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"

SOURCE="${REPO_ROOT}/apps/web/resources/js/types/api.d.ts"
TARGET="${REPO_ROOT}/packages/shared-types/src/api.d.ts"

if [[ ! -f "${SOURCE}" ]]; then
  echo "[sync-types] ERROR: source not found: ${SOURCE}" >&2
  echo "[sync-types] Run 'make artisan ARGS=\"typescript:transform\"' first to generate it." >&2
  exit 1
fi

if [[ -f "${TARGET}" ]] && cmp -s "${SOURCE}" "${TARGET}"; then
  echo "[sync-types] No changes — ${TARGET} already up to date."
  exit 0
fi

mkdir -p "$(dirname "${TARGET}")"
cp "${SOURCE}" "${TARGET}"
echo "[sync-types] Synced ${SOURCE} -> ${TARGET}"
