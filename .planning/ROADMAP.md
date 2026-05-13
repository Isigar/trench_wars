# Roadmap: Trenchwars

## Overview

Round 1 of Trenchwars walks from a deployable Laravel skeleton with Discord login through clan/player domain, generic game model, manual matches, Discord bot for signup UX, tournaments with brackets, CMS for editorial and the public calendar, automatic CRCON-driven match capture, and a polish pass. Phases mirror the existing M1–M9 milestone sequence in `.docs/14-roadmap.md` 1:1 — each phase delivers something usable in production, and later phases do not strictly require all of the previous beyond their explicit dependencies. Total round-1 sizing per source roadmap: ~10–12 weeks for one experienced full-stack dev; M1–M5 alone (~5 weeks) is a credible competitive-system MVP without tournaments, CMS, or RCON automation.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work — these mirror `.docs/14-roadmap.md` M1–M9.
- Decimal phases (2.1, 2.2): Urgent insertions if they arise (marked with INSERTED).

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Foundations** — Deployable Laravel app with Discord login, admin panel, audit infrastructure, i18n plumbing, and Railway deploy.
- [x] **Phase 2: Clans & tags** — Clans exist as first-class entities; players have public profiles with privacy controls.
- [x] **Phase 3: Games & match types** — Admin can model any game's roles and match types fully in Filament; HLL is seeded.
- [ ] **Phase 4: Matches (manual)** — Matches can be created, signed up to, played, and results entered manually.
- [ ] **Phase 5: Discord bot v1** — Most match interactions can happen from Discord (slash commands, modals, RSVPs, role sync).
- [ ] **Phase 6: Tournaments & brackets** — Run an end-to-end tournament with a bracket UI and standings.
- [ ] **Phase 7: CMS** — League can publish editorial content and curate the public calendar.
- [ ] **Phase 8: RCON automation** — Match results and player stats flow in automatically from CRCON when matches are played on registered servers.
- [ ] **Phase 9: Polish** — Notifications, search, leaderboards, mod tooling, performance, accessibility, and hardening.

## Phase Details

### Phase 1: Foundations
**Goal**: Ship a deployable Laravel app on Railway with Discord OAuth login, an admin panel, audit infrastructure, and i18n plumbing — the platform skeleton everything else lands on.
**Depends on**: Nothing (first phase)
**Requirements**: REQ-constraint-railway-deploy, REQ-constraint-en-launch-i18n-ready
**Success Criteria** (what must be TRUE):
  1. A user can land on the deployed app and log in with Discord; first login creates `users` + `players` + `player_privacy` rows automatically.
  2. An admin can open the Filament panel and see base resources for User, Player, Role, and Permission.
  3. Every admin-side create/update/delete is captured in the activity log and visible per-resource and on `/admin/audit`.
  4. The UI renders only through `__()` / `t()` (no hardcoded strings) with `lang/en/*.php` and Inertia-shared `translations` working end-to-end.
  5. `web` and `worker` services run on Railway from the monorepo with Postgres + Redis plugins; CI runs Pint, PHPStan level 8, and Pest on every push.
**Plans**: 18 plans
- [x] 01-01-PLAN.md — pnpm monorepo skeleton (apps/bot, apps/rcon-worker, packages/shared-types stubs)
- [x] 01-02-PLAN.md — docker-compose.yml at repo root + per-service Dockerfiles + .env.example + Makefile
- [x] 01-03-PLAN.md — author CLAUDE.md (AI/dev conventions) + README.md (first-time setup walkthrough)
- [x] 01-04-PLAN.md — composer create-project Laravel 12 in apps/web; configure pgsql/redis/Discord env; Postgres extensions migration
- [x] 01-05-PLAN.md — install Pest 4 + Larastan L8 + Pint + Debugbar; tests/Pest.php + BootHealthcheckTest smoke
- [x] 01-06-PLAN.md — install Inertia v2 + Vue 3 + Vite; HandleInertiaRequests middleware; pages/Home.vue placeholder
- [x] 01-07-PLAN.md — install Tailwind v4 (CSS-first) + Reka UI + Lucide + Fontsource; UI-SPEC token system; Public layout + primitives
- [x] 01-08-PLAN.md — i18n plumbing (lang/en/*.php + Inertia translations prop + useT composable + NoHardcodedStringsTest)
- [x] 01-09-PLAN.md — Discord OAuth via Socialite (DiscordController + ProvisionFirstLogin listener + LoginButton CTA)
- [x] 01-10-PLAN.md — User/Player/PlayerPrivacy models + migrations (UUID PKs, citext email, jsonb bio, soft deletes) + factories
- [ ] 01-11-PLAN.md — install spatie/laravel-permission v7; PermissionSeeder; trenchwars:make-admin artisan command
- [x] 01-12-PLAN.md — install Filament v3 with dual-Tailwind workaround (Pitfall 1); AdminPanelProvider gated by admin-access
- [ ] 01-13-PLAN.md — Filament resources: User, Player (with inline player_privacy), Role, Permission
- [ ] 01-14-PLAN.md — install spatie/laravel-activitylog v5 (uuid PK migration); per-resource Audit tab + global /admin/audit page
- [ ] 01-15-PLAN.md — install spatie/laravel-data + typescript-transformer; UserData/PlayerData/PlayerPrivacyData DTOs; trenchwars:typescript-generate command syncing to packages/shared-types
- [x] 01-16-PLAN.md — GitHub Actions matrix CI (web + bot + rcon-worker + shared-types) with path filters
- [x] 01-17-PLAN.md — per-service Railway config (nixpacks.toml + railway.json) + RAILWAY-DEPLOY.md walkthrough
- [ ] 01-18-PLAN.md — [BLOCKING] migrate --force on fresh DB + full quality gates + manual smoke (Filament dual-Tailwind + Discord OAuth real-app)
**UI hint**: yes

### Phase 2: Clans & tags
**Goal**: Stand up clans as first-class entities with tags, memberships, invites/applications, and public-facing pages — including privacy-aware player profiles.
**Depends on**: Phase 1
**Requirements**: REQ-tenancy-single-guild, REQ-constraint-single-guild, REQ-tenancy-multi-clan, REQ-goal-public-profiles
**Success Criteria** (what must be TRUE):
  1. A public visitor can browse a clan directory at `/clans` and open a clan detail page at `/clans/{slug}` without authentication.
  2. A public visitor can open any player profile at `/players/{slug}` and only see fields permitted by that player's per-section flags + global `show_to` tier.
  3. A clan leader/officer can manage their clan from the "My Clan" page (edit profile, invite/accept members, assign roles) with audit log entries written for every change.
  4. The `discord_guild` table holds exactly one row, and each clan stores a `discord_role_id` rather than its own guild id.
  5. A player has at most one active `ClanMembership` (enforced by partial unique index), and membership history is preserved when they leave or move clans.
**Plans**: 14 plans
- [x] 02-01-PLAN.md — Wave 0 scaffolding (composer install + test stubs + factory stubs)
- [x] 02-02-PLAN.md — Migrations (7 tables incl. partial unique index for D-009)
- [x] 02-03-PLAN.md — Models (6 new + Player HasTranslations migration) + factories + model tests
- [x] 02-04-PLAN.md — Seeders (DiscordGuild singleton + ClanTag starter set) + single-row tests
- [x] 02-05-PLAN.md — PlayerPrivacyGate service + 6 DTOs + 2 unit tests
- [x] 02-06-PLAN.md — ClanSlugGenerator + i18n key files + 8 Vue UI primitive components
- [x] 02-07-PLAN.md — Public controllers (Clans Directory/Show + Player Profile) + routes + 4 feature tests
- [x] 02-08-PLAN.md — Public Vue pages (Clans/Players) + UserMenu + PublicLayout nav slot
- [x] 02-09-PLAN.md — My Clan controllers (Create/Profile/Members) + Policies + MyClanManagementTest
- [x] 02-10-PLAN.md — ClanInviteService + controller + ClanInviteTest
- [x] 02-11-PLAN.md — ClanApplicationService + MyClan/Index.vue (4-tab UI) + ClanApplicationTest
- [x] 02-12-PLAN.md — ClanResource + ClanTagResource (Filament) + 3 RelationManagers + presence test
- [x] 02-13-PLAN.md — Remaining Filament resources (Membership/Invite/Application/DiscordGuild) + admin tests
- [x] 02-14-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates
**UI hint**: yes

### Phase 3: Games & match types
**Goal**: Provide a fully relational, admin-editable game model so HLL (and any future game) can be configured in Filament without code changes.
**Depends on**: Phase 2
**Requirements**: REQ-platform-vision
**Success Criteria** (what must be TRUE):
  1. An admin can create or edit a Game and its GameRoles in Filament with `(game_id, key)` uniqueness enforced.
  2. An admin can create a GameMatchType (e.g. "Scrim 50v50") and set `GameMatchTypeRoleLimit` capacities per role through Filament Relation Managers.
  3. Seeded HLL data exists out of the box (Commander, Officer, SL, Rifleman, Assault, AR, Medic, Engineer, Support, HMG, AT, Sniper, Spotter, Tank Cmdr, Crewman + starter match types: Scrim 50v50, Skirmish 6v6, Friendly, Tournament, Clan War) and is fully editable post-seed.
  4. Adding a new game requires zero code changes — only Filament data entry.
**Plans**: 10 plans
- [x] 03-01-PLAN.md — Wave 0 scaffolding (4 factory stubs + 7 test stubs + admin.php i18n key extension)
- [x] 03-02-PLAN.md — Migrations (4 tables: games, game_roles, game_match_types, game_match_type_role_limits; UNIQUE + CHECK + cascade FKs)
- [x] 03-03-PLAN.md — Models (4 new) + factories + 4 model tests (UNIQUE + CHECK + cross-game saving guard + audit)
- [x] 03-04-PLAN.md — Spatie laravel-data DTOs (4) + TS regen + shared-types sync + GameDataTest
- [x] 03-05-PLAN.md — GameSeeder (HLL preset: 1 Game + 15 Roles + 5 MatchTypes + capacity matrix) + idempotency tests
- [x] 03-06-PLAN.md — GameResource (Filament) + Roles + MatchTypes RelationManagers
- [x] 03-07-PLAN.md — GameMatchTypeResource (Filament) + RoleLimits RelationManager (Pattern 3 scoped Select) + MatchTypesRelationManager URL-override amendment
- [x] 03-08-PLAN.md — GameResourcesPresentTest (admin reachability + RelationManager render checks + Pitfall 3 typo guard)
- [x] 03-09-PLAN.md — i18n key coverage audit + GameAuditLogTest (D-012)
- [x] 03-10-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates
**UI hint**: yes


### Phase 4: Matches (manual)
**Goal**: Make scheduling and recording matches the primary platform workflow — without RCON automation yet — covering creation, slot signups, capacity enforcement, tag-restricted access, and manual results.
**Depends on**: Phase 3
**Requirements**: REQ-goal-match-workflows
**Success Criteria** (what must be TRUE):
  1. A clan officer/leader can create a match by choosing a game match type; slots are materialised from `GameMatchTypeRoleLimit` and signups open automatically.
  2. A logged-in player can sign up to a specific role slot, and the live count of confirmed signups can never exceed slot capacity (enforced by DB transaction with row lock).
  3. A public visitor can view the match calendar at `/matches` and any match detail page at `/matches/{id}` with slot availability rendered.
  4. An organiser/admin can enter or override a match result (winner, scores, MVPs) in Filament and the change is audited.
  5. Tag-restricted matches reject signups from clans whose tags are not in `match_access_rules`, and creating a public match auto-creates a kept-in-sync `Event` row.
**Plans**: 13 plans
- [x] 04-01-PLAN.md — Wave 0 scaffolding (6 factory stubs + 21 Pest RED stubs + matches.php + admin.php appendix)
- [x] 04-02-PLAN.md — Migrations (6 tables: matches, match_slots, match_access_rules, match_results, match_mvps, events polymorphic)
- [x] 04-03-PLAN.md — Models + 6 real factories + User activeClanMembership amendment + 6 model tests
- [x] 04-04-PLAN.md — MatchStatusService + MatchNotOpenException + state-machine test
- [x] 04-05-PLAN.md — MatchSlotMaterialiserService (snapshot-at-create) + tests
- [x] 04-06-PLAN.md — MatchSignupService (D-010 row-locked) + 3 exception classes + 3 test files incl. pcntl concurrency
- [ ] 04-07-PLAN.md — 8 DTOs + TS regen + shared-types sync + 3 unit tests
- [ ] 04-08-PLAN.md — MatchObserver (polymorphic Event sync) + Match::booted() amendment + observer test
- [ ] 04-09-PLAN.md — MatchResource Filament wizard + 4 RelationManagers + EventResource + MatchResultService + 3 admin tests
- [ ] 04-10-PLAN.md — Public controllers (calendar + show + signup) + MatchSignupRequest + routes + 3 feature tests
- [ ] 04-11-PLAN.md — Public Vue pages (Matches/Index + Show) + 5 components + EventDateBadge + PublicLayout amendment
- [ ] 04-12-PLAN.md — i18n key audit + upgraded MatchResourcePresentTest + MatchAuditLogTest (D-012)
- [ ] 04-13-PLAN.md — [BLOCKING] phase verification + ROADMAP update + REQUIREMENTS update + final quality gates
**UI hint**: yes

### Phase 5: Discord bot v1
**Goal**: Move the day-to-day match interactions into Discord (slash commands, modals, RSVP buttons) so clan members can organise scrims without leaving Discord, while keeping `web` as the source of truth.
**Depends on**: Phase 4
**Requirements**: REQ-goal-discord-ux
**Success Criteria** (what must be TRUE):
  1. A Discord user can invoke `/clan info|list|apply`, `/match list|info|signup|leave`, `/profile`, and `/me` and get correct, privacy-aware responses inside the 3s interaction window (or via `deferReply` for slow paths).
  2. A Discord user can sign up to a match slot via the `/match signup` modal and the resulting `match_signups` row appears on the website immediately, with clan-role membership rules enforced server-side.
  3. When a match is created on the website, the host clan's announce channel receives an embed with RSVP buttons, persisted in `discord_outbound_messages` (`pending → sent | failed`) for durability.
  4. Joining or leaving a clan on the website triggers Discord role assignment/removal via Horizon-retried jobs; manual Discord-side role changes reconcile via `guildMemberUpdate` hook.
  5. All bot→web traffic uses the Sanctum `bot:*` scoped token + `X-Bot-Acts-As-User` header, and audit log entries correctly attribute the human causer behind every Discord action.
**Plans**: 14 plans
- [x] 02-01-PLAN.md — Wave 0 scaffolding (composer install + test stubs + factory stubs)
- [x] 02-02-PLAN.md — Migrations (7 tables incl. partial unique index for D-009)
- [x] 02-03-PLAN.md — Models (6 new + Player HasTranslations migration) + factories + model tests
- [x] 02-04-PLAN.md — Seeders (DiscordGuild singleton + ClanTag starter set) + single-row tests
- [x] 02-05-PLAN.md — PlayerPrivacyGate service + 6 DTOs + 2 unit tests
- [x] 02-06-PLAN.md — ClanSlugGenerator + i18n key files + 8 Vue UI primitive components
- [x] 02-07-PLAN.md — Public controllers (Clans Directory/Show + Player Profile) + routes + 4 feature tests
- [x] 02-08-PLAN.md — Public Vue pages (Clans/Players) + UserMenu + PublicLayout nav slot
- [x] 02-09-PLAN.md — My Clan controllers (Create/Profile/Members) + Policies + MyClanManagementTest
- [x] 02-10-PLAN.md — ClanInviteService + controller + ClanInviteTest
- [ ] 02-11-PLAN.md — ClanApplicationService + MyClan/Index.vue (4-tab UI) + ClanApplicationTest
- [ ] 02-12-PLAN.md — ClanResource + ClanTagResource (Filament) + 3 RelationManagers + presence test
- [ ] 02-13-PLAN.md — Remaining Filament resources (Membership/Invite/Application/DiscordGuild) + admin tests
- [ ] 02-14-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates

### Phase 6: Tournaments & brackets
**Goal**: Deliver tournaments as a first-class round-1 capability — formats, bracket generation, public bracket views, standings, and admin tooling for forfeits/withdrawals.
**Depends on**: Phase 5 (Discord announcements for matches), Phase 4 (matches), Phase 3 (game model)
**Requirements**: REQ-success-tournament-end-to-end
**Success Criteria** (what must be TRUE):
  1. An admin can create a tournament, register 8 clans as participants, seed them, and generate a single-elim bracket end-to-end without manual SQL or admin patching.
  2. The same workflow is available for round-robin, double-elim, and swiss formats with their respective bracket/round generation rules.
  3. A public visitor can open a tournament page at `/tournaments/{slug}` and switch between Overview, Bracket, Schedule, Standings, and Participants tabs; the bracket renders in custom Vue + SVG components with live polling every 30s during active rounds.
  4. When a bracket match finishes, `winner_participant_id` is recorded and the next bracket pulls participants via `advances_to_bracket_id` automatically; standings recompute with format-appropriate tiebreakers.
  5. Admin can reseed (when no matches played in a stage), forfeit, withdraw a participant, and recalculate standings via Filament actions, all audited.
**Plans**: 14 plans
- [x] 02-01-PLAN.md — Wave 0 scaffolding (composer install + test stubs + factory stubs)
- [x] 02-02-PLAN.md — Migrations (7 tables incl. partial unique index for D-009)
- [x] 02-03-PLAN.md — Models (6 new + Player HasTranslations migration) + factories + model tests
- [x] 02-04-PLAN.md — Seeders (DiscordGuild singleton + ClanTag starter set) + single-row tests
- [x] 02-05-PLAN.md — PlayerPrivacyGate service + 6 DTOs + 2 unit tests
- [x] 02-06-PLAN.md — ClanSlugGenerator + i18n key files + 8 Vue UI primitive components
- [x] 02-07-PLAN.md — Public controllers (Clans Directory/Show + Player Profile) + routes + 4 feature tests
- [x] 02-08-PLAN.md — Public Vue pages (Clans/Players) + UserMenu + PublicLayout nav slot
- [x] 02-09-PLAN.md — My Clan controllers (Create/Profile/Members) + Policies + MyClanManagementTest
- [ ] 02-10-PLAN.md — ClanInviteService + controller + ClanInviteTest
- [ ] 02-11-PLAN.md — ClanApplicationService + MyClan/Index.vue (4-tab UI) + ClanApplicationTest
- [ ] 02-12-PLAN.md — ClanResource + ClanTagResource (Filament) + 3 RelationManagers + presence test
- [ ] 02-13-PLAN.md — Remaining Filament resources (Membership/Invite/Application/DiscordGuild) + admin tests
- [ ] 02-14-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates
**UI hint**: yes

### Phase 7: CMS
**Goal**: Give the league an editorial surface — articles, categories, public calendar — so announcements and tournament write-ups can ship from Filament with translatable content and scheduled publishing.
**Depends on**: Phase 6 (tournaments + matches both surface as Events on the calendar)
**Requirements**: REQ-goal-cms, REQ-success-public-browse
**Success Criteria** (what must be TRUE):
  1. A `cms-editor` can create, schedule, and publish an article in Filament with translatable title/excerpt/body (Tiptap editor), hero image via medialibrary, and a category, with publishing flowing Draft → Scheduled → Published via Laravel Scheduler.
  2. A public visitor can browse `/blog`, open `/blog/{slug}` (server-rendered HTML with `markdown-it`), and view a calendar at `/events` with month/week/day views populated by both auto-generated match/tournament events and editorial events.
  3. The full round-1 public surface (clans, players, calendar, bracket views, articles) is reachable without authentication, with SSR enabled in production for first paint on public pages.
  4. Postgres FTS search works on articles, clans, and players via a header search bar and `/search?q=…` results page.
  5. Sitemap and meta tags are emitted; `<html lang>` reflects active locale; Discord announce on publish is wired (per-article configurable).
**Plans**: 14 plans
- [x] 02-01-PLAN.md — Wave 0 scaffolding (composer install + test stubs + factory stubs)
- [x] 02-02-PLAN.md — Migrations (7 tables incl. partial unique index for D-009)
- [x] 02-03-PLAN.md — Models (6 new + Player HasTranslations migration) + factories + model tests
- [x] 02-04-PLAN.md — Seeders (DiscordGuild singleton + ClanTag starter set) + single-row tests
- [x] 02-05-PLAN.md — PlayerPrivacyGate service + 6 DTOs + 2 unit tests
- [x] 02-06-PLAN.md — ClanSlugGenerator + i18n key files + 8 Vue UI primitive components
- [x] 02-07-PLAN.md — Public controllers (Clans Directory/Show + Player Profile) + routes + 4 feature tests
- [x] 02-08-PLAN.md — Public Vue pages (Clans/Players) + UserMenu + PublicLayout nav slot
- [ ] 02-09-PLAN.md — My Clan controllers (Create/Profile/Members) + Policies + MyClanManagementTest
- [ ] 02-10-PLAN.md — ClanInviteService + controller + ClanInviteTest
- [ ] 02-11-PLAN.md — ClanApplicationService + MyClan/Index.vue (4-tab UI) + ClanApplicationTest
- [ ] 02-12-PLAN.md — ClanResource + ClanTagResource (Filament) + 3 RelationManagers + presence test
- [ ] 02-13-PLAN.md — Remaining Filament resources (Membership/Invite/Application/DiscordGuild) + admin tests
- [ ] 02-14-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates
**UI hint**: yes

### Phase 8: RCON automation
**Goal**: Close the round-1 acceptance loop — when a match is played on a registered match server, results and per-player stats arrive automatically from CRCON, with manual override always available as a safety net.
**Depends on**: Phase 5 (Discord result announcements), Phase 4 (matches/results)
**Requirements**: REQ-goal-rcon-history, REQ-constraint-league-owns-servers, REQ-success-end-to-end-scrim
**Success Criteria** (what must be TRUE):
  1. An admin can register a `MatchServer` in Filament with encrypted CRCON credentials and run "Test Connection" to verify CRCON reachability and current game state.
  2. Booking a match against a server reserves `[scheduled_start − 5m, scheduled_end + 30m]` in `match_server_bookings`, and the Postgres exclusion constraint prevents any overlap on the same server.
  3. When a booked match runs, `rcon-worker` opens a CRCON session, streams normalised events to `web` via HMAC-signed `POST /api/internal/match/{id}/events`, and at `match_end` the system auto-populates `MatchResult` (`source = 'rcon'`) plus per-player `MatchPlayerStat` rows.
  4. CRCON failure modes (unreachable on session open, mid-match log gap, key rotated) degrade gracefully — match flagged for manual entry, error event surfaced in admin, manual override still wins.
  5. Two clans can complete the full round-1 happy path end-to-end: Discord OAuth → clan create → roster build → scrim schedule → Discord signup → CRCON-played → auto-recorded result + per-player stats — without manual data entry on the happy path.
**Plans**: 14 plans
- [x] 02-01-PLAN.md — Wave 0 scaffolding (composer install + test stubs + factory stubs)
- [x] 02-02-PLAN.md — Migrations (7 tables incl. partial unique index for D-009)
- [x] 02-03-PLAN.md — Models (6 new + Player HasTranslations migration) + factories + model tests
- [x] 02-04-PLAN.md — Seeders (DiscordGuild singleton + ClanTag starter set) + single-row tests
- [x] 02-05-PLAN.md — PlayerPrivacyGate service + 6 DTOs + 2 unit tests
- [x] 02-06-PLAN.md — ClanSlugGenerator + i18n key files + 8 Vue UI primitive components
- [x] 02-07-PLAN.md — Public controllers (Clans Directory/Show + Player Profile) + routes + 4 feature tests
- [ ] 02-08-PLAN.md — Public Vue pages (Clans/Players) + UserMenu + PublicLayout nav slot
- [ ] 02-09-PLAN.md — My Clan controllers (Create/Profile/Members) + Policies + MyClanManagementTest
- [ ] 02-10-PLAN.md — ClanInviteService + controller + ClanInviteTest
- [ ] 02-11-PLAN.md — ClanApplicationService + MyClan/Index.vue (4-tab UI) + ClanApplicationTest
- [ ] 02-12-PLAN.md — ClanResource + ClanTagResource (Filament) + 3 RelationManagers + presence test
- [ ] 02-13-PLAN.md — Remaining Filament resources (Membership/Invite/Application/DiscordGuild) + admin tests
- [ ] 02-14-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates

### Phase 9: Polish
**Goal**: Buffer milestone covering the things every shipping product needs but that don't fit cleanly into a feature-driven phase — notifications, search depth, leaderboards, mod tooling, performance, accessibility, and hardening.
**Depends on**: Phase 8 (everything else must exist before polishing it)
**Requirements**: (No new v1 requirements — completes existing scope; consumes any open polish backlog from prior phases.)
**Success Criteria** (what must be TRUE):
  1. A logged-in user has a notifications hub (web bell + Discord DM rules) with at least default sensible rules wired (match starting in 1h/15m, match cancelled, result published).
  2. Leaderboards render top clans and top players by stat windows, derived from `MatchPlayerStat` aggregates.
  3. Moderators have bulk actions, ban/suspend tooling, and a dispute resolution workflow for match results in Filament — all audited.
  4. A performance pass has eliminated obvious N+1s, applied a documented cache-key strategy, and image variants serve as WebP at appropriate sizes; pages on the round-1 public surface render in target time budgets.
  5. An accessibility pass has verified AA contrast on both themes, keyboard-only navigation through every public flow, and visible focus rings; rate-limit and abuse-vector hardening pass is documented.
**Plans**: 14 plans
- [x] 02-01-PLAN.md — Wave 0 scaffolding (composer install + test stubs + factory stubs)
- [x] 02-02-PLAN.md — Migrations (7 tables incl. partial unique index for D-009)
- [x] 02-03-PLAN.md — Models (6 new + Player HasTranslations migration) + factories + model tests
- [x] 02-04-PLAN.md — Seeders (DiscordGuild singleton + ClanTag starter set) + single-row tests
- [x] 02-05-PLAN.md — PlayerPrivacyGate service + 6 DTOs + 2 unit tests
- [x] 02-06-PLAN.md — ClanSlugGenerator + i18n key files + 8 Vue UI primitive components
- [ ] 02-07-PLAN.md — Public controllers (Clans Directory/Show + Player Profile) + routes + 4 feature tests
- [ ] 02-08-PLAN.md — Public Vue pages (Clans/Players) + UserMenu + PublicLayout nav slot
- [ ] 02-09-PLAN.md — My Clan controllers (Create/Profile/Members) + Policies + MyClanManagementTest
- [ ] 02-10-PLAN.md — ClanInviteService + controller + ClanInviteTest
- [ ] 02-11-PLAN.md — ClanApplicationService + MyClan/Index.vue (4-tab UI) + ClanApplicationTest
- [ ] 02-12-PLAN.md — ClanResource + ClanTagResource (Filament) + 3 RelationManagers + presence test
- [ ] 02-13-PLAN.md — Remaining Filament resources (Membership/Invite/Application/DiscordGuild) + admin tests
- [ ] 02-14-PLAN.md — [BLOCKING] phase verification + ROADMAP update + final quality gates
**UI hint**: yes

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundations | 18/18 | Complete | 2026-05-04 |
| 2. Clans & tags | 14/14 | Complete | 2026-05-12 |
| 3. Games & match types | 10/10 | Complete | 2026-05-13 |
| 4. Matches (manual) | 0/TBD | Not started | - |
| 5. Discord bot v1 | 0/TBD | Not started | - |
| 6. Tournaments & brackets | 0/TBD | Not started | - |
| 7. CMS | 0/TBD | Not started | - |
| 8. RCON automation | 0/TBD | Not started | - |
| 9. Polish | 0/TBD | Not started | - |
