<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatchType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Source: .planning/phases/03-games-match-types/03-03-PLAN.md task 3.
| Analog: apps/web/tests/Feature/Models/ClanModelTest.php
| Replaces the Wave 0 RED stub from plan 03-01 (Wave 0 marker removed).
*/

it('enforces composite UNIQUE (game_id, key) at the DB layer', function (): void {
    $game = Game::factory()->create();
    GameMatchType::factory()->for($game)->create(['key' => 'scrim_50v50']);

    expect(fn () => GameMatchType::factory()->for($game)->create(['key' => 'scrim_50v50']))
        ->toThrow(QueryException::class);
});

it('allows the same match-type key across different games', function (): void {
    $gameA = Game::factory()->create();
    $gameB = Game::factory()->create();

    GameMatchType::factory()->for($gameA)->create(['key' => 'tournament']);
    GameMatchType::factory()->for($gameB)->create(['key' => 'tournament']);

    expect(GameMatchType::where('key', 'tournament')->count())->toBe(2);
});

it('round-trips name through HasTranslations', function (): void {
    $matchType = GameMatchType::factory()->create(['name' => ['en' => 'Original']]);

    $matchType->setTranslation('name', 'en', 'Scrim 50v50');
    $matchType->save();

    expect($matchType->fresh()->getTranslation('name', 'en'))->toBe('Scrim 50v50');
});

it('round-trips description through HasTranslations independently of name', function (): void {
    $matchType = GameMatchType::factory()->create([
        'name' => ['en' => 'Tournament'],
        'description' => ['en' => 'Original desc'],
    ]);

    $matchType->setTranslation('description', 'en', 'A best-of-three tournament.');
    $matchType->save();

    $reloaded = $matchType->fresh();
    expect($reloaded->getTranslation('description', 'en'))->toBe('A best-of-three tournament.');
    expect($reloaded->getTranslation('name', 'en'))->toBe('Tournament');
});

it('accepts a NULL description on insert (column is nullable in migration)', function (): void {
    // HasTranslations writes `{"en": null}` when the model's `description` is set to null and
    // saved through the Eloquent mutator. To prove the DB column itself accepts SQL NULL
    // (the migration's `->nullable()` contract), we insert via the underlying query builder
    // which bypasses the mutator.
    $game = Game::factory()->create();
    $id = (string) Str::uuid();

    DB::table('game_match_types')->insert([
        'id' => $id,
        'game_id' => $game->id,
        'key' => 'no_desc_type',
        'name' => json_encode(['en' => 'No Description']),
        'description' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $raw = GameMatchType::find($id)?->getRawOriginal('description');
    expect($raw)->toBeNull();
});

it('cascades delete when the parent Game is deleted', function (): void {
    $game = Game::factory()->create();
    GameMatchType::factory()->for($game)->create();
    GameMatchType::factory()->for($game)->create();

    $gameId = $game->id;
    expect(GameMatchType::where('game_id', $gameId)->count())->toBe(2);

    $game->delete();

    expect(GameMatchType::where('game_id', $gameId)->count())->toBe(0);
});

it('belongs to a Game via BelongsTo', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();

    expect($matchType->game)->not->toBeNull();
    expect($matchType->game->id)->toBe($game->id);
});
