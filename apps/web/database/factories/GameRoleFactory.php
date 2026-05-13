<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Factory: GameRole.
 *
 * Default scope: a fresh Game is auto-created per row. To attach roles to an existing game,
 * call `GameRole::factory()->for($game)->create([...])`.
 *
 * @extends Factory<GameRole>
 */
class GameRoleFactory extends Factory
{
    protected $model = GameRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'key' => fake()->unique()->regexify('[a-z0-9_]{4,12}'),
            'display_name' => ['en' => fake()->words(2, true)],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
