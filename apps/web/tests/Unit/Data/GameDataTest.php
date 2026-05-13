<?php

declare(strict_types=1);

/*
| Wave 2 implementation — replaces Wave 0 RED stub from plan 03-01.
| Covers REQ-platform-vision: GameData and friends expose the full JSONB locale
| array via getTranslations() instead of the active-locale scalar (Pitfall 4),
| and nested DTO hydration is eager-load aware (Phase 2 ClanData pattern).
| See .planning/phases/03-games-match-types/03-VALIDATION.md row "03-04 T2".
| Wave 0 marker removed (T-03-01-01 phase-close grep audit clears).
*/

use App\Data\GameData;
use App\Data\GameMatchTypeRoleLimitData;
use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --------------------------------------------------------------------------
// Pitfall 4: fromModel surfaces the full JSONB locale array, NOT the
// active-locale scalar. This is the security-critical assertion.
// --------------------------------------------------------------------------

it('fromModel returns the full JSONB locale array on the name field', function (): void {
    $game = Game::factory()->create();
    $game->setTranslation('name', 'en', 'HLL');
    $game->setTranslation('name', 'fr', 'HLL FR');
    $game->save();

    $dto = GameData::fromModel($game->fresh());

    expect($dto->name)->toBe(['en' => 'HLL', 'fr' => 'HLL FR']);
});

// --------------------------------------------------------------------------
// Eager-load awareness: relations that are NOT loaded produce empty arrays
// rather than triggering N+1 lazy-load queries.
// --------------------------------------------------------------------------

it('roles array is empty when relation is not loaded', function (): void {
    $game = Game::factory()->create();
    GameRole::factory()->for($game)->create();

    // Re-fetch without eager-loading roles
    $fresh = Game::query()->find($game->id);
    expect($fresh)->not->toBeNull();

    $dto = GameData::fromModel($fresh);

    expect($dto->roles)->toBe([]);
    expect($fresh->relationLoaded('roles'))->toBeFalse();
});

it('roles array is populated when relation is eager-loaded', function (): void {
    $game = Game::factory()->create();
    $role = GameRole::factory()->for($game)->create([
        'key' => 'commander',
        'display_name' => ['en' => 'Commander', 'fr' => 'Commandant'],
    ]);

    $fresh = Game::query()->with('roles')->find($game->id);
    expect($fresh)->not->toBeNull();

    $dto = GameData::fromModel($fresh);

    expect($dto->roles)->toHaveCount(1);
    expect($dto->roles[0]->id)->toBe($role->id);
    // Pitfall 4: nested DTO also surfaces the full JSONB array, not the scalar.
    expect($dto->roles[0]->display_name)->toBe(['en' => 'Commander', 'fr' => 'Commandant']);
});

// --------------------------------------------------------------------------
// Nested-relation hydration: GameMatchType.role_limits populates when
// the full chain `matchTypes.roleLimits` is eager-loaded.
// --------------------------------------------------------------------------

it('match_types hydrates nested role_limits when both relations are loaded', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 7,
    ]);

    $fresh = Game::query()->with('matchTypes.roleLimits')->find($game->id);
    expect($fresh)->not->toBeNull();

    $dto = GameData::fromModel($fresh);

    expect($dto->match_types)->toHaveCount(1);
    expect($dto->match_types[0]->role_limits)->toHaveCount(1);
    expect($dto->match_types[0]->role_limits[0]->capacity)->toBe(7);
    expect($dto->match_types[0]->role_limits[0]->game_role_id)->toBe($role->id);
});

// --------------------------------------------------------------------------
// Pivot-shape contract: RoleLimit DTO carries ONLY id, FKs, capacity,
// sort_order — no translatable fields (name/description are nowhere on it).
// --------------------------------------------------------------------------

it('GameMatchTypeRoleLimitData carries only the pivot fields (no translatable arrays)', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 12,
        'sort_order' => 3,
    ]);

    $arr = GameMatchTypeRoleLimitData::fromModel($limit)->toArray();

    expect($arr)->toHaveKeys(['id', 'game_match_type_id', 'game_role_id', 'capacity', 'sort_order']);
    expect($arr)->not->toHaveKey('name');
    expect($arr)->not->toHaveKey('description');
    expect($arr['capacity'])->toBe(12);
    expect($arr['sort_order'])->toBe(3);
});
