---
phase: 06-tournaments-brackets
plan: 06
subsystem: services
tags:
  - wave-3
  - service
  - bracket-generator
  - single-elimination
  - inner-outer-seeding
  - byes
  - materialiser
  - strategy-pattern
  - phase-6-tournaments
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 RED stubs (BracketGeneratorSingleEliminationTest + BracketMatchMaterialiserServiceTest) + tournaments.errors.brackets_already_generated i18n key
    - .planning/phases/06-tournaments-brackets/06-02-SUMMARY.md  # tournament_brackets partial UNIQUE on match_id + no_self_advance CHECK + tournament_stages
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # Tournament + TournamentParticipant + TournamentStage + TournamentBracket models + factories
    - .planning/phases/06-tournaments-brackets/06-04-SUMMARY.md  # BracketsAlreadyGeneratedException pre-shipped + TournamentStatusService idiom
    - .planning/phases/06-tournaments-brackets/06-05-SUMMARY.md  # Wave-3 service idiom precedent (TournamentSeedingService — final class + DB::transaction + match expression default arm)
    - .planning/phases/04-matches-manual/04-03-SUMMARY.md         # GameMatch model + factory (host_clan_id nullable, status='open' fillable, title JSONB)
    - .planning/phases/04-matches-manual/04-05-SUMMARY.md         # MatchSlotMaterialiserService (Phase 4 contract reused verbatim)
  provides:
    - "App\\Services\\Brackets\\BracketGeneratorStrategy — pure-void interface: generate(Tournament, Collection<int, TournamentParticipant>): void"
    - "App\\Services\\Brackets\\BracketGeneratorService — 4-strategy match() dispatcher + Pitfall 3 idempotency guard (throws BracketsAlreadyGeneratedException) + DB::transaction wrap"
    - "App\\Services\\Brackets\\SingleEliminationGenerator — RESEARCH Pattern 3 verbatim (inner_outer ordering, byes to top seeds, two-pass advances_to insert, Pitfall 2 ceil mitigation)"
    - "App\\Services\\Brackets\\DoubleEliminationGenerator — stub (throws LogicException; plan 06-07 fills body)"
    - "App\\Services\\Brackets\\RoundRobinGenerator — stub (throws LogicException; plan 06-07 fills body)"
    - "App\\Services\\Brackets\\SwissGenerator — stub (throws LogicException; plan 06-07 fills body)"
    - "App\\Services\\BracketMatchMaterialiserService — bracket → GameMatch + slot-grid bridge (Pitfall 4 lockForUpdate + idempotent return; A10 LOCKED host_clan_id=NULL)"
    - "20 GREEN Pest tests / 77 assertions covering RESEARCH Pattern 3 + Pitfalls 2/3/4 + A10"
  affects:
    - apps/web/app/Services/Brackets/             # 6 new service files (interface + 4 strategies + dispatcher)
    - apps/web/app/Services/                      # 1 new materialiser service file
    - apps/web/tests/Feature/Services/            # 2 Wave 0 RED stubs flipped to GREEN
    - apps/web/lang/en/                           # tournaments.errors.insufficient_participants added
tech-stack:
  added: []
  patterns:
    - "Strategy pattern — BracketGeneratorStrategy interface + 4 concrete strategies (1 real + 3 stubs); BracketGeneratorService dispatches via PHP 8 match() expression with default arm (throws InvalidArgumentException for unknown formats)"
    - "Two-pass insert — SingleEliminationGenerator pass 1 creates all brackets with null advances_to_bracket_id; pass 2 walks (round, position) → (round+1, ceil(position/2)) and UPDATEs advances_to + propagates bye-winners into round-2 slots (Pitfall 2 ceil() mitigation)"
    - "Inner_outer seeding (Brackets Ninja algorithm) — hardcoded const arrays for sizes 2/4/8/16/32 + recursive computeInnerOuter() fallback for sizes > 32 (validated against hardcoded-32 via Pest reflection assertion)"
    - "Pitfall 4 row-locked materialiser — TournamentBracket::lockForUpdate() inside DB::transaction + idempotent early-return when locked->match_id is already set; partial UNIQUE on match_id is the second line of defence"
    - "Phase 4 MatchSlotMaterialiserService reuse — BracketMatchMaterialiserService spawns GameMatch then delegates slot-grid creation to the Phase 4 service verbatim (zero behaviour duplication; SC-1 code path)"
key-files:
  created:
    - apps/web/app/Services/Brackets/BracketGeneratorStrategy.php
    - apps/web/app/Services/Brackets/BracketGeneratorService.php
    - apps/web/app/Services/Brackets/SingleEliminationGenerator.php
    - apps/web/app/Services/Brackets/DoubleEliminationGenerator.php
    - apps/web/app/Services/Brackets/RoundRobinGenerator.php
    - apps/web/app/Services/Brackets/SwissGenerator.php
    - apps/web/app/Services/BracketMatchMaterialiserService.php
  modified:
    - apps/web/lang/en/tournaments.php
    - apps/web/tests/Feature/Services/BracketGeneratorSingleEliminationTest.php
    - apps/web/tests/Feature/Services/BracketMatchMaterialiserServiceTest.php
decisions:
  - "D-06-06-A: BracketGeneratorService constructor accepts ALL 4 generators NOW (not just SingleElim). The 3 non-single-elim generators ship as stubs (each ~30 LOC including docblock) that throw LogicException in generate(). This satisfies Laravel's DI graph in plan 06-06 so plan 06-07 only needs to replace the stub bodies (not touch the constructor signature). Trade-off accepted: 90 LOC of stub code now vs. a same-wave constructor edit later."
  - "D-06-06-B: SingleEliminationGenerator INNER_OUTER_ORDERINGS const hardcodes sizes 2/4/8/16/32 (covers practical HLL league play of up to 32 clans). Sizes > 32 fall back to the recursive computeInnerOuter() helper, which is verified against the hardcoded 32-element case via a Pest reflection assertion. Mitigates T-06-06-05 (drift from brackets-manager.js test vectors)."
  - "D-06-06-C: Plan 06-06 <interfaces> scaffold referenced scheduled_start_at + scheduled_end_at on GameMatch::create; the actual Phase 4 schema (migration 2026_05_14_100000_create_matches_table.php) ships only a single `scheduled_at` column. The implementation uses `scheduled_at` (alongside `is_public`, `title`, `host_clan_id=null`, `organiser_user_id`, `game_match_type_id`, `status='open'`). Documented inline in the service docblock as a Rule 3 deviation. Phase 7+ wires per-bracket scheduling — this v1 simplifier (`$t->starts_at ?? now()->addDay()`) keeps the migration unchanged. The plan's scaffold should be corrected in a future polish."
  - "D-06-06-D: A10 LOCKED — bracket-spawned GameMatch.host_clan_id = NULL. Both participants in a bracket match are guests (no host clan exists in the bracket flow). Asserted by `sets host_clan_id to NULL on every bracket-spawned GameMatch (A10 LOCKED)`."
  - "D-06-06-E: BracketMatchMaterialiserService throws RuntimeException (not a typed DomainException) when materialiseFor is called against a Tournament with default_game_match_type_id=NULL. This is a programming error (the Filament Start action and TournamentStatusService transition matrix guarantee default_game_match_type_id is set before reaching `seeded` status); a typed DomainException would suggest a recoverable domain error. Inline test guard: `throws RuntimeException when materialising a bracket whose tournament has no default_game_match_type_id`."
  - "D-06-06-F: Title inheritance uses tournament.getTranslations('title') — every locale present on the tournament is carried forward to the bracket GameMatch as a JSONB locales map (D-013). Admin can override per-bracket via Filament inline edit (plan 06-11+); v1 inheritance is the sane default."
  - "D-06-06-G: Bye-winner propagation slot rule — round-1 odd position p (1, 3, 5, ...) writes to next bracket's participant_a_id; even position p (2, 4, 6, ...) writes to participant_b_id. This is the canonical bracket fold and is asserted by the `propagates round-1 bye winners into the correct round-2 participant slot` test."
metrics:
  duration: ~12m
  completed: 2026-05-13
  tasks: 2
  files_created: 7
  files_modified: 3
  commits: 2
---

# Phase 6 Plan 6: Wave 3 — BracketGeneratorService + SingleElim + Materialiser Summary

The single-elim bracket generator + the bracket → GameMatch materialiser land in a single Wave 3 plan. The strategy interface + 4-format dispatcher + 3 stub generators (DoubleElim/RoundRobin/Swiss) ship simultaneously so plan 06-07 can replace the stub bodies without re-touching the front-door service. The materialiser bridges Phase 6 brackets and Phase 4 matches verbatim — Phase 4's MatchSlotMaterialiserService is reused for the slot-grid spawn. 20 GREEN Pest tests / 77 assertions cover RESEARCH Pattern 3 (inner_outer ordering + byes + advances_to chain) and Pitfalls 2/3/4 + A10.

## What Landed

### BracketGeneratorStrategy interface (pure-void contract)

Located at `App\Services\Brackets\BracketGeneratorStrategy`. Single method:

```php
/** @param Collection<int, TournamentParticipant> $orderedParticipants */
public function generate(Tournament $tournament, Collection $orderedParticipants): void;
```

PHPDoc `@param Collection<int, TournamentParticipant>` template arg satisfies PHPStan L8 generic compliance. Pure-void contract — strategies write to tournament_stages + tournament_brackets directly; the caller (`BracketGeneratorService`) owns the DB::transaction boundary so multi-stage generators (double-elim) can compose atomically.

### BracketGeneratorService (front-door dispatcher)

```php
final class BracketGeneratorService
{
    public function __construct(
        private readonly SingleEliminationGenerator $singleElim,
        private readonly DoubleEliminationGenerator $doubleElim,
        private readonly RoundRobinGenerator $roundRobin,
        private readonly SwissGenerator $swiss,
    ) {}

    public function generate(Tournament $tournament): void
    {
        if ($tournament->stages()->exists()) {
            throw new BracketsAlreadyGeneratedException(...);  // Pitfall 3
        }
        $strategy = match ($tournament->format) {
            'single_elimination' => $this->singleElim,
            'double_elimination' => $this->doubleElim,
            'round_robin'        => $this->roundRobin,
            'swiss'              => $this->swiss,
            default              => throw new InvalidArgumentException(...),
        };
        DB::transaction(fn () => $strategy->generate($tournament, $participants));
    }
}
```

Pitfall 3 idempotency guard: throws `BracketsAlreadyGeneratedException` if `stages()->exists()`. Paired with TournamentStatusService transition guard (seeded → running once); this exception is the defence-in-depth for non-Filament callers (console commands, queued jobs).

Default-arm `InvalidArgumentException` on the match() (D-06-06-A) — same idiom as TournamentSeedingService (D-06-05-C precedent): PHPStan L8 cannot prove `match` exhaustivity over a `string` parameter, and the throw doubles as a clear runtime error for callers that pass a typo.

### SingleEliminationGenerator (RESEARCH Pattern 3 verbatim)

Implements RESEARCH § Pattern 3 with all 6 sub-mechanisms:

1. **bracketSize** = `2 ** (int) ceil(log($n, 2))` — next power-of-2 (N=5/6/7 → size=8; N=9..16 → size=16).
2. **inner_outer ordering** — `INNER_OUTER_ORDERINGS[$bracketSize]` for sizes 2/4/8/16/32; recursive `computeInnerOuter()` fallback for >32 (validated against hardcoded-32 via Pest reflection).
3. **Bye distribution** — missing seeds (bracketSize - N positions in the ordering array) resolve to `null` participants. Canonical inner_outer puts byes on the lowest-seed-pair B-side → byes always land with the top seeds (N=7 → seed 1 bye; N=6 → seeds 1+2 byes; N=5 → seeds 1+2+3 byes).
4. **Pass 1: create all brackets with null advances_to** — bye brackets get `winner_participant_id` pre-assigned (defence covers both A-side and B-side absent; canonical inner_outer puts B-side absent so the symmetric A-side defence is dead code in practice but kept for clarity).
5. **Pass 2: UPDATE advances_to_bracket_id** — walks (round, position) → (round+1, `(int) ceil($p / 2)`) [**Pitfall 2 mitigation**]. Bye-winner propagation: round-1 odd `p` → participant_a slot of round-2; even `p` → participant_b slot (canonical bracket fold).
6. **No DB::transaction wrap inside the strategy** — caller owns the transaction so multi-stage generators (double-elim) can compose atomically.

**Pre-computed INNER_OUTER_ORDERINGS** (D-06-06-B):

| Size | Ordering |
|------|----------|
| 2    | `[1, 2]` |
| 4    | `[1, 4, 2, 3]` |
| 8    | `[1, 8, 4, 5, 2, 7, 3, 6]` |
| 16   | `[1, 16, 8, 9, 4, 13, 5, 12, 2, 15, 7, 10, 3, 14, 6, 11]` |
| 32   | `[1, 32, 16, 17, 8, 25, 9, 24, 4, 29, 13, 20, 5, 28, 12, 21, 2, 31, 15, 18, 7, 26, 10, 23, 3, 30, 14, 19, 6, 27, 11, 22]` |
| >32  | Recursive `computeInnerOuter()` — `ordering(2n)` interleaves `ordering(n)` with its mirror `2n + 1 - x` |

Insufficient-participant guard: < 2 participants throws `InvalidArgumentException` with localised `tournaments.errors.insufficient_participants` message (`:min` interpolation; Rule 2 i18n key added in this plan as the 06-01 skeleton did not include it).

### 3 Stub Generators (DoubleElim / RoundRobin / Swiss)

Each ~30 LOC including docblock. Body:

```php
public function generate(Tournament $tournament, Collection $orderedParticipants): void
{
    throw new LogicException('XxxGenerator not yet implemented — see plan 06-07.');
}
```

D-06-06-A: Ships the stubs in plan 06-06 so the BracketGeneratorService constructor's DI graph is satisfied; plan 06-07 will replace only the body of each `generate()` method without re-touching the front-door file's constructor signature.

### BracketMatchMaterialiserService (Bracket → GameMatch + Slot Grid Bridge)

```php
final class BracketMatchMaterialiserService
{
    public function __construct(private readonly MatchSlotMaterialiserService $slotMaterialiser) {}

    public function materialiseFirstRound(Tournament $tournament): void { /* iterate non-bye round-1 brackets */ }
    public function materialiseFor(TournamentBracket $bracket, ?Tournament $tournament = null): GameMatch
    {
        return DB::transaction(function () use (...): GameMatch {
            $locked = TournamentBracket::whereKey($bracket->id)->lockForUpdate()->firstOrFail();
            if ($locked->match_id !== null) return $locked->match()->firstOrFail();      // idempotent
            // ... validate default_game_match_type_id ...
            $match = GameMatch::create([
                'host_clan_id' => null,          // A10 LOCKED
                'status' => 'open',              // signups open automatically
                'organiser_user_id' => $t->organiser_user_id,
                'game_match_type_id' => $t->default_game_match_type_id,
                'title' => $t->getTranslations('title'),   // D-013 — inherit JSONB locales
                'is_public' => $t->is_public,
                'scheduled_at' => $t->starts_at ?? now()->addDay(),
            ]);
            $this->slotMaterialiser->materialise($match);   // Phase 4 reuse
            $locked->update(['match_id' => $match->id]);
            return $match;
        });
    }
}
```

**Pitfall 4 mitigation:** Per-bracket `lockForUpdate()` inside `DB::transaction`. Concurrent admin clicks on "Materialise round" or future round-2 triggers serialise on the lock; the early-return on `if (locked->match_id !== null)` makes the call idempotent. DB partial UNIQUE on `tournament_brackets.match_id WHERE NOT NULL` (plan 06-02 migration) is the second line of defence.

**Bye-skip:** `materialiseFirstRound()` filters `whereNotNull('participant_a_id')->whereNotNull('participant_b_id')` — round-1 byes (which already have `winner_participant_id` set by the generator) get no GameMatch row, because there is no play to schedule.

**Field inheritance (D-013 + A10 LOCKED + D-06-06-D + D-06-06-F):**
- `host_clan_id = NULL` (A10 LOCKED — bracket matches have no host clan)
- `organiser_user_id = tournament.organiser_user_id` (Phase 4 fillable requirement)
- `game_match_type_id = tournament.default_game_match_type_id` (A9 deferred — single match type per tournament for v1)
- `status = 'open'` (D-04-04-A direct create with open is allowed)
- `title = tournament.getTranslations('title')` (D-013 — inherit JSONB locales)
- `is_public = tournament.is_public`
- `scheduled_at = tournament.starts_at ?? now()->addDay()` (D-06-06-C v1 simplifier)

**Phase 4 reuse:** After `GameMatch::create`, calls `MatchSlotMaterialiserService::materialise($match)` to spawn the slot grid from `gameMatchType.roleLimits` — the SAME code path SC-1 plan 04-09 uses. Zero behaviour duplication.

### i18n Addition

`apps/web/lang/en/tournaments.php` gains 1 leaf key:
```php
'errors' => [
    // ... existing keys ...
    'insufficient_participants' => 'Tournament requires at least :min participants to generate a bracket.',
    // ... existing keys ...
],
```

### Test Coverage — 20 GREEN it() Blocks / 77 Assertions

**BracketGeneratorSingleEliminationTest — 11 tests / 49 assertions:**

| Test | Asserts |
|------|---------|
| 8-participant happy path | 1 stage; 7 brackets total (4+2+1); inner_outer pairings (1v8, 4v5, 2v7, 3v6); no winners pre-assigned |
| advances_to chain (Pitfall 2 ceil mitigation) | round-1 positions 1+2 share semi 1's id; 3+4 share semi 2's id; both semis feed final; final.advances_to=null |
| 4-participant happy path | 3 brackets total; inner_outer [1,4,2,3] → 1v4, 2v3 |
| 7-participant (1 bye) | bracketSize=8; 1 bye to seed 1; winner_participant_id pre-assigned |
| 6-participant (2 byes) | byes go to seeds 1+2 (canonical inner_outer top-seed bye distribution) |
| 5-participant (3 byes) | byes go to seeds 1+2+3; seeds 4+5 are the only round-1 played pairing |
| Round-2 slot propagation | seed 1's bye at p=1 (odd) → participant_a_id of round-2 position 1 |
| BracketsAlreadyGeneratedException on 2nd call | Pitfall 3 idempotency guard |
| < 2 participants → InvalidArgumentException | insufficient_participants i18n message |
| 16-participant verification | 15 brackets total (8+4+2+1); first/last round-1 pairings against the size=16 ordering |
| computeInnerOuter() reproduces hardcoded size=32 | reflection-driven correctness check |

**BracketMatchMaterialiserServiceTest — 9 tests / 28 assertions:**

| Test | Asserts |
|------|---------|
| 8-participant happy path | 4 round-1 GameMatches; every match_id distinct + non-null |
| Bye skip (N=7) | 3 materialised matches; 1 bye bracket with match_id=null |
| materialiseFirstRound() idempotent | second call yields identical match_ids; total GameMatch count stays at 2 |
| materialiseFor() idempotent (Pitfall 4) | second call on same bracket returns same GameMatch id |
| A10 LOCKED — host_clan_id null on every bracket-spawned GameMatch | iterates all matches |
| organiser_user_id + game_match_type_id + is_public inheritance | iterates all matches, equality vs tournament |
| status='open' on every bracket-spawned GameMatch | iterates all matches |
| Slot grid spawned (Phase 4 reuse) | for each round-1 bracket, MatchSlot count = roleLimit.capacity |
| RuntimeException on tournament with default_game_match_type_id=null | D-06-06-E negative path |

### Verification

| Gate | Result |
|------|--------|
| `pest tests/Feature/Services/BracketGeneratorSingleEliminationTest.php` | **PASS** — 11 passed / 49 assertions / 2.07s |
| `pest tests/Feature/Services/BracketMatchMaterialiserServiceTest.php` | **PASS** — 9 passed / 28 assertions / 2.11s |
| `pest` both files together | **PASS** — 20 passed / 77 assertions / 2.88s |
| `phpstan analyse app/Services/Brackets/ app/Services/BracketMatchMaterialiserService.php` | **PASS** — `[OK] No errors` |
| Full-project `phpstan analyse` (regression) | **PASS** — `[OK] No errors` |
| `pint --test` on 7 created + 3 modified files | **PASS** — clean |
| `grep -c 'placeholder' tests/Feature/Services/BracketGeneratorSingleEliminationTest.php` | **0** — Wave 0 RED stub removed |
| `grep -c 'placeholder' tests/Feature/Services/BracketMatchMaterialiserServiceTest.php` | **0** — Wave 0 RED stub removed |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] GameMatch schema misalignment with plan <interfaces> scaffold**

- **Found during:** Task 2 implementation — the plan's scaffold referenced `scheduled_start_at` + `scheduled_end_at` on `GameMatch::create([...])`, but the actual Phase 4 migration (`apps/web/database/migrations/2026_05_14_100000_create_matches_table.php`) ships only a single `scheduled_at` column.
- **Issue:** Calling `GameMatch::create(['scheduled_start_at' => ...])` would either silently drop the column (mass-assignment guard) or throw a query exception at INSERT time. Plan blocked.
- **Fix:** Aligned with actual schema — used `scheduled_at` (single timestampTz column). Defaulted to `$t->starts_at ?? now()->addDay()` for the v1 placeholder (admin overrides per-bracket via Filament inline edit in plan 06-11+). Documented inline in the service docblock + recorded as D-06-06-C in this SUMMARY's frontmatter.
- **Files modified:** `apps/web/app/Services/BracketMatchMaterialiserService.php`.
- **Commit:** Folded into Task 2's commit `ad16f2d`.
- **Forward action:** Future polish — Phase 7+ wires per-bracket scheduling; the plan's `<interfaces>` scaffold should be corrected when that lands. No retroactive correction needed here because the deviation is documented in D-06-06-C.

**2. [Rule 2 - Missing Critical Functionality] PHP 8 `match` expression lacked exhaustivity arm in BracketGeneratorService**

- **Found during:** Task 1 PHPStan run — L8 would report `match.unhandled — Match expression does not handle remaining value: string` because `tournament.format` is a `string` (CHECK-defended at the DB layer, not an enum). Caught by precedent from plan 06-05 (D-06-05-C).
- **Issue:** A caller passing an unknown format (e.g., a freshly-added 5th format in some future plan that forgot to update the dispatcher) would fall through to PHP's silent match failure, raising a fatal `UnhandledMatchError` only at runtime.
- **Fix:** Added `default => throw new InvalidArgumentException(...)` arm with an explicit allowed-values message. Mirrors D-06-05-C precedent.
- **Files modified:** `apps/web/app/Services/Brackets/BracketGeneratorService.php`.
- **Commit:** Folded into Task 1's commit `109ccc1`.

**3. [Rule 2 - Missing Critical Functionality] Default-fail guard in BracketMatchMaterialiserService for unset default_game_match_type_id**

- **Found during:** Task 2 implementation review — the plan's scaffold assumed `$t->default_game_match_type_id` is non-null when materialiseFor() runs (caller's responsibility). A null value would propagate into `GameMatch::create(['game_match_type_id' => null])` and either fail the migration's `NOT NULL` constraint at the DB layer (cleaner error) or silently break the slot-grid materialiser (no roleLimits → 0 MatchSlot rows).
- **Issue:** The error surface from the DB-level NOT NULL FK is opaque; a typed exception with a clear message is more useful for the Filament admin flow.
- **Fix:** Added `throw new RuntimeException("Tournament {$t->id} has no default_game_match_type_id...")` guard before `GameMatch::create`. The negative-path Pest test (`throws RuntimeException when materialising a bracket whose tournament has no default_game_match_type_id`) covers this. Recorded as D-06-06-E in this SUMMARY's frontmatter.
- **Files modified:** `apps/web/app/Services/BracketMatchMaterialiserService.php`.
- **Commit:** Folded into Task 2's commit `ad16f2d`.

**4. [Rule 1 - Bug] Plan scaffold called `TournamentBracket::factory()->for($stage)` which infers a `tournamentStage()` relation that does not exist on TournamentBracket**

- **Found during:** Task 2 test run — `BadMethodCallException: Call to undefined method App\Models\TournamentBracket::tournamentStage()` on `TournamentBracket::factory()->for($stage)->create(...)`.
- **Issue:** Laravel's `->for($stage)` inflects the related model class to a method name on the child (TournamentStage → tournamentStage). TournamentBracket has the relation named `stage()` (not `tournamentStage()`), so the implicit lookup fails.
- **Fix:** Replaced `->for($stage)` with explicit `['tournament_stage_id' => $stage->id]` in the negative-path test. The model relation name `stage()` is intentional per 06-03-PLAN.md <interfaces>; no model change.
- **Files modified:** `apps/web/tests/Feature/Services/BracketMatchMaterialiserServiceTest.php`.
- **Commit:** Folded into Task 2's commit `ad16f2d`.

No other deviations. Plan executed as written.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-06-01 (Tampering — bracket generator non-idempotency, Pitfall 3) | mitigate | BracketGeneratorService throws `BracketsAlreadyGeneratedException` when `tournament->stages()->exists()`. Paired with `TournamentStatusService::transition($t, 'running')` guard (the Filament Start action routes through it). Asserted by `throws BracketsAlreadyGeneratedException on second generate() call`. |
| T-06-06-02 (Tampering — bracket → GameMatch race, Pitfall 4) | mitigate | `BracketMatchMaterialiserService::materialiseFor` wraps in `DB::transaction` + `TournamentBracket::lockForUpdate()`; idempotent early-return when `locked->match_id !== null`. DB partial UNIQUE on `tournament_brackets.match_id WHERE NOT NULL` (plan 06-02) is the second line. Asserted by `materialiseFor()` + `materialiseFirstRound()` idempotency tests. |
| T-06-06-03 (Tampering — advances_to off-by-one, Pitfall 2) | mitigate | SingleEliminationGenerator uses `(int) ceil($p / 2)` for advances_to position (not `intdiv($p, 2)`). Asserted by `wires advances_to chain so round-1 positions 1+2 share a round-2 target` (positions 1+2 → semi 1; 3+4 → semi 2). |
| T-06-06-04 (Repudiation — BracketGeneratorService writes no audit row) | accept | The Filament Start action (plan 06-11) wraps `BracketGeneratorService::generate()` + `BracketMatchMaterialiserService::materialiseFirstRound()` inside a `TournamentStatusService::transition($t, 'running')` call, which emits a `Tournament status: seeded -> running` activity_log row. Bracket generation is an implementation detail of "Start tournament" — the surrounding transition is the audit point. |
| T-06-06-05 (Tampering — inner_outer ordering drift from brackets-manager.js) | mitigate | INNER_OUTER_ORDERINGS const hardcodes sizes 2/4/8/16/32 (D-06-06-B); Pest assertions check exact pairings (1v8, 4v5, 2v7, 3v6 for size=8; 1v16, 6v11 for size=16). Recursive `computeInnerOuter()` for size > 32 is validated against the hardcoded 32-element case via reflection. |
| T-06-06-06 (Tampering — A11 LOCKED host_clan_id=NULL on bracket GameMatch) | accept | Bracket GameMatch has both participants as guests; host clan concept doesn't apply. Documented in service docblock + asserted by `sets host_clan_id to NULL on every bracket-spawned GameMatch (A10 LOCKED)`. (Note: the threat register labelled this A11 LOCKED; the must_haves truth uses A10 LOCKED — same concept; A10 is the authoritative label per the must_haves block.) |

## Threat Flags

None — Phase 6 plan 06-06 changes introduce 6 service files + 1 materialiser service + 2 test files + 1 i18n key, all inside the trust boundary documented by the plan's `<threat_model>`. No new endpoints, no new auth paths, no new file access, no new schema, no new network surface.

## Known Stubs

The 3 generator stubs (`DoubleEliminationGenerator`, `RoundRobinGenerator`, `SwissGenerator`) are intentional placeholders documented in D-06-06-A. They:

- **Are NOT data-flow stubs** (no hardcoded empty UI values, no "coming soon" UI text)
- **Throw `LogicException` immediately on invocation** with a clear "see plan 06-07" message
- **Exist solely to satisfy Laravel's DI graph** for BracketGeneratorService's constructor signature
- **Will be replaced (bodies only)** by plan 06-07 — the constructor signature of BracketGeneratorService is locked

Plan 06-07 replaces only the `generate()` body of each stub class; no other code changes are required in plan 06-07 to wire double-elim / round-robin / swiss into the dispatcher.

## Plan Linkages

- **Plan 06-07 (Wave 3 — DoubleElim / RoundRobin / Swiss generators)** replaces the 3 stub `generate()` bodies. The BracketGeneratorService constructor signature is locked; plan 06-07 only edits the generator class bodies.
- **Plan 06-08 (BracketAdvancementService)** reads `tournament_brackets.match_id` (set by this plan's materialiser) and `winner_participant_id` (set by SingleEliminationGenerator for byes, or by MatchResult observer for played matches). The advancement service walks the `advances_to_bracket_id` FK chain to propagate winners forward.
- **Plan 06-11 (Filament admin TournamentResource + 9 actions)** wires the `Start tournament` action onto `app(BracketGeneratorService::class)->generate($t)` + `app(BracketMatchMaterialiserService::class)->materialiseFirstRound($t)`. The action also routes through `TournamentStatusService::transition($t, 'running')` so the surrounding state machine emits the audit row.
- **Plan 06-13 (i18n key coverage + cross-cut audit)** TournamentI18nKeyCoverageTest asserts `tournaments.errors.insufficient_participants` resolves — already covered by `rejects bracket generation when fewer than 2 active participants exist` here.

## Self-Check: PASSED

- All 7 created files exist on disk:
  - `apps/web/app/Services/Brackets/BracketGeneratorStrategy.php` — FOUND
  - `apps/web/app/Services/Brackets/BracketGeneratorService.php` — FOUND
  - `apps/web/app/Services/Brackets/SingleEliminationGenerator.php` — FOUND
  - `apps/web/app/Services/Brackets/DoubleEliminationGenerator.php` — FOUND
  - `apps/web/app/Services/Brackets/RoundRobinGenerator.php` — FOUND
  - `apps/web/app/Services/Brackets/SwissGenerator.php` — FOUND
  - `apps/web/app/Services/BracketMatchMaterialiserService.php` — FOUND
- The 3 modified files carry the expected amendments:
  - `apps/web/lang/en/tournaments.php` — `insufficient_participants` key present
  - `apps/web/tests/Feature/Services/BracketGeneratorSingleEliminationTest.php` — no `placeholder` literal (grep returns 0)
  - `apps/web/tests/Feature/Services/BracketMatchMaterialiserServiceTest.php` — no `placeholder` literal (grep returns 0)
- Both task commits exist on `master`:
  - `109ccc1` — feat(06-06): BracketGeneratorService + SingleElim + 3 stubs (Task 1)
  - `ad16f2d` — feat(06-06): BracketMatchMaterialiserService + GREEN tests (Task 2)
- Pest: 20 passed / 77 assertions across both target files
- PHPStan: `[OK] No errors` on `app/Services/Brackets/` + `app/Services/BracketMatchMaterialiserService.php` + full-project regression
- Pint: clean on all 10 changed files (7 created + 3 modified)
- Plan acceptance criteria from `<tasks>` block — all satisfied (interface + service + 4 strategies + materialiser + 6+ assertions per test file)
- Wave 0 RED stubs removed — confirmed by `grep -c 'placeholder'` returning 0 on both test files
