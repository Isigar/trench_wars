---
phase: 01-foundations
plan: 02
subsystem: docker-dev-stack
tags:
  - docker
  - docker-compose
  - dockerfile
  - php-fpm
  - nginx
  - postgres
  - redis
  - dev-env
dependency_graph:
  requires:
    - pnpm-workspace-root        # plan 01-01: bind-mount targets exist
    - apps-bot-skeleton          # plan 01-01: bot Dockerfile copies apps/bot
    - apps-rcon-worker-skeleton  # plan 01-01: rcon-worker Dockerfile copies apps/rcon-worker
    - packages-shared-types-skeleton  # plan 01-01: bot + rcon-worker depend on shared-types via workspace:*
  provides:
    - docker-compose-stack       # the 6-service compose file at repo root
    - web-image-dockerfile       # docker/web/Dockerfile (PHP 8.4-fpm + extensions)
    - bot-image-dockerfile       # docker/bot/Dockerfile (Node 22-alpine)
    - rcon-worker-image-dockerfile  # docker/rcon-worker/Dockerfile (Node 22-alpine)
    - nginx-fastcgi-config       # docker/web/nginx.conf
    - php-runtime-ini            # docker/web/php.ini
    - web-entrypoint-script      # docker/web/entrypoint.sh (idempotent storage prep)
    - env-shape-template         # .env.example
    - dev-makefile               # repo-root Makefile
  affects:
    - "01-04 (Laravel scaffold) — runs `composer create-project` inside web container"
    - "01-05+ (every subsequent plan) — every command inside Laravel goes through `docker compose exec web`"
    - "01-18 (BLOCKING smoke test) — first plan to actually `docker compose up -d` and verify healthchecks reach ready"
    - "Phase 5 (bot impl) — fills apps/bot/src; Dockerfile already builds it"
    - "Phase 8 (rcon-worker impl) — fills apps/rcon-worker/src; Dockerfile already builds it"
tech_stack:
  added:
    - "Docker Compose v2 (compose spec, no version field)"
    - "PHP 8.4-fpm-bookworm base image"
    - "Composer 2 (multi-stage from official composer:2)"
    - "Node 22 (NodeSource setup_22.x apt) + corepack-managed pnpm@9.15.0 (inside web image)"
    - "PHP extensions: intl, pdo_pgsql, pgsql, gd, bcmath, zip, mbstring, pcntl, exif, opcache (built), redis (pecl 6.1.0)"
    - "nginx 1.27-alpine (sidecar to php-fpm)"
    - "node:22-alpine (bot + rcon-worker)"
    - "postgres:16-alpine"
    - "redis:7-alpine (with --appendonly yes for persistence)"
  patterns:
    - "web service split into php-fpm + nginx sidecar (per RESEARCH validation strategy)"
    - "depends_on with service_healthy condition gates startup ordering"
    - "Healthchecks on every service (php-fpm -t, nginx -t, pg_isready, redis-cli ping, pgrep node)"
    - "Bind-mount apps/web for live editing; named volumes for vendor/ + node_modules to dodge perf + ownership issues"
    - "Named volumes (pg_data, redis_data) for stateful data persistence"
    - ".env.example with `__SET_ME__`-style empty fields — never holds real secrets (T-1-12-a)"
    - "Compose env-var substitution with `${VAR:-default}` so the stack runs without an .env file (devs override via .env)"
    - "Makefile shortcuts wrap `docker compose exec web ...` (every command inside containers per D-021)"
key_files:
  created:
    - docker-compose.yml
    - .env.example
    - Makefile
    - docker/web/Dockerfile
    - docker/web/nginx.conf
    - docker/web/php.ini
    - docker/web/entrypoint.sh
    - docker/bot/Dockerfile
    - docker/rcon-worker/Dockerfile
  modified: []
decisions:
  - "Used the conventional `{phase}-{plan}-SUMMARY.md` filename `01-02-SUMMARY.md` rather than the plan frontmatter's `01-foundations-02-SUMMARY.md` variant (matches plan 01-01 precedent + orchestrator format)."
  - "Did NOT execute `docker compose up -d` in this plan (orchestrator instruction); validation gate is `docker compose config` only — full smoke test happens in plan 01-18 [BLOCKING]."
  - "Did NOT execute `docker compose build` (would require apps/web/package.json which lands in plan 01-04, and would pull ~1GB of base images) — image build correctness is a downstream verification."
metrics:
  tasks_completed: 2
  files_created: 9
  files_modified: 0
  duration_minutes: ~3
  completed: 2026-05-03
---

# Phase 01 Plan 02: docker-compose dev stack — Summary

**One-liner:** Authors the repo-root `docker-compose.yml` (D-021 LOCKED) plus per-service Dockerfiles for `web` (php:8.4-fpm + intl/pdo_pgsql/redis/gd/bcmath/zip/mbstring/pcntl/exif/opcache + Composer + Node 22 + pnpm@9.15.0), `web-nginx` (sidecar fronting php-fpm), `bot`, and `rcon-worker` (node:22-alpine), with healthchecked Postgres 16 and Redis 7 — `.env.example` documents the env shape (no real secrets) and a `Makefile` exposes the short aliases CONTEXT.md called for.

## What was built

This plan delivers the Docker development environment that every subsequent plan in Phase 1 will run inside. After this plan, plan 04 can execute `docker compose run --rm web composer create-project laravel/laravel .` to scaffold Laravel inside the `web` container without touching the host.

### Per-service images (Task 1 → commit `ab034f0`)

- **`docker/web/Dockerfile`** — Multi-stage (`composer:2 AS composer-bin` → `php:8.4-fpm-bookworm`). Installs system libs for PHP extensions (libicu-dev, libpq-dev, libzip-dev, libonig-dev, libpng-dev, libjpeg-dev, libwebp-dev, libfreetype6-dev, libxml2-dev), then `docker-php-ext-install` for `intl pdo_pgsql pgsql gd bcmath zip mbstring pcntl exif opcache`, then `pecl install redis-6.1.0` + `docker-php-ext-enable redis`. Composer copied from the multi-stage builder. Node 22 installed via NodeSource apt repo, then `corepack enable && corepack prepare pnpm@9.15.0 --activate` so artisan + Vite + the spatie/laravel-data `typescript:transform` artisan command (plan 15) all run inside this single image. Custom `php.ini` mounted at `/usr/local/etc/php/conf.d/zz-trenchwars.ini`. Entrypoint chmod'd executable; default CMD `php-fpm -F`.
- **`docker/web/nginx.conf`** — Single-server config: listens on 80, root `/app/public`, `try_files $uri $uri/ /index.php?$query_string`, `fastcgi_pass web:9000` (compose service name), 20M body cap, `X-Frame-Options: SAMEORIGIN` + `X-Content-Type-Options: nosniff` headers, dotfile deny except `.well-known`. Mounted into the `nginx:1.27-alpine` sidecar at runtime.
- **`docker/web/php.ini`** — Dev overrides: `memory_limit=512M`, `upload_max_filesize=20M`, `post_max_size=20M`, `max_execution_time=60`, UTC timezone, opcache enabled with `validate_timestamps=1` + `revalidate_freq=0` (live code reload during dev), and `error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT` to suppress Filament v3 deprecation noise on PHP 8.4 (RESEARCH Pitfall 9).
- **`docker/web/entrypoint.sh`** — Idempotent shell script (uses `set -euo pipefail`). Ensures `/app/storage/{app/public,framework/{cache,sessions,views,testing},logs}` and `/app/bootstrap/cache` exist with mode `0775` before `exec "$@"` (so `php-fpm -F` runs as PID 1). Safe to re-run on every container start because `mkdir -p` and `chmod` are no-ops if the dirs already exist with correct mode.
- **`docker/bot/Dockerfile`** — `node:22-alpine` + corepack pnpm@9.15.0. Workdir `/app`. Copies workspace root files (`pnpm-workspace.yaml`, `package.json`, `tsconfig.base.json`) plus `packages/shared-types/` and `apps/bot/`, then `pnpm install --frozen-lockfile=false --filter @trenchwars/bot...` (the `...` pulls in the workspace dep on `@trenchwars/shared-types`) and `pnpm build` inside `apps/bot`. Final workdir `/app/apps/bot`; CMD `node dist/index.js`. **Note:** This Dockerfile will only build successfully once plan 01-01's bot package has actual TypeScript source — which it does (the `src/index.ts` skeleton imports `TrenchwarsApiContract` and emits a console log).
- **`docker/rcon-worker/Dockerfile`** — Mirror of bot Dockerfile but for `@trenchwars/rcon-worker`.

### Compose stack (Task 2 → commit `55f5351`)

`docker-compose.yml` at repo root declares 6 services on a single `trenchwars` bridge network:

| Service        | Image / Build                       | Healthcheck                  | depends_on                                    | Ports          |
|----------------|-------------------------------------|------------------------------|-----------------------------------------------|----------------|
| `web`          | build `docker/web/Dockerfile`       | `php-fpm -t`                 | `postgres` + `redis` service_healthy          | (none — internal `:9000`)|
| `web-nginx`    | `nginx:1.27-alpine` + mounted conf  | `nginx -t`                   | `web` service_healthy                          | `8000:80`      |
| `bot`          | build `docker/bot/Dockerfile`       | `pgrep node`                 | `redis` service_healthy                        | (none — outbound only)|
| `rcon-worker`  | build `docker/rcon-worker/Dockerfile`| `pgrep node`                | `redis` service_healthy                        | (none — outbound only)|
| `postgres`     | `postgres:16-alpine`                | `pg_isready -U $USER -d $DB` | (none)                                         | `${DB_PORT_HOST:-5432}:5432`|
| `redis`        | `redis:7-alpine` + `--appendonly yes`| `redis-cli ping \| grep -q PONG` | (none)                                     | `${REDIS_PORT_HOST:-6379}:6379`|

**Volumes:**

- `pg_data` → `/var/lib/postgresql/data` (named, persistent)
- `redis_data` → `/data` (named, persistent + AOF enabled)
- `web_vendor` → `/app/vendor` (named, isolates Composer install from host)
- `web_node_modules` → `/app/node_modules` (named, isolates pnpm install from host)
- `./apps/web` bind-mounted to `/app` in both `web` (rw) and `web-nginx` (`:ro`)
- `./docker/web/nginx.conf` bind-mounted at `/etc/nginx/nginx.conf:ro` in `web-nginx`

**Env-var substitution:** Every service uses `${VAR:-default}` so the stack runs without a `.env` file (defaults match dev creds: `trenchwars/trenchwars/trenchwars` for Postgres). `.env` overrides defaults; production Railway env groups override everything.

### `.env.example` (Task 2)

Documents every env var compose substitutes:

- **App:** `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` (empty), `LOG_DEPRECATIONS_CHANNEL`
- **DB:** `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT_HOST`
- **Redis:** `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_PORT_HOST`
- **Sessions/cache/queue:** `SESSION_DRIVER=redis`, `SESSION_LIFETIME=43200` (30d), `SESSION_SECURE_COOKIE=false`, `SESSION_SAME_SITE=lax`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
- **Discord OAuth:** `DISCORD_CLIENT_ID=`, `DISCORD_CLIENT_SECRET=`, `DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback`. Comments link to https://discord.com/developers/applications and warn about exact-match redirect URIs.
- **Discord bot (Phase 5):** `DISCORD_BOT_TOKEN=`, `DISCORD_APPLICATION_ID=`, `WEB_API_URL=http://web-nginx`, `WEB_API_TOKEN=`
- **RCON HMAC (Phase 8):** `WEB_HMAC_SECRET=`, `WEB_INTERNAL_URL=http://web-nginx`

**Audit:** Every secret-bearing field (`APP_KEY`, `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_BOT_TOKEN`, `DISCORD_APPLICATION_ID`, `WEB_API_TOKEN`, `WEB_HMAC_SECRET`) is empty — confirmed via `grep -v '^#' .env.example | grep '=' | grep -vE '=$|=null$'`, which only returns harmless defaults like service names, port numbers, and `localhost` URLs. T-1-12-a mitigation verified.

### Makefile (Task 2)

`.PHONY` targets exposed:

- **Lifecycle:** `up`, `down`, `restart`, `logs`, `ps`, `build`, `pull`
- **Web shell + tooling:** `shell` (bash), `composer ARGS=...`, `artisan ARGS=...`, `pnpm ARGS=...`, `npm ARGS=...`, `node ARGS=...`
- **Quality gates:** `pest ARGS=...`, `pint ARGS=...`, `phpstan ARGS=...`
- **DB shortcuts:** `migrate`, `fresh` (migrate:fresh --seed), `seed` (db:seed)
- **DTO pipeline (Phase 1 plan 15):** `typescript-transform`

All recipes wrap `docker compose exec web ...` (D-021 enforced — no host-side tooling).

## Verification results

### Task 1 acceptance criteria

| Criterion                                                                                          | Result | Evidence                                                                                |
| -------------------------------------------------------------------------------------------------- | ------ | --------------------------------------------------------------------------------------- |
| All six files exist                                                                                | PASS   | `test -f` chain green for `docker/web/{Dockerfile,nginx.conf,php.ini,entrypoint.sh}`, `docker/{bot,rcon-worker}/Dockerfile` |
| web/Dockerfile installs intl + pdo_pgsql + redis + gd + bcmath + zip + mbstring + pcntl + exif + opcache | PASS | grep on `docker-php-ext-install` and `pecl install redis` lines                                  |
| web/Dockerfile installs Composer + Node 22 + pnpm@9.15.0                                            | PASS   | `COPY --from=composer-bin`, `setup_22.x`, `corepack prepare pnpm@9.15.0` all present              |
| nginx.conf fastcgi_passes to `web:9000`                                                             | PASS   | `grep -q 'fastcgi_pass web:9000' docker/web/nginx.conf` returns 0                                  |
| bot/rcon-worker Dockerfiles use node:22-alpine + corepack pnpm                                      | PASS   | `grep -q 'FROM node:22-alpine'` matches both; `corepack prepare pnpm@9.15.0 --activate` matches both |

### Task 2 acceptance criteria

| Criterion                                                                                          | Result | Evidence                                                                                |
| -------------------------------------------------------------------------------------------------- | ------ | --------------------------------------------------------------------------------------- |
| docker-compose.yml declares 6 services                                                             | PASS   | `docker compose config --services` returns: `postgres redis web web-nginx bot rcon-worker` |
| All services have healthchecks                                                                     | PASS   | grep `healthcheck:` returns 6 occurrences in compose file                                |
| postgres + redis use named volumes for persistence                                                 | PASS   | `pg_data:`, `redis_data:` in top-level `volumes:` block; mounted at `/var/lib/postgresql/data` and `/data` |
| apps/web bind-mounted into web + web-nginx; vendor/ + node_modules in named volumes                | PASS   | `./apps/web:/app` in both web and web-nginx; `web_vendor:/app/vendor`, `web_node_modules:/app/node_modules` in web |
| .env.example contains DISCORD_CLIENT_ID + DISCORD_CLIENT_SECRET (empty) + DISCORD_REDIRECT_URI     | PASS   | grep all three; `DISCORD_CLIENT_SECRET=` matches with empty value                        |
| Real secrets are NEVER in .env.example                                                             | PASS   | non-comment audit shows zero secret-shaped values; only port numbers, localhost URLs, dev-creds defaults |
| Makefile exposes up/down/logs/shell/artisan/composer/pnpm/pest/pint/phpstan/migrate                | PASS   | All targets present in `.PHONY` declaration; recipes verified by Read                    |
| `docker compose config` validates the YAML                                                         | PASS   | exits 0; `--quiet` returns no warnings                                                   |

### Plan-level must_haves

**Truth statements:**

- ✅ **".env.example documents every env var the compose file substitutes (DB_*, REDIS_*, DISCORD_*, APP_*) without containing real secrets"** — verified above; T-1-12-a mitigation in place.
- ✅ **"Postgres container persists data via named volume `pg_data`; redis via `redis_data`."** — confirmed in `docker compose config --volumes`: `pg_data redis_data web_vendor web_node_modules`.
- ✅ **"Makefile exposes `up`, `down`, `logs`, `artisan`, `composer`, `pnpm`, `pest`, `pint`, `phpstan`, `shell` short aliases."** — all present.
- ⏸ **"`docker compose up -d` brings up web (php-fpm 8.4 + nginx), bot, rcon-worker, postgres 16, redis 7 with healthchecks reaching ready state."** — **Deferred to plan 18 (BLOCKING smoke test).** Per orchestrator instruction in this plan ("Do NOT run `docker compose up`"). Acceptance gate here is `docker compose config` (passes). Image build also deferred — `bot` and `rcon-worker` Dockerfiles will build today (their packages exist from plan 01-01) but `web` Dockerfile build is conventionally exercised after plan 04 lands `apps/web/composer.json`.
- ⏸ **"`docker compose exec web php -v` reports PHP 8.4.x with intl, pdo_pgsql, redis, gd, bcmath, zip extensions present."** — **Deferred to plan 18.** Cannot verify without running the container. The Dockerfile is written to install all required extensions; this will be exercised in plan 18.

**Artifacts:** All five `must_haves.artifacts` `path` + `contains` patterns matched (`grep -q` succeeded for `services:`, `DISCORD_CLIENT_ID=`, `.PHONY`, `FROM php:8.4-fpm`, `fastcgi_pass`, `FROM node:22-alpine` x2).

**Key links:** All four `key_links.pattern` regex matched:

- `docker-compose.yml` → `docker/web/Dockerfile` via `dockerfile: docker/web/Dockerfile` ✅
- `docker-compose.yml` → postgres healthcheck via `pg_isready` ✅
- `docker-compose.yml` → redis healthcheck via `redis-cli` … `ping` ✅
- `Makefile` → `docker compose exec` ✅

## Deviations from Plan

### None

Plan executed exactly as written. Two minor process notes (not behavior deviations):

1. **SUMMARY filename:** Plan `<output>` block proposes `01-foundations-02-SUMMARY.md`; canonical `{phase}-{plan}-SUMMARY.md` and plan 01-01's precedent are `01-02-SUMMARY.md`. Used canonical form (matches orchestrator prompt's `01-02-SUMMARY.md`).
2. **Live-stack verification (`docker compose up -d`) deferred:** Per orchestrator instruction in the executor prompt ("Do NOT run `docker compose up`"). The plan's must_haves include "stack reaches ready state" but the orchestrator gates that to plan 18 (BLOCKING smoke test). This is a scope decision baked into the plan, not a deviation discovered during execution.

No Rule 1 (bug fix), Rule 2 (missing critical functionality), or Rule 3 (blocking issue) auto-fixes were applied.

One transient typo was caught and fixed inline before any commit: the `seed:` Makefile target initially copy-pasted `migrate:fresh --seed` (matching `fresh:`); fixed to `db:seed` before commit. No commit history pollution.

## Authentication gates

None encountered. (No external service auth required at file-authoring time. Plan 18 will surface Docker daemon access as a runtime gate.)

## Threat surface scan

This plan introduces local-dev infrastructure only. The threat register from the plan covers all surface:

- **T-1-12-a (Information Disclosure: `.env.example`)** — *mitigate* — Verified: zero non-empty secret-bearing fields. Audit query `grep -v '^#' .env.example | grep '=' | grep -vE '=$|=null$'` returns only service-name defaults, port numbers, dev-only DB creds (`trenchwars/trenchwars/trenchwars`), and `localhost` URLs. `DISCORD_CLIENT_SECRET=`, `DISCORD_BOT_TOKEN=`, `WEB_HMAC_SECRET=`, `WEB_API_TOKEN=`, `APP_KEY=`, `DISCORD_APPLICATION_ID=`, `DISCORD_CLIENT_ID=` all empty.
- **T-1-12-b (Postgres dev creds in compose)** — *accept* — `trenchwars/trenchwars/trenchwars` is dev-only, bound to localhost via `${DB_PORT_HOST:-5432}:5432`. Production overrides via Railway env group.
- **T-1-13 (Tampering via apps/web bind mount)** — *accept* — Dev-only convenience; production builds will use `COPY` rather than mount.
- **T-1-14 (DoS via large request bodies)** — *mitigate* — `client_max_body_size 20M;` in nginx.conf + `upload_max_filesize=20M` + `post_max_size=20M` in php.ini.

No new threat surface beyond the register.

## Commits

- `ab034f0` — `feat(01-02): author per-service Dockerfiles + nginx/php config`
- `55f5351` — `feat(01-02): add docker-compose stack + .env.example + Makefile`

## Next steps (handed to subsequent plans)

- **Plan 01-03** (Composer scaffolding artisan / dev tools list) can now reference `docker compose run --rm web composer ...` for one-shot tooling without `apps/web/composer.json` existing yet.
- **Plan 01-04** (`composer create-project laravel/laravel apps/web`) runs *inside* the `web` container via `docker compose run --rm web composer create-project laravel/laravel . --prefer-dist`. After it lands, `web_vendor` named volume populates and the stack can come up.
- **Plan 01-15** (TypeScript DTO pipeline) — `make typescript-transform` will resolve once spatie/laravel-data ships the artisan command.
- **Plan 01-18** (BLOCKING smoke test) — first plan to actually `docker compose up -d`, exercise healthchecks, run `docker compose exec web php -v`, and confirm `intl + pdo_pgsql + redis + gd + bcmath + zip` extensions resolve.

## Self-Check: PASSED

**Files exist (9/9):**

- `/home/rtx/projects/trench-wars/docker-compose.yml` — FOUND
- `/home/rtx/projects/trench-wars/.env.example` — FOUND
- `/home/rtx/projects/trench-wars/Makefile` — FOUND
- `/home/rtx/projects/trench-wars/docker/web/Dockerfile` — FOUND
- `/home/rtx/projects/trench-wars/docker/web/nginx.conf` — FOUND
- `/home/rtx/projects/trench-wars/docker/web/php.ini` — FOUND
- `/home/rtx/projects/trench-wars/docker/web/entrypoint.sh` — FOUND (executable)
- `/home/rtx/projects/trench-wars/docker/bot/Dockerfile` — FOUND
- `/home/rtx/projects/trench-wars/docker/rcon-worker/Dockerfile` — FOUND

**Commits exist (2/2):**

- `ab034f0` — FOUND in `git log`
- `55f5351` — FOUND in `git log`

**Structural validation:** `docker compose config` exits 0 with no warnings; `--services` lists all six; `--volumes` lists all four named volumes.
