<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameMatchType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Factory: GameMatchType.
 *
 * Default scope: a fresh Game is auto-created per row. To attach match types to an existing
 * game, call `GameMatchType::factory()->for($game)->create([...])`.
 *
 * `description` defaults to null (matches the migration's `nullable()` JSONB column).
 *
 * @extends Factory<GameMatchType>
 */
class GameMatchTypeFactory extends Factory
{
    protected $model = GameMatchType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'key' => fake()->unique()->regexify('[a-z0-9_]{4,12}'),
            'name' => ['en' => fake()->words(2, true)],
            'description' => null,
            'is_active' => true,
        ];
    }
}
