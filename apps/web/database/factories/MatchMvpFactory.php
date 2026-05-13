<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MatchMvp;
use App\Models\MatchResult;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/04-matches-manual/04-03-PLAN.md <interfaces> MatchMvpFactory block.
 *
 * Replaces the Wave 0 stub (commit 6e5024c). Default scope: a fresh MatchResult + a fresh
 * Player per row; default category 'kills' with a random value in [1, 100].
 *
 * @extends Factory<MatchMvp>
 */
class MatchMvpFactory extends Factory
{
    protected $model = MatchMvp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_result_id' => MatchResult::factory(),
            'player_id' => Player::factory(),
            'category' => 'kills',
            'value' => fake()->numberBetween(1, 100),
        ];
    }
}
