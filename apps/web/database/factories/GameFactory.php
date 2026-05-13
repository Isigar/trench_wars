<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Factory: Game.
 *
 * `key` is generated via regexify('[a-z0-9_]{4,12}') so it passes the DB-level CHECK
 * `key ~ '^[a-z0-9_]+$'`. Str::slug() would emit hyphens which fail the CHECK.
 *
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->regexify('[a-z0-9_]{4,12}'),
            'name' => ['en' => fake()->words(2, true)],
            'is_active' => true,
        ];
    }
}
