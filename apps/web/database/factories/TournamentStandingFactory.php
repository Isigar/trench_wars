<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md <interfaces> TournamentStandingFactory.
 *
 * Replaces the Wave 0 stub. Default scope spawns fresh Tournament + Stage +
 * Participant — three independent factory trees by default, so callers MUST
 * use ->for($tournament)->for($stage, 'stage')->for($participant, 'participant')
 * (or override the *_id columns) when a single tree is required.
 *
 * Wins/losses/draws default to 0; points + tiebreak_score default to 0.00
 * (decimal:2 cast); rank defaults to NULL (computed by
 * StandingsCalculatorService — plan 06-09).
 *
 * @extends Factory<TournamentStanding>
 */
class TournamentStandingFactory extends Factory
{
    protected $model = TournamentStanding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'tournament_stage_id' => TournamentStage::factory(),
            'participant_id' => TournamentParticipant::factory(),
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'points' => 0,
            'tiebreak_score' => 0,
            'rank' => null,
        ];
    }
}
