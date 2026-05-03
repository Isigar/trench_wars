# Decisions (synthesized intel)

All decisions originate from a single LOCKED ADR-class document and are therefore authoritative. Per-doc precedence override: none. Default precedence (`ADR > SPEC > PRD > DOC`) applies.

source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
status: locked
classification confidence: high

---

## D-001 — Stack: Laravel + Inertia + Vue
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: web app stack
- decision: Laravel 12 (PHP 8.4) + Inertia v2 + Vue 3 + Filament v3.
- rationale: Filament covers heavy CMS/admin/permission-matrix work for free; Inertia gives single-codebase forms/routing; owner is comfortable in PHP/Vue.
- considered: Next.js + Postgres + Prisma; NestJS + React; Django + DRF + React.
- trade-offs: PHP ecosystem for Discord bot is weak — accepted by running the bot as a separate Node service.

---

## D-002 — Auth: Discord OAuth only
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: authentication
- decision: Login is Discord-only. Discord ID is the canonical user identity.
- rationale: The community lives on Discord; email/password adds maintenance and account-recovery surface for no benefit.
- trade-offs: Site is unusable to anyone Discord-banned. Acceptable.

---

## D-003 — Single league guild
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: Discord topology / tenancy
- decision: One shared Discord guild for the entire league. Each clan = a Discord role inside that guild.
- rationale: Owner explicitly chose this model. Simplifies bot permissions, reduces operational overhead, makes cross-clan discovery natural.
- trade-offs: Per-clan privacy in Discord depends on channel/role configuration discipline.

---

## D-004 — Discord bot is a thin display layer
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: Discord bot architecture
- decision: discord.js v14 bot in Node calls the Laravel API for every interaction. Bot has no DB and no domain logic.
- rationale: Owner-stated requirement; keeps state in one place.
- trade-offs: Each interaction = one network call to web; need to be careful with the 3s Discord interaction window. Mitigated with `deferReply` for slow paths.

---

## D-005 — RCON via CRCON
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: HLL match-server integration
- decision: Integrate with hll_rcon_tool (CRCON); league deploys CRCON alongside each match server.
- rationale: Existing mature open-source tool for HLL. League owns the servers, so CRCON deployment is in scope.
- trade-offs: External dependency; mitigated with a stable adapter layer (`apps/rcon-worker`) so swapping to direct RCON later is contained.

---

## D-006 — Multi-clan league platform (multi-tenant)
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: tenancy model
- decision: One deployment hosts many clans, public clan directory, cross-clan matches.
- rationale: Stated requirement. League platform pattern, not single-clan site.

---

## D-007 — Generic game model with HLL preset, fully relational
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: Game / GameRole / GameMatchType data model
- decision: Game / GameRole / GameMatchType / GameMatchTypeRoleLimit as proper relational tables, all editable in Filament. No JSON game config.
- rationale: Owner explicitly rejected JSON. Filament Relation Managers give clean editing UI; FKs prevent orphaned slots; validations live in DB and form rules.
- considered: JSON config column on `games`; HLL-only schema.

---

## D-008 — No "groups" inside clans; tags instead
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: clan taxonomy / match access control
- decision: Clans don't have internal sub-groups. Clans carry tags (m:n) used for match access ACLs.
- rationale: Owner rejected internal groups, asked for tag-based filtering. Simpler model; ACLs become "match.allowed_tags".

---

## D-009 — One active clan per player, with internal roles
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: clan membership
- decision: A player has at most one active `ClanMembership`. Roles: Leader / Officer / Member / Recruit. History preserved.
- rationale: Mirrors how HLL clans operate; keeps signup logic simple.

---

## D-010 — Match signups by role slot
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: match signup model
- decision: Players sign up to specific slots derived from the chosen match type. Capacity enforced server-side.
- rationale: Closest to real HLL competitive practice; allows pre-match composition without out-of-band coordination.

---

## D-011 — Tournaments first-class in round 1
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: round-1 scope
- decision: Round-robin, single-elim, double-elim, swiss formats. Tournament → Stage → Round → Bracket → Match.
- rationale: Owner included tournaments in round-1 scope.
- trade-offs: Adds ~2 weeks to round 1.

---

## D-012 — Filament covers every domain entity, with audit
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: admin / audit
- decision: Every domain entity gets a Filament resource. Per-resource Audit tab + global `/admin/audit` page. spatie/laravel-activitylog as the engine.
- rationale: Owner's explicit ask for clans/players/profiles in Filament with audit logs.

---

## D-013 — i18n plumbed from day one, EN at launch
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: internationalisation
- decision: All strings via `__()` / `t()`; translatable user content via spatie/laravel-translatable jsonb. Round 1 ships EN only. URL has no locale prefix at launch.
- rationale: Cheap upfront, expensive to retrofit.

---

## D-014 — Hosting: Railway
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: deployment platform
- decision: Five services + Postgres + Redis on Railway.
- rationale: Owner's choice. Railway handles per-service deploys, plugins, env management cleanly.

---

## D-015 — Monorepo with pnpm workspaces; Laravel inside `apps/web`
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: repository / workspace layout
- decision: Single repo. `apps/web` (Laravel), `apps/bot`, `apps/rcon-worker`, `packages/shared-types`. Composer stays in `apps/web`.
- rationale: Shared TS types, atomic PRs across services, single CI pipeline. pnpm workspaces are lightweight.

---

## D-016 — Postgres 16
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: primary datastore
- decision: Postgres 16 over MySQL.
- rationale: JSONB for translatable columns, exclusion constraints for booking ranges, partial unique indexes for "one active clan", more rigorous typing.

---

## D-017 — No Laravel starter kit
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: auth scaffolding
- decision: Hand-roll auth scaffolding around Discord Socialite. No Breeze/Jetstream.
- rationale: Both starters ship session+email auth that we'd remove. Single-purpose Discord auth is shorter to write than to strip.

---

## D-018 — Per-section privacy + global tier on player profiles
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: player privacy
- decision: `player_privacy` 1:1 table with booleans (`show_real_name`, `show_discord_tag`, `show_clan_history`, `show_match_history`, `show_stats`) + global `show_to` tier (`public|community|clan|private`).
- rationale: Owner asked for user-controllable profile data sharing. Per-section gives granularity; global tier is a fast switch.

---

## D-019 — Result capture: CRCON live log + always-available manual override
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: match result capture
- decision: Auto-capture via CRCON when bookable; manual entry/override always present.
- rationale: RCON pipelines fail; we never want a match locked to "incomplete" because of network issues.

---

## D-020 — TypeScript types generated from Laravel DTOs
- source: /home/rtx/projects/trench-wars/.docs/15-decisions.md
- status: Accepted (locked)
- date: 2026-05-03
- scope: cross-service type safety
- decision: spatie/laravel-data + custom `typescript:generate` artisan command emits types into `resources/js/types/api.d.ts` and `packages/shared-types`.
- rationale: Keeps frontend, bot, and rcon-worker in lockstep with backend contracts.

---

## Update protocol
Per source: when updating a decision, append a new D-### with `Status: Supersedes D-###` rather than editing the original.
