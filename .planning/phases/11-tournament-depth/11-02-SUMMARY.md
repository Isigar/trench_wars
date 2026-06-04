---
phase: 11-tournament-depth
plan: 02
subsystem: services
tags: [elo, swiss, buchholz, tdd, tournament, pest-tdd]

# Dependency graph
requires:
  - phase: 11-tournament-depth
    plan: 01
    provides: clans.elo_rating + elo_matches_count columns, tournament_standings.median_buchholz column, EloRatingServiceTest + SwissMedianBuchholzTest RED scaffolds
  - phase: 06-tournaments-brackets
    provides: SwissStandingsCalculator, TournamentStanding model
  - phase: 02-clans-tags
    provides: Clan model
provides:
  - EloRatingService (K=32 standard Elo, DB::transaction lockForUpdate, activity log)
  - median Buchholz Cut-1 in SwissStandingsCalculator (third tiebreaker: points->buchholz->median->seed)
  - 11-01 RED scaffolds turned GREEN (EloRatingServiceTest 4/4, SwissMedianBuchholzTest 2/2)
affects:
  - 11-03 (BracketAdvancementService Elo hook — calls EloRatingService::applyResult with rated_at idempotency)
  - 11-05 (shared-types regen — TournamentStandingData::median_buchholz already wired in 11-01)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Stateless final class service with DB::transaction + lockForUpdate (both rows re-fetched inside lock)
    - Elo expected-score formula: 1/(1+10^((Rb-Ra)/400)); K=32 integer-rounded delta
    - Buchholz Cut-1: sort opponent scores, array_shift (drop lowest) + array_pop (drop highest) when count>=3
    - TDD RED -> GREEN -> REFACTOR cadence per plan 11-02 spec

key-files:
  created:
    - apps/web/app/Services/EloRatingService.php
  modified:
    - apps/web/app/Services/Standings/SwissStandingsCalculator.php

key-decisions:
  - "BASE=1500 constant removed from EloRatingService — it is the DB column default (migration), not used in Elo arithmetic; PHPStan L8 classConstant.unused would fail the gate (Rule 1 auto-fix during REFACTOR cycle)"
  - "Concurrent write safety: both clan rows re-fetched with lockForUpdate inside DB::transaction; stale caller-supplied model values are never used for math (T-11-02-02)"
  - "Activity log causer may be null for system-attributed bracket advances — causedBy(auth()->user()) returns null-safe when called from a queued path"

# Metrics
duration: 121s
completed: 2026-06-04
---

# Phase 11 Plan 02: EloRatingService + Median Buchholz Summary

**EloRatingService (K=32 standard Elo, DB transaction, activity audit) and Buchholz Cut-1 third tiebreaker in SwissStandingsCalculator — two RED scaffolds turned GREEN**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-06-04T11:17:03Z
- **Completed:** 2026-06-04T11:19:04Z
- **Tasks:** 2 (EloRatingService GREEN + SwissStandingsCalculator median Buchholz GREEN)
- **Files modified:** 2

## Accomplishments

- `EloRatingService` final class implemented: `applyResult(Clan $winner, Clan $loser, bool $draw = false): void`, K=32 formula, DB::transaction + lockForUpdate on both clan rows, integer rounding, elo_matches_count increment, activity log with before/after rating deltas
- `SwissStandingsCalculator` extended: third pass computes `$medianBuchholzByParticipant` (Buchholz Cut-1 — drop highest + lowest opponent score when >=3 opponents, else equal to plain Buchholz), added to `$rows[]`, `usort` tiebreaker (3rd between buchholz and seed), and `TournamentStanding::create()` call
- All 4 EloRatingServiceTest cases GREEN: equal-win 1516/1484, draw 0-delta, upset gains >16, elo_matches_count increment
- Both SwissMedianBuchholzTest cases GREEN: calculator writes column (not stuck at 0), <3-opponent edge = plain Buchholz
- Existing StandingsCalculatorServiceTest 9/9 still GREEN (no Swiss ranking regression)
- PHPStan L8 clean; Pint 672 files PASS

## Task Commits

1. **EloRatingService GREEN** - `42a4de5` (feat)
2. **Median Buchholz GREEN** - `09fd10d` (feat)
3. **Remove unused BASE constant** - `a837f0a` (refactor — PHPStan L8 classConstant.unused fix)

## Files Created/Modified

- `apps/web/app/Services/EloRatingService.php` - NEW: K=32 Elo service, DB transaction, activity log
- `apps/web/app/Services/Standings/SwissStandingsCalculator.php` - MODIFIED: median Buchholz Cut-1 pass + tiebreaker + create() field

## Gate Results

| Gate | Result |
|------|--------|
| `make pest EloRatingServiceTest.php` | 4/4 GREEN |
| `make pest SwissMedianBuchholzTest.php` | 2/2 GREEN |
| `make pest StandingsCalculatorServiceTest.php` | 9/9 GREEN (no regression) |
| `make phpstan` | No errors |
| `make pint --test` | 672 files PASS |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] BASE=1500 private const removed**
- **Found during:** REFACTOR cycle (PHPStan L8 gate run after GREEN)
- **Issue:** `private const BASE = 1500` was unused — the plan noted "BASE only matters for new-clan default, set in migration". PHPStan L8 `classConstant.unused` flagged it as an error.
- **Fix:** Removed `BASE` constant; `K = 32` retained as the only service constant.
- **Files modified:** `apps/web/app/Services/EloRatingService.php`
- **Commit:** `a837f0a`

---

**Total deviations:** 1 auto-fixed (Rule 1 — unused constant, PHPStan L8 compliance)
**Impact on plan:** Cosmetic. No math or behavior change.

## Known Stubs

None — both services are fully implemented with real math and real DB writes.

## Threat Flags

No new network endpoints, auth paths, file access patterns, or schema changes introduced. All changes are internal service logic.

## Self-Check: PASSED

Files exist:
- `apps/web/app/Services/EloRatingService.php`: FOUND
- `apps/web/app/Services/Standings/SwissStandingsCalculator.php`: FOUND

Commits:
- `42a4de5`: FOUND (EloRatingService)
- `09fd10d`: FOUND (Median Buchholz)
- `a837f0a`: FOUND (Refactor)

---
*Phase: 11-tournament-depth*
*Completed: 2026-06-04*
