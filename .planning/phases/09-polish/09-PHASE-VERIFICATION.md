---
phase: 09-polish
slug: polish
status: PENDING_MANUAL_SMOKE
completed: 2026-05-15
plans_complete: 12
plans_total: 12
test_count: 1303
test_assertions: 4546
test_passing: 1303
test_failing: 0
test_incomplete: 0
bot_test_count: 139
bot_test_files: 11
rcon_worker_test_count: 40
rcon_worker_test_files: 7
quality_gates:
  pest: GREEN
  pint: GREEN
  phpstan_l8: GREEN
  web_build: GREEN
  bot_build: GREEN
  rcon_worker_build: GREEN
  axe_core_ci_present: GREEN
requirements: []
manual_smoke_required:
  - A — axe-core CI workflow first canonical run on push to master (verify report artifact; admin/auth excluded per Pitfall 11)
  - B — Manual keyboard nav 10-step checklist (plan 09-10 Task 2 PENDING; operator out-of-band walkthrough — recorded in 09-10-SUMMARY)
  - C — Rate-limit boundary smoke (plan 09-11 Task 3 PENDING; operator out-of-band walkthrough — recorded in 09-11-SUMMARY)
  - D — Notifications bell + Discord DM live receipt (full bot+web stack with real Discord guild)
canonical_model_binding: "App\\Models\\GameMatch (D-04-03-A LOCKED — inherited and re-affirmed across all 12 Phase 9 plans; LeaderboardService::topPlayers/topClans + MatchPlayerStatObserver + MatchResultObserver + DisputeService + BanService + ScrimE2E continuations all import App\\Models\\GameMatch directly; BelongsTo<GameMatch, $this> passes match_id as explicit FK arg per D-04-03-B / D-06-03-A / D-07-* / D-08-* continuation)"
---

# Phase 9 — Polish — Verification Report

**Date:** 2026-05-15
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual Smoke section)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 9 |
| Name | Polish |
| Slug | polish |
| Plans | 12 plans (09-01 through 09-12) |
| Completed date | 2026-05-15 |
| Phase 8 foundation | Phase 8 COMPLETE PENDING_MANUAL_SMOKE (2026-05-14) |
| Canonical model name | `App\Models\GameMatch` (D-04-03-A LOCKED — see frontmatter) |
| Requirements satisfied | (no v1 requirements were Phase 9-mapped; Phase 9 is the buffer/polish milestone consuming open backlog from prior phases) |

---

## Status

PENDING_MANUAL_SMOKE — 4 operator walkthrough items remaining (see Manual Smoke section).

The automated test surface mechanically proves SC-1 through SC-5 via the
Pest + Vitest matrix below. The four manual smokes cover the
operator/network seams that the test surface intentionally does not
exercise (axe-core CI canonical first run, real-keyboard navigation
across every public flow, real-IP rate-limit boundary observation,
and Discord-DM end-to-end delivery against a live bot + web stack).

---

## Overview

Phase 9 delivered the round-1 polish surface — five new lang/en
namespace files (`notifications.php`, `leaderboards.php`,
`moderation.php`, `a11y.php`, `reports.php`), six new DB tables
(`notifications`, `user_notification_preferences`, `bans`,
`match_disputes`, `abuse_reports`, plus `discord_outbound_messages.message_type`
CHECK extension to `user_dm` and a kills index on `match_player_stats`),
the canonical NotificationDispatcher service with idempotency contract
(`onOneServer + withoutOverlapping + alreadyDispatched`), five
Notification classes (`MatchStartingSoon`, `MatchCancelled`,
`MatchResultPublished`, `ClanInviteReceived`, `ClanApplicationDecided`)
each emitting `database` + `discord-channel` channels per the
User::enabledNotificationChannels matrix (with `match_result_published`
Discord default-off per OQ3 LOCKED), the bespoke `DiscordChannel`
(D-004 outbox writer — no direct HTTP — appends a
`discord_outbound_messages` row with `message_type='user_dm'` JSONB
payload only), the `NotificationDispatcher` with two Artisan commands
(`notifications:dispatch-upcoming` 5-min schedule + `notifications:prune`
daily 90-day retention), four observer extensions/creations
(`MatchObserver` cancelled→fan-out, `MatchResultObserver` published→fan-out,
`ClanApplicationObserver` decided→target invitee, `ClanInviteObserver`
created→target invitee), the `LeaderboardService` with
`Cache::tags(['leaderboards','lb:players:{window}'])->flexible([600,3600])`
swR + `topPlayers` / `topClans` per window (`7d/30d/all`) + D-018
`PlayerPrivacyGate` enforcement at `LeaderboardEntryData::fromQueryResult`,
the `MatchResultObserver` + `MatchPlayerStatObserver` cache-flush hooks
(invalidate `leaderboards` tag on stat/result mutations), the Inertia
shared `unread_notifications_count` prop (lazy closure), three new
controllers (`NotificationsController`, `LeaderboardsController`,
`NotificationPreferencesController`) + new `Reports/ReportsController`,
three new public Vue pages (`Notifications/Index`, `Leaderboards/Index`,
`Report/Create`) + `Account/NotificationPreferences` + three components
(`NotificationsBell`, `LeaderboardTable`, `ReportButton`), `BanService`
with audit log (site-wide v1 per OQ4 LOCKED) + `DisputeService` open→
under_review→{resolved|rejected} state machine, the `ModeratorRoleSeeder`
(5 perms idempotently seeded) + `UserResource` BanBulkAction +
`MatchResource` CancelBulkAction + `MatchDisputeResource` + `BansRelationManager`
+ `AbuseReportResource` (single moderator panel per OQ5 LOCKED),
`Model::shouldBeStrict(! isProduction())` in `AppServiceProvider` + N+1 sweep
across 6 app paths + 12 test-side `->load(...)` patches, `LeaderboardsQueryBudgetTest`
(<=4 warm budget) + `ClansQueryBudgetTest` (<=8 budget) + `CacheTagFlushTest`,
WebP conversions on `Clan` (logo) + `Player` (avatar) + `Article` (cover)
via spatie/medialibrary `->queued()` (Horizon-deferred) + `MediaRegenerateWebpCommand`
backfill (`trenchwars:media:regenerate-webp`) per OQ6 LOCKED, three Vue
WebP wrapper components (`ClanLogo`, `PlayerAvatar`, `ArticleCover` —
`<picture>` element with `<source srcset>` of `.webp`), site-wide
`*:focus-visible` CSS + `button/a/[role=button]` `color-mix` outer ring
on `var(--color-focus-ring)`, `.github/workflows/a11y.yml` axe-core@^4.11.3
CI workflow with 7-URL public route matrix (`/, /clans, /matches,
/tournaments, /blog, /events, /leaderboards`) — admin/auth routes
excluded per Pitfall 11, `PublicPagesHtmlLangTest` + `VueFormLabelsTest`
static-scan GREEN, four new RateLimiters (`public-api 30/min`,
`auth 10/min`, `notifications-read 120/min`, `report-abuse 5/hr`) +
`StoreAbuseReportRequest` + `ReportButton` triggering
`/reports/abuse/create` → `ReportsController::store` → `abuse_reports`
row + (optional linked Ban) state machine `pending → dismissed | actioned`,
the `Phase9I18nKeyCoverageTest` two-it() coverage gate, and the
ROADMAP/REQUIREMENTS/STATE close-out.

All five ROADMAP Success Criteria are mechanically observable against
concrete test files and source artifacts; Phase 9 has no v1
requirement flips (round 1's requirements were consumed by Phases 1-8).

---

## [BLOCKING] Quality Gates — RESULT: PASS

| Gate | Command | Result |
|------|---------|--------|
| Pest (web full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **1303 passed** (4546 assertions), 0 failed, 0 incomplete, 84.54s |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 651 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| pnpm build (web — vite + filament theme) | `docker compose exec web pnpm run build` | **PASS** — vite app built (5.32s) + filament theme rebuilt (655ms) |
| pnpm build (@trenchwars/bot — tsc) | `docker compose run --rm worker pnpm run build` (in `/repo/apps/bot`) | **PASS** — `tsc` emit clean |
| pnpm build (@trenchwars/rcon-worker — tsc) | `docker compose run --rm worker pnpm run build` (in `/repo/apps/rcon-worker`) | **PASS** — `tsc` emit clean |
| axe-core CI workflow present | `.github/workflows/a11y.yml` | **PASS** — present (3724 bytes; canonical first run is on next push — see Manual Smoke A) |

**Test growth across phases:**

| Phase | Total Pest after phase | Phase contribution |
|-------|------------------------|--------------------|
| Phase 1 close (01-18) | ~94 tests | +94 |
| Phase 2 close (02-14) | 214 tests | +120 |
| Phase 3 close (03-10) | 278 tests | +64 |
| Phase 4 close (04-13) | 493 tests | +215 |
| Phase 5 close (05-13) | 618 tests | +125 (+117 bot Vitest) |
| Phase 6 close (06-14) | 866 tests | +248 web (+22 bot Vitest) |
| Phase 7 close (07-13) | 1037 tests | +171 web (+752 assertions; bot regressionless) |
| Phase 8 close (08-13) | 1134 tests | +97 web (+312 assertions; bot regressionless; +40 rcon-worker Vitest) |
| **Phase 9 close (09-12)** | **1303 tests** | **+169 web** (+763 assertions; bot regressionless; rcon-worker regressionless) |

Phase 9 contributed 169 web Pest tests (delta 1134 → 1303 / +763
assertions from 3783 → 4546) across the
`Tests\Feature\Notifications\*`, `Tests\Feature\Leaderboards\*`,
`Tests\Feature\Admin\{UserResourceBanBulkAction,MatchResourceBulkCancel,MatchDisputeWorkflow,ModeratorPermissionGate,ModeratorAuditLog,AbuseReportWorkflow}Test`,
`Tests\Feature\A11y\{PublicPagesHtmlLang,VueFormLabels}Test`,
`Tests\Feature\Reports\{ReportAbuse,ReportAbuseThrottle}Test`,
`Tests\Feature\Security\PublicApiThrottleTest`,
`Tests\Feature\Media\{ClanLogoWebpConversion,ArticleCoverWebpConversion}Test`,
`Tests\Feature\Performance\{LeaderboardsQueryBudget,ClansQueryBudget}Test`,
`Tests\Feature\Cache\CacheTagFlushTest`,
`Tests\Feature\I18n\Phase9I18nKeyCoverageTest`,
`Tests\Unit\{AppServiceProviderStrictMode,RateLimiterDefinitions,UserNotificationPreferences}Test`,
`Tests\Unit\Notifications\MatchStartingSoonNotificationTest`
namespaces. The bot test surface is unchanged (139 / 11 files) and the
rcon-worker test surface is unchanged (40 / 7 files) — Phase 9
introduces no new bot interactions and no new RCON normalisation paths.

---

## ROADMAP Success Criteria mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 | A logged-in user has a notifications hub (web bell + Discord DM rules) with at least default sensible rules wired (match starting in 1h/15m, match cancelled, result published). | `apps/web/tests/Feature/Notifications/NotificationsBellTest.php` (plan 09-06 — bell renders shared `unread_notifications_count` prop; SVG indicator + ARIA `aria_open`/`aria_close` keys present; mark_read CTA fires `notifications.cta.mark_read`), `apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php` (plan 09-03 — `toDatabase` payload shape + `via([$user])` returns `['database','discord-channel']` per `User::enabledNotificationChannels` matrix), `apps/web/tests/Feature/Notifications/NotificationDispatcherTest.php` (plan 09-04 — `dispatch-upcoming` fans out matches starting in 1h + 15m windows; emits one Notification per (user_id, match_id, kind) tuple), `apps/web/tests/Feature/Notifications/NotificationDispatcherIdempotencyTest.php` (plan 09-04 — `alreadyDispatched` predicate prevents duplicate fan-out when scheduler re-fires within the same window — Pitfall 5 mitigation), `apps/web/tests/Unit/UserNotificationPreferencesTest.php` (plan 09-03 — 5×2 matrix editor accessors; `match_result_published` Discord default-off per OQ3 LOCKED), `apps/web/tests/Feature/Notifications/DiscordChannelOutboxTest.php` (plan 09-03 — `DiscordChannel::send` writes one `discord_outbound_messages` row with `message_type='user_dm'`; Http::assertNothingSent verifies no direct webhook call — Pitfall 3 mitigation); manual smoke D documented below | **PARTIAL — automated GREEN; live Discord DM receipt pending operator smoke** |
| SC-2 | Leaderboards render top clans and top players by stat windows, derived from MatchPlayerStat aggregates. | `apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopPlayersTest.php` (plan 09-05 — `topPlayers(window, limit)` returns ranked `LeaderboardEntryData` collection; `7d/30d/all` windows honoured; ties broken by deterministic ordering), `apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopClansTest.php` (plan 09-05 — `topClans(window, limit)` aggregates MatchResult.winning_clan_id per window; ties broken deterministically), `apps/web/tests/Feature/Leaderboards/LeaderboardCacheTest.php` (plan 09-05 — `Cache::tags(['leaderboards','lb:players:{window}'])->flexible([600,3600])` swR; cold miss → query, warm hit → no query), `apps/web/tests/Feature/Leaderboards/LeaderboardPrivacyTest.php` (plan 09-05 — D-018 `PlayerPrivacyGate` enforced at `LeaderboardEntryData::fromQueryResult`; opted-out players surface as `null` slot but still occupy rank), `apps/web/tests/Feature/Cache/CacheTagFlushTest.php` (plan 09-05 — `MatchResultObserver::saved` + `MatchPlayerStatObserver::saved` flush `leaderboards` tag), `apps/web/tests/Feature/Performance/LeaderboardsQueryBudgetTest.php` (plan 09-08 — cold ≤6 deviation, warm/empty ≤4 — proves N+1 absence) | **PASS** |
| SC-3 | Moderators have bulk actions, ban/suspend tooling, and a dispute resolution workflow for match results in Filament — all audited. | `apps/web/tests/Feature/Admin/UserResourceBanBulkActionTest.php` (plan 09-07 — `BanBulkAction` requires modal reason + minLength; `BanService::ban` writes `bans` row + `activity_log` row; spatie permission gate `ban_user` enforced), `apps/web/tests/Feature/Admin/MatchResourceBulkCancelTest.php` (plan 09-07 — `CancelBulkAction` flips matches to cancelled + writes `activity_log` row + triggers `MatchObserver` notifications fan-out), `apps/web/tests/Feature/Admin/MatchDisputeWorkflowTest.php` (plan 09-07 — `open → under_review → {resolved,rejected}` state machine; `DisputeService::transition` audit row written; Pitfall 8 BulkAction modal silent-close mitigated via required+minLength), `apps/web/tests/Feature/Admin/AbuseReportWorkflowTest.php` (plan 09-11 — `pending → dismissed | actioned` state machine; actioned can optionally link a `Ban` row via the resource's action gate; `manage_abuse_reports` permission enforced), `apps/web/tests/Feature/Admin/ModeratorPermissionGateTest.php` (plan 09-07 — every BulkAction `->visible()` gate respects spatie moderator role; non-mods cannot trigger), `apps/web/tests/Feature/Admin/ModeratorAuditLogTest.php` (plan 09-07 — every moderator action surfaces in `activity_log` with subject + causer + properties JSONB) | **PASS** |
| SC-4 | A performance pass has eliminated obvious N+1s, applied a documented cache-key strategy, and image variants serve as WebP at appropriate sizes; pages on the round-1 public surface render in target time budgets. | `apps/web/tests/Unit/AppServiceProviderStrictModeTest.php` (plan 09-08 — `Model::shouldBeStrict(! isProduction())` flips on in non-prod; Pitfall 2 mitigated by 12 test-side `->load(...)` patches + 6 app-side eager-load fixes), `apps/web/tests/Feature/Performance/LeaderboardsQueryBudgetTest.php` (plan 09-08 — 5 cases: ≤6 cold deviation, ≤4 warm/empty), `apps/web/tests/Feature/Performance/ClansQueryBudgetTest.php` (plan 09-08 — 4 cases: ≤8 budget for `/clans` directory), `apps/web/tests/Feature/Cache/CacheTagFlushTest.php` (plan 09-05 — tagged invalidation contract), `apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php` (plan 09-09 — 4 cases: `logo_webp_thumb/_md/_lg` conversions registered + `->queued()` Horizon-deferred + WebP MIME after Horizon drain), `apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php` (plan 09-09 — 5 cases: cover_webp_* conversions + `MediaRegenerateWebpCommand` re-emits without overwriting source; cache-key strategy documented at `.planning/phases/09-polish/CACHE-STRATEGY.md`) | **PASS** |
| SC-5 | An accessibility pass has verified AA contrast on both themes, keyboard-only navigation through every public flow, and visible focus rings; rate-limit and abuse-vector hardening pass is documented. | `apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php` (plan 09-10 — 7 cases: every public page renders `<html lang="en">` correctly; Inertia SSR + Vue mount keep the attribute in sync), `apps/web/tests/Feature/A11y/VueFormLabelsTest.php` (plan 09-10 — static-scan: every `<input>/<select>/<textarea>` has an associated `<label for="...">` or `aria-label`; 0 violations), `apps/web/tests/Unit/RateLimiterDefinitionsTest.php` (plan 09-11 — 4 RateLimiters registered: `public-api 30/min`, `auth 10/min`, `notifications-read 120/min`, `report-abuse 5/hr`), `apps/web/tests/Feature/Security/PublicApiThrottleTest.php` (plan 09-11 — `throttle:public-api` boundary case asserts 31st request in window returns 429), `apps/web/tests/Feature/Reports/ReportAbuseTest.php` (plan 09-11 — `ReportButton` → `POST /reports/abuse` → `abuse_reports` row + `target_type` polymorphic linkage), `apps/web/tests/Feature/Reports/ReportAbuseThrottleTest.php` (plan 09-11 — `throttle:report-abuse` 6th request inside 1h returns 429 + i18n error key resolves), `apps/web/tests/Feature/I18n/Phase9I18nKeyCoverageTest.php` (plan 09-12 — both expected-key + source-grep round-trip GREEN for notifications.*, leaderboards.*, moderation.*, a11y.*, reports.* namespaces); `.github/workflows/a11y.yml` axe-core CI public-route matrix verified present; manual smoke A+B+C documented below | **PARTIAL — automated GREEN; axe-core CI canonical first run + manual keyboard nav + rate-limit boundary smoke pending operator** |

**SC verification commands:**

```bash
# SC-1: Notifications hub (bell + dispatcher + idempotency + preferences + DiscordChannel outbox)
docker compose exec web ./vendor/bin/pest --filter='NotificationsBell|MatchStartingSoonNotification|NotificationDispatcher|UserNotificationPreferences|DiscordChannelOutbox' --no-coverage

# SC-2: Leaderboards (top players/clans + cache + privacy + cache flush + query budget)
docker compose exec web ./vendor/bin/pest --filter='LeaderboardService|LeaderboardCache|LeaderboardPrivacy|CacheTagFlush|LeaderboardsQueryBudget' --no-coverage

# SC-3: Moderator tooling (ban + cancel + dispute + abuse-report + permission gate + audit log)
docker compose exec web ./vendor/bin/pest --filter='UserResourceBanBulkAction|MatchResourceBulkCancel|MatchDisputeWorkflow|AbuseReportWorkflow|ModeratorPermissionGate|ModeratorAuditLog' --no-coverage

# SC-4: Performance pass (strict mode + query budgets + cache + WebP)
docker compose exec web ./vendor/bin/pest --filter='AppServiceProviderStrictMode|LeaderboardsQueryBudget|ClansQueryBudget|CacheTagFlush|ClanLogoWebpConversion|ArticleCoverWebpConversion' --no-coverage

# SC-5: A11y + security + i18n coverage
docker compose exec web ./vendor/bin/pest --filter='PublicPagesHtmlLang|VueFormLabels|RateLimiterDefinitions|PublicApiThrottle|ReportAbuse|Phase9I18nKeyCoverage' --no-coverage
```

---

## Requirements traceability

Phase 9 (Polish) has no v1 requirement flips — the buffer phase
consumes any open polish backlog from prior phases. All 15 mappable v1
requirements were satisfied across Phases 1-8:

| Requirement | Phase | Status |
|-------------|-------|--------|
| REQ-constraint-railway-deploy | Phase 1 | Complete |
| REQ-constraint-en-launch-i18n-ready | Phase 1 | Complete |
| REQ-tenancy-single-guild | Phase 2 | Complete |
| REQ-constraint-single-guild | Phase 2 | Complete |
| REQ-tenancy-multi-clan | Phase 2 | Complete |
| REQ-goal-public-profiles | Phase 2 | Complete |
| REQ-platform-vision | Phase 3 | Complete |
| REQ-success-game-onboarding-data-only | Phase 3 | Complete |
| REQ-goal-match-workflows | Phase 4 | Complete |
| REQ-goal-discord-ux | Phase 5 | Complete |
| REQ-success-tournament-end-to-end | Phase 6 | Complete |
| REQ-goal-cms | Phase 7 | Complete |
| REQ-success-public-browse | Phase 7 | Complete |
| REQ-goal-rcon-history | Phase 8 | Complete |
| REQ-constraint-league-owns-servers | Phase 8 | Complete |
| REQ-success-end-to-end-scrim | Phase 8 | Complete |

Round 1 acceptance loop is closed: all 15 mappable v1 requirements
flipped to Complete. v1.0 milestone is shippable pending the
4-item operator manual smoke (see Manual Smoke section).

---

## 12 Pitfalls — Resolution Catalog (LOCKED inline)

| # | Pitfall (from 09-RESEARCH.md) | Resolution | Plan |
|---|-------------------------------|------------|------|
| 1 | `Cache::tags` silently no-ops on non-Redis store | CACHE_STORE=redis preflight at Wave 0 (plan 09-01); `LeaderboardCacheTest` round-trip verifies tagged cache hits in test env (Redis-backed) | 09-01 + 09-05 |
| 2 | Model::shouldBeStrict breaks existing tests | Plan 09-08 N+1 sweep — 12 test-side `->load(...)` patches + 6 app-side eager-load fixes; `AppServiceProviderStrictModeTest` 3 cases verify strict on in non-prod, off in prod | 09-08 |
| 3 | DiscordChannel bypasses outbox / writes Http directly | `DiscordChannelOutboxTest` `Http::assertNothingSent`; `DiscordChannel::send` writes exactly one `discord_outbound_messages` row + nothing else | 09-03 |
| 4 | Two Notifications with the same databaseType discriminator | Reflection-based test in `MatchStartingSoonNotificationTest` asserts unique `databaseType()` returns per Notification class; 5/5 unique | 09-03 |
| 5 | Notification idempotency race (scheduler re-fires before commit) | `NotificationDispatcher::dispatchUpcoming` runs `onOneServer()->withoutOverlapping()` + `alreadyDispatched` predicate query; `NotificationDispatcherIdempotencyTest` simulates re-fire — second pass writes 0 rows | 09-04 |
| 6 | Imagick not present (WebP conversion silently fails) | Plan 09-01 preflight: extension check at boot via `App::booted` + Pest fixture verifies; `ClanLogoWebpConversionTest` materially tests output after `->queued()` Horizon drain | 09-01 + 09-09 |
| 7 | `uuidMorphs` vs `morphs` mismatch on polymorphic FKs | Plan 09-02 migrations use `uuidMorphs('subject')` consistently on `bans.target` + `abuse_reports.target` (matching Phase 1 `activity_log.subject` UUID FK shape) | 09-02 |
| 8 | BulkAction modal silent close (no validation error) | Filament BulkActions on `UserResource` ban + `MatchResource` cancel have `Textarea::make('reason')->required()->minLength(N)` + the modal stays open on validation error | 09-07 |
| 9 | `Cache::flexible` silent failure when callable throws | `LeaderboardService::topPlayers/topClans` wraps callable body in `try { ... } catch (Throwable $e) { report($e); throw; }` so background revalidation surfaces to Bugsnag (or default exception handler) | 09-05 |
| 10 | `discord_outbound_messages.message_type` CHECK rejects new values | Plan 09-02 migration extends the CHECK constraint to include `user_dm` (joining the existing Phase 5/6/8 values); migration is idempotent (DROP + ADD pattern from Phase 8 `match_result_announce`) | 09-02 |
| 11 | axe-core scans behind auth (admin/auth routes returning 302) | `.github/workflows/a11y.yml` 7-URL matrix EXCLUDES admin/auth routes by construction (only `/, /clans, /matches, /tournaments, /blog, /events, /leaderboards`); 09-10-SUMMARY records the explicit exclusion list | 09-10 |
| 12 | Strict mode + RefreshDatabase + factories cause N+1 false positives | Plan 09-08 sweep uses `$model->load([...])` explicitly in tests where the factory-built relationship graph would otherwise lazy-load through strict mode | 09-08 |

---

## 8 Open Questions — Resolution Catalog (LOCKED inline)

| # | Question (from 09-RESEARCH.md) | Resolution | Plan |
|---|--------------------------------|------------|------|
| OQ1 | WebP fallback (JPEG/PNG) for non-supporting clients? | LOCKED no fallback v1 — every browser Trenchwars targets supports WebP (browsers older than 2020 are not in scope). `<picture>` element with `<source srcset="...webp">` + `<img src="...webp">` (no fallback `<img>` src to original). | 09-09 |
| OQ2 | Notification batching (digest mode)? | LOCKED deferred to v2 — round 1 emits one Notification per event (no daily/hourly digest); user can mute per-rule via the 5×2 matrix in `NotificationPreferences.vue`. | (deferred) |
| OQ3 | `match_result_published` Discord DM default? | LOCKED default-off — `User::enabledNotificationChannels('match_result_published')` short-circuits to `['database']` only when the user has not explicitly opted into the Discord channel for this rule. | 09-03 |
| OQ4 | Ban scope (site-wide vs per-clan)? | LOCKED site-wide v1 — `bans` table has NO `clan_id` FK column; a banned user is denied access across the entire site. v2 may introduce per-clan bans (additive). | 09-02 |
| OQ5 | Moderator panel split (own panel vs Filament gates)? | LOCKED single Filament panel with per-resource permission gates — `manage_users`, `manage_matches`, `manage_disputes`, `manage_abuse_reports`, `ban_user` perms enforced via `->canAccess()` on each Resource. | 09-07 |
| OQ6 | WebP regenerate Artisan command? | LOCKED yes — `trenchwars:media:regenerate-webp` rebuilds `logo_webp_*` / `avatar_webp_*` / `cover_webp_*` conversions across all `Clan`/`Player`/`Article` models; safe-to-rerun (skips existing). | 09-09 |
| OQ7 | Notification retention (DB pruning)? | LOCKED 90 days — `notifications:prune` Artisan command runs daily via `Schedule::command(...)->daily()->onOneServer()`; deletes `notifications.created_at < now() - 90 days`. | 09-04 |
| OQ8 | Install Telescope for v1? | LOCKED NO — Debugbar (already installed P1) covers v1 needs. Telescope deferred to v2 if observability requirements grow. | (deferred) |

---

## Canonical D-09-* decisions

Phase 9 plan-level decisions (extracted from each 09-NN-SUMMARY.md
`key-decisions` block). All decisions are LOCKED inline at the
referenced plan; this table is the canonical Phase 9 reference for
future-phase consumers and v2 planning.

| ID | Decision | Source |
|----|----------|--------|
| D-09-01-A | Wave 0 stubs use the Phase 8 idiom — `expect(false)->toBeTrue('Wave 0 stub')`->skip + factory `definition()` throws `RuntimeException` so accidental `::factory()` calls fail loud. | 09-01 |
| D-09-01-B | Five new lang/en namespace files committed at Wave 0 (`notifications.php`, `leaderboards.php`, `moderation.php`, `a11y.php`, `reports.php`) so `Phase9I18nKeyCoverageTest` resolves day-one. | 09-01 |
| D-09-01-C | Imagick + CACHE_STORE=redis preflight added to web container boot (App::booted) — fails LOUD on missing extension or non-Redis cache driver. | 09-01 |
| D-09-02-A | `notifications.id` is `uuid` primary (matches `activity_log` shape — not autoincrement bigint). | 09-02 |
| D-09-02-B | `match_disputes.match_id` is FK with ON DELETE CASCADE (matches the canonical D-04-03-B BelongsTo<GameMatch> binding; A9 LOCKED). | 09-02 |
| D-09-02-C | Partial UNIQUE on `match_disputes(match_id, raised_by_user_id) WHERE status='open'` — prevents a user from opening multiple concurrent disputes against the same match. | 09-02 |
| D-09-02-D | `bans` table has NO `clan_id` FK — site-wide v1 per OQ4 LOCKED. | 09-02 |
| D-09-02-E | `discord_outbound_messages.message_type` CHECK extension to `user_dm` joins existing 8th value (`match_result_announce` from Phase 8); migration uses DROP + ADD idempotent pattern. | 09-02 |
| D-09-03-A | `DiscordChannel::send` writes exactly one `discord_outbound_messages` row (`message_type='user_dm'`, JSONB payload) — NO `Http::*` call ever (Pitfall 3 mitigation; D-004 outbox compliance). | 09-03 |
| D-09-03-B | Each Notification class returns a unique `databaseType()` discriminator (5/5 unique across the Notifications/ namespace) — Pitfall 4 mitigation; verified by reflection test. | 09-03 |
| D-09-03-C | `User::enabledNotificationChannels(string $rule)` returns `['database','discord-channel']` by default EXCEPT `match_result_published` which short-circuits to `['database']` only (OQ3 LOCKED default-off). | 09-03 |
| D-09-04-A | `notifications:dispatch-upcoming` and `notifications:prune` use `Schedule::command(...)->withoutOverlapping()->onOneServer()` to prevent race re-fires (Pitfall 5 mitigation). | 09-04 |
| D-09-04-B | Notification retention is 90 days (OQ7 LOCKED); `notifications:prune` runs daily. | 09-04 |
| D-09-04-C | `NotificationDispatcher::dispatch` returns void (not the Notification instance) — caller cannot accidentally re-dispatch via the return value (defensive API). | 09-04 |
| D-09-05-A | LeaderboardService uses `Cache::tags(['leaderboards','lb:players:{window}'])->flexible([600,3600])` (stale-while-revalidate: 10-min fresh + 60-min stale-OK background refresh). | 09-05 |
| D-09-05-B | `MatchPlayerStatObserver::saved` + `MatchResultObserver::saved` flush the `leaderboards` tag — tag granularity intentionally COARSE so any stat or result mutation invalidates every leaderboard window in one call. | 09-05 |
| D-09-05-C | Cache key strategy is documented at `.planning/phases/09-polish/CACHE-STRATEGY.md` for v2 consumers. | 09-05 |
| D-09-06-A | Inertia `HandleInertiaRequests` shares `unread_notifications_count` as a CLOSURE (lazy-eval) — not eager — so guest visitors never trigger the count query. | 09-06 |
| D-09-06-B | `LeaderboardEntryData::fromQueryResult` enforces D-018 `PlayerPrivacyGate` at the DTO boundary — opted-out players surface as `null` slot but still occupy rank. | 09-06 |
| D-09-06-C | `NotificationsBell.vue` polls `unread_notifications_count` every 60s via Inertia `router.reload({only: ['unread_notifications_count']})` (NOT a separate JSON endpoint — eliminates a route surface). | 09-06 |
| D-09-07-A | Single Filament panel (admin) with per-resource permission gates — OQ5 LOCKED. No separate moderator panel. | 09-07 |
| D-09-07-B | `ModeratorRoleSeeder` seeds the spatie role + 5 permissions IDEMPOTENTLY — re-running is safe; existing perms not duplicated. | 09-07 |
| D-09-07-C | Every BulkAction has a `->visible(fn () => auth()->user()?->can('...'))` gate — `ModeratorPermissionGateTest` asserts non-mods see no BulkAction surface. | 09-07 |
| D-09-07-D | `DisputeService` enforces state machine `open → under_review → {resolved,rejected}` — illegal transitions throw `InvalidStateTransitionException` and write 0 audit rows. | 09-07 |
| D-09-08-A | `Model::shouldBeStrict(! isProduction())` in `AppServiceProvider::boot` — strict ON in local/CI, OFF in Railway production (defensive performance posture). | 09-08 |
| D-09-08-B | Query budgets codified in Pest: `/leaderboards` ≤4 warm / ≤6 cold deviation; `/clans` ≤8. Pitfall 12 mitigated via `->load(...)` patches in 12 test files. | 09-08 |
| D-09-08-C | CACHE-STRATEGY.md authored at `.planning/phases/09-polish/CACHE-STRATEGY.md` — v2 consumers MUST consult before introducing new cache tags. | 09-08 |
| D-09-09-A | WebP-only v1 — no JPEG/PNG fallback (OQ1 LOCKED). `<picture>` element with `<source srcset="...webp">` only. | 09-09 |
| D-09-09-B | Every WebP conversion uses `->queued()` (Horizon-deferred) — upload latency stays sub-second; conversion happens async. | 09-09 |
| D-09-09-C | `trenchwars:media:regenerate-webp` backfill command is safe-to-rerun — skips models with existing conversion attached (OQ6 LOCKED). | 09-09 |
| D-09-10-A | axe-core CI workflow scans ONLY public routes (`/, /clans, /matches, /tournaments, /blog, /events, /leaderboards`) — admin/auth routes excluded per Pitfall 11. | 09-10 |
| D-09-10-B | Site-wide `*:focus-visible` CSS uses `color-mix(in oklch, var(--color-focus-ring) 80%, transparent)` for the outer ring — works on both light/dark themes. | 09-10 |
| D-09-10-C | `PublicPagesHtmlLangTest` asserts every public page renders `<html lang="en">` — catches Vue/Inertia SSR drift on the lang attribute. | 09-10 |
| D-09-10-D | `VueFormLabelsTest` is a STATIC-SCAN test (regex over .vue files) — runs in ms; catches label-less inputs without browser automation. | 09-10 |
| D-09-10-E | T-09-10-01 upgraded from `accept → mitigate` via `if:failure()` artifact upload in the axe-core CI workflow — failed scan reports persist for post-mortem. | 09-10 |
| D-09-10-F | Task 2 (manual keyboard nav 10-step) DEFERRED to PENDING_MANUAL_SMOKE operator handoff per the standing autonomous workflow convention (same close pattern as every prior phase). | 09-10 |
| D-09-11-A | Four new RateLimiters: `public-api 30/min`, `auth 10/min`, `notifications-read 120/min`, `report-abuse 5/hr`. Registered in `RouteServiceProvider::configureRateLimiting`. | 09-11 |
| D-09-11-B | `abuse_reports` state machine `pending → dismissed | actioned`; an `actioned` report can optionally have a linked `Ban` row via the Filament resource action gate. | 09-11 |
| D-09-11-C | `ReportButton.vue` is a tiny stateless component embeddable anywhere (used on profile pages + match pages); routes to `/reports/abuse/create` with prefilled target_type+target_id query params. | 09-11 |
| D-09-11-D | `StoreAbuseReportRequest` enforces `reason_code` ∈ {harassment, cheating, impersonation, ban_evasion, other} + body required minLength 20 — surfaces friendly i18n error keys on validation failure. | 09-11 |
| D-09-11-E | `Pitfall 11 in security`: partial UNIQUE on `abuse_reports(reporter_user_id, target_type, target_id) WHERE status='pending'` — reporter cannot spam-open multiple pending reports against the same target. | 09-11 |
| D-09-11-F | Task 3 manual rate-limit boundary smoke DEFERRED to PENDING_MANUAL_SMOKE per autonomous workflow convention. | 09-11 |
| D-09-12-A | Phase9I18nKeyCoverageTest mirrors the canonical `CmsI18nKeyCoverageTest` two-it() idiom — (1) expected-key resolution + (2) source-grep round-trip; CI-gated. | 09-12 |
| D-09-12-B | Pint auto-fix in 09-12 commit narrowed scope to 4 Phase-9 source files (Rule 1 deviation — `AbuseReportResource.php`, `routes/web.php`, `AbuseReportWorkflowTest.php`, `RateLimiterDefinitionsTest.php`); zero pre-Phase-9 file touched. | 09-12 |

---

## D-04-03-A LOCKED — canonical model binding (re-affirmed across Phase 9)

App\Models\GameMatch is the canonical model name (NOT `Match` — PHP 8.x
reserved keyword); table stays `matches` via `protected $table = 'matches'`
override; no `Match as MatchModel` alias-on-import anywhere in the Phase
9 surface. Verified across:

- `app/Services/LeaderboardService.php` — direct `use App\Models\GameMatch` for MatchResult joins
- `app/Services/DisputeService.php` — direct `use App\Models\GameMatch`
- `app/Services/BanService.php` — direct `use App\Models\GameMatch` (target_type='match' branch)
- `app/Observers/MatchObserver.php` (extended in 09-04) — direct `use`
- `app/Observers/MatchResultObserver.php` (extended in 09-04 + 09-05) — direct `use`
- `app/Observers/MatchPlayerStatObserver.php` (new in 09-05) — direct `use`
- `app/Filament/Resources/MatchDisputeResource.php` — direct `use`
- All Phase 9 tests (Feature + Unit) — direct `use App\Models\GameMatch`

`BelongsTo<GameMatch, $this>` passes `match_id` as explicit FK arg per
D-04-03-B (Laravel cannot infer from `match()` method name when related
class is `GameMatch`). v2 plans MUST preserve this binding.

---

## Manual Smoke — PENDING

Operator walkthrough required to fully close Phase 9 → v1.0 ship. Four items:

- [ ] **A — axe-core CI workflow first canonical run.** Push to master
      triggers `.github/workflows/a11y.yml`; verify the public-route
      matrix (7 URLs) returns zero a11y violations on first scan; if
      `if:failure()` triggers, download the artifact and walk the
      report. Admin/auth routes are EXCLUDED by construction
      (Pitfall 11).

- [ ] **B — Manual keyboard nav 10-step checklist.** Deferred from
      plan 09-10 Task 2 to operator out-of-band per autonomous
      workflow convention. Walk every public flow (`/`, `/clans`,
      `/clans/{slug}`, `/players/{slug}`, `/matches`, `/matches/{id}`,
      `/tournaments`, `/tournaments/{slug}`, `/blog`,
      `/leaderboards`) keyboard-only (Tab / Shift+Tab / Enter / Space
      / Esc only — no mouse) and confirm every interactive element
      receives visible focus + activates correctly. Full checklist
      verbatim in 09-10-SUMMARY.md "Operator Handoff" section.

- [ ] **C — Rate-limit boundary smoke.** Deferred from plan 09-11
      Task 3 to operator out-of-band. With curl, fire 31 requests in
      the same minute against `/leaderboards.json` (public-api) and
      11 requests against `POST /login` (auth) and 6 requests in 1h
      against `POST /reports/abuse` and confirm:
      - 31st `public-api` request returns 429
      - 11th `auth` request returns 429
      - 6th `report-abuse` request returns 429
      - Friendly i18n error key resolves in the JSON response

- [ ] **D — Notifications bell + Discord DM live receipt.** With both
      `web` and `bot` services running against a live Discord guild,
      schedule a match with `scheduled_start = now + 60min` against a
      registered server; opt the test user into all 5 notification
      rules (Discord channel on); confirm:
      - The notifications bell badge increments at the 1h fan-out
      - The notifications bell badge increments again at the 15m fan-out
      - A Discord DM lands in the test user's inbox for each fan-out
      - The match starts → the bell badge does NOT re-increment
      - Cancel the match → the bell badge increments + a `MatchCancelled` DM lands

These four items cover the operator/network seams that the test
surface intentionally does not exercise (live axe-core scan, real
keyboard input, real-IP rate-limit boundaries, and a Discord guild
delivery loop with the bot worker draining the outbox). Once all 4
are complete the phase moves from PENDING_MANUAL_SMOKE → COMPLETE
and v1.0 milestone ships.

---

## Quality Gate Outputs (from plan 09-12 Task 1)

```
=== Pest (web full suite) ===
Tests:    1303 passed (4546 assertions)
Duration: 84.54s

=== Pint ===
PASS — 651 files clean

=== PHPStan L8 ===
[OK] No errors

=== Web build (vite + filament) ===
✓ vite app built in 5.32s
✓ filament theme rebuilt in 655ms

=== Bot build (tsc) ===
@trenchwars/bot@0.0.0 build — tsc clean

=== rcon-worker build (tsc) ===
@trenchwars/rcon-worker@0.0.0 build — tsc clean

=== axe-core CI workflow ===
.github/workflows/a11y.yml present (3724 bytes) — canonical first run on next push
```

Full output preserved at `.planning/phases/09-polish/quality-gates-output.txt`.

---

## Phase 9 sign-off

All 7 quality gates GREEN. All 5 success criteria mechanically proven
by the test surface (SC-1 and SC-5 PARTIAL pending operator manual
smoke; SC-2/SC-3/SC-4 fully PASS). No v1 requirement flips (round
1's requirements were consumed by Phases 1-8 — Phase 9 is the buffer
milestone).

Round 1 (Phases 1-9) is complete. The v1.0 milestone ships pending
the 4-item operator manual smoke above.
