---
phase: 01-foundations
plan: 04
subsystem: laravel-12-scaffold
tags:
  - laravel
  - composer
  - php-8.4
  - postgres
  - redis
  - migrations
  - discord-oauth
  - env
dependency_graph:
  requires:
    - docker-compose-stack          # plan 01-02: web container with PHP 8.4 + Composer + Postgres + Redis
    - web-image-dockerfile          # plan 01-02: php:8.4-fpm + intl/pdo_pgsql/redis extensions
    - dev-makefile                  # plan 01-02: make migrate / make artisan aliases consumed by plan
    - claude-md-conventions         # plan 01-03: container-only command discipline (D-021)
  provides:
    - laravel-12-skeleton           # apps/web/ Laravel 12.58.0 application root
    - apps-web-env-shape            # apps/web/.env.example aligned with repo-root .env.example
    - pgsql-default-connection      # config/database.php 'default' => env('DB_CONNECTION','pgsql')
    - discord-services-config       # config/services.php['discord'] reads DISCORD_CLIENT_ID/_SECRET/_REDIRECT_URI
    - redis-session-cache-queue     # config/session.php driver=redis (default); CACHE_STORE+QUEUE_CONNECTION=redis via env
    - postgres-extensions-migration # uuid-ossp + pgcrypto + citext enabled per Pitfall 5
  affects:
    - "01-05 (Composer dev tools — Pint, Larastan, Pest, spatie/*, Filament, Socialite) — installs INTO this composer.json"
    - "01-06 (Inertia + Vue 3 + Vite) — modifies bootstrap/app.php + adds HandleInertiaRequests middleware"
    - "01-07 (Tailwind v4 + dual-Tailwind workaround) — replaces resources/css/app.css + adds vite.config.js plugins"
    - "01-09 (Discord Socialite OAuth) — wires socialiteproviders/discord, registers via Event::listen, consumes config/services.php['discord']"
    - "01-10 (UUID-PK users + players + player_privacy schema) — relies on uuid-ossp + citext extensions enabled here; replaces deleted users/cache/jobs default migrations"
    - "01-12 (Filament v3) — installs into composer.json; uses pgsql connection"
    - "01-15 (DTO pipeline) — registers spatie/laravel-data + typescript-transformer in composer.json"
    - "01-16 (CI) — runs Pest+PHPStan+Pint via composer.json scripts"
    - "01-18 (BLOCKING smoke test) — exercises full stack-up path including this scaffold's artisan + migrate"
tech_stack:
  added:
    - "Laravel Framework 12.58.0 (composer create-project laravel/laravel ^12.0)"
    - "laravel/tinker ^2.10.1 (Laravel 12 default dev dep)"
    - "laravel/pint ^1.24 (Laravel 12 default dev dep — formatter; configured separately in plan 01-05)"
    - "laravel/sail ^1.41 (Laravel 12 default dev dep — unused; D-021 uses our own docker-compose.yml)"
    - "laravel/pail ^1.2.2 (Laravel 12 default dev dep — log tail)"
    - "phpunit/phpunit ^11.5.50 (Laravel 12 default — Pest replaces this in plan 01-05)"
    - "fakerphp/faker ^1.23, mockery/mockery ^1.6, nunomaduro/collision ^8.6 (Laravel 12 default dev deps)"
    - "Postgres extensions: uuid-ossp 1.1, pgcrypto 1.3, citext 1.6 (enabled in trenchwars database)"
  patterns:
    - "Containerised composer create-project: scaffold to /tmp/laravel inside web container, then merge into /app to coexist with named-volume mounts (vendor, node_modules) and entrypoint-created bootstrap/+storage/ dirs (Rule 3 deviation)"
    - "docker-compose runtime env vars override Laravel .env at runtime (DB_CONNECTION=pgsql injected by compose) — Laravel .env values are dev-default fallbacks only"
    - "First migration enables Postgres extensions BEFORE any table migration (research Pitfall 5); deleted Laravel default users/cache/jobs migrations to keep DB clean for plan 10's UUID-PK schema"
    - "Discord OAuth credentials wired via env() in config/services.php (Pattern 1) — consumed by plan 01-09 Socialite provider registration"
    - "session cookie SameSite=Lax + Secure-in-prod-only + HttpOnly (Pitfall 3) — works on http://localhost OAuth callback"
key_files:
  created:
    - apps/web/composer.json
    - apps/web/composer.lock
    - apps/web/artisan
    - apps/web/bootstrap/app.php
    - apps/web/bootstrap/providers.php
    - apps/web/public/index.php
    - apps/web/public/.htaccess
    - apps/web/public/favicon.ico
    - apps/web/public/robots.txt
    - apps/web/app/Http/Controllers/Controller.php
    - apps/web/app/Models/User.php
    - apps/web/app/Providers/AppServiceProvider.php
    - apps/web/config/app.php
    - apps/web/config/auth.php
    - apps/web/config/cache.php
    - apps/web/config/filesystems.php
    - apps/web/config/logging.php
    - apps/web/config/mail.php
    - apps/web/config/queue.php
    - apps/web/database/.gitignore
    - apps/web/database/factories/UserFactory.php
    - apps/web/database/seeders/DatabaseSeeder.php
    - apps/web/database/migrations/0001_01_01_000000_enable_postgres_extensions.php
    - apps/web/resources/css/app.css
    - apps/web/resources/js/app.js
    - apps/web/resources/js/bootstrap.js
    - apps/web/resources/views/welcome.blade.php
    - apps/web/routes/console.php
    - apps/web/routes/web.php
    - apps/web/storage/  # Laravel runtime tree with .gitkeep stubs
    - apps/web/tests/Feature/ExampleTest.php
    - apps/web/tests/Unit/ExampleTest.php
    - apps/web/tests/TestCase.php
    - apps/web/phpunit.xml
    - apps/web/package.json
    - apps/web/vite.config.js
    - apps/web/.editorconfig
    - apps/web/.env.example
    - apps/web/.gitattributes
    - apps/web/.gitignore
    - apps/web/README.md
  modified:
    - apps/web/.env.example       # task 2 — overwrote Laravel default with Trenchwars-side shape
    - apps/web/config/database.php # task 2 — default connection sqlite -> pgsql
    - apps/web/config/services.php # task 2 — added discord provider block
    - apps/web/config/session.php  # task 2 — default driver database -> redis; lifetime 120 -> 43200
  deleted:
    - apps/web/.gitkeep                                                          # task 1 — placeholder from plan 01-01
    - apps/web/database/migrations/0001_01_01_000000_create_users_table.php      # task 2 — replaced by plan 10 UUID-PK users
    - apps/web/database/migrations/0001_01_01_000001_create_cache_table.php      # task 2 — cache uses redis driver
    - apps/web/database/migrations/0001_01_01_000002_create_jobs_table.php      # task 2 — queue uses redis driver
decisions:
  - "Used the canonical {phase}-{plan}-SUMMARY.md filename (01-04-SUMMARY.md) consistent with plans 01-01..01-03 precedent rather than the plan frontmatter's 01-foundations-04-SUMMARY.md variant."
  - "Scaffolded Laravel into /tmp/laravel inside the web container then moved files into /app rather than running composer create-project directly into /app — necessary because the web container's named volumes (web_vendor at /app/vendor, web_node_modules at /app/node_modules) and the entrypoint script (which creates bootstrap/cache + storage/* dirs before exec) make /app non-empty before composer runs. Composer refuses to create-project in a non-empty directory. Plan author anticipated a .gitkeep collision (single workaround in <action> step 'Important' note) but not the named-volume + entrypoint collision; this is a Rule 3 fix-blocking-issue auto-fix."
  - "Reset `apps/web/.env` after task 1's APP_KEY generation because composer create-project ALSO ran key:generate as a post-install script, and re-running it via 'docker compose exec web php artisan key:generate --force' appended a second base64 key to the existing line rather than replacing it (resulting in malformed APP_KEY=base64:...=base64:...= concatenation). Fixed by overwriting .env from task 2's clean .env.example then running key:generate once. Rule 1 bug fix."
  - "Ran `php artisan migrate:fresh --force` after task 2 commit because composer create-project's post-install script ran `php artisan migrate --graceful` while web container env was already DB_CONNECTION=pgsql (overrides .env). This populated postgres with the Laravel-default `users`, `cache`, `cache_locks`, `failed_jobs`, `jobs`, `job_batches`, `password_reset_tokens`, `sessions` tables — schema we explicitly do NOT want (plan 10 authors UUID-PK replacements). migrate:fresh dropped them, leaving postgres with only the migrations table + the extensions migration record. Rule 1 fix; no code change so not a separate commit."
  - "Did not register the Socialite Discord provider in app/Providers/AppServiceProvider.php — that's plan 01-09's scope (this plan only declares the env-keyed credentials block per the plan's <objective>: 'no Discord OAuth implementation yet — just bootstrap + DB connectivity + extension migration')."
  - "Did not delete laravel/sail or laravel/pail dev-deps from composer.json. They're Laravel 12 defaults and harmless; plan 01-05 (Composer dev tools) is the canonical place to curate the dev-deps list."
  - "Did not modify apps/web/bootstrap/app.php beyond Laravel 12 defaults. The plan's task 2 Behavior bullet mentions 'app()->setLocale(...)' and 'trusts the standard middleware stack (Inertia/HandleInertiaRequests added in plan 06)' — but the explicit <action> steps 1-8 do not include any bootstrap/app.php edits, and Inertia middleware lands in plan 01-06. Left bootstrap/app.php as Laravel 12 default."
metrics:
  tasks_completed: 2
  files_created: 57   # Laravel 12 skeleton (~55 tracked) + extensions migration + summary
  files_modified: 4
  files_deleted: 4
  duration_minutes: 7
  completed: 2026-05-03
---

# Phase 01 Plan 04: Laravel 12 scaffold inside web container — Summary

**One-liner:** Scaffolds Laravel 12.58.0 into `apps/web/` via `composer create-project laravel/laravel . ^12.0` inside the `web` container (D-021), wires `pgsql` as the default database connection, configures session/cache/queue to `redis`, declares the Discord Socialite credentials block in `config/services.php`, and ships the FIRST migration enabling Postgres `uuid-ossp` + `pgcrypto` + `citext` extensions (Pitfall 5 mitigation) — the foundation plan 10's UUID-PK schema migrations build on.

## What was built

This plan fills the bind-mounted `apps/web/` directory (which was an empty placeholder after plan 01-01) with a complete Laravel 12 application skeleton, then configures it to use the docker-compose-provided `postgres` and `redis` services. After this plan, `docker compose exec web php artisan about` reports a fully wired application: Laravel 12.58.0, PHP 8.4.20, Composer 2.9.7, Database=pgsql, Cache+Session+Queue=redis. The first table migration in plan 10 will be unblocked because the required Postgres extensions (`uuid-ossp` for `gen_random_uuid()`-style UUID v4, `pgcrypto` as a fallback UUID source, `citext` for case-insensitive email column) are already enabled in the `trenchwars` database.

### Task 1 — `composer create-project laravel/laravel . ^12.0` inside web container (commit `c455b04`)

- Brought up postgres + redis (`docker compose up -d postgres redis`) and waited for both healthchecks to report `healthy` before proceeding.
- Built the `web` image (`docker compose build web` — first build of the day; pulled `php:8.4-fpm-bookworm`, installed system libs, compiled `intl + pdo_pgsql + pgsql + gd + bcmath + zip + mbstring + pcntl + exif + opcache`, `pecl install redis-6.1.0`, copied Composer 2.9.7 from multi-stage builder, installed Node 22 + corepack pnpm@9.15.0; ~3 minutes wall).
- Removed `apps/web/.gitkeep` placeholder from plan 01-01 so composer wouldn't see it as a non-empty-directory blocker.
- Ran `docker compose run --rm --workdir /tmp --entrypoint sh web -c "composer create-project --prefer-dist laravel/laravel /tmp/laravel ^12.0 --no-interaction && <move script>"` — see Deviations section for why we used /tmp + move script rather than the plan's direct-into-/app approach.
- Composer pulled 111 packages, ran the post-install script chain (autoload dump, package:discover, key:generate, vendor:publish, migrate --graceful) and installed `vendor/` into the web_vendor named volume.
- Re-ran `docker compose exec web php artisan key:generate --force` per plan instruction (later detected as a Rule 1 bug — see Deviations).
- `chown -R 1000:1000 /app` inside container so host user owns the scaffolded files (composer ran as root; bind-mount preserves UID/GID).
- Verified `docker compose exec web php artisan --version` returns `Laravel Framework 12.58.0` and `php --version` returns `PHP 8.4.20`.
- Committed the entire Laravel skeleton (~55 tracked files): `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, plus root files (`composer.json`, `composer.lock`, `package.json`, `vite.config.js`, `phpunit.xml`, `artisan`, `.editorconfig`, `.env.example`, `.gitattributes`, `.gitignore`, `README.md`).

### Task 2 — pgsql + redis + Discord wiring + Postgres extensions migration (commit `caf0200`)

- **`apps/web/.env.example` overwritten** with the Trenchwars-side env shape per plan: `APP_NAME=Trenchwars`, `APP_TIMEZONE=UTC`, `APP_URL=http://localhost:8000`, `DB_CONNECTION=pgsql` + `DB_HOST=postgres` + `DB_DATABASE=trenchwars` + `DB_USERNAME=trenchwars` + `DB_PASSWORD=trenchwars`, `SESSION_DRIVER=redis` + `SESSION_LIFETIME=43200` (30d remember-me) + `SESSION_SAME_SITE=lax` + `SESSION_SECURE_COOKIE=false` (localhost http; toggled in production), `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_HOST=redis`, `MAIL_MAILER=log`, `DISCORD_CLIENT_ID=` + `DISCORD_CLIENT_SECRET=` + `DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback` (with comments linking to Discord developer portal). All secret-bearing fields empty — T-1-15-b mitigation.
- **`apps/web/.env`** reset from .env.example then `php artisan key:generate --force` once → single clean `APP_KEY=base64:jxid8sQLkLqf7mXQWiq73miEE0wcA5ZW8VIjWJdUgdA=`.
- **`apps/web/config/database.php`** — `'default' => env('DB_CONNECTION', 'pgsql')` (was `'sqlite'`). The pgsql connection block already matched plan spec (host + port + database + username + password + search_path=public + sslmode=prefer), so no further changes needed there.
- **`apps/web/config/services.php`** — Appended `'discord'` provider block reading `DISCORD_CLIENT_ID` / `DISCORD_CLIENT_SECRET` / `DISCORD_REDIRECT_URI` from env. Source comment cites RESEARCH Pattern 1 + socialiteproviders.com/Discord.
- **`apps/web/config/session.php`** — `'driver' => env('SESSION_DRIVER', 'redis')` (was `'database'`); `'lifetime' => (int) env('SESSION_LIFETIME', 43200)` (was 120). Other Pitfall-3-relevant settings (`same_site=lax`, `secure=env('SESSION_SECURE_COOKIE')`, `http_only=true`, `partitioned=false`) already matched plan spec in Laravel 12 default.
- **`apps/web/database/migrations/0001_01_01_000000_enable_postgres_extensions.php`** — New migration enabling `uuid-ossp` + `pgcrypto` + `citext`. Down migration intentionally empty (extensions may be relied on by other migrations; cascade-drop would damage citext columns). Header comment cites Pitfall 5 + the alphabetical-sort-before-create-users-table reasoning + the create_users_table deletion strategy.
- **Deleted Laravel default migrations** — `database/migrations/0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`. Cache + jobs use redis driver (no tables needed). Users table replaced by plan 10's UUID-PK + discord_id schema.
- **Ran `php artisan config:clear` then `php artisan migrate --force`** → extensions migration succeeded (`20.23ms DONE`).
- **Verified extensions in postgres:** `SELECT extname FROM pg_extension WHERE extname IN ('uuid-ossp','pgcrypto','citext')` → 3 rows (citext 1.6, pgcrypto 1.3, uuid-ossp 1.1).
- **Ran `php artisan migrate:fresh --force`** post-commit to clean up Laravel-default schema that composer's post-install migrate had silently created (Rule 1 — see Deviations). Final DB state: only `migrations` table + extensions; ready for plan 10 UUID-PK schema.

## Verification results

### Task 1 acceptance criteria

| Criterion | Result | Evidence |
| --------- | ------ | -------- |
| `apps/web/composer.json` exists & declares `laravel/framework: ^12.0` | PASS | `grep '"laravel/framework"' composer.json` matches; `grep '"^12\\.' composer.json` matches |
| `apps/web/artisan` exists and is executable | PASS | `test -x apps/web/artisan` returns 0 |
| `docker compose exec web php artisan --version` reports Laravel 12.x | PASS | `Laravel Framework 12.58.0` |
| `apps/web/.env` exists (NOT committed) with generated APP_KEY | PASS | `.env` exists; `.gitignore` excludes `.env`; `APP_KEY=base64:jxid8sQLkLqf7mXQWiq73miEE0wcA5ZW8VIjWJdUgdA=` |
| `apps/web/.gitkeep` removed | PASS | `! test -f apps/web/.gitkeep` returns 0 |

### Task 2 acceptance criteria

| Criterion | Result | Evidence |
| --------- | ------ | -------- |
| `apps/web/.env.example` mirrors repo-root shape (DB_CONNECTION=pgsql, REDIS, SESSION_DRIVER=redis, DISCORD_*) | PASS | All 4 grep patterns matched |
| `apps/web/config/services.php` has `discord` provider block | PASS | `grep "'discord'"` matches; `grep DISCORD_CLIENT_ID` matches |
| `apps/web/config/database.php` default connection is pgsql | PASS | `grep "'default' => env('DB_CONNECTION', 'pgsql')"` matches |
| Extensions migration exists & runs successfully | PASS | File exists; `migrate` reported `0001_01_01_000000_enable_postgres_extensions ... DONE` |
| Laravel default users/cache/jobs migrations REMOVED | PASS | All 3 `! test -f ...` checks return 0 |
| Postgres `pg_extension` table contains uuid-ossp, pgcrypto, citext | PASS | psql query returned 3 rows |

### Plan-level must_haves

**Truth statements:**

- ✅ **"`apps/web/` is a working Laravel 12 application created via `composer create-project laravel/laravel . ^12.0` inside the web container."** — Verified: artisan `--version` reports Laravel Framework 12.58.0; container-only execution per D-021.
- ✅ **"`make artisan ARGS=\"about\"` reports Laravel 12.x running on PHP 8.4."** — Verified via `docker compose exec web php artisan about` (host has no `make` installed; raw docker compose form documented in CLAUDE.md §1 is equivalent): `Laravel Version 12.58.0`, `PHP Version 8.4.20`, `Composer Version 2.9.7`. The `make` alias works any time `make` is on host (CI runners have it).
- ✅ **"`make migrate` runs the postgres-extensions migration first and succeeds against the postgres service."** — Verified: `php artisan migrate` ran the extensions migration; subsequent `migrate:fresh` confirmed it's the only migration on disk and runs in batch 1.
- ✅ **"Postgres extensions `uuid-ossp`, `citext`, `pgcrypto` are enabled in the trenchwars database."** — Verified via `SELECT extname FROM pg_extension`: 3 rows.
- ✅ **"Discord OAuth env keys (`DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI`) read by `config/services.php`."** — Verified: services.php contains the `'discord'` block with all three `env(...)` reads.
- ✅ **"Session driver is redis; cache store redis; queue connection redis (per .env)."** — Verified via `php artisan about`: Cache=redis, Session=redis, Queue=redis.

**Artifacts:**

- ✅ `apps/web/composer.json` contains `"laravel/framework"` (line 12).
- ✅ `apps/web/database/migrations/0001_01_01_000000_enable_postgres_extensions.php` contains `CREATE EXTENSION` (3 occurrences).
- ✅ `apps/web/config/services.php` contains `'discord'` (provider block).
- ✅ `apps/web/config/database.php` contains `'pgsql'` (default + connection block).

**Key links:**

- ✅ `apps/web/config/services.php` → `.env DISCORD_*` via `env('DISCORD_CLIENT_ID')` (regex `env\\('DISCORD_CLIENT_ID'` matches).
- ✅ `apps/web/database/migrations/0001_01_01_000000_enable_postgres_extensions.php` → Postgres database via `DB::statement` (regex `uuid-ossp` matches in the statement).

### Requirements completion

PLAN frontmatter `requirements:` field lists:

- **REQ-constraint-railway-deploy** — Foundation laid: `apps/web/.env.example` documents the env-key shape that Railway env groups will populate; runtime env vars (DB_CONNECTION, REDIS_HOST, etc.) are sourced from compose at dev-time and from Railway env groups at prod-time per D-014. Railway's Postgres plugin allows `CREATE EXTENSION` for uuid-ossp/citext/pgcrypto (verified per RESEARCH Pitfall 5).
- **REQ-constraint-en-launch-i18n-ready** — Foundation laid: APP_LOCALE=en, APP_FALLBACK_LOCALE=en in .env.example; APP_FAKER_LOCALE=en_US for tests. Full i18n end-to-end wiring lands in plan 01-08; this plan only sets the locale defaults.

Both will be marked complete via `gsd-sdk query requirements.mark-complete` in the state-update step (foundational portion satisfied; full implementation lands in plans 01-08 and 01-16).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] composer create-project failed because /app was non-empty (named volumes + entrypoint artifacts)**

- **Found during:** Task 1, on first invocation of the plan's prescribed `docker compose run --rm --workdir /app web composer create-project --prefer-dist laravel/laravel . "^12.0" --no-interaction`.
- **Issue:** Composer aborted with `Project directory "/app/." is not empty.` This was caused by:
  1. The web service's named volumes mount empty `vendor/` and `node_modules/` directories at `/app/vendor` and `/app/node_modules` (plan 01-02 set these up to isolate Composer/pnpm install from host bind-mount).
  2. The `docker/web/entrypoint.sh` script (plan 01-02) creates `/app/storage/{app/public,framework/{cache,sessions,views,testing},logs}` and `/app/bootstrap/cache` with mode 0775 BEFORE invoking the user's command.
  - The plan author anticipated a `.gitkeep` collision (its `<action>` "Important" workaround uses `find -maxdepth 1 -name '.gitkeep' -delete`) but not the named-volume + entrypoint collision.
- **Fix:** Scaffolded into `/tmp/laravel` inside the web container (using `--entrypoint sh` to bypass the entrypoint), then wrote a small inline shell script that:
  1. For each top-level item in `/tmp/laravel` except `vendor/` and `node_modules/`: if the same name doesn't exist in `/app`, `mv` it; if it does (e.g. `bootstrap/`, `storage/` from entrypoint), `cp -a $item/. /app/$item/` to merge the trees.
  2. For `vendor/`: `cp -a /tmp/laravel/vendor/. /app/vendor/` because `/app/vendor` is the named-volume mount target (composer needs the dependencies there, not in /tmp/laravel/vendor which is ephemeral after the container exits).
- **Files modified:** Method-only deviation; final filesystem state matches plan intent. No persistent file changes beyond what the plan prescribed.
- **Commit:** `c455b04` (the result is the same Laravel skeleton; only the path to get there was different).

**2. [Rule 1 - Bug] Duplicate APP_KEY concatenation in apps/web/.env**

- **Found during:** Task 1 verification, after running `docker compose exec web php artisan key:generate --force` per plan step 4.
- **Issue:** `composer create-project` runs `@php artisan key:generate --ansi` as a `post-create-project-cmd` script, which sets `APP_KEY=base64:<key1>=` in the freshly scaffolded `.env`. The plan's task 1 step 4 prescribes a SECOND `key:generate --force` to "ensure" the key is set — but Laravel's KeyGenerateCommand uses a regex replace on the line `APP_KEY=...` and on a line that already ends with `=`, the regex appended `base64:<key2>=` instead of replacing it, producing the malformed `APP_KEY=base64:<key1>=base64:<key2>=`.
- **Fix:** In task 2, after writing the new `.env.example`, ran `cp .env.example .env` from host (overwriting the malformed file with the clean template that has empty `APP_KEY=`) and then ran `php artisan key:generate --force` ONCE inside the container. Final state: single clean `APP_KEY=base64:jxid8sQLkLqf7mXQWiq73miEE0wcA5ZW8VIjWJdUgdA=`.
- **Files modified:** `apps/web/.env` (gitignored — not committed).
- **Commit:** No code commit needed — the task-1-committed `.env.example` had `APP_KEY=` empty and the .env file is gitignored. The fix lives in the local working tree.

**3. [Rule 1 - Bug] Postgres has stale Laravel-default schema (users/cache/jobs/sessions tables) from composer's post-install migrate**

- **Found during:** Post-task-2 verification when running `php artisan migrate:status` and inspecting the `migrations` table in postgres.
- **Issue:** `composer create-project` runs `@php artisan migrate --graceful --ansi` as a post-install script (Laravel 12 default behavior). At that moment, the web container's runtime env had `DB_CONNECTION=pgsql` (injected by docker-compose `environment:` block, which takes precedence over `.env`'s `DB_CONNECTION=sqlite`). Result: the Laravel default `users`, `cache`, `cache_locks`, `failed_jobs`, `jobs`, `job_batches`, `password_reset_tokens`, `sessions` tables were created in the trenchwars postgres database — schema we explicitly do NOT want (plan 10 authors UUID-PK replacements with Discord-OAuth-only fields, no password_reset_tokens, etc.). The migrations table also had records for the deleted-from-disk migrations.
- **Fix:** Ran `docker compose exec -T web php artisan migrate:fresh --force` after the task 2 commit. This dropped all tables (Postgres extension definitions are not affected by `migrate:fresh` because they live at the database level, not in user tables) and re-ran the only on-disk migration (the extensions migration). Final DB state: `migrations` table (1 row: extensions migration in batch 1) + 3 enabled extensions; ready for plan 10's UUID-PK schema.
- **Files modified:** None (DB state only, not code).
- **Commit:** No code commit (DB state lives in the `pg_data` named volume, not in git).

### Process notes (not behavior deviations)

- **SUMMARY filename:** Plan `<output>` block proposes `.planning/phases/01-foundations/01-foundations-04-SUMMARY.md`; the orchestrator prompt and the canonical `{phase}-{plan}-SUMMARY.md` convention used by plans 01-01..01-03 call for `01-04-SUMMARY.md`. Used the canonical form to maintain consistency.
- **Host has no `make` installed:** All plan-prescribed `make ...` commands were executed via the equivalent `docker compose ...` form (CLAUDE.md §1 documents both). No behavior change.

No Rule 4 (architectural) decisions surfaced.

## Authentication gates

None encountered. (Discord credentials are declared empty in `.env.example`; no actual OAuth flow runs in this plan — that's plan 01-09. Postgres + Redis use dev-default creds from compose.)

## Threat surface scan

The plan's threat register declares two boundaries (`.env` → app config, app → postgres) and five threats. All `mitigate` dispositions verified:

| Threat ID | Disposition | Mitigation Verified |
| --------- | ----------- | ------------------- |
| T-1-15-a (Information Disclosure: apps/web/.env) | mitigate | ✅ — `.env` is gitignored at both repo root (`.gitignore`) and in Laravel's own `apps/web/.gitignore`; `git status` confirms `.env` does not appear in tracked files; commit `c455b04` did not include `.env`. |
| T-1-15-b (Information Disclosure: apps/web/.env.example) | mitigate | ✅ — Audit: `grep -v '^#' apps/web/.env.example \| grep '=' \| grep -vE '=$\|=null$\|="\\$\\{'` returns only non-secret defaults: `APP_NAME=Trenchwars`, `APP_ENV=local`, `APP_DEBUG=true`, `APP_TIMEZONE=UTC`, `APP_URL=http://localhost:8000`, `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en`, `APP_FAKER_LOCALE=en_US`, `APP_MAINTENANCE_DRIVER=file`, `PHP_CLI_SERVER_WORKERS=4`, `BCRYPT_ROUNDS=12`, `LOG_CHANNEL=stack`, `LOG_STACK=single`, `LOG_LEVEL=debug`, `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`, `DB_DATABASE=trenchwars`, `DB_USERNAME=trenchwars`, `DB_PASSWORD=trenchwars` (dev creds — bound to localhost, T-1-12-b accepted in plan 01-02), `SESSION_DRIVER=redis`, `SESSION_LIFETIME=43200`, `SESSION_ENCRYPT=false`, `SESSION_PATH=/`, `SESSION_DOMAIN=null`, `SESSION_SAME_SITE=lax`, `SESSION_SECURE_COOKIE=false`, `BROADCAST_CONNECTION=log`, `FILESYSTEM_DISK=local`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `CACHE_PREFIX=`, `MEMCACHED_HOST=127.0.0.1`, `REDIS_CLIENT=phpredis`, `REDIS_HOST=redis`, `REDIS_PASSWORD=null`, `REDIS_PORT=6379`, `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS="hello@trenchwars.local"`, `DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback`. All secret-bearing fields (`APP_KEY`, `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`) are empty. |
| T-1-16 (Tampering: DB::statement in extensions migration) | accept | ✅ — Verified: only literal SQL strings (`'CREATE EXTENSION IF NOT EXISTS "uuid-ossp";'` etc.); no user input flows into the migration. |
| T-1-06 (Tampering / CSRF: session config) | mitigate | ✅ — `session.php` `same_site=env('SESSION_SAME_SITE', 'lax')` (Pitfall 3); `secure=env('SESSION_SECURE_COOKIE')` (false on localhost http; true in prod via Railway env override); `http_only=env('SESSION_HTTP_ONLY', true)`; `partitioned=false`. Inertia/HandleInertiaRequests middleware lands in plan 01-06 and handles XSRF cookie automatically — no `<meta name="csrf-token">` in any Blade template (verified: `apps/web/resources/views/welcome.blade.php` is Laravel's marketing-page default with no csrf meta tag). |
| T-1-07 (Spoofing: session hijacking) | mitigate | ✅ — `session.php` driver=redis (NOT file); cookie httpOnly + secure-in-prod + samesite=lax; Laravel's built-in `Auth::login()` regenerates session ID on login (default behavior, not changed by this plan). |

**Threat flags:** None — no new security-relevant surface (no endpoints, no auth paths, no schema beyond extensions migration) introduced beyond the register.

## Commits

- `c455b04` — `feat(01-04): scaffold Laravel 12 skeleton in apps/web via composer create-project`
- `caf0200` — `feat(01-04): wire pgsql + redis + Discord env; add postgres extensions migration`

## Next steps (handed to subsequent plans)

- **Plan 01-05** (Composer dev tools — Pint, Larastan, Pest, spatie/*, Filament, Socialite) — installs into `apps/web/composer.json` via `docker compose exec web composer require ...`. The `composer.json` + `composer.lock` from this plan are the install target.
- **Plan 01-06** (Inertia v2 + Vue 3 + Vite) — modifies `apps/web/bootstrap/app.php` to register `HandleInertiaRequests` middleware; relies on session driver = redis (set here).
- **Plan 01-07** (Tailwind v4 + dual-Tailwind workaround) — replaces `apps/web/resources/css/app.css` and adds plugins to `apps/web/vite.config.js`.
- **Plan 01-08** (i18n end-to-end) — relies on APP_LOCALE / APP_FALLBACK_LOCALE in .env.example; creates `apps/web/lang/en/{auth,common,validation,admin}.php`.
- **Plan 01-09** (Discord Socialite OAuth) — registers `socialiteproviders/discord` provider via `Event::listen` in `app/Providers/AppServiceProvider.php`; consumes `config/services.php['discord']` block authored here.
- **Plan 01-10** (UUID-PK users + players + player_privacy schema) — relies on `uuid-ossp` + `citext` extensions enabled here; replaces the deleted Laravel-default users migration with a UUID-PK + discord_id + citext-email schema.
- **Plan 01-12** (Filament v3) — installs into composer.json; uses pgsql connection.
- **Plan 01-15** (DTO pipeline) — registers spatie/laravel-data + typescript-transformer in composer.json.
- **Plan 01-18** (BLOCKING smoke test) — exercises the full stack-up path including this plan's `make migrate` and `php artisan about`.

## Self-Check: PASSED

**Files exist:**

- `/home/rtx/projects/trench-wars/apps/web/composer.json` — FOUND
- `/home/rtx/projects/trench-wars/apps/web/composer.lock` — FOUND
- `/home/rtx/projects/trench-wars/apps/web/artisan` — FOUND (executable)
- `/home/rtx/projects/trench-wars/apps/web/bootstrap/app.php` — FOUND
- `/home/rtx/projects/trench-wars/apps/web/public/index.php` — FOUND
- `/home/rtx/projects/trench-wars/apps/web/.env.example` — FOUND (66 lines)
- `/home/rtx/projects/trench-wars/apps/web/config/database.php` — FOUND (default=pgsql)
- `/home/rtx/projects/trench-wars/apps/web/config/services.php` — FOUND (discord block present)
- `/home/rtx/projects/trench-wars/apps/web/config/session.php` — FOUND (driver=redis, lifetime=43200)
- `/home/rtx/projects/trench-wars/apps/web/database/migrations/0001_01_01_000000_enable_postgres_extensions.php` — FOUND (37 lines)
- `/home/rtx/projects/trench-wars/apps/web/database/migrations/0001_01_01_000000_create_users_table.php` — MISSING (intentional — deleted)
- `/home/rtx/projects/trench-wars/apps/web/database/migrations/0001_01_01_000001_create_cache_table.php` — MISSING (intentional — deleted)
- `/home/rtx/projects/trench-wars/apps/web/database/migrations/0001_01_01_000002_create_jobs_table.php` — MISSING (intentional — deleted)

**Commits exist:**

- `c455b04` — FOUND in `git log` (`feat(01-04): scaffold Laravel 12 skeleton in apps/web via composer create-project`)
- `caf0200` — FOUND in `git log` (`feat(01-04): wire pgsql + redis + Discord env; add postgres extensions migration`)

**Runtime verification:**

- `docker compose exec web php artisan about` reports Laravel Framework 12.58.0 + PHP 8.4.20 + Composer 2.9.7
- `docker compose exec web php artisan about` reports Cache=redis, Database=pgsql, Queue=redis, Session=redis
- `docker compose exec postgres psql -U trenchwars -d trenchwars -tAc "SELECT count(*) FROM pg_extension WHERE extname IN ('uuid-ossp','pgcrypto','citext');"` returns `3`
- `docker compose exec web php artisan migrate:status` returns: `0001_01_01_000000_enable_postgres_extensions ... [1] Ran` (after migrate:fresh)
- All 4 stack services healthy: `web` healthy, `web-nginx` healthy, `postgres` healthy, `redis` healthy
