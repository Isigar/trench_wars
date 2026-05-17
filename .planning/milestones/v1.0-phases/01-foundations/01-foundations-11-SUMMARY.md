---
phase: 01-foundations
plan: 11
subsystem: auth-rbac
tags: [rbac, spatie-permission, artisan, filament-prep]
dependency-graph:
  requires: [01-10]
  provides:
    - "spatie/laravel-permission v7 installed with permissions/roles tables (UUID model_id)"
    - "default_guard_name pinned to 'web' (Pitfall 4 mitigation for plan 12 Filament gate)"
    - "User::HasRoles trait + $guard_name='web'"
    - "admin-access + audit.view permissions seeded; super-admin role with all permissions; cms-editor placeholder role"
    - "PermissionSeeder + DatabaseSeeder wired for migrate:fresh --seed"
    - "trenchwars:make-admin <discord_id> artisan command (idempotent)"
  affects:
    - "plan 01-12 Filament install (will gate panel via canAccessPanel + admin-access)"
    - "plan 01-14 audit log UI (will gate /admin/audit via audit.view)"
    - "plan 01-09 Discord OAuth (User model now has HasRoles — no schema impact)"
tech-stack:
  added:
    - "spatie/laravel-permission ^7.4 (resolved 7.4.1)"
    - "spatie/laravel-package-tools 1.93.0 (transitive)"
  patterns:
    - "UUID polymorphic morph keys for Spatie permission tables (override default unsignedBigInteger)"
    - "Guard pinning belt-and-braces: config default_guard_name + model $guard_name property"
    - "Idempotent seeder pattern: forgetCachedPermissions → findOrCreate → forgetCachedPermissions"
    - "Idempotent artisan grant: findOrCreate permission/role + givePermissionTo (Spatie skip-on-duplicate)"
key-files:
  created:
    - "apps/web/config/permission.php (published from spatie + customised)"
    - "apps/web/database/migrations/2026_05_03_110000_create_permission_tables.php (UUID model_id)"
    - "apps/web/database/seeders/PermissionSeeder.php"
    - "apps/web/app/Console/Commands/MakeAdminCommand.php"
    - "apps/web/tests/Feature/Auth/MakeAdminCommandTest.php"
    - "apps/web/tests/Feature/Auth/PermissionSeederTest.php"
  modified:
    - "apps/web/composer.json (+spatie/laravel-permission)"
    - "apps/web/composer.lock"
    - "apps/web/app/Models/User.php (+HasRoles trait, +$guard_name='web')"
    - "apps/web/database/seeders/DatabaseSeeder.php (replaced stale Test User factory call with PermissionSeeder)"
decisions:
  - "Set default_guard_name='web' in config/permission.php AND $guard_name='web' on User model (defence in depth — Pitfall 4)"
  - "Override published Spatie migration to use uuid('model_id') for both model_has_permissions + model_has_roles (D-002)"
  - "Re-grant all permissions to super-admin in MakeAdminCommand (defence-in-depth so command works on freshly migrated DB without seeder run)"
  - "Replaced obsolete DatabaseSeeder body (referenced User::factory()->create(['name' => …]) which is invalid after plan 10 — User has no name field)"
metrics:
  duration_minutes: 4
  tasks_completed: 2
  files_created: 6
  files_modified: 4
  tests_added: 6
completed_date: 2026-05-04
---

# Phase 01 Plan 11: spatie/laravel-permission install + RBAC seeding + make-admin command Summary

## One-liner

spatie/laravel-permission v7.4.1 installed with UUID-compatible morph keys, web-guard-pinned config (Pitfall 4), seeded admin-access + audit.view permissions + super-admin role, and an idempotent `trenchwars:make-admin` artisan command — Filament panel gating prerequisite (plan 12) is fully unblocked.

## What was built

**Task 1 — Permission infra + seeder (commit `e6410c5`):**
- `composer require spatie/laravel-permission:^7.4` (resolved 7.4.1) inside the web container per D-021.
- Published `config/permission.php` + `2026_05_03_110000_create_permission_tables.php` (renamed from auto-generated timestamp to project conventions).
- Customised migration: replaced `unsignedBigInteger('model_id')` with `uuid('model_id')` on both `model_has_permissions` and `model_has_roles` so polymorphic morph relations work with our UUID-PK User model (D-002).
- Pinned `default_guard_name => 'web'` in `config/permission.php` — Pitfall 4 mitigation: without this, Spatie permission cache keys can derive from a different guard than Filament queries, silently breaking admin authorisation.
- Added `use HasRoles;` trait + `protected string $guard_name = 'web';` on the User model (belt-and-braces guard pinning per CLAUDE.md §6).
- `PermissionSeeder` seeds `admin-access` (gates Filament panel — plan 12) and `audit.view` (gates /admin/audit — plan 14), creates `super-admin` role with all permissions, and `cms-editor` placeholder role for Phase 7+. Idempotent via `forgetCachedPermissions` + `findOrCreate`.
- `DatabaseSeeder` updated to call `PermissionSeeder` (the prior `User::factory()->create(['name' => …])` call was broken — User has no `name` field after plan 10).

**Task 2 — make-admin command (RED `0d09d82` → GREEN `2acba6b`):**
- `App\Console\Commands\MakeAdminCommand` with signature `trenchwars:make-admin {discord_id}`.
- Locates user by `discord_id`; emits `User with discord_id=… not found` and exits 1 if missing (operator hint about Discord login).
- Idempotent grant: `findOrCreate` permission + role (defence-in-depth so the command works without prior seeder run), then `givePermissionTo('admin-access')` + `assignRole('super-admin')`. Spatie's grant ops are no-ops on duplicates.
- Two new test files cover all 3 documented behaviours (not-found / happy-path / idempotent) plus the seeder contract (3 tests). 6 tests, 16 assertions, all green.

**Style cleanup (commit `e52c8f9`):**
- Pint flagged the published Spatie migration for `class_attributes_separation` — long `->primary([...], 'name')` lines were reformatted onto multiple lines. No behavior change.

## Verification

- `make pest` — 28 tests, 97 assertions, all passing (full suite — no regressions across i18n, models, auth, inertia smoke).
- `make pint --test` — 54 files clean.
- `make phpstan` — Level 8, no errors.
- `php artisan list | grep trenchwars` — `trenchwars:make-admin` registered.
- Direct Postgres queries confirm 2 permissions + 2 roles, all `guard_name='web'`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Replaced broken DatabaseSeeder body**

- **Found during:** Task 1, when reading current DatabaseSeeder.php
- **Issue:** The pre-existing `DatabaseSeeder` called `User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com'])`. After plan 10, the User model exposes no `name` field — only `discord_id`, `username`, `email`, etc. Running `php artisan db:seed` (or `migrate:fresh --seed`) would have raised an "Unknown column" or fillable error.
- **Fix:** Replaced the body with the plan-specified `$this->call([PermissionSeeder::class])` (which the plan already required anyway).
- **Files modified:** `apps/web/database/seeders/DatabaseSeeder.php`
- **Commit:** `e6410c5`

**2. [Rule 1 — Style] Pint reformat of published Spatie migration**

- **Found during:** Final verification gate (`pint --test`).
- **Issue:** The published Spatie v7 migration uses long single-line `->primary([...], 'name')` calls that violate the project's `class_attributes_separation` Pint rule.
- **Fix:** `pint database/migrations/2026_05_03_110000_create_permission_tables.php` — formatting only, no behavior change.
- **Files modified:** `apps/web/database/migrations/2026_05_03_110000_create_permission_tables.php`
- **Commit:** `e52c8f9`

No architectural changes (Rule 4) needed.

## Auth Gates

None — all operations were inside the web container; no external services required.

## Threat Flags

None — this plan implements the mitigations called out in the threat model exactly:
- T-1-04 (Filament panel access bypass) — `default_guard_name='web'` config + `$guard_name='web'` model property in place; plan 12 will wire `canAccessPanel()`.
- T-1-26 (mass-assignment to permissions table) — only the authoritative spatie API is used (`givePermissionTo`, `assignRole`); no controllers / fillable on `model_has_permissions`.
- T-1-27 (admin grant via shell) — accepted; activity_log (plan 14) will capture subsequent admin actions.

## Commits

| Hash | Type | Message |
|------|------|---------|
| `e6410c5` | feat | install spatie/laravel-permission v7 + permissions infra |
| `0d09d82` | test | add failing tests for make-admin command + permission seeder (RED) |
| `2acba6b` | feat | implement trenchwars:make-admin artisan command (GREEN) |
| `e52c8f9` | style | pint class_attributes_separation on permission migration |

## Self-Check: PASSED

All claimed files exist on disk; all claimed commits are reachable in `git log`.

- FOUND: apps/web/config/permission.php
- FOUND: apps/web/database/migrations/2026_05_03_110000_create_permission_tables.php
- FOUND: apps/web/database/seeders/PermissionSeeder.php
- FOUND: apps/web/app/Console/Commands/MakeAdminCommand.php
- FOUND: apps/web/tests/Feature/Auth/MakeAdminCommandTest.php
- FOUND: apps/web/tests/Feature/Auth/PermissionSeederTest.php
- FOUND: e6410c5
- FOUND: 0d09d82
- FOUND: 2acba6b
- FOUND: e52c8f9
