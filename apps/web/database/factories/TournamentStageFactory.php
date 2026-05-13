<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md <interfaces> TournamentStageFactory.
 *
 * Replaces the Wave 0 stub. Default scope spawns a fresh Tournament + sets
 * type='elim' / ordinal=1 (the canonical first stage for a single-elim tournament).
 * For multi-stage trees, increment ordinal per call.
 *
 * @extends Factory<TournamentStage>
 */
class TournamentStageFactory extends Factory
{
    protected $model = TournamentStage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'type' => 'elim',
            'ordinal' => 1,
            'name' => null,
            'settings' => null,
        ];
    }
}
