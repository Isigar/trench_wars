<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\GameMatchTypeRoleLimit;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples §
 * DTO: GameMatchTypeRoleLimitData.
 *
 * Pivot-shape DTO carrying only the capacity matrix entry — no translatable fields,
 * no nested relations. The (game_match_type_id, game_role_id) pair is unique per
 * MatchType at the DB layer; the cross-game invariant (matchType.game_id === role.game_id)
 * is enforced at the model layer via the saving() listener (Pitfall 10).
 */
#[TypeScript]
final class GameMatchTypeRoleLimitData extends Data
{
    public function __construct(
        public string $id,
        public string $game_match_type_id,
        public string $game_role_id,
        public int $capacity,
        public int $sort_order,
    ) {}

    /**
     * Build a GameMatchTypeRoleLimitData from a GameMatchTypeRoleLimit Eloquent model.
     */
    public static function fromModel(GameMatchTypeRoleLimit $limit): self
    {
        return new self(
            id: $limit->id,
            game_match_type_id: $limit->game_match_type_id,
            game_role_id: $limit->game_role_id,
            capacity: $limit->capacity,
            sort_order: $limit->sort_order,
        );
    }
}
