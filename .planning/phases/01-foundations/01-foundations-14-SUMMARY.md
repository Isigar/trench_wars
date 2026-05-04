---
phase: 01-foundations
plan: 14
subsystem: audit
tags: [spatie-activitylog, filament, audit, postgres, uuid, i18n]

# Dependency graph
requires:
  - phase: 01-foundations
    provides: "spatie/laravel-permission RBAC with audit.view permission seeded (plan 11)"
  - phase: 01-foundations
    provides: "Filament v3 admin panel + UserResource/PlayerResource with form schemas (plans 12, 13)"
  - phase: 01-foundations
    provides: "Eloquent User and Player UUID-PK models (plans 09, 10)"
provides:
  - "spatie/laravel-activitylog v5 installed; activity_log table with UUID subject_id/causer_id"
  - "LogsActivity trait on User and Player models with logFillable + logOnlyDirty"
  - "User trait suppresses last_login_at-only changes (login-spam mitigation)"
  - "/admin/audit Filament Page with event/subject_type/date-range filters, gated by audit.view"
  - "Per-resource Audit tab (User + Player Edit/View pages render activity history)"
  - "audit-tab Blade partial reusable by future Phase 2+ resources"
affects:
  - "All future Filament resources (Phase 2+ clan/match/tournament): wrap form schemas in Tabs::make() and append Audit tab via filament.partials.audit-tab"
  - "All Eloquent models with admin mutations (Phase 2+): adopt LogsActivity trait for SC-3 coverage"

# Tech tracking
tech-stack:
  added:
    - "spatie/laravel-activitylog ^5.0 (PHP 8.4 floor — D-001)"
  patterns:
    - "v5 schema: single create migration with attribute_changes + properties columns (no separate batch_uuid migration)"
    - "ALTER subject_id/causer_id to uuid via follow-up migration (drop indexes first; re-create after column type change)"
    - "Filament Page implementing HasTable + InteractsWithTable; public table(Table $table) method instead of deprecated protected getters"
    - "Audit Page canAccess() gated on audit.view permission (web guard)"
    - "Per-resource Audit tab via Forms\\Components\\Tabs + Placeholder rendering a Blade partial"

key-files:
  created:
    - "apps/web/app/Filament/Pages/Audit.php"
    - "apps/web/resources/views/filament/pages/audit.blade.php"
    - "apps/web/resources/views/filament/partials/audit-tab.blade.php"
    - "apps/web/database/migrations/2026_05_03_140000_create_activity_log_table.php"
    - "apps/web/database/migrations/2026_05_03_140100_add_uuid_columns_to_activity_log.php"
    - "apps/web/config/activitylog.php"
    - "apps/web/tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php"
    - "apps/web/tests/Feature/Audit/AuditPageTest.php"
  modified:
    - "apps/web/app/Models/User.php (LogsActivity + dontLogIfAttributesChangedOnly login-spam suppress)"
    - "apps/web/app/Models/Player.php (LogsActivity)"
    - "apps/web/app/Filament/Resources/UserResource.php (Tabs wrapper + Audit tab)"
    - "apps/web/app/Filament/Resources/PlayerResource.php (Tabs wrapper + Audit tab)"
    - "apps/web/app/Providers/Filament/AdminPanelProvider.php (register Audit::class)"
    - "apps/web/lang/en/admin.php (audit.* + tab.* keys)"
    - "apps/web/composer.json + composer.lock"

key-decisions:
  - "v5 namespace adoption: Spatie\\Activitylog\\Models\\Concerns\\LogsActivity (not legacy Traits\\LogsActivity); LogOptions in Spatie\\Activitylog\\Support"
  - "v5 published migration consolidates v4 sequence (create + add_event + add_batch_uuid) into one; no batch_uuid column shipped — v4-era plan text describing 3 separate migrations no longer applies"
  - "Audit Page extends Filament\\Pages\\Page implements HasTable using public table(Table) method (Filament v3.3 idiomatic); skipped deprecated protected getTable*() getters"
  - "Audit Page canAccess() requires audit.view permission (super-admin role inherits via PermissionSeeder); admin-access alone does NOT grant /admin/audit access — test users must explicitly grant audit.view"
  - "Per-resource Audit tab uses Forms\\Components\\Placeholder rendering a Blade partial (no Livewire child component) — keeps the tab read-only and lightweight per CLAUDE.md §6"

patterns-established:
  - "UUID-PK migration ALTER pattern: DROP INDEX → ALTER COLUMN TYPE uuid USING NULL → CREATE INDEX (Postgres requirement)"
  - "LogsActivity options for User-like models: logFillable + logOnlyDirty + dontLogIfAttributesChangedOnly([noisy_columns]) to avoid log-spam on housekeeping updates"
  - "Filament v3 Page+Table custom-page recipe: implements HasTable, use InteractsWithTable, public table(Table $table): Table"
  - "Read-only audit surface: no Tables\\Actions\\EditAction or DeleteAction on activity_log columns; per CLAUDE.md §6 + D-012"

requirements-completed:
  - REQ-constraint-railway-deploy

# Metrics
duration: 9m 9s
completed: 2026-05-04
---

# Phase 01-foundations Plan 14: Audit Infrastructure Summary

**spatie/laravel-activitylog v5 wired with UUID activity_log; LogsActivity on User+Player (login-spam suppressed); Filament `/admin/audit` page + per-resource Audit tabs gated by `audit.view`.**

## Performance

- **Duration:** 9m 9s
- **Started:** 2026-05-04T18:30:27Z
- **Completed:** 2026-05-04T18:39:36Z
- **Tasks:** 2 (each TDD: RED → GREEN)
- **Files modified:** 14 (8 created, 6 modified)
- **Tests:** 7 audit cases / 12 assertions (all green); 54/54 full Pest suite green

## Accomplishments

- spatie/laravel-activitylog ^5.0 installed and integrated with our UUID-PK schema
- `activity_log` table created with `subject_id`/`causer_id` columns altered to `uuid` (one-shot ALTER on empty table)
- `LogsActivity` trait on `User` (with `dontLogIfAttributesChangedOnly(['last_login_at'])` so OAuth login-touch updates don't pollute the log) and `Player` (basic fillable diff logging)
- Custom Filament `Audit` Page at `/admin/audit` rendering `Activity::query()` with columns (created_at, causer.username, event, subject_type, subject_id, description) and three filters (event SelectFilter, subject_type SelectFilter, date-range Filter); gated by the `audit.view` permission seeded in plan 11
- Empty-state and populated views for the Audit page (D-013 i18n via `admin.audit.empty.*` and `admin.audit.col.*`)
- Per-resource Audit tab on UserResource and PlayerResource Edit/View forms via `Forms\Components\Tabs` + `Placeholder` rendering a reusable `filament.partials.audit-tab` Blade partial (most-recent 50 entries scoped to the current record)
- 7 Pest tests covering: User update logging, last_login suppression, Player update logging, /admin/audit GET 200 for admins, empty-state copy, populated activity rows, 403 for non-admins

## Task Commits

Each task followed a TDD cycle:

1. **Task 1 RED — failing LogsActivity tests** — `293eaed` (test)
2. **Task 1 GREEN — install activitylog v5 + UUID migration + traits** — `49e7296` (feat)
3. **Task 2 RED — failing AuditPageTest** — `8ab46bd` (test)
4. **Task 2 GREEN — Audit page + per-resource tabs + lang + permission gate** — `ed02ef9` (feat)

## Files Created/Modified

### Created

- `apps/web/app/Filament/Pages/Audit.php` — read-only Filament page over `Activity::query()` with three filters; canAccess gated on `audit.view`
- `apps/web/resources/views/filament/pages/audit.blade.php` — empty-state + populated rendering
- `apps/web/resources/views/filament/partials/audit-tab.blade.php` — reusable per-record activity feed
- `apps/web/database/migrations/2026_05_03_140000_create_activity_log_table.php` — Spatie v5 stub renamed to plan timestamp
- `apps/web/database/migrations/2026_05_03_140100_add_uuid_columns_to_activity_log.php` — DROP INDEX → ALTER subject_id/causer_id TYPE uuid → CREATE INDEX
- `apps/web/config/activitylog.php` — published v5 config (defaults retained)
- `apps/web/tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php` — 3 cases
- `apps/web/tests/Feature/Audit/AuditPageTest.php` — 4 cases

### Modified

- `apps/web/app/Models/User.php` — `use LogsActivity` + `getActivitylogOptions()` with `logFillable + logOnlyDirty + dontLogIfAttributesChangedOnly(['last_login_at']) + setDescriptionForEvent`
- `apps/web/app/Models/Player.php` — `use LogsActivity` + `getActivitylogOptions()` with `logFillable + logOnlyDirty + setDescriptionForEvent`
- `apps/web/app/Filament/Resources/UserResource.php` — wrap form schema in `Tabs::make('user_tabs')` (Profile + Audit tabs)
- `apps/web/app/Filament/Resources/PlayerResource.php` — wrap form schema in `Tabs::make('player_tabs')` (Profile + Audit tabs)
- `apps/web/app/Providers/Filament/AdminPanelProvider.php` — register `Audit::class` in `pages([])`
- `apps/web/lang/en/admin.php` — extend `audit.*` (`nav`, `title`, `col.*`, `no_activity_yet`) + add `tab.*` (`profile`, `audit`)
- `apps/web/composer.json` + `composer.lock` — add `spatie/laravel-activitylog ^5.0`

## Decisions Made

- **v5 single-migration adoption (not 3-migration v4 sequence).** The plan's Action step described a v4-era sequence (create + add_event + add_batch_uuid). The v5 stub consolidates these into one migration with `attribute_changes` + `properties` columns and no `batch_uuid`. We renamed the single published migration to the plan's timestamp slot and authored the UUID-conversion follow-up at the next slot. Test assertion was updated to read attribute diffs from `attribute_changes` (not `properties.attributes`).
- **v5 namespaces.** `LogsActivity` moved from `Spatie\Activitylog\Traits\LogsActivity` (v4) to `Spatie\Activitylog\Models\Concerns\LogsActivity` (v5); `LogOptions` from `Spatie\Activitylog\LogOptions` to `Spatie\Activitylog\Support\LogOptions`. The plan's `<action>` block had v4 imports — we used the v5 paths.
- **Filament v3 idiomatic Page+Table API.** Used the public `table(Table $table): Table` method (Filament v3.3 contract) rather than the deprecated protected `getTableQuery() / getTableColumns() / getTableFilters()` getters described in the plan.
- **`audit.view` permission gating.** Plan 11 already seeded `audit.view`; this plan binds it to the Audit page via `canAccess()` (web guard). The AuditPageTest grants both `admin-access` and `audit.view` to its admin fixture; super-admin role inherits both via `Role::syncPermissions(Permission::all())`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] v5 published migration is a single file, not the v4 trio**
- **Found during:** Task 1 (`vendor:publish` step)
- **Issue:** Plan's `bash` recipe `mv` three migration files (`create_activity_log_table`, `add_event_column_to_activity_log_table`, `add_batch_uuid_column_to_activity_log_table`). v5 only publishes one consolidated migration with `attribute_changes` + `properties` columns and no `batch_uuid`.
- **Fix:** Renamed the single published migration to `2026_05_03_140000_create_activity_log_table.php`; authored the planned UUID-conversion follow-up at `2026_05_03_140100`; skipped the missing two renames.
- **Files modified:** `apps/web/database/migrations/2026_05_03_140000_create_activity_log_table.php` (renamed)
- **Verification:** Both migrations run cleanly; `\d activity_log` shows uuid subject_id/causer_id; 7/7 audit tests pass
- **Committed in:** `49e7296` (Task 1)

**2. [Rule 3 — Blocking] v5 namespace changes for LogsActivity + LogOptions**
- **Found during:** Task 1 (importing the trait into User/Player)
- **Issue:** Plan imports `Spatie\Activitylog\Traits\LogsActivity` and `Spatie\Activitylog\LogOptions`. v5 moved these to `Spatie\Activitylog\Models\Concerns\LogsActivity` and `Spatie\Activitylog\Support\LogOptions`.
- **Fix:** Used v5 namespaces in User and Player.
- **Files modified:** `apps/web/app/Models/User.php`, `apps/web/app/Models/Player.php`
- **Verification:** Composer autoload resolves; Pest fixtures import cleanly; 3/3 ActivityLoggedOnAdminMutationsTest cases pass
- **Committed in:** `49e7296` (Task 1)

**3. [Rule 1 — Bug] Test assertion read v4 `properties.attributes` instead of v5 `attribute_changes`**
- **Found during:** Task 1 (RED→GREEN — test still failing after install)
- **Issue:** Plan's RED test asserted `expect((array) $log->properties->toArray())->toHaveKey('attributes.username')`. In v5 the diff lives in the new `attribute_changes` column.
- **Fix:** Assert `$log->attribute_changes->toArray()['attributes']` has key `username`.
- **Files modified:** `apps/web/tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php`
- **Verification:** Test passes
- **Committed in:** `49e7296` (Task 1)

**4. [Rule 2 — Missing Critical] Audit page test fixture missing audit.view permission**
- **Found during:** Task 2 GREEN (AuditPageTest 403s for admin)
- **Issue:** Plan's RED test grants only `admin-access`. The Audit page now (correctly per CLAUDE.md §6) gates on `audit.view`. The 403 was the *intended* security outcome but blocked the planned admin GET 200 assertion.
- **Fix:** Test fixture grants both `admin-access` and `audit.view`. Production admins are super-admins (PermissionSeeder syncs all permissions to the role) so this matches reality.
- **Files modified:** `apps/web/tests/Feature/Audit/AuditPageTest.php`
- **Verification:** All 4 AuditPageTest cases pass
- **Committed in:** `ed02ef9` (Task 2)

**5. [Rule 1 — Bug] PHPStan flagged redundant `method_exists` check on canAccess()**
- **Found during:** Post-Task-2 PHPStan run
- **Issue:** `auth()->user()` returns `App\Models\User|null` (via Auth contract), and User has the `HasRoles` trait (and thus `hasPermissionTo`). `method_exists($user, 'hasPermissionTo')` always evaluates to true → PHPStan level 8 error (`function.alreadyNarrowedType`).
- **Fix:** Removed the `method_exists` guard; added a `@var User|null` docblock to satisfy strict typing.
- **Files modified:** `apps/web/app/Filament/Pages/Audit.php`
- **Verification:** `make phpstan` clean; `make pint` clean; full Pest suite still green
- **Committed in:** `ed02ef9` (Task 2)

---

**Total deviations:** 5 auto-fixed (3 blocking — Rule 3, 1 bug — Rule 1, 1 missing — Rule 2)
**Impact on plan:** All five were directly caused by v4→v5 schema/namespace deltas in spatie/laravel-activitylog and Filament v3.3 API drift relative to the plan's prose. No scope creep — every artifact in the plan's frontmatter `files_modified` list is present.

## Issues Encountered

- None during the work itself; the deviations above were the only friction.

## TDD Gate Compliance

- Task 1 RED gate: `293eaed` (test commit) — failing tests pre-install
- Task 1 GREEN gate: `49e7296` (feat commit) — package + traits + migrations land
- Task 2 RED gate: `8ab46bd` (test commit) — failing tests pre-page
- Task 2 GREEN gate: `ed02ef9` (feat commit) — page + tabs + lang land

No REFACTOR commit needed; Pint auto-format diffs were rolled into Task 2's GREEN commit.

## Verification Results

- `make pest tests/Feature/Audit` → 7/7 green (3 ActivityLoggedOnAdminMutationsTest + 4 AuditPageTest)
- `make pest` (full) → 54/54 green
- `make pint --test` → no issues
- `make phpstan` → no errors
- Postgres `\d activity_log` → confirms `subject_id uuid`, `causer_id uuid`, indexes `subject` + `causer` re-created

## User Setup Required

None — no external service configuration. Activity log is internal Postgres infra.

## Threat Flags

None — every new surface in this plan is covered by the plan's `<threat_model>` (T-1-05 mitigate, T-1-29 + T-1-30 accept) and re-verified at execution time.

## Self-Check: PASSED

Files verified to exist on disk:
- FOUND: apps/web/app/Filament/Pages/Audit.php
- FOUND: apps/web/resources/views/filament/pages/audit.blade.php
- FOUND: apps/web/resources/views/filament/partials/audit-tab.blade.php
- FOUND: apps/web/database/migrations/2026_05_03_140000_create_activity_log_table.php
- FOUND: apps/web/database/migrations/2026_05_03_140100_add_uuid_columns_to_activity_log.php
- FOUND: apps/web/config/activitylog.php
- FOUND: apps/web/tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php
- FOUND: apps/web/tests/Feature/Audit/AuditPageTest.php

Commits verified in git log:
- FOUND: 293eaed (test RED Task 1)
- FOUND: 49e7296 (feat GREEN Task 1)
- FOUND: 8ab46bd (test RED Task 2)
- FOUND: ed02ef9 (feat GREEN Task 2)

## Next Phase Readiness

- SC-3 (audit infra) is satisfied: every admin mutation on User and Player lands in `activity_log`; per-resource and global views are live.
- Phase 2+ Filament resources should adopt the same `Tabs::make() + Forms\Components\Placeholder + filament.partials.audit-tab` pattern to inherit per-record audit tabs for free.
- Phase 2+ models that flow through admin mutations should `use LogsActivity` and define `getActivitylogOptions()` returning at minimum `LogOptions::defaults()->logFillable()->logOnlyDirty()`.

---
*Phase: 01-foundations*
*Plan: 14*
*Completed: 2026-05-04*
