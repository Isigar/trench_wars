# Phase 1: Foundations - Context

**Gathered:** 2026-05-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Ship a deployable Laravel 12 + PHP 8.4 app on Railway with Discord OAuth login, a Filament v3 admin panel, audit infrastructure, i18n plumbing, and CI — the platform skeleton everything else lands on.

**In scope:**
- pnpm-workspaces monorepo at repo root (`apps/web`, `apps/bot`, `apps/rcon-worker`, `packages/shared-types`).
- `docker-compose.yml` at repo root with all five services + Postgres 16 + Redis 7 (per D-021); host installs of PHP/Postgres/Redis are NOT used.
- Laravel 12 inside `apps/web` (created via `composer create-project` inside the `web` container).
- Discord OAuth via Laravel Socialite. First-login auto-provisions `users` + `players` + `player_privacy` rows in a single DB transaction.
- Filament v3 panel at `/admin` with 4 resources: User, Player, Role, Permission. Permission gate `admin-access`. First admin seeded via artisan command.
- spatie/laravel-activitylog wired with per-resource Audit tab AND global `/admin/audit` page.
- i18n end-to-end: PHP `lang/en/*.php` files, Inertia-shared `translations` prop, Vue `t()` helper.
- Railway service definitions (`web`, `worker`) using shared `apps/web` image.
- GitHub Actions CI matrix: `web` (Pest + PHPStan level 8 + Pint), `bot` (tsc + vitest + eslint), `rcon-worker` (tsc + vitest + eslint).
- TypeScript DTO generation via spatie/laravel-data + custom `typescript:generate` artisan → `resources/js/types/api.d.ts` + `packages/shared-types`.

**Out of scope (deferred to later phases):**
- Clan models, slots, signups, matches (Phase 2+).
- Discord bot service code (Phase 5; only the package skeleton + dockerfile in P1).
- RCON worker code (Phase 8; only the package skeleton + dockerfile in P1).
- `guilds` OAuth scope / league-membership gate (deferred — answer captured in Open Questions).
- Filament Clan/Match/Tournament resources (their owning phases).

</domain>

<decisions>
## Implementation Decisions

### Scaffolding Order & Layout

- **Scaffold order**: Init pnpm monorepo first (`apps/web`, `apps/bot`, `apps/rcon-worker`, `packages/shared-types`) → write `docker-compose.yml` (D-021) → bring up containers → `composer create-project laravel/laravel apps/web` *inside the web container* → wire Inertia v2 / Vue 3 / Filament v3 / Tailwind v4.
- **`apps/web` internal layout**: Standard Laravel tree (`app/`, `routes/`, `database/`, `resources/`, `lang/`, `tests/`). No `Domain/`, `Services/`, `ValueObjects/` abstractions in P1 — keep PSR-4 `app/` flat; introduce abstractions only when a phase actually needs them.
- **Composer dev tools day 1**: Pint, Larastan/PHPStan **level 8**, Pest, spatie/laravel-data, spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-translatable, filament/filament v3, laravel/socialite. Minimal config, baseline ignore file where needed.
- **Frontend pipeline**: Vite + `@vitejs/plugin-vue` + Tailwind v4 (CSS-first config, no `tailwind.config.js`) + Inertia v2 + `@inertiajs/vue3` + `ziggy-js` for routes. SSR config scaffolded but optional in dev (enabled in production-mode docker compose later).

### Discord OAuth & First-Login Flow

- **Login UI surface**: Single "Log in with Discord" button on `/` (landing) → `GET /auth/discord/redirect` → callback `GET /auth/discord/callback`. No separate `/login` page in P1. Logged-out landing also doubles as the marketing surface.
- **First-login provisioning**: Inside `DB::transaction()`: upsert `users` (by `discord_id`), create `players` (1:1 FK), create `player_privacy` with defaults (`show_to=community`, all section booleans `true`). Triggered by a `Login` event listener — testable, observable, idempotent on re-login.
- **OAuth scopes**: `identify` + `email` only. Sufficient for `discord_id`, `username`, `discriminator`, `global_name`, `avatar`, `email`. `guilds` scope deferred to a later phase (logged in Open Questions).
- **Post-login destination + session**: Redirect to `/` with success toast (Inertia flash). Session cookie `SameSite=Lax`, `Secure` in production, `HttpOnly`. Remember-me 30 days. No admin redirect in P1; the panel is reachable via direct `/admin` for users with `admin-access` permission.

### Filament Admin, Audit & i18n

- **Filament panel & gating**: Mount at `/admin` (Filament default). Gate via `admin-access` permission (spatie/laravel-permission). First admin seeded via `php artisan trenchwars:make-admin <discord_id>` (idempotent — creates `admin-access` permission if missing, attaches it to user). Theme: dark default + light option (CON-frontend-goals); placeholder accent `#A4262C` on muted olive.
- **P1 Filament resources**: Exactly 4 per success criterion 2 — **User**, **Player**, **Role**, **Permission**. Each with list/view/edit. Player resource shows linked `player_privacy` fields inline. No bulk actions or exports yet.
- **Audit infrastructure**: spatie/laravel-activitylog with `LogsActivity` trait on User and Player. Per-resource Audit tab inside each Filament resource. Global `/admin/audit` Filament custom page listing all activity with filters (causer / subject_type / date range). `causer_type` derived from authenticated user. Indefinite retention per CON-audit-retention.
- **i18n end-to-end wiring**: `lang/en/{auth,common,validation,admin}.php` PHP files for backend strings. Inertia middleware shares a `translations` prop containing the active locale's flat-keyed dictionary. Vue helper `t(key, params)` resolves from `usePage().props.translations` with `:?param` interpolation. Validation messages localized. No JSON translation files — PHP arrays only for canonical English. Translatable user content (will be introduced in later phases) uses spatie/laravel-translatable JSONB columns.

### Claude's Discretion

- Exact Vite chunking strategy.
- Specific Tailwind v4 token values beyond the placeholder accent.
- Pest test scaffolding shape (Pest's preset feature/unit split is fine).
- PHPStan baseline contents (just generate one if level 8 introduces existing issues from Filament).
- Specific Filament page slugs and column ordering inside resources.
- The exact set of activitylog events recorded per resource (covers create/update/delete by default; refine if it gets noisy).

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets

None — this is the first phase of a greenfield repo. Only `.planning/` and `.docs/` exist on disk.

### Established Patterns

None — Phase 1 establishes the baseline patterns (Laravel + Inertia + Vue 3 conventions, monorepo layout, Docker dev workflow).

### Integration Points

- All future phase code lands in `apps/web/{app,routes,resources,database,lang,tests}/` — Laravel-standard tree.
- Discord bot code lands in `apps/bot/` (Phase 5).
- RCON worker code lands in `apps/rcon-worker/` (Phase 8).
- TypeScript DTOs from spatie/laravel-data are emitted to `apps/web/resources/js/types/api.d.ts` and re-exported via `packages/shared-types` so bot + rcon-worker can import them.

</code_context>

<specifics>
## Specific Ideas

- **Containerised tooling**: All `composer`, `pnpm`, `php artisan`, `npm` commands run inside containers via `docker compose exec web ...`. Add a `Makefile` or `bin/` shim scripts at repo root so the most common commands (`make up`, `make artisan`, `make composer`) are short.
- **Volumes**: `apps/web` mounted as a bind mount into the `web` container; `vendor/` and `node_modules` use named volumes (or anonymous volumes) to avoid bind-mount perf issues on Windows-side filesystems. (We're already on the WSL filesystem at `/home/rtx/projects/trench-wars` per the user's setup.)
- **Postgres + Redis**: Compose services with named volumes for persistence. Default DB `trenchwars`, user `trenchwars`, password `trenchwars` for dev only. Production uses Railway env vars.
- **`docker-compose.override.yml` not used in P1** — keep a single `docker-compose.yml`; introduce override file only if Phase 2+ needs prod-vs-dev variant.
- **Discord OAuth env vars**: `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI` — documented in `.env.example` with comments pointing to https://discord.com/developers/applications. Local dev redirect URI: `http://localhost:8000/auth/discord/callback`.
- **Audit log first impressions**: Default activitylog retention is "forever". Add a single `database/seeders/AuditDemoSeeder.php` test fixture so the `/admin/audit` page isn't empty on first launch (only seeded in `local`/`testing` envs).
- **CI Pest**: Run a single smoke test in P1 — `it('boots')` that asserts the app responds 200 to `/`. Keep CI green from day one.

</specifics>

<deferred>
## Deferred Ideas

- **`guilds` OAuth scope** — gate login on league-guild membership. Captured in PROJECT.md Open Questions ("Does logging in require being in the league guild, or only encouraged?"). Decide before Phase 5 (Discord bot) since the bot itself is in-guild and naturally validates membership.
- **Multi-locale support** — D-013 says ship EN only at launch; locale switcher and additional `lang/{cs,sk,pl,...}/*.php` files are out of P1 scope.
- **JSON locale files for client-side i18n** — explicitly rejected for P1 (PHP arrays only).
- **`docker-compose.override.yml`** for prod-vs-dev variants — not needed yet; revisit if Phase 8 (RCON) introduces dev/prod-divergent worker config.
- **Activity log retention sweeper** — CON-audit-retention says "round-1 indefinite; revisit at six months". No sweeper job in P1.
- **Bulk actions / exports in Filament** — not in P1 scope; add when admin volume justifies.
- **SSR enabled-by-default in production** — placeholder Vite SSR config is scaffolded but actually wiring SSR in `web` container is deferred until a later phase has a measurable need.

</deferred>
