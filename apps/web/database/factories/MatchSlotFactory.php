<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/04-matches-manual/04-03-PLAN.md <interfaces> MatchSlotFactory block.
 *
 * Replaces the Wave 0 stub (commit 6e5024c).
 *
 * IMPORTANT — DEFAULT IS A CROSS-GAME PAIR.
 *
 * Calling `MatchSlot::factory()->create()` with no overrides spawns a fresh GameMatch
 * (which spawns its own GameMatchType, which spawns its own Game) AND a separate fresh
 * GameRole (which spawns its own Game). The match.game and role.game will differ.
 *
 * The DB schema does NOT enforce a cross-game CHECK between GameMatch.game (via match-type)
 * and slot.role.game — that invariant is application-level (see Phase 3 RoleLimit saving()
 * guard and the materialiser in plan 04-05). For tests that need a same-game pair, build
 * the parents explicitly:
 *
 *   $game = Game::factory()->create();
 *   $matchType = GameMatchType::factory()->for($game)->create();
 *   $match = GameMatch::factory()->for($matchType)->create();
 *   $role = GameRole::factory()->for($game)->create();
 *   MatchSlot::factory()->create([
 *       'match_id' => $match->id,
 *       'game_role_id' => $role->id,
 *   ]);
 *
 * @extends Factory<MatchSlot>
 */
class MatchSlotFactory extends Factory
{
    protected $model = MatchSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'game_role_id' => GameRole::factory(),
            'slot_index' => fake()->numberBetween(0, 49),
            'occupant_user_id' => null,
            'confirmed_at' => null,
            'sort_order' => 0,
        ];
    }
}
