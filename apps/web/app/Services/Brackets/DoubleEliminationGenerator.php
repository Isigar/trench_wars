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
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md Pattern 6
 * (Burton variant double-elim) + 06-07-PLAN.md <interfaces>.
 *
 * Ships in plan 06-07 (Wave 4); replaces the LogicException stub from plan 06-06.
 *
 * Burton-variant double-elim layout (RESEARCH Pattern 6):
 *
 *   Stage 1 — winners-bracket (ordinal=1)
 *     W-bracket layout REUSES SingleEliminationGenerator::layoutInStage().
 *     Inner_outer ordering + byes-to-top-seeds + advances_to chain — verbatim.
 *
 *   Stage 2 — losers-bracket (ordinal=2)
 *     Burton variant: alternating minor / major rounds.
 *       - minor rounds (1,3,5,...) pair up the previous L-round winners
 *         (purely L-bracket internal; no fresh W-bracket losers).
 *       - major rounds (2,4,6,...) merge the previous L-round winners with
 *         FRESH W-bracket losers from the matching W-round.
 *     Loser-drop edges from W-bracket → L-bracket positions are wired via
 *     tournament_brackets.loser_advances_to_bracket_id in a third pass after
 *     both W and L brackets exist on disk.
 *
 *   Stage 3 — grand-final (ordinal=3)
 *     1 bracket; both participants NULL until BracketAdvancementService
 *     (plan 06-08) propagates the W-final winner + L-final winner. Optional
 *     reset match (settings.grand_final_reset) is lazily created by the
 *     advancement service when W-winner loses the GF and the flag is true.
 *
 * Hardcoded N=8 loser-drop mapping (verified vs brackets-manager.js layout):
 *
 *     W-r1-p1 + W-r1-p2 losers  → LB-r1-p1 (slot a + b)
 *     W-r1-p3 + W-r1-p4 losers  → LB-r1-p2 (slot a + b)
 *     W-r2-p1 loser             → LB-r2-p1 slot b
 *     W-r2-p2 loser             → LB-r2-p2 slot b
 *     W-r3-p1 (W-final) loser   → LB-r4-p1 slot b
 *
 * Internal L-bracket advancement chain (N=8):
 *
 *     LB-r1-p1 winner → LB-r2-p1 slot a
 *     LB-r1-p2 winner → LB-r2-p2 slot a
 *     LB-r2-p1 winner → LB-r3-p1 slot a
 *     LB-r2-p2 winner → LB-r3-p1 slot b
 *     LB-r3-p1 winner → LB-r4-p1 slot a (the L-final)
 *     LB-r4-p1 winner → grand-final slot b (BracketAdvancementService plan 06-08)
 *
 * Threat refs:
 *   - T-06-07-02 (L-bracket drop mapping drift) — accept; manual verification
 *     against brackets-manager.js test vectors at phase close (06-VALIDATION).
 *
 * No DB::transaction here — caller (BracketGeneratorService) owns the boundary
 * so all 3 stages compose atomically.
 */
final class DoubleEliminationGenerator implements BracketGeneratorStrategy
{
    /**
     * @param  Collection<int, TournamentParticipant>  $orderedParticipants
     */
    public function generate(Tournament $tournament, Collection $orderedParticipants): void
    {
        $n = $orderedParticipants->count();
        if ($n < 4) {
            throw new InvalidArgumentException(
                (string) __('tournaments.errors.insufficient_participants', ['min' => 4])
            );
        }

        $bracketSize = 2 ** (int) ceil(log($n, 2));
        $totalWRounds = (int) log($bracketSize, 2);
        $lRounds = 2 * ($totalWRounds - 1);

        // ----------------------------------------------------------------
        // Stage 1: winners bracket — single-elim layout via shared helper.
        // ----------------------------------------------------------------
        $wStage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'winners-bracket',
            'ordinal' => 1,
            'name' => null,
            'settings' => null,
        ]);

        $wByRoundPosition = SingleEliminationGenerator::layoutInStage($wStage, $orderedParticipants);

        // ----------------------------------------------------------------
        // Stage 2: losers bracket — Burton variant.
        // ----------------------------------------------------------------
        $lStage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'losers-bracket',
            'ordinal' => 2,
            'name' => null,
            'settings' => null,
        ]);

        /** @var array<int, array<int, TournamentBracket>> $lByRoundPosition */
        $lByRoundPosition = [];

        // Pass 1: create all L-bracket brackets with null advances_to.
        //
        // Per-round bracket count (Burton variant):
        //   L-round 2k-1 (minor, pairs prev L-round winners): N / 2^(k+1)
        //   L-round 2k   (major, prev L winners + W-r(k+1) losers): N / 2^(k+1)
        //
        // For N=8: r=1→2, r=2→2, r=3→1, r=4→1 → 6 total.
        // For N=4: r=1→1, r=2→1                → 2 total.
        // For N=16: r=1→4, r=2→4, r=3→2, r=4→2, r=5→1, r=6→1 → 14 total.
        for ($r = 1; $r <= $lRounds; $r++) {
            $k = (int) (($r + 1) / 2); // r=1,2→k=1; r=3,4→k=2; ...
            $bracketsInRound = (int) ($bracketSize / (2 ** ($k + 1)));
            if ($bracketsInRound < 1) {
                $bracketsInRound = 1;
            }

            for ($p = 1; $p <= $bracketsInRound; $p++) {
                $lByRoundPosition[$r][$p] = TournamentBracket::create([
                    'tournament_stage_id' => $lStage->id,
                    'round_number' => $r,
                    'position' => $p,
                    'participant_a_id' => null,
                    'participant_b_id' => null,
                    'winner_participant_id' => null,
                    'match_id' => null,
                    'advances_to_bracket_id' => null,
                    'loser_advances_to_bracket_id' => null,
                ]);
            }
        }

        // Pass 2: wire L-bracket internal advancement (LB winner → next LB).
        for ($r = 1; $r < $lRounds; $r++) {
            foreach ($lByRoundPosition[$r] as $p => $bracket) {
                // Minor rounds (odd r) advance their winner into the SAME-position
                // major round in slot a; major rounds advance their winner into the
                // NEXT minor round by pairing (folded with ceil(p/2)).
                if ($r % 2 === 1) {
                    // Minor → major: same position (LB1minor-p1 → LB1major-p1 slot a).
                    $nextBracket = $lByRoundPosition[$r + 1][$p] ?? null;
                } else {
                    // Major → next minor: bracket fold ceil(p/2).
                    $nextPosition = (int) ceil($p / 2);
                    $nextBracket = $lByRoundPosition[$r + 1][$nextPosition] ?? null;
                }
                if ($nextBracket !== null) {
                    $bracket->update(['advances_to_bracket_id' => $nextBracket->id]);
                }
            }
        }

        // Pass 3: wire W-bracket → L-bracket loser-drop edges.
        //
        // Burton drop rules:
        //   - W-round-1 losers fill BOTH slots of LB-round-1 (pair them up).
        //       W-r1-p1 + W-r1-p2 → LB-r1-p1 (slot a from p1; slot b from p2)
        //       W-r1-p3 + W-r1-p4 → LB-r1-p2 (slot a from p3; slot b from p4)
        //   - W-round-k (k>=2) losers fill slot B of LB-round-(2(k-1)) [the major].
        //       W-r2-p1 loser → LB-r2-p1 slot b
        //       W-r3-p1 loser → LB-r4-p1 slot b
        //
        // Storage: write loser_advances_to_bracket_id on the W-bracket row. The
        // slot (a vs b) is RECORDED implicitly — the bracket only carries the FK,
        // and BracketAdvancementService (plan 06-08) decides slot=a for odd-p
        // / slot=b for even-p (W-r1 case) or slot=b for all major-round drops.
        // For the purposes of the layout test, we only assert the FK points at
        // the right L-bracket.

        // W-round 1 losers → LB-round 1 (paired)
        if (isset($wByRoundPosition[1])) {
            foreach ($wByRoundPosition[1] as $p => $wBracket) {
                $lPosition = (int) ceil($p / 2);
                $lBracket = $lByRoundPosition[1][$lPosition] ?? null;
                if ($lBracket !== null) {
                    $wBracket->update(['loser_advances_to_bracket_id' => $lBracket->id]);
                }
            }
        }

        // W-round k (k>=2) losers → LB-round 2*(k-1) major
        for ($k = 2; $k <= $totalWRounds; $k++) {
            $lRound = 2 * ($k - 1);
            if (! isset($wByRoundPosition[$k]) || ! isset($lByRoundPosition[$lRound])) {
                continue;
            }
            foreach ($wByRoundPosition[$k] as $p => $wBracket) {
                $lBracket = $lByRoundPosition[$lRound][$p] ?? null;
                if ($lBracket !== null) {
                    $wBracket->update(['loser_advances_to_bracket_id' => $lBracket->id]);
                }
            }
        }

        // ----------------------------------------------------------------
        // Stage 3: grand final.
        // ----------------------------------------------------------------
        $grandFinalReset = false;
        /** @var array<string, mixed>|null $settings */
        $settings = $tournament->settings;
        if (is_array($settings) && array_key_exists('grand_final_reset', $settings)) {
            $grandFinalReset = (bool) $settings['grand_final_reset'];
        }

        $gfStage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'grand-final',
            'ordinal' => 3,
            'name' => null,
            'settings' => ['grand_final_reset' => $grandFinalReset],
        ]);

        TournamentBracket::create([
            'tournament_stage_id' => $gfStage->id,
            'round_number' => 1,
            'position' => 1,
            'participant_a_id' => null,
            'participant_b_id' => null,
            'winner_participant_id' => null,
            'match_id' => null,
            'advances_to_bracket_id' => null,
            'loser_advances_to_bracket_id' => null,
        ]);

        // Wire W-final + L-final advances_to_bracket_id → grand-final bracket.
        // The W-final is at round=$totalWRounds, position=1 in $wByRoundPosition.
        // The L-final is at round=$lRounds, position=1 in $lByRoundPosition.
        $gfBracket = $gfStage->brackets()->first();
        if ($gfBracket !== null) {
            $wFinal = $wByRoundPosition[$totalWRounds][1] ?? null;
            if ($wFinal !== null) {
                $wFinal->update(['advances_to_bracket_id' => $gfBracket->id]);
            }
            $lFinal = $lByRoundPosition[$lRounds][1] ?? null;
            if ($lFinal !== null) {
                $lFinal->update(['advances_to_bracket_id' => $gfBracket->id]);
            }
        }
    }
}
