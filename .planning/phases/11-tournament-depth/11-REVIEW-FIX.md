---
phase: 11
fixed_at: 2026-06-04T12:30:00Z
review_path: (inline findings — no REVIEW.md file)
iteration: 1
findings_in_scope: 3
fixed: 3
skipped: 0
status: all_fixed
---

# Phase 11: Code Review Fix Report

**Fixed at:** 2026-06-04T12:30:00Z
**Source review:** Inline findings (CR-01, CR-02, WR-01)
**Iteration:** 1

**Summary:**
- Findings in scope: 3
- Fixed: 3
- Skipped: 0

## Fixed Issues

### CR-01: bye brackets break Swiss auto-completion

**Files modified:** `apps/web/app/Services/BracketAdvancementService.php`, `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php`
**Commits:** `83de865` (fix), `6d9fe24` (test correction — 'eliminated' → 'withdrawn' status)
**Applied fix:** Added `->whereNull('winner_participant_id')` to the Swiss premature-completion guard query in `allBracketsComplete()`. Byes have `match_id=null AND winner_participant_id≠null`; only a bracket that is BOTH unmaterialised AND undecided represents a pending round. Updated the method docblock to document the bye-exclusion logic and added `T-11-03-04` to the threat-ref table.

**New test:** `it('odd-N Swiss tournament auto-completes when last real match resolves (bye must not block completion)')` in `SwissAutoAdvanceTest.php` — creates a 1-round Swiss stage (final round, 2 active + 1 withdrawn participant so `totalRounds=1`) with 1 real match + 1 pre-assigned bye bracket, calls `advance()` on the last real match, asserts tournament status transitions to `'completed'`.

---

### CR-02: Elo applied twice under concurrent re-fire

**Files modified:** `apps/web/app/Services/BracketAdvancementService.php`, `apps/web/tests/Feature/Services/EloAdvancementHookTest.php`
**Commit:** `409d8ff`
**Applied fix:** Replaced the stale-object `$bracket->rated_at === null` check with a `TournamentBracket::whereKey($bracket->id)->lockForUpdate()->first()` re-fetch inside the `DB::transaction`. The idempotency check and the `rated_at` stamp both operate on the locked row (`$lockedBracket`), not the pre-transaction PHP object. Added `$bracket->rated_at = $lockedBracket->rated_at` so the in-memory object stays consistent. Updated the `T-11-03-01` threat-ref comment.

**New test:** `it('advance() reads rated_at from the locked DB row, not the stale PHP object (CR-02 concurrent guard)')` in `EloAdvancementHookTest.php` — fires the first advance (stamps `rated_at` in DB), then calls `advance()` again via a synthetic in-memory `MatchResult` (simulating a concurrent caller with a stale pre-transaction view), asserts both clans' `elo_rating` are unchanged.

---

### WR-01: null clan deref in seeding

**Files modified:** `apps/web/app/Services/TournamentSeedingService.php`, `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php`
**Commits:** `97c9d4e` (fix), `8c24b80` (PHPStan + Pint clean-up)
**Applied fix:** Replaced `$a->clan->elo_rating ?? 1500` with local variables (`$clanA = $a->clan; $ratingA = ($clanA !== null ? $clanA->elo_rating : null) ?? 1500`) to guard against soft-deleted clans at runtime. The nullsafe `?->` form was rejected by PHPStan's `nullsafe.neverNull` rule (Larastan types `BelongsTo` as non-null at the property level even though Eloquent returns null at runtime for soft-deleted relations). Pint fixed `\Throwable` → `Throwable` in the new test.

**New test:** `it('by_rank treats a soft-deleted clan as elo_rating 1500 without throwing TypeError (WR-01)')` in `TournamentSeedingServiceTest.php` — creates a high-elo normal clan + high-elo clan that is then soft-deleted, seeds the tournament, asserts no exception and that the normal clan gets seed 1 (1800 > 1500 fallback).

---

## Skipped Issues

None.

---

## Gate Results

```
make pest --filter=SwissAutoAdvanceTest|EloAdvancementHookTest|TournamentSeedingServiceTest|BracketAdvancementServiceTest
  35 passed (111 assertions)

make pest (full suite)
  1368 passed (4810 assertions)

make phpstan
  [OK] No errors

make pint --test
  PASS  674 files
```

---

_Fixed: 2026-06-04T12:30:00Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
