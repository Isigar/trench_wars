---
phase: 01-foundations
plan: 17
subsystem: infra
tags: [railway, nixpacks, deploy, monorepo, php84, node22, postgres, redis]

requires:
  - phase: 01-foundations
    provides: "Pest 4 + Larastan/PHPStan L8 + Pint installed in apps/web (plan 01-05)"

provides:
  - "Per-service Railway config: apps/{web,bot,rcon-worker}/{nixpacks.toml,railway.json}"
  - "Documentation-only railway.toml stub at repo root pointing to RAILWAY-DEPLOY.md"
  - "RAILWAY-DEPLOY.md operator-facing runbook (5 services + 2 plugins, env-group wiring, troubleshooting)"

affects:
  - "Operator first-time deploy is now a documented walkthrough; no per-service tribal knowledge"
  - "Worker service shares the apps/web image; only startCommand differs (queue:work)"
  - "Future plans authoring Railway-touching changes update RAILWAY-DEPLOY.md alongside config"

tech-stack:
  added:
    - "Nixpacks PHP 8.4 provider (auto-detected via NIXPACKS_PHP_ROOT_DIR=/app/public)"
    - "Nixpacks Node 22 provider (NIXPACKS_NODE_VERSION=22) for bot + rcon-worker"
  patterns:
    - "Per-service Root Directory monorepo deploy: apps/{web,bot,rcon-worker} as Railway Root Directory targets"
    - "Same image, different startCommand for web ↔ worker services"
    - "railway.json + nixpacks.toml co-located in each service directory (Pitfall 6 — root-level railway.toml does NOT respect Root Directory)"

key-files:
  created:
    - "apps/web/nixpacks.toml"
    - "apps/web/railway.json"
    - "apps/bot/nixpacks.toml"
    - "apps/bot/railway.json"
    - "apps/rcon-worker/nixpacks.toml"
    - "apps/rcon-worker/railway.json"
    - "railway.toml"
    - ".planning/phases/01-foundations/RAILWAY-DEPLOY.md"

key-decisions:
  - "Repo-root railway.toml ships as a documentation-only stub (19 lines) — Pitfall 6 says a single root railway.toml does not respect per-service Root Directory, so per-subdir railway.json is the load-bearing config."
  - "Worker service is NOT a separate config tree — operator creates a second Railway service with the same Root Directory (apps/web) and overrides startCommand to `php artisan queue:work --tries=3 --backoff=10 --timeout=120` per dashboard. RAILWAY-DEPLOY.md documents this pattern."
  - "apps/web/nixpacks.toml uses pnpm install --no-frozen-lockfile (no committed pnpm-lock.yaml yet) — matches plan 01-16's CI choice."
  - "Web service healthcheck on /up (Laravel default) with restartPolicyType ON_FAILURE + maxRetries 3; bot + rcon-worker railway.json omit healthcheck (worker services without HTTP listeners)."

patterns-established:
  - "Each Railway service ships its own railway.json + nixpacks.toml at the path Railway treats as Root Directory"
  - "PHP nixpacks: explicit nixPkgs list (intl/pdo_pgsql/redis/gd/bcmath/zip/mbstring/pcntl/exif/opcache) — Nixpacks PHP plugin only auto-installs a subset"
  - "Build phase chains: composer install → pnpm install → pnpm run build → artisan config/route/view:cache"
  - "Operator runbook (RAILWAY-DEPLOY.md) cross-refs CLAUDE.md §6, PROJECT.md D-014, 01-RESEARCH.md pitfalls — single source of deploy truth"

requirements-completed: [REQ-constraint-railway-deploy]

duration: ~3m
completed: 2026-05-03
---

# Phase 01 Plan 17: Railway Deploy Config Summary

**Per-service Railway deploy config (nixpacks.toml + railway.json) for web/bot/rcon-worker plus a 204-line operator runbook (RAILWAY-DEPLOY.md) covering the 5-service + 2-plugin topology end-to-end — first-time deploy is now a checklist, not tribal knowledge.**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-05-03T23:01:00Z (approx — based on file mtimes)
- **Completed:** 2026-05-03T23:04:44Z (commit be8cbdb timestamp)
- **Tasks:** 2 (Task 1: per-service config files, Task 2: RAILWAY-DEPLOY.md walkthrough)

## Commits

- `2668a31` — chore(01-17): add per-service Railway config (nixpacks.toml + railway.json)
- `be8cbdb` — docs(01-17): add RAILWAY-DEPLOY.md operator walkthrough

## What Was Built

### apps/web/{nixpacks.toml,railway.json}
- PHP 8.4 + Node 22 + pnpm in nixPkgs; explicit extension list (intl, pdo_pgsql, redis, gd, bcmath, zip, mbstring, pcntl, exif, opcache)
- Build chain: composer install (no-dev, optimized) → pnpm install (no-frozen-lockfile) → pnpm run build → artisan config:cache + route:cache + view:cache
- Start: `/start-server.sh` (Nixpacks PHP provider auto-wires php-fpm + Caddy when NIXPACKS_PHP_ROOT_DIR=/app/public)
- railway.json: NIXPACKS builder + healthcheckPath=/up + restartPolicyType=ON_FAILURE + maxRetries=3 + healthcheckTimeout=30s

### apps/bot/{nixpacks.toml,railway.json} + apps/rcon-worker/{nixpacks.toml,railway.json}
- Node 22 + pnpm in nixPkgs
- pnpm install run from monorepo root with --filter (Pitfall 6: workspace deps require monorepo-root install context)
- Start commands target the per-package dist entrypoints (Phase 5 wires real bot logic; Phase 8 wires rcon-worker)
- railway.json: NIXPACKS builder + restartPolicyType=ON_FAILURE + no healthcheck (no HTTP listener)

### railway.toml (repo root)
- 19-line documentation-only stub
- Per Pitfall 6: a single root railway.toml does NOT propagate to per-service Root Directory deploys, so this file ships as a pointer to RAILWAY-DEPLOY.md

### .planning/phases/01-foundations/RAILWAY-DEPLOY.md (204 lines)
- Step-by-step first-time deploy: railway init → attach Postgres + Redis plugins → create 4 env groups (app, database, redis, discord) → create 4 services with Root Directory wiring (web/worker share apps/web) → push to deploy → smoke check (/up + Filament login + bot heartbeat)
- Service ↔ env-group mapping table (per .docs/02-architecture.md)
- Worker service documented as same image as web with `php artisan queue:work --tries=3 --backoff=10 --timeout=120` startCommand override
- NIXPACKS_PHP_ROOT_DIR=/app/public for web+worker; NIXPACKS_NODE_VERSION=22 for bot+rcon-worker (Pitfall 6)
- Troubleshooting table covering 11 common failure modes: build errors, OAuth callback mismatch, CSRF token leak (research Pitfall 3), Filament panel guard mismatch (research Pitfall 4), Spatie permission guard, queue idle, env-var refresh, psql extensions (research Pitfall 5), bot DB isolation (D-004), worker startCommand override, healthcheck timeout
- Cross-refs CLAUDE.md §6, PROJECT.md D-014, 01-RESEARCH.md pitfalls — single source of deploy truth

## Acceptance Verification

All must-have truths verified at commit time:

- ✓ Per-service Railway config exists at apps/web/railway.json, apps/bot/railway.json, apps/rcon-worker/railway.json (root-directory-per-service pattern per RESEARCH Pitfall 6)
- ✓ Each service uses Nixpacks (Dockerfile escape hatch documented in RAILWAY-DEPLOY.md troubleshooting). Web sets NIXPACKS_PHP_ROOT_DIR=/app/public so Caddy serves from public/
- ✓ RAILWAY-DEPLOY.md walks operator through railway init → Postgres+Redis plugins → 4 env groups → Root Directory wiring → push branch
- ✓ Worker service is documented as same image as web with `php artisan queue:work --tries=3` startCommand override (Horizon comes in Phase 5+)
- ✓ Five services + 2 plugins fit the D-014 topology

Key links verified:
- ✓ apps/web/railway.json → apps/web/nixpacks.toml via build.nixpacksConfigPath="nixpacks.toml"

## Deviations from Plan

None — plan was authored against RESEARCH Pitfall 6 with full per-service config; what shipped matches the plan's `<action>` block plus the documentation-only railway.toml stub the plan called out.

The only minor adjustment: the plan example's `pnpm install --frozen-lockfile=false` was replaced with `pnpm install --no-frozen-lockfile` (canonical pnpm 9 CLI syntax — matches plan 01-16's identical correction).

## Lessons / Patterns

- **Same-image-different-startCommand** is the cleanest way to model Laravel web ↔ worker on Railway (one Dockerfile/nixpacks.toml, two services with different startCommand overrides)
- **railway.toml at repo root is a trap** for monorepo deploys — Pitfall 6 calls it out, and we ship a 19-line stub purely so future operators know not to extend it
- **Operator runbooks are part of the deploy contract** — without RAILWAY-DEPLOY.md, the per-service Root Directory + env group wiring is an oral tradition; with it, deploy is a 30-minute checklist

## Next

Plan 01-18 (final phase 1 plan) wires up the production branch + readme deploy badge + verifies CI gates the deploy path. Plans 01-07..01-15 (Tailwind v4, Discord OAuth, Filament admin, RBAC, audit log, i18n, DTO/TS transformer) execute first per ROADMAP wave dependencies.
