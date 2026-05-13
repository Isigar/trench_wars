<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/03-games-match-types/03-03-PLAN.md task 3.
| Analog: apps/web/tests/Feature/Models/ClanMembershipModelTest.php
| Replaces the Wave 0 RED stub from plan 03-01 (Wave 0 marker removed).
|
| This file pulls double duty:
|   1. Standard model assertions (composite UNIQUE, capacity CHECK, cascade chain, BelongsTo).
|   2. THE SECURITY-CRITICAL TEST: Pitfall 10 cross-game RoleLimit invariant enforced by the
|      model `saving()` listener (the only programmatic guard — Postgres cannot CHECK across
|      tables cheaply per Assumption A6).
*/

/**
 * @return array{game: Game, matchType: GameMatchType, role: GameRole}
 */
function sameGameTriple(): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();

    return ['game' => $game, 'matchType' => $matchType, 'role' => $role];
}

it('creates a valid same-game RoleLimit (saving guard does not false-positive)', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 6,
    ]);

    expect($limit->exists)->toBeTrue();
    expect($limit->capacity)->toBe(6);
    expect(GameMatchTypeRoleLimit::where('id', $limit->id)->exists())->toBeTrue();
});

it('enforces composite UNIQUE (game_match_type_id, game_role_id) at the DB layer', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 1,
    ]);

    expect(fn () => GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 2,
    ]))->toThrow(QueryException::class);
});

it('enforces capacity >= 0 CHECK constraint at the DB layer', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    expect(fn () => GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => -1,
    ]))->toThrow(QueryException::class);
});

/*
| Pitfall 10 — the security-critical assertion of this entire wave.
|
| Postgres cannot cheaply express a cross-table CHECK constraint:
| `CHECK ((SELECT game_id FROM game_match_types WHERE id = game_match_type_id) =
|          (SELECT game_id FROM game_roles      WHERE id = game_role_id))`
| would require a PL/pgSQL trigger function (Assumption A6 ruled out).
|
| The cross-game invariant therefore lives ONLY at the model layer's `saving()` listener.
| If this test fails, the system silently accepts cross-game RoleLimit pairs — a data-
| integrity break that propagates downstream into the signup engine (Phase 4).
*/
it('throws DomainException when matchType.game_id != role.game_id (Pitfall 10 guard)', function (): void {
    $gameA = Game::factory()->create();
    $gameB = Game::factory()->create();

    $matchTypeOfA = GameMatchType::factory()->for($gameA)->create();
    $roleOfB = GameRole::factory()->for($gameB)->create();

    expect(fn () => GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchTypeOfA->id,
        'game_role_id' => $roleOfB->id,
        'capacity' => 1,
    ]))->toThrow(DomainException::class);
});

it('cascades delete via parent MatchType (FK cascadeOnDelete)', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 4,
    ]);

    $limitId = $limit->id;
    expect(GameMatchTypeRoleLimit::where('id', $limitId)->exists())->toBeTrue();

    $matchType->delete();

    expect(GameMatchTypeRoleLimit::where('id', $limitId)->exists())->toBeFalse();
});

it('cascades delete via parent Role (FK cascadeOnDelete)', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 4,
    ]);

    $limitId = $limit->id;

    $role->delete();

    expect(GameMatchTypeRoleLimit::where('id', $limitId)->exists())->toBeFalse();
});

it('cascades delete via parent Game (chain: Game -> MatchType+Role -> RoleLimit)', function (): void {
    ['game' => $game, 'matchType' => $matchType, 'role' => $role] = sameGameTriple();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 4,
    ]);

    $limitId = $limit->id;

    $game->delete();

    expect(GameMatchTypeRoleLimit::where('id', $limitId)->exists())->toBeFalse();
    expect(GameMatchType::where('id', $matchType->id)->exists())->toBeFalse();
    expect(GameRole::where('id', $role->id)->exists())->toBeFalse();
});

it('exposes matchType() + role() BelongsTo relations', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 5,
    ]);

    expect($limit->matchType)->not->toBeNull();
    expect($limit->matchType->id)->toBe($matchType->id);
    expect($limit->role)->not->toBeNull();
    expect($limit->role->id)->toBe($role->id);
});

it('logs activity on create (D-012)', function (): void {
    ['matchType' => $matchType, 'role' => $role] = sameGameTriple();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 3,
    ]);

    $activity = Activity::query()
        ->where('subject_type', GameMatchTypeRoleLimit::class)
        ->where('subject_id', $limit->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
