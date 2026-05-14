<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
| Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 2.
| Asserts MatchPlayerStat model + DB-tier invariants:
|   1. (match_id, player_id) UNIQUE rejects a duplicate insert;
|      updateOrCreate() on the pair is idempotent.
|   2. CHECK rejects negative kills (and by symmetry deaths/team_kills/score).
|   3. kdr() accessor handles deaths=0 without ZeroDivisionError.
*/

it('rejects a duplicate (match_id, player_id) at the DB tier and is idempotent under updateOrCreate', function (): void {
    $match = GameMatch::factory()->create();
    $player = Player::factory()->create();

    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create();

    // Wrap the duplicate INSERT in a nested DB::transaction so the Postgres
    // failed-transaction abort stays inside the savepoint — the outer
    // RefreshDatabase transaction (and subsequent queries in this test) stay
    // healthy. Mirrors the Phase 4 / 8 pattern for UNIQUE-violation probes.
    $threw = false;
    try {
        DB::transaction(function () use ($match, $player): void {
            MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create();
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('mps_match_player_unique');
    }
    expect($threw)->toBeTrue();

    // updateOrCreate on the same composite key is harmless and updates the row.
    $updated = MatchPlayerStat::query()->updateOrCreate(
        ['match_id' => $match->id, 'player_id' => $player->id],
        ['kills' => 99, 'deaths' => 3, 'team_kills' => 0, 'score' => 9900],
    );

    expect($updated->kills)->toBe(99);
    expect(MatchPlayerStat::query()->where('match_id', $match->id)->where('player_id', $player->id)->count())->toBe(1);
});

it('rejects negative kills via DB CHECK constraint', function (): void {
    $match = GameMatch::factory()->create();
    $player = Player::factory()->create();

    $threw = false;
    try {
        DB::table('match_player_stats')->insert([
            'id' => Str::uuid()->toString(),
            'match_id' => $match->id,
            'player_id' => $player->id,
            'kills' => -1,
            'deaths' => 0,
            'team_kills' => 0,
            'score' => 0,
            'role_played' => null,
            'weapons_used' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('match_player_stats_nonneg_check');
    }
    expect($threw)->toBeTrue();
});

it('kdr() accessor returns the rounded ratio when deaths > 0 and falls back to kills when deaths = 0', function (): void {
    $match = GameMatch::factory()->create();
    $playerOne = Player::factory()->create();
    $playerTwo = Player::factory()->create();

    $statKD = MatchPlayerStat::factory()->forMatch($match)->forPlayer($playerOne)->create([
        'kills' => 20,
        'deaths' => 10,
        'team_kills' => 0,
        'score' => 2000,
    ]);

    expect($statKD->kdr())->toBe(2.0);

    $statNoDeaths = MatchPlayerStat::factory()->forMatch($match)->forPlayer($playerTwo)->create([
        'kills' => 5,
        'deaths' => 0,
        'team_kills' => 0,
        'score' => 500,
    ]);

    // deaths=0 → accessor falls back to kills (typed as int per spec).
    expect($statNoDeaths->kdr())->toBe(5);
});
