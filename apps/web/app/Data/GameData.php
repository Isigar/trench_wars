<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameRole;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § DTO: GameData.
 *
 * `name` is a JSONB locale-keyed array via spatie/laravel-translatable.
 * In TS it surfaces as `Record<string, string> | null`.
 *
 * `roles` and `match_types` are denormalised lists of nested DTOs populated when their
 * relations are eager-loaded — otherwise the arrays are empty (eager-load aware; avoids
 * N+1 traps).
 *
 * Use `GameData::fromModel($game)` to construct from a Game Eloquent model — `$game->name`
 * returns the active-locale scalar; the DTO carries the full JSONB array via
 * `getTranslations()` (RESEARCH.md Pitfall 4).
 */
#[TypeScript]
final class GameData extends Data
{
    /**
     * @param  array<string, string>|null  $name
     * @param  list<GameRoleData>  $roles
     * @param  list<GameMatchTypeData>  $match_types
     */
    public function __construct(
        public string $id,
        public string $key,
        public ?array $name,
        public bool $is_active,
        public array $roles,
        public array $match_types,
    ) {}

    /**
     * Build a GameData from a Game Eloquent model.
     *
     * Requires `roles` and/or `matchTypes` to be eager-loaded for the nested DTO lists
     * to be populated — unloaded relations surface as empty arrays.
     */
    public static function fromModel(Game $game): self
    {
        /** @var list<GameRoleData> $roles */
        $roles = $game->relationLoaded('roles')
            ? $game->roles->map(fn (GameRole $r) => GameRoleData::fromModel($r))->all()
            : [];

        /** @var list<GameMatchTypeData> $matchTypes */
        $matchTypes = $game->relationLoaded('matchTypes')
            ? $game->matchTypes->map(fn (GameMatchType $m) => GameMatchTypeData::fromModel($m))->all()
            : [];

        return new self(
            id: $game->id,
            key: $game->key,
            name: $game->getTranslations('name') ?: null,
            is_active: $game->is_active,
            roles: $roles,
            match_types: $matchTypes,
        );
    }
}
