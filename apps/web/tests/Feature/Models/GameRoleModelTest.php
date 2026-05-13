<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameRole;
use Illuminate\Database\QueryException;

/*
| Source: .planning/phases/03-games-match-types/03-03-PLAN.md task 3.
| Analog: apps/web/tests/Feature/Models/ClanModelTest.php + ClanMembershipModelTest.php
| Replaces the Wave 0 RED stub from plan 03-01 (Wave 0 marker removed).
*/

it('enforces composite UNIQUE (game_id, key) at the DB layer', function (): void {
    $game = Game::factory()->create();
    GameRole::factory()->for($game)->create(['key' => 'rifleman']);

    expect(fn () => GameRole::factory()->for($game)->create(['key' => 'rifleman']))
        ->toThrow(QueryException::class);
});

it('allows the same role key across different games', function (): void {
    $gameA = Game::factory()->create();
    $gameB = Game::factory()->create();

    GameRole::factory()->for($gameA)->create(['key' => 'rifleman']);
    GameRole::factory()->for($gameB)->create(['key' => 'rifleman']);

    expect(GameRole::where('key', 'rifleman')->count())->toBe(2);
});

it('round-trips display_name through HasTranslations', function (): void {
    $role = GameRole::factory()->create(['display_name' => ['en' => 'Original']]);

    $role->setTranslation('display_name', 'en', 'Squad Leader');
    $role->save();

    $reloaded = $role->fresh();
    expect($reloaded->getTranslation('display_name', 'en'))->toBe('Squad Leader');
});

it('cascades delete when the parent Game is deleted', function (): void {
    $game = Game::factory()->create();
    GameRole::factory()->for($game)->create();
    GameRole::factory()->for($game)->create();

    $gameId = $game->id;
    expect(GameRole::where('game_id', $gameId)->count())->toBe(2);

    $game->delete();

    expect(GameRole::where('game_id', $gameId)->count())->toBe(0);
});

it('belongs to a Game via BelongsTo', function (): void {
    $game = Game::factory()->create();
    $role = GameRole::factory()->for($game)->create();

    expect($role->game)->not->toBeNull();
    expect($role->game->id)->toBe($game->id);
});
