---
phase: 06-tournaments-brackets
plan: 09
subsystem: services
tags:
  - wave-5
  - service
  - standings
  - strategy-pattern
  - pitfall-6
  - phase-6-tournaments
  - sc-4
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 RED stub StandingsCalculatorServiceTest
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # TournamentStanding + TournamentParticipant models + factories
    - .planning/phases/06-tournaments-brackets/06-06-SUMMARY.md  # BracketGeneratorService + BracketMatchMaterialiserService — fixture builders in GREEN tests
    - .planning/phases/06-tournaments-brackets/06-07-SUMMARY.md  # 3 non-single-elim generators (round-robin, swiss, double-elim) — produce stage types consumed by calculators
    - .planning/phases/06-tournaments-brackets/06-08-SUMMARY.md  # BracketAdvancementService + StandingsCalculatorService no-op stub — replaced in-place here
  provides:
    - "App\\Services\\StandingsCalculatorService — real front-door dispatcher replacing the plan 06-08 stub; PHP 8 match() on tournament.format → 1 of 4 strategy classes; wipe-and-recompute inside DB::transaction + Tournament::lockForUpdate (Pitfall 6)"
    - "App\\Services\\Standings\\StandingsCalculatorStrategy — strategy interface (compute(Tournament): void); mirrors BracketGeneratorStrategy pattern from plan 06-06"
    - "App\\Services\\Standings\\SingleEliminationStandingsCalculator — rank derived from elimination round: `2^(finalRound - eliminationRound) + 1`; winner=1, runner-up=2, semi loser=3, QF loser=5"
    - "App\\Services\\Standings\\DoubleEliminationStandingsCalculator — Burton-variant placement order: 1=GF winner, 2=GF loser, 3=L-final loser, walk L-rounds backwards for remaining ranks"
    - "App\\Services\\Standings\\RoundRobinStandingsCalculator — FIFA 3/1/0 default points; admin override via tournament.settings.roundrobin_points_per_{win,draw,loss}; tiebreaker (h2h DESC, point_diff DESC, seed ASC)"
    - "App\\Services\\Standings\\SwissStandingsCalculator — chess 1/0.5/0 points; bye=1pt; Buchholz tiebreak (sum of opponents' final points); sort (points DESC, buchholz DESC, seed ASC)"
    - "9 GREEN Pest tests / 31 assertions covering all 4 format strategies, wipe-and-recompute idempotency, admin points-scheme override, and A5 LOCKED withdrawn-participant retention"
  affects:
    - apps/web/app/Services/StandingsCalculatorService.php  # in-place replacement of plan 06-08 stub
    - apps/web/app/Services/Standings/                       # 5 new files (interface + 4 strategies)
    - apps/web/tests/Feature/Services/StandingsCalculatorServiceTest.php  # Wave 0 RED → GREEN
tech-stack:
  added: []
  patterns:
    - "Strategy pattern + PHP 8 match() dispatch — mirrors BracketGeneratorService (plan 06-06). Front-door class is the only DI surface; 4 strategy classes are auto-resolved by the container. New formats Phase 9+ can add a strategy without touching the front-door body beyond a single match arm."
    - "Wipe-and-recompute — `tournament->standings()->delete()` runs FIRST inside the recalculate transaction; strategies then INSERT new rows. Avoids the complexity of upsert-per-(stage_id, participant_id). The table is small (≤ 64 rows × N stages per tournament) so wipe cost is negligible. If any INSERT fails mid-strategy the DB::transaction rolls back atomically and existing standings stay intact."
    - "Pitfall 6 mitigation — DB::transaction wraps recalculate(); FIRST statement is `Tournament::query()->whereKey($id)->lockForUpdate()->first()`. Identical pattern to BracketAdvancementService and BracketGeneratorService. Concurrent BracketAdvancementService advances on the same tournament serialise on this row; standings recalc never races itself."
    - "App\\Services\\Standings\\* namespace mirrors App\\Services\\Brackets\\* (plan 06-06 / 06-07) — same `final class X implements Strategy` shape per file. Locality for IDE navigation: Brackets/ for generation, Standings/ for ranking."
    - "Single-elim placement formula `2^(finalRound - R) + 1` — produces shared-rank ties (Phase 9 polish: tiebreak by seed for distinct placements). For an 8-team bracket: winner=1, runner-up=2, semi losers=3 (shared by 2), QF losers=5 (shared by 4). Equivalent to industry convention used by Challonge / brackets-manager.js."
    - "Round-robin tiebreaker is direct head-to-head only (v1) — Phase 9 polish adds a mini-table over the tied subset for transitive 3-way ties (e.g., A beat B, B beat C, C beat A → all 1W h2h → would currently fall through to point_diff)."
    - "Swiss Buchholz is plain sum-of-opponents-points — median Buchholz variant (drop highest + lowest opponent scores) deferred to Phase 9."
    - "TournamentStage::brackets() default ordering trap — the model relationship is `hasMany(...)->orderBy('round_number')->orderBy('position')`. Appending `->orderByDesc('round_number')` to a query off this relationship does NOT override the default — it appends a tertiary sort, leaving the primary ASC sort in force. The single-elim calculator's `derivePlacement()` and the double-elim calculator's `findStageWinner()` both must `->reorder()->orderByDesc('round_number')` to drop the default before reordering. Discovered during the 8-clan single-elim test which initially gave rank=5 to semi-final losers (round-1 was the first match returned, so eliminationRound was misread as 1)."
key-files:
  created:
    - apps/web/app/Services/Standings/StandingsCalculatorStrategy.php
    - apps/web/app/Services/Standings/SingleEliminationStandingsCalculator.php
    - apps/web/app/Services/Standings/DoubleEliminationStandingsCalculator.php
    - apps/web/app/Services/Standings/RoundRobinStandingsCalculator.php
    - apps/web/app/Services/Standings/SwissStandingsCalculator.php
  modified:
    - apps/web/app/Services/StandingsCalculatorService.php  # replaced plan 06-08 stub body with real front-door
    - apps/web/tests/Feature/Services/StandingsCalculatorServiceTest.php  # Wave 0 RED → GREEN with 9 it() blocks
decisions:
  - "D-06-09-A: TournamentStage::brackets() relationship ships with a default orderBy(round_number ASC, position ASC). Appending `orderByDesc('round_number')` to a query off the relation does NOT replace the existing sort — it appends a tertiary sort. The relationship's default sort silently wins for the first key. The 8-clan single-elim test exposed this: semi-final losers were placed at rank 5 (round 1's placement) because the queries returned their round-1 bracket as 'last' before their round-2 one. Fix: `->reorder()->orderByDesc('round_number')` to drop the default before sorting. Applied in SingleEliminationStandingsCalculator::derivePlacement and DoubleEliminationStandingsCalculator::computePlacements."
  - "D-06-09-B: Wipe-and-recompute over upsert-per-row — recalculate() deletes all existing standings rows for the tournament BEFORE invoking the strategy, then strategies INSERT fresh. Avoids the per-(stage_id, participant_id) upsert complexity. The standings table is small enough (≤ 64 rows × N stages per tournament; typically ≤ 64 rows total) that the wipe cost is negligible. The whole operation is wrapped in DB::transaction; if any INSERT fails, the wipe rolls back atomically. Trade-off accepted: a mid-transaction read of standings would briefly see an empty table — but the read is inside the same DB::transaction so consistent-read snapshot applies."
  - "D-06-09-C: PHPStan baseline avoidance via local-counter arrays instead of nested-shape `$stats[$id]['wins']++`. PHPStan L8 cannot infer that `$stats[$pid] = [...full shape...]` followed by `$stats[$pid]['wins']++` keeps the 'wins' key in the array shape — it widens to `array{wins?: int, ...}` after the increment and then flags every subsequent access. Splitting into 5 flat arrays (`$wins[$id]`, `$losses[$id]`, `$draws[$id]`, `$pointDiff[$id]`, `$h2h[$id]`) gives PHPStan unambiguous `array<string, int>` shapes that pass L8 without baseline entries."
  - "D-06-09-D: Standings rows for double-elim and swiss are written against ONE canonical stage_id — the grand-final stage (double-elim) or the first swiss-round stage (swiss). The original RESEARCH note suggested standings could span multiple stages, but the public Standings tab (plan 06-12) and the Filament Recalculate action (plan 06-11) want a single canonical query: `tournament_standings WHERE tournament_id = X ORDER BY rank`. Aggregating into one stage keeps that query simple. Round-robin standings stay on the single 'group' stage_id (no decision needed). Single-elim standings stay on the single 'elim' stage_id (no decision needed)."
  - "D-06-09-E: Withdrawn / disqualified participants are INCLUDED in standings — A5 LOCKED (forfeit and withdraw both stop FUTURE matches; past matches retain results). The query is `whereIn('status', ['active','withdrawn','disqualified'])`. Their wins/losses/draws/points reflect their performance up to the withdrawal point; rank is computed as if they finished. The Vue Standings tab (plan 06-12) is responsible for rendering withdrawn status as a visual badge."
  - "D-06-09-F: Round-robin default points scheme is FIFA 3/1/0 — admin override via `tournament.settings.roundrobin_points_per_win` (default 3), `roundrobin_points_per_draw` (default 1), `roundrobin_points_per_loss` (default 0). Plan locked this inline; planner consulted FIFA Laws of the Game for canonical defaults."
  - "D-06-09-G: Round-robin tiebreaker is direct head-to-head only (v1) — Phase 9 polish adds a mini-table for transitive 3-way ties. The implementation handles the simple 2-way case (A beat B → A ranks higher); the edge case of (A beat B, B beat C, C beat A all tied on points) falls through to point_diff DESC then seed ASC. Documented in the class docblock."
  - "D-06-09-H: Swiss tiebreaker is plain Buchholz only (v1) — median Buchholz variant is Phase 9 polish. Buchholz sums each participant's opponents' FINAL points (computed after all rounds), not pre-round points — RESEARCH Pattern 5 verbatim."
metrics:
  duration: ~7m
  completed: 2026-05-13
  tasks: 2
  files_created: 5
  files_modified: 2
  commits: 2
---

# Phase 6 Plan 9: Wave 5 — StandingsCalculatorService Summary

The SC-4 standings recompute engine landed in full. `StandingsCalculatorService::recalculate()` replaces the plan 06-08 no-op stub with the real 4-strategy dispatcher. `BracketAdvancementService`'s observer chain (plan 06-08) now drives real standings computation on every MatchResult write; Filament's "Recalculate standings" admin action (plan 06-11) wires onto the same entry point; the public Standings tab (plan 06-12) reads `tournament_standings` rows ordered by `rank`.

9 GREEN Pest tests / 31 assertions cover all 4 format calculators, wipe-and-recompute idempotency, admin points-scheme override, the TournamentStage::brackets() default-ordering trap (D-06-09-A), and the A5 LOCKED withdrawn-participant retention semantics.

## What Landed

### StandingsCalculatorService (front-door)

Located at `App\Services\StandingsCalculatorService`. Replaces the plan 06-08 stub in-place — public signature `recalculate(Tournament $tournament): void` is preserved verbatim so the plan 06-08 callers (`app(StandingsCalculatorService::class)->recalculate($t)` inside `BracketAdvancementService::advance()`) continue to work via container resolution.

The dispatcher:

```php
public function recalculate(Tournament $tournament): void
{
    $strategy = match ($tournament->format) {
        'single_elimination' => $this->singleElim,
        'double_elimination' => $this->doubleElim,
        'round_robin'        => $this->roundRobin,
        'swiss'              => $this->swiss,
        default              => throw new InvalidArgumentException("Unknown tournament format: ..."),
    };

    DB::transaction(function () use ($tournament, $strategy): void {
        Tournament::query()->whereKey($tournament->id)->lockForUpdate()->first();  // Pitfall 6
        $tournament->standings()->delete();                                          // wipe
        $strategy->compute($tournament);                                             // recompute
    });
}
```

### Strategy interface

```php
namespace App\Services\Standings;

interface StandingsCalculatorStrategy
{
    public function compute(Tournament $tournament): void;
}
```

### SingleEliminationStandingsCalculator

| Concern | Behaviour |
|---------|-----------|
| Rank derivation | `placement = 2^(finalRound - eliminationRound) + 1`; winner=1; runner-up=2 |
| 8-clan placements | 1 winner, 1 runner-up (rank 2), 2 semi losers (rank 3), 4 QF losers (rank 5) |
| Wins/losses | Counted from MatchResult rows linked to brackets the participant played in |
| Draws | Always 0 (single-elim has no draws) |
| Points | Equal to wins (1 per win) |
| Tiebreak_score | 0 (unused for single-elim) |
| A5 LOCKED | Withdrawn/disqualified participants retain past match results |

The `derivePlacement()` method walks the bracket tree backwards via `->reorder()->orderByDesc('round_number')` to find the participant's elimination round. Tournament winners and runner-ups are short-circuited via the final-bracket check (no need to compute a formula).

### DoubleEliminationStandingsCalculator

| Concern | Behaviour |
|---------|-----------|
| Rank 1 | Grand-final winner (prefers reset-match winner when GF was reset) |
| Rank 2 | Grand-final loser |
| Rank 3 | L-bracket final loser |
| Rank 4+ | Walk L-bracket rounds backwards; losers in earlier rounds get higher numeric ranks |
| W/L counts | Aggregated across all 3 stages (winners-bracket, losers-bracket, grand-final) |
| Standings stage_id | Always grand-final stage (single canonical id for query simplicity) |

`computePlacements()` first reads the grand-final stage's highest-round bracket with a winner; assigns rank 1 + 2. Then walks the L-bracket from `max(round_number)` down to 1, assigning shared ranks to all losers in each L-round.

### RoundRobinStandingsCalculator

| Concern | Behaviour |
|---------|-----------|
| Default points | 3 per win, 1 per draw, 0 per loss (FIFA standard) |
| Admin override | `tournament.settings.roundrobin_points_per_{win,draw,loss}` |
| Tiebreaker | (points DESC, h2h DESC, point_diff DESC, seed ASC) |
| Head-to-head | Direct h2h only; Phase 9 mini-table for transitive ties |
| point_diff | `allies_score - axis_score` (signed sum across all matches) |

### SwissStandingsCalculator

| Concern | Behaviour |
|---------|-----------|
| Points | 1 per win, 0.5 per draw, 0 per loss |
| Bye | Winner of a single-participant bracket gets 1.0 points |
| Tiebreak_score | Buchholz = sum of each participant's opponents' FINAL points |
| Sort | (points DESC, buchholz DESC, seed ASC) |
| Stage aggregation | All swiss-round stages aggregate into one standings table keyed by the first swiss-round stage's id |

Two-pass algorithm:
1. **First pass** — iterate all brackets across all swiss-round stages; accumulate raw points + collect each participant's opponent list.
2. **Second pass** — for each participant, sum opponent points → Buchholz.

### Test Coverage — 9 GREEN it() Blocks / 31 Assertions

| Test | Format | Asserts |
|------|--------|---------|
| single-elim assigns rank 1 to tournament winner and rank 2 to runner-up | single-elim 4-clan | standings.rank for final winner = 1; runner-up = 2; 2 semi losers = 3 |
| single-elim 8-clan assigns ranks 1, 2, 3-tie, 5-tie by elimination round | single-elim 8-clan | rank distribution = [1, 2, 3, 3, 5, 5, 5, 5] |
| round-robin ranks by FIFA points 3/1/0 | round-robin 3-clan | rank order matches points-DESC (6pts > 3pts > 0pts) |
| round-robin 4-clan: h2h breaks tie between participants on equal points | round-robin 4-clan | 2 participants tied on 6pts → h2h winner ranks 1, loser ranks 2 |
| round-robin respects admin override of points-per-win via tournament settings | round-robin 3-clan | `roundrobin_points_per_win=5` → p1 with 2 wins gets 10pts (not 6) |
| swiss ranks by points then Buchholz tiebreak | swiss 4-clan | tied participants ranked by tiebreak_score (Buchholz) |
| double-elim assigns rank 1 to grand-final winner and rank 2 to grand-final loser | double-elim 4-clan | GF winner.rank=1; GF loser.rank=2 |
| recalculate wipes existing standings before recompute | round-robin 3-clan | 3 fixture rows + recalculate → still 3 rows (not 6); old wins=99 wiped |
| withdrawn participant retains W/L from played matches (A5 LOCKED) | round-robin 3-clan | p1 with 2 past wins + status='withdrawn' → standings.wins=2 retained |

### Verification

| Gate | Result |
|------|--------|
| `pest tests/Feature/Services/StandingsCalculatorServiceTest.php` | **PASS** — 9 passed / 31 assertions |
| `pest tests/Feature/Services/` (full Services regression) | **PASS** — 146 passed / 415 assertions |
| `pest` (full project) | **766 passed** / 17 failed (all pre-existing Wave 0 placeholders for plans 06-10 → 06-14; -1 from plan 06-08 baseline of 18 since this plan converted its own RED to GREEN) |
| `phpstan analyse` (full project, default config = app/ + bootstrap/ + database/ + routes/) | **PASS** — `[OK] No errors` |
| `phpstan analyse app/Services/StandingsCalculatorService.php app/Services/Standings/` (explicit calculator paths) | **PASS** — no errors |
| `pint --test` on all 6 created/modified non-test files | **PASS** — clean (1 auto-fix accepted on DoubleEliminationStandingsCalculator unary_operator_spaces) |
| `pint --test` on test file | **PASS** — clean |
| `grep -c 'placeholder' tests/Feature/Services/StandingsCalculatorServiceTest.php` | **0** — Wave 0 RED stub removed |
| Pattern fidelity — strategy + match() dispatcher | **honoured** — mirrors BracketGeneratorService verbatim |
| Pitfall 6 — Tournament::lockForUpdate | **mitigated** — first statement inside DB::transaction in recalculate() |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] TournamentStage::brackets() default ordering masked `orderByDesc('round_number')`**

- **Found during:** Task 2 (8-clan single-elim test). Initial implementation gave rank 5 to semi-final losers instead of rank 3. Trace showed `derivePlacement()` was finding the participant's ROUND-1 bracket as the "last bracket" they appeared in.
- **Issue:** `TournamentStage::brackets()` is defined as `hasMany(TournamentBracket::class)->orderBy('round_number')->orderBy('position')`. Querying off this relationship and appending `->orderByDesc('round_number')` does NOT replace the existing sort — Eloquent appends to the orderBy chain, so the final SQL is `ORDER BY round_number ASC, position ASC, round_number DESC`. The first key wins for ties, so round-1 always sorts first.
- **Fix:** Insert `->reorder()` before `->orderByDesc('round_number')` to drop the relationship's default sort. Applied in `SingleEliminationStandingsCalculator::derivePlacement()` and `DoubleEliminationStandingsCalculator::computePlacements()` (the `$finalDecider` query inside grand-final placement).
- **Files modified:** `apps/web/app/Services/Standings/SingleEliminationStandingsCalculator.php`, `apps/web/app/Services/Standings/DoubleEliminationStandingsCalculator.php`.
- **Commit:** Folded into Task 2 commit `10f76d5`.
- **D-06-09-A** documents the decision rationale.

**2. [Rule 1 - Bug] PHPStan L8 partial-array-shape inference rejected nested counter increments**

- **Found during:** Task 1 PHPStan gate — `$stats[$pid] = ['wins' => 0, ...]` followed by `$stats[$pAId]['wins']++` flagged with "Offset 'wins' might not exist on array{wins?: int, ...}". PHPStan widens the array shape after the increment to make all keys optional.
- **Issue:** The plan's `<interfaces>` scaffold used nested-shape arrays (`$stats[$pid] = ['wins' => ..., 'losses' => ..., ...]`) and incremented `$stats[$pid]['wins']++`. PHPStan L8 cannot prove the shape is preserved across the increment without explicit re-typing on every read.
- **Fix:** Split into 5 flat counter arrays — `$wins[$id]`, `$losses[$id]`, `$draws[$id]`, `$pointDiff[$id]`, `$h2h[$id]`. Each is typed as `array<string, int>` (or `array<string, array<string, int>>` for h2h). PHPStan accepts the flat shape without baseline entries. Same pattern applied to SwissStandingsCalculator.
- **Files modified:** `apps/web/app/Services/Standings/RoundRobinStandingsCalculator.php`, `apps/web/app/Services/Standings/SwissStandingsCalculator.php`.
- **Commit:** Folded into Task 1 commit `2fb90e9`.
- **D-06-09-C** documents the decision rationale.

**3. [Rule 1 - Bug] Swiss redundant null check after `$stages->isEmpty()` early return**

- **Found during:** Task 1 PHPStan gate — `Strict comparison using === between App\Models\TournamentStage and null will always evaluate to false`.
- **Issue:** `$stages = $tournament->stages()->where(...)->get(); if ($stages->isEmpty()) return;` already guarantees `$stages->first()` returns non-null. The subsequent `if ($primaryStage === null) return;` is dead code from a PHPStan POV.
- **Fix:** Removed the redundant null check; added a comment explaining the implicit guarantee.
- **Files modified:** `apps/web/app/Services/Standings/SwissStandingsCalculator.php`.
- **Commit:** Folded into Task 1 commit `2fb90e9`.

**4. [Rule 1 - Bug] Empty test marker (risky) for the 3-clan h2h scenario**

- **Found during:** Task 2 first run — Pest reported 1 risky test (no assertions) for the 3-clan h2h tiebreak case. The 3-clan setup produces a 3-way cycle on points where every tiebreaker chain falls through to seed ASC, making the test meaningless. The 4-clan h2h test below it covers the case correctly.
- **Issue:** The scaffold attempt at a 3-clan fixture was abandoned mid-write; the surrounding comment was left in place without filling in the assertion body.
- **Fix:** Removed the empty 3-clan placeholder. The 4-clan h2h test is the canonical fixture for this scenario.
- **Files modified:** `apps/web/tests/Feature/Services/StandingsCalculatorServiceTest.php`.
- **Commit:** Folded into Task 2 commit `10f76d5`.

**5. [Rule 3 - Blocking] Pint flagged unary_operator_spaces on DoubleEliminationStandingsCalculator**

- **Found during:** Task 1 Pint gate.
- **Issue:** Automatic style violation introduced by the initial write (spaces around `++` operator).
- **Fix:** `pint` auto-fixed in place; change committed verbatim. No semantic impact.
- **Files modified:** `apps/web/app/Services/Standings/DoubleEliminationStandingsCalculator.php`.
- **Commit:** Folded into Task 1 commit `2fb90e9`.

No other deviations. Plan executed substantially as written with the scaffold adjustments documented above.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-09-01 (Tampering — concurrent recalculate trampling) | mitigate | `DB::transaction` wraps recalculate(); `Tournament::lockForUpdate` is the FIRST statement. Concurrent advance() calls on the same tournament serialise on the parent row; standings recalc never races itself. |
| T-06-09-02 (Tampering — round-robin points scheme override) | accept | Admin-only access via Filament (D-012); `tournament.settings` JSONB is validated against numeric coercion at write time. |
| T-06-09-03 (Repudiation — no per-recalculate audit trail) | accept | `TournamentStanding::LogsActivity` fires per-row create/update; per-recalculate "summary" log row is Phase 9 polish. |
| T-06-09-04 (Tampering — stale standings if recalculate fails mid-flight) | mitigate | `DB::transaction` wrap — if any standings row insert fails, the whole recalculate (including the wipe) rolls back atomically. Existing standings rows survive. |
| T-06-09-05 (Information Disclosure — standings expose withdrawn participants' stats) | accept | A5 LOCKED — past matches retain their results in standings; admin-visible only via Filament; the public Standings tab respects participant status via Vue rendering (plan 06-12 deferred). |

## Threat Flags

None — Phase 6 plan 06-09 changes introduce 1 modified service (front-door swap) + 5 new strategy classes + 1 GREEN test file. No new endpoints, no new auth paths, no new file access, no new schema, no new network surface. All work stays inside the trust boundary documented by the plan's `<threat_model>`.

## Known Stubs

None — the SC-4 standings engine is complete. The 4 calculators handle all D-011 formats. Plan 06-11 (Filament "Recalculate standings" admin action) wires onto `StandingsCalculatorService::recalculate()` with no further changes needed in this service.

## Plan Linkages

- **Plan 06-08 (BracketAdvancementService)** — `advance()` calls `app(StandingsCalculatorService::class)->recalculate($tournament)` via container resolution (T-06-08-07 circular DI break). This plan's real implementation now drives real standings on every MatchResult save; `assignFinalPlacements()` (which copies standings.rank → tournament_participants.placement) starts producing real placements on tournament completion.
- **Plan 06-11 (Filament admin TournamentResource + 9 actions)** — the "Recalculate standings" admin action calls `StandingsCalculatorService::recalculate($tournament)` directly. The public signature is locked from plan 06-08; this plan only fills the body.
- **Plan 06-12 (public Standings tab)** — Vue page reads `tournament_standings WHERE tournament_id = X ORDER BY rank ASC`. The canonical-stage_id convention (D-06-09-D) means a single query suffices regardless of format.
- **Plan 06-14 (8-clan single-elim capstone)** — exercises the full chain end-to-end: admin start → BracketGeneratorService → BracketMatchMaterialiserService → players sign up → MatchResultService::upsert → MatchResultObserver → BracketAdvancementService → real standings recompute (via this plan) → next bracket materialised → tournament auto-completes → tournament_participants.placement populated.
- **Plan 06-13 (i18n key coverage)** — no new i18n keys introduced by this plan. The `InvalidArgumentException` in the format match() throws an untranslated developer error (path that should be unreachable given DB CHECK on tournaments.format).

## Self-Check: PASSED

- 5 created files exist on disk:
  - `apps/web/app/Services/Standings/StandingsCalculatorStrategy.php` — FOUND
  - `apps/web/app/Services/Standings/SingleEliminationStandingsCalculator.php` — FOUND
  - `apps/web/app/Services/Standings/DoubleEliminationStandingsCalculator.php` — FOUND
  - `apps/web/app/Services/Standings/RoundRobinStandingsCalculator.php` — FOUND
  - `apps/web/app/Services/Standings/SwissStandingsCalculator.php` — FOUND
- 2 modified files carry the expected amendments:
  - `apps/web/app/Services/StandingsCalculatorService.php` — `final class` with 4 constructor-injected strategies; `recalculate()` body wraps DB::transaction + lockForUpdate + standings wipe + match()-dispatched strategy call
  - `apps/web/tests/Feature/Services/StandingsCalculatorServiceTest.php` — no `placeholder` literal (grep returns 0); 9 it() blocks
- 2 task commits exist on `master`:
  - `2fb90e9` — feat(06-09): StandingsCalculatorService + 4 format strategies (Task 1)
  - `10f76d5` — test(06-09): GREEN StandingsCalculatorServiceTest — 9 tests, 4 formats (Task 2)
- Pest: 9 new passed / 31 assertions; full project 766 passed / 17 failed (all pre-existing Wave 0 placeholders for plans 06-10 → 06-14); +9 tests vs plan 06-08 baseline (757), −1 failure (18 → 17 since this plan converted its own RED to GREEN).
- PHPStan: full project `[OK] No errors`.
- Pint: clean on all 7 created/modified files (1 auto-fix accepted on DoubleEliminationStandingsCalculator).
- Plan acceptance criteria from `<tasks>` block — all satisfied (interface + 4 strategies + replaced front-door; rank derivation rules per format; FIFA defaults + admin override; Buchholz tiebreak; A5 LOCKED withdrawn-participant retention; Pitfall 6 lock).
- Wave 0 RED stub removed — confirmed by `grep -c 'placeholder'` returning 0 on the test file.
