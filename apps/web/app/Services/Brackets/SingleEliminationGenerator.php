<?php

declare(strict_types=1);

namespace App\Services\Brackets;

use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md Pattern 3
 * (single-elim inner_outer + byes) + 06-06-PLAN.md <interfaces>.
 *
 * Implements RESEARCH Pattern 3 verbatim:
 *
 *   1. Compute bracketSize = 2^ceil(log2(N)) (next power-of-2; e.g., N=7 → size=8).
 *   2. Resolve inner_outer pre-computed ordering for that size (sizes 2/4/8/16/32
 *      hardcoded; sizes >32 use the recursive doubling computeInnerOuter()).
 *   3. Pad missing seeds (bracketSize - N positions) with NULL — these are byes.
 *      Brackets Ninja inner_outer guarantees byes land on the lowest-N positions
 *      of the ordering array, which correspond to the TOP seeds (per the
 *      ordering's first elements being seed 1, 2, 3, ... distributed across the
 *      tree). Concretely: for N=7, bracketSize=8, the missing seed is "8" which
 *      pairs with seed 1 in round-1 position 1 → seed 1 gets the bye.
 *   4. Two-pass insert:
 *      - Pass 1: create all brackets with null advances_to_bracket_id; set
 *        winner_participant_id for byes (round-1 bracket where participantB
 *        is null).
 *      - Pass 2: UPDATE advances_to_bracket_id using a (round_number, position)
 *        lookup map; propagate bye-winners into round-2 participant slots.
 *
 *   Pitfall mitigations (Phase 6 RESEARCH):
 *     - Pitfall 2: use `(int) ceil($p / 2)` for advances_to target position, not
 *       `intdiv($p, 2)` (would map position 1 → 0).
 *     - Pitfall 11: advances_to_bracket_id != bracket_id is guaranteed because
 *       the algorithm always writes a round_number+1 bracket FK — never self.
 *       DB CHECK constraint is the safety net.
 *
 *   Bye-slot assignment in round 2 (canonical bracket fold):
 *     - round-1 odd position p (e.g., 1, 3) → next bracket's participant_a slot
 *     - round-1 even position p (e.g., 2, 4) → next bracket's participant_b slot
 *
 *   No DB::transaction wrap here — the caller (BracketGeneratorService) owns the
 *   transaction boundary so multi-stage generators can compose atomically.
 *
 * Threat refs:
 *   - T-06-06-03 (advances_to off-by-one) — mitigated by ceil()
 *   - T-06-06-05 (inner_outer ordering drift)  — mitigated by hardcoded const arrays for sizes 4/8/16/32
 */
final class SingleEliminationGenerator implements BracketGeneratorStrategy
{
    /**
     * Inner_outer pre-computed orderings (RESEARCH Pattern 3; cross-referenced
     * against brackets-manager.js inner_outer seeding).
     *
     * Indexed by bracket size (power of 2). Each entry is the 1-indexed seed
     * order for round-1 pairings: pair (ordering[0], ordering[1]),
     * (ordering[2], ordering[3]), ...
     *
     * Pre-computed sizes (4/8/16/32) cover practical HLL league play; sizes > 32
     * fall back to the recursive doubling computeInnerOuter() helper.
     *
     * @var array<int, list<int>>
     */
    private const INNER_OUTER_ORDERINGS = [
        2 => [1, 2],
        4 => [1, 4, 2, 3],
        8 => [1, 8, 4, 5, 2, 7, 3, 6],
        16 => [1, 16, 8, 9, 4, 13, 5, 12, 2, 15, 7, 10, 3, 14, 6, 11],
        32 => [
            1, 32, 16, 17, 8, 25, 9, 24, 4, 29, 13, 20, 5, 28, 12, 21,
            2, 31, 15, 18, 7, 26, 10, 23, 3, 30, 14, 19, 6, 27, 11, 22,
        ],
    ];

    /**
     * @param  Collection<int, TournamentParticipant>  $orderedParticipants
     */
    public function generate(Tournament $tournament, Collection $orderedParticipants): void
    {
        $n = $orderedParticipants->count();
        if ($n < 2) {
            throw new InvalidArgumentException(
                (string) __('tournaments.errors.insufficient_participants', ['min' => 2])
            );
        }

        // Create the single elim stage; layout shared with plan 06-07
        // DoubleEliminationGenerator W-bracket via layoutInStage().
        $stage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'elim',
            'ordinal' => 1,
            'name' => null,
            'settings' => null,
        ]);

        self::layoutInStage($stage, $orderedParticipants);
    }

    /**
     * Inner_outer ordering + two-pass insert + bye-winner propagation for a single-elim
     * tree. Extracted in plan 06-07 (Task 1) so DoubleEliminationGenerator can reuse
     * the W-bracket layout verbatim — see RESEARCH Pattern 6.
     *
     * The caller MUST have already created $stage. The map of generated brackets is
     * returned as `array<int $round, array<int $position, TournamentBracket>>` so
     * the caller can wire loser_advances_to_bracket_id on each W-bracket against
     * the L-bracket positions in a subsequent pass.
     *
     * @param  Collection<int, TournamentParticipant>  $orderedParticipants
     * @return array<int, array<int, TournamentBracket>>
     */
    public static function layoutInStage(TournamentStage $stage, Collection $orderedParticipants): array
    {
        $n = $orderedParticipants->count();
        $bracketSize = 2 ** (int) ceil(log($n, 2));
        $totalRounds = (int) log($bracketSize, 2);

        $ordering = self::INNER_OUTER_ORDERINGS[$bracketSize] ?? self::computeInnerOuter($bracketSize);

        // Map ordering positions (0..bracketSize-1) → participant or null (bye).
        // ordering values are 1-indexed seeds; we resolve by seedPosition - 1
        // against the 0-indexed orderedParticipants collection. If seedPosition > N
        // the participant is null (bye slot).
        /** @var array<int, TournamentParticipant|null> $participantsByPos */
        $participantsByPos = [];
        foreach ($ordering as $i => $seedPosition) {
            $participantsByPos[$i] = $orderedParticipants->get($seedPosition - 1);
        }

        // Two-pass insert.
        //
        // Pass 1: create all brackets with null advances_to; for round-1 byes
        // (participant_b is null), pre-assign winner_participant_id to the bye-grantee.
        /** @var array<int, array<int, TournamentBracket>> $byRoundPosition */
        $byRoundPosition = [];

        for ($r = 1; $r <= $totalRounds; $r++) {
            $bracketsInRound = (int) ($bracketSize / (2 ** $r));
            for ($p = 1; $p <= $bracketsInRound; $p++) {
                $participantA = null;
                $participantB = null;
                $winnerParticipantId = null;

                if ($r === 1) {
                    $pairIndex = ($p - 1) * 2;
                    $participantA = $participantsByPos[$pairIndex] ?? null;
                    $participantB = $participantsByPos[$pairIndex + 1] ?? null;

                    // Bye: B is null + A is present → A wins by bye.
                    if ($participantB === null && $participantA !== null) {
                        $winnerParticipantId = $participantA->id;
                    }
                    // Symmetric defence: A is null + B is present → B wins by bye.
                    // (Shouldn't happen with canonical inner_outer ordering — byes
                    // always land on the B side — but the algorithm holds either way.)
                    if ($participantA === null && $participantB !== null) {
                        $winnerParticipantId = $participantB->id;
                    }
                }

                $byRoundPosition[$r][$p] = TournamentBracket::create([
                    'tournament_stage_id' => $stage->id,
                    'round_number' => $r,
                    'position' => $p,
                    'participant_a_id' => $participantA?->id,
                    'participant_b_id' => $participantB?->id,
                    'winner_participant_id' => $winnerParticipantId,
                    'match_id' => null,
                    'advances_to_bracket_id' => null,
                    'loser_advances_to_bracket_id' => null,
                ]);
            }
        }

        // Pass 2: UPDATE advances_to_bracket_id for all non-final-round brackets.
        // Propagate bye-winners directly into the next round's participant slot.
        for ($r = 1; $r < $totalRounds; $r++) {
            foreach ($byRoundPosition[$r] as $p => $bracket) {
                $nextRoundPosition = (int) ceil($p / 2); // Pitfall 2 mitigation
                $nextBracket = $byRoundPosition[$r + 1][$nextRoundPosition];

                $bracket->update(['advances_to_bracket_id' => $nextBracket->id]);

                // Bye-winner propagation (round 1 → round 2 only — multi-round byes
                // do not exist in canonical inner_outer single-elim).
                if ($r === 1 && $bracket->winner_participant_id !== null) {
                    $slot = ($p % 2 === 1) ? 'participant_a_id' : 'participant_b_id';
                    $nextBracket->update([$slot => $bracket->winner_participant_id]);
                }
            }
        }

        return $byRoundPosition;
    }

    /**
     * Recursive inner_outer ordering computation for power-of-2 sizes > 32.
     * Reference: brackets-manager.js inner_outer seeding algorithm.
     *
     * Algorithm: ordering(2) = [1, 2]; ordering(2n) interleaves ordering(n)
     * with its mirror (2n + 1 - x for each x). Produces the canonical bracket
     * pairings where seeds 1 and 2 only meet in the final.
     *
     * Made static in plan 06-07 (Task 1) so the layoutInStage() helper — also
     * static — can call it without an instance. The existing PHPStan reflection
     * test invokes it via setAccessible(true), which works for static methods.
     *
     * @return list<int>
     */
    private static function computeInnerOuter(int $size): array
    {
        if ($size <= 2) {
            return [1, 2];
        }
        $half = (int) ($size / 2);
        $top = self::computeInnerOuter($half);
        $bottom = array_map(fn (int $x): int => $size + 1 - $x, $top);

        $result = [];
        foreach ($top as $i => $val) {
            $result[] = $val;
            $result[] = $bottom[$i];
        }

        return $result;
    }
}
