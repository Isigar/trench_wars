---
phase: 11-tournament-depth
plan: 01
subsystem: database
tags: [migrations, eloquent, elo, swiss, buchholz, tournament, pest-tdd]

# Dependency graph
requires:
  - phase: 06-tournaments-brackets
    provides: Tournament, TournamentBracket, TournamentStage, TournamentStanding, TournamentStandingData models and DTOs
  - phase: 03-games-match-types
    provides: GameMatchType model (referenced by new tournament_stages FK)
  - phase: 02-clans-tags
    provides: Clan model (extended with elo_rating + elo_matches_count)
provides:
  - clans.elo_rating (int default 1500) + clans.elo_matches_count (int default 0) columns
  - tournament_brackets.rated_at (nullable timestampTz idempotency marker)
  - tournament_standings.median_buchholz (decimal 8,2 default 0)
  - tournament_stages.game_match_type_id (nullable uuid FK nullOnDelete to game_match_types)
  - TournamentStage::gameMatchType() BelongsTo relation
  - TournamentStandingData::median_buchholz float field
  - 4 RED test scaffolds for Phase 11 Waves 2-4 (EloRating, SwissMedianBuchholz, SwissAutoAdvance, StageMatchTypeOverride)
affects:
  - 11-02 (EloRatingService + BracketAdvancementService hooks — reads elo_rating, writes rated_at)
  - 11-03 (SwissStandingsCalculator — writes median_buchholz)
  - 11-04 (BracketMatchMaterialiserService — reads game_match_type_id)
  - 11-05 (shared-types regen — picks up TournamentStandingData::median_buchholz)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Schema::table additive-column migration with safe defaults (nullOnDelete FK, default 0 for numeric)
    - TDD RED scaffold pattern: test files exist and fail for the correct unwired reason
    - Spatie Data constructor ordering: new DTO fields inserted between existing fields at exact position

key-files:
  created:
    - apps/web/database/migrations/2026_06_05_100000_add_elo_rating_to_clans.php
    - apps/web/database/migrations/2026_06_05_100010_add_rated_at_to_tournament_brackets.php
    - apps/web/database/migrations/2026_06_05_100020_add_median_buchholz_to_tournament_standings.php
    - apps/web/database/migrations/2026_06_05_100030_add_game_match_type_id_to_tournament_stages.php
    - apps/web/tests/Feature/Services/EloRatingServiceTest.php
    - apps/web/tests/Feature/Services/SwissMedianBuchholzTest.php
    - apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php
    - apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php
  modified:
    - apps/web/app/Models/Clan.php
    - apps/web/app/Models/TournamentBracket.php
    - apps/web/app/Models/TournamentStanding.php
    - apps/web/app/Models/TournamentStage.php
    - apps/web/app/Data/TournamentStandingData.php

key-decisions:
  - "D-11-01-A: game_match_type_id uses nullOnDelete (not cascade) — deleting a GameMatchType nulls the stage override, stage survives. T-11-01-01 threat mitigation."
  - "D-11-01-B: median_buchholz decimal(8,2) default 0 — matches existing points/tiebreak_score precision; Phase-6 standings inserts that omit the column succeed unchanged (T-11-01-02 mitigation)."
  - "D-11-01-C: elo_rating NOT NULL default 1500 — every existing + new clan starts at canonical base; no null-rating leak into seeding (T-11-01-03 mitigation)."
  - "D-11-01-D: RED scaffold idempotency test (SwissAutoAdvanceTest) calls SwissGenerator::generateNextRound directly to assert existence-check guard — tests the guard contract independently of the auto-advance wiring (plan 11-02)."

patterns-established:
  - "Phase 11 RED scaffold pattern: each test file fails with BindingResolutionException (missing service) or assertion mismatch (column default != computed value) — never parse errors, always meaningful RED"

requirements-completed: [TOUR-01, TOUR-02, TOUR-03, TOUR-04]

# Metrics
duration: ~7min
completed: 2026-06-04
---

# Phase 11 Plan 01: Schema + RED Scaffolds Summary

**Four additive schema columns (elo_rating, rated_at, median_buchholz, game_match_type_id) with model wiring and four RED Pest scaffolds for Phase 11 tournament depth**

## Performance

- **Duration:** ~7 min
- **Started:** 2026-06-04T11:33:48Z
- **Completed:** 2026-06-04T11:40:36Z
- **Tasks:** 2
- **Files modified:** 13

## Accomplishments

- 4 additive migrations apply cleanly on migrate:fresh --seed (all 6 columns confirmed via Schema::hasColumn)
- Model fillable/casts wired for Clan, TournamentBracket, TournamentStanding, TournamentStage; TournamentStage::gameMatchType() BelongsTo added with PHPStan L8 generic annotation
- TournamentStandingData DTO extended with median_buchholz between tiebreak_score and rank (Spatie Data constructor order preserved)
- 4 RED test scaffolds in place: EloRatingServiceTest (4 tests, BindingResolutionException), SwissMedianBuchholzTest (2 tests, column stuck at 0), SwissAutoAdvanceTest (2 tests, advance not wired), StageMatchTypeOverrideTest (1 RED + 1 already-green)

## Task Commits

1. **Task 1: Four additive migrations + model fillable/casts/relations** - `f9eb57d` (feat)
2. **Task 2: Extend TournamentStandingData DTO + author four RED test scaffolds** - `9e28eac` (test)

**Plan metadata:** (see final docs commit)

## Files Created/Modified

- `apps/web/database/migrations/2026_06_05_100000_add_elo_rating_to_clans.php` - elo_rating + elo_matches_count on clans
- `apps/web/database/migrations/2026_06_05_100010_add_rated_at_to_tournament_brackets.php` - rated_at nullable timestamp on tournament_brackets
- `apps/web/database/migrations/2026_06_05_100020_add_median_buchholz_to_tournament_standings.php` - median_buchholz decimal(8,2) on tournament_standings
- `apps/web/database/migrations/2026_06_05_100030_add_game_match_type_id_to_tournament_stages.php` - game_match_type_id nullable FK nullOnDelete on tournament_stages
- `apps/web/app/Models/Clan.php` - added elo_rating + elo_matches_count to fillable + casts
- `apps/web/app/Models/TournamentBracket.php` - added rated_at to fillable + casts
- `apps/web/app/Models/TournamentStanding.php` - added median_buchholz to fillable + casts
- `apps/web/app/Models/TournamentStage.php` - added game_match_type_id to fillable; added gameMatchType() BelongsTo
- `apps/web/app/Data/TournamentStandingData.php` - added median_buchholz float constructor param + fromModel mapping
- `apps/web/tests/Feature/Services/EloRatingServiceTest.php` - RED scaffold for K=32 math, draw, upset, elo_matches_count
- `apps/web/tests/Feature/Services/SwissMedianBuchholzTest.php` - RED scaffold for median_buchholz column write
- `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php` - RED scaffold for Swiss round auto-advance + idempotency
- `apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php` - RED scaffold for stage-level match type override

## Decisions Made

- game_match_type_id FK uses nullOnDelete not cascadeDelete — dropping a GameMatchType must not destroy tournament stages (T-11-01-01)
- median_buchholz decimal(8,2) default 0 matches existing points/tiebreak_score precision; Phase-6 inserts omitting it succeed (T-11-01-02)
- elo_rating NOT NULL default 1500 — no null-rating leak into Elo-based seeding (T-11-01-03)
- RED scaffold idempotency test calls SwissGenerator::generateNextRound directly for isolation — asserts the guard contract independently of auto-advance wiring

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] SwissMedianBuchholzTest first test vacuously passed on initial write**
- **Found during:** Task 2 (RED scaffold verification run)
- **Issue:** First test compared p1.median_buchholz==0 vs p1.tiebreak_score==0; both defaulted to 0 after p1 beats a 0-pt opponent. Test passed without exercising the "calculator must write the column" contract.
- **Fix:** Replaced the fixture target: now checks p3 (loser), who faces p1 scoring 1.0 pt. p3.tiebreak_score=1.0 but median_buchholz=0.0 (stuck at default) → meaningful RED.
- **Files modified:** apps/web/tests/Feature/Services/SwissMedianBuchholzTest.php
- **Committed in:** 9e28eac (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 — test logic bug)
**Impact on plan:** Single test fixture correction. No scope change.

## Issues Encountered

None beyond the test logic deviation above.

## User Setup Required

None — pure migrations + model edits. No external service configuration required.

## Next Phase Readiness

- Schema foundation stable for Waves 2-4: all four columns present with correct types/defaults
- 4 RED scaffolds provide concrete GREEN targets for plans 11-02 (EloRatingService + auto-advance), 11-03 (SwissStandingsCalculator), 11-04 (BracketMatchMaterialiserService override)
- migrate:fresh --seed green; PHPStan L8 + Pint clean
- shared-types regen (picks up TournamentStandingData::median_buchholz) deferred to plan 11-05 per plan spec

## Self-Check: PASSED

All 13 created/modified files exist. Both task commits (f9eb57d, 9e28eac) present in git log.

---
*Phase: 11-tournament-depth*
*Completed: 2026-06-04*
