<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 08-01). All counters are non-negative by
 * default (the DB enforces this via `match_player_stats_nonneg_check`).
 * Use `forMatch()` + `forPlayer()` state helpers to pin FKs without spawning
 * default parent factories (plan 08-03 idiom).
 *
 * @extends Factory<MatchPlayerStat>
 */
class MatchPlayerStatFactory extends Factory
{
    protected $model = MatchPlayerStat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kills = fake()->numberBetween(0, 30);

        return [
            'match_id' => GameMatch::factory(),
            'player_id' => Player::factory(),
            'kills' => $kills,
            'deaths' => fake()->numberBetween(0, 15),
            'team_kills' => 0,
            'score' => $kills * 100,
            'role_played' => null,
            'weapons_used' => null,
        ];
    }

    /**
     * Pin the stat row to a specific match (skips the default GameMatch::factory()).
     */
    public function forMatch(GameMatch $match): self
    {
        return $this->state(fn (array $attributes): array => [
            'match_id' => $match->id,
        ]);
    }

    /**
     * Pin the stat row to a specific player (skips the default Player::factory()).
     */
    public function forPlayer(Player $player): self
    {
        return $this->state(fn (array $attributes): array => [
            'player_id' => $player->id,
        ]);
    }
}
