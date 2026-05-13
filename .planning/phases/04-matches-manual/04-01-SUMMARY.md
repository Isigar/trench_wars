---
phase: 04-matches-manual
plan: 01
subsystem: matches
tags: [phase-4, wave-0, scaffolding, factory-stubs, red-stubs, i18n]
dependency_graph:
  requires: [03-matches-types-complete, phase-3-summary]
  provides:
    - phase-4-wave-0-baseline
    - red-stub-targets-for-04-02-through-04-13
    - matches-i18n-key-surface
    - admin-match-resource-i18n-key-surface
  affects:
    - apps/web/database/factories/ (6 new throw-on-use factory stubs)
    - apps/web/tests/Feature/ (3 new subdirs + 19 stub files)
    - apps/web/tests/Unit/Data/ (3 new stub files)
    - apps/web/lang/en/matches.php (NEW)
    - apps/web/lang/en/admin.php (6 new top-level groups appended)
tech_stack:
  added: []
  patterns:
    - throw-on-use-factory-stub
    - markTestIncomplete-RED-stub
    - phpstan-ignore-next-line-for-future-class
    - i18n-key-precommit-pattern
key_files:
  created:
    - apps/web/database/factories/MatchFactory.php
    - apps/web/database/factories/MatchSlotFactory.php
    - apps/web/database/factories/MatchAccessRuleFactory.php
    - apps/web/database/factories/MatchResultFactory.php
    - apps/web/database/factories/MatchMvpFactory.php
    - apps/web/database/factories/EventFactory.php
    - apps/web/lang/en/matches.php
    - apps/web/tests/Feature/Models/MatchModelTest.php
    - apps/web/tests/Feature/Models/MatchSlotModelTest.php
    - apps/web/tests/Feature/Models/MatchAccessRuleModelTest.php
    - apps/web/tests/Feature/Models/MatchResultModelTest.php
    - apps/web/tests/Feature/Models/MatchMvpModelTest.php
    - apps/web/tests/Feature/Models/EventModelTest.php
    - apps/web/tests/Feature/Services/MatchSlotMaterialiserServiceTest.php
    - apps/web/tests/Feature/Services/MatchSignupServiceTest.php
    - apps/web/tests/Feature/Services/MatchSignupConcurrencyTest.php
    - apps/web/tests/Feature/Services/MatchStatusServiceTest.php
    - apps/web/tests/Feature/Services/MatchResultServiceTest.php
    - apps/web/tests/Feature/Matches/MatchCalendarPageTest.php
    - apps/web/tests/Feature/Matches/MatchShowPageTest.php
    - apps/web/tests/Feature/Matches/MatchSignupControllerTest.php
    - apps/web/tests/Feature/Matches/MatchSignupTagRestrictedTest.php
    - apps/web/tests/Feature/Admin/MatchResourcePresentTest.php
    - apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php
    - apps/web/tests/Feature/Admin/MatchAuditLogTest.php
    - apps/web/tests/Feature/Observers/MatchEventSyncTest.php
    - apps/web/tests/Unit/Data/MatchDataTest.php
    - apps/web/tests/Unit/Data/PublicMatchDataTest.php
    - apps/web/tests/Unit/Data/EventDataTest.php
  modified:
    - apps/web/lang/en/admin.php
decisions:
  - id: D-04-01-A
    decision: |
      Reuse the Phase 3 Wave 0 factory-stub idiom: string-literal $model FQN
      (`'App\\Models\\Match'` etc.) combined with per-line `@phpstan-ignore-next-line`
      annotations covering `missingType.generics` (on the class docblock) and
      `property.defaultValue` (on the $model property). Mirrors commit 1d4d736.
      Rationale: CLAUDE.md §3 forbids regenerating phpstan-baseline.neon here,
      and the canonical `@extends Factory<\App\Models\Match>` + typed `$model`
      both fail PHPStan L8 against the as-yet-uncreated model classes. Plan 04-03
      MUST remove these ignores when the real model files land.
  - id: D-04-01-B
    decision: |
      `Match` model FQN is the singular `App\Models\Match` (Pitfall 5 / Assumption
      A4 — legal PHP 8 class identifier; clashes with the lowercase `match` keyword
      are illegal but PascalCase is not). Factory file is `MatchFactory.php`.
      Adoption of RESEARCH Recommendation B (keep `Match`, header docblock warns).
  - id: D-04-01-C
    decision: |
      `pcntl` is present in the trenchwars-web container — verified via
      `docker compose exec web php -m | grep pcntl`. Plan 04-06 SC-2 concurrency
      test can use `pcntl_fork()` (Pitfall 4 primary path); the dual-DB-connection
      fallback (Pitfall 4 option 2) is not needed on this image. The header
      comment block in MatchSignupConcurrencyTest.php records this verification
      for plan 04-06 implementers.
metrics:
  duration_minutes: 6
  completed: 2026-05-13
---

# Phase 4 Plan 01: Wave 0 Scaffolding Summary

**One-liner:** Wave 0 baseline for Phase 4 — 6 throw-on-use factory stubs, 22 Pest RED test stubs across 6 test subdirs, and the matches.php + admin.php i18n key surface that plans 04-02 through 04-13 will flip GREEN cluster by cluster.

## What Shipped

### 6 factory stubs

Each factory throws `RuntimeException` from `definition()`, so any later-wave test accidentally calling `Factory::factory()->create()` before plan 04-03 lands surfaces as an immediate fatal (T-04-01-01 mitigation). The `$model` attribute is a string FQN because the model class file doesn't yet exist — plan 04-03 switches to `::class` and removes the `@phpstan-ignore` lines.

| File | $model FQN string | Flipped GREEN by |
|------|-------------------|------------------|
| apps/web/database/factories/MatchFactory.php | `'App\\Models\\Match'` | 04-03 |
| apps/web/database/factories/MatchSlotFactory.php | `'App\\Models\\MatchSlot'` | 04-03 |
| apps/web/database/factories/MatchAccessRuleFactory.php | `'App\\Models\\MatchAccessRule'` | 04-03 |
| apps/web/database/factories/MatchResultFactory.php | `'App\\Models\\MatchResult'` | 04-03 |
| apps/web/database/factories/MatchMvpFactory.php | `'App\\Models\\MatchMvp'` | 04-03 |
| apps/web/database/factories/EventFactory.php | `'App\\Models\\Event'` | 04-03 |

### 22 Pest RED test stubs

Every stub is a single `it('placeholder — replace in plan 04-NN', fn() => $this->markTestIncomplete(...))` block. Pest reports them as **incomplete** (yellow WARN), not passed, so un-replaced stubs at phase-close are unambiguous (T-04-01-03 mitigation).

| Test stub path | Flipped GREEN by |
|----------------|------------------|
| apps/web/tests/Feature/Models/MatchModelTest.php | 04-03 |
| apps/web/tests/Feature/Models/MatchSlotModelTest.php | 04-03 |
| apps/web/tests/Feature/Models/MatchAccessRuleModelTest.php | 04-03 |
| apps/web/tests/Feature/Models/MatchResultModelTest.php | 04-03 |
| apps/web/tests/Feature/Models/MatchMvpModelTest.php | 04-03 |
| apps/web/tests/Feature/Models/EventModelTest.php | 04-03 |
| apps/web/tests/Feature/Services/MatchStatusServiceTest.php | 04-04 |
| apps/web/tests/Feature/Services/MatchSlotMaterialiserServiceTest.php | 04-05 |
| apps/web/tests/Feature/Services/MatchSignupServiceTest.php | 04-06 |
| apps/web/tests/Feature/Services/MatchSignupConcurrencyTest.php | 04-06 |
| apps/web/tests/Feature/Matches/MatchSignupTagRestrictedTest.php | 04-06 |
| apps/web/tests/Unit/Data/MatchDataTest.php | 04-07 |
| apps/web/tests/Unit/Data/PublicMatchDataTest.php | 04-07 |
| apps/web/tests/Unit/Data/EventDataTest.php | 04-07 |
| apps/web/tests/Feature/Observers/MatchEventSyncTest.php | 04-08 |
| apps/web/tests/Feature/Admin/MatchResourcePresentTest.php | 04-09 (revisited 04-12) |
| apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php | 04-09 |
| apps/web/tests/Feature/Admin/MatchAuditLogTest.php | 04-09 (revisited 04-12) |
| apps/web/tests/Feature/Services/MatchResultServiceTest.php | 04-09 |
| apps/web/tests/Feature/Matches/MatchCalendarPageTest.php | 04-10 |
| apps/web/tests/Feature/Matches/MatchShowPageTest.php | 04-10 |
| apps/web/tests/Feature/Matches/MatchSignupControllerTest.php | 04-10 |

### matches.php i18n key tree (NEW file — 14 keys)

```
matches.
├── signup.
│   ├── error.
│   │   ├── capacity_full        "Sorry — that role is full."
│   │   ├── tag_restricted       (tag-restricted refusal copy)
│   │   ├── already_signed_up    (idempotency refusal copy)
│   │   ├── not_open             (status-gated refusal copy)
│   │   └── no_active_clan       (D-009 active-membership refusal copy)
│   └── success                  "Signed up to :role."
├── status.error.invalid_transition   (with :from and :to interpolation)
├── calendar.
│   ├── title                    "Match calendar"
│   └── empty                    (empty-calendar copy)
└── show.
    ├── title_fallback
    ├── signup_button
    ├── cancel_signup_button
    ├── slot_open
    └── slot_taken_anonymous
```

### admin.php appended groups (6 new top-level keys)

| Group | Sub-keys | Used by |
|-------|----------|---------|
| `admin.match` | label, plural_label, section.{profile,audit}, wizard.{step_type,step_type_desc,step_schedule,step_schedule_desc,step_review,step_review_desc}, fields.{game_match_type,organiser,host_clan,server_address,scheduled_at,status,is_public,title,description}, actions.{open_signups,lock_signups,cancel} | MatchResource (04-09) + admin actions (04-09) + audit (04-12) |
| `admin.match_slot` | label, plural_label, fields.{game_role,slot_index,occupant_user,confirmed_at} | SlotsRelationManager (04-09) |
| `admin.match_access_rule` | label, plural_label, fields.clan_tag | AccessRulesRelationManager (04-09) |
| `admin.match_result` | label, plural_label, fields.{winner_clan,allies_score,axis_score,notes,recorded_by,recorded_at} | ResultRelationManager (04-09) |
| `admin.match_mvp` | label, plural_label, fields.{player,category,value} | MvpsRelationManager (04-09) |
| `admin.event` | label, plural_label, fields.{eventable,starts_at,ends_at,title,is_public} | EventResource (04-12) |

Existing groups (`admin.game.*`, `admin.clan.*`, `admin.user.*`, `admin.player.*`, `admin.audit.*`, etc.) preserved verbatim.

## Verification

| Gate | Result |
|------|--------|
| `make pest` | **22 incomplete + 278 passed**, 822 assertions, 11.75 s; zero regressions vs the 278-test Phase 3 baseline |
| `make pest --filter='Match\|EventModel\|EventData'` | 22 incomplete + 32 incidental passed |
| `make phpstan` | **0 errors** across 156 files |
| `make pint --test` (touched paths) | clean (8 + 42 files) |
| `docker compose exec web php -r "require 'lang/en/matches.php';"` | parses without warning, returns 4-key top-level array |
| pcntl extension (Assumption A8) | **PRESENT** in trenchwars-web container — primary `pcntl_fork()` path viable for plan 04-06 SC-2 |

## Decisions Made

- **D-04-01-A:** Adopt Phase 3 Wave 0 factory-stub idiom (string FQN + `@phpstan-ignore-next-line` on `missingType.generics` and `property.defaultValue`) — see decision block above.
- **D-04-01-B:** `App\Models\Match` is the canonical model FQN (singular, PascalCase legal) per Pitfall 5 / Assumption A4.
- **D-04-01-C:** pcntl is present; plan 04-06 uses pcntl_fork() (Pitfall 4 primary path), no fallback needed on this image.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] PHPStan L8 cannot type-check factories that reference non-existent model classes**
- **Found during:** Task 1 verification (`make phpstan` run after writing 6 factory stubs)
- **Issue:** Initial stub used `/** @var class-string */ protected $model = 'App\\Models\\Match';` and `@extends Factory<Model>` — PHPStan reported 18 errors on `missingType.generics`, `property.phpDocType` covariance, and `property.defaultValue` because `App\Models\Match` does not yet exist.
- **Fix:** Adopted the Phase 3 Wave 0 idiom from commit 1d4d736: replaced `@var class-string` with a class-level `@phpstan-ignore-next-line missingType.generics` (above the class declaration) and a property-level `@phpstan-ignore-next-line property.defaultValue` (above `$model`). CLAUDE.md §3 forbids regenerating `phpstan-baseline.neon`, so per-line ignores are the only available workaround. Plan 04-03 MUST remove these annotations and add the proper generic when the real model files land.
- **Files modified:** all 6 factory stubs (MatchFactory, MatchSlotFactory, MatchAccessRuleFactory, MatchResultFactory, MatchMvpFactory, EventFactory)
- **Commit:** 6e5024c

### Non-deviations (planned ambiguities resolved)

- **MatchSignupTagRestrictedTest plan-number mapping:** The plan body assigned this stub to "plan 04-06" via the action mapping ("MatchSignupServiceTest + MatchSignupConcurrencyTest + MatchSignupTagRestrictedTest: plan 04-06"). The acceptance-criteria block hinted at "MatchCalendarPageTest -> 04-10" but did not re-mention this file; followed the action mapping → 04-06. Plan 04-06's SC-5 tag-restricted behaviour confirms.
- **MatchResultServiceTest plan-number mapping:** Plan action mapping says 04-09 ("alongside the Filament ResultRelationManager"); used 04-09. Both 04-VALIDATION.md Per-Task Verification Map and the plan body agree.
- **Plan grep pattern `grep -c 'admin.match' apps/web/lang/en/admin.php`:** The plan's verification block uses `grep -c 'admin.match'`, but the file contains the Laravel array key `'match'` (not the dotted string `'admin.match'`). The data is structurally correct — `__('admin.match.label')` resolves correctly at runtime. The plan's grep was over-eager; ignored as a no-op false-negative.

## Auth Gates

None — Wave 0 is pure file scaffolding, no authentication or external service involved.

## Known Stubs

By design — this entire plan IS the stub set. All 22 test files use `markTestIncomplete()`, and all 6 factories throw `RuntimeException` from `definition()`. Each stub names the plan that flips it GREEN (see tables above). The Phase 4 Wave 0 invariant `wave_0_complete: true` (04-VALIDATION.md) is now satisfied.

No accidental stubs introduced — every placeholder is intentional and tracked.

## Threat Surface Notes

No new threat-flag surface. Threat register T-04-01-01..04 from the plan are all addressed by the chosen idioms:
- **T-04-01-01:** RuntimeException-on-definition() ensures any pre-04-03 factory call fatals.
- **T-04-01-02:** All 14 service-layer matches.* + 30+ admin.match*/admin.event.* keys exist before code references them; `__()` against a missing key returns the literal key string in production, which would silently ship a Discord-token-shaped UI bug.
- **T-04-01-03:** markTestIncomplete reports as WARN, not PASS, so un-replaced stubs at phase close are visible in the Pest summary line.
- **T-04-01-04:** pcntl pre-flight header comment in MatchSignupConcurrencyTest.php records the verification + fallback procedure for plan 04-06.

## Commits

| Hash | Task | Files | Lines |
|------|------|-------|-------|
| `6e5024c` | Task 1 — factory stubs + lang files | 8 | +340 |
| `8435020` | Task 2 — 22 Pest RED stubs | 22 | +303 |

## Self-Check: PASSED

- Factory files exist: 6/6 found
- Test stub files exist: 22/22 found
- matches.php exists and parses
- admin.php has all 6 new top-level groups (`match`, `match_slot`, `match_access_rule`, `match_result`, `match_mvp`, `event`)
- Existing admin.* groups preserved (`game`, `clan`, `user`, `player`, `audit`, …)
- pcntl comment present in MatchSignupConcurrencyTest.php
- Commit hashes 6e5024c and 8435020 exist in `git log --oneline -3`
- `make pest`: 22 incomplete, 278 passed, zero failures, zero regressions
- `make phpstan`: 0 errors
- `make pint --test` (touched paths): clean
