---
phase: 09-polish
plan: 07
subsystem: moderator-tooling
tags: [wave-5, moderation, filament-bulk-actions, dispute-state-machine, sc-3, d-012, d-09-03-a, d-09-07-a, pitfall-4, pitfall-8, d-04-03-a-locked]
requires:
  - "09-02 Wave 1 — bans + match_disputes migrations (partial UNIQUE one_open_dispute_per_user_per_match)"
  - "09-03 Wave 2 — App\\Models\\Ban + App\\Models\\MatchDispute (D-09-03-A: no LogsActivity trait)"
  - "09-04 Wave 3 — App\\Notifications\\MatchCancelled + MatchObserver::maybeNotifyCancellation chain"
  - "Phase 1 — Spatie permission + Filament panel guard pinned to 'web' (Pitfall 4)"
  - "Phase 1 — UserResource + MatchResource + activity_log infra"
  - "Phase 4 — App\\Models\\GameMatch (D-04-03-A LOCKED) + status enum draft|open|locked|played|cancelled"
provides:
  - "App\\Services\\BanService — issue/lift/isCurrentlyBanned with audit_log integration"
  - "App\\Services\\DisputeService — open/transition/nextStatesFor with state-machine enforcement"
  - "App\\Exceptions\\DisputeAlreadyOpenException — SQLSTATE 23505 → domain exception mapping"
  - "App\\Exceptions\\InvalidDisputeTransitionException — state-machine illegal-pair domain exception"
  - "Database\\Seeders\\ModeratorRoleSeeder — moderator role + 5 perms; wired into DatabaseSeeder"
  - "App\\Filament\\Resources\\UserResource — ban + unban BulkActions; BansRelationManager mount"
  - "App\\Filament\\Resources\\UserResource\\RelationManagers\\BansRelationManager — read-only ban history tab"
  - "App\\Filament\\Resources\\MatchResource — mark_cancelled BulkAction (moderate-disputes gate)"
  - "App\\Filament\\Resources\\MatchDisputeResource — admin queue + transition Action"
  - "App\\Filament\\Resources\\MatchDisputeResource\\Pages\\{ListMatchDisputes,ViewMatchDispute} — page stubs"
  - "5 Pest tests GREEN (37 new tests): UserResourceBanBulkActionTest, MatchResourceBulkCancelTest, MatchDisputeWorkflowTest, ModeratorPermissionGateTest, ModeratorAuditLogTest"
  - "i18n: admin.user.relations.bans key for BansRelationManager tab title"
affects:
  - "plan 09-08 N+1 strict-mode flip: MatchDisputeResource::getEloquentQuery pre-eager-loads raisedBy + resolvedBy + match to survive the flip"
  - "plan 09-11 abuse_reports: AbuseReportResource will reuse the moderate-disputes / view-reports / manage-reports perms seeded here; permission scaffold is in place"
  - "plan 09-11 ban-check middleware: BanService::isCurrentlyBanned is the canonical 'is user banned now?' API for middleware composition"
  - "plan 09-12 i18n key coverage: moderation.* + admin.user.relations.bans land here for Phase9I18nKeyCoverageTest"
tech-stack:
  added: []
  patterns:
    - "Single Filament panel + per-resource permission gates (Open Question 5 LOCKED) — `canViewAny()` returns `Gate::allows('moderate-disputes')`; resource disappears from sidebar for non-moderators; `/admin/match-disputes` returns 403 for non-moderators. No separate ModeratorPanel — keeps Filament boot graph + theme assets at the size of a single panel."
    - "BulkAction visibility gate as a closure-over-permission — every BulkAction declares `->visible(fn () => auth()->user()?->can('moderate-X'))`. The closure runs on every Livewire render so a role change mid-session updates the UI on the next page render. Defence in depth: BanService::issue does NOT re-check the gate (the panel gate + Filament visibility are sufficient; service is the SOR for shape validity)."
    - "activity_log subject = UUID-PK domain entity (D-09-07-A) — Ban + MatchDispute carry bigint PKs from `\$table->id()` migrations, but activity_log.subject_id is `uuid` (plan 01-14 conversion). DisputeService::transition writes the log row with subject=GameMatch (UUID PK), embedding dispute_id in properties for cross-reference. BanService::issue/lift use subject=User (UUID PK) directly. The pattern keeps the audit timeline filterable by match subject (one match → all dispute transitions in one query) and avoids the bigint↔uuid coercion failure."
    - "Service-layer activity_log row INSTEAD of LogsActivity trait (D-09-03-A) — Ban + MatchDispute intentionally OMIT the trait so the audit row reads 'Alice banned Bob (temporary, reason: …)' rather than the trait's auto-generated 'Ban created' skeleton. BanService + DisputeService write explicit `activity()->causedBy()->performedOn()->withProperties()->log()` rows inside the same DB::transaction as the mutation, so a transaction rollback discards the audit row too."
    - "Bulk-action: skip terminal states gracefully — MatchResource mark_cancelled iterates each selected match and silently skips status='played' or status='cancelled'. The alternative (failing on mixed-state selections) would force moderators to manually filter before bulk-cancelling, which is a UX trap given the table's default sort is by scheduled_at desc (mixing recent and historic rows). Single bulk-level activity_log row carries the cancelled count + reason; per-match observer chain handles per-match audit + notification dispatch."
    - "Partial-unique-violation → domain exception mapping — DisputeService::open catches QueryException, sniffs `\$e->getCode() === '23505'` (Postgres SQLSTATE unique_violation), and re-throws as DisputeAlreadyOpenException. Mirrors Phase 4 MatchSignupService idiom (D-04-06-A): never preflight with SELECT-then-INSERT (TOCTOU race) — let the DB partial-UNIQUE `one_open_dispute_per_user_per_match` (Pitfall 11) be the source of truth."
    - "Self-clearing terminal columns on re-open — DisputeService::transition sets `resolved_by_user_id = null` + `resolved_at = null` on every non-terminal transition (e.g., rejected → under_review re-open). Without this, an 'amended ruling' would carry the original moderator's id forever; with it, a fresh terminal transition writes fresh values. Tested by MatchDisputeWorkflowTest 'it re-opens a rejected dispute back to under_review'."
key-files:
  created:
    - "apps/web/app/Services/BanService.php — 162 lines, issue/lift/isCurrentlyBanned"
    - "apps/web/app/Services/DisputeService.php — 240 lines, open/transition/nextStatesFor + state machine"
    - "apps/web/app/Exceptions/DisputeAlreadyOpenException.php — domain exception (23505 mapping)"
    - "apps/web/app/Exceptions/InvalidDisputeTransitionException.php — state-machine illegal-pair domain exception"
    - "apps/web/database/seeders/ModeratorRoleSeeder.php — 70 lines, idempotent firstOrCreate + syncPermissions"
    - "apps/web/app/Filament/Resources/UserResource/RelationManagers/BansRelationManager.php — read-only ban history tab"
    - "apps/web/app/Filament/Resources/MatchDisputeResource.php — 280 lines, admin queue + transition Action"
    - "apps/web/app/Filament/Resources/MatchDisputeResource/Pages/ListMatchDisputes.php — page stub"
    - "apps/web/app/Filament/Resources/MatchDisputeResource/Pages/ViewMatchDispute.php — page stub"
  modified:
    - "apps/web/app/Filament/Resources/UserResource.php — added ban + unban BulkActions; BansRelationManager via getRelations()"
    - "apps/web/app/Filament/Resources/MatchResource.php — added mark_cancelled BulkAction (single-action BulkActionGroup)"
    - "apps/web/database/seeders/DatabaseSeeder.php — wired ModeratorRoleSeeder into the call chain (after PermissionSeeder)"
    - "apps/web/lang/en/admin.php — added admin.user.relations.bans key for BansRelationManager tab title"
    - "apps/web/tests/Feature/Admin/UserResourceBanBulkActionTest.php — Wave 0 stub → 9 GREEN tests"
    - "apps/web/tests/Feature/Admin/MatchResourceBulkCancelTest.php — Wave 0 stub → 4 GREEN tests"
    - "apps/web/tests/Feature/Admin/MatchDisputeWorkflowTest.php — Wave 0 stub → 11 GREEN tests"
    - "apps/web/tests/Feature/Admin/ModeratorPermissionGateTest.php — Wave 0 stub → 8 GREEN tests"
    - "apps/web/tests/Feature/Admin/ModeratorAuditLogTest.php — Wave 0 stub → 5 GREEN tests"
decisions:
  - "D-09-07-A — activity_log subject for MatchDispute mutations is the OWNING GameMatch (UUID PK), not the dispute bigint PK. Plan 09-07 task 1's spec said `subject=dispute`, but activity_log.subject_id was migrated to `uuid` in plan 01-14 (HasUuids domain models). MatchDispute uses `\$table->id()` (bigint) per plan 09-02 migration, so a bigint id cannot coerce into a uuid column at INSERT time. Resolution: subject=match (UUID PK already loaded via belongsTo); dispute_id lives in properties for downstream cross-reference. This produces a coherent per-match audit timeline when filtering by subject and matches the open-dispute row (which already used subject=match). Documented in DisputeService::transition docblock; tested by ModeratorAuditLogTest assertion 'subject_type === GameMatch::class'."
  - "D-09-07-B — Open Question 5 LOCKED: single Filament panel approach. Per-resource permission gates (canViewAny + BulkAction `->visible(...->can(\"moderate-X\"))`). No separate ModeratorPanel registered in AdminPanelProvider. Rationale: a second panel doubles asset compilation (Vite + Filament v3 + dual-Tailwind workaround per plan 12) for negligible separation gain — the gate-per-resource approach gives the same access control with lower complexity. ModeratorPermissionGateTest locks this in (test: 'non-moderator user cannot access /admin/match-disputes — assertForbidden')."
  - "D-09-07-C — Open Question 10 LOCKED: moderator role does NOT inherit from super-admin. PermissionSeeder owns super-admin (8 perms incl. admin-access). ModeratorRoleSeeder owns moderator (5 distinct moderate-* + report perms). A super-admin user has admin-access but NOT moderate-users (asserted by ModeratorPermissionGateTest 'super-admin retains admin-access permission after ModeratorRoleSeeder runs'). Adding moderator perms to super-admin would happen explicitly in a future seeder, never by side-effect — the syncPermissions whitelist in this seeder is closed."
  - "D-09-07-D — BulkAction terminal-state skip-list is silent (skip-and-continue), not error-and-abort. MatchResource mark_cancelled silently ignores selected matches with status in [played, cancelled]. Rationale: the default table sort is scheduled_at desc, which mixes recent open matches with older played matches; failing the entire bulk on a single terminal selection would be a UX trap forcing moderators to pre-filter. The activity_log row's `count` property reflects ACTUAL cancellations (skipped rows are not counted), so the audit trail is precise."
  - "D-09-07-E — MatchResource bulk_cancel writes ONE bulk-level activity_log row, not N per-match rows. The per-match audit comes from the existing MatchObserver chain (each `\$match->update(['status' => 'cancelled'])` fires the observer which writes per-match logs via Match::LogsActivity trait). Plan 09-07 task 2 asked for `bulk-cancel issues match_cancelled notifications` — observer-chain ownership of the per-match audit + notification side-effects keeps the BulkAction code path lean and avoids double-logging."
metrics:
  duration_seconds: 2340
  duration_human: "~39m"
  completed_at: "2026-05-15T14:55:00Z"
  files_created: 9
  files_modified: 9
  total_files: 18
  services_added: 2
  exceptions_added: 2
  seeders_added: 1
  filament_resources_added: 1
  filament_resource_extensions: 2
  filament_relation_managers_added: 1
  filament_pages_added: 2
  filament_bulk_actions_added: 3
  filament_actions_added: 1
  permissions_seeded: 5
  roles_seeded: 1
  tests_added_this_plan: 37
  tests_now_passing: 1248
  tests_now_skipped: 14
  suite_total: 1262
  baseline_passing: 1211
  baseline_skipped: 19
  wave_0_stubs_turned_green: 5
  pint_files_passed: 18
  phpstan_errors: 0
  lines_added_approx: 1900
---

# Phase 9 Plan 07: Wave 5 — Moderator Tooling (BanService + DisputeService + 3 Filament resources) Summary

Shipped the entire moderator surface for SC-3: two domain services with state-machine + audit-log integration, three Filament resource changes (UserResource bulk ban/unban + BansRelationManager, MatchResource bulk-cancel, MatchDisputeResource queue + transition Action), and a seeded moderator role with the locked 5-permission matrix. Open Question 5 + Question 10 + Pitfall 4 + Pitfall 8 all LOCKED here.

Five Wave 0 stubs turned GREEN; full suite is now 1248 passed + 14 skipped (4301 assertions) in 81.6s.

## Moderator Permission Matrix (LOCKED via ModeratorRoleSeeder)

| Permission           | Used by (admin surface)                                              | Threat refs    |
|----------------------|----------------------------------------------------------------------|----------------|
| `moderate-users`     | UserResource ban + unban BulkActions                                  | T-09-07-01, 03 |
| `moderate-disputes`  | MatchResource mark_cancelled BulkAction + MatchDisputeResource queue  | T-09-07-06, 07 |
| `moderate-content`   | ArticleResource flag/hide (plan 09-11 reserved)                       | — (future)     |
| `view-reports`       | AbuseReportResource list (plan 09-11)                                 | — (future)     |
| `manage-reports`     | AbuseReportResource pending → actioned (plan 09-11)                   | — (future)     |

All 5 permissions are seeded with `guard_name='web'` (Pitfall 4 — match Filament panel guard). The `moderator` role syncs exactly these 5 — `syncPermissions()` strictness ensures a re-run never accidentally inherits unrelated perms (e.g., articles.delete from super-admin).

## Dispute State Machine (LOCKED in DisputeService::ALLOWED_TRANSITIONS)

```
                      ┌──────────────────────────┐
                      │                          │
                      ▼                          │
    [open] ──► [under_review] ──► [resolved]     │ resolution required:
                      │              terminal     │   result_amended
                      │                           │   result_voided
                      └──► [rejected] ────────────┘   no_action
                                ▲                    sanction_issued
                                │
                                └─ resolved_at + resolved_by_user_id
                                   self-cleared on re-open so the next
                                   terminal write picks up fresh values
                                   (DisputeService::transition L182-190)
```

Illegal transitions throw `InvalidDisputeTransitionException`. `resolved` is the only terminal state (no outgoing edges in v1; future "amend ruling" would carve a `resolved → under_review` edge or hand-edit the row, audit trail intact).

The transition Action's form Select populates `to_status` options from `DisputeService::nextStatesFor($dispute)` — the UI never offers an illegal transition. Service-layer validation is defence in depth (a power user submitting a forged Livewire request still fails at the service).

## BulkAction Visibility Gate Catalog

| Resource                                     | BulkAction       | Permission gate         | Visible() closure                                                                |
|----------------------------------------------|------------------|-------------------------|----------------------------------------------------------------------------------|
| `UserResource`                               | `ban`            | `moderate-users`        | `auth()->user()?->can('moderate-users')`                                          |
| `UserResource`                               | `unban`          | `moderate-users`        | `auth()->user()?->can('moderate-users')`                                          |
| `MatchResource`                              | `mark_cancelled` | `moderate-disputes`     | `auth()->user()?->can('moderate-disputes')`                                       |
| `MatchDisputeResource`                       | _(no bulks)_     | _(canViewAny gate)_     | `Gate::allows('moderate-disputes')` at resource level                             |
| `MatchDisputeResource`/Action `transition`   | _(per-record)_   | `moderate-disputes`     | `\$svc->nextStatesFor(\$record) !== [] && Gate::allows('moderate-disputes')`     |

Every gate runs on every Livewire render → a role revocation mid-session updates the UI on the next page render. ModeratorPermissionGateTest locks every cell in this matrix.

## Pitfall 4 Regression Guard (Spatie default_guard = web matches Filament panel guard)

```
config('permission.default_guard_name') === 'web'    ← config/permission.php L54
config('auth.defaults.guard')          === 'web'    ← config/auth.php
ModeratorRoleSeeder::run()             →  Role + Permission rows with guard_name='web'
AdminPanelProvider                     →  ->authGuard('web')
User                                   →  protected string \$guard_name = 'web';
```

Asserted by `ModeratorPermissionGateTest` test 'Spatie permission default_guard=web matches Filament panel guard'. A future drift on any of these layers fails the test.

## Pitfall 8 Mitigation — required + minLength on every BulkAction form field

Filament v3 silently closes the modal when a form field validation fails without an explicit error renderer (research Pitfall 8). Every BulkAction in this plan declares `->required()` + `->minLength(10)` (or `->minLength(N)` per field). Three tests assert this:

1. `UserResourceBanBulkActionTest` — 'UserResource ban BulkAction enforces required reason field (Pitfall 8)' — empty reason → `assertHasTableBulkActionErrors(['reason'])`, no Ban row created.
2. `MatchResourceBulkCancelTest` — 'MatchResource bulk-cancel BulkAction enforces required reason (Pitfall 8)' — empty reason → form error, no status flip.
3. `MatchDisputeWorkflowTest` (service-level) — 'it rejects transition to resolved without a valid resolution' — defence in depth at the service layer.

## Audit Log Coverage (T-09-07-03 — Repudiation mitigation)

| Action                          | Description                          | Causer            | Subject       | Properties                                                |
|---------------------------------|--------------------------------------|-------------------|---------------|-----------------------------------------------------------|
| BanService::issue               | `user.banned`                        | issuedBy          | User          | ban_id, ban_type, reason, expires_at                      |
| BanService::lift                | `user.ban_lifted`                    | liftedBy          | User          | ban_id, lift_reason                                       |
| DisputeService::open            | `match.dispute_opened`               | raisedBy          | GameMatch     | dispute_id, body                                          |
| DisputeService::transition      | `match.dispute_transitioned`         | by                | GameMatch     | dispute_id, from, to, resolution, notes                   |
| MatchResource bulk-cancel       | `match.bulk_cancelled`               | auth().user       | _(none)_      | count, reason                                             |
| MatchObserver per-match update  | _(LogsActivity trait per GameMatch)_ | _(via observer)_  | GameMatch     | _(LogsActivity default — attribute_changes diff)_         |

Every BanService + DisputeService row is written inside the SAME DB::transaction as the mutation, so transaction rollback discards both the data + audit row.

## Wave 0 → GREEN (5 tests)

```
UserResourceBanBulkActionTest               Wave 0 (1 skipped) →  9 passed
MatchResourceBulkCancelTest                 Wave 0 (1 skipped) →  4 passed
MatchDisputeWorkflowTest                    Wave 0 (1 skipped) → 11 passed
ModeratorPermissionGateTest                 Wave 0 (1 skipped) →  8 passed
ModeratorAuditLogTest                       Wave 0 (1 skipped) →  5 passed
                                                                ─────────
                                                                37 new GREEN tests
```

Skip-list count check:
- Pre-plan (09-06): 19 skipped.
- Post-plan (09-07): 14 skipped (19 − 5 = 14 ✓).

## Quality Gates

| Gate                                                                                            | Result                                                                |
|-------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------|
| `pest --filter="UserResourceBanBulkActionTest"`                                                 | **9 passed** / 47 assertions / 3.3s                                   |
| `pest --filter="ModeratorAuditLogTest"`                                                         | **5 passed** / 33 assertions / 2.3s                                   |
| `pest --filter="MatchResourceBulkCancelTest"`                                                   | **4 passed** / 16 assertions                                          |
| `pest --filter="MatchDisputeWorkflowTest"`                                                      | **11 passed** / 47 assertions                                         |
| `pest --filter="ModeratorPermissionGateTest"`                                                   | **8 passed** / 24 assertions / 2.6s                                   |
| `pest tests/Feature/Admin --no-coverage` (regression on entire admin surface)                   | **194 passed + 1 skipped** / 598 assertions / 20s                     |
| `pest --no-coverage` (full suite)                                                               | **1248 passed + 14 skipped** (4301 assertions) in 81.6s               |
| Baseline delta (passed)                                                                         | +37 (1211 → 1248) — exactly the 37 new GREEN tests this plan added    |
| Baseline delta (skipped)                                                                        | −5 (19 → 14) — exactly the 5 Wave 0 stubs turned GREEN                |
| Pint `--test` on 18 touched files                                                               | **PASS** (after 2 auto-fix passes: fully_qualified_strict_types)      |
| PHPStan analyse level 8 on touched app/ files                                                   | **OK, no errors** (after fixing missingType.generics + null-narrowing) |

## Deviations from Plan

### Rule 1 — Bug: activity_log subject_id (uuid) cannot accept MatchDispute bigint PK

**1. [Rule 1 — Bug] `DisputeService::transition` initially used `performedOn($dispute)`; SQLSTATE 22P02 at insert time**

- **Found during:** Task 1 service-level test 'it writes activity_log on ban issued, ban lifted, dispute opened, dispute transitioned' — the dispute_transitioned insert failed with `SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type uuid: "2"`.
- **Issue:** The plan's task 1 spec said `subject=dispute, log='match.dispute_transitioned'`. activity_log.subject_id was migrated to `uuid` in plan 01-14 (HasUuids domain models). MatchDispute carries a `$table->id()` bigint PK per the plan 09-02 migration. The bigint id silently coerces to the text "2" and fails to insert as a uuid value.
- **Fix:** DisputeService::transition writes the row with `performedOn($dispute->match)` (the owning GameMatch carries a UUID PK), embedding `dispute_id` in properties. Same pattern already applied to DisputeService::open (subject=match, dispute_id in props). Locked as **D-09-07-A**; documented in the DisputeService docblock.
- **Files modified:** `apps/web/app/Services/DisputeService.php`, `apps/web/tests/Feature/Admin/ModeratorAuditLogTest.php`.
- **Commit:** `dbb0232`.

### Rule 3 — Blocking: PHPStan null-narrowing on Filament BulkAction form data

**2. [Rule 3 — Blocker] `isset(...) && ... !== null` flagged as 'Strict comparison using !== between mixed and null will always evaluate to true'**

- **Found during:** Task 1 PHPStan verification on UserResource ban BulkAction action closure.
- **Issue:** PHPStan correctly observes that `mixed` after `isset()` is already narrowed to non-null; the additional `!== null` is dead code. The original guard was meant to handle Filament's two empty-shape conventions (`null` vs `''`).
- **Fix:** Rewrote as `$rawExpiresAt = $data['expires_at'] ?? null; $expiresAt = ($rawExpiresAt !== null && $rawExpiresAt !== '') ? Carbon::parse(...) : null;` — covers both empty shapes without redundant null checks.
- **Files modified:** `apps/web/app/Filament/Resources/UserResource.php`.
- **Commit:** `dbb0232`.

### Rule 3 — Blocking: PHPStan generic-class type spec on Filament `getEloquentQuery`

**3. [Rule 3 — Blocker] `getEloquentQuery()` return type `Builder` flagged as missingType.generics**

- **Found during:** Task 2 PHPStan verification.
- **Issue:** Filament v3's `Resource::getEloquentQuery()` overrides return generic `Builder<TModel>` and PHPStan L8 requires the override to declare the concrete bound.
- **Fix:** Added `@return Builder<MatchDispute>` docblock annotation. No runtime change.
- **Files modified:** `apps/web/app/Filament/Resources/MatchDisputeResource.php`.
- **Commit:** `fb0ac9f`.

### Rule 3 — Blocking: PHPStan null-narrowing on Ban->user and MatchDispute->match

**4. [Rule 3 — Blocker] `performedOn($ban->user)` + `performedOn($dispute->match)` flagged as `App\Models\User|null` / `App\Models\GameMatch|null` given**

- **Found during:** Task 1 PHPStan verification.
- **Issue:** `Ban::user()` is `BelongsTo<User, Ban>` which PHPStan narrows to `User|null` even though every Ban row has a non-null `user_id` FK (cascadeOnDelete). Same shape for `MatchDispute::match`.
- **Fix:** Explicit null-coalesce-throw guards (`$subject = $ban->user ?? throw new RuntimeException(...)`) so the type system sees a non-null instance entering `performedOn()`. The throw arm is defensive — a NULL would indicate an FK integrity failure that the schema's cascadeOnDelete prevents.
- **Files modified:** `apps/web/app/Services/BanService.php`, `apps/web/app/Services/DisputeService.php`.
- **Commit:** `dbb0232`.

### Rule 3 — Blocking: PHPStan config-path test on Spatie permission

**5. [Rule 3 — Blocker] Initial ModeratorPermissionGateTest used `config('permission.default_guard')`; the actual config key is `default_guard_name`**

- **Found during:** Task 2 test verification.
- **Issue:** The test mis-named the config key, returned null, and the `expect(null)->toBe('web')->or(...)` chain did not short-circuit correctly.
- **Fix:** Corrected the key to `permission.default_guard_name` (see `config/permission.php` L54) and dropped the `or()` chain in favour of a simple single expectation.
- **Files modified:** `apps/web/tests/Feature/Admin/ModeratorPermissionGateTest.php`.
- **Commit:** `fb0ac9f`.

### Rule 4 — None

No architectural changes required. Every adjustment was a Rule 1 schema-alignment (D-09-07-A) or a Rule 3 PHPStan / test-config fix.

## Authentication Gates

None. Plan ran fully autonomously inside the Docker stack (web + postgres + redis healthy). No external API, no human action required.

## Known Stubs

None. Every code path is fully wired:

- BanService writes real Ban rows + real activity_log rows; tested.
- DisputeService writes real MatchDispute rows + real activity_log rows + enforces state machine; tested.
- Filament BulkActions inject services via DI and write through them; no mock paths.
- BansRelationManager is read-only by design (`isReadOnly()` returns true); intentional, not a stub.
- MatchDisputeResource form() is read-only by design (ViewMatchDispute page); transition Action is the only mutation path.

## Threat Flags

None. The plan's `<threat_model>` (T-09-07-01..08) covers every introduced surface:

| Threat                                                  | Component                                          | Mitigation status                                                                                                                                                                                  |
|----------------------------------------------------------|---------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| T-09-07-01 (Elevation — non-moderator bulk-ban)         | UserResource ban + unban BulkActions              | **PASS** — `->visible(... ->can('moderate-users'))`; ModeratorPermissionGateTest 'non-moderator user cannot see UserResource ban BulkAction'.                                                       |
| T-09-07-02 (Tampering — moderator bans admin/moderator) | All BulkActions                                    | **ACCEPT** (per plan) — v1 has no ban-tier hierarchy.                                                                                                                                              |
| T-09-07-03 (Repudiation — moderator denies the ban)     | BanService + DisputeService                       | **PASS** — every mutation writes activity_log row with causer + subject + properties. ModeratorAuditLogTest 5 cases.                                                                              |
| T-09-07-04 (Spoofing — system user as ban issuer)       | BanService::issue signature                       | **PASS** — `issuedBy: User` is non-nullable; service requires a real User instance.                                                                                                                |
| T-09-07-05 (DoS — mass ban via Bulk)                    | Filament BulkAction selection                     | **ACCEPT** (per plan) — Filament selects current-page records (default 25); 10k mass-ban requires explicit 'select all'.                                                                          |
| T-09-07-06 (Information Disclosure — dispute body)      | MatchDisputeResource canViewAny gate              | **PASS** — `Gate::allows('moderate-disputes')` blocks non-moderators at the resource level; ModeratorPermissionGateTest 'non-moderator cannot access /admin/match-disputes — assertForbidden'.    |
| T-09-07-07 (Tampering — invalid dispute transition)     | DisputeService::transition state machine          | **PASS** — every illegal pair throws InvalidDisputeTransitionException; MatchDisputeWorkflowTest 'rejects invalid transition open -> resolved directly' + 'rejects transition from resolved'.    |
| T-09-07-08 (DoS — Pitfall 8 silent modal close)         | All BulkAction form fields                        | **PASS** — every field has `->required()` + `->minLength(N)`; 3 tests assert inline `assertHasTableBulkActionErrors(['reason'])` behaviour.                                                       |

No new surface beyond the threat register. No threat flags added.

## Self-Check: PASSED

**Files checked (9 created, 9 modified — 18 total):**

```
FOUND: apps/web/app/Services/BanService.php
FOUND: apps/web/app/Services/DisputeService.php
FOUND: apps/web/app/Exceptions/DisputeAlreadyOpenException.php
FOUND: apps/web/app/Exceptions/InvalidDisputeTransitionException.php
FOUND: apps/web/database/seeders/ModeratorRoleSeeder.php
FOUND: apps/web/database/seeders/DatabaseSeeder.php                     (modified)
FOUND: apps/web/app/Filament/Resources/UserResource.php                 (modified)
FOUND: apps/web/app/Filament/Resources/UserResource/RelationManagers/BansRelationManager.php
FOUND: apps/web/app/Filament/Resources/MatchResource.php                (modified)
FOUND: apps/web/app/Filament/Resources/MatchDisputeResource.php
FOUND: apps/web/app/Filament/Resources/MatchDisputeResource/Pages/ListMatchDisputes.php
FOUND: apps/web/app/Filament/Resources/MatchDisputeResource/Pages/ViewMatchDispute.php
FOUND: apps/web/lang/en/admin.php                                       (modified)
FOUND: apps/web/tests/Feature/Admin/UserResourceBanBulkActionTest.php   (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Admin/MatchResourceBulkCancelTest.php     (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Admin/MatchDisputeWorkflowTest.php        (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Admin/ModeratorPermissionGateTest.php    (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Admin/ModeratorAuditLogTest.php           (Wave 0 → GREEN)
```

**Commits verified:**

```
FOUND: dbb0232 feat(09-07): BanService + DisputeService + ModeratorRoleSeeder + UserResource bulk ban/unban (Task 1)
FOUND: fb0ac9f feat(09-07): MatchResource bulk-cancel + MatchDisputeResource + 3 GREEN admin tests (Task 2)
```

**Stub elimination verified:**

```
$ docker compose exec -T web ./vendor/bin/pest --filter="UserResourceBanBulkActionTest|MatchResourceBulkCancelTest|MatchDisputeWorkflowTest|ModeratorPermissionGateTest|ModeratorAuditLogTest" --no-coverage
  Tests: 37 passed (169 assertions) — all 5 Wave 0 stubs turned GREEN
```

**Suite delta:**

```
Pre-plan baseline (09-06):    1211 passed + 19 skipped
Post-plan (09-07):            1248 passed + 14 skipped
                              ────────────  ──────────
                              +37 passed    −5 skipped
```

All 9 created + 9 modified files present on disk; both commits resolve in `git log`.
