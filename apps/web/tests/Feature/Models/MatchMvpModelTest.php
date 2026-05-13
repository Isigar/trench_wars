<?php

declare(strict_types=1);

use App\Models\MatchMvp;
use App\Models\MatchResult;
use App\Models\Player;
use Illuminate\Database\QueryException;

/*
| Source: .planning/phases/04-matches-manual/04-03-PLAN.md task 3.
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
*/

it('creates a valid mvp via factory', function (): void {
    $mvp = MatchMvp::factory()->create();
    expect($mvp->exists)->toBeTrue();
    expect($mvp->category)->toBe('kills');
});

it('enforces composite UNIQUE (match_result_id, category, player_id)', function (): void {
    $result = MatchResult::factory()->create();
    $player = Player::factory()->create();

    MatchMvp::factory()->create([
        'match_result_id' => $result->id,
        'player_id' => $player->id,
        'category' => 'kills',
    ]);

    expect(fn () => MatchMvp::factory()->create([
        'match_result_id' => $result->id,
        'player_id' => $player->id,
        'category' => 'kills',
    ]))->toThrow(QueryException::class);
});

it('allows the same player across different categories of the same result', function (): void {
    $result = MatchResult::factory()->create();
    $player = Player::factory()->create();

    MatchMvp::factory()->create([
        'match_result_id' => $result->id,
        'player_id' => $player->id,
        'category' => 'kills',
    ]);
    MatchMvp::factory()->create([
        'match_result_id' => $result->id,
        'player_id' => $player->id,
        'category' => 'objective',
    ]);

    expect(MatchMvp::where('match_result_id', $result->id)->count())->toBe(2);
});

it('enforces match_mvps_category_check CHECK constraint', function (): void {
    expect(fn () => MatchMvp::factory()->create(['category' => 'banana']))
        ->toThrow(QueryException::class);
});

it('accepts each valid category enum value', function (): void {
    foreach (['kills', 'defense', 'objective', 'mvp'] as $category) {
        $result = MatchResult::factory()->create();
        $mvp = MatchMvp::factory()->create([
            'match_result_id' => $result->id,
            'category' => $category,
        ]);
        expect($mvp->category)->toBe($category);
    }
});

it('exposes result and player BelongsTo relations', function (): void {
    $result = MatchResult::factory()->create();
    $player = Player::factory()->create();
    $mvp = MatchMvp::factory()->create([
        'match_result_id' => $result->id,
        'player_id' => $player->id,
    ]);

    expect($mvp->result?->id)->toBe($result->id);
    expect($mvp->player?->id)->toBe($player->id);
});

it('cascades on parent match_result delete', function (): void {
    $result = MatchResult::factory()->create();
    $mvp = MatchMvp::factory()->create(['match_result_id' => $result->id]);
    $mvpId = $mvp->id;

    $result->delete();

    expect(MatchMvp::where('id', $mvpId)->exists())->toBeFalse();
});
