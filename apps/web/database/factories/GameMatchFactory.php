<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/04-matches-manual/04-03-PLAN.md <interfaces> MatchFactory block.
 *
 * Replaces the Wave 0 stub (commit 6e5024c). The original Wave 0 stub used the string FQN
 * `'App\\Models\\Match'` and threw RuntimeException from definition(). Now resolvable as
 * GameMatch::class — the per-line phpstan-ignore annotations from the stub are removed
 * and the canonical generic `@extends Factory<GameMatch>` is restored.
 *
 * Factory class name is `GameMatchFactory` (file renamed from `MatchFactory.php` to
 * `GameMatchFactory.php` in plan 04-03 — see GameMatch model docblock for naming-decision
 * rationale).
 *
 * Default scope: a fresh GameMatchType + a fresh User (organiser) per row. host_clan is
 * null by default (matches the migration's nullable FK). To attach to specific parents:
 *
 *   GameMatch::factory()->for($matchType)->for($organiser, 'organiser')->create()
 *
 * @extends Factory<GameMatch>
 */
class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_match_type_id' => GameMatchType::factory(),
            'title' => ['en' => fake()->sentence(3)],
            'description' => null,
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'organiser_user_id' => User::factory(),
            'host_clan_id' => null,
            'server_address' => null,
            'status' => 'open',
            'is_public' => true,
        ];
    }
}
