# Trenchwars — AI/Developer Conventions

This file is the contract for every AI agent and developer touching this codebase.
It is loaded automatically by GSD planners and executors. Read it before writing any code.

> Locked architectural decisions live in `.planning/PROJECT.md` (table `D-001..D-021`).
> When this file conflicts with PROJECT.md, PROJECT.md wins — open a new D-### that supersedes the old one rather than editing in place.

---

## 1. Container-Only Commands (D-021 LOCKED)

ALL `composer`, `php`, `php artisan`, `pnpm`, `npm`, `node`, and `vite` commands run **inside containers**. Host-level installs of PHP, Postgres, Redis, and Composer are NOT used and MUST NOT be referenced in plans, scripts, or docs.

Use the Makefile aliases from repo root:

```bash
make up                              # docker compose up -d
make shell                           # bash inside web container
make artisan ARGS="migrate"          # php artisan migrate
make composer ARGS="require foo/bar" # composer require ...
make pnpm ARGS="install"             # pnpm install (inside web container, also resolves apps/bot, apps/rcon-worker via workspaces)
make pest                            # ./vendor/bin/pest
make pint                            # ./vendor/bin/pint
make phpstan                         # ./vendor/bin/phpstan analyse
```

Or directly:

```bash
docker compose exec web php artisan migrate
docker compose exec web ./vendor/bin/pest --filter=AuthDiscordOAuthTest
```

**Never** document, suggest, or commit a command that runs on the host PHP. The host PHP is 8.3 with broken `intl`; the container PHP is 8.4 with all extensions.

## 2. Stack & Versions (D-001 LOCKED)

| Layer | Library | Version |
|---|---|---|
| Web framework | `laravel/framework` | `^12.0` (PHP 8.4) |
| Server adapter | `inertiajs/inertia-laravel` | `^2.0` (legacy v2 protocol) |
| Frontend | Vue 3 + `@inertiajs/vue3@^2` + Vite ^6 | |
| CSS | Tailwind v4 (CSS-first via `@theme`) | with `tailwindcss-v3@npm:tailwindcss@^3.4` aliased install for Filament theme only |
| Admin panel | `filament/filament` | `^3.3` (v3 LOCKED — see plan 12 for dual-Tailwind workaround) |
| OAuth | `laravel/socialite` + `socialiteproviders/discord` | `^5.27` + `^4.2` |
| RBAC | `spatie/laravel-permission` | `^7.4` |
| Audit | `spatie/laravel-activitylog` | `^5.0` (PHP 8.4 floor) |
| Translatable | `spatie/laravel-translatable` | `^6.14` |
| DTOs / TS | `spatie/laravel-data` + `spatie/laravel-typescript-transformer` | `^4.22` + `^3.0` |
| Bot | Node 22 + `discord.js@^14.26` (Phase 5 only) | |
| RCON worker | Node 22 + `undici` + `ws` (Phase 8 only) | |
| Datastores | Postgres 16 + Redis 7 | |

Pinning rationale and conflicts (Tailwind v4 vs Filament v3) — see `.planning/phases/01-foundations/01-RESEARCH.md`.

## 3. Code Style & Static Analysis

- **Pint** — `make pint` (write) / `make pint ARGS="--test"` (CI gate). Default Laravel preset.
- **Larastan / PHPStan level 8** — `make phpstan`. Baseline at `apps/web/phpstan-baseline.neon` is regenerated only with explicit user request.
- **Pre-commit:** Pint + PHPStan are CI gates (plan 16). Failing either blocks merge.

## 4. Test Conventions

- **Pest** (NOT PHPUnit syntax). `it('does the thing', ...)`, `test('does the thing', ...)`, `expect(...)`.
- **Feature** tests in `apps/web/tests/Feature/`. **Unit** in `apps/web/tests/Unit/`. Browser tests are **deferred** in P1.
- **Always inside the container:** `make pest ARGS="--filter=DiscordLoginTest"` or `docker compose exec web ./vendor/bin/pest`.
- **Shared traits** (`RefreshDatabase`, `Auth` helpers) wired in `apps/web/tests/Pest.php`.
- **Wave 0 test scaffolding** for every phase precedes implementation (per `.planning/phases/{phase}/*-VALIDATION.md`).

## 5. Path Conventions

Everything Laravel-related lives in `apps/web/` (D-015 LOCKED). The repo root is **not** a Laravel project — running `php artisan ...` from the repo root will not work.

| Lives in | Contents |
|---|---|
| `apps/web/` | Laravel 12 application (PHP, Inertia, Filament, Vue) |
| `apps/bot/` | Discord bot (Node, TS) — Phase 5 implementation |
| `apps/rcon-worker/` | CRCON worker (Node, TS) — Phase 8 implementation |
| `packages/shared-types/` | Generated TS types from `spatie/laravel-data` |
| `docker/` | Per-service Dockerfiles + nginx + php.ini |
| `docker-compose.yml` | Local dev stack (D-021) |
| `.planning/` | GSD planning artifacts (do NOT touch from app code) |
| `.docs/` | Frozen design docs (read-only reference) |

Composer stays inside `apps/web/`; do **not** hoist `composer.json` to the repo root.

## 6. Security

- **Never commit secrets.** `.env` is gitignored; `.env.example` documents shape only with empty values for all secret-like fields.
- **Discord client_secret + bot token are sensitive.** They live in Railway env groups (D-014) in production and in your local `.env` for dev.
- **CSRF:** Inertia handles XSRF via cookie automatically. Do NOT add `<meta name="csrf-token">` to `apps/web/resources/views/app.blade.php` (research Pitfall 3).
- **Discord redirect_uri:** must EXACTLY match what's registered on https://discord.com/developers/applications, including trailing slash and protocol (research Pitfall 2).
- **Session cookie:** `SameSite=Lax`, `HttpOnly`, `Secure` only in production. `SESSION_SECURE_COOKIE=false` for local HTTP.
- **Postgres extensions** (`uuid-ossp`, `citext`) are enabled by the **first migration**, not by the postgres image (research Pitfall 5).
- **Activity log writes are append-only via the `LogsActivity` trait** — Filament admin UI never exposes edit/delete on `activity_log` rows.
- **Spatie permission guard** must match Filament's panel guard (`web`) — set `default_guard => 'web'` in `config/permission.php` (research Pitfall 4).

## 7. i18n (D-013 LOCKED)

- **Every UI string** flows through `__()` (PHP/Blade) or `t()` (Vue). Hardcoded strings are a CI failure (Pest static test in plan 08).
- **PHP arrays only** for canonical English: `apps/web/lang/en/{auth,common,validation,admin}.php`. NO JSON locale files in P1.
- **Translatable user content** (clan descriptions, etc.) uses `spatie/laravel-translatable` with JSONB columns keyed by locale. Phase 2+ introduces models that use this; the trait + columns are ready in P1.
- **Locale resolution order:** `user.locale` (DB) → `?lang=` query → cookie → Accept-Language header → `en` fallback.
- **i18n key naming:** namespaced by surface area (`auth.*`, `common.*`, `admin.*`, `validation.*`), snake-case, parameter interpolation via `:?param`.
- **No URL locale prefix** at launch (D-013).

## 8. Architecture Constraints

- **Discord bot is a thin display layer** (D-004). Bot code in `apps/bot/` calls the Laravel API for every interaction. **No DB writes from the bot. No business logic in the bot.**
- **RCON worker → web is HMAC-signed** (CON-arch-rcon-to-web-comm). 60s replay window. Never run RCON business logic in the worker; it is a normaliser only.
- **Filament covers every domain entity** (D-012). Resources land in their owning phase. P1 ships only User, Player, Role, Permission resources.
- **Generic game model** (D-007). HLL is a seeded preset, not hardcoded. Adding a new game in Phase 3+ is data-only.
- **One active ClanMembership per player** (D-009). Enforced by partial unique index in Phase 2.
- **One Discord guild for the league** (D-003). `discord_guild` table holds exactly one row.
- **Discord ID is canonical user identity** (D-002). Stored as `text UNIQUE` (snowflake overflows JS `Number`).

## 9. Locked Decisions Quick Reference

| ID | Decision | Source |
|---|---|---|
| D-001 | Stack: Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 | PROJECT.md |
| D-002 | Auth: Discord OAuth only; Discord ID canonical | PROJECT.md |
| D-003 | One league Discord guild; clan = role inside guild | PROJECT.md |
| D-004 | Bot is thin display layer; no DB, no domain logic | PROJECT.md |
| D-005 | RCON via CRCON; league deploys CRCON alongside servers | PROJECT.md |
| D-006 | Multi-clan league platform; one deploy hosts many clans | PROJECT.md |
| D-007 | Generic Game/Role/MatchType tables; HLL seeded | PROJECT.md |
| D-008 | Tags m:n on clans; no internal sub-groups | PROJECT.md |
| D-009 | One active ClanMembership; history preserved | PROJECT.md |
| D-010 | Match signups by role slot; capacity row-locked | PROJECT.md |
| D-011 | Tournaments first-class round 1 (4 formats) | PROJECT.md |
| D-012 | Filament + spatie/activitylog audit infra | PROJECT.md |
| D-013 | i18n plumbed day one; EN at launch; no URL prefix | PROJECT.md |
| D-014 | Railway 5 services + Postgres + Redis plugins | PROJECT.md |
| D-015 | pnpm-workspaces monorepo | PROJECT.md |
| D-016 | Postgres 16 (over MySQL) | PROJECT.md |
| D-017 | No starter kit; hand-roll Discord Socialite auth | PROJECT.md |
| D-018 | Per-section + global tier player privacy | PROJECT.md |
| D-019 | CRCON live capture + manual override | PROJECT.md |
| D-020 | TS types from spatie/laravel-data | PROJECT.md |
| D-021 | Local dev via docker-compose; host PHP/Postgres/Redis NOT used | PROJECT.md |

## 10. When Updating This File

- This file is **convention**, not a runtime config. Edit it deliberately.
- Any change here that contradicts a `D-###` decision MUST be paired with a new superseding `D-###` in `.planning/PROJECT.md`.
- AI agents reading this file MUST follow it verbatim — when in doubt, refuse to deviate and surface the conflict to the human.
