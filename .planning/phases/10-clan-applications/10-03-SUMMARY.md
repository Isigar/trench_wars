---
phase: 10-clan-applications
plan: "03"
subsystem: clan-applications
tags: [controller, bot-api, web, routes, tdd, sanctum, inertia]
dependency_graph:
  requires:
    - ClanApplicationService::apply() with 3 guards (plan 10-02)
    - ClanNotRecruitingException / AlreadyInClanException / DuplicateApplicationException (plan 10-01)
    - ClanApplicationData::fromModel (existing)
    - bot.acts-as middleware + abilities:bot:act-as-user stack (plan 05-03/05-04)
    - bot.errors.clan_not_recruiting / already_in_clan / duplicate_application keys (plan 10-02)
    - clans.applications.applied i18n key (plan 10-02)
  provides:
    - BotApiClanApplicationController::store (POST /api/bot/clans/{clan:slug}/applications, acts-as-user group)
    - ClanApplyController::store (POST /clans/{clan:slug}/apply, auth group)
    - bot.clans.applications.store named route in api.php
    - clans.apply named route in web.php
    - BotApiClanApplicationTest (4 cases: 201 happy + 3x 422 guard)
    - BotApiClanApplicationAbilitiesTest (3 cases: 403 without act-as + Pitfall 7 + ordering proof)
    - ClanApplyWebTest (6 cases: happy path, message, whitespace->null, 3 guards, guest)
  affects:
    - apps/web/app/Http/Controllers/BotApi/BotApiClanApplicationController.php
    - apps/web/app/Http/Controllers/Clans/ClanApplyController.php
    - apps/web/routes/api.php
    - apps/web/routes/web.php
    - apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php
    - apps/web/tests/Feature/Bot/BotApiClanApplicationAbilitiesTest.php
    - apps/web/tests/Feature/Clans/ClanApplyWebTest.php
tech_stack:
  added: []
  patterns:
    - TDD RED→GREEN idiom (test commit before implementation commit)
    - BotApiMatchSignupController catch-block pattern (typed exception → 422 i18n envelope)
    - BotApiClanController { data } single-object envelope (NOT 'slot' key)
    - MyClan/ClanApplicationController base DomainException → ValidationException::withMessages pattern
    - Pitfall 7 contract documented: missing X-Bot-Acts-As-User passes through with bot-user identity
key_files:
  created:
    - apps/web/app/Http/Controllers/BotApi/BotApiClanApplicationController.php
    - apps/web/app/Http/Controllers/Clans/ClanApplyController.php
    - apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php
    - apps/web/tests/Feature/Bot/BotApiClanApplicationAbilitiesTest.php
    - apps/web/tests/Feature/Clans/ClanApplyWebTest.php
  modified:
    - apps/web/routes/api.php
    - apps/web/routes/web.php
decisions:
  - "BotApiClanApplicationController uses three typed catch blocks (not base DomainException) to map each exception to a distinct error code — same pattern as BotApiMatchSignupController"
  - "ClanApplyController catches base DomainException on the web surface — covers all three subclasses without enumeration; their i18n messages are embedded by the service"
  - "clans.apply route placed near applications.cancel in web.php auth group — both are applicant-facing routes outside /my-clan prefix"
  - "bot.clans.applications.store route placed first inside acts-as-user group (before match signup routes) — alphabetical by resource"
  - "No FormRequest for bot endpoint — plan 10-03 notes v1 bot takes no body (message is null from Discord slash command)"
requirements_completed: [CLAN-01, CLAN-02, CLAN-03]
duration: "8min"
completed: "2026-06-04"
---

# Phase 10 Plan 03: Controllers + Routes + Tests Summary

**`POST /api/bot/clans/{slug}/applications` (201/422 bot endpoint) + `POST /clans/{slug}/apply` (web redirect) with three typed-exception → error-code mappings, both route registrations, and 13 tests across 3 test files.**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-06-04T~
- **Completed:** 2026-06-04
- **Tasks:** 2 (Task 1: bot controller + abilities test; Task 2: web controller + web test)
- **Files modified:** 7

## Accomplishments

- `BotApiClanApplicationController`: mirrors `BotApiMatchSignupController` exactly — `Auth::user()` is the rebound human; three typed catch blocks map to distinct 422 error codes with bot.errors.* i18n keys; success returns 201 `{ data: ClanApplicationData::fromModel($application) }`
- `ClanApplyController`: catches base `\DomainException` (covers all three subclasses), maps to `ValidationException::withMessages(['application' => [...]])`; whitespace-only message normalized to null; redirects back with flash `clans.applications.applied`
- Both routes registered: `bot.clans.applications.store` inside `abilities:bot:act-as-user + bot.acts-as` group; `clans.apply` inside `auth` group
- 13 tests across 3 files — all pass; phpstan L8 + pint clean

## Task Commits

1. **Task 1 (RED)** — Failing tests for bot endpoint + abilities + web endpoint - `311cae1` (test)
2. **Task 1+2 (GREEN)** — Both controllers + both routes - `c9d0b11` (feat)

## Files Created/Modified

- `apps/web/app/Http/Controllers/BotApi/BotApiClanApplicationController.php` — Bot endpoint; 3 typed catch blocks; 201 { data } success
- `apps/web/app/Http/Controllers/Clans/ClanApplyController.php` — Web endpoint; base DomainException → ValidationException; whitespace normalization
- `apps/web/routes/api.php` — Added BotApiClanApplicationController import + POST /clans/{clan:slug}/applications route inside acts-as-user group
- `apps/web/routes/web.php` — Added ClanApplyController import + POST /clans/{clan:slug}/apply route inside auth group
- `apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php` — 4 cases (201 + 3x 422 guard codes)
- `apps/web/tests/Feature/Bot/BotApiClanApplicationAbilitiesTest.php` — 3 cases (403 without act-as, Pitfall 7 contract, ability ordering proof)
- `apps/web/tests/Feature/Clans/ClanApplyWebTest.php` — 6 cases (happy path, message, whitespace->null, 3 guard errors, guest redirect)

## Decisions Made

- **Typed catch blocks on bot controller:** Matches `BotApiMatchSignupController` pattern exactly — three typed catches ensure each exception maps unambiguously to its error code string. Base `\DomainException` catch would work technically but wouldn't enforce distinct codes.
- **Base DomainException on web controller:** The web surface shows i18n error messages to the user (not error codes), so catching the base class is sufficient and forward-compatible if new subclasses are added.
- **No FormRequest for bot endpoint:** Discord slash command sends no body in v1 (message is always null from Discord). Confirmed in plan spec.
- **Route ordering in acts-as group:** Bot clan applications route placed before match signups (alphabetical by resource: `clans` before `matches`).

## Deviations from Plan

None — plan executed exactly as written.

## Gate Results

| Gate | Result |
|------|--------|
| `make pest --filter='BotApiClanApplication'` | PASS (7 passed, 22 assertions) |
| `make pest --filter=ClanApplyWebTest` | PASS (6 passed, 16 assertions) |
| `make phpstan` L8 | PASS (No errors — 422 files) |
| `make pint --test` | PASS (661 files) |
| `route:list --name=applications` | bot.clans.applications.store + applications.cancel present |
| `route:list --name=clans.apply` | clans.apply present under auth |
| `grep -c "ClanApplicationData::fromModel"` controller | 1 |
| `grep -c "bot.clans.applications.store"` api.php | 1 |
| `grep -c "ValidationException::withMessages"` web controller | 1 |
| `grep -c "clans.apply"` web.php | 1 |

## Known Stubs

None — this plan ships controller and route logic with no UI stubs. The bot endpoint (plan 10-05) already calls this URL shape; the web form UI (plan 10-04) wires the button to this route.

## Threat Flags

No new security surface beyond the plan's threat model:
- T-10-03-01 mitigated: route is under `abilities:bot:act-as-user` + `bot.acts-as`; `Auth::user()` is the rebound human; AbilitiesTest proves 403 without act-as ability.
- T-10-03-02 mitigated: bot controller uses `Auth::user()` exclusively; web controller uses `$request->user()` — neither accepts a client-supplied applicant id.
- T-10-03-03 mitigated: both controllers delegate every write to `ClanApplicationService::apply()`.
- T-10-03-04 accepted: errors are i18n-keyed strings, not stack traces.

## Self-Check: PASSED

Files confirmed present:
- apps/web/app/Http/Controllers/BotApi/BotApiClanApplicationController.php — FOUND (created)
- apps/web/app/Http/Controllers/Clans/ClanApplyController.php — FOUND (created)
- apps/web/routes/api.php — FOUND (modified)
- apps/web/routes/web.php — FOUND (modified)
- apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php — FOUND (created)
- apps/web/tests/Feature/Bot/BotApiClanApplicationAbilitiesTest.php — FOUND (created)
- apps/web/tests/Feature/Clans/ClanApplyWebTest.php — FOUND (created)

Commits confirmed:
- 311cae1 — FOUND (RED test)
- c9d0b11 — FOUND (GREEN impl)
