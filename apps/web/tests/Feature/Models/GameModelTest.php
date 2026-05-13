<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameRole;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/03-games-match-types/03-03-PLAN.md task 3.
| Analog: apps/web/tests/Feature/Models/ClanModelTest.php
| Replaces the Wave 0 RED stub from plan 03-01 (Wave 0 marker removed).
*/

it('creates a Game with a slug-safe key, JSONB name, and is_active=true', function (): void {
    $game = Game::factory()->create();

    expect($game->key)->toMatch('/^[a-z0-9_]+$/');
    expect($game->getTranslation('name', 'en'))->toBeString();
    expect($game->is_active)->toBeTrue();
});

it('round-trips name through HasTranslations', function (): void {
    $game = Game::factory()->create(['name' => ['en' => 'Original']]);

    $game->setTranslation('name', 'en', 'Hell Let Loose');
    $game->save();

    $reloaded = $game->fresh();
    expect($reloaded->getTranslation('name', 'en'))->toBe('Hell Let Loose');
    expect($reloaded->getTranslations('name')['en'])->toBe('Hell Let Loose');
});

it('enforces UNIQUE key at the DB layer', function (): void {
    Game::factory()->create(['key' => 'hll']);

    expect(fn () => Game::factory()->create(['key' => 'hll']))
        ->toThrow(QueryException::class);
});

it('enforces key-format CHECK constraint (key must match ^[a-z0-9_]+$)', function (): void {
    expect(fn () => Game::factory()->create(['key' => 'INVALID UPPER']))
        ->toThrow(QueryException::class);
});

it('exposes roles() HasMany ordered by sort_order', function (): void {
    $game = Game::factory()->create();
    GameRole::factory()->for($game)->create(['key' => 'rifleman', 'sort_order' => 2]);
    GameRole::factory()->for($game)->create(['key' => 'medic', 'sort_order' => 1]);

    $keys = $game->roles()->pluck('key')->all();
    expect($keys)->toBe(['medic', 'rifleman']);
});

it('exposes matchTypes() HasMany', function (): void {
    $game = Game::factory()->create();
    GameMatchType::factory()->for($game)->create();
    GameMatchType::factory()->for($game)->create();

    expect($game->matchTypes()->count())->toBe(2);
});

it('logs activity on create (D-012)', function (): void {
    $game = Game::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', Game::class)
        ->where('subject_id', $game->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
