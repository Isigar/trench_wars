---
phase: 11-tournament-depth
plan: 03
subsystem: services
tags: [elo, swiss, auto-advance, seeding, bracket-advancement, tournament, pest]

# Dependency graph
requires:
  - phase: 11-tournament-depth
    plan: 01
    provides: rated_at column on tournament_brackets, elo_rating on clans
  - phase: 11-tournament-depth
    plan: 02
    provides: EloRatingService::applyResult, SwissStandingsCalculator median Buchholz
  - phase: 06-tournaments-brackets
    provides: BracketAdvancementService, TournamentSeedingService, SwissGenerator
provides:
  - Elo hook (rated_at-guarded, once per bracket) wired into BracketAdvancementService::advance()
  - Swiss auto-advance (idempotent, swiss-only, exhaustion guard) wired into advance()
  - Premature-completion guard in allBracketsComplete() for swiss tournaments
  - by_rank seeding orders by clan.elo_rating DESC (tiebreak created_at DESC, D-11-03-A)
  - EloAdvancementHookTest (NEW) — 3 cases GREEN
  - SwissAutoAdvanceTest — 4 cases GREEN (1 pre-existing + 3 new including premature-completion guard)
  - TournamentSeedingServiceTest — 2 new cases GREEN (elo_rating DESC, all-1500 regression)
affects:
  - 11-05 (shared-types: no new fields here)
  - Phase-6 advancement tests: all still GREEN (no regression)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Elo hook inside existing DB::transaction: rated_at null-guard before applyResult, stamp inside same transaction (T-11-03-01)"
    - "Swiss auto-advance: inline exhaustion guard (currentRound < totalRounds), nextRoundExists existence check, app(SwissGenerator)->generateNextRound + $tournament->touch() (T-11-03-02, T-11-03-03)"
    - "Swiss premature-completion guard: allBracketsComplete() returns false when any swiss-round stage has match_id=null brackets (plan 11-03 BLOCKER-class finding)"
    - "by_rank composite sort: single usort() closure, primary elo_rating DESC, tiebreak created_at DESC — stable two-key composition (D-11-03-A)"
    - "circular-DI break: EloRatingService and SwissGenerator resolved via app() at call-site, same pattern as existing StandingsCalculatorService"

key-files:
  created:
    - apps/web/tests/Feature/Services/EloAdvancementHookTest.php
  modified:
    - apps/web/app/Services/BracketAdvancementService.php
    - apps/web/app/Services/TournamentSeedingService.php
    - apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php
    - apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php

key-decisions:
  - "D-11-03-A: tiebreak for by_rank is created_at DESC (not ASC per CONTEXT.md decision #4). No-regression requirement (TOUR-02 SC) wins: all-1500 clans produce byte-identical output to pre-Phase-11 sortByDesc('created_at')."
  - "Premature-completion guard uses match_id IS NULL on swiss-round stage brackets as the proxy for 'next round unmaterialised'. This is correct because swiss brackets never have match_id set on creation — only BracketMatchMaterialiserService sets it later."
  - "Tournament::touch() after generateNextRound bumps updated_at so the 30s public poll detects the new round. The TournamentObserver fires tournament_announce_update on the update — no extra wiring needed."
  - "idempotency test was changed from calling SwissGenerator::generateNextRound directly (which throws LogicException when rounds are exhausted) to calling BracketAdvancementService::advance() with a synthetic MatchResult — this is the actual double-fire path being guarded."
  - "Tournament::stages() HasMany has a default orderBy('ordinal') ASC; test that needs the highest-ordinal stage must use reorder() before orderByDesc() to clear the default, otherwise the two ORDER BY clauses compose incorrectly."

# Metrics
duration: ~12min
completed: 2026-06-04
---

# Phase 11 Plan 03: Elo Hook + Swiss Auto-Advance + by_rank Elo Summary

**Elo hook (rated_at-guarded), Swiss auto-advance (idempotent + premature-completion guard), and by_rank seeding repointerd to clan elo_rating DESC — all RED scaffolds GREEN, all Phase-6 tests regression-free**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-06-04T11:30Z
- **Completed:** 2026-06-04
- **Tasks:** 2 (hooks + seeding)
- **Files modified:** 4 + 1 new

## Accomplishments

### Task 1: Elo hook + Swiss auto-advance in advance()

- `BracketAdvancementService::advance()` now has two new hooks inserted between step 5 (standings recalc) and step 6 (Discord announce), both inside the existing DB::transaction with Tournament::lockForUpdate:

  **Elo hook (5a):** skips when `$loserParticipant === null` (bye) or `$bracket->rated_at !== null` (already rated). Resolves winner/loser clans from participant.clan_id, calls `app(EloRatingService::class)->applyResult($winnerClan, $loserClan)`, then stamps `$bracket->update(['rated_at' => now()])` inside the same transaction. (T-11-03-01)

  **Swiss auto-advance hook (5b):** fires only when `$stage->type === 'swiss-round'`. Checks `roundComplete` (no null winners in stage), inline exhaustion guard (mirrors SwissGenerator lines 151-159: `currentRound < totalRounds`), `nextRoundExists` check. On all guards passing: `app(SwissGenerator::class)->generateNextRound($tournament)` + `$tournament->touch()`. (T-11-03-02, T-11-03-03)

- **Premature-completion guard (BLOCKER-class finding):** `allBracketsComplete()` extended with a swiss-specific check: if the tournament format is 'swiss' and any swiss-round stage has brackets with `match_id IS NULL`, returns false. This prevents the tournament from spuriously completing after a non-final round resolves (newly auto-generated next round has unmaterialised brackets).

- **EloAdvancementHookTest (NEW):** 3 cases GREEN:
  - First result moves both clans' elo_rating + stamps rated_at
  - Re-fire advance() on already-rated bracket: ratings unchanged, rated_at identical
  - Bye bracket: no Elo applied, rated_at stays null (plan 11-03 INFO assertion confirmed)

- **SwissAutoAdvanceTest:** 4 cases GREEN (1 original + 3 new):
  - Original: recording final round-1 result auto-generates round 2 (stage count 1→2)
  - New: auto-advance does not generate past the final round (exhaustion guard)
  - New (BLOCKER): tournament does NOT auto-complete after non-final round resolves
  - New (idempotency): re-firing advance() on an already-advanced bracket creates no duplicate stage

- **BracketAdvancementServiceTest:** all 9 existing Phase-6 tests still GREEN (no regression)

### Task 2: by_rank seeding by clan elo_rating

- `TournamentSeedingService::orderByRank()` replaced: `$participants->loadMissing('clan')` then `$participants->sort(fn($a, $b) => ...)` with primary key `elo_rating DESC`, tiebreak `created_at DESC`. Returns `->values()`.

- Decision D-11-03-A: tiebreak is `created_at DESC` (not ASC per CONTEXT.md) so all-1500 clans produce byte-identical output to pre-Phase-11 `sortByDesc('created_at')`.

- **TournamentSeedingServiceTest:** 2 new cases GREEN + all 16 existing cases still GREEN:
  - `by_rank orders participants by clan elo_rating desc (high elo = seed 1)`: 1800 vs 1200 → 1800 gets seed 1 regardless of creation order
  - `by_rank with all clans at 1500 matches created_at desc order (D-11-03-A no-regression)`: 4 distinct created_at, all elo=1500 → newest = seed 1

## Task Commits

1. **Elo hook + Swiss auto-advance + premature-completion guard + EloAdvancementHookTest + SwissAutoAdvanceTest** - `642e92f` (feat)
2. **by_rank seeding + TournamentSeedingServiceTest** - `d5b11e9` (feat)

## Files Created/Modified

- `apps/web/app/Services/BracketAdvancementService.php` - MODIFIED: Clan import, Elo hook (5a), Swiss auto-advance (5b), premature-completion guard in allBracketsComplete(), SwissGenerator import added by Pint
- `apps/web/app/Services/TournamentSeedingService.php` - MODIFIED: orderByRank() rewritten for elo_rating DESC + created_at DESC tiebreak
- `apps/web/tests/Feature/Services/EloAdvancementHookTest.php` - NEW: 3 Elo hook tests
- `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php` - MODIFIED: 3 new tests (exhaustion, premature-completion, idempotency)
- `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php` - MODIFIED: Clan import + 2 new by_rank/elo tests

## Gate Results

| Gate | Result |
|------|--------|
| `make pest SwissAutoAdvanceTest.php` | 4/4 GREEN |
| `make pest EloAdvancementHookTest.php` | 3/3 GREEN |
| `make pest TournamentSeedingServiceTest.php` | 18/18 GREEN (including 2 new) |
| `make pest BracketAdvancementServiceTest.php` | 9/9 GREEN (no regression) |
| Full `tests/Feature/Services/` suite | 174/174 GREEN |
| `make phpstan` | No errors |
| `make pint --test` | 674 files PASS |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] PHPStan level 8: nullsafe property access unnecessary**
- **Found during:** PHPStan gate
- **Issue:** `$a->clan?->elo_rating ?? 1500` — PHPStan inferred `clan` attribute via Eloquent magic getter is non-nullable (`nullsafe.neverNull` error)
- **Fix:** Changed to `$a->clan->elo_rating ?? 1500` (the `?? 1500` fallback still handles integrity edge cases at runtime; the `?->` was removed to satisfy PHPStan)
- **Files modified:** `apps/web/app/Services/TournamentSeedingService.php`

**2. [Rule 1 - Bug] Pint: fully_qualified_strict_types fix**
- **Found during:** Pint gate
- **Issue:** `\App\Services\Brackets\SwissGenerator::class` fully-qualified in BracketAdvancementService; Pint auto-extracted it as a use import
- **Fix:** Pint auto-fix applied; `use App\Services\Brackets\SwissGenerator;` added to imports

**3. [Rule 1 - Bug] SwissAutoAdvanceTest: stages()->orderByDesc('ordinal') returns wrong stage**
- **Found during:** Test failure on "auto-advance does not generate a round past the final swiss round"
- **Issue:** `Tournament::stages()` HasMany has a default `->orderBy('ordinal')` ASC. Chaining `->orderByDesc('ordinal')` appends a second ORDER BY, but PostgreSQL uses the first (ASC) as primary. The test got stage1 (ordinal=1) when it expected stage2 (ordinal=2) — stage2 already had MatchResults, causing UniqueConstraintViolation.
- **Fix:** Added `->reorder()` before `->orderByDesc('ordinal')` in the test to clear the default ordering
- **Files modified:** `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php`

**4. [Rule 2 - Missing functionality] Idempotency test changed to use advance() instead of generateNextRound() directly**
- **Found during:** Test execution
- **Issue:** The original RED scaffold called `app(SwissGenerator::class)->generateNextRound($tournament->fresh())` to test idempotency. But after auto-advance creates round 2, `currentRound(2) >= totalRounds(2)` causes generateNextRound to throw LogicException — it is NOT a no-op. The test needed to test BracketAdvancementService's guard (not SwissGenerator's throw).
- **Fix:** Changed test to call `BracketAdvancementService::advance()` with a synthetic MatchResult for an already-decided bracket, which exercises the `nextRoundExists` guard path
- **Files modified:** `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php`

### Plan-checker Findings Handled

**[BLOCKER-class] Premature Swiss completion — FIXED**
- Added swiss-specific guard in `allBracketsComplete()`: returns false when any swiss-round stage has brackets with `match_id IS NULL` (freshly auto-generated, not yet materialised)
- Test: "tournament does NOT auto-complete after a non-final swiss round resolves" — GREEN

**[INFO] Bye rated_at null — ASSERTED**
- `EloAdvancementHookTest::bye bracket does not apply Elo and leaves rated_at null` explicitly asserts `expect($byeBracket->fresh()->rated_at)->toBeNull()`

## Known Stubs

None — all implementations are fully functional with real DB writes and real test assertions.

## Threat Flags

No new network endpoints, auth paths, file access patterns, or schema changes introduced. All changes are internal service logic.

## Self-Check: PASSED

Files exist:
- `apps/web/app/Services/BracketAdvancementService.php`: FOUND
- `apps/web/app/Services/TournamentSeedingService.php`: FOUND
- `apps/web/tests/Feature/Services/EloAdvancementHookTest.php`: FOUND
- `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php`: FOUND
- `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php`: FOUND

Commits:
- `642e92f`: FOUND (Elo hook + Swiss auto-advance)
- `d5b11e9`: FOUND (by_rank seeding)

---
*Phase: 11-tournament-depth*
*Completed: 2026-06-04*
