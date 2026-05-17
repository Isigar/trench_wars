# Phase 1: Foundations - Research

**Researched:** 2026-05-03
**Domain:** Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 + Tailwind v4 + Discord OAuth + Railway monorepo deploy + i18n + audit + spatie packages
**Confidence:** HIGH (verified against Context7 + Packagist + npm registry on 2026-05-03; one MAJOR locked-decision conflict surfaced — Tailwind v4 vs Filament v3)

## Summary

Phase 1 ships a deployable monorepo skeleton that all later phases land on: pnpm workspaces at repo root, `docker-compose.yml` for parity-with-Railway local dev, Laravel 12 inside `apps/web` with Discord OAuth + Filament v3 admin + spatie packages + i18n end-to-end, and a Railway five-service deployment with GitHub Actions CI matrix. CONTEXT.md (smart-discuss output) is the definitive source — every decision tagged D-001..D-021 is locked, including the choice of Filament v3 (not v4/v5) and Tailwind v4 CSS-first.

**Three findings dominate this research and the planner must absorb all three before drafting plans:**

1. **MAJOR conflict — Tailwind v4 vs Filament v3.** Filament v3's official docs explicitly state v3 only supports Tailwind v3 for custom themes; v4 support arrives in Filament v4. Both D-001 (Filament v3) and CON-stack-frontend-libraries (Tailwind v4 CSS-first) are LOCKED. The known production-grade workaround is **two Vite configs + npm-aliased dual-Tailwind-version install** (Tailwind v4 for the public site, Tailwind v3 aliased for the Filament theme only). This must surface in the discuss-phase output and at minimum become its own dedicated task in the plan. [VERIFIED: filamentphp.com/docs/3.x/panels/themes; filamentthemes.com/guides/how-to-use-filament-with-tailwind-css-v4; nathangross.me/blog/using-filament-v3-with-tailwind-css-v4-a-pnpm-workspaces-approach]

2. **D-001 version pinning is two majors behind current.** Filament v5.6.2 is current (latest v3 = v3.3.50, released 2026-04-04). Inertia.js npm package `@inertiajs/vue3` has dist-tag `latest=3.0.3` and `legacy=2.3.21` — the v3 package corresponds to a new Inertia.js v3 protocol release. D-001 says "Inertia v2", which maps to `@inertiajs/vue3@^2.0` (latest in that line: 2.3.21). All locked. The planner pins to `@inertiajs/vue3@^2.0`, `inertiajs/inertia-laravel@^2.0` (NOT 3.x), `filament/filament:^3.3`, `tailwindcss@^4` (with `tailwindcss-v3@npm:tailwindcss@^3` alias for Filament). This is advisory-only — D-001 is locked — but the planner should mention the version-staleness in a task note so the user can review again before Phase 2. [VERIFIED: packagist.org/p2/filament/filament; npm registry 2026-05-03]

3. **Three categories of pre-existing OAuth/CSRF landmines** that bite first-time scaffolders of this exact stack: Discord redirect_uri must EXACTLY match what's registered on the Discord Developer Portal (including trailing slash and protocol); Inertia + CSRF requires `HandleInertiaRequests` middleware in the `web` group AND no `<meta name="csrf-token">` in the Blade root template (Inertia handles XSRF via cookie); session cookie `SameSite=Lax` works for OAuth redirects on `localhost`, `SameSite=None` requires `Secure=true` which fails on plain HTTP localhost. Standard prevention is documented in pitfall section.

**Primary recommendation:** Plans for Phase 1 should follow this strict execution order: (1) repo-root scaffolding (pnpm workspace + docker-compose.yml + .env.example + Makefile), (2) `composer create-project laravel/laravel apps/web` *inside* the `web` container, (3) database connection + extensions migration (uuid-ossp, citext), (4) Inertia v2 + Vue 3 + Vite + Tailwind v4 frontend pipeline, (5) Filament v3 install + dual-Tailwind workaround for theme, (6) spatie packages (permission, activitylog, translatable, data) + DTO TypeScript pipeline, (7) Discord Socialite + first-login provisioning + Filament canAccessPanel gate, (8) i18n shared-props plumbing (laravel-vue-i18n), (9) Pest smoke test + PHPStan/Pint config, (10) GitHub Actions matrix CI, (11) Railway service definitions + nixpacks/Dockerfile choice. Each is its own task or wave; the dual-Tailwind workaround (Filament v3 vs Tailwind v4) is the highest-risk task and should ship first within the frontend-pipeline wave.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**From CONTEXT.md `<decisions>`:**

#### Scaffolding Order & Layout
- **Scaffold order**: Init pnpm monorepo first (`apps/web`, `apps/bot`, `apps/rcon-worker`, `packages/shared-types`) → write `docker-compose.yml` (D-021) → bring up containers → `composer create-project laravel/laravel apps/web` *inside the web container* → wire Inertia v2 / Vue 3 / Filament v3 / Tailwind v4.
- **`apps/web` internal layout**: Standard Laravel tree (`app/`, `routes/`, `database/`, `resources/`, `lang/`, `tests/`). No `Domain/`, `Services/`, `ValueObjects/` abstractions in P1 — keep PSR-4 `app/` flat; introduce abstractions only when a phase actually needs them.
- **Composer dev tools day 1**: Pint, Larastan/PHPStan **level 8**, Pest, spatie/laravel-data, spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-translatable, filament/filament v3, laravel/socialite. Minimal config, baseline ignore file where needed.
- **Frontend pipeline**: Vite + `@vitejs/plugin-vue` + Tailwind v4 (CSS-first config, no `tailwind.config.js`) + Inertia v2 + `@inertiajs/vue3` + `ziggy-js` for routes. SSR config scaffolded but optional in dev (enabled in production-mode docker compose later).

#### Discord OAuth & First-Login Flow
- **Login UI surface**: Single "Log in with Discord" button on `/` (landing) → `GET /auth/discord/redirect` → callback `GET /auth/discord/callback`. No separate `/login` page in P1.
- **First-login provisioning**: Inside `DB::transaction()`: upsert `users` (by `discord_id`), create `players` (1:1 FK), create `player_privacy` with defaults (`show_to=community`, all section booleans `true`). Triggered by a `Login` event listener — testable, observable, idempotent on re-login.
- **OAuth scopes**: `identify` + `email` only. `guilds` scope deferred.
- **Post-login destination + session**: Redirect to `/` with success toast (Inertia flash). Session cookie `SameSite=Lax`, `Secure` in production, `HttpOnly`. Remember-me 30 days. No admin redirect; `/admin` reachable for users with `admin-access` permission.

#### Filament Admin, Audit & i18n
- **Filament panel & gating**: Mount at `/admin`. Gate via `admin-access` permission (spatie/laravel-permission). First admin seeded via `php artisan trenchwars:make-admin <discord_id>` (idempotent). Theme: dark default + light option; placeholder accent `#A4262C`.
- **P1 Filament resources**: Exactly 4 — **User**, **Player**, **Role**, **Permission**. Player resource shows linked `player_privacy` fields inline. No bulk actions or exports yet.
- **Audit infrastructure**: spatie/laravel-activitylog with `LogsActivity` trait on User and Player. Per-resource Audit tab inside each Filament resource. Global `/admin/audit` Filament custom page. `causer_type` derived from authenticated user. Indefinite retention.
- **i18n end-to-end wiring**: `lang/en/{auth,common,validation,admin}.php` PHP files. Inertia middleware shares a `translations` prop. Vue helper `t(key, params)` resolves from `usePage().props.translations` with `:?param` interpolation. Validation messages localized. No JSON translation files — PHP arrays only for canonical English. Translatable user content uses spatie/laravel-translatable JSONB columns.

**From PROJECT.md (D-001..D-021, all LOCKED — full list in PROJECT.md `<decisions>`):**
- D-001 Stack: Laravel 12 (PHP 8.4) + Inertia v2 + Vue 3 + Filament v3
- D-002 Auth: Discord OAuth only; Discord ID is canonical user identity
- D-013 i18n plumbed from day one; EN at launch; no URL locale prefix
- D-014 Hosting on Railway (5 services + Postgres + Redis plugins)
- D-015 pnpm-workspaces monorepo; `apps/web` (Laravel), `apps/bot`, `apps/rcon-worker`, `packages/shared-types`; Composer stays in `apps/web`
- D-016 Postgres 16 (over MySQL)
- D-017 No Laravel starter kit; hand-roll auth scaffolding around Discord Socialite
- D-020 TypeScript types generated from Laravel DTOs via spatie/laravel-data + `typescript:generate` artisan command
- D-021 Local dev via custom `docker-compose.yml` at repo root; all five Railway services containerized; host installs of PHP/Postgres/Redis are not used

### Claude's Discretion (from CONTEXT.md)
- Exact Vite chunking strategy.
- Specific Tailwind v4 token values beyond the placeholder accent.
- Pest test scaffolding shape (Pest's preset feature/unit split is fine).
- PHPStan baseline contents (just generate one if level 8 introduces existing issues from Filament).
- Specific Filament page slugs and column ordering inside resources.
- The exact set of activitylog events recorded per resource (covers create/update/delete by default; refine if it gets noisy).

### Deferred Ideas (OUT OF SCOPE)
- **`guilds` OAuth scope** — gate login on league-guild membership. Captured in PROJECT.md Open Questions. Decide before Phase 5.
- **Multi-locale support** — D-013 says ship EN only at launch; locale switcher and additional `lang/{cs,sk,pl,...}/*.php` files are out of P1.
- **JSON locale files for client-side i18n** — explicitly rejected for P1 (PHP arrays only).
- **`docker-compose.override.yml`** for prod-vs-dev variants — not needed yet.
- **Activity log retention sweeper** — round-1 indefinite; revisit at six months. No sweeper job in P1.
- **Bulk actions / exports in Filament** — not in P1 scope.
- **SSR enabled-by-default in production** — placeholder Vite SSR config is scaffolded but actually wiring SSR in `web` container is deferred.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-constraint-railway-deploy | Five-service Railway topology (web, worker, bot, rcon-worker, db, redis); secrets in Railway env groups; `.env.example` documents shape. Not optimised for self-hosting elsewhere round 1. | Railway monorepo deploy section (Architecture Patterns); env-var contracts (`${{Postgres.DATABASE_URL}}`, `${{Redis.REDIS_URL}}`); root-directory-per-service config; nixpacks vs Dockerfile decision documented in Common Pitfalls. |
| REQ-constraint-en-launch-i18n-ready | All UI strings via `__()` / `t()`; translatable models use jsonb keyed by locale; adding a locale is config + content task. English at launch; multi-language possible without refactor. | i18n end-to-end wiring section: PHP `lang/en/*.php` for static strings, Inertia `translations` shared prop via `HandleInertiaRequests::share`, `laravel-vue-i18n` plugin for `t()` helper, spatie/laravel-translatable JSONB for user content (no models in P1, but trait+columns ready). |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Discord OAuth redirect/callback | API/Backend (`apps/web` Laravel routes) | Browser (302 redirect target) | Sensitive client_secret + token exchange MUST stay server-side; Browser only follows 302 to Discord and back |
| First-login user provisioning | API/Backend (Login event listener inside `apps/web`) | Database (`users`, `players`, `player_privacy` rows in single transaction) | Atomicity requires DB transaction; never trust the client to create entitlement rows |
| Filament admin panel rendering | Frontend Server / SSR (Filament Livewire SSR; `apps/web` PHP-FPM) | Browser (Alpine.js + Livewire DOM updates) | Filament IS server-rendered — that's the architectural premise; browser only handles micro-interactions |
| Public landing page (`/`) | Frontend Server / SSR (Inertia SSR enabled in production) | Browser (Vue 3 hydration) | Per CON-frontend-goals: SSR for first paint on public pages |
| i18n string resolution (UI) | API/Backend (PHP `__()` for backend; pre-resolved into Inertia shared prop) | Browser (`t()` helper reads `usePage().props.translations`) | Locale resolution is a server concern (D-013 order: user.locale → ?lang=  → cookie → header → en); browser only renders pre-shared dictionary |
| Audit log writing (activitylog) | API/Backend (LogsActivity trait + `causer` resolution from auth) | Database (`activity_log` table) | Causer integrity requires server-trusted authentication context |
| Audit log viewing | Frontend Server / SSR (Filament Page) | Database (filtered query) | Read-only admin surface; no client write path |
| spatie/laravel-data DTOs → TypeScript | Build-time (artisan command emits `.d.ts`) | Source-control (committed `api.d.ts` + `packages/shared-types`) | Build artifact, not a runtime; emitted into both `resources/js/types/` and `packages/shared-types` for cross-app sharing |
| Static assets (CSS/JS bundles) | CDN/Static (Vite build → Railway static + Filament theme bundle) | Frontend Server (served via Laravel public/) | Vite emits hashed filenames; Filament Vite theme bundle is a separate output dir |
| Postgres data layer | Database/Storage (Postgres 16 + extensions: uuid-ossp, citext) | API/Backend (Laravel pgsql connection + Eloquent) | Postgres extensions enabled via migration; case-insensitive emails via `citext` |
| Redis (queues, cache, session) | Database/Storage (Redis 7) | API/Backend (Laravel Redis client; later Horizon) | P1 wires Redis but doesn't yet use queues/Horizon — that lands in Phase 5+ |

## Standard Stack

### Core (server, locked by D-001..D-021)

| Library | Version (verified 2026-05-03) | Purpose | Why Standard |
|---------|---|---------|--------------|
| `laravel/framework` | `^12.0` (D-001 LOCKED — current Laravel = 13.7.0; we're one major behind by choice) | Application framework | LOCKED. Owner choice; Filament v3 supports Laravel ^11.28+ ^12.0 ^13.0 so stable across multiple Laravel majors |
| `inertiajs/inertia-laravel` | `^2.0` (D-001 "Inertia v2"; current = 3.0.6 — locked to v2) | Server-side Inertia adapter | LOCKED to v2 protocol per D-001 |
| `laravel/socialite` | `^5.27` (5.27.0 released 2026-04-24) | OAuth client | Official package; Discord support via socialiteproviders extension |
| `socialiteproviders/discord` | `^4.2` (4.2.0 released 2023-07-24) | Discord OAuth provider | De facto Discord provider; mature, no recent breakage. Last release is 2023 — note: stable ≠ abandoned. Alternatives: `martinbean/socialite-discord-provider` (less mature), `revolution/socialite-discord` |
| `laravel/sanctum` | `^4.3` (4.3.1 released 2026-02-07) | API tokens for `bot`→`web` (Phase 5+) | Required by 03-stack.md; install in P1 even though use lands later |
| `filament/filament` | `^3.3` (3.3.50 released 2026-04-04 — D-001 v3 LOCKED; current latest = 5.6.2) | Admin panel | LOCKED v3 — see "Don't Hand-Roll" + "Common Pitfalls" for the Tailwind v4 conflict |
| `spatie/laravel-permission` | `^7.4` (7.4.1 released 2026-04-29) | Roles & permissions | Standard PHP RBAC; requires Laravel ^12 ^13 + PHP ^8.3 |
| `spatie/laravel-activitylog` | `^5.0` (5.0.0 released 2026-03-25 — major bump from v4) | Audit log | LogsActivity trait wires create/update/delete automatically. **v5 is breaking from v4**: requires PHP ^8.4 — fits our stack |
| `spatie/laravel-translatable` | `^6.14` (6.14.1 released 2026-04-23) | Translatable user content | JSONB-backed; for clan descriptions/etc. in P2+. Required to be installed + trait-ready in P1 per CONTEXT.md |
| `spatie/laravel-data` | `^4.22` (4.22.1 released 2026-04-27) | DTOs | Generates TS types via `spatie/laravel-typescript-transformer` |
| `spatie/laravel-typescript-transformer` | `^3.0` (3.0.3 released 2026-03-17) | TS generation | Companion package — `php artisan typescript:transform` emits `.d.ts` |
| `tightenco/ziggy` | `^2.6` (2.6.2 released 2026-03-05) | JS route helper | Provides `route()` matching Laravel's named routes; Vue plugin `ZiggyVue` |

### Core (frontend, locked)

| Library | Version (verified 2026-05-03) | Purpose | Why Standard |
|---------|---|---------|--------------|
| `vue` | `^3.5` (3.5.33 latest; v3.6 in alpha) | Vue 3 | LOCKED |
| `@inertiajs/vue3` | `^2.0` (2.3.21 latest in v2 line; npm dist-tag `legacy`. v3 = `latest` but D-001 says v2) | Inertia client | LOCKED to v2 protocol; install with explicit `^2.0` constraint |
| `vite` | `^6.0` (6.0.6) — see Pitfall about Vite 8 | Bundler | Vite 8.0.10 is npm `latest` but Laravel 12 ships templates targeting Vite 6; pin `^6.0` for stability |
| `@vitejs/plugin-vue` | `^6.0` (6.0.6 — npm `latest`; tracks Vite 6) | Vue SFC support | Required by Vite for `.vue` files |
| `laravel-vite-plugin` | `^2.0` (current Laravel 12 default) | Vite/Laravel bridge | Bundled with Laravel new-app skeleton |
| `tailwindcss` | `^4.0` (4.2.4 — CSS-first config per UI-SPEC.md) | Utility CSS | LOCKED. **Conflicts with Filament v3** — see Pitfall section |
| `@tailwindcss/vite` | `^4.0` (4.2.4) | Tailwind v4 Vite plugin | Required by v4 — replaces PostCSS plugin |
| `tailwindcss-v3` (npm alias to `tailwindcss@^3`) | aliased install (`tailwindcss-v3@npm:tailwindcss@^3.4`) | Filament theme compilation only | **Workaround required** — Filament v3 needs Tailwind v3 for theme.css |
| `ziggy-js` | `^2.6` (2.6.2) | Client-side `route()` | Pairs with `tightenco/ziggy` |
| `laravel-vue-i18n` | `^2.8` (2.8.0 — actively maintained at github.com/xiCO2k/laravel-vue-i18n) | Vue 3 i18n plugin | Reads Laravel `lang/*.php` arrays at build time + Inertia shared `translations` prop at runtime |
| `reka-ui` | `^2.9` (2.9.6) | Headless Vue primitives (UI-SPEC.md) | Vue port of Radix UI; replaces shadcn (which is React-only) |
| `lucide-vue-next` | `^0.x` (latest tag) | Icons | Per UI-SPEC.md |
| `vue-sonner` | latest | Toasts | Per UI-SPEC.md |
| `@fontsource-variable/inter`, `@fontsource-variable/jetbrains-mono` | latest | Self-hosted fonts | Per UI-SPEC.md |

### Dev tools (composer-require-dev)

| Library | Version | Purpose |
|---------|---|---------|
| `pestphp/pest` | `^4.7` (4.7.0 released 2026-05-03 — released TODAY) | Test framework (Laravel default) |
| `pestphp/pest-plugin-laravel` | `^4.x` | Pest Laravel helpers |
| `larastan/larastan` | `^3.9` (3.9.6 released 2026-04-16) | PHPStan extension; level 8 per success criteria |
| `laravel/pint` | `^1.29` (1.29.1 released 2026-04-20) | PHP code style |
| `pestphp/pest-plugin-browser` | `^4.x` (P1 may skip browser tests; Pest v4 ships browser support but Phase 1 success criteria don't require it) | Browser tests (deferred unless Phase 1 needs them) |

### Supporting (CI / build / infra)

| Library | Purpose | When to use |
|---------|---------|-------------|
| `shivammathur/setup-php@v2` | GitHub Actions PHP setup | CI workflow for `apps/web` |
| `actions/checkout@v4` | Repo checkout | All workflows |
| `actions/cache@v4` | Composer + pnpm cache | Speed up CI |
| `pnpm/action-setup@v4` | pnpm install in CI | All Node-side workflows |
| `actions/setup-node@v4` | Node 22 setup | bot + rcon-worker workflows |

### Alternatives Considered (and rejected for P1)

| Instead of | Could Use | Tradeoff / Why rejected |
|------------|-----------|----------|
| Filament v3 | Filament v4 (current GA) or v5 (current latest) | LOCKED to v3 by D-001. v4 supports Tailwind v4 natively, v5 has new schema/forms API. **Advisory only** — recommend the user re-review at Phase 2 boundary |
| `@inertiajs/vue3@^2` | `@inertiajs/vue3@^3` (current `latest` dist-tag) | LOCKED to v2 by D-001. Inertia v3 protocol exists and is now default; D-001 was authored when v2 was current. Advisory: revisit before Phase 5 |
| socialiteproviders/discord | `martinbean/socialite-discord-provider` | Less downloads, less proven. Stick with socialiteproviders. |
| laravel-vue-i18n | `vue-i18n` (canonical) + glob import lang JSON | laravel-vue-i18n reads Laravel PHP arrays directly = single source of truth (per CONTEXT.md "PHP arrays only") |
| Laravel Sail (`docker-compose.yml`) | Hand-rolled `docker-compose.yml` | LOCKED by D-021 to hand-rolled. Sail's compose generates extra services we don't want (mailpit, meilisearch, etc.) |
| Nixpacks (Railway default) | Custom Dockerfile per service | Nixpacks auto-detects Laravel and uses php-fpm + Caddy. **Recommend nixpacks** for `web`/`worker`/`bot`/`rcon-worker` to start; custom Dockerfile is escape-hatch if Nixpacks fails on monorepo subdirectory builds |

**Installation commands (composer side, in `apps/web` inside the `web` container):**

```bash
# Initial Laravel skeleton (creates apps/web/)
docker compose run --rm web composer create-project --prefer-dist laravel/laravel . "^12.0"

# Production deps
docker compose exec web composer require \
  inertiajs/inertia-laravel:"^2.0" \
  laravel/socialite:"^5.27" \
  socialiteproviders/discord:"^4.2" \
  laravel/sanctum:"^4.3" \
  filament/filament:"^3.3" \
  spatie/laravel-permission:"^7.4" \
  spatie/laravel-activitylog:"^5.0" \
  spatie/laravel-translatable:"^6.14" \
  spatie/laravel-data:"^4.22" \
  spatie/laravel-typescript-transformer:"^3.0" \
  tightenco/ziggy:"^2.6"

# Dev deps
docker compose exec web composer require --dev \
  pestphp/pest:"^4.7" \
  pestphp/pest-plugin-laravel:"^4.0" \
  larastan/larastan:"^3.9" \
  laravel/pint:"^1.29"

# Filament panel scaffolding
docker compose exec web php artisan filament:install --panels

# spatie publish-and-migrate
docker compose exec web php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
docker compose exec web php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
docker compose exec web php artisan vendor:publish --tag=typescript-transformer-config
docker compose exec web php artisan migrate
```

**Frontend (pnpm, at `apps/web/`):**

```bash
pnpm --filter web add @inertiajs/vue3@^2 vue@^3.5 \
  @vitejs/plugin-vue laravel-vite-plugin \
  tailwindcss@^4 @tailwindcss/vite \
  tailwindcss-v3@npm:tailwindcss@^3.4 \
  ziggy-js@^2.6 \
  laravel-vue-i18n reka-ui lucide-vue-next vue-sonner \
  '@fontsource-variable/inter' '@fontsource-variable/jetbrains-mono'
```

(Adjust workspace filter syntax to match the chosen pnpm setup; `apps/web` package name TBD.)

**Version verification (run before locking versions in plan):**
```bash
composer show -a laravel/framework | head
composer show -a filament/filament | grep -E '^version' | head -5
npm view @inertiajs/vue3 versions --json | tail -20
npm view tailwindcss version
```
Verified versions in this research: laravel/framework 12.x line is current and tracks PHP 8.4; spatie/laravel-permission 7.4.1 (2026-04-29); spatie/laravel-data 4.22.1 (2026-04-27); spatie/laravel-translatable 6.14.1 (2026-04-23); spatie/laravel-activitylog 5.0.0 (2026-03-25); laravel/socialite 5.27.0 (2026-04-24); socialiteproviders/discord 4.2.0 (2023-07-24, no breakage in Laravel 12); inertiajs/inertia-laravel v3.0.6 IS the latest but D-001 locks v2; we install `inertiajs/inertia-laravel:^2.0`. [VERIFIED: Packagist 2026-05-03]

## Architecture Patterns

### System Architecture Diagram

```
                    ┌────────────────────────────────────────┐
                    │ Discord OAuth                          │
                    │ (https://discord.com/api/oauth2/...)   │
                    └────────┬──────────────────▲────────────┘
                             │ 302 redirect      │ token exchange
                             │ (browser follows) │ (server side)
                             ▼                   │
┌──────────┐   1. GET /     ┌──────────────────────────────┐
│ Browser  │ ────────────── │ apps/web (Laravel 12)        │
│  (Vue 3) │                │  ├─ /                        │ ← Inertia page
│ Inertia  │ 2. Click "Log  │  ├─ /auth/discord/redirect   │ ← Socialite::driver('discord')->redirect()
│   v2     │     in"        │  ├─ /auth/discord/callback   │ ← Socialite::driver('discord')->user()
└────▲─────┘ ────────────── │  │       │                   │
     │                      │  │       └─→ DB::transaction │
     │ 3. Inertia visit      │  │            ├─ users      │ ← upsert by discord_id
     │    response (JSON +   │  │            ├─ players    │ ← create with FK to users
     │    SSR HTML)          │  │            └─ player_priv│ ← create with defaults
     │                      │  │                           │
     │                      │  ├─ /admin (Filament)        │ ← gated by canAccessPanel + admin-access permission
     │                      │  │   ├─ User Resource        │
     │                      │  │   ├─ Player Resource      │
     │                      │  │   ├─ Role Resource        │
     │                      │  │   ├─ Permission Resource  │
     │                      │  │   └─ /admin/audit (custom)│
     │                      │  │                           │
     │                      │  └─ HandleInertiaRequests    │
     │                      │      shares: auth, locale,   │
     │                      │      translations, ziggy     │
     │                      └────────────┬─────────────────┘
     │                                   │
     │                          ┌────────┼────────┐
     │                          ▼        ▼        ▼
     │                    Postgres   Redis   activity_log
     │                    16+ext.    7      table (spatie)
     │                                       
     │
     │ ←──── Inertia shared props on every visit ────────────
     │       ├─ translations: { 'auth.discord.button_label': 'Log in with Discord', ... }
     │       ├─ locale:       'en'
     │       ├─ auth.user:    { id, discord_id, username, ... } | null
     │       └─ ziggy:        { url, port, defaults, routes: { ... } }
     ▼
   Vue 3 + t() helper resolves keys → renders i18n-aware UI
```

**Build-time pipeline (separate from runtime above):**

```
spatie/laravel-data DTOs (PHP)
       │
       │ php artisan typescript:transform
       ▼
apps/web/resources/js/types/api.d.ts   ←── frontend imports as @/types/api
       │
       │ (separate copy step or symlink)
       ▼
packages/shared-types/src/api.d.ts     ←── apps/bot, apps/rcon-worker import
```

### Recommended Project Structure

```
trenchwars/                                  # repo root, pnpm workspace
├── docker-compose.yml                       # 5 services + postgres 16 + redis 7 (D-021)
├── .env.example                             # documents shape only — never committed real secrets
├── pnpm-workspace.yaml                      # packages: apps/*, packages/*
├── package.json                             # root scripts (build, dev, test) — orchestrates per-app
├── tsconfig.base.json                       # shared TS config
├── Makefile                                 # short aliases: make up, make artisan, make composer
├── railway.json | per-service railway.toml  # Railway service config (root-dir per service)
├── .github/
│   └── workflows/
│       ├── web.yml                          # Pest + PHPStan L8 + Pint, path filter apps/web/**
│       ├── bot.yml                          # tsc + vitest + eslint, path filter apps/bot/**
│       ├── rcon-worker.yml                  # tsc + vitest + eslint, path filter apps/rcon-worker/**
│       └── shared-types.yml                 # tsc only, path filter packages/shared-types/**
├── apps/
│   ├── web/                                 # Laravel 12 application
│   │   ├── composer.json
│   │   ├── package.json                     # Vite + Vue + Inertia + Tailwind v4 + tailwindcss-v3 alias
│   │   ├── vite.config.ts                   # MAIN: Tailwind v4 (public site SSR + Inertia)
│   │   ├── vite.filament.config.ts          # SECONDARY: Tailwind v3 (Filament theme only)
│   │   ├── postcss.config.js                # FILAMENT-ONLY (or remove if using Tailwind v3 via Vite)
│   │   ├── nixpacks.toml | Dockerfile       # Railway build directive (nixpacks first; Dockerfile fallback)
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/Auth/DiscordController.php
│   │   │   │   └── Middleware/HandleInertiaRequests.php
│   │   │   ├── Listeners/ProvisionFirstLogin.php
│   │   │   ├── Models/{User,Player,PlayerPrivacy}.php   # only the P1 subset
│   │   │   ├── Providers/Filament/AdminPanelProvider.php
│   │   │   └── Filament/
│   │   │       ├── Resources/{User,Player,Role,Permission}Resource.php
│   │   │       └── Pages/Audit.php
│   │   ├── bootstrap/
│   │   │   └── app.php                      # middleware groups, Inertia, sanctum, etc.
│   │   ├── config/
│   │   │   ├── services.php                 # discord client_id/secret/redirect
│   │   │   ├── permission.php               # spatie config (published)
│   │   │   ├── activitylog.php              # spatie config
│   │   │   ├── translatable.php             # spatie config
│   │   │   └── i18n.php                     # custom: available_locales = ['en']
│   │   ├── database/
│   │   │   ├── migrations/
│   │   │   │   ├── 0000_00_00_000001_enable_postgres_extensions.php   # uuid-ossp, citext
│   │   │   │   ├── 0000_00_00_000002_create_users_table.php           # discord_id text unique, etc.
│   │   │   │   ├── ..._create_players_table.php
│   │   │   │   ├── ..._create_player_privacy_table.php
│   │   │   │   ├── ..._create_permission_tables.php                   # spatie
│   │   │   │   └── ..._create_activity_log_table.php                  # spatie
│   │   │   └── seeders/
│   │   │       ├── DatabaseSeeder.php
│   │   │       ├── PermissionSeeder.php                                # admin-access + role seeds
│   │   │       └── AuditDemoSeeder.php                                 # local/testing only
│   │   ├── lang/en/
│   │   │   ├── auth.php
│   │   │   ├── common.php
│   │   │   ├── validation.php
│   │   │   └── admin.php
│   │   ├── resources/
│   │   │   ├── css/
│   │   │   │   ├── app.css                  # Tailwind v4 @import + @theme + @custom-variant dark
│   │   │   │   └── filament/admin/theme.css # Tailwind v3 (separate Vite build target)
│   │   │   ├── js/
│   │   │   │   ├── app.ts                   # createInertiaApp + ZiggyVue + i18nVue
│   │   │   │   ├── ssr.ts                   # createServer + renderToString
│   │   │   │   ├── pages/
│   │   │   │   │   └── Home.vue
│   │   │   │   ├── layouts/
│   │   │   │   │   └── PublicLayout.vue
│   │   │   │   ├── components/ui/           # Reka UI wrappers (Button, IconButton, etc.)
│   │   │   │   ├── composables/
│   │   │   │   │   ├── useTheme.ts
│   │   │   │   │   └── useLocale.ts
│   │   │   │   └── types/
│   │   │   │       └── api.d.ts             # generated by spatie/laravel-data
│   │   │   └── views/
│   │   │       └── app.blade.php            # @vite + @inertia + @inertiaHead + <html lang>
│   │   ├── routes/
│   │   │   ├── web.php                      # /, /auth/discord/redirect, /auth/discord/callback
│   │   │   └── auth.php                     # if split out
│   │   └── tests/
│   │       ├── Pest.php
│   │       ├── Feature/
│   │       │   ├── AppBootTest.php          # smoke: GET / returns 200
│   │       │   ├── DiscordLoginTest.php     # Socialite::fake() integration test
│   │       │   └── FirstLoginProvisioningTest.php
│   │       └── Unit/
│   ├── bot/                                  # P1: skeleton only
│   │   ├── package.json                     # discord.js v14 (Phase 5 implements)
│   │   ├── tsconfig.json
│   │   ├── Dockerfile                       # node:22-alpine
│   │   └── src/index.ts                     # placeholder ("not implemented in P1")
│   └── rcon-worker/                          # P1: skeleton only
│       ├── package.json
│       ├── tsconfig.json
│       ├── Dockerfile                       # node:22-alpine
│       └── src/index.ts                     # placeholder
└── packages/
    └── shared-types/
        ├── package.json
        ├── tsconfig.json
        └── src/
            └── index.ts                     # re-exports apps/web/resources/js/types/api.d.ts
```

### Pattern 1: Discord OAuth Socialite Integration

**What:** Wire Discord as a Socialite provider via the socialiteproviders/discord package, then a two-route handshake (redirect + callback).

**When to use:** Discord is the only auth method (D-002).

**Steps:**
1. Add Discord credentials to `config/services.php`:
   ```php
   // Source: socialiteproviders.com/Discord/
   'discord' => [
       'client_id'     => env('DISCORD_CLIENT_ID'),
       'client_secret' => env('DISCORD_CLIENT_SECRET'),
       'redirect'      => env('DISCORD_REDIRECT_URI'),
   ],
   ```
2. Register the provider in `app/Providers/AppServiceProvider.php` `boot()` (Laravel 11+ has no EventServiceProvider by default — register via Event facade):
   ```php
   // Source: socialiteproviders.com/usage/
   use Illuminate\Support\Facades\Event;
   use SocialiteProviders\Manager\SocialiteWasCalled;

   public function boot(): void
   {
       Event::listen(function (SocialiteWasCalled $event) {
           $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
       });
   }
   ```
3. Routes (`routes/web.php`):
   ```php
   // Source: laravel.com/docs/12.x/socialite (Context7 verified)
   use Laravel\Socialite\Facades\Socialite;

   Route::get('/auth/discord/redirect', function () {
       return Socialite::driver('discord')
           ->scopes(['identify', 'email'])
           ->redirect();
   })->name('auth.discord.redirect');

   Route::get('/auth/discord/callback', [DiscordController::class, 'callback'])
       ->name('auth.discord.callback');
   ```
4. Callback controller wraps everything in a transaction (idempotent):
   ```php
   // First-login provisioning per CONTEXT.md
   public function callback(): RedirectResponse
   {
       $discordUser = Socialite::driver('discord')->user();

       $user = DB::transaction(function () use ($discordUser) {
           $user = User::updateOrCreate(
               ['discord_id' => $discordUser->getId()],
               [
                   'username'    => $discordUser->getNickname() ?: $discordUser->getName(),
                   'email'       => $discordUser->getEmail(),
                   'avatar_url'  => $discordUser->getAvatar(),
                   'locale'      => $discordUser->user['locale'] ?? 'en',
                   'last_login_at' => now(),
               ]
           );

           if (! $user->player) {
               $player = $user->player()->create([
                   'slug'         => Str::slug($user->username) . '-' . Str::lower(Str::random(4)),
                   'display_name' => null,
               ]);
               $player->privacy()->create([
                   'show_to'             => 'community',
                   'show_real_name'      => false,   // default false (sensitive)
                   'show_discord_tag'    => true,
                   'show_clan_history'   => true,
                   'show_match_history'  => true,
                   'show_stats'          => true,
               ]);
           }

           return $user;
       });

       Auth::login($user, remember: true);
       return redirect('/')->with('success', __('auth.discord.success', ['name' => $user->username]));
   }
   ```
5. Test with `Socialite::fake()` per Laravel 12 docs (Context7-verified pattern).

### Pattern 2: Inertia v2 + Vue 3 + Vite + Tailwind v4 setup

**What:** Wire Inertia v2 as the controller-to-view bridge with Vue 3 SSR-ready and Tailwind v4 CSS-first.

**When to use:** All public site pages (`/`, future `/clans/*`, `/players/*`, etc.).

**Files:**

`apps/web/resources/css/app.css` (Tailwind v4 CSS-first, source: tailwindcss.com/docs/installation/framework-guides/laravel/vite):
```css
@import "tailwindcss";

@source "../views/**/*.blade.php";
@source "../js/**/*.{ts,vue}";
@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php";
@source "../../storage/framework/views/*.php";

@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *));

@theme {
  --font-sans: "Inter Variable", "Inter", ui-sans-serif, system-ui, sans-serif;
  --font-mono: "JetBrains Mono Variable", "JetBrains Mono", ui-monospace, monospace;
  /* per UI-SPEC.md token block */
}

:root, [data-theme=dark] {
  --color-bg: #1A1B16; /* etc. — full token set in UI-SPEC.md */
}
[data-theme=light] {
  --color-bg: #F5F2E6;
}
```

`apps/web/vite.config.ts` (main bundle, Tailwind v4):
```typescript
// Source: tailwindcss.com/docs/installation/framework-guides/laravel/vite
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts'],
      ssr:   'resources/js/ssr.ts',
      refresh: true,
    }),
    vue({
      template: { transformAssetUrls: { base: null, includeAbsolute: false } },
    }),
    tailwindcss(),
  ],
});
```

`apps/web/vite.filament.config.ts` (Filament theme only, Tailwind v3):
```typescript
// Source: filamentthemes.com/guides/how-to-use-filament-with-tailwind-css-v4
// + nathangross.me/blog/using-filament-v3-with-tailwind-css-v4-a-pnpm-workspaces-approach
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/filament/admin/theme.css'],
      buildDirectory: 'build/filament',  // separate output
      refresh: true,
    }),
  ],
  css: {
    postcss: {
      plugins: [
        require('tailwindcss-v3'),         // npm-aliased Tailwind v3
        require('autoprefixer'),
      ],
    },
  },
});
```

`apps/web/resources/js/app.ts` (Inertia v2 + Vue 3 + ZiggyVue + i18nVue):
```typescript
// Source: inertiajs.com/docs/v2/installation/client-side-setup (Context7-verified)
import './bootstrap';
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { i18nVue } from 'laravel-vue-i18n';

createInertiaApp({
  resolve: (name) =>
    resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    app.use(plugin);
    app.use(ZiggyVue);
    app.use(i18nVue, {
      lang: props.initialPage.props.locale ?? 'en',
      resolve: (lang: string) => {
        const langs = import.meta.glob('../../lang/*.json', { eager: true });
        return langs[`../../lang/${lang}.json`] as object;
      },
    });
    app.mount(el);
  },
  progress: { color: '#A4262C' },
});
```

(Note: `laravel-vue-i18n` reads JSON files; we'll generate them from `lang/en/*.php` via the package's bundled `php-translations` Vite plugin OR a pre-build script. Per CONTEXT.md decision "PHP arrays only", we author PHP and the plugin compiles to JSON for the bundle.)

### Pattern 3: Filament v3 panel with spatie/laravel-permission gate

**What:** Mount Filament at `/admin` and gate with the `admin-access` permission via `canAccessPanel`.

**When to use:** Single-panel admin (P1 only has the admin panel).

**Files:**

`app/Models/User.php` (HasRoles trait + FilamentUser contract):
```php
// Source: spatie.be/docs/laravel-permission/v6 + filamentphp.com/docs/3.x/users
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements FilamentUser
{
    use HasUuids, HasRoles, LogsActivity;

    protected $fillable = ['discord_id', 'username', 'email', 'avatar_url', 'locale', 'last_login_at'];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasPermissionTo('admin-access');
        }
        return false;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['last_login_at']);  // suppress login spam
    }

    public function player(): HasOne { return $this->hasOne(Player::class); }
}
```

`app/Providers/Filament/AdminPanelProvider.php`:
```php
// Source: filamentphp.com/docs/3.x/panels (Context7-verified)
use Filament\Panel;
use Filament\Support\Colors\Color;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->login()                           // Filament's built-in login — but we override the entry point
        ->brandName('Trenchwars')
        ->colors(['primary' => Color::hex('#A4262C')])
        ->darkMode()                        // dark default per UI-SPEC.md
        ->viteTheme('resources/css/filament/admin/theme.css', 'build/filament')  // dual-Tailwind setup
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
        ->pages([
            \App\Filament\Pages\Audit::class,
        ])
        ->resources([
            \App\Filament\Resources\UserResource::class,
            \App\Filament\Resources\PlayerResource::class,
            \App\Filament\Resources\RoleResource::class,
            \App\Filament\Resources\PermissionResource::class,
        ])
        ->authMiddleware(['web', 'auth']);
}
```

(Note: Filament's built-in `->login()` provides email/password — for our Discord-only flow, we either drop `->login()` and force users to `/auth/discord/redirect`, or override Filament's login page to immediately redirect to Discord OAuth. Either is reasonable; recommend dropping `->login()` and adding a top-level redirect.)

### Pattern 4: Audit log via spatie/laravel-activitylog

**What:** LogsActivity trait emits `created/updated/deleted` events; Filament shows them per-resource and on a global page.

**When to use:** Every user-facing entity (P1: User, Player only).

```php
// Source: spatie.be/docs/laravel-activitylog
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Player extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event) => "Player {$event}");
    }
}
```

Global `/admin/audit` page extends `Filament\Pages\Page` with a `Filament\Tables\Table` over `Activity::query()`.

### Pattern 5: i18n end-to-end via Inertia shared props

**What:** PHP `lang/en/*.php` arrays are the canonical source; Inertia shares them as a `translations` prop; Vue helper resolves on the client.

`app/Http/Middleware/HandleInertiaRequests.php`:
```php
// Source: laravel.com/docs/13.x/authorization (Context7-verified pattern)
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn () => $request->user()?->only(['id', 'discord_id', 'username', 'avatar_url']),
            'locale'       => app()->getLocale(),
            'translations' => fn () => $this->translations(app()->getLocale()),
            'flash'        => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'ziggy'        => fn () => [
                ...(new \Tighten\Ziggy\Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ]);
    }

    protected function translations(string $locale): array
    {
        return collect(['auth', 'common', 'validation', 'admin'])
            ->mapWithKeys(fn ($namespace) => [$namespace => __($namespace, [], $locale)])
            ->all();
    }
}
```

Vue `t()` composable (or via `laravel-vue-i18n` plugin):
```typescript
// resources/js/composables/useT.ts
import { trans } from 'laravel-vue-i18n';
export const t = trans;
```

### Anti-Patterns to Avoid

- **Configuring Tailwind via `tailwind.config.js`** — Tailwind v4 CSS-first means tokens live in `@theme { }`, NOT in JS config. UI-SPEC.md is explicit. [VERIFIED: tailwindcss.com/docs]
- **Loading PostCSS plugin in `postcss.config.js` for the main bundle** — Tailwind v4 uses `@tailwindcss/vite`, not `@tailwindcss/postcss`. PostCSS is only used for the Filament v3 theme (`vite.filament.config.ts`).
- **Filling out `tailwind.config.js` content/globs** — replaced by `@source` directives inside `app.css`.
- **Using Filament's built-in login form** — D-017 forbids hand-rolled email/password. Either remove `->login()` from AdminPanelProvider or override Filament's login page to redirect to Discord.
- **Putting business logic in the Discord callback controller** — wrap everything in a Login event listener (`ProvisionFirstLogin`) for testability.
- **Manually writing TypeScript types for API DTOs** — D-020 forbids it. Generate via `php artisan typescript:transform`.
- **Hardcoding Discord Snowflake IDs as bigint** — discord_id is a 64-bit unsigned integer that overflows JS Number; store as `text` and treat as opaque. Per `05-database-schema.md` line `discord_id text UNIQUE NOT NULL`. [VERIFIED: 05-database-schema.md]
- **Using Postgres `bigint` for primary keys** — schema says `uuid PK` everywhere with `gen_random_uuid()` default. Use Laravel's `HasUuids` trait + `$keyType = 'string'`.
- **Letting Vite emit `app.js` to `public/build/` AND Filament theme to `public/build/`** — they'll fight. The Filament Vite config MUST emit to a separate `buildDirectory` (e.g., `build/filament`) and the panel provider points there.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth flow with Discord | Custom HTTP client + token storage | `laravel/socialite` + `socialiteproviders/discord` | OAuth state/CSRF/PKCE/token refresh edge cases — all handled |
| Role + permission RBAC | Custom users_roles/permissions tables + queries | `spatie/laravel-permission` v7 | Cache invalidation, multi-guard, model scoping all solved |
| Audit log | Hand-rolled "history" tables and observers | `spatie/laravel-activitylog` v5 | Causer resolution + batch UUIDs + JSON property diffs |
| Translatable JSONB columns | Custom getter/setter accessors | `spatie/laravel-translatable` v6 | Locale fallback chain + Eloquent integration |
| TypeScript types from PHP | Hand-typed `interface User { ... }` files | `spatie/laravel-data` + `spatie/laravel-typescript-transformer` | Drift between server/client types is a constant source of bugs |
| Admin CRUD UI | Custom Vue admin with forms + tables | `filament/filament` v3 | Months of work. D-001 + D-012 LOCKED |
| Named-route `/clans/{slug}` URLs in Vue | Hand-built URL helpers | `tightenco/ziggy` (server PHP) + `ziggy-js` (Vue plugin) | Single source of truth for routes |
| First-paint SSR | Hand-rolled SSR pipeline | Inertia SSR via `inertia:start-ssr` Node entry | Inertia v2 ships SSR with `createServer` + `renderToString` |
| Docker dev env scaffolding | Sail or hand-rolled mash of Dockerfiles | Hand-rolled per D-021 (Sail's defaults add Mailpit/Meilisearch we don't want) | Trade-off accepted: hand-roll a minimal compose |
| GitHub Actions PHP setup | Hand-installing PHP in workflow | `shivammathur/setup-php@v2` | Maintained, supports PHP 8.4, includes intl/curl/etc. extensions |
| Pest browser tests harness | Custom Selenium/Puppeteer rig | `pestphp/pest-plugin-browser` (Pest v4) | First-class Laravel integration; deferred unless Phase 1 needs |
| Discord redirect_uri management | Hardcoding into config | `env('DISCORD_REDIRECT_URI')` + Railway env group | Per-env redirect URIs; never check secrets into git |
| HTTP request CSRF | Hand-managing X-XSRF-TOKEN | Inertia's built-in XSRF cookie handling | Inertia reads `XSRF-TOKEN` cookie automatically; do NOT add `<meta name="csrf-token">` |

**Key insight:** Phase 1 is a glue phase — every concern is solved by a battle-tested library. The risk is integration friction (Tailwind v4 vs Filament v3, Inertia + CSRF, Discord redirect_uri exact match), NOT code design. Plans should optimize for shipping known-good integrations and adding tests that catch the integration mistakes early.

## Common Pitfalls

### Pitfall 1: Tailwind v4 + Filament v3 — incompatible without dual-Vite-config workaround [HIGH SEVERITY — LOCKED-DECISION CONFLICT]

**What goes wrong:** Filament v3 ships theme.css that uses Tailwind v3 syntax (`@layer`, `@apply` with v3 tokens, no `@theme`). Installing only Tailwind v4 produces broken Filament theme builds.

**Why it happens:** Filament v3 was released before Tailwind v4 stabilized; v4 support arrives in Filament v4.

**How to avoid:**
- Install BOTH `tailwindcss@^4` (main) AND `tailwindcss-v3@npm:tailwindcss@^3.4` (npm alias).
- Two Vite configs: `vite.config.ts` (Tailwind v4 for public site) + `vite.filament.config.ts` (Tailwind v3 via PostCSS for the Filament theme bundle).
- Update `package.json` build scripts to run BOTH: `"build": "vite build && vite build --config vite.filament.config.ts"`.
- AdminPanelProvider points at the Filament-specific build directory: `->viteTheme('resources/css/filament/admin/theme.css', 'build/filament')`.

**Warning signs:**
- Filament admin panel CSS partially broken / classes missing
- Build error: `Cannot resolve @tailwindcss/forms` (Filament v3 uses this plugin in its theme; v4 ecosystem doesn't)
- Filament v3.3.34+ specifically reported breakage (#18451) — pin to a known-good v3 release like 3.3.50

[VERIFIED: filamentphp.com/docs/3.x/panels/themes; filamentthemes.com; nathangross.me]

### Pitfall 2: Discord OAuth `redirect_uri` mismatch [HIGH SEVERITY]

**What goes wrong:** Discord rejects token exchange with `redirect_uri_mismatch` error.

**Why it happens:**
- Trailing slash mismatch (`http://localhost:8000/auth/discord/callback` vs `http://localhost:8000/auth/discord/callback/`)
- HTTP vs HTTPS mismatch
- Port mismatch when running behind a proxy or non-standard port
- Multiple redirect URIs registered in Discord but the env var doesn't exactly match one of them
- `redirectUrl()` method on Socialite driver doesn't always update token exchange (issue #436)

**How to avoid:**
- Document EXACT URLs in `.env.example`:
  - Local: `DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback`
  - Prod: `DISCORD_REDIRECT_URI=https://trenchwars.example/auth/discord/callback` (no trailing slash)
- Register BOTH on the Discord Developer Portal (https://discord.com/developers/applications → OAuth2 → Redirects).
- Pest test asserts the redirect URL contains the expected `redirect_uri` query param.

**Warning signs:**
- 400 error from Discord on callback
- Discord error page: "Invalid OAuth2 redirect_uri"

[VERIFIED: laravel.io/forum (multiple threads); github.com/laravel/socialite/issues/436]

### Pitfall 3: Inertia + CSRF — 419 errors after redirect [MEDIUM SEVERITY]

**What goes wrong:** Inertia POSTs return 419 Page Expired or fail silently after a Discord OAuth redirect.

**Why it happens:**
- `<meta name="csrf-token">` tag in Blade root template — interferes with Inertia's automatic XSRF cookie handling
- Session cookie `SameSite=None` set without `Secure=true` — browsers reject on HTTP localhost
- Wrong `SESSION_DOMAIN` env var (e.g. `.localhost` doesn't work in Chrome)
- App served on a non-default port — XSRF-TOKEN cookie scope misaligned (#1556)

**How to avoid:**
- DO NOT include `<meta name="csrf-token">` in `app.blade.php` — Inertia doesn't need it
- Use `SameSite=Lax` for dev (works on HTTP localhost) and prod
- Set `SESSION_SECURE_COOKIE=true` only in production
- Leave `SESSION_DOMAIN=null` for local dev
- Verify `HandleInertiaRequests` is in the `web` middleware group (it should be; default registration via `bootstrap/app.php` `->withMiddleware()` adds it automatically when `inertiajs/inertia-laravel` is installed)

**Warning signs:**
- 419 Page Expired on first POST after login
- Inertia visits silently fail with no error in console
- Form submissions return 302 redirects to login when user IS logged in

[VERIFIED: inertiajs.com/docs/v2/security/csrf-protection; multiple Laracasts/issue threads]

### Pitfall 4: Filament v3 + spatie/laravel-permission — guard mismatch [MEDIUM SEVERITY]

**What goes wrong:** `$user->hasPermissionTo('admin-access')` returns false even though the user has the permission, because the guard doesn't match.

**Why it happens:** spatie/laravel-permission scopes permissions to a guard. Filament uses the panel's auth guard (default: `web`). If `config/permission.php` `default_guard` is `api` or `web`, mismatch → silent fail.

**How to avoid:**
- Set `config/permission.php` `default_guard` to `web` (or whatever Filament uses)
- When seeding permissions, explicitly set the guard: `Permission::create(['name' => 'admin-access', 'guard_name' => 'web']);`
- In `canAccessPanel()`, use `$this->hasPermissionTo('admin-access', filament()->getAuthGuard())` to be safe

**Warning signs:**
- 403 Forbidden on `/admin` even after assigning the permission via tinker
- `hasPermissionTo` returns true in tinker but false in HTTP context (this is the symptom)

[VERIFIED: filamentmastery.com/articles/handle-authorization-in-filament-policies-roles-guards]

### Pitfall 5: Postgres extensions not enabled [MEDIUM SEVERITY]

**What goes wrong:** Migration fails with `ERROR: function gen_random_uuid() does not exist` or `ERROR: type "citext" does not exist`.

**Why it happens:** Postgres extensions are per-database, not per-cluster. The `postgres:16` Docker image creates a database but doesn't auto-enable extensions.

**How to avoid:** First migration enables required extensions:
```php
public function up(): void
{
    DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
    DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto";');  // alternative gen_random_uuid source
    DB::statement('CREATE EXTENSION IF NOT EXISTS citext;');
}
```
On Railway, the Postgres plugin allows `CREATE EXTENSION` for standard ones (uuid-ossp, citext, pgcrypto) — verified by Railway docs.

**Note on UUIDv7:** Postgres 16 does NOT have a built-in `gen_random_uuid()` returning v7 — it's still v4. Use `Str::uuid7()` in app code (Laravel 11.30+) or `symfony/uid` for client-generated v7. The schema doc plans to switch to Postgres 17's UUIDv7 later.

[VERIFIED: docker-library/postgres issue #1045; Laravel 12 docs Str::uuid7]

### Pitfall 6: Railway monorepo build path confusion [MEDIUM SEVERITY]

**What goes wrong:** Railway tries to build the entire repo for every service, or fails to find `composer.json` in the root.

**Why it happens:** Railway's "Root Directory" setting per service tells the builder which subdirectory contains the app. This is set via the dashboard OR `railway.toml` — but **`railway.toml` paths are absolute and do NOT follow the Root Directory setting**.

**How to avoid:**
- Per-service Root Directory set in Railway dashboard:
  - `web` service: `apps/web`
  - `worker` service: `apps/web` (same image, different start command)
  - `bot` service: `apps/bot`
  - `rcon-worker` service: `apps/rcon-worker`
- For Laravel-specific Nixpacks support: set `NIXPACKS_PHP_ROOT_DIR=/app/public` in service env (so Caddy serves from `apps/web/public/`).
- Alternatively: include a `railway.json` in EACH `apps/*` subdirectory and set the absolute `dockerfilePath` if using a Dockerfile. Don't try to use a single root `railway.toml` for multi-service deploys.
- For pnpm-monorepo Node services (bot, rcon-worker): set `NIXPACKS_NODE_VERSION=22` and use `pnpm install --filter <pkg>` in the build command if pnpm workspaces require pruning.

**Warning signs:**
- Railway build logs show "no PHP detected" for the `web` service (means the Root Directory was not set correctly)
- bot service builds entire monorepo and tries to install Laravel — wrong Root Directory

[VERIFIED: docs.railway.com/guides/monorepo; docs.railway.com/builds/build-configuration]

### Pitfall 7: spatie/laravel-activitylog v5 — breaking changes from v4 [LOW-MEDIUM SEVERITY]

**What goes wrong:** Tutorials online may reference v4 API; v5 (released 2026-03-25) bumped PHP requirement to ^8.4 and changed some defaults.

**How to avoid:**
- Reference Context7 docs for v5 specifically (or the GitHub README at github.com/spatie/laravel-activitylog).
- The `LogsActivity` trait + `getActivitylogOptions()` method API is unchanged.
- Migration table name (`activity_log`) and columns are unchanged.
- BUT: the Filament integration plugins (e.g., `pxlrbt/filament-activity-log`, `rmsramos/activitylog`) may still reference v4 and need verification before adoption. Recommend: skip Filament-specific activitylog plugins in P1 and roll our own `/admin/audit` page using a vanilla `Filament\Tables\Table`.

[VERIFIED: Packagist 5.0.0 release 2026-03-25; PHP requirement ^8.4 confirmed]

### Pitfall 8: laravel-vue-i18n + Inertia SSR — JSON resolve at build vs runtime [LOW SEVERITY]

**What goes wrong:** SSR build fails because `import.meta.glob('../../lang/*.json')` returns Promises (async) but SSR `createServer` expects sync resolution.

**How to avoid:** In `ssr.ts`, use `eager: true` form:
```typescript
const langs = import.meta.glob('../../lang/*.json', { eager: true });
return langs[`../../lang/${lang}.json`];  // already-resolved object
```
Per laravel-vue-i18n README explicit SSR notes.

**Warning signs:**
- `php artisan inertia:start-ssr` exits immediately with "translations is not defined"
- SSR build emits no JS for the lang files

[VERIFIED: github.com/xiCO2k/laravel-vue-i18n README SSR section]

### Pitfall 9: PHP 8.4 deprecation noise from Filament v3 [LOW SEVERITY]

**What goes wrong:** PHP 8.4 deprecated implicit nullable parameters (`function foo(string $x = null)` must now be `function foo(?string $x = null)`). Filament v3 was authored in PHP 8.2 era and may emit deprecation warnings.

**How to avoid:**
- Pin to Filament v3.3.50 (latest v3) — the team has been backporting fixes
- Suppress deprecation log noise in production: `LOG_DEPRECATIONS_CHANNEL=null` in `.env`
- Add to PHPStan baseline if Larastan flags Filament-internal issues

**Warning signs:**
- `composer install` shows Filament dependency satisfied warnings
- Deprecation warnings in `storage/logs/laravel.log` from `vendor/filament/`

[VERIFIED: github.com/filamentphp/filament/discussions/15656]

## Runtime State Inventory

> Phase 1 is a greenfield phase — no rename/refactor. This section is omitted as not applicable.
>
> **Verified:** No prior code, no prior data, no prior services. The repo contains only `.docs/`, `.planning/`, `.git/`, `.idea/`. Confirmed via `ls -la /home/rtx/projects/trench-wars/`. There is no runtime state to migrate.

## Code Examples

Verified patterns from official sources (full code in Architecture Patterns section above):

### Discord OAuth callback with first-login provisioning

See Pattern 1 above. [Source: laravel.com/docs/12.x/socialite (Context7); socialiteproviders.com/Discord/]

### Inertia v2 + Vue 3 createInertiaApp setup

See Pattern 2 above. [Source: inertiajs.com/docs/v2/installation/client-side-setup (Context7)]

### Tailwind v4 CSS-first @theme + @custom-variant dark

```css
/* Source: tailwindcss.com/docs (Context7-verified) + UI-SPEC.md */
@import "tailwindcss";

@source "../views/**/*.blade.php";
@source "../js/**/*.{ts,vue}";

@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *));

@theme {
  --font-sans: "Inter Variable", "Inter", ui-sans-serif, system-ui, sans-serif;
  --color-bg: #1A1B16;
  /* ... */
}
```

### Spatie permission seeding

```php
// Source: spatie.be/docs/laravel-permission/v6/basic-usage/new-app
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

Permission::create(['name' => 'admin-access', 'guard_name' => 'web']);
Permission::create(['name' => 'audit.view',   'guard_name' => 'web']);

$super = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
$super->givePermissionTo(Permission::all());
```

### Custom artisan command to create first admin

```php
// app/Console/Commands/MakeAdmin.php
class MakeAdmin extends Command
{
    protected $signature = 'trenchwars:make-admin {discord_id}';
    public function handle(): int
    {
        $user = User::where('discord_id', $this->argument('discord_id'))->firstOrFail();
        $user->givePermissionTo('admin-access');
        $user->assignRole('super-admin');
        $this->info("Admin granted to {$user->username}");
        return self::SUCCESS;
    }
}
```

### Spatie/laravel-data DTO with TypeScript export

```php
// Source: spatie.be/docs/laravel-data/v4/advanced-usage/typescript (Context7)
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $discord_id,
        public string $username,
        public ?string $email,
        public ?string $avatar_url,
        public string $locale,
    ) {}
}
```
Then `php artisan typescript:transform` emits to `resources/js/types/api.d.ts`.

## State of the Art

| Old Approach | Current Approach (2026-05) | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Tailwind v3 with `tailwind.config.js` | Tailwind v4 CSS-first via `@theme` + `@custom-variant` + `@source` | Tailwind v4.0 (Jan 2025) | LOCKED into v4 by UI-SPEC.md; conflicts with Filament v3 (which is also locked) — workaround documented |
| Inertia v1 protocol | Inertia v2 protocol (and now v3 in 2026) | v2 in 2024; v3 became default in 2026 | D-001 says v2 — pin `@inertiajs/vue3@^2.0` (legacy dist-tag) |
| Filament v3 | Filament v4 (GA), v5 (latest) | v4 GA late 2025; v5 GA early 2026 | LOCKED v3 — note for future review |
| Laravel 11 EventServiceProvider | Laravel 11+ uses `Event::listen` in AppServiceProvider | Laravel 11 (Mar 2024) | Affects how socialiteproviders/discord is registered — use Event facade in `boot()` |
| `@inertiajs/vue3@^1` (Vue 2 era) | `@inertiajs/vue3@^2` (Vue 3 only — v2 dropped Vue 2) | Inertia v2 release | Vue 2 EOL Dec 2023; not relevant to greenfield |
| Pest v3 | Pest v4 (with browser plugin) | Pest v4 (2025) | Use Pest v4; browser tests deferred |
| spatie/laravel-activitylog v4 | v5 (PHP 8.4 required) | 2026-03-25 | We're on PHP 8.4 — fine |
| `tightenco/ziggy` | Same package, v2.x | — | Stable |
| Vite 5/6 | Vite 8 (npm latest) | 2026 | Pin Vite ^6 — Laravel 12 templates target v6, untested with v8 |

**Deprecated/outdated:**
- `@inertiajs/inertia-vue` (Vue 2): EOL — never install
- `@inertiajs/inertia` (the umbrella package): replaced by per-framework adapters
- `spatie/laravel-activitylog` v4 and earlier: superseded; v5 has PHP 8.4 floor matching ours

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | "Inertia v2" in D-001 means the v2 protocol (`@inertiajs/vue3@^2.0`), not the new v3 protocol | Standard Stack | If user actually meant "use the latest version called v2 in 2024 but I'm fine with v3 now", we're behind. Mitigation: surface in discuss-phase before locking the install command. |
| A2 | spatie/laravel-data v4 is the right major to install (not v3 — D-020 doesn't pin a major) | Standard Stack | v4 is current and supported on Laravel 12; no risk |
| A3 | Railway auto-detection of monorepo subdirectory works for Laravel apps using nixpacks with `NIXPACKS_PHP_ROOT_DIR=/app/public` | Architecture Patterns / Pitfall 6 | If nixpacks auto-detection breaks, fallback is per-service Dockerfile (well-documented). Low risk. |
| A4 | `socialiteproviders/discord` v4.2.0 (released 2023-07-24) still works correctly with Laravel 12 + PHP 8.4 + new Event::listen registration pattern | Standard Stack / Pattern 1 | If broken, alternatives are `martinbean/socialite-discord-provider` or hand-rolled provider class. Multiple production users on Laravel 11+ confirm working. Low risk. |
| A5 | The dual-Tailwind-version workaround (Tailwind v4 main + Tailwind v3 alias for Filament theme) is the correct path rather than downgrading the public site to Tailwind v3 | Pitfall 1 | Two community sources document this approach and the official Filament docs acknowledge it. Mitigation: this is the planner's most important task — verify the workaround end-to-end in a smoke test before declaring P1 done. |
| A6 | `laravel-vue-i18n` will support reading PHP `lang/*.php` arrays directly OR there's a build step that converts them to JSON — which is what CONTEXT.md mandates | Pattern 5 | The package has a Vite plugin that handles this. If it doesn't, fallback is a custom artisan command `php artisan i18n:export` that writes `resources/lang/{locale}.json`. Low risk. |
| A7 | `gen_random_uuid()` from `uuid-ossp` extension produces UUID v4 acceptable for our Eloquent `HasUuids` trait + `$keyType='string'` setup | Pitfall 5 | UUID v4 is what `HasUuids` produces by default. Switch to `Str::uuid7()` in Phase 2+ if ordering matters. No risk in P1. |
| A8 | The CONTEXT.md statement that "Frontend pipeline: ... Vite + ..." includes pinning Vite ^6 — we extrapolate from Laravel 12 default templates | Standard Stack | If Vite ^8 is preferred, only one config file changes. Low risk. |

**Note:** A1, A4, A5 are the high-leverage assumptions. The discuss-phase or planner should explicitly confirm A1 (Inertia version) and treat A5 (dual-Tailwind) as the highest-risk task in the plan.

## Open Questions

1. **Filament v3 vs upgrade to v4/v5 advisory.**
   - What we know: D-001 LOCKED to v3. Current is v5.6.2. Filament v4 supports Tailwind v4 natively (no dual-Vite workaround needed). v5 has a redesigned schema/forms API.
   - What's unclear: Does the user want a 30-second confirmation before committing the Tailwind workaround?
   - Recommendation: **Surface this in the discuss-phase as an advisory.** D-001 stands; don't override. But the user may want to know the cost they're paying. If they choose to upgrade, Filament v5 + Tailwind v4 = clean integration; the trade-off is more breaking changes from training-data resources written about v3.

2. **Inertia v2 vs v3 version interpretation.**
   - What we know: D-001 says "Inertia v2". `@inertiajs/vue3@^2.0` is the v2 protocol; npm `legacy` dist-tag now. v3.0.3 is `latest`.
   - What's unclear: Did D-001 mean "the protocol called v2 at the time of authoring" (now legacy) or "the major version always called v2"?
   - Recommendation: **Pin to `^2.0` per literal reading of D-001.** If user wants v3 protocol, they should add a new D-### that supersedes the locked stack version. Note in plan task that this version is the "legacy" dist-tag.

3. **`@admin/audit` Filament page implementation: own page vs plugin.**
   - What we know: Multiple Filament plugins exist for activitylog (`pxlrbt/filament-activity-log`, `rmsramos/activitylog`), but they may target Filament v4/v5.
   - What's unclear: Are they v3-compatible AND activitylog-v5-compatible?
   - Recommendation: **Build vanilla Filament page in P1.** A custom page with a `Filament\Tables\Table` over `Activity::query()` is ~30 lines of code and avoids dependency-chain risk. Re-evaluate plugins in Phase 2+.

4. **Filament built-in login page treatment.**
   - What we know: Filament v3 ships email/password login by default. We use Discord OAuth only (D-002, D-017).
   - What's unclear: Drop `->login()` from AdminPanelProvider entirely (forces 401 → user navigates to `/auth/discord/redirect`)? Or override the login page to redirect to Discord?
   - Recommendation: **Drop `->login()`** and add a top-level "Login required" redirect in Filament's auth middleware. Cleaner; no ambiguous secondary login surface.

5. **Discord Developer Portal application setup automation.**
   - What we know: User must create a Discord application at https://discord.com/developers/applications and copy CLIENT_ID + CLIENT_SECRET into env vars.
   - What's unclear: Is this a documented onboarding step, or do we expect the user to figure it out from `.env.example` comments?
   - Recommendation: **Add to README.md / docs as a numbered "First-time setup" section** with screenshots-equivalent prose for Discord application setup.

6. **`composer create-project` vs `laravel new` — both create a Laravel skeleton, but they differ slightly.**
   - What we know: CONTEXT.md says "composer create-project laravel/laravel apps/web *inside the web container*".
   - What's unclear: `laravel new` (the official installer) is preferred per Laravel docs but requires installing the laravel/installer globally first. Inside a fresh Docker container, that's two steps.
   - Recommendation: **Stick with `composer create-project --prefer-dist laravel/laravel . "^12.0"` per CONTEXT.md.** It works first-shot inside the container with no additional global tooling.

7. **CLAUDE.md authorship (will be created in Phase 1).**
   - Phase 1 should produce `./CLAUDE.md` documenting: container-only command pattern (`docker compose exec`), Pint + PHPStan L8 rules, Pest test conventions, file path conventions (everything inside `apps/web/`), security: never commit secrets, Discord OAuth flow expectations.
   - Recommendation: **Author CLAUDE.md as the LAST task in P1's "Repo Foundations" wave** so subsequent phases inherit the conventions.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Docker | All services (D-021) | ✓ | 29.3.0 | None — blocking; **D-021 ACTIVE BLOCKER per STATE.md**: Docker Desktop WSL integration must be enabled |
| Node.js | pnpm + bot/rcon-worker dev | ✓ | 22.22.2 | None |
| pnpm | Workspace root | ✗ | — | Install via `npm install -g pnpm@9` (or use `corepack enable && corepack use pnpm@latest`) |
| PHP (host) | None — runs in container per D-021 | partial | 8.3.30 (intl missing) | Not used; runs via container |
| Composer (host) | None — runs in container | — | — | Use `docker compose exec web composer ...` |
| Postgres (host) | None — runs in container | ✗ | — | Container `postgres:16-alpine` |
| Redis (host) | None — runs in container | ✗ | — | Container `redis:7-alpine` |
| `npx` | One-off tools (ctx7, etc.) | ✓ | bundled | — |
| `make` | Repo root Makefile (CONTEXT.md "specific ideas") | unknown — likely on Ubuntu/WSL | — | Hand-roll bash scripts in `bin/` if make unavailable |
| `git` | Source control | ✓ (repo is initialised; no working tree yet) | — | — |

**Missing dependencies with no fallback:**
- Docker Desktop with WSL integration enabled (per D-021 + STATE.md ACTIVE BLOCKER) — user action required before Phase 1 can execute.

**Missing dependencies with fallback:**
- pnpm: install globally OR use corepack (Node 22 ships with corepack).

**Important note on PHP 8.3 host install:** Host PHP at 8.3.30 with broken intl. **This is NOT used.** All PHP runs inside the `web` container at PHP 8.4. Composer commands run via `docker compose exec web composer ...`. No host changes required.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | **Pest 4.7+** (composer-required `--dev`) — Laravel default since 12.x |
| Config file | `apps/web/tests/Pest.php` (default Pest config; describes Feature/Unit/Browser groups) + `apps/web/phpunit.xml` (Pest's underlying XML config — auto-generated) |
| Quick run command | `docker compose exec web ./vendor/bin/pest --filter=<name> -x` |
| Full suite command | `docker compose exec web ./vendor/bin/pest --parallel` |
| Browser plugin | NOT installed in P1 (deferred — Phase 1 success criteria are all server-side, no browser-only behaviors) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| Success-1 | A user can land on `/` and click "Log in with Discord" → first login creates `users` + `players` + `player_privacy` | Pest Feature (HTTP + DB) | `docker compose exec web ./vendor/bin/pest tests/Feature/DiscordLoginTest.php -x` | ❌ Wave 0 |
| Success-1 (alt) | First-login provisioning is wrapped in a single DB transaction (rollback on failure) | Pest Unit | `./vendor/bin/pest tests/Unit/ProvisionFirstLoginTest.php -x` | ❌ Wave 0 |
| Success-2 | An admin can open `/admin` and see User/Player/Role/Permission resources | Pest Feature | `./vendor/bin/pest tests/Feature/AdminPanelAccessTest.php -x` | ❌ Wave 0 |
| Success-2 (gate) | A user without `admin-access` permission gets 403 on `/admin` | Pest Feature | `./vendor/bin/pest tests/Feature/AdminPanelGateTest.php -x` | ❌ Wave 0 |
| Success-3 | Filament create/update/delete writes to `activity_log` and is visible per-resource and on `/admin/audit` | Pest Feature | `./vendor/bin/pest tests/Feature/AuditLogTest.php -x` | ❌ Wave 0 |
| Success-4 | UI renders only via `__()` / `t()` — no hardcoded strings | Static / Pest custom | `./vendor/bin/pest tests/Static/NoHardcodedStringsTest.php -x` (custom: greps Vue templates) + manual lint rule | ❌ Wave 0 |
| Success-4 (Inertia) | `translations` Inertia shared prop is populated and `app.locale` matches | Pest Feature | `./vendor/bin/pest tests/Feature/InertiaSharedPropsTest.php -x` | ❌ Wave 0 |
| Success-5 | `web` and `worker` services start on Railway from monorepo with Postgres + Redis | Manual / smoke | `railway up` then visit health endpoint | ❌ Manual — verified post-deploy |
| Success-5 (CI) | CI runs Pint, PHPStan level 8, Pest on every push | GitHub Actions workflow | `git push` and observe Actions tab | ❌ Wave 0 (workflow files) |
| REQ-railway-deploy | All five services (web/worker/bot/rcon-worker + postgres/redis) reachable via Railway env URLs | Manual smoke | curl/browser checks | Manual |
| REQ-i18n-ready | `lang/en/auth.php` keys reachable via `__()` in PHP and `t()` in Vue | Pest Feature | `./vendor/bin/pest tests/Feature/I18nKeysAvailableTest.php -x` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `docker compose exec web ./vendor/bin/pest tests/Feature/<the-file-touched>.php -x` (typically <30s)
- **Per wave merge:** `docker compose exec web ./vendor/bin/pest --parallel` (full suite)
- **Phase gate:** Full suite green + Pint pass + PHPStan level 8 pass before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `apps/web/tests/Pest.php` — generated by `php artisan pest:install`, customise to define Feature + Unit groups
- [ ] `apps/web/tests/Feature/AppBootTest.php` — covers smoke `it('boots') GET / returns 200` (CONTEXT.md Specifics)
- [ ] `apps/web/tests/Feature/DiscordLoginTest.php` — covers Success-1 via `Socialite::fake()`
- [ ] `apps/web/tests/Feature/AdminPanelAccessTest.php` — covers Success-2
- [ ] `apps/web/tests/Feature/AdminPanelGateTest.php` — covers Success-2 gate
- [ ] `apps/web/tests/Feature/AuditLogTest.php` — covers Success-3
- [ ] `apps/web/tests/Feature/InertiaSharedPropsTest.php` — covers Success-4 Inertia plumbing
- [ ] `apps/web/tests/Feature/I18nKeysAvailableTest.php` — covers REQ-i18n-ready
- [ ] `apps/web/tests/Static/NoHardcodedStringsTest.php` — custom Pest test that greps templates (or a separate eslint rule)
- [ ] `apps/web/phpstan.neon` — extends Larastan, level 8, with baseline ignore for Filament-internal warnings
- [ ] `apps/web/pint.json` — Pint config (defaults are fine; no override needed)
- [ ] `.github/workflows/web.yml` — Pest + PHPStan + Pint on path filter `apps/web/**`
- [ ] `.github/workflows/bot.yml` — tsc + eslint + vitest on `apps/bot/**`
- [ ] `.github/workflows/rcon-worker.yml` — tsc + eslint + vitest on `apps/rcon-worker/**`
- [ ] `.github/workflows/shared-types.yml` — tsc on `packages/shared-types/**`

Framework install commands (Wave 0):
```bash
docker compose exec web composer require pestphp/pest:"^4.7" pestphp/pest-plugin-laravel:"^4.0" --dev
docker compose exec web composer require larastan/larastan:"^3.9" --dev
docker compose exec web composer require laravel/pint:"^1.29" --dev
docker compose exec web php artisan pest:install
```

## Security Domain

> Required per `.planning/config.json` `security_enforcement: true`, `security_asvs_level: 1`.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Discord OAuth via Socialite (D-002); session cookie `SameSite=Lax` + `HttpOnly` + `Secure` (prod); remember-me 30 days |
| V3 Session Management | yes | Laravel session driver `database` (or `redis`) — never `file` in prod; CSRF via Inertia XSRF cookie (NO `<meta>` tag); session regeneration on login |
| V4 Access Control | yes | spatie/laravel-permission with `admin-access` permission gating Filament; `canAccessPanel()` per Filament docs |
| V5 Input Validation | yes | Laravel `FormRequest` + spatie/laravel-data validation; Filament resource form validation; OAuth state validated by Socialite automatically |
| V6 Cryptography | yes | Laravel's `Crypt::encryptString` for any encrypted casts (planned for `match_servers.crcon_api_key_encrypted` in Phase 8 — not P1); Discord client_secret in env vars only |
| V7 Error Handling & Logging | yes | spatie/laravel-activitylog for audit trail; Laravel logs with `LOG_CHANNEL=stack` and Railway log streaming; never log passwords/tokens |
| V8 Data Protection | yes | Discord email stored as nullable; player_privacy `show_to` controls public exposure (Phase 2 surface, but column ships in P1) |
| V9 Communication | yes | HTTPS enforced via Laravel `URL::forceScheme('https')` in production; Railway provides TLS termination |
| V10 Malicious Code | partial | Composer/pnpm dep audit via `composer audit` + `pnpm audit` in CI |
| V11 Business Logic | minimal | First-login provisioning is idempotent (upsert by discord_id) — re-clicking login twice doesn't create duplicate players |
| V12 Files & Resources | n/a in P1 | No file uploads in P1 (avatar uploads ship in Phase 2 with spatie/laravel-medialibrary) |
| V13 API & Web Services | partial | Sanctum installed but not yet used (Phase 5+); CSRF via Inertia |
| V14 Configuration | yes | Secrets in Railway env groups (D-014); `.env.example` documents shape only; `.env` in `.gitignore` |

### Known Threat Patterns for {Laravel + Inertia + Discord OAuth} stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| OAuth state CSRF | Tampering / Spoofing | Socialite handles state token automatically; do NOT bypass via `stateless()` unless absolutely needed |
| OAuth redirect_uri tampering | Tampering | Discord verifies registered redirect_uri; we hardcode via env var (no dynamic redirect URLs) |
| OAuth token theft via session fixation | Spoofing | Laravel regenerates session on `Auth::login()` automatically |
| Open redirect after login | Tampering | We redirect to a hardcoded `/` (no `?intended=` query param parsing in P1) |
| Account takeover via Discord ID hijack | Spoofing | Discord IDs are cryptographically generated by Discord; we trust them. Risk: if Discord deletes a user's account and reassigns the ID — extremely rare; not mitigating in P1 |
| Privilege escalation via permission tampering | Elevation | spatie/laravel-permission writes via authoritative API only; no Mass Assignment to `model_has_permissions`; admin grants via artisan command (not API) |
| SQL injection | Tampering | Eloquent + parameterised queries; no raw `DB::statement()` outside extension-enable migration |
| XSS in user data (Discord username can be Unicode) | Tampering | Vue auto-escapes; Blade auto-escapes; `v-html` is a code review concern (P1: no user-authored content) |
| CSRF on POST endpoints | Tampering | Inertia auto-handles XSRF cookie; Laravel `web` middleware group enforces |
| Audit log tampering | Repudiation | Read-only via Filament UI (no edit/delete actions); Postgres-level INSERT-only via DB user grants (recommend in Phase 9 hardening) |
| Secret leak via git | Information Disclosure | `.env` in `.gitignore`; pre-commit hook scans for `DISCORD_CLIENT_SECRET=...` patterns (recommend `gitleaks` in CI) |
| Brute-force login | DoS | N/A — no email/password; Discord rate-limits OAuth endpoints |
| DoS via OAuth callback flood | DoS | Laravel's default rate-limit middleware on the callback route; Cloudflare/Railway edge limits |

### Project Constraints (from CLAUDE.md)

> **CLAUDE.md does NOT exist yet** — it will be authored in Phase 1 (per CONTEXT.md and additional context note). The planner MUST include a task to author `./CLAUDE.md` covering:
>
> - **Container-only command pattern:** All `composer`, `php artisan`, `pnpm`, `npm` commands run via `docker compose exec <service> <command>`. Never document a host-level command.
> - **Code style:** Pint runs on every commit (or pre-commit hook); CI fails on Pint violations.
> - **Static analysis:** PHPStan level 8 via Larastan; baseline regenerated only with explicit user request.
> - **Test conventions:** Pest, not PHPUnit syntax (`it()`, `test()`, `expect()`); Feature tests in `tests/Feature/`, Unit in `tests/Unit/`. Always inside the container.
> - **Path conventions:** Everything Laravel-related lives in `apps/web/`. Bot in `apps/bot/`. RCON worker in `apps/rcon-worker/`. Shared TS types in `packages/shared-types/`.
> - **Security:** Never commit secrets. Always use env vars. `.env.example` documents shape. Discord client_secret is sensitive.
> - **i18n:** All UI strings via `__()` / `t()`. CI checks for hardcoded strings in Vue templates (custom static test).
> - **Architecture pattern:** Discord bot is a thin display layer (D-004) — never put business logic there. Always call `apps/web` API.

## Sources

### Primary (HIGH confidence — Context7 + official docs + Packagist registry)

**Laravel 12 docs (Context7):**
- [/websites/laravel_12_x](https://laravel.com/docs/12.x) — installation, Socialite, migrations, sessions, middleware, queues, Postgres
- [/websites/laravel](https://laravel.com/docs) — UUIDs, Str helpers, Eloquent

**Filament v3 docs (Context7 + WebFetch):**
- [filamentphp.com/docs/3.x/panels/themes](https://filamentphp.com/docs/3.x/panels/themes) — VERIFIED: requires Tailwind v3 for theme.css
- [/websites/filamentphp](https://filamentphp.com/docs/5.x) (current docs, but the API is similar across v3-v5 for canAccessPanel + FilamentUser)

**Inertia v2 docs (Context7):**
- [/websites/inertiajs_v2](https://inertiajs.com/docs/v2) — installation, SSR, CSRF, shared props
- [inertiajs.com/docs/v2/getting-started/upgrade-guide](https://inertiajs.com/docs/v2/getting-started/upgrade-guide) — `@inertiajs/vue3@^2.0` is the v2 protocol package

**Tailwind v4 docs (Context7):**
- [/websites/tailwindcss](https://tailwindcss.com/docs) — `@theme`, `@source`, `@custom-variant`, Vite plugin
- [tailwindcss.com/docs/installation/framework-guides/laravel/vite](https://tailwindcss.com/docs/installation/framework-guides/laravel/vite) — official Laravel + Tailwind v4 setup

**spatie/* docs (Context7):**
- [/spatie/laravel-permission](https://spatie.be/docs/laravel-permission/v6) — install, traits, Filament integration
- [/spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) — LogsActivity trait
- [/spatie/laravel-translatable](https://github.com/spatie/laravel-translatable) — HasTranslations trait
- [/websites/spatie_be_laravel-data_v4](https://spatie.be/docs/laravel-data/v4) — DTOs and TypeScript transformer

**socialiteproviders/discord (Context7):**
- [/socialiteproviders/providers](https://socialiteproviders.com/Discord/) — install, Laravel 11+ Event::listen registration

**Railway docs (Context7):**
- [/websites/railway](https://docs.railway.com/) — monorepo deploy, Root Directory per service, env var injection
- [docs.railway.com/guides/monorepo](https://docs.railway.com/guides/monorepo) — directory setup
- [docs.railway.com/builds/build-configuration](https://docs.railway.com/builds/build-configuration) — Root Directory setting

**Ziggy docs (Context7):**
- [/tighten/ziggy](https://github.com/tighten/ziggy) — install, ZiggyVue plugin, php artisan ziggy:generate

**Packagist registry (Verified 2026-05-03):**
- laravel/framework v13.7.0 latest; v12.x current LTS (D-001 locks v12)
- filament/filament v5.6.2 latest; v3.3.50 latest in v3 line (D-001 locks v3)
- spatie/laravel-permission 7.4.1 (2026-04-29)
- spatie/laravel-data 4.22.1 (2026-04-27)
- spatie/laravel-translatable 6.14.1 (2026-04-23)
- spatie/laravel-activitylog 5.0.0 (2026-03-25 — major bump)
- laravel/socialite 5.27.0 (2026-04-24)
- socialiteproviders/discord 4.2.0 (2023-07-24 — stable, not abandoned)
- laravel/sanctum 4.3.1 (2026-02-07)
- inertiajs/inertia-laravel 3.0.6 latest; ^2.0 line is what D-001 references
- tightenco/ziggy 2.6.2 (2026-03-05)
- larastan/larastan 3.9.6 (2026-04-16)
- pestphp/pest 4.7.0 (2026-05-03 — released today)
- laravel/pint 1.29.1 (2026-04-20)

**npm registry (Verified 2026-05-03):**
- @inertiajs/vue3 latest=3.0.3, legacy=2.3.21 (D-001 locks ^2)
- tailwindcss 4.2.4
- @tailwindcss/vite 4.2.4
- vue 3.5.33 (latest stable)
- vite 8.0.10 latest (recommend pin ^6 for Laravel 12 compat)
- @vitejs/plugin-vue 6.0.6
- ziggy-js 2.6.2
- laravel-vue-i18n 2.8.0
- reka-ui 2.9.6
- discord.js 14.26.4

### Secondary (MEDIUM confidence — community sources cross-verified)

- [filamentthemes.com/guides/how-to-use-filament-with-tailwind-css-v4](https://filamentthemes.com/guides/how-to-use-filament-with-tailwind-css-v4) — npm-alias workaround for dual Tailwind versions; cross-verified against Filament v3 docs official acknowledgement
- [nathangross.me/blog/using-filament-v3-with-tailwind-css-v4-a-pnpm-workspaces-approach](https://nathangross.me/blog/using-filament-v3-with-tailwind-css-v4-a-pnpm-workspaces-approach) — pnpm workspaces variant of the same workaround; cross-verified
- [github.com/filamentphp/filament/discussions/8283](https://github.com/filamentphp/filament/discussions/8283) — community-recommended canAccessPanel pattern with spatie/laravel-permission
- [filamentmastery.com/articles/handle-authorization-in-filament-policies-roles-guards](https://filamentmastery.com/articles/handle-authorization-in-filament-policies-roles-guards) — guard mismatch pitfall (Pitfall 4)
- [github.com/inertiajs/inertia/issues/1454](https://github.com/inertiajs/inertia/issues/1454) — CSRF/419 pitfall (Pitfall 3)
- [docs.docker.com/guides/frameworks/laravel/production-setup/](https://docs.docker.com/guides/frameworks/laravel/production-setup/) — production Laravel + Docker reference
- [pestphp.com/docs/pest-v4-is-here-now-with-browser-testing](https://pestphp.com/docs/pest-v4-is-here-now-with-browser-testing) — Pest v4 browser plugin

### Tertiary (LOW confidence — flagged for validation)

- [martinbean/socialite-discord-provider](https://packagist.org/packages/martinbean/socialite-discord-provider) — alternative Discord provider, not the chosen one. Listed only as a fallback option.
- [laravel-news.com/package/bezhansalleh-filament-shield](https://laravel-news.com/package/bezhansalleh-filament-shield) — opinionated shield plugin; deferred (we roll vanilla Filament + spatie integration in P1)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — every version verified against Packagist + npm registry on 2026-05-03; all libraries have HIGH-quality Context7 entries
- Architecture: HIGH — Inertia + Vue + Tailwind + Filament + Laravel patterns are extensively documented
- Pitfalls: HIGH — Tailwind v4 / Filament v3 conflict has multiple primary + secondary sources confirming workaround; OAuth + CSRF pitfalls are documented in official Inertia/Laravel docs and confirmed in community issue threads
- Security: HIGH — STRIDE patterns are standard for OAuth + Laravel + Inertia; no novel threat surface in P1
- Validation: HIGH — Pest 4 + Laravel 12 testing patterns are mainstream

**Research date:** 2026-05-03
**Valid until:** ~2026-06-03 (30 days for stable libs); ~2026-05-15 for Filament/Tailwind workaround details (the dual-version setup is the kind of detail that gets superseded by upstream fixes)
