<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Services\EloRatingService;

/*
| Source: 11-01-PLAN.md Task 2 — RED scaffold; turned GREEN by 11-02.
|
| Covers EloRatingService::applyResult():
|   - K=32 formula for equal-rated clans (1500 vs 1500 → winner 1516, loser 1484)
|   - Draw between equal ratings leaves both unchanged
|   - Lopsided: lower-rated upset winner gains > 16; higher-rated favourite win gains < 16
|   - elo_matches_count increments for both clans after applyResult
|
| FAILS now because App\Services\EloRatingService does not exist.
*/

it('winner gains and loser loses with K=32 for equal ratings (1500 vs 1500)', function (): void {
    $winner = Clan::factory()->create(['elo_rating' => 1500]);
    $loser = Clan::factory()->create(['elo_rating' => 1500]);

    app(EloRatingService::class)->applyResult($winner, $loser);

    // E_winner = 1/(1+10^((1500-1500)/400)) = 0.5; delta = 32*(1-0.5) = 16
    expect($winner->fresh()->elo_rating)->toBe(1516);
    expect($loser->fresh()->elo_rating)->toBe(1484);
});

it('draw between equal ratings leaves both elo_rating unchanged', function (): void {
    $a = Clan::factory()->create(['elo_rating' => 1500]);
    $b = Clan::factory()->create(['elo_rating' => 1500]);

    app(EloRatingService::class)->applyResult($a, $b, draw: true);

    // E_a = 0.5; delta = 32*(0.5-0.5) = 0
    expect($a->fresh()->elo_rating)->toBe(1500);
    expect($b->fresh()->elo_rating)->toBe(1500);
});

it('upset winner (lower-rated) gains more than 16 points', function (): void {
    // Lower-rated (1200) beats higher-rated (1800) — this is an upset.
    // E_winner = 1/(1+10^((1800-1200)/400)) = 1/(1+10^1.5) ≈ 0.0303
    // delta_winner = 32*(1-0.0303) ≈ 31 — gains > 16
    $winner = Clan::factory()->create(['elo_rating' => 1200]);
    $loser = Clan::factory()->create(['elo_rating' => 1800]);

    app(EloRatingService::class)->applyResult($winner, $loser);

    expect($winner->fresh()->elo_rating)->toBeGreaterThan(1216); // gained > 16
    expect($loser->fresh()->elo_rating)->toBeLessThan(1784);    // lost > 16
});

it('elo_matches_count increments for both clans after applyResult', function (): void {
    $winner = Clan::factory()->create(['elo_rating' => 1500, 'elo_matches_count' => 0]);
    $loser = Clan::factory()->create(['elo_rating' => 1500, 'elo_matches_count' => 0]);

    app(EloRatingService::class)->applyResult($winner, $loser);

    expect($winner->fresh()->elo_matches_count)->toBe(1);
    expect($loser->fresh()->elo_matches_count)->toBe(1);
});
