---
phase: 02-clans-tags
plan: "09"
subsystem: backend-controllers
tags: [policy, authorization, controllers, formrequests, routes, audit-log]
dependency_graph:
  requires: [02-03, 02-05, 02-06, 02-07]
  provides: [ClanPolicy, ClanMembershipPolicy, MyClanController, ClanCreateController, MyClanProfileController, MyClanMemberController]
  affects: [02-10, 02-11, 02-12]
tech_stack:
  added: []
  patterns: [Laravel-Policy, FormRequest-authorize, DB-transaction-audit, Gate-authorize]
key_files:
  created:
    - apps/web/app/Policies/ClanPolicy.php
    - apps/web/app/Policies/ClanMembershipPolicy.php
    - apps/web/app/Providers/AuthServiceProvider.php
    - apps/web/app/Http/Controllers/Clans/ClanCreateController.php
    - apps/web/app/Http/Controllers/MyClan/MyClanController.php
    - apps/web/app/Http/Controllers/MyClan/MyClanProfileController.php
    - apps/web/app/Http/Controllers/MyClan/MyClanMemberController.php
    - apps/web/app/Http/Requests/Clans/StoreClanRequest.php
    - apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php
    - apps/web/app/Http/Requests/MyClan/UpdateMemberRoleRequest.php
    - apps/web/resources/js/pages/MyClan/Index.vue
  modified:
    - apps/web/bootstrap/providers.php
    - apps/web/routes/web.php
    - apps/web/lang/en/clans.php
    - apps/web/tests/Feature/Clans/MyClanManagementTest.php
decisions:
  - "AuthServiceProvider extends Foundation\AuthServiceProvider for policy registration discoverability; registered in bootstrap/providers.php (Laravel 12 explicit list)"
  - "Officer-cannot-promote-to-Leader split across policy + FormRequest: policy owns same-clan/role check; FormRequest owns the promotion guard (policy lacks access to desired role at authorize()-time)"
  - "Gate::authorize() used directly in MyClanMemberController::remove() because base Controller is bare in Laravel 12 (no AuthorizesRequests trait); no architectural change needed"
  - "Inertia ValidationException redirects back (not 422 JSON) — MyClanManagementTest uses assertSessionHasErrors for reserved-slug test"
  - "activity_log event filter (where event='updated') added to audit assertions to avoid collision with factory-created 'created' events with null causer"
  - "MyClan/Index.vue stub created so Inertia ensure_pages_exist check passes in tests; full page deferred to plan 02-12"
metrics:
  duration: "468s (~8 min)"
  completed: "2026-05-12T19:16:26Z"
  tasks_completed: 3
  files_changed: 14
---

# Phase 2 Plan 09: My Clan Controllers + Policies + FormRequests Summary

**One-liner:** ClanPolicy + ClanMembershipPolicy + 4 auth-gated controllers + 3 FormRequests for the My Clan management surface, with full audit-log coverage and 23 Pest tests GREEN.

## What Was Built

### Task 1: Policies + AuthServiceProvider (commit 44096ba)

**ClanPolicy** (`final class ClanPolicy`):
- `view()` → always true (public clan pages)
- `update()` → active Leader/Officer membership in the clan (T-02-05-02 mitigation)
- `delete()` → always false from My Clan (admin-only)
- `transferLeadership()` → Leader only

**ClanMembershipPolicy** (`final class ClanMembershipPolicy`):
- `update()` → active Leader/Officer in same clan as target (T-02-05-01 cross-clan safety)
- `remove()` → Leader/Officer in same clan; Leader cannot self-remove while still Leader (must demote first)

**AuthServiceProvider**: `extends Foundation\AuthServiceProvider`, `$policies` map registers both policies. Registered in `bootstrap/providers.php`.

### Task 2: Controllers + FormRequests + Routes (commit f4b3650)

**ClanCreateController**: `DB::transaction` wrapping `Clan::create` + `ClanMembership::create` (Leader role). Checks one-active-membership (409 Conflict). Catches `ReservedSlugException` → ValidationException on `name` field.

**MyClanController**: Pattern 7 gate — no membership → null state; member/recruit → redirect to `/clans/{slug}`; leader/officer → render management page with `ClanData`, `ClanMembershipData` props; `invites/applications` are empty arrays (plans 02-10/02-11 wire those).

**MyClanProfileController**: `UpdateClanProfileRequest::authorize()` → `ClanPolicy::update`. Calls `$clan->update($request->validated())` — `discord_role_id` is absent from validated keys (T-02-05-02 mitigation).

**MyClanMemberController**:
- `updateRole()` → `UpdateMemberRoleRequest` (policy + Officer-cannot-promote-to-Leader guard in FormRequest::authorize)
- `remove()` → `Gate::authorize('remove', $membership)` → D-009: `$membership->update(['left_at' => now()])` (no hard delete)

**Routes** (all in `auth` middleware group):
```
POST   /clans                              → clans.store
GET    /my-clan                            → my-clan.index
PATCH  /my-clan/profile/{clan:slug}        → my-clan.profile.update
PATCH  /my-clan/members/{membership}/role  → my-clan.members.role
DELETE /my-clan/members/{membership}       → my-clan.members.remove
```

### Task 3: MyClanManagementTest (commit 95d2314)

23 Pest assertions GREEN covering:
- Auth gate (guest → login redirect; no-clan → null state; member/recruit → redirect; leader/officer → management page)
- POST /clans atomic create + audit log + reserved-slug session error + 409 on one-active violation
- PATCH profile: Leader/Officer OK; Member 403; discord_role_id silently dropped
- PATCH role: Leader→officer OK; Officer→leader 403; Officer→recruit OK; audit log with event='updated' filter
- DELETE member: left_at set, row preserved (D-009), audit log; Leader self-remove 403; non-leader 403

## Policy Authorization Matrix

| Actor | `ClanPolicy::update` | `ClanMembershipPolicy::update` | `ClanMembershipPolicy::remove` |
|-------|---------------------|-------------------------------|-------------------------------|
| Guest | N/A (auth middleware) | N/A | N/A |
| Non-member | false | false | false |
| Recruit | false | false | false |
| Member | false | false | false |
| Officer | true | true (cannot promote to Leader) | true (cannot self-remove) |
| Leader | true | true | true (cannot self-remove) |

## ClanCreateController Transaction Shape

```
DB::transaction {
    Clan::create([slug, tag, name, description, country_code, owner_user_id, status='active'])
    ClanMembership::create([clan_id, user_id, role='leader', joined_at=now(), left_at=null])
}
```

LogsActivity fires inside the transaction via Eloquent's `saved` event — both rows are audited atomically (T-02-05-08).

## Mass-Assignment Guard Verification

`UpdateClanProfileRequest::rules()` declares ONLY `['name', 'tag', 'description', 'country_code']`. `discord_role_id` is absent. `$clan->update($request->validated())` therefore cannot receive `discord_role_id` regardless of request body contents. Verified by `MyClanManagementTest` assertion that `discord_role_id` stays null after a PATCH including that field.

## Audit Log Coverage Proof

| Mutation | Model | Event | Verified by |
|---------|-------|-------|-------------|
| POST /clans | Clan | created | `writes activity_log entry for Clan create` |
| PATCH /my-clan/profile | Clan | updated | `writes activity_log entry when Leader updates clan profile` |
| PATCH /my-clan/members/role | ClanMembership | updated | `writes activity_log entry on member role change` |
| DELETE /my-clan/members | ClanMembership | updated | `writes activity_log entry on member remove` |

All activity rows confirmed to have `causer_id = $actor->id` (LogsActivity auto-captures `auth()->user()`).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] PHPStan type errors in controllers**
- **Found during:** Task 2 (PHPStan run after writing controllers)
- **Issue:** `$request->user()` returns `User|null` — PHPStan correctly flags `->id` access; `$membership->clan` is nullable BelongsTo — PHPStan flags property access on null
- **Fix:** Added `/** @var User $user */` type assertions; extracted `$clan` variable with type assertion before property access
- **Files modified:** `ClanCreateController.php`, `MyClanController.php`, `UpdateMemberRoleRequest.php`
- **Commit:** f4b3650

**2. [Rule 1 - Bug] Base Controller has no `authorize()` method in Laravel 12**
- **Found during:** Task 2 (PHPStan run)
- **Issue:** Laravel 12 fresh install ships a bare `abstract class Controller {}` with no `AuthorizesRequests` trait
- **Fix:** Used `Gate::authorize('remove', $membership)` directly in `MyClanMemberController::remove()` instead of `$this->authorize()`
- **Files modified:** `MyClanMemberController.php`
- **Commit:** f4b3650

**3. [Rule 1 - Bug] Reserved-slug returns 302 not 422**
- **Found during:** Task 3 (test run)
- **Issue:** Laravel/Inertia converts `ValidationException` to a redirect with session errors (not JSON 422) in browser-context requests; test was asserting 422
- **Fix:** Changed test assertion from `assertStatus(422)->assertJsonValidationErrors` to `assertSessionHasErrors(['name'])`
- **Files modified:** `MyClanManagementTest.php`
- **Commit:** 95d2314

**4. [Rule 1 - Bug] Activity log `latest()` order collision with factory `created` events**
- **Found during:** Task 3 (test failures for audit-log assertions)
- **Issue:** `Activity::where(...)->latest()->first()` was returning the factory's `created` event (with null causer) instead of the test request's `updated` event; both have identical `created_at` timestamps in fast test execution
- **Fix:** Added `->where('event', 'updated')` filter to all audit-log update assertions
- **Files modified:** `MyClanManagementTest.php`
- **Commit:** 95d2314

### Pre-existing Out-of-Scope Issue (Deferred)

`tests/Unit/Services/PlayerPrivacyGateTest.php` has a Pint style issue (`fully_qualified_strict_types`) that pre-dates this plan. Logged to `deferred-items.md`.

## Known Stubs

- `resources/js/pages/MyClan/Index.vue` — stub page (renders only `clans.my_clan.title`). Full implementation is plan 02-12. The stub exists to satisfy Inertia's `ensure_pages_exist` check in tests.

## Threat Flags

No new threat surface introduced beyond what was planned in the 02-09 threat model. All T-02-05-01 through T-02-05-06 mitigations are implemented.

## Self-Check: PASSED

| Check | Result |
|-------|--------|
| `apps/web/app/Policies/ClanPolicy.php` | FOUND |
| `apps/web/app/Policies/ClanMembershipPolicy.php` | FOUND |
| `apps/web/app/Providers/AuthServiceProvider.php` | FOUND |
| `apps/web/app/Http/Controllers/Clans/ClanCreateController.php` | FOUND |
| `apps/web/app/Http/Controllers/MyClan/MyClanController.php` | FOUND |
| `apps/web/app/Http/Controllers/MyClan/MyClanProfileController.php` | FOUND |
| `apps/web/app/Http/Controllers/MyClan/MyClanMemberController.php` | FOUND |
| `apps/web/app/Http/Requests/Clans/StoreClanRequest.php` | FOUND |
| `apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php` | FOUND |
| `apps/web/app/Http/Requests/MyClan/UpdateMemberRoleRequest.php` | FOUND |
| `apps/web/resources/js/pages/MyClan/Index.vue` (stub) | FOUND |
| `apps/web/tests/Feature/Clans/MyClanManagementTest.php` | FOUND |
| Commit 44096ba | EXISTS |
| Commit f4b3650 | EXISTS |
| Commit 95d2314 | EXISTS |
