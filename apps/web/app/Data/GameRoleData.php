<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\GameRole;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § DTO: GameRoleData.
 *
 * `display_name` is a JSONB locale-keyed array via spatie/laravel-translatable.
 * In TS it surfaces as `Record<string, string> | null`.
 *
 * Use `GameRoleData::fromModel($role)` to build from a GameRole Eloquent model —
 * `$role->display_name` returns the active-locale scalar; the DTO carries the
 * full JSONB array so the frontend can switch locale without a server round-trip
 * (RESEARCH.md Pitfall 4).
 */
#[TypeScript]
final class GameRoleData extends Data
{
    /**
     * @param  array<string, string>|null  $display_name
     */
    public function __construct(
        public string $id,
        public string $game_id,
        public string $key,
        public ?array $display_name,
        public int $sort_order,
        public bool $is_active,
    ) {}

    /**
     * Build a GameRoleData from a GameRole Eloquent model.
     *
     * Uses `getTranslations()` to retrieve the full JSONB locale array
     * rather than the active-locale scalar returned by `$role->display_name`.
     */
    public static function fromModel(GameRole $role): self
    {
        return new self(
            id: $role->id,
            game_id: $role->game_id,
            key: $role->key,
            display_name: $role->getTranslations('display_name') ?: null,
            sort_order: $role->sort_order,
            is_active: $role->is_active,
        );
    }
}
