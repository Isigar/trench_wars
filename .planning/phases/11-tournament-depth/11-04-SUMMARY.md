---
phase: 11-tournament-depth
plan: 04
subsystem: tournaments
tags: [filament, laravel, tournaments, brackets, match-types, i18n]

requires:
  - phase: 11-01
    provides: "tournament_stages.game_match_type_id column + TournamentStage::gameMatchType BelongsTo + RED scaffold StageMatchTypeOverrideTest"
  - phase: 06-tournaments-brackets
    provides: "BracketMatchMaterialiserService, StagesRelationManager, TournamentBracket, TournamentStage models"
  - phase: 03-games-match-types
    provides: "GameMatchType model + Game::matchTypes() + Pattern 3 cross-game scoped Select idiom"

provides:
  - "BracketMatchMaterialiserService uses stage.game_match_type_id ?? tournament.default_game_match_type_id (TOUR-04)"
  - "StagesRelationManager: editable game_match_type_id Select scoped to tournament's game (Pattern 3)"
  - "StagesRelationManager: gameMatchType.key TextColumn with '—' placeholder"
  - "admin.tournament_stage.fields.game_match_type_id i18n label"

affects:
  - 11-05
  - future-materialiser-callers

tech-stack:
  added: []
  patterns:
    - "TOUR-04 stage override: stage.game_match_type_id ?? tournament.default_game_match_type_id in materialiser"
    - "Pattern 3 in StagesRelationManager: getOwnerRecord() returns Tournament; traverse ->game->matchTypes() for scoped Select"

key-files:
  created:
    - "apps/web/tests/Feature/Admin/StagesRelationManagerOverrideTest.php"
  modified:
    - "apps/web/app/Services/BracketMatchMaterialiserService.php"
    - "apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php"
    - "apps/web/lang/en/admin.php"
    - "apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php"

key-decisions:
  - "D-11-04-A: Materialiser stage resolution uses $locked->stage()->first() (query inside the transaction) rather than $locked->stage relation property — ensures the stage is fetched within the DB::transaction and lockForUpdate scope"
  - "D-11-04-B: PHPStan L8 prefers ternary null-check over ?-> nullsafe operator when it can prove the receiver is non-null (nullsafe.neverNull rule); used ($stage !== null) ? $stage->game_match_type_id : null pattern"
  - "D-11-04-C: StagesRelationManager getOwnerRecord() returns the parent Tournament (not the child TournamentStage); options closure traverses tournament->game->matchTypes() directly"
  - "D-11-04-D: First scoping test uses a unit approach (direct closure logic execution) rather than mountTableAction because ViewAction registers an infolist that conflicts with the form() signature in Filament v3.3 Livewire tests"

patterns-established:
  - "Pattern 3 reuse in StagesRelationManager: identical to RoleLimitsRelationManager but parent traversal is Tournament->game (not GameMatchType->game)"

requirements-completed: [TOUR-04]

duration: 350s
completed: 2026-06-04
---

# Phase 11 Plan 04: Stage-level GameMatchType override (TOUR-04) Summary

**Stage-level `game_match_type_id` override in `BracketMatchMaterialiserService` using `stage override ?? tournament default` + cross-game-scoped Filament Select on StagesRelationManager**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-06-04T11:22:14Z
- **Completed:** 2026-06-04T11:28:04Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- `BracketMatchMaterialiserService::materialiseFor()` now resolves the effective match type as `stage.game_match_type_id ?? tournament.default_game_match_type_id`; the all-null guard throws a `RuntimeException` mentioning both missing sources
- `StagesRelationManager` gains an editable `game_match_type_id` Select (Pattern 3 cross-game scoped) + `EditAction` + `gameMatchType.key` TextColumn; `ordinal`/`type`/`name` remain `->disabled()` (T-06-11-04 preserved)
- `admin.tournament_stage.fields.game_match_type_id` i18n label added; `NoHardcodedStrings` gate green

## Task Commits

1. **Task 1: Stage-override match-type resolution in the materialiser** - `4535ceb` (feat)
2. **Task 2: Cross-game-scoped Select + column on StagesRelationManager + i18n** - `af5f225` (feat)

## Files Created/Modified

- `apps/web/app/Services/BracketMatchMaterialiserService.php` - TOUR-04 resolution: stage override ?? tournament default; extended RuntimeException message
- `apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php` - Added third test case (both null throws with extended message); turned RED scaffold GREEN
- `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` - Added editable game_match_type_id Select (Pattern 3) + EditAction + TextColumn
- `apps/web/lang/en/admin.php` - Added `game_match_type_id => 'Match type override'` to tournament_stage.fields
- `apps/web/tests/Feature/Admin/StagesRelationManagerOverrideTest.php` - Created: cross-game scoping test + persist test + ordinal invariant test

## Decisions Made

- **D-11-04-A:** Used `$locked->stage()->first()` (query inside transaction) instead of `$locked->stage` relation property to ensure stage is fetched inside the `DB::transaction` + `lockForUpdate` scope.
- **D-11-04-B:** PHPStan L8 `nullsafe.neverNull` rule rejects `?->game_match_type_id` when it can prove the receiver non-null. Used explicit ternary `($stage !== null) ? $stage->game_match_type_id : null` pattern.
- **D-11-04-C:** `getOwnerRecord()` in StagesRelationManager returns the parent **Tournament** (not the TournamentStage). Options closure traverses `tournament->game->matchTypes()` — no stage traversal needed.
- **D-11-04-D:** First scoping test uses unit approach (executes closure logic directly against fixtured Tournament) rather than `mountTableAction` because `ViewAction` registers an infolist schema that conflicts with the `form(Form $form)` signature in Filament v3.3 Livewire tests (TypeError: `Infolist` passed where `Form` expected).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `TournamentStage` import removed by Pint (unused after PHPStan-driven refactor)**
- **Found during:** Task 1 (after PHPStan nullsafe.neverNull fix)
- **Issue:** Added `use App\Models\TournamentStage` for the `@var` annotation, then refactored to ternary (removing the annotation), leaving the import unused
- **Fix:** Pint auto-removed the unused import
- **Files modified:** `apps/web/app/Services/BracketMatchMaterialiserService.php`
- **Committed in:** 4535ceb

**2. [Rule 1 - Bug] `use RuntimeException` import is redundant in global namespace**
- **Found during:** Task 2 verification (PHP warning emitted by Pest)
- **Issue:** `RuntimeException` is in the global namespace; a `use RuntimeException` import has no effect and emits a PHP warning
- **Fix:** Removed the redundant import
- **Files modified:** `apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php`
- **Committed in:** af5f225

---

**Total deviations:** 2 auto-fixed (both Rule 1 — import cleanliness)
**Impact on plan:** Neither affected logic or behavior. Pint gate enforced clean code.

## Issues Encountered

- `mountTableAction('edit', $stage)` in the scoping test triggered a Filament v3.3 `TypeError`: `ViewAction` calls `infolist()` which falls back to our `form()` method but passes an `Infolist` object instead of `Form`. Fixed by switching to a direct closure-logic unit test (plan note explicitly permitted this path).

## Self-Check

Verified files exist:
- `apps/web/app/Services/BracketMatchMaterialiserService.php` — FOUND
- `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` — FOUND
- `apps/web/lang/en/admin.php` — FOUND
- `apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php` — FOUND
- `apps/web/tests/Feature/Admin/StagesRelationManagerOverrideTest.php` — FOUND

Commits: `4535ceb` (Task 1), `af5f225` (Task 2) — both present in git log

## Self-Check: PASSED

All files exist. Both commits verified in git log.

## Next Phase Readiness

- TOUR-04 complete: materialiser correctly uses stage override when set
- StagesRelationManager is no longer fully read-only — game_match_type_id is the sole editable field
- Ready for plan 11-05 (remaining tournament depth work)

---
*Phase: 11-tournament-depth*
*Completed: 2026-06-04*
