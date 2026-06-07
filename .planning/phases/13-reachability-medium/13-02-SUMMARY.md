# 13-02 SUMMARY — Double-elim N≥8 losers-bracket slot collision (REACH-02)

**Status:** ✅ Complete

**The bug (correctness):** `BracketAdvancementService` derived the loser-drop slot with
`resolveSlot($bracket->position)` — the SAME source-parity rule used for the winner-advance. In a
double-elim losers bracket, an LB *major* round receives two inbound participants: the LB-internal
winner (via `advances_to_bracket_id` → slot A) and a fresh winners-bracket loser (via
`loser_advances_to_bracket_id`). For an odd-positioned W bracket (e.g. W-r2-p1, position 1),
`resolveSlot(1)='a'` made the loser overwrite the LB winner already in slot A — silently dropping a
real participant for N≥8 (and N=4).

**The fix:** added `resolveLoserSlot(TournamentBracket)` matching `DoubleEliminationGenerator`'s drop
rules (lines 164-175):
- W-round-1 losers are paired into LB-round-1 → slot by source parity (odd→a, even→b).
- W-round-k (k≥2) losers drop into an LB major round whose slot A is reserved for the LB winner → they
  take **slot B**.
Only winners-bracket rows ever carry `loser_advances_to_bracket_id`, so the source `round_number`
cleanly distinguishes the two cases.

**Tests:** 2 focused N=8 repro tests (W-r2 loser and W-final loser each land in slot B without
overwriting a pre-seeded slot-A participant). Caught a secondary test-query gotcha: `brackets()` has a
default ascending order, so `orderByDesc('round_number')` is ignored — select the max round explicitly.

Gates: full Services/Tournaments/Models/Data suite (449 tests), PHPStan L8, Pint — all green.
