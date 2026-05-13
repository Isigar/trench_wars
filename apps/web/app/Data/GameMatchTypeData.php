<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § DTO: GameMatchTypeData.
 *
 * Both `name` and `description` are JSONB locale-keyed arrays via spatie/laravel-translatable.
 * In TS each surfaces as `Record<string, string> | null`.
 *
 * `role_limits` is a denormalised list of GameMatchTypeRoleLimitData populated when the
 * `roleLimits` relation is eager-loaded — otherwise the array is empty (eager-load aware;
 * avoids N+1 traps).
 *
 * Use `GameMatchTypeData::fromModel($matchType)` to construct from a GameMatchType Eloquent
 * model — `$matchType->name` returns the active-locale scalar; the DTO carries the full
 * JSONB array via `getTranslations()` (RESEARCH.md Pitfall 4).
 */
#[TypeScript]
final class GameMatchTypeData extends Data
{
    /**
     * @param  array<string, string>|null  $name
     * @param  array<string, string>|null  $description
     * @param  list<GameMatchTypeRoleLimitData>  $role_limits
     */
    public function __construct(
        public string $id,
        public string $game_id,
        public string $key,
        public ?array $name,
        public ?array $description,
        public bool $is_active,
        public array $role_limits,
    ) {}

    /**
     * Build a GameMatchTypeData from a GameMatchType Eloquent model.
     *
     * Requires `roleLimits` to be eager-loaded for the nested DTO list to be populated.
     */
    public static function fromModel(GameMatchType $matchType): self
    {
        /** @var list<GameMatchTypeRoleLimitData> $roleLimits */
        $roleLimits = $matchType->relationLoaded('roleLimits')
            ? $matchType->roleLimits->map(fn (GameMatchTypeRoleLimit $l) => GameMatchTypeRoleLimitData::fromModel($l))->all()
            : [];

        return new self(
            id: $matchType->id,
            game_id: $matchType->game_id,
            key: $matchType->key,
            name: $matchType->getTranslations('name') ?: null,
            description: $matchType->getTranslations('description') ?: null,
            is_active: $matchType->is_active,
            role_limits: $roleLimits,
        );
    }
}
