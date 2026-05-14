<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use App\Services\LeaderboardService;
use Illuminate\Support\Carbon;

/*
| Source: .planning/phases/09-polish/09-05-PLAN.md task 1.
|
| GREEN replacement for the Wave 0 stub (plan 09-01).
| Asserts SC-2 (leaderboard players) — top-N by SUM(kills) within window.
|
| Plan-vs-reality drift resolved in 09-05 (D-09-05-C):
|   - matches has no game_id column — filter via game_match_types.game_id.
|   - Service filters by scheduled_at >= now()-INTERVAL window via Carbon.
*/

it('returns top players sorted by total kills descending', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    $high = Player::factory()->create();
    $mid = Player::factory()->create();
    $low = Player::factory()->create();

    MatchPlayerStat::factory()->forMatch($match)->forPlayer($high)->create(['kills' => 30, 'deaths' => 5]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($mid)->create(['kills' => 20, 'deaths' => 10]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($low)->create(['kills' => 5, 'deaths' => 7]);

    $rows = app(LeaderboardService::class)->topPlayers('7d');

    expect($rows)->toHaveCount(3);
    expect((string) $rows[0]->player_id)->toBe($high->id);
    expect((int) $rows[0]->kills)->toBe(30);
    expect((string) $rows[1]->player_id)->toBe($mid->id);
    expect((string) $rows[2]->player_id)->toBe($low->id);
});

it('filters to scheduled_at within 7-day window', function (): void {
    $inWindow = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(3)]);
    $outsideWindow = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(30)]);

    $inPlayer = Player::factory()->create();
    $outPlayer = Player::factory()->create();

    MatchPlayerStat::factory()->forMatch($inWindow)->forPlayer($inPlayer)->create(['kills' => 10, 'deaths' => 2]);
    MatchPlayerStat::factory()->forMatch($outsideWindow)->forPlayer($outPlayer)->create(['kills' => 100, 'deaths' => 2]);

    $rows = app(LeaderboardService::class)->topPlayers('7d');

    expect($rows)->toHaveCount(1);
    expect((string) $rows[0]->player_id)->toBe($inPlayer->id);
});

it('returns rows across all windows including all-time', function (): void {
    $oldMatch = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(365)]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($oldMatch)->forPlayer($player)->create(['kills' => 42, 'deaths' => 9]);

    $rows = app(LeaderboardService::class)->topPlayers('all');

    expect($rows)->toHaveCount(1);
    expect((int) $rows[0]->kills)->toBe(42);
});

it('filters by game_id when supplied (routes via game_match_types.game_id per D-09-05-C)', function (): void {
    $gameA = Game::factory()->create();
    $gameB = Game::factory()->create();
    $typeA = GameMatchType::factory()->create(['game_id' => $gameA->id]);
    $typeB = GameMatchType::factory()->create(['game_id' => $gameB->id]);

    $matchA = GameMatch::factory()->for($typeA, 'gameMatchType')->create([
        'scheduled_at' => Carbon::now()->subDays(1),
    ]);
    $matchB = GameMatch::factory()->for($typeB, 'gameMatchType')->create([
        'scheduled_at' => Carbon::now()->subDays(1),
    ]);

    $pA = Player::factory()->create();
    $pB = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($matchA)->forPlayer($pA)->create(['kills' => 50, 'deaths' => 1]);
    MatchPlayerStat::factory()->forMatch($matchB)->forPlayer($pB)->create(['kills' => 80, 'deaths' => 1]);

    $rows = app(LeaderboardService::class)->topPlayers('7d', $gameA->id);

    expect($rows)->toHaveCount(1);
    expect((string) $rows[0]->player_id)->toBe($pA->id);
});

it('returns at most limit rows', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    for ($i = 0; $i < 5; $i++) {
        $p = Player::factory()->create();
        MatchPlayerStat::factory()->forMatch($match)->forPlayer($p)->create(['kills' => 10 + $i, 'deaths' => 1]);
    }

    $rows = app(LeaderboardService::class)->topPlayers('7d', null, 3);

    expect($rows)->toHaveCount(3);
});

it('returns NULL kdr when deaths sum to zero (NULLIF guard)', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 20, 'deaths' => 0]);

    $rows = app(LeaderboardService::class)->topPlayers('7d');

    expect($rows)->toHaveCount(1);
    expect($rows[0]->kdr)->toBeNull();
});

it('throws InvalidArgumentException on unknown window', function (): void {
    app(LeaderboardService::class)->topPlayers('1y');
})->throws(InvalidArgumentException::class);
