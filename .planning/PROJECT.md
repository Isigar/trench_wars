# Trenchwars

## What This Is

Trenchwars is a web platform where multiple clans organise competitive matches and tournaments — primarily for Hell Let Loose, designed so additional games can be added without code changes. Discord is the primary social layer; the website is the source of truth for clans, players, matches, results, and editorial content. It is built as a multi-clan league platform with one shared league Discord guild, public clan directory, public player profiles, public match calendar, and public bracket viewer.

## Core Value

Two clans can schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically — without manual data entry on the happy path.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

(None yet — round 1 not yet shipped.)

### Active

<!-- Round-1 scope. Detailed list with REQ-IDs lives in REQUIREMENTS.md. -->

- [ ] Multi-clan league platform with public clan directory, player profiles, match calendar, bracket viewer (REQ-tenancy-multi-clan)
- [ ] Single shared league Discord guild; each clan = a Discord role inside that guild (REQ-tenancy-single-guild, REQ-constraint-single-guild)
- [ ] Game-agnostic relational data model (Game / GameRole / GameMatchType / GameMatchTypeRoleLimit) with HLL as a seeded preset (REQ-platform-vision)
- [ ] Structured match/tournament workflows replacing ad-hoc Discord scheduling (REQ-goal-match-workflows)
- [ ] Automatic match history capture per clan and per player via CRCON (REQ-goal-rcon-history)
- [ ] Public profiles for clans and players with controllable privacy (per-section + global tier) (REQ-goal-public-profiles)
- [ ] CMS for league announcements and event calendar (REQ-goal-cms)
- [ ] Tight Discord UX: signups, RSVPs, results announcements via slash commands and channel posts (REQ-goal-discord-ux)
- [ ] Round-1 success: end-to-end scrim happy path (REQ-success-end-to-end-scrim)
- [ ] Round-1 success: 8-clan single-elim tournament end-to-end (REQ-success-tournament-end-to-end)
- [ ] Round-1 success: public visitor can browse clans, players, calendar, bracket views, articles (REQ-success-public-browse)
- [ ] League owns HLL game servers; CRCON installed on each (REQ-constraint-league-owns-servers)
- [ ] English at launch; multi-language possible without refactor (REQ-constraint-en-launch-i18n-ready)
- [ ] Deployed to Railway (REQ-constraint-railway-deploy)

### Out of Scope

<!-- Explicit boundaries. Documented to prevent re-adding. -->

- Native mobile apps — round-1 non-goal; web is mobile-first responsive instead
- Real-time spectator overlays — round-1 non-goal; out of round-1 scope, complexity vs value
- Anti-cheat / VAC integrations — round-1 non-goal; CRCON-based stats only
- Federation across multiple league installs — round-1 non-goal; single-install model intentionally
- Per-clan custom domains — round-1 non-goal; one league domain
- Per-clan theming — round-1 non-goal; single league brand only
- Email/password authentication — community lives on Discord; OAuth-only is simpler (D-002)
- Real-time chat — Discord covers it; on-site comments/reactions out of round-1 scope (CON-cms-comments-out-of-scope)
- Video uploads — storage/bandwidth out of round-1 scope
- Meilisearch — round-1 search uses Postgres FTS; Meilisearch deferred (CON-cms-search)
- WebSocket live updates on tournament page — round-1 polls; WebSockets considered for v2 (CON-tournament-public-view)
- Per-clan voice channels managed by the bot — out of round-1 (Open Question)
- Self-hosting outside Railway — round-1 not optimised for self-hosting elsewhere (REQ-constraint-railway-deploy)

## Context

Owner is comfortable in PHP/Vue. Round-1 is the first ship of a brand-new product with no prior code. The community already lives on Discord and matches today are scheduled ad-hoc in chat — Trenchwars replaces that with structured workflows while keeping Discord as the social surface. CRCON (`hll_rcon_tool`) is the existing open-source HLL admin tool the league already operates; the platform integrates with it rather than reimplementing RCON. Filament v3 is leaned on heavily for admin/CMS/permissions UI to keep the buildable scope realistic for a single full-stack developer. Total round-1 sizing per existing M1–M9 roadmap: ~10–12 weeks for one experienced full-stack dev; M1–M5 alone (~5 weeks) is a credible competitive-system MVP without tournaments, CMS, or RCON automation.

## Constraints

- **Tech stack — Web**: Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 + Tailwind v4 (CSS-first config) — owner's stack of choice; Filament v3 covers heavy CMS/admin/permission-matrix work for free (D-001, CON-stack-versions, CON-stack-web-libraries, CON-stack-frontend-libraries)
- **Tech stack — Bot**: Node.js 22 + discord.js v14 + TypeScript + ioredis + undici — discord.js is the de facto Discord library; PHP ecosystem for Discord is weak so the bot is a separate Node service (D-001 trade-off, CON-stack-bot-libraries)
- **Tech stack — RCON**: Node.js 22 + TypeScript + undici + ioredis + ws — TS aligns with bot tooling; CRCON exposes an HTTP+WebSocket API (CON-stack-rcon-libraries)
- **Datastore**: Postgres 16 + Redis 7 — JSONB for translatable columns, exclusion constraints for booking ranges, partial unique indexes for "one active clan", more rigorous typing than MySQL (D-016)
- **Hosting**: Railway (5 services + Postgres + Redis plugins) — owner's choice; per-service deploys, env groups, plugins (D-014, CON-arch-railway-deployment)
- **Repository layout**: Single pnpm-workspaces monorepo (`apps/web`, `apps/bot`, `apps/rcon-worker`, `packages/shared-types`); Composer stays inside `apps/web` (D-015, CON-arch-monorepo-layout)
- **Auth**: Discord OAuth only via Laravel Socialite — community lives on Discord; email/password adds maintenance for no benefit (D-002)
- **Discord topology**: One shared league guild; each clan = a Discord role inside that guild (D-003)
- **Bot architecture**: discord.js bot is a thin display layer that calls the Laravel API for every interaction; bot has no DB and no domain logic (D-004)
- **RCON integration**: CRCON (`hll_rcon_tool`) is the integration target; league deploys CRCON alongside each match server (D-005)
- **Tenancy**: Multi-clan league platform — one deployment hosts many clans, public directory, cross-clan matches (D-006)
- **Game model**: Generic Game / GameRole / GameMatchType / GameMatchTypeRoleLimit relational tables (no JSON game config); HLL is a seeded preset, not hard-coded (D-007)
- **Clan taxonomy**: Clans don't have internal sub-groups; they carry tags (m:n) used for match access ACLs (D-008)
- **Clan membership**: A player has at most one active ClanMembership; roles Leader / Officer / Member / Recruit; history preserved (D-009)
- **Match signup model**: Players sign up to specific role slots derived from chosen match type; capacity enforced server-side via DB transaction with row lock (D-010)
- **Tournament scope**: Round-robin, single-elim, double-elim, swiss formats are first-class in round 1 (D-011)
- **Admin / audit**: Every domain entity gets a Filament resource; per-resource Audit tab + global `/admin/audit` page; spatie/laravel-activitylog as the engine (D-012)
- **i18n**: All strings via `__()` / `t()`; translatable user content via spatie/laravel-translatable jsonb; round 1 ships EN only; URL has no locale prefix at launch (D-013)
- **Auth scaffolding**: No Laravel starter kit (Breeze/Jetstream); hand-rolled around Discord Socialite (D-017)
- **Player privacy**: Per-section booleans (`show_real_name`, `show_discord_tag`, `show_clan_history`, `show_match_history`, `show_stats`) + global `show_to` tier (`public|community|clan|private`) (D-018)
- **Result capture**: CRCON live log auto-capture when bookable; manual entry/override always available (D-019)
- **Type safety**: TypeScript types generated from Laravel DTOs via spatie/laravel-data + custom `typescript:generate` artisan command, emitted into `resources/js/types/api.d.ts` and `packages/shared-types` (D-020)
- **Bot↔web auth**: Sanctum personal access token scoped `bot:*` + header `X-Bot-Acts-As-User: <discord_id>` so `web` resolves causer correctly (CON-arch-bot-to-web-comm)
- **rcon-worker→web auth**: HMAC SHA-256 over `(timestamp + body)`, header `X-Rcon-Signature`, 60s freshness window (CON-arch-rcon-to-web-comm)
- **CI**: GitHub Actions matrix per app — `web` runs Pest + PHPStan level 8 + Pint; `bot` and `rcon-worker` run tsc + vitest + eslint (CON-arch-ci)
- **NFR — Frontend**: Fast first load (SSR enabled in production), mobile-first, dark mode default + light option, accessible (keyboard, screen-reader, AA contrast), translatable from day one (CON-frontend-goals)
- **NFR — Audit retention**: Round-1 indefinite; revisit at six months (CON-audit-retention)
- **NFR — Discord security**: HMAC + 60s replay window; bot uses scoped token; rate limits per-bot-token (CON-discord-security)
- **NFR — RCON security**: CRCON keys encrypted at rest via Laravel encrypted casts; outbound HTTPS-only when CRCON exposed publicly; HMAC inbound (CON-rcon-security)
- **Local dev environment**: All services run in containers via `docker-compose.yml` at repo root; host runs only Docker Desktop (with WSL integration), Node 22, and Composer-via-container. PHP/Postgres/Redis are NOT installed on host (D-021)

## Locked Decisions

21 ADR decisions LOCKED (D-001..D-020 from `.docs/15-decisions.md` + D-021 added 2026-05-03 during autonomous Phase 1 environment review). Update protocol: append a new D-### with `Status: Supersedes D-###` rather than editing the original.

<decisions>
| ID | Decision | Rationale | Status |
|----|----------|-----------|--------|
| D-001 | Stack: Laravel 12 (PHP 8.4) + Inertia v2 + Vue 3 + Filament v3 | Filament covers heavy CMS/admin/permission-matrix work for free; Inertia gives single-codebase forms/routing; owner is comfortable in PHP/Vue. Trade-off: PHP for Discord bot is weak — accepted by running bot as separate Node service. | LOCKED |
| D-002 | Auth: Discord OAuth only; Discord ID is canonical user identity | Community lives on Discord; email/password adds maintenance and account-recovery surface for no benefit. Trade-off: site is unusable to anyone Discord-banned. | LOCKED |
| D-003 | Single league Discord guild; each clan = a Discord role inside that guild | Owner's chosen topology. Simplifies bot permissions, reduces operational overhead, makes cross-clan discovery natural. | LOCKED |
| D-004 | Discord bot is a thin display layer (calls Laravel API for every interaction; no DB; no domain logic) | Owner-stated requirement; keeps state in one place. Trade-off: 3s Discord interaction window — mitigated with `deferReply` for slow paths. | LOCKED |
| D-005 | RCON integration via CRCON (`hll_rcon_tool`); league deploys CRCON alongside each match server | Existing mature open-source tool for HLL; league owns the servers. Trade-off: external dependency mitigated with stable adapter layer in `apps/rcon-worker`. | LOCKED |
| D-006 | Multi-clan league platform; one deployment hosts many clans; public directory; cross-clan matches | Stated requirement. League platform pattern, not single-clan site. | LOCKED |
| D-007 | Generic Game / GameRole / GameMatchType / GameMatchTypeRoleLimit relational tables; HLL is a seeded preset; no JSON game config | Owner explicitly rejected JSON. Filament Relation Managers give clean editing UI; FKs prevent orphaned slots; validations live in DB and form rules. | LOCKED |
| D-008 | No internal sub-groups inside clans; clans carry tags (m:n) used for match access ACLs | Owner rejected internal groups, asked for tag-based filtering. Simpler model; ACLs become `match.allowed_tags`. | LOCKED |
| D-009 | One active ClanMembership per player; roles Leader/Officer/Member/Recruit; history preserved | Mirrors how HLL clans operate; keeps signup logic simple. | LOCKED |
| D-010 | Match signups by role slot (slots derived from chosen match type, capacity enforced server-side) | Closest to real HLL competitive practice; allows pre-match composition without out-of-band coordination. | LOCKED |
| D-011 | Tournaments first-class in round 1: round-robin, single-elim, double-elim, swiss | Owner included tournaments in round-1 scope. Trade-off: ~2 extra weeks. | LOCKED |
| D-012 | Filament covers every domain entity; per-resource Audit tab + global `/admin/audit`; spatie/laravel-activitylog as engine | Owner's explicit ask for clans/players/profiles in Filament with audit logs. | LOCKED |
| D-013 | i18n plumbed from day one; EN at launch; URL has no locale prefix at launch | Cheap upfront, expensive to retrofit. | LOCKED |
| D-014 | Hosting on Railway (5 services + Postgres + Redis plugins) | Owner's choice. Railway handles per-service deploys, plugins, env management cleanly. | LOCKED |
| D-015 | pnpm-workspaces monorepo; `apps/web` (Laravel), `apps/bot`, `apps/rcon-worker`, `packages/shared-types`; Composer stays in `apps/web` | Shared TS types, atomic PRs across services, single CI pipeline. pnpm workspaces are lightweight. | LOCKED |
| D-016 | Postgres 16 (over MySQL) | JSONB for translatable columns, exclusion constraints for booking ranges, partial unique indexes for "one active clan", more rigorous typing. | LOCKED |
| D-017 | No Laravel starter kit (Breeze/Jetstream); hand-roll auth scaffolding around Discord Socialite | Both starters ship session+email auth that we'd remove. Single-purpose Discord auth is shorter to write than to strip. | LOCKED |
| D-018 | Per-section privacy booleans + global `show_to` tier (`public|community|clan|private`) on player profiles | Owner asked for user-controllable profile data sharing. Per-section gives granularity; global tier is a fast switch. | LOCKED |
| D-019 | Result capture: CRCON live log auto-capture when bookable; manual entry/override always available | RCON pipelines fail; we never want a match locked to "incomplete" because of network issues. | LOCKED |
| D-020 | TypeScript types generated from Laravel DTOs via spatie/laravel-data + `typescript:generate` artisan command | Keeps frontend, bot, and rcon-worker in lockstep with backend contracts. | LOCKED |
| D-021 | Local dev via custom `docker-compose.yml` at repo root, with all five Railway services containerized (web/php-fpm 8.4, bot/node 22, rcon-worker/node 22, postgres 16, redis 7); host installs of PHP/Postgres/Redis are not used for development | Owner's machine has PHP 8.3 without dynamic-load support (`intl` extension fails) and no host Postgres/Redis. Containerizing all services keeps dev parity with Railway prod topology and avoids host pollution. Trade-off: requires Docker Desktop with WSL integration; first-time pull cost. Composer/pnpm/artisan run via `docker compose exec`. | LOCKED |
</decisions>

## Open Questions

Unresolved at planning time (from `.docs/16-open-questions.md`). Advisory, not blocking. Capture answers as new decisions in the table above when resolved.

### Branding & content
- Brand name confirmed as "Trenchwars"? (User-supplied direction confirms yes for now.)
- Logo / mark direction.
- Default accent colour confirmed (current placeholder: deep red ~#A4262C on muted olive).
- Domain name / hostnames.

### Editorial
- Initial blog categories. Suggested: News, Match Reports, Tournament Updates, Community.
- Editorial team — who has `cms-editor` at launch?
- Cadence of league announcements (informs notification settings defaults).

### Matches & tournaments
- Confirm initial HLL match-type set: `Scrim 50v50`, `Skirmish 6v6`, `Friendly`, `Clan War`, `Tournament`. Anything to add?
- Default best-of for tournament finals.
- Whether forfeits award default scores or just a winner.
- Tiebreaker rules for round-robin: head-to-head → point differential → coin flip?
- Swiss tiebreakers: Buchholz, opponent points, head-to-head — order?
- Maximum tournament size we should design for (visual bracket limits).

### Discord
- Does logging in require being in the league guild, or only encouraged?
- Channel naming convention for clan announce channels (so admins don't have to paste IDs).
- Bot avatar / banner.
- Per-clan voice channels managed by the bot? (Out of round 1 by default.)

### RCON
- CRCON version we standardise on.
- Server pool sizing — how many active match servers at launch?
- Who manages the CRCON deploy on each server? (Probably league IT, but worth confirming.)
- Should we cache CRCON live logs ourselves for replay, or rely on CRCON's retention?

### Data & retention
- Activity log retention policy beyond round 1 (current default: indefinite).
- GDPR right-to-erasure flow: anonymise vs delete? Anonymise is preferred for match-history integrity.
- Backup cadence for Postgres on Railway.

### Future locales
- Order of additional languages (CS, SK, PL, DE, RU, FR?).
- Whether to translate clan-authored content (we don't translate; owner does).

### Performance & ops
- SSR on by default in production, or opt-in per route?
- Rate limit numbers for the public API.
- Email provider for transactional mail (currently no email need beyond OAuth — Discord covers DMs).
- Monitoring stack: Railway logs only, or add Sentry / Logtail?

### Naming conventions
- Slug rules (current default: lowercase, kebab, ASCII only — confirmed?).
- Clan tag length cap (suggested: 2–8 chars).
- Reserved words list for clan slugs (`admin`, `me`, `api`, etc.).

---
*Last updated: 2026-05-03 after roadmap initialization from intel ingest*
