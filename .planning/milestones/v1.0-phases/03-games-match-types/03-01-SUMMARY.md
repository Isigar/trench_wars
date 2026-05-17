---
phase: 03-games-match-types
plan: 01
subsystem: scaffolding
tags:
  - wave-0
  - factories
  - pest-stubs
  - i18n
dependency_graph:
  requires: []
  provides:
    - "4 throw-on-use factory stubs (Game/GameRole/GameMatchType/GameMatchTypeRoleLimit)"
    - "7 RED Pest stubs proving Phase 3 test surface is wired"
    - "admin.* lang keys for the 4 Phase 3 resources + admin.nav.platform"
  affects:
    - "apps/web/database/factories/*"
    - "apps/web/tests/Feature/Models/Game*.php"
    - "apps/web/tests/Feature/Database/"
    - "apps/web/tests/Feature/Admin/GameResourcesPresentTest.php"
    - "apps/web/tests/Unit/Data/GameDataTest.php"
    - "apps/web/lang/en/admin.php"
tech_stack:
  added: []
  patterns:
    - "Wave 0 RuntimeException-throwing factory stubs (analog: Phase 2 plan 02-01)"
    - "Wave 0 RED Pest stubs with literal 'placeholder' string for phase-close grep audit (threat-mitigation T-03-01-01)"
    - "class_exists() proof-of-existence assertions (no `use` import of missing classes)"
    - "Per-line @phpstan-ignore-next-line for documented temporary type elisions"
key_files:
  created:
    - apps/web/database/factories/GameFactory.php
    - apps/web/database/factories/GameRoleFactory.php
    - apps/web/database/factories/GameMatchTypeFactory.php
    - apps/web/database/factories/GameMatchTypeRoleLimitFactory.php
    - apps/web/tests/Feature/Models/GameModelTest.php
    - apps/web/tests/Feature/Models/GameRoleModelTest.php
    - apps/web/tests/Feature/Models/GameMatchTypeModelTest.php
    - apps/web/tests/Feature/Models/GameMatchTypeRoleLimitModelTest.php
    - apps/web/tests/Feature/Database/GameSeederTest.php
    - apps/web/tests/Feature/Admin/GameResourcesPresentTest.php
    - apps/web/tests/Unit/Data/GameDataTest.php
  modified:
    - apps/web/lang/en/admin.php
decisions:
  - "Drop the PHPDoc `@extends Factory<\\App\\Models\\X>` generic from Wave 0 factory stubs and use per-line `@phpstan-ignore-next-line` instead. Plan 03-03 restores the generics when the real models land. CLAUDE.md §3 forbids baseline regeneration here, and creating empty marker model classes would prematurely flip the RED `class_exists()` assertions in Models tests to GREEN, breaking the Wave 0 contract."
metrics:
  duration_seconds: 289
  duration_human: "~5 minutes"
  completed_at: "2026-05-13T11:36:04Z"
  commits:
    - 1d4d736
    - 5a2457f
---

# Phase 3 Plan 01: Wave 0 Scaffolding Summary

Land the Wave 0 scaffold for Phase 3: 4 throw-on-use factory stubs + 7 RED Pest stubs proving the test surface is wired + admin.php key-group extension so downstream Filament resources can call `__()` without `MissingTranslationException`.

## Objective Achieved

Phase 3 test surface is wired — `make pest ARGS="--filter=Game"` discovers 7 RED tests (not "no tests found"); each failing test calls `class_exists()` on the class plan 03-XX will land. The 4 factory stubs throw `RuntimeException('… Wave 0 stub — replaced by plan 03-03.')` if accidentally invoked. `apps/web/lang/en/admin.php` now hosts 4 new top-level key groups (`game`, `game_role`, `game_match_type`, `game_match_type_role_limit`), a new `nav.platform` entry, and 4 new `audit.subject` entries — all matching the Phase 2 `clan_*` key shape verbatim.

## Wave 0 Stub Map

### Factory stubs (4 files) — replaced by plan 03-03

| File | Replaced by |
|------|-------------|
| `apps/web/database/factories/GameFactory.php` | plan 03-03 (Wave 2) |
| `apps/web/database/factories/GameRoleFactory.php` | plan 03-03 (Wave 2) |
| `apps/web/database/factories/GameMatchTypeFactory.php` | plan 03-03 (Wave 2) |
| `apps/web/database/factories/GameMatchTypeRoleLimitFactory.php` | plan 03-03 (Wave 2) |

Each factory throws `RuntimeException` from `definition()`. The `$model` property uses a **string FQN** (`'App\\Models\\Game'`) so PHP does not eager-load the not-yet-existing class — see deviation note below.

### Pest RED stubs (7 files)

| File | Asserts (currently RED) | Replaced by |
|------|-------------------------|-------------|
| `tests/Feature/Models/GameModelTest.php` | `class_exists('App\\Models\\Game')` | plan 03-03 (Wave 2) |
| `tests/Feature/Models/GameRoleModelTest.php` | `class_exists('App\\Models\\GameRole')` | plan 03-03 (Wave 2) |
| `tests/Feature/Models/GameMatchTypeModelTest.php` | `class_exists('App\\Models\\GameMatchType')` | plan 03-03 (Wave 2) |
| `tests/Feature/Models/GameMatchTypeRoleLimitModelTest.php` | `class_exists('App\\Models\\GameMatchTypeRoleLimit')` | plan 03-03 (Wave 2) |
| `tests/Feature/Database/GameSeederTest.php` | `class_exists('Database\\Seeders\\GameSeeder')` | plan 03-05 (Wave 3) |
| `tests/Feature/Admin/GameResourcesPresentTest.php` | `class_exists('App\\Filament\\Resources\\GameResource')` | plan 03-08 (Wave 6) |
| `tests/Unit/Data/GameDataTest.php` | `class_exists('App\\Data\\GameData')` | plan 03-04 (Wave 2) |

Every `it()` description contains the literal string `placeholder` so the phase-close grep audit (threat-mitigation T-03-01-01) detects any un-replaced stub.

The `tests/Feature/Database/` directory did not previously exist — it was created via the first test file. `phpunit.xml` already globs `tests/Feature/**` recursively, so the new sub-directory is auto-discovered without `phpunit.xml` changes.

The `GameResourcesPresentTest.php` stub also ships the `beforeEach()` admin-permission-seed block from Phase 2's `ClanResourcesPresentTest.php` so plan 03-08's replacement is purely additive (adding `it()` blocks for each `/admin/games`, `/admin/game-match-types` route check).

## admin.php Key-Group Delta

| Group | Type | Example Keys |
|-------|------|--------------|
| `admin.game.*` | NEW | `label`, `plural_label`, `section.{profile,roles,match_types}`, `fields.{key,name,name_locale,name_text,is_active}`, `help.key_format`, `tab.{profile,roles,match_types,audit}` |
| `admin.game_role.*` | NEW | `label`, `plural_label`, `fields.{key,display_name,display_name_locale,display_name_text,sort_order,is_active}`, `help.key_format` |
| `admin.game_match_type.*` | NEW | `label`, `plural_label`, `section.{profile,role_limits}`, `fields.{key,name,name_locale,name_text,description,description_locale,description_text,is_active,game}`, `help.key_format`, `tab.{profile,role_limits,audit}` |
| `admin.game_match_type_role_limit.*` | NEW | `label`, `plural_label`, `fields.{role,capacity,sort_order}`, `help.{capacity_min_zero,role_scope}` |
| `admin.audit.subject.*` | EXTENDED | +4 entries: `Game`, `GameRole`, `GameMatchType`, `GameMatchTypeRoleLimit` (short-name keys mirroring existing `Clan`, `ClanTag`, etc.) |
| `admin.nav.*` | NEW | `platform` (handle for the Filament `navigationGroup()` used in plans 03-06/03-07) |

Placeholder English copy — plan 03-09 will finalise the audit per threat-mitigation T-03-01-02.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking issue] Factory PHPDoc `@extends` generic dropped; per-line `@phpstan-ignore` substituted.**

- **Found during:** Task 1 (PHPStan run after writing the 4 factory stubs)
- **Issue:** The plan's acceptance criteria required `/** @extends Factory<\App\Models\Game> */` PHPDoc generics on every factory class plus a typed `protected $model` property. PHPStan L8 (Larastan) walks the generic and complains:
  - `class.notFound: PHPDoc tag @extends has invalid type App\Models\Game` (model class does not exist yet — plan 03-03 creates it)
  - `generics.notSubtype: Type App\Models\Game in generic type Factory<App\Models\Game> is not subtype of TModel of Model` (PHPStan can't even compare to `Model` because the class is missing)
  - `property.defaultValue: $model (class-string<TModel>) does not accept default value of type string`
- **Why we cannot use the obvious workaround:** Creating empty `class Game extends Model {}` marker files in `app/Models/` would resolve PHPStan but would also flip the RED `expect(class_exists('App\\Models\\Game'))->toBeTrue()` assertion in `GameModelTest.php` to GREEN, defeating the Wave 0 RED gate. CLAUDE.md §3 forbids regenerating `phpstan-baseline.neon` without explicit user request.
- **Fix:** Drop the `@extends` generic from the class docblock; drop the `class-string` type on `$model`; add `@phpstan-ignore-next-line missingType.generics` above the class and `@phpstan-ignore-next-line property.defaultValue` above the `$model` declaration. Each factory now carries a deviation note in the class docblock pointing plan 03-03 at the lines it must restore. The `$model` property still uses the string FQN form so PHP does not eager-load the missing class — that part of the plan's approach is preserved.
- **Files modified:** all 4 factory stubs (`GameFactory.php`, `GameRoleFactory.php`, `GameMatchTypeFactory.php`, `GameMatchTypeRoleLimitFactory.php`)
- **Commit:** 1d4d736
- **Forward-compat note for plan 03-03:** when adding the `Game` / `GameRole` / `GameMatchType` / `GameMatchTypeRoleLimit` models, plan 03-03 must (a) remove both `@phpstan-ignore-next-line` annotations, (b) restore the PHPDoc `@extends Factory<\App\Models\X>` generic above each factory, (c) flip the `$model` string FQN to `X::class`, and (d) add the `use App\Models\X;` import. This mirrors the Phase 2 plan 02-03 transition Phase 2 plan 02-01's ClanFactory underwent.

### Auth Gates

None — Wave 0 scaffolding makes no auth-related changes.

### Architectural Changes

None — Wave 0 is structural only.

## Verification Gates (all green)

| Gate | Command | Result |
|------|---------|--------|
| Pest discovery + RED gate | `make pest ARGS="--filter=Game"` | 7 failed (assertions), 0 errors, 0 "no tests found" — exactly the Wave 0 RED contract |
| I18n no-regression | `make pest ARGS="--filter=I18n"` | 7 passed (44 assertions) |
| Static analysis | `make phpstan` | `[OK] No errors` (16 → 0) |
| Code style | `make pint ARGS="--test"` | 195 files PASS (full repo) |
| Key resolution | walk `lang/en/admin.php` for all 14 Phase 3 keys | 14/14 OK |

## Lessons Learned

1. **PHPStan generics + missing classes is an irreducible conflict.** Phase 2 plan 02-01 used the "string FQN" trick to keep the runtime lazy but did NOT keep the PHPDoc `@extends` generic — looking back at the current `ClanFactory.php`, the generic is `Factory<Clan>` (real class). The Phase 3 plan was authored under the assumption that PHPStan would not validate generic arguments when the property is typed `class-string` only; in practice it does. Future Wave 0 plans should not require the `@extends Factory<\App\Models\X>` PHPDoc generic at all — they should either (a) omit it entirely or (b) gate the entire factory file behind a per-line `@phpstan-ignore-next-line missingType.generics`. This plan now records that lesson via the deviation.

2. **The `placeholder` literal in stub `it()` descriptions is load-bearing.** Threat-mitigation T-03-01-01 (in this plan's threat model) explicitly says "Each `it()` block contains the literal string `placeholder` so a grep audit at phase close detects un-replaced stubs." Phase 2's stubs used `"placeholder Wave 0 stub - replace in Wave N"` with the exact same `placeholder` token; this plan adopted the same pattern (`"placeholder — Wave 0 RED stub replaced by plan 03-XX"`) so the existing audit grep continues to work.

3. **`tests/Feature/Database/` auto-discovers via phpunit.xml `<directory>` glob.** No `phpunit.xml` change was needed for the new sub-directory — the Phase 1 testsuite glob already recurses into `tests/Feature/**`.

## Threat Flags

None — Wave 0 scaffolding does not introduce new network endpoints, auth surface, or trust boundaries. Threats T-03-01-01 and T-03-01-02 in the plan's threat model are addressed by:
- T-03-01-01 mitigated: every stub `it()` description includes the literal `placeholder` token (phase-close grep audit)
- T-03-01-02 accepted: plan 03-09 will run the canonical i18n audit on the placeholder English copy

## Known Stubs

All 7 RED Pest stubs and all 4 throw-on-use factory stubs are **intentional Wave 0 placeholders** by the plan's design, not unintended stubs. Each is mapped to its replacement plan in the table above, and the literal `placeholder` token in every `it()` description supports the threat-mitigation grep audit at phase close.

## Self-Check: PASSED

**Created files exist (11 + 1 modified):**

- FOUND: apps/web/database/factories/GameFactory.php
- FOUND: apps/web/database/factories/GameRoleFactory.php
- FOUND: apps/web/database/factories/GameMatchTypeFactory.php
- FOUND: apps/web/database/factories/GameMatchTypeRoleLimitFactory.php
- FOUND: apps/web/tests/Feature/Models/GameModelTest.php
- FOUND: apps/web/tests/Feature/Models/GameRoleModelTest.php
- FOUND: apps/web/tests/Feature/Models/GameMatchTypeModelTest.php
- FOUND: apps/web/tests/Feature/Models/GameMatchTypeRoleLimitModelTest.php
- FOUND: apps/web/tests/Feature/Database/GameSeederTest.php
- FOUND: apps/web/tests/Feature/Admin/GameResourcesPresentTest.php
- FOUND: apps/web/tests/Unit/Data/GameDataTest.php
- FOUND (modified): apps/web/lang/en/admin.php

**Commits exist:**

- FOUND: 1d4d736 — feat(03-01): scaffold Wave 0 — 4 factory stubs + 7 RED Pest stubs
- FOUND: 5a2457f — feat(03-01): extend lang/en/admin.php with Phase 3 key groups
