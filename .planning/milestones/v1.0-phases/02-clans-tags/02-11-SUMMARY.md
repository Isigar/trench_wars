---
phase: 02-clans-tags
plan: 11
subsystem: clans
tags: [service, controller, vue, state-machine, applications, my-clan]
dependency_graph:
  requires: [02-03, 02-05, 02-06, 02-09, 02-10]
  provides: [ClanApplicationService, ClanApplicationController, MyClan/Index.vue]
  affects: [routes/web.php, Clan model, MyClanController, ClanApplicationData, StoreClanInviteRequest]
tech_stack:
  added: []
  patterns: [DB::transaction atomicity, state-machine service, LogsActivity model observer, Ziggy global route() declaration]
key_files:
  created:
    - apps/web/app/Services/ClanApplicationService.php
    - apps/web/app/Http/Controllers/MyClan/ClanApplicationController.php
    - apps/web/resources/js/pages/MyClan/Index.vue (replaced stub)
  modified:
    - apps/web/app/Http/Controllers/MyClan/MyClanController.php
    - apps/web/app/Http/Requests/MyClan/StoreClanInviteRequest.php
    - apps/web/app/Models/Clan.php
    - apps/web/app/Data/ClanApplicationData.php
    - apps/web/routes/web.php
    - apps/web/lang/en/clans.php
    - apps/web/resources/js/types/inertia.d.ts
    - apps/web/resources/js/types/api.d.ts
    - apps/web/tests/Feature/Clans/ClanApplicationTest.php
decisions:
  - ClanApplicationData.fromModel() factory added to include applicant_username for Vue display (Rule 2)
  - StoreClanInviteRequest.prepareForValidation() resolves invited_username -> invited_user_id (Rule 2 amendment from plan 02-10)
  - Activity log tests filter on event='updated' to exclude the factory-created 'created' event with causer_id=null
  - route() Ziggy global declared in inertia.d.ts with declare global{} to work in module context (Rule 3)
  - Template href='/clans' hardcoded (not route()) to avoid vue-tsc template context limitation for global functions
metrics:
  duration: ~12min
  completed: 2026-05-12
  tasks: 3
  files: 11
---

# Phase 02 Plan 11: ClanApplicationService + MyClan/Index.vue Summary

**One-liner:** ClanApplication state machine (pending → accepted/declined/cancelled) with DB::transaction atomicity + 4-tab MyClan management UI (Profile/Members/Invites/Applications) consuming all wave 4 services.

## Tasks Completed

| # | Task | Commit | Key files |
|---|------|--------|-----------|
| 1 | ClanApplicationService + controller + routes + MyClanController final | 96ce077 | ClanApplicationService.php, ClanApplicationController.php, MyClanController.php, routes/web.php, Clan.php |
| 2 | MyClan/Index.vue 4-tab management UI | 710bd0a | MyClan/Index.vue, ClanApplicationData.php, inertia.d.ts, api.d.ts |
| 3 | ClanApplicationTest full state machine | 300fd61 | ClanApplicationTest.php |

## ClanApplicationService State Transitions

```
pending → accepted  (Leader/Officer of target clan; DB::transaction: app.update + ClanMembership.create)
pending → declined  (Leader/Officer of target clan; app.update only)
pending → cancelled (Applicant withdraws; app.update only)
```

**Atomicity (T-02-07-03):** `accept()` uses `DB::transaction` — if membership insert fails (e.g., D-009 partial unique index on concurrent accept/invite), the application status rolls back to `pending`.

**Trust boundary checks:**
- T-02-07-01: `acceptorMembership.role in [leader, officer] AND clan_id matches` — `abort(403)` if not
- T-02-07-02: `app.status === 'pending'` before any transition — `DomainException` if not
- T-02-07-03: `applicant has no active membership` checked BEFORE transaction AND D-009 index as last line

## MyClan/Index.vue Tab Map

| Tab | Key component | Action |
|-----|--------------|--------|
| Profile | TextInput, Textarea | Inertia useForm PATCH /my-clan/profile/{slug} |
| Members | MemberRow (showActions=true), role select, inline remove confirm | router.patch/delete per row |
| Invites | StatusBadge(pending), Revoke ghost-danger button | router.delete /my-clan/invites/{id} |
| Applications | Accept primary + Decline ghost-danger per row, message truncated 120 chars | router.post /my-clan/applications/{id}/accept|decline |
| No-clan state | Inline create-clan form (TextInput + Textarea) | Inertia useForm POST /clans |

**Invite modal:** Accepts `invited_username` (not `invited_user_id`) — resolved server-side via `StoreClanInviteRequest::prepareForValidation()` amendment.

## Routes Added

| Method | URI | Name | Actor |
|--------|-----|------|-------|
| POST | /my-clan/applications/{application}/accept | my-clan.applications.accept | Leader/Officer |
| POST | /my-clan/applications/{application}/decline | my-clan.applications.decline | Leader/Officer |
| POST | /applications/{application}/cancel | applications.cancel | Applicant |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing display data] ClanApplicationData.fromModel() with applicant_username**
- **Found during:** Task 2
- **Issue:** ClanApplicationData only had raw UUIDs; Vue Applications tab needed applicant username for display
- **Fix:** Added `applicant_username` field + `fromModel()` factory + `collectFromModels()` to ClanApplicationData. MyClanController uses `collectFromModels()` instead of `collect()`.
- **Files modified:** apps/web/app/Data/ClanApplicationData.php, apps/web/app/Http/Controllers/MyClan/MyClanController.php

**2. [Rule 2 - Plan amendment] StoreClanInviteRequest invited_username resolution**
- **Found during:** Task 2 (specified in plan as Rule 2 amendment)
- **Issue:** MyClan/Index.vue invite modal sends `invited_username` (plain text), but plan 02-10's StoreClanInviteRequest expected `invited_user_id` (UUID)
- **Fix:** Added `prepareForValidation()` to StoreClanInviteRequest that resolves `invited_username` → `invited_user_id` via `User::where('username', $username)->first()?->id`. Added `invited_username` as nullable rule.
- **Files modified:** apps/web/app/Http/Requests/MyClan/StoreClanInviteRequest.php

**3. [Rule 2 - Missing relation] Clan::applications() HasMany relation**
- **Found during:** Task 1
- **Issue:** Clan model had `invites()` but no `applications()` relation
- **Fix:** Added `applications(): HasMany<ClanApplication>` to Clan model
- **Files modified:** apps/web/app/Models/Clan.php

**4. [Rule 3 - Blocking typecheck] Ziggy route() global declaration**
- **Found during:** Task 2 (vue-tsc typecheck)
- **Issue:** `route()` is registered globally by ZiggyVue plugin at runtime, but TypeScript/vue-tsc didn't recognize it
- **Fix:** Added `declare global { function route(...) }` to apps/web/resources/js/types/inertia.d.ts. One remaining template-context limitation required using `/clans` hardcoded for the browse link (vue-tsc template globals work differently from script globals).
- **Files modified:** apps/web/resources/js/types/inertia.d.ts

**5. [Rule 1 - Bug] Activity log test query returns wrong event**
- **Found during:** Task 3 (3 tests failing)
- **Issue:** `Activity::latest()->first()` for a subject with both `created` (causer=null, factory) and `updated` (causer=leader, HTTP) events returned the wrong one under same-second timestamps
- **Fix:** Added `.where('event', 'updated')` filter to activity log assertions in tests
- **Files modified:** apps/web/tests/Feature/Clans/ClanApplicationTest.php

## Test Results

- **ClanApplicationTest:** 13 tests / 43 assertions — all GREEN
- **All Clans tests:** 86 tests / 375 assertions — all GREEN
- **Full suite:** 194 passed (2 pre-existing Wave 0 stubs in plan 02-13 — Admin Filament resources)
- **PHPStan:** clean (no new errors)
- **Pint:** 1 pre-existing issue in PlayerPrivacyGateTest.php (plan 02-05, not in scope)

## Known Stubs

None — all data is wired end-to-end.

## Threat Flags

None — all surfaces covered by the plan's threat model (T-02-07-01 through T-02-07-06).

## Self-Check: PASSED

| Item | Status |
|------|--------|
| ClanApplicationService.php | FOUND |
| ClanApplicationController.php | FOUND |
| MyClan/Index.vue (474 lines >= 80) | FOUND |
| ClanApplicationTest.php | FOUND |
| Commit 96ce077 | FOUND |
| Commit 710bd0a | FOUND |
| Commit 300fd61 | FOUND |
