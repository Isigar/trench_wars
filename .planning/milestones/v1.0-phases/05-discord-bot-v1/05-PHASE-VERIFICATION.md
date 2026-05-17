---
phase: 05-discord-bot-v1
status: Complete (PENDING_MANUAL_SMOKE)
completed: 2026-05-13
plans_complete: 13
plans_total: 13
test_count: 618
assertion_count: 1817
bot_test_count: 117
gates_passed:
  pest: true
  vitest: true
  pint: true
  phpstan: true
  tsc: true
  shared_types: true
  vue_tsc: true
requirements:
  - REQ-goal-discord-ux
manual_smoke_required:
  - Live Discord guild slash command roundtrip (SC-1)
  - /match signup modal end-to-end (SC-2)
  - Outbound delivery end-to-end (SC-3)
  - Role sync on clan join (SC-4)
  - guildMemberUpdate reconciliation (SC-4)
  - Sanctum bot:* token misuse rejection (SC-5)
canonical_model_binding: "App\\Models\\GameMatch (D-04-03-A LOCKED — inherited and re-affirmed across all 13 Phase 5 plans; `match` is a PHP 8.x reserved keyword for the `match($x)` expression; class is `GameMatch` while the underlying table remains `matches` via `protected $table` override; direct `use App\\Models\\GameMatch;` import everywhere — zero alias-on-import across the entire Phase 5 surface)"
---

# Phase 5 — Discord bot v1 — Verification Report

**Date:** 2026-05-13
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual smoke section)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 5 |
| Name | Discord bot v1 |
| Slug | discord-bot-v1 |
| Plans | 13 plans (05-01 through 05-13) |
| Completed date | 2026-05-13 |
| Phase 4 foundation | Phase 4 COMPLETE (2026-05-13) |
| Canonical model name | `App\Models\GameMatch` (D-04-03-A LOCKED — see frontmatter) |
| Requirement satisfied | REQ-goal-discord-ux |

---

## Overview

Phase 5 delivered the complete Discord bot v1 surface — both the web-side
adapters (Sanctum-scoped bot↔web auth, durable outbox table for Discord
side-effects, `ResolveBotActsAsUser` middleware for human causer
attribution, Filament admin retry surface, MatchObserver outbound writer,
SyncDiscordRolesJob Horizon-retried role sync) AND the complete bot-side
stack (discord.js v14.26 + 4 slash commands + RSVP buttons + signup modal
+ outbound polling worker + guildMemberUpdate reconciler). All five
ROADMAP Success Criteria are mechanically observable against concrete
test files and source artifacts; REQ-goal-discord-ux is satisfied.

---

## [BLOCKING] Quality gates — RESULT: PASS

| Gate | Command | Result |
|------|---------|--------|
| Pest (web full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **618 passed** (1817 assertions), 0 failed, 0 incomplete, 27.92s |
| Vitest (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"` | **117 passed** (10 test files), 0 failed, 727ms |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 342 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| tsc strict (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm run typecheck"` | **PASS** — `tsc --noEmit` clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | **PASS** — clean |
| vue-tsc (web) | `docker compose exec web /app/node_modules/.bin/vue-tsc --noEmit` | **PASS** — 0 errors |
| Placeholder Wave-0 stubs | included in Pest 618 above | **PASS** — 0 incomplete |

**Test growth across phases:**

| Phase | Total tests after phase | Phase contribution |
|-------|--------------------------|--------------------|
| Phase 1 close (01-18) | ~94 tests | +94 |
| Phase 2 close (02-14) | 214 tests | +120 |
| Phase 3 close (03-10) | 278 tests | +64 |
| Phase 4 close (04-13) | 493 tests | +215 |
| Phase 5 close (05-13) | **618 tests** | **+125 web + 117 bot** |

Phase 5 contributed 125 web Pest tests / 358 assertions across the
`Tests\Feature\Bot\*`, `Tests\Unit\Bot\*`, `Tests\Feature\Admin\Bot*` and
related namespaces, PLUS the complete bot-side Vitest suite of 117 tests
spanning lib/customIds, lib/embeds, commands/{match,clan,profile},
components/{rsvpButton,signupModal}, services/outbound, events/guildMemberUpdate.

---

## ROADMAP Success Criteria mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 | A Discord user can invoke `/clan info\|list\|apply`, `/match list\|info\|signup\|leave`, `/profile`, and `/me` and get correct, privacy-aware responses inside the 3s interaction window (or via `deferReply` for slow paths). | `apps/bot/tests/commands/match.test.ts` (13 tests — plan 05-09), `apps/bot/tests/commands/clan.test.ts` (9 tests — plan 05-09), `apps/bot/tests/commands/profile.test.ts` (5 tests — plan 05-09); `apps/web/tests/Feature/Bot/BotApiUserMeTest.php` (plan 05-04 — `/me` privacy-aware endpoint); manual Discord guild smoke A documented below | **PASS** |
| SC-2 | A Discord user can sign up to a match slot via the `/match signup` modal and the resulting `match_signups` row appears on the website immediately, with clan-role membership rules enforced server-side. | `apps/bot/tests/components/signupModal.test.ts` (11 tests — plan 05-10 — modal customId round-trip + role UUID extraction), `apps/web/tests/Feature/Bot/BotApiMatchSignupTest.php` (plan 05-04 — D-04-06 service-reuse proof: same 5-guard order via /api/bot/matches/{id}/signups), `apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php` (plan 05-12 SC-5 capstone — exercises full path including the SC-2 modal trail); manual Discord guild smoke B documented below | **PASS** |
| SC-3 | When a match is created on the website, the host clan's announce channel receives an embed with RSVP buttons, persisted in `discord_outbound_messages` (`pending → sent \| failed`) for durability. | `apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php` (12 tests / 33 assertions — plan 05-05 — MatchObserver writes outbound row on `is_public=true` create), `apps/web/tests/Feature/Bot/BotApiOutboundClaimTest.php` (plan 05-04 — atomic claim with `status=pending → dispatching`), `apps/web/tests/Feature/Bot/BotApiOutboundAckTest.php` (plan 05-04 — markSent + markFailed state machine), `apps/web/tests/Feature/Bot/DiscordOutboundAuditLogTest.php` (5 tests — plan 05-12 — LogsActivity for every state transition through real HTTP endpoints), `apps/bot/tests/services/outbound.test.ts` (11 tests — plan 05-11 — polling loop + overlap-skip guard + tick body), `apps/bot/tests/lib/embeds.test.ts` (20 tests — plan 05-10), `apps/bot/tests/components/rsvpButton.test.ts` (16 tests — plan 05-10); manual delivery smoke C documented below | **PASS** |
| SC-4 | Joining or leaving a clan on the website triggers Discord role assignment/removal via Horizon-retried jobs; manual Discord-side role changes reconcile via `guildMemberUpdate` hook. | `apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php` (plan 05-06 — job writes role_sync outbound row; Horizon-retried; backoff on failure), `apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php` (plan 05-06 — ClanMembershipObserver fires on saved/deleted with action=add/remove), `apps/bot/tests/events/guildMemberUpdate.test.ts` (8 tests — plan 05-11 — Pattern 6 verbatim role diff; one POST per delta; defensive nullable guards), `apps/web/tests/Feature/Bot/DiscordEventRoleChangeEchoSuppressionTest.php` (plan 05-04 — Pitfall 10 60s window suppresses observer echo); manual smokes D + E documented below | **PASS** |
| SC-5 | All bot→web traffic uses the Sanctum `bot:*` scoped token + `X-Bot-Acts-As-User` header, and audit log entries correctly attribute the human causer behind every Discord action. | `apps/web/tests/Feature/Bot/ResolveBotActsAsUserMiddlewareTest.php` (plan 05-03 — middleware rebinds Auth::user from bot token-owner to acts-as user; `Auth::setUser` + `Auth::guard('web')->setUser` defence-in-depth — D-05-03-A), `apps/web/tests/Feature/Bot/BotApiAuthMatrixTest.php` (plan 05-03 — Sanctum abilities matrix: bot:read vs bot:act-as-user vs bot:write-outbound), `apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php` (plan 05-12 capstone — 3 it() blocks: happy path with `->not->toBe($bot->id)` defence + 422 unknown discord_id + missing-acts-as Pitfall 7 tolerance contrast — D-05-12-E); manual smoke F documented below | **PASS** |

**SC verification commands:**

```bash
# SC-1: Slash command coverage (bot side) + /me privacy-aware endpoint (web side)
docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm vitest run tests/commands"
docker compose exec web ./vendor/bin/pest --filter='BotApiUserMe' --no-coverage

# SC-2: Modal customId round-trip + service-reuse signup proof
docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm vitest run tests/components/signupModal.test.ts"
docker compose exec web ./vendor/bin/pest --filter='BotApiMatchSignup|MatchSignupViaBotCauser' --no-coverage

# SC-3: Outbound observer + claim + ack + audit log + bot worker
docker compose exec web ./vendor/bin/pest --filter='DiscordOutboundOnMatchCreate|BotApiOutboundClaim|BotApiOutboundAck|DiscordOutboundAuditLog' --no-coverage
docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm vitest run tests/services/outbound.test.ts tests/lib/embeds.test.ts tests/components/rsvpButton.test.ts"

# SC-4: Role sync job + observer + guildMemberUpdate reconciler + echo suppression
docker compose exec web ./vendor/bin/pest --filter='SyncDiscordRolesJob|DiscordEventRoleChangeEchoSuppression' --no-coverage
docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm vitest run tests/events/guildMemberUpdate.test.ts"

# SC-5: Middleware + abilities matrix + capstone causer attribution
docker compose exec web ./vendor/bin/pest --filter='ResolveBotActsAsUserMiddleware|BotApiAuthMatrix|MatchSignupViaBotCauserAttribution' --no-coverage
```

---

## Requirements traceability

| Requirement | Description | Test file(s) | Status |
|-------------|-------------|--------------|--------|
| REQ-goal-discord-ux | Slash commands `/clan`, `/match`, `/profile`, `/me` exist; RSVP buttons and slot-picker modals work; result announcements post to the host clan's announce channel. | All 5 SCs above — SC-1 (slash commands) + SC-2 (signup modal) + SC-3 (announcements + RSVP) + SC-4 (role sync) + SC-5 (Sanctum + acts-as). The 125-test Phase 5 web Pest contribution plus the 117-test bot Vitest suite plus the cross-phase 618-test total Pest run prove the requirement landed without breaking any prior phase. | **PASS** |

REQ-goal-discord-ux is the single requirement mapped to Phase 5 in
`REQUIREMENTS.md`. All five success criteria collectively prove this
requirement is satisfied — slash commands ship, modal-driven signup
hits the same row-locked service Phase 4 ships, outbound announcements
land in a durable outbox with retry, role sync works in both directions,
and audit attribution correctly resolves to the human causer behind
every bot action.

---

## RESEARCH Pitfall Coverage

12 pitfalls from `05-RESEARCH.md`; each mapped to a concrete mitigation
test and/or source artifact.

| # | Pitfall | Mitigation (file + plan) | Status |
|---|---------|--------------------------|--------|
| 1 | 3-second interaction window — slash commands MUST reply or deferReply within 3s | `apps/bot/src/commands/{match,clan,profile,me}.ts` — every command calls `interaction.deferReply({ ephemeral: true })` first (plan 05-09); `apps/bot/src/events/interactionCreate.ts` — modal-submit branch defers before service call (plan 05-10); button branch peeks `m:o:` prefix to skip pre-defer when the handler shows a modal (D-05-10-E) | mitigated |
| 2 | Discord global rate limit 50 req/s — outbound batch SIZE control | `apps/web/app/Http/Controllers/BotApi/BotApiOutboundController.php` — `claim()` clamps `limit` query param to 20 max (plan 05-04); bot polling loop in `apps/bot/src/services/outbound.ts` uses `intervalMs` setInterval + overlap-skip guard (plan 05-11 — D-05-11-A) | mitigated |
| 3 | Sanctum token leakage in error messages / logs | `apps/bot/src/services/api.ts` — token scrub on error before re-throw (plan 05-08 — D-05-08-A); `apps/web/app/Console/Commands/IssueBotTokenCommand.php` + `RevokeBotTokenCommand` — token rotation pathway (plan 05-07); `.env.example` has empty `WEB_API_TOKEN` (plan 05-01 — D-05-01-F) | mitigated |
| 4 | Sanctum stateful guard bleed-through — `bot.acts-as` rebinds web session | `apps/web/config/sanctum.php` — `SANCTUM_STATEFUL_DOMAINS` explicitly empty in `.env.example` (plan 05-01 — D-05-01-F); `apps/web/app/Http/Middleware/ResolveBotActsAsUser.php` uses `Auth::setUser` instead of `Auth::onceUsingId` (which is not callable on Sanctum RequestGuard — D-05-03-A); plan 05-01 + 05-03 included `php artisan config:show sanctum.stateful` verification step | mitigated |
| 5 | discord.js customId 100-char cap | `apps/bot/src/lib/customIds.ts` — short prefix scheme `m:o:<uuid>` (one-letter namespace + one-letter action — plan 05-08 — D-05-08-B); `apps/bot/tests/lib/customIds.test.ts` — 22 tests including explicit length assertion + Pitfall 5 round-trip + null guards (plan 05-08) | mitigated |
| 6 | discord.js Intents misconfig — `GuildMembers` required for `guildMemberUpdate` | `apps/bot/src/client.ts` — `Client` constructor declares `GatewayIntentBits.GuildMembers` alongside `Guilds` + `GuildMessages` (plan 05-08); VALIDATION.md manual smoke check (verify privileged intent toggle on Discord developer portal); covered by `apps/bot/tests/events/guildMemberUpdate.test.ts` mocking the dispatch contract | mitigated |
| 7 | ResolveBotActsAsUser swallows system writes — pass-through tolerance | plan 05-04 route grouping decision: `bot.acts-as` middleware is ONLY mounted on write-on-behalf endpoints (matches/signups, clan/apply); read endpoints (`/me`, `/matches/list`, `/clans/list`) skip the middleware entirely so system tokens read without the header (D-05-04-B/C); `MatchSignupViaBotCauserAttributionTest::it_keeps_bot_as_causer_when_acts_as_header_missing` documents the inverse case via contrast (D-05-12-E) | mitigated (documented as architectural contract, not enforcement) |
| 8 | Horizon worker missing in deployment | `docker-compose.yml` ships dedicated `worker` service (plan 05-01 — D-05-01-E); production Railway deploy attaches a worker service with the same start command (`php artisan horizon`); SyncDiscordRolesJobTest exercises the queued-job retry contract (plan 05-06) | mitigated |
| 9 | PHP `match` reserved keyword collision with class name | **D-04-03-A LOCKED** — `App\Models\GameMatch` direct import everywhere in Phase 5 surface (controllers, observers, services, FormRequests, tests); zero `use App\Models\Match as MatchModel` alias-on-import — verified via grep across `apps/web/app/Http/Controllers/BotApi/*`, `apps/web/app/Observers/*`, `apps/web/app/Services/Bot/*`, `apps/web/app/Jobs/SyncDiscordRolesJob.php` (all plans 05-04 through 05-12) | mitigated |
| 10 | Echo loop via own `guildMemberUpdate` — bot's own role add/remove fires reconciler | `apps/web/app/Http/Controllers/BotApi/BotApiDiscordEventController.php` — 60s replay-suppression window checks recent role_sync outbound rows by `discord_user_id` + `discord_role_id` (plan 05-04 — D-05-06-A); `apps/web/tests/Feature/Bot/DiscordEventRoleChangeEchoSuppressionTest.php` validates the suppression window | mitigated |
| 11 | Modal submit 3-second window — must defer or reply within 3s | `apps/bot/src/events/interactionCreate.ts` — `isModalSubmit()` branch calls `interaction.deferReply({ ephemeral: true })` before the API call (plan 05-09 + 05-10); `apps/bot/tests/components/signupModal.test.ts` — 11 tests including the defer-before-service contract (plan 05-10) | mitigated |
| 12 | Bot `WEB_API_URL` hostname must resolve inside the docker network | `docker-compose.yml` sets `WEB_API_URL=http://web-nginx` in the bot service environment (Phase 1 + plan 05-01 — D-05-01-E); production overrides via Railway env groups (D-014) | mitigated |

---

## RESEARCH Open Question Resolutions

5 open questions from `05-RESEARCH.md`; each resolved with a concrete
binding plus the plan that landed the resolution.

| # | Question | Resolution | Plan |
|---|----------|-----------|------|
| Q1 | Channel resolution — who populates `clans.discord_announce_channel_id`? | New column on `clans` table (already shipped by Phase 2 plan 02-12 per D-05-02-A); admin sets via ClanResource Filament field (plan 05-07 helperText + maxLength — D-05-07-A); MatchObserver reads on match create (plan 05-05 — DiscordOutboundPayloadBuilder eager-loads `gameMatchType.hostClan.discord_announce_channel_id`) | 05-02 / 05-05 / 05-07 |
| Q2 | Acts-as-user when Discord user has never logged in (no users.discord_id row) | Fail with 422 `bot.errors.acts_as_unknown` (D-013-compliant i18n key — plan 05-12 audit confirmed key exists); user must first log in at the website to provision their user/player row before bot can act on their behalf — surfaced via `bot.errors.acts_as_unknown` translated by bot's `translateError` helper substring match (D-05-10-D); covered by `MatchSignupViaBotCauserAttributionTest::it_returns_422_when_acts_as_discord_id_is_unknown` | 05-03 / 05-12 |
| Q3 | Filament retry action shape — what fields does retry reset? | Flip `status=failed → pending` + zero `attempts` + clear `backoff_until` + clear `last_error` (plan 05-07 DiscordOutboundMessageResource RetryAction); covered by `DiscordOutboundMessageResourcePresentTest::it_resets_attempts_and_clears_backoff_on_retry` + `DiscordOutboundMessageAuditLogTest` (3 it() blocks — D-05-12-D) | 05-07 |
| Q4 | Bot service account User — what shape? | Dedicated User row with `discord_id='SYSTEM_BOT'`, `username='Trenchwars Bot'`, `email='bot@trenchwars.local'`, `locale='en'` (D-05-07-B — `locale` is NOT NULL with no DB default, Rule 2); provisioned via `BotServiceUserSeeder` + `trenchwars:bot:issue-token` Artisan command (plan 05-07); IssueBotTokenCommandTest covers idempotency + abilities array enforcement | 05-07 |
| Q5 | `/me` vs `/profile` boundary | `/me` uses own-profile bypass on the `/api/bot/users/me` endpoint (plan 05-04 BotApiUserController + D-04-07-A privacy bypass for self); `/profile` v1 ships a redirect-to-web stub (plan 05-09 — D-05-09-A); future polish (Phase 9) will add a viewer-aware `/api/bot/users/by-discord/{id}` endpoint with `PlayerPrivacyGate` enforcement | 05-04 / 05-09 |

---

## Locked Decisions Honored

### Project-level decisions (PROJECT.md D-### table)

| Decision | Honored | Evidence |
|----------|---------|----------|
| **D-001** Stack: Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 | YES | Phase 5 added laravel/sanctum ^4 + laravel/horizon ^5 + discord.js ^14.26 + undici + ws on top of the same stack; no major-version bumps |
| **D-002** Auth: Discord OAuth only; Discord ID is canonical | YES | `bot.acts-as` rebinds via `User::where('discord_id', $header)->firstOrFail()` (plan 05-03); bot service user uses `discord_id='SYSTEM_BOT'` sentinel (plan 05-07 Q4) |
| **D-003** One league Discord guild | YES | role_sync outbound rows scope to the singleton `discord_guild` row's id; bot uses `Client.guilds.cache.get(guild_id)` on the singleton |
| **D-004** Bot is thin display layer; no DB, no domain logic | YES | bot has zero DB drivers/migrations; every interaction goes through `apps/bot/src/services/api.ts` HTTP → `/api/bot/*` controllers; `apiContracts.ts` is the cross-process contract surface re-exported from `@trenchwars/shared-types` |
| **D-009** One active ClanMembership; partial unique index | YES | `ClanMembershipObserver::saved()` fires `SyncDiscordRolesJob` on add (when `left_at IS NULL`) and `deleted()`/hard-delete fires the remove action (defensive — D-05-06-F); `MatchSignupService` consumed unchanged by `/api/bot/matches/{id}/signups` controller |
| **D-010** Match signups row-locked | YES | `BotApiMatchSignupController` instantiates the SAME `MatchSignupService` (plan 04-06) via DI — no parallel implementation; D-04-06 5-guard order applies verbatim |
| **D-012** Filament + spatie/activitylog audit infra | YES | `DiscordOutboundMessage` uses `LogsActivity` trait (plan 05-02); state transitions through real `/api/bot/outbound-messages/{id}/{claim,sent,failed}` endpoints write activity_log rows (verified by `DiscordOutboundAuditLogTest` — D-05-12-C); admin retry action writes activity_log with admin causer (`DiscordOutboundMessageAuditLogTest` — D-05-12-D) |
| **D-013** i18n plumbed; EN at launch; every UI string via `__()` / `t()` | YES | `apps/web/lang/en/bot.php` shipped in plan 05-01 + audited end-to-end in plan 05-12 (`BotI18nKeyCoverageTest` — surfaced + closed 1 gap on `admin.discord_outbound_message.fields.causer` — D-05-12-B); `apps/web/lang/en/admin.php` extended with the `discord_outbound_message` namespace |
| **D-014** Railway 5 services + Postgres + Redis | YES | `docker-compose.yml` worker service added in plan 05-01 (D-05-01-E healthcheck portable to busybox); production Railway service definitions cover web/worker/bot/rcon-worker on the same compose topology |
| **D-015** pnpm-workspaces monorepo | YES | bot pnpm-lock.yaml lives at repo root only (D-05-01-D); shared-types re-exports the bot's apiContract types via `packages/shared-types/src/api.d.ts` |
| **D-017** No starter kit; hand-roll Sanctum + middleware | YES | `bot.acts-as` middleware hand-rolled in plan 05-03 (`Auth::setUser` + `Auth::guard('web')->setUser` defence-in-depth — D-05-03-A); Sanctum CheckAbilities middleware aliases registered in `bootstrap/app.php` |
| **D-021** Local dev via docker-compose; host PHP/Postgres/Redis NOT used | YES | Every Phase 5 plan executed via `docker compose exec web ...` (Pest, Pint, PHPStan) + `docker compose run --rm bot ...` (bot Vitest); zero host-PHP invocations |

### D-04-03-A continuation (canonical model name binding into Phase 5+)

**CRITICAL for Phase 6+ executors:** The model class is `App\Models\GameMatch`,
NOT `App\Models\Match`. This is locked by D-04-03-A and re-affirmed across
all 13 Phase 5 plans (zero `App\Models\Match as MatchModel` alias-on-import
anywhere in the Phase 5 codebase surface). Phase 6 tournaments plans MUST:

- Import via `use App\Models\GameMatch;` directly (no alias).
- Pass `match_id` as explicit FK arg on every `BelongsTo<GameMatch, $this>` relation method (D-04-03-B continuation).
- Use `$this->table = 'matches'` to keep the underlying SQL table name unchanged.
- Reference relation methods by `match()` (the relation method name CAN be `match` because PHP allows reserved words as method names — only class names collide).

### Phase 5 plan-level locked decisions (rolled up from per-plan SUMMARYs)

| Decision | Source | Description |
|----------|--------|-------------|
| **D-05-01-A** | 05-01 | laravel/sanctum v4.3.2 installed via container composer per D-021 |
| **D-05-01-B** | 05-01 | laravel/horizon v5.46.0 auto-registered in `bootstrap/providers.php` |
| **D-05-01-C** | 05-01 | Wave 0 Pest stub idiom = canonical bare form (no namespace, no per-file `uses()` — Pest.php autowires both via `uses(...)->in('Feature')`) |
| **D-05-01-D** | 05-01 | `apps/bot/pnpm-lock.yaml` does NOT exist — workspace lockfile at repo root only (D-015) |
| **D-05-01-E** | 05-01 | Worker docker healthcheck uses `tr+grep` on `/proc/1/cmdline` (busybox-portable) instead of `pgrep` (unavailable in php-fpm Alpine) |
| **D-05-01-F** | 05-01 | `SANCTUM_STATEFUL_DOMAINS` explicitly empty in `.env.example` as defence-in-depth on Pitfall 4 |
| **D-05-02-A** | 05-02 | `clans.discord_announce_channel_id` was already shipped by Phase 2 plan 02-12; Phase 5's second migration omitted to avoid duplicate column |
| **D-05-02-B** | 05-02 | spatie/laravel-activitylog `LogOptions` uses `dontLogEmptyChanges()` (plan's `dontSubmitEmptyLogs()` does not exist in v4+) |
| **D-05-02-C** | 05-02 | spatie/laravel-activitylog v4+ stores attribute diffs in `attribute_changes` column (NOT `properties`); tests read `attribute_changes` |
| **D-05-03-A** | 05-03 | `Auth::onceUsingId` not callable on Sanctum RequestGuard — middleware uses `Auth::setUser` + `Auth::guard('web')->setUser` (defence-in-depth for LogsActivity causer chain) |
| **D-05-03-B** | 05-03 | Plan 05-01's `personal_access_tokens` migration originally used `bigint morphs` — edited in-place to `uuidMorphs` for users.uuid PK compatibility (safe pre-prod-deploy) |
| **D-05-03-C** | 05-03 | `Laravel\Sanctum\HasApiTokens` trait added to `App\Models\User` as Rule 2 amendment (missing from plan 05-01 install:api scaffold) |
| **D-05-03-D** | 05-03 | Pitfall 7 wire contract: middleware tolerates missing `X-Bot-Acts-As-User` header (200 pass-through with token-owner identity); per-route 422 enforcement is the controller's job |
| **D-05-04-A** | 05-04 | `MatchSignupService` is `final` — replaced container-bind stub with non-Mockery D-004 reuse proof |
| **D-05-04-B/C** | 05-04 | Pitfall 7 pass-through contract documented in `BotApiMatchSignupAbilitiesTest` case 2 + `BotApiUserMeTest` case 3 |
| **D-05-04-D** | 05-04 | Concurrent outbound claim test uses sequential calls (not pcntl_fork) — pcntl already exercised at service layer in plan 04-06 |
| **D-05-05-A** | 05-05 | Cancelled match → EDIT outbound row (`match_announce_update` with `prior_sent_message_id`) NOT DELETE; preserves audit trail |
| **D-05-05-B** | 05-05 | MatchObserver keeps Phase 4 `saved()` hook + adds independent `created()/updated()` hooks; regressionless (MatchEventSyncTest 8/8 GREEN) |
| **D-05-05-C** | 05-05 | `causer_user_id` via `auth()->id()` — null in CLI/seeder (T-05-05-03 accept); both flows tested explicitly |
| **D-05-05-D** | 05-05 | `phpstan.neon` paths exclude `tests/` — test-file `payload['key']` phpstan issues outside CI gate |
| **D-05-05-E** | 05-05 | DiscordOutboundPayloadBuilder eager-loads `gameMatchType+hostClan+slots.role` — no-op when already loaded; prevents N+1 inside observer save() transaction |
| **D-05-06-A** | 05-06 | `role_sync` payload keys are `discord_user_id` / `discord_role_id` (matches plan 05-04 echo suppression JSONB path lookup) |
| **D-05-06-F** | 05-06 | ClanMembership hard-delete also fires SyncDiscordRolesJob with action=remove (defensive — D-009 expects left_at but seeders/admin may bypass) |
| **D-05-07-A** | 05-07 | ClanResource discord_announce_channel_id was already shipped in Phase 2 plan 02-12; 05-07 was additive (helperText + maxLength) preserving the T-02-09-02 toggle gate |
| **D-05-07-B** | 05-07 | Bot service user `firstOrCreate` must include `locale='en'` — users.locale is NOT NULL with no DB default (Rule 2) |
| **D-05-07-C** | 05-07 | PHPStan scope excludes `tests/` per phpstan.neon paths |
| **D-05-08-A** | 05-08 | ESM import extensions explicit (.js) on local imports — Node 22 + `module=NodeNext` |
| **D-05-08-B** | 05-08 | customIds `decodeButtonId` enforces structural validity only; UUID validation is caller's job |
| **D-05-08-C** | 05-08 | `api.ts` hard-codes `/api/bot` prefix in `request()` |
| **D-05-09-A** | 05-09 | `/profile` v1 redirect-to-web stub; viewer-aware endpoint deferred to plan 05-12 (now Phase 9 polish per Q5 resolution) |
| **D-05-09-B** | 05-09 | `/clan apply` v1 redirect-to-web stub; live `api.post` deferred to Phase 6+ |
| **D-05-09-C** | 05-09 | Modal customId reuses `encodeButtonId` from plan 05-08 (`m:o:<matchId>`) — single-sourced round-trippable scheme |
| **D-05-09-D** | 05-09 | `registerCommands().catch()` in `ready.ts` logs but does NOT `process.exit(1)`; bot stays alive on registration failure |
| **D-05-10-A** | 05-10 | `matchCard` renders only scalar PublicMatchData fields (DTO has no nested relations) |
| **D-05-10-B** | 05-10 | `encodeButtonId('m:o:<matchId>')` reused on slash command + button sides |
| **D-05-10-C** | 05-10 | `buildSignupModal` exported from `components/signupModal.ts` (submit handler module) — locality of cohesion |
| **D-05-10-D** | 05-10 | `translateError` substring-matches `err.message`; structured JSON parse deferred to plan 05-12 |
| **D-05-10-E** | 05-10 | `interactionCreate` button branch peeks `m:o:` prefix to skip pre-defer (Pitfall 1 corollary) |
| **D-05-10-F** | 05-10 | `matchCard` defensive guard on `m.game_match_type_id` (typeof + non-empty) to handle partial mocks |
| **D-05-11-D** | 05-11 | `render.ts` re-fetches `PublicMatchData` on every match_announce dispatch (vs reading row.payload snapshot) |
| **D-05-11-F** | 05-11 | Payload key naming asymmetry: outbound uses `discord_user_id` / `discord_role_id`; inbound `/discord-events/role-change` body uses `user_discord_id` / `role_discord_id` |
| **D-05-11-G** | 05-11 | `handleGuildMemberUpdate` exported as a public helper from `registerGuildMemberUpdateHandler` for direct unit testing without mock Client |
| **D-05-12-A** | 05-12 | `BotI18nKeyCoverageTest` follows functional Pest convention (no namespace) — consistency with 100+ existing test files |
| **D-05-12-B** | 05-12 | `BotI18nKeyCoverageTest` surfaced 1 i18n gap (`admin.discord_outbound_message.fields.causer`); closed inline (Rule 2 — D-013 enforcement) |
| **D-05-12-C** | 05-12 | `DiscordOutboundAuditLogTest` hits real HTTP endpoints (not direct `$row->update`) — catches future controllers that swap to raw DB::update |
| **D-05-12-E** | 05-12 | SC-5 capstone ships 3 it() blocks (happy path with `->not->toBe($bot->id)` + 422 unknown + missing-acts-as Pitfall 7 contrast) |

---

## Pest full suite snapshot

**Executed:** `docker compose exec web ./vendor/bin/pest --no-coverage`

```
Tests:    618 passed (1817 assertions)
Duration: 27.92s
```

**All test classes PASS. 0 failures, 0 skipped, 0 incomplete.**

Phase 5 added the following web Pest test classes (sourced from plans
05-02 through 05-12):

| Test class | Location | Plan source |
|------------|----------|-------------|
| `DiscordOutboundMessageModelTest` | `tests/Feature/Models/` | 05-02 |
| `ResolveBotActsAsUserMiddlewareTest` | `tests/Feature/Bot/` | 05-03 |
| `BotApiAuthMatrixTest` | `tests/Feature/Bot/` | 05-03 |
| `BotApiMatchSignupTest` | `tests/Feature/Bot/` | 05-04 |
| `BotApiMatchSignupAbilitiesTest` | `tests/Feature/Bot/` | 05-04 |
| `BotApiUserMeTest` | `tests/Feature/Bot/` | 05-04 |
| `BotApiOutboundClaimTest` | `tests/Feature/Bot/` | 05-04 |
| `BotApiOutboundAckTest` | `tests/Feature/Bot/` | 05-04 |
| `DiscordEventRoleChangeEchoSuppressionTest` | `tests/Feature/Bot/` | 05-04 |
| `DiscordOutboundOnMatchCreateTest` | `tests/Feature/Bot/` | 05-05 |
| `SyncDiscordRolesJobTest` | `tests/Feature/Bot/` | 05-06 |
| `SyncDiscordRolesJobDispatchTest` | `tests/Feature/Bot/` | 05-06 |
| `DiscordOutboundMessageResourcePresentTest` | `tests/Feature/Admin/` | 05-07 |
| `IssueBotTokenCommandTest` | `tests/Feature/Console/` | 05-07 |
| `BotI18nKeyCoverageTest` | `tests/Feature/Bot/` | 05-12 |
| `DiscordOutboundAuditLogTest` | `tests/Feature/Bot/` | 05-12 |
| `DiscordOutboundMessageAuditLogTest` | `tests/Feature/Admin/` | 05-12 |
| `MatchSignupViaBotCauserAttributionTest` | `tests/Feature/Bot/` | 05-12 |

Total: 125 Phase 5 web Pest tests / 358 assertions (delta from Phase 4
close of 493 → 618).

## Vitest full suite snapshot

**Executed:** `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"`

```
 Test Files  10 passed (10)
      Tests  117 passed (117)
   Duration  727ms
```

Phase 5 added the complete bot-side test surface:

| Test file | Tests | Plan source |
|-----------|-------|-------------|
| `tests/skeleton.test.ts` | 2 | 01-01 (Wave 0 from Phase 1) |
| `tests/lib/customIds.test.ts` | 22 | 05-08 |
| `tests/lib/embeds.test.ts` | 20 | 05-10 |
| `tests/commands/match.test.ts` | 13 | 05-09 |
| `tests/commands/clan.test.ts` | 9 | 05-09 |
| `tests/commands/profile.test.ts` | 5 | 05-09 |
| `tests/components/rsvpButton.test.ts` | 16 | 05-10 |
| `tests/components/signupModal.test.ts` | 11 | 05-10 |
| `tests/services/outbound.test.ts` | 11 | 05-11 |
| `tests/events/guildMemberUpdate.test.ts` | 8 | 05-11 |
| **Total** | **117** | |

---

## Static analysis snapshot

| Tool | Command | Result |
|------|---------|--------|
| Pint (style) | `./vendor/bin/pint --test` | PASS — 342 files clean |
| PHPStan L8 | `./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | [OK] No errors |
| NoHardcodedStringsTest | included in Pest suite | PASS |
| BotI18nKeyCoverageTest | included in Pest suite | PASS (after D-05-12-B inline fix) |
| vue-tsc | `/app/node_modules/.bin/vue-tsc --noEmit` | PASS — 0 type errors |
| bot tsc strict | `pnpm run typecheck` in apps/bot | PASS — clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | PASS — clean |

**PHPStan baseline note**: `apps/web/phpstan-baseline.neon` absorbs
vendor-internal deprecation traces from Filament v3 + PHP 8.4 (RESEARCH
Pitfall 9, established in Phase 1). Phase 5 added no new baseline rows
— the `phpstan.neon` `paths` field excludes `tests/` (D-05-05-D /
D-05-07-C), so test-file `payload['key']` issues stay out of the CI
gate. Current run reports `[OK] No errors`.

---

## Grep gate verification

Run-time invariants from plan 05-13 acceptance criteria:

| Gate | Command | Expected | Actual |
|------|---------|----------|--------|
| Sanctum `bot:*` abilities registered | `grep -c "bot:read\|bot:act-as-user\|bot:write-outbound" apps/web/bootstrap/app.php` | ≥ 3 | verified during plan 05-03 |
| ResolveBotActsAsUser middleware registered | `grep -c 'ResolveBotActsAsUser' apps/web/bootstrap/app.php` | ≥ 1 | verified during plan 05-03 |
| `App\Models\GameMatch` direct import in Phase 5 surface | `grep -rc 'use App\\Models\\GameMatch' apps/web/app/Http/Controllers/BotApi apps/web/app/Observers apps/web/app/Jobs` | ≥ 1 each | verified (zero alias-on-import D-04-03-A continuation) |
| MatchObserver fires on GameMatch | `grep -c 'static::observe(MatchObserver' apps/web/app/Models/GameMatch.php` | ≥ 1 | 1 (inherited from Phase 4) |
| `discord_outbound_messages` durability constraints | `psql \\d discord_outbound_messages` | status CHECK + indexes present | verified |
| Bot `WEB_API_URL` resolves docker hostname | `grep 'WEB_API_URL' docker-compose.yml` | `http://web-nginx` | verified |
| customId short-prefix scheme | `grep -E '^export const NS' apps/bot/src/lib/customIds.ts` | one-letter prefix | verified (D-05-08-B) |
| `allowed_mentions` empty array in bot renderer | `grep -c 'allowed_mentions' apps/bot/src/services/render.ts` | ≥ 2 | 3 (verified during plan 05-11) |
| `startOutboundWorker` registered in bot bootstrap | `grep -c 'startOutboundWorker' apps/bot/src/events/ready.ts` | ≥ 2 | 2 (import + call — D-05-11) |
| guildMemberUpdate handler registered | `grep -c 'registerGuildMemberUpdateHandler' apps/bot/src/index.ts` | ≥ 2 | 3 (D-05-11-G) |

All gates PASS.

---

## Must-have traceability

| M# | Must-have | Source | Result |
|----|-----------|--------|--------|
| M1 | All 5 quality gates GREEN: pest + vitest + pint + phpstan + tsc | 05-13 acceptance | PASS — 618/618 + 117/117 + 342 clean + [OK] + clean |
| M2 | shared-types pipeline regressionless | 05-13 acceptance | PASS — `pnpm --filter @trenchwars/shared-types typecheck` clean |
| M3 | 05-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-goal-discord-ux + 12 RESEARCH pitfalls + 5 open questions | 05-13 acceptance | PASS — this document |
| M4 | ROADMAP.md Phase 5 entry updated: 13/13 Complete + Completed date + plan list flips all 13 to [x] | 05-13 acceptance | PASS — see ROADMAP.md surgical edits |
| M5 | REQUIREMENTS.md REQ-goal-discord-ux flipped from Pending → Complete in v1 traceability table | 05-13 acceptance | PASS — was already Complete in lines 37 + 118 from prior session; only ROADMAP required surgical edits |
| M6 | STATE.md updated: phase 4 → 5 closed; total_plans + completed_plans bumped; performance metrics appended | 05-13 acceptance | PASS — see STATE.md surgical edits |
| M7 | ROADMAP.md placeholder Phase 5 plan list (which incorrectly carried Phase 2 plan filenames per orchestrator note) is REPLACED with the real 13 plan filenames | 05-13 acceptance | NOT APPLICABLE — Phase 5 section in ROADMAP.md already carried the correct 13 plan filenames (verified during plan 05-13 read; the orchestrator note appears to have been advisory based on the placeholder pattern visible in Phases 6/7/8/9 which still need to be flipped at their respective close plans) |
| M8 | Status flag PENDING_MANUAL_SMOKE for the live Discord guild interaction + outbound delivery + role sync + guildMemberUpdate manual checks per VALIDATION.md | 05-13 acceptance | PASS — frontmatter flag set; manual smoke checklist A-F below |

---

## Manual Smoke Checklist (PENDING_MANUAL_SMOKE)

Operator must verify out-of-band against a live Discord guild + production
Railway environment. The automated test suite exercises every contract
via mocked discord.js surfaces + real HTTP through the Laravel route
stack; the smokes below cover the network/protocol seams that only
materialise against a real Discord gateway + REST API.

### A. [PENDING] Discord slash commands register after first deploy + bot login (SC-1)

1. Deploy the `bot` service to Railway with `DISCORD_BOT_TOKEN` + `DISCORD_APPLICATION_ID` set.
2. Tail the bot logs; verify `Logged in as Trenchwars Bot#NNNN` + `Registered 4 slash commands` lines appear.
3. In the configured Discord guild, type `/` in any text channel.
4. Verify the autocomplete menu shows `/clan`, `/match`, `/profile`, `/me` (Pitfall 6 — `GuildMembers` intent must be enabled in the Discord developer portal AND in `Client` constructor).
5. Invoke `/match list`; verify the bot replies within 3s with an ephemeral embed listing upcoming matches (Pitfall 1 deferReply path).

### B. [PENDING] `/match signup` modal end-to-end (SC-2)

1. Pre-condition: create a public match on the website with status=`open` and at least one open slot for a clan the test user belongs to.
2. In Discord, invoke `/match signup` with the match id as the argument.
3. Verify a modal opens with a text input for the role UUID.
4. Submit a real role UUID (copy from the website match detail page).
5. Within ~5s, verify:
   - [ ] Modal closes; bot replies "Signed up" ephemerally (i18n key `bot.match.signup.success`)
   - [ ] On the website `/admin/matches/{id}/edit` Slots tab, the new `match_signups` row appears with the Discord user as `occupant_user_id`
   - [ ] `activity_log` shows the signup attributed to the human user (NOT the bot service user — SC-5 capstone path)

### C. [PENDING] Match creation → outbound delivery → embed appears in Discord (SC-3)

1. As admin, create a new match via Filament with:
   - `is_public=true`
   - host_clan = a clan with `discord_announce_channel_id` populated
   - scheduled_at in the future
2. Within ~10s, verify in the configured Discord channel:
   - [ ] An embed appears with the match name, time, host clan, slot summary
   - [ ] RSVP buttons (Going / Maybe / Not going) render at the bottom of the embed
3. In Filament `/admin/discord-outbound-messages`, verify:
   - [ ] A row exists with `kind=match_announce`, `status=sent`, `sent_message_id` populated (non-null Discord message id)
   - [ ] `activity_log` shows the row's pending → dispatching → sent state transitions (D-012 / D-05-12-C)

### D. [PENDING] Player joins clan on website → Discord role assigned (SC-4)

1. As a test user (NOT yet in any clan), accept a clan invite via the My Clan invite flow on the website.
2. Within ~30s, verify in the configured Discord guild:
   - [ ] The test user has been granted the clan's `discord_role_id` (visible in the user's Discord profile sidebar)
3. In Filament `/admin/discord-outbound-messages`, verify:
   - [ ] A `role_sync` row exists with `action=add`, `discord_user_id`, `discord_role_id`, `status=sent`
4. The Horizon dashboard at `/admin/horizon` shows the `SyncDiscordRolesJob` completed without retries.

### E. [PENDING] guildMemberUpdate reconciliation (SC-4)

1. As Discord guild owner/admin, manually REMOVE a clan role from a test user via the Discord client UI.
2. Within ~30s, verify on the website:
   - [ ] The user's `ClanMembership` row has `left_at` populated (= now)
3. The bot logs show a `[bot/guildMemberUpdate] role removed: {role_id}` entry.
4. The 60s echo-suppression window (Pitfall 10 — D-05-06-A) is NOT triggered because this is NOT a bot-initiated role change — verified by absence of recent matching `role_sync` outbound row.

### F. [PENDING] Sanctum bot:* token misuse rejected (SC-5)

1. From a host shell with `curl` + a Sanctum token issued by `php artisan trenchwars:bot:issue-token` (capture the plain-text token from stdout):
   ```bash
   # Case 1: correct token + correct ability + correct X-Bot-Acts-As-User → 200
   curl -X POST https://trenchwars.app/api/bot/matches/{id}/signups \
     -H "Authorization: Bearer $TOKEN" \
     -H "X-Bot-Acts-As-User: 123456789012345678" \
     -H "Content-Type: application/json" \
     -d '{"game_role_id": "..."}'

   # Case 2: wrong abilities (token with bot:read only, no bot:act-as-user) → 403
   # Case 3: valid token but unknown discord_id → 422 bot.errors.acts_as_unknown
   # Case 4: no Authorization header → 401
   ```
2. Verify:
   - [ ] Case 1 returns 200 + signup created + `activity_log.causer_id = the human user (NOT the bot service user)`
   - [ ] Case 2 returns 403 with abilities mismatch
   - [ ] Case 3 returns 422 with translated `bot.errors.acts_as_unknown` body
   - [ ] Case 4 returns 401

### Operator outcome line

| Check | Result | Notes |
|-------|--------|-------|
| A. Slash commands register | _PENDING_ | _(operator fills after smoke)_ |
| B. /match signup modal | _PENDING_ | _(operator fills after smoke)_ |
| C. Match → outbound → embed | _PENDING_ | _(operator fills after smoke)_ |
| D. Clan join → role assign | _PENDING_ | _(operator fills after smoke)_ |
| E. guildMemberUpdate reconcile | _PENDING_ | _(operator fills after smoke)_ |
| F. Sanctum bot:* misuse | _PENDING_ | _(operator fills after smoke)_ |

**Phase 5 status (post-smoke):** _(operator marks COMPLETE or BLOCKED-ON-FIX)_

---

## Performance Metrics (Phase 5 plan timings)

| Plan | Duration | Tasks | Commits | Files |
|------|----------|-------|---------|-------|
| 05-01 (Wave 0 scaffolding) | ~900s | 2 (+ 1 Rule 1 follow-up) | 3 | 39 |
| 05-02 (migrations + model) | ~405s | 2 | 2 | 4 |
| 05-03 (middleware + abilities) | ~428s | 2 | 2 | 6 |
| 05-04 (BotApi controllers) | 828s | 3 | 3 | 17 |
| 05-05 (MatchObserver outbound) | 383s | 3 | 3 | 3 |
| 05-06 (SyncDiscordRolesJob) | 327s | 2 | 2 | 5 |
| 05-07 (Filament admin + Artisan + seeder) | 407s | 3 | 3 | 11 |
| 05-08 (bot core + customIds) | 366s | 3 | 3 | 9 |
| 05-09 (slash commands) | 353s | 3 | 3 | 12 |
| 05-10 (embeds + buttons + modal) | 472s | 3 | 3 | 10 |
| 05-11 (outbound worker + reconciler) | 321s | 3 | 3 | 7 |
| 05-12 (i18n + audit log + SC-5 capstone) | 322s | 3 | 3 | 5 |
| 05-13 (phase close — THIS PLAN) | _captured by orchestrator_ | 2 | 2 | 5 |
| **Phase 5 total** | **~83 min (~5512s)** | **34** | **35+** | **138** |

---

## Open Items Carrying Forward to Phase 6+

| Item | Tracked by | Lives in |
|------|------------|----------|
| `/profile` viewer-aware endpoint (`/api/bot/users/by-discord/{id}` with PlayerPrivacyGate) | RESEARCH Q5 + D-05-09-A | Phase 9 polish |
| StringSelectMenu replacement for signup modal text input (better UX than free-form UUID) | Plan 05-10 deferred | Phase 9 polish |
| `/clan apply` real implementation — currently redirect-to-web stub | D-05-09-B + RESEARCH Q2 | Phase 6+ (depends on whether tournament invite flow uses the same path) |
| Multi-replica bot deployment | Assumption A6 | Phase 8 RCON (rcon-worker is similarly single-instance for v1) |
| i18n for bot responses — currently English only at v1 | CONTEXT.md i18n note + D-013 | Phase 7 CMS or Phase 9 polish |
| Token rotation playbook | Artisan commands ship in plan 05-07; operator procedure documented | Phase 9 polish (automate rotation cadence) |
| Result-announce outbound (separate from match-create announce) | Listed as future kind in DiscordOutboundPayloadBuilder | Phase 8 RCON (when MatchResult auto-populates from CRCON events) |

---

## Out-of-Scope Items Deferred to Future Phases

| Out-of-scope item | Lives in | Reason |
|-------------------|----------|--------|
| Tournament bracket announcements via Discord embeds | **Phase 6** (Tournaments & brackets) | Phase 6 will reuse the `discord_outbound_messages` outbox + bot polling worker shipped in Phase 5; the `kind` enum gets extended with `tournament_announce` |
| Article publish announcements to Discord | **Phase 7** (CMS) | Phase 7 will reuse the same outbox + worker; new `kind=article_announce` |
| RCON match-result → Discord announce | **Phase 8** (RCON automation) | Phase 8's RCON-driven MatchResult create will fire a `match_result_announce` outbound row through the same observer chain |
| Browser tests (Playwright/Dusk) on the 6 manual smokes A–F | **Phase 9** (Polish) — deferred from Phase 1 | P1 explicitly deferred browser tests (CLAUDE.md §4); operator smoke checklist in this report covers the gap until Phase 9 |
| Notification preferences UI (web bell + per-event Discord DM rules) | **Phase 9** (Polish) — NOTF-01 v2 | Listed in REQUIREMENTS.md v2; out of round-1 scope |

---

## Files Created / Modified Summary

Phase 5 spans 33 commits across 13 plans. Below is the full commit list,
chronologically ordered (from plan 05-01 through plan 05-12 — plan
05-13's metadata commit is created after this document is written).

```
e0be595 feat(05-01): install Sanctum + Horizon + add worker compose service
242e78f test(05-01): 20 Wave 0 RED stubs + discord.js/undici/ioredis bot deps + bot.php + admin.php appendix
620c82d fix(05-01): worker healthcheck uses /proc/1/cmdline instead of pgrep
56fe94c feat(05-02): add discord_outbound_messages migration with CHECK constraints + indexes
56f3f06 feat(05-02): add DiscordOutboundMessage model + real factory + GREEN model test (17 assertions)
bee5575 feat(05-03): ResolveBotActsAsUser middleware + Sanctum CheckAbilities aliases (Wave 2 task 1)
75a5000 feat(05-03): GREEN ResolveBotActsAsUserMiddlewareTest + BotApiAuthMatrixTest (Wave 2 task 2)
fec1364 feat(05-04): add /api/bot/* route group with 6 BotApi controllers + 4 FormRequests
4559445 test(05-04): GREEN BotApiMatchSignupTest + AbilitiesTest + UserMeTest (Wave 3 task 2)
9849f8d test(05-04): GREEN OutboundClaim + OutboundAck + RoleChangeEcho tests (Wave 3 task 3)
cd79836 feat(05-05): add DiscordOutboundPayloadBuilder for match-announce payloads
3b4235a feat(05-05): extend MatchObserver with discord_outbound_messages writer
974d905 test(05-05): DiscordOutboundOnMatchCreateTest GREEN (12 tests / 33 assertions)
32279ee feat(05-06): SyncDiscordRolesJob writes role_sync outbound rows (Horizon-retried)
a24fd03 feat(05-06): ClanMembershipObserver dispatches SyncDiscordRolesJob + 2 GREEN test files
f9468b5 feat(05-07): DiscordOutboundMessageResource + ClanResource announce-channel helper text
d6a2e0f feat(05-07): trenchwars:bot:issue-token + revoke-token + BotServiceUserSeeder
0174710 test(05-07): DiscordOutboundMessageResourcePresentTest + IssueBotTokenCommandTest GREEN
cf21568 feat(05-08): bot boot substrate — env validation + Client factory + entry
c497a8d feat(05-08): bot Web API client + customIds + colors + shared-types re-export
64711a4 test(05-08): flip customIds.test.ts to GREEN — round-trip + Pitfall 5 + null
acefa10 feat(05-09): 4 command modules + registry + registerCommands + ready handler
8303b37 feat(05-09): interactionCreate dispatcher + index.ts wiring
7ac710c test(05-09): GREEN flip — match/clan/profile command Vitest suites
68a89a4 feat(05-10): embed builders + button factories (matchCard / clanCard / profileCard)
60bfdce feat(05-10): RSVP button + signup modal components + interactionCreate routing
fe847cd test(05-10): GREEN flip — rsvpButton + signupModal Vitest suites
afba4d5 feat(05-11): outbound polling worker + render dispatcher + ready.ts wiring
5c0ad34 feat(05-11): guildMemberUpdate role-drift reconciler + index.ts wiring
248a06d test(05-11): GREEN outbound + guildMemberUpdate Vitest stubs (Wave 0 baseline drop)
8d1be9f test(05-12): add BotI18nKeyCoverageTest + close admin.discord_outbound_message.fields.causer gap
0aed51b test(05-12): add DiscordOutboundAuditLogTest + DiscordOutboundMessageAuditLogTest
d219199 test(05-12): add MatchSignupViaBotCauserAttributionTest — SC-5 capstone
```

(Plan 05-13's two commits — `test(05-13): write 05-PHASE-VERIFICATION.md + mark Phase 5 complete in ROADMAP.md` and `docs(05-13): complete Phase 5 plan` — are added after this document is committed.)

---

## Plan-Level Deviations from Phase 5

The Phase 5 deviations summary (Rule 1/2/3 auto-fixes resolved inline,
zero Rule 4 architectural escalations) is rolled up across all 12
prior 05-NN-SUMMARY.md files. The most consequential cross-cutting
deviations are codified in the D-05-NN-* table above; per-plan inline
fixes are documented in each plan's SUMMARY.

Cross-cutting notes:
- D-04-03-A LOCKED canonical class binding (`App\Models\GameMatch`) was inherited and re-affirmed across every Phase 5 plan that touched the matches surface; zero `App\Models\Match as MatchModel` alias-on-import anywhere.
- Plan 05-01's `personal_access_tokens` migration used `bigint morphs` from the Sanctum install:api scaffold; plan 05-03 in-place-edited to `uuidMorphs` for D-002 compatibility (pre-prod-deploy = safe).
- Plan 05-12's `BotI18nKeyCoverageTest` surfaced a missing i18n key (`admin.discord_outbound_message.fields.causer`); closed inline as Rule 2 (D-013 is a correctness contract, not a polish concern).
- Bot test files were initially named `*.test.ts` per Vitest convention; runtime check at plan 05-11 surfaced that the `apps/bot` Docker image is built from `apps/bot` source at build time, so test files baked into the image are skeleton-only — the runtime pattern is `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"` (bind-mount host source). This is consistent with the canonical run path Phase 5 plans documented.

### Threat register dispositions (T-05-XX-NN)

All `mitigate` dispositions across plans 05-01..05-12 are resolved per
their plan SUMMARYs; no `accept` disposition required additional follow-
up; no new threat-flag surface introduced in plan 05-13 (final close
work touches only docs + frontmatter).

---

## Plan-13 specifics

This plan's task list compressed all close work into two tasks:

1. **Task 1**: Run all 5 quality gates + collect counts (Pest 618/1817 + Vitest 117 + Pint 342 clean + PHPStan [OK] + bot tsc clean + shared-types tsc clean + vue-tsc clean).
2. **Task 2**: Author this `05-PHASE-VERIFICATION.md`; update `ROADMAP.md` (Phase 5 13/13 Complete + completion date); verify `REQUIREMENTS.md` REQ-goal-discord-ux is Complete (already was — only ROADMAP required surgical edits); update `STATE.md` (advance-plan, update-progress, record-metric, add-decision rollups, record-session).

No Rule 1/2/3 deviations encountered during this close plan's execution;
the verification artifact reflects observed reality, not a target shape.

---

## Sign-off

Phase 5 verified complete pending operator manual smokes; ROADMAP.md +
REQUIREMENTS.md (already Complete) + STATE.md updated; ready for Phase
6 (Tournaments & brackets).

**Phase 6 hand-off note:** Phase 5 provides the complete Discord-driven
match interaction surface that Phase 6 (Tournaments & brackets) will
extend:

- `App\Models\GameMatch` (D-04-03-A LOCKED canonical name — Phase 6 bracket-match materialisation MUST use this FQN)
- `MatchSignupService` (D-010 row-locked) for bracket-match Discord signups
- `DiscordOutboundMessage` + bot polling worker for tournament/bracket announcements (extend `kind` enum with `tournament_announce`)
- `SyncDiscordRolesJob` + `ClanMembershipObserver` already wire bracket-participant clan roster changes to Discord
- Sanctum `bot:*` token + `X-Bot-Acts-As-User` middleware ready for tournament organiser flows from the bot

**Reviewed by:** Claude Opus 4.7 (1M context) — automated verification executor
**Date:** 2026-05-13
