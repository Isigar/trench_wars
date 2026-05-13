<?php

declare(strict_types=1);

namespace App\Services\Brackets;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-06-PLAN.md <interfaces>.
 *
 * Strategy contract for the 4 LOCKED bracket formats (D-011):
 *   - single_elimination  → SingleEliminationGenerator (plan 06-06; this plan)
 *   - double_elimination  → DoubleEliminationGenerator (plan 06-07; stub in this plan)
 *   - round_robin         → RoundRobinGenerator        (plan 06-07; stub in this plan)
 *   - swiss               → SwissGenerator             (plan 06-07; stub in this plan)
 *
 * Pure-void contract: implementations write tournament_stages + tournament_brackets
 * rows directly to the database. The caller (BracketGeneratorService) wraps the
 * generate() call in DB::transaction so partial writes always roll back atomically
 * (Pitfall 4 row-locked materialisation in the materialiser is a separate concern).
 *
 * The strategy MUST NOT call DB::transaction itself — the front-door service owns
 * the transaction boundary so multi-stage generators (double_elim) can write
 * multiple stages atomically.
 */
interface BracketGeneratorStrategy
{
    /**
     * Generate the stage(s) + bracket tree for $tournament from the seeded
     * participants. Pre-conditions:
     *   - $tournament->status === 'seeded' (caller enforces; service does not)
     *   - $orderedParticipants is non-empty and ordered by seed asc (1..N)
     *
     * Post-conditions:
     *   - tournament_stages rows exist for this tournament (>= 1)
     *   - tournament_brackets rows exist for round 1 with participant_a_id +
     *     participant_b_id assigned (or participant_b_id = null for byes)
     *   - advances_to_bracket_id is set on every non-final-round bracket
     *
     * @param  Collection<int, TournamentParticipant>  $orderedParticipants
     */
    public function generate(Tournament $tournament, Collection $orderedParticipants): void;
}
