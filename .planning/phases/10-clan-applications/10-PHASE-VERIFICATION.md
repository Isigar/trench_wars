---
phase: 10-clan-applications
slug: clan-applications
status: COMPLETE
completed: 2026-06-04
plans_complete: 7
plans_total: 7
test_count_web: 1335
test_assertions_web: 4724
test_passing_web: 1335
test_failing_web: 0
bot_test_count: 190
bot_test_files: 15
quality_gates:
  migrate_fresh_seed: GREEN
  pest: GREEN
  pint: GREEN
  phpstan_l8: GREEN
  vue_tsc: GREEN
  bot_vitest: GREEN
  bot_tsc: GREEN
  bot_eslint: GREEN
requirements: [CLAN-01, CLAN-02, CLAN-03, CLAN-04]
---

# Phase 10 — Clan Applications — Verification Report

**Date:** 2026-06-04
**Phase status:** COMPLETE (all automated gates PASS; no manual smoke required for Phase 10)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 10 |
| Name | Clan applications |
| Slug | clan-applications |
| Plans | 7 plans (10-01 through 10-07) |
| Completed date | 2026-06-04 |
| Phase 9 foundation | Phase 9 COMPLETE PENDING_MANUAL_SMOKE (2026-05-15) |
| Requirements satisfied | CLAN-01, CLAN-02, CLAN-03, CLAN-04 |

---

## Status

COMPLETE — all 8 quality gates GREEN on a fresh schema (migrate:fresh --seed).
All four ROADMAP success criteria are mechanically proven by named, runnable tests.
No manual smoke items — Phase 10 has no axe-core, keyboard-nav, rate-limit-boundary,
or live-Discord-delivery seams that automated tests cannot cover.

---

## Overview

Phase 10 delivered the clan-application *submission* path (Phase 2 built review-only):

- **Schema** (10-01): `clans.accepts_applications` boolean column (default true) +
  `clan_applications_one_pending_per_clan` partial unique index
  `(applicant_user_id, clan_id) WHERE status='pending'` (D-009 idiom) +
  three typed `DomainException` subclasses (`ClanNotRecruitingException`,
  `AlreadyInClanException`, `DuplicateApplicationException`).
- **Service** (10-02): `ClanApplicationService::apply(Clan, User, ?string)` —
  three ordered eligibility guards (not-recruiting → already-in-clan → duplicate-pending)
  throwing typed exceptions; happy path creates a `pending` ClanApplication via `LogsActivity`.
- **Controllers + routes** (10-03): `BotApiClanApplicationController::store`
  (`POST /api/bot/clans/{clan:slug}/applications`, acts-as-user group) returning
  `201 { data: … }` / 3 × 422 typed-exception codes; `ClanApplyController::store`
  (`POST /clans/{clan:slug}/apply`, auth group) redirecting back with flash or
  ValidationException. 13 tests across 3 files.
- **Recruiting toggle surfaces** (10-04): `ClanData.accepts_applications` bool +
  shared-types regen + `UpdateClanProfileRequest` boolean rule + MyClan native checkbox +
  Filament `Toggle::make` + admin i18n keys. 4 tests (leader toggle, non-member 403,
  DTO shape, DTO false-reflects).
- **Bot wiring** (10-05): `/clan apply <slug>` slash command + `clan_apply` button decode
  branch both flipped from redirect-to-web stubs to live `api.post('/clans/…/applications',
  {}, { actsAsDiscordId })`. `translateError` extended with 3 new clan error codes.
- **Web form** (10-06): `ClanShowController` adds three eligibility props
  (`acceptsApplications`, `viewerIsActiveMember`, `viewerHasPendingApplication`);
  `Clans/Show.vue` adds a `showApplyBlock`-gated Apply-to-join form (heading + optional
  Textarea + submit Button + error slot). 9-case eligibility matrix test.

32 Phase-10-specific tests across 7 Pest files + 31 bot Vitest tests (11 clan.test.ts +
20 rsvpButton.test.ts). Web suite grew from 1303 (Phase 9 close) to 1335 (+32 tests,
+178 assertions).

---

## [BLOCKING] Quality Gates — RESULT: PASS

All gates run on a fresh schema (`migrate:fresh --seed`) on 2026-06-04.

| Gate | Command | Result |
|------|---------|--------|
| Schema durability | `make artisan ARGS="migrate:fresh --seed"` | **PASS** — 57 migrations + all seeders |
| Pest (web full suite) | `make pest` | **1335 passed** (4724 assertions), 0 failed, 0 incomplete, 96.26s |
| Pint | `make pint ARGS="--test"` | **PASS** — 663 files clean |
| PHPStan L8 | `make phpstan` | **[OK] No errors** |
| vue-tsc | `docker compose exec web node_modules/.bin/vue-tsc --noEmit` | **PASS** (no output) |
| Bot Vitest | `cd apps/bot && node_modules/.bin/vitest run` | **190 passed** (15 files), 0 failed, 1.08s |
| Bot tsc | `cd apps/bot && node_modules/.bin/tsc --noEmit` | **PASS** (no output) |
| Bot ESLint | `cd apps/bot && node_modules/.bin/eslint .` | **PASS** (no output) |

**Test growth across phases:**

| Phase | Total Pest after phase | Phase contribution |
|-------|------------------------|--------------------|
| Phase 9 close (09-12) | 1303 tests (4546 assertions) | +169 web |
| **Phase 10 close (10-07)** | **1335 tests (4724 assertions)** | **+32 web (+178 assertions)** |

Bot Vitest surface: 190 tests / 15 files (unchanged from Phase 10-05 close — all 190 green).

---

## ROADMAP Success Criteria Mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 | A logged-in user who is not already in an active clan can submit an application from a clan's public page, and the application appears in the clan's Filament admin view | `apps/web/tests/Feature/Clans/ClanApplyWebTest.php` (plan 10-03 — 6 cases: happy-path submit, optional message, whitespace→null, 3 guard errors including already-in-clan / duplicate-pending / clan-not-recruiting, guest redirect); `apps/web/tests/Feature/Clans/ClanShowApplyTest.php` (plan 10-06 — 9 cases: eligibility matrix — guest-open, guest-closed, eligible-authed, active-member, historical-member, pending-application, declined-application, cancelled-application, pending-to-other-clan); Filament admin view is the existing `ClanApplicationResource` (Phase 2 — read side) which is unchanged — submitted applications appear there because `ClanApplication::create()` writes to the same `clan_applications` table the resource queries | **PASS** |
| SC-2 | A user can run `/clan apply <slug>` in Discord and receive confirmation that their application was submitted (the bot calls the web API rather than returning a stub redirect) | `apps/bot/tests/commands/clan.test.ts` — `describe('apply subcommand')` (plan 10-05 — 4 cases: deferred-ephemeral-first, `api.post('/clans/${slug}/applications', {}, { actsAsDiscordId })` called with correct path + Discord ID, success reply `'Your application has been submitted.'`, clan_not_recruiting translated reply); `apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php` (plan 10-03 — 4 cases: 201 happy path returns `{ data: ClanApplicationData }`, 3 × 422 typed-exception error codes `clan_not_recruiting / already_in_clan / duplicate_application`) | **PASS** |
| SC-3 | Submitting a second application to the same clan while one is pending, or applying when already in an active clan, returns a clear, localized error on both web and Discord — no duplicate or ineligible application is persisted | `apps/web/tests/Feature/Clans/ClanApplyServiceTest.php` (plan 10-02 — 6 cases: happy-path null message, happy-path with message, Guard 1 `ClanNotRecruitingException`, Guard 2 `AlreadyInClanException`, Guard 3 `DuplicateApplicationException` pending-count-stays-1, declined-then-reapply NOT blocked); `apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php` (plan 10-03 — 3 × 422 guard codes with i18n bot.errors.* message bodies); `apps/web/tests/Feature/Clans/ClanApplyWebTest.php` (plan 10-03 — guard error cases assert `ValidationException::withMessages` redirect + session error key); `apps/bot/tests/components/rsvpButton.test.ts` — `translateError` it()-blocks for `already_in_clan`, `duplicate_application`, `clan_not_recruiting` (plan 10-05 — 3 cases); `apps/bot/tests/commands/clan.test.ts` — `clan_not_recruiting` translated-reply assertion (plan 10-05); DB-layer last-line defence: `clan_applications_one_pending_per_clan` partial unique index (plan 10-01 — schema verified via `migrate:fresh --seed` durability gate) | **PASS** |
| SC-4 | A clan leader or officer can toggle "accepting applications" on their clan; any application attempt to a closed clan is rejected with a localized reason on both web and Discord | `apps/web/tests/Feature/Clans/ClanAcceptsApplicationsToggleTest.php` (plan 10-04 — 4 cases: leader PATCHes accepts_applications=false → DB updated, non-member 403 → value unchanged, ClanData DTO reflects bool type, DTO reflects false when model has false); `apps/web/tests/Feature/Clans/ClanApplyServiceTest.php` Guard 1 (plan 10-02 — `accepts_applications=false` → `ClanNotRecruitingException`; no application row created); `apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php` 422 clan_not_recruiting case (plan 10-03 — bot surface rejects closed-clan application with `error: 'clan_not_recruiting'`); `apps/web/tests/Feature/Clans/ClanApplyWebTest.php` guard-1 case (plan 10-03 — web surface rejects with localized error message) | **PASS** |

---

## SC Verification Commands

```bash
# SC-1: Web submit + Apply block visibility
docker compose exec web ./vendor/bin/pest --filter='ClanApplyWebTest|ClanShowApplyTest' --no-coverage

# SC-2: Bot slash command + bot API endpoint
cd apps/bot && node_modules/.bin/vitest run tests/commands/clan.test.ts
docker compose exec web ./vendor/bin/pest --filter='BotApiClanApplicationTest' --no-coverage

# SC-3: All eligibility guards (service + bot API + web + bot translateError + DB index)
docker compose exec web ./vendor/bin/pest --filter='ClanApplyServiceTest|BotApiClanApplicationTest|ClanApplyWebTest' --no-coverage
cd apps/bot && node_modules/.bin/vitest run tests/components/rsvpButton.test.ts

# SC-4: Recruiting toggle + rejection on both surfaces
docker compose exec web ./vendor/bin/pest --filter='ClanAcceptsApplicationsToggleTest|ClanApplyServiceTest|BotApiClanApplicationTest|ClanApplyWebTest' --no-coverage
```

---

## Requirements Traceability

| Requirement | Plan(s) | Test file(s) | Status |
|-------------|---------|-------------|--------|
| CLAN-01 | 10-02, 10-03, 10-06 | ClanApplyWebTest, ClanShowApplyTest | **Complete** |
| CLAN-02 | 10-03, 10-05 | clan.test.ts, BotApiClanApplicationTest | **Complete** |
| CLAN-03 | 10-01, 10-02, 10-03, 10-05 | ClanApplyServiceTest, BotApiClanApplicationTest, ClanApplyWebTest, rsvpButton.test.ts | **Complete** |
| CLAN-04 | 10-01, 10-02, 10-03, 10-04 | ClanAcceptsApplicationsToggleTest, ClanApplyServiceTest, BotApiClanApplicationTest, ClanApplyWebTest | **Complete** |

---

## Open Items

### Button slug/UUID discrepancy (plan 10-05 decision 10-05-A)

**Determination:** OPTION (a) — NOT on any shipping flow.

`grep -rn "kind: 'clan_apply'" apps/bot/src | grep -v test` returns:

```
apps/bot/src/lib/customIds.ts:18:    | { kind: 'clan_apply'; clanId: string };
apps/bot/src/lib/customIds.ts:28:        case 'clan_apply':
apps/bot/src/lib/customIds.ts:45:        return { kind: 'clan_apply', clanId: parts[2]! };
apps/bot/src/components/rsvpButton.ts:5:// Updated in Phase 10-05: clan_apply branch flipped from redirect-to-web stub
apps/bot/src/components/rsvpButton.ts:24://   clan_apply              -> api.post(/clans/{clanId}/applications, {})
apps/bot/src/components/rsvpButton.ts:96:    if (decoded.kind === 'clan_apply') {
```

`grep -rn "encodeButtonId" apps/bot/src | grep -v test` shows `encodeButtonId` is only called
with `match_signup`, `match_leave`, and `match_open_signup_modal` kinds — **never `clan_apply`**.

No production code creates a `clan_apply` button. The `c:a:` prefix exists only in the
type system (`ButtonAction` union), the `encodeButtonId` switch arm, and the `decodeButtonId`
decoder. The `rsvpButton.ts` decode handler is reachable code but the button that would
trigger it is never emitted. The **slash command path** (`/clan apply <slug>`) is the
**only live CLAN-02 shipping surface**.

**Tracked follow-up:** The `clan_apply` button decode branch posts `decoded.clanId` (UUID)
to `/clans/{clanId}/applications` but the web route is `{clan:slug}`-bound. If a future plan
adds a button creator (e.g., a "Apply to this clan" button on clan info embeds), it MUST
either (a) switch to slug encoding or (b) add a UUID-bound API route alias. Document at
that time; no action needed now.

### Deferred ideas (from 10-CONTEXT.md)

These two items were explicitly deferred to v1.1+ at planning time. They are not open
defects — they are known missing-from-scope features.

1. **Discord modal for optional cover message on `/clan apply`:** v1.1 submits message-less
   from Discord (single-arg slash command). A future plan can add a `ModalSubmitInteraction`
   flow to capture the `message` field before calling the API.

2. **Leader-facing "new application received" notification/Discord ping:** The
   `ClanApplicationObserver` fires `ClanApplicationDecided` only (Phase 9, plan 09-03).
   No create-side notification in v1.1. A future plan can add `ClanApplicationReceived`
   notification using the same `NotificationDispatcher + DiscordChannel` outbox pattern.

---

## Phase 10 Sign-off

All 8 quality gates GREEN on migrate:fresh --seed. All four ROADMAP success criteria
mechanically proven by named, runnable tests. CLAN-01..04 requirements satisfied.
The button discrepancy is confirmed NOT a shipping defect (no production button creator exists).

Phase 10 closes COMPLETE. v1.1 continues with Phase 11 (Tournament depth).
