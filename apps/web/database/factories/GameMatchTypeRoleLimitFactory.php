<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples §
 * Factory: GameMatchTypeRoleLimit.
 *
 * IMPORTANT — DEFAULT IS A CROSS-GAME PAIR (WILL TRIGGER saving() GUARD).
 *
 * Calling `GameMatchTypeRoleLimit::factory()->create()` with no overrides generates
 * a fresh Game for the MatchType AND a separate fresh Game for the Role. The cross-game
 * `saving()` listener on the model will then throw DomainException on persist.
 *
 * To create a VALID (same-game) RoleLimit in a test, construct the parents explicitly:
 *
 *   $game = Game::factory()->create();
 *   $matchType = GameMatchType::factory()->for($game)->create();
 *   $role = GameRole::factory()->for($game)->create();
 *   GameMatchTypeRoleLimit::factory()->create([
 *       'game_match_type_id' => $matchType->id,
 *       'game_role_id'       => $role->id,
 *   ]);
 *
 * This factory's bare default is intentionally cross-game: it is the negative-path fixture
 * used by `GameMatchTypeRoleLimitModelTest` to assert the saving() guard fires (Pitfall 10).
 *
 * @extends Factory<GameMatchTypeRoleLimit>
 */
class GameMatchTypeRoleLimitFactory extends Factory
{
    protected $model = GameMatchTypeRoleLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_match_type_id' => GameMatchType::factory(),
            'game_role_id' => GameRole::factory(),
            'capacity' => fake()->numberBetween(0, 50),
            'sort_order' => 0,
        ];
    }
}
