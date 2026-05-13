<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TournamentBracket;
use App\Models\TournamentStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md <interfaces> TournamentBracketFactory.
 *
 * Replaces the Wave 0 stub. Default scope spawns a fresh TournamentStage + sets
 * round_number=1 / position=1 (canonical first bracket in round 1). All
 * participant FKs + match_id + advance pointers default to NULL — un-materialised
 * bracket shape, valid against migration 2026_05_15_100300's nullable columns +
 * partial UNIQUE on match_id (NULLs allowed) + no_self_advance CHECK (NULLs
 * evaluate to NULL not FALSE in Postgres so they pass).
 *
 * For composite-UNIQUE collisions across factory chains in the same stage, tests
 * MUST override (round_number, position) explicitly — the default is the same
 * for every fresh call to ::factory()->create().
 *
 * @extends Factory<TournamentBracket>
 */
class TournamentBracketFactory extends Factory
{
    protected $model = TournamentBracket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_stage_id' => TournamentStage::factory(),
            'round_number' => 1,
            'position' => 1,
            'participant_a_id' => null,
            'participant_b_id' => null,
            'winner_participant_id' => null,
            'match_id' => null,
            'advances_to_bracket_id' => null,
            'loser_advances_to_bracket_id' => null,
        ];
    }
}
