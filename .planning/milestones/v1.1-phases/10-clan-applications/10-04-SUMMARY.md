---
phase: 10-clan-applications
plan: "04"
subsystem: clan-applications
tags: [dto, form, filament, i18n, tdd, recruiting-toggle]
dependency_graph:
  requires:
    - clans.accepts_applications column + Clan boolean cast (plan 10-01)
    - clans.form.accepts_applications.{label,hint} i18n keys (plan 10-02)
  provides:
    - ClanData.accepts_applications bool property (DTO + fromModel)
    - App.Data.ClanData.accepts_applications: boolean in shared-types api.d.ts
    - UpdateClanProfileRequest accepts_applications boolean rule (whitelist)
    - MyClan profile tab accepts_applications checkbox (profileForm field + template)
    - ClanResource Toggle::make('accepts_applications') Filament admin form
    - admin.clan.fields.accepts_applications + accepts_applications_help i18n keys
  affects:
    - apps/web/app/Data/ClanData.php
    - apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php
    - apps/web/resources/js/pages/MyClan/Index.vue
    - apps/web/app/Filament/Resources/ClanResource.php
    - apps/web/lang/en/admin.php
    - apps/web/resources/js/types/api.d.ts
    - packages/shared-types/src/api.d.ts
tech_stack:
  added: []
  patterns:
    - TDD RED→GREEN for DTO field + request rule (test commit before implementation commit)
    - spatie/laravel-data ClanData::fromModel pattern (add field to constructor + fromModel)
    - UpdateClanProfileRequest 'sometimes' + 'boolean' PATCH-semantics whitelist
    - Filament Toggle::make() pattern (mirrors discord_advanced_fields_enabled idiom)
    - Native HTML checkbox with t() i18n labels (no shared Toggle/Checkbox component)
    - artisan typescript:transform + sync-types.sh for shared-types regen
key_files:
  created:
    - apps/web/tests/Feature/Clans/ClanAcceptsApplicationsToggleTest.php
  modified:
    - apps/web/app/Data/ClanData.php
    - apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php
    - apps/web/resources/js/pages/MyClan/Index.vue
    - apps/web/app/Filament/Resources/ClanResource.php
    - apps/web/lang/en/admin.php
    - apps/web/resources/js/types/api.d.ts
    - packages/shared-types/src/api.d.ts
decisions:
  - "accepts_applications placed after status in ClanData constructor to preserve field declaration order matching the clans table column order (migration adds after status)"
  - "Native HTML checkbox chosen for MyClan profile tab — no shared Toggle/Checkbox component exists (only Button/TextInput/Textarea/Select); plan notes explicitly state this"
  - "sync-types.sh run after artisan typescript:transform to propagate api.d.ts to packages/shared-types/src/api.d.ts (two-step pattern matching the existing D-020 convention)"
  - "T-10-04-02 preserved: discord_role_id remains absent from UpdateClanProfileRequest::rules(); only accepts_applications is added"
metrics:
  duration: "120s"
  completed: "2026-06-04"
  tasks_completed: 2
  files_changed: 7
requirements: [CLAN-04]
---

# Phase 10 Plan 04: Recruiting Toggle Surface Summary

**One-liner:** `ClanData.accepts_applications` bool DTO field + shared-types regen + `UpdateClanProfileRequest` boolean rule + MyClan native checkbox + Filament `Toggle::make` + admin i18n keys, completing the CLAN-04 leader self-service toggle surface.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 (RED) | Failing tests: ClanData DTO field + leader toggle + non-member 403 | af2ed73 | apps/web/tests/Feature/Clans/ClanAcceptsApplicationsToggleTest.php |
| 1 (GREEN) | ClanData.accepts_applications + UpdateClanProfileRequest rule + shared-types regen | 8fca05d | apps/web/app/Data/ClanData.php, UpdateClanProfileRequest.php, api.d.ts (2 locations) |
| 2 | MyClan checkbox + Filament Toggle + admin i18n | 1fe1c72 | MyClan/Index.vue, ClanResource.php, admin.php |

## What Was Built

### Task 1 — ClanData DTO + request rule + tests + shared-types

**`ClanData.php`:** Added `public bool $accepts_applications` to the constructor (after `$status`) and `accepts_applications: $clan->accepts_applications` to `fromModel()`. The existing `#[TypeScript]` attribute causes the transformer to emit the field.

**`UpdateClanProfileRequest.php`:** Added `'accepts_applications' => ['sometimes', 'boolean']` to `rules()`. The `'sometimes'` PATCH-semantics rule means the field is only validated (and included in `validated()`) when present in the request. `MyClanProfileController::update()` calls `$clan->update($request->validated())` with no change — the whitelist addition is sufficient.

**Shared-types regen:** `make artisan ARGS="typescript:transform"` wrote `accepts_applications: boolean` to `apps/web/resources/js/types/api.d.ts`. `sync-types.sh` propagated it to `packages/shared-types/src/api.d.ts`.

**`ClanAcceptsApplicationsToggleTest.php`:** 4 Pest tests:
- Leader PATCHes `accepts_applications=false` → assertRedirect + `$clan->fresh()->accepts_applications` toBeFalse
- Non-member PATCHes → 403 + value unchanged (T-10-04-01)
- `ClanData::fromModel()` returns bool type
- `ClanData::fromModel()` reflects false when model has false

### Task 2 — MyClan checkbox + Filament Toggle + admin i18n

**`MyClan/Index.vue`:**
- `profileForm` extended with `accepts_applications: props.clan?.accepts_applications ?? true`
- In profile tab, after country TextInput and before Save button: a `<div class="flex flex-col gap-1">` containing a `<label class="flex items-center gap-2">` with `<input type="checkbox" v-model="profileForm.accepts_applications" />` + `<span>{{ t('clans.form.accepts_applications.label') }}</span>`, and a hint `<p>{{ t('clans.form.accepts_applications.hint') }}</p>`. Both strings via `t()` — D-013 compliant. `saveProfile()` submits the whole `profileForm` unmodified.

**`ClanResource.php`:** Added `Forms\Components\Toggle::make('accepts_applications')->label(__('admin.clan.fields.accepts_applications'))->helperText(__('admin.clan.fields.accepts_applications_help'))->default(true)` after the `tags` Select and before the Discord-advanced toggle. The field is fillable + boolean-cast (plan 10-01), so Filament round-trips it with no `dehydrated()` override needed.

**`admin.php`:** Added to `clan.fields`:
- `'accepts_applications' => 'Accept applications'`
- `'accepts_applications_help' => 'When off, users cannot apply to join this clan.'`

## Gate Results

| Gate | Result |
|------|--------|
| `make pest --filter=ClanAcceptsApplicationsToggleTest` | PASS (4 passed, 6 assertions) |
| `make pest --filter=NoHardcodedStrings` | PASS (1 passed, 1 assertion) |
| `make artisan ARGS="typescript:transform"` + `sync-types.sh` | PASS (accepts_applications: boolean in api.d.ts) |
| `vue-tsc --noEmit` | PASS (no errors) |
| `make phpstan` L8 | PASS (No errors — 422 files) |
| `make pint --test` | PASS (662 files) |

## TDD Gate Compliance

- RED gate: commit `af2ed73` (`test(10-04)`) — tests failed as expected (3 failures on DTO property, 1 pass on redirect)
- GREEN gate: commit `8fca05d` (`feat(10-04)`) — all 4 tests pass

## Deviations from Plan

**[Rule 3 - Fix] sync-types.sh run after typescript:transform**
- **Found during:** Task 1 verification
- **Issue:** `make typescript-transform` is not a registered Makefile target; `make artisan ARGS="typescript:transform"` runs the artisan command in-container but only updates `apps/web/resources/js/types/api.d.ts`. `packages/shared-types/src/api.d.ts` is a separate copy synced via `sync-types.sh`. The plan notes "only touch it if the sync command reports a missing export" but the two files diverged until the sync ran.
- **Fix:** Ran `bash packages/shared-types/scripts/sync-types.sh` after the artisan command to propagate the updated type definition.
- **Files modified:** packages/shared-types/src/api.d.ts
- **Commit:** 8fca05d (same GREEN commit)

## Known Stubs

None — all fields are wired to real model data. The accepts_applications checkbox in MyClan reads `props.clan?.accepts_applications ?? true` (real value from ClanData DTO) and submits through the whitelisted request rule to the database.

## Threat Flags

No new security surface beyond the plan's threat model:
- T-10-04-01 mitigated: 403 test confirms `UpdateClanProfileRequest::authorize()` (via ClanPolicy::update) blocks non-members.
- T-10-04-02 mitigated: only `accepts_applications` added to `rules()`; `discord_role_id` and other fields remain excluded from `validated()`.

## Self-Check: PASSED

Files confirmed present:
- apps/web/app/Data/ClanData.php — FOUND (modified, 2 occurrences of accepts_applications)
- apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php — FOUND (modified, 1 rule)
- apps/web/resources/js/pages/MyClan/Index.vue — FOUND (modified, 4 occurrences)
- apps/web/app/Filament/Resources/ClanResource.php — FOUND (Toggle::make present)
- apps/web/lang/en/admin.php — FOUND (2 keys)
- apps/web/tests/Feature/Clans/ClanAcceptsApplicationsToggleTest.php — FOUND
- packages/shared-types/src/api.d.ts — FOUND (accepts_applications: boolean)

Commits confirmed:
- af2ed73 — FOUND (RED test)
- 8fca05d — FOUND (GREEN impl)
- 1fe1c72 — FOUND (Vue + Filament + i18n)
