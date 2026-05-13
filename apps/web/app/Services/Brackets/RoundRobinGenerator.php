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
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md Pattern 4
 * (circle method) + 06-07-PLAN.md <interfaces>.
 *
 * Ships in plan 06-07 (Wave 4); replaces the LogicException stub from plan 06-06.
 *
 * Algorithm (canonical circle method):
 *
 *   1. If N is odd, append a ghost (null) participant → N+1 (even).
 *   2. Fix participants[0]; the remaining N (or N-1 if originally even) rotate.
 *   3. Round r in [0, totalRounds - 1]:
 *      a. First pair: (fix, rotating[r mod (count-1)])
 *      b. Pairs i in [1, matchesPerRound - 1]:
 *           (rotating[(r + i) mod (count-1)], rotating[(r - i + (count-1)) mod (count-1)])
 *      c. SKIP bracket row creation when either side is the ghost.
 *
 * Every C(N, 2) pair plays exactly once. Ghost-vs-real pairings are NOT
 * materialised as rows — the materialiser (plan 06-06 BracketMatchMaterialiserService)
 * also defends by skipping brackets where participant_b is NULL, so a stray
 * insertion would not propagate, but defence-in-depth here keeps the row count
 * truthful to the played match count.
 *
 * Storage:
 *   - 1 stage (type='group', ordinal=1) carrying ALL rounds.
 *   - tournament_brackets rows with round_number = r+1, position = 1..matchesPerRound.
 *   - advances_to_bracket_id is NULL for every bracket (round-robin has no
 *     advancement chain).
 *
 * Threat refs:
 *   - T-06-07-03 (ghost-vs-real bracket row leaks) — mitigated by skipping
 *     creation when either side is the ghost.
 *
 * No DB::transaction here — caller (BracketGeneratorService) owns the boundary.
 */
final class RoundRobinGenerator implements BracketGeneratorStrategy
{
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

        // Build a 0-indexed array; append a ghost (null) when N is odd.
        /** @var array<int, TournamentParticipant|null> $participants */
        $participants = $orderedParticipants->values()->all();
        if ($n % 2 === 1) {
            $participants[] = null;
        }
        $count = count($participants);
        $rounds = $count - 1;
        $matchesPerRound = (int) ($count / 2);

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'group',
            'ordinal' => 1,
            'name' => null,
            'settings' => null,
        ]);

        $fix = $participants[0];
        /** @var array<int, TournamentParticipant|null> $rotating */
        $rotating = array_slice($participants, 1);
        $rotatingCount = count($rotating);

        for ($r = 0; $r < $rounds; $r++) {
            $position = 1;

            // First pair: (fix, rotating[r mod (count-1)]).
            $pA = $fix;
            $pB = $rotating[$r % $rotatingCount];
            if ($pA !== null && $pB !== null) {
                TournamentBracket::create([
                    'tournament_stage_id' => $stage->id,
                    'round_number' => $r + 1,
                    'position' => $position++,
                    'participant_a_id' => $pA->id,
                    'participant_b_id' => $pB->id,
                    'winner_participant_id' => null,
                    'match_id' => null,
                    'advances_to_bracket_id' => null,
                    'loser_advances_to_bracket_id' => null,
                ]);
            }

            // Remaining pairs.
            for ($i = 1; $i < $matchesPerRound; $i++) {
                $pA = $rotating[($r + $i) % $rotatingCount];
                $pB = $rotating[($r - $i + $rotatingCount) % $rotatingCount];
                if ($pA !== null && $pB !== null) {
                    TournamentBracket::create([
                        'tournament_stage_id' => $stage->id,
                        'round_number' => $r + 1,
                        'position' => $position++,
                        'participant_a_id' => $pA->id,
                        'participant_b_id' => $pB->id,
                        'winner_participant_id' => null,
                        'match_id' => null,
                        'advances_to_bracket_id' => null,
                        'loser_advances_to_bracket_id' => null,
                    ]);
                }
            }
        }
    }
}
