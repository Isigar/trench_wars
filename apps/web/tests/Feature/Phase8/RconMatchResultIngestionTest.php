<?php

declare(strict_types=1);

use App\Jobs\Rcon\CloseMatchJob;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchPlayerStat;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\Rcon\MatchPlayerStatAggregator;
use Database\Seeders\RconWorkerSystemUserSeeder;

/*
| Source: .planning/phases/08-rcon-automation/08-08-PLAN.md task 2 (5-case behaviour list).
|
| Replaces the Wave-0 RED stub from plan 08-01. Covers
| {@see \App\Jobs\Rcon\CloseMatchJob::handle()} end-to-end against the
| {@see \App\Services\Rcon\MatchPlayerStatAggregator} +
| {@see \App\Services\MatchResultService::upsertFromRcon()} chain.
|
| Cases:
|   1. Happy path — 10 kills + match_end → MatchResult source=rcon, scores from payload,
|      recorded_by=rcon-worker system user, allies/axis scores correct.
|   2. Missing match_end → manual_entry_required=true, NO MatchResult row.
|   3. Zero kills + match_end → manual_entry_required=true BUT MatchResult row still created.
|   4. CloseMatchJob → match.status transitions open → played (MatchStatusService chained).
|   5. MatchPlayerStat rows exist after CloseMatchJob (aggregator chained).
|
| The seeder MUST run before each test so MatchResultService::upsertFromRcon can
| firstOrFail the rcon-worker user by email. Pest's afterEach + RefreshDatabase
| trait does NOT auto-run seeders; we trigger it via beforeEach.
*/

beforeEach(function (): void {
    $this->seed(RconWorkerSystemUserSeeder::class);
});

/**
 * Provision a {User, Player} pair pinned to `$steamId`.
 */
function rmrSeedPlayer(string $steamId): Player
{
    $user = User::factory()->create();

    /** @var Player $player */
    $player = Player::factory()->create([
        'user_id' => $user->id,
        'steam_id_64' => $steamId,
    ]);

    return $player;
}

// ---------------------------------------------------------------------------
// Case 1: Happy path — 10 kills + match_end → MatchResult source=rcon.
// ---------------------------------------------------------------------------

it('writes a MatchResult with source=rcon and rcon-worker causer on match_end happy path', function (): void {
    rmrSeedPlayer('111');
    rmrSeedPlayer('222');
    $match = GameMatch::factory()->create(['status' => 'open']);

    // 10 player_kill events.
    for ($i = 0; $i < 10; $i++) {
        MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    }
    // match_end event with allies_score=4, axis_score=2.
    MatchEvent::factory()->for($match, 'match')->matchEnd('allies', 4, 2)->create();

    // Synchronously execute the job (no Bus::fake — we want the real handle()).
    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    /** @var MatchResult|null $result */
    $result = MatchResult::where('match_id', $match->id)->first();

    expect($result)->not->toBeNull();
    expect($result->source)->toBe('rcon');
    expect($result->allies_score)->toBe(4);
    expect($result->axis_score)->toBe(2);

    $rconUser = User::where('email', 'rcon-worker@system.trenchwars')->first();
    expect($rconUser)->not->toBeNull();
    expect($result->recorded_by_user_id)->toBe($rconUser->id);
});

// ---------------------------------------------------------------------------
// Case 2: Missing match_end → manual_entry_required=true, NO MatchResult.
// ---------------------------------------------------------------------------

it('flips manual_entry_required=true and writes NO MatchResult when match_end is missing', function (): void {
    rmrSeedPlayer('111');
    rmrSeedPlayer('222');
    $match = GameMatch::factory()->create(['status' => 'open']);

    // Kills present but NO match_end event.
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();

    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    expect($match->fresh()->manual_entry_required)->toBeTrue();
    expect(MatchResult::where('match_id', $match->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Case 3: Zero kills + match_end → manual_entry_required=true BUT result written.
// ---------------------------------------------------------------------------

it('flags manual_entry_required AND writes MatchResult when kill count is zero (best-effort)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);

    // ONLY match_end — no kills (CRCON dropped the stream mid-match).
    MatchEvent::factory()->for($match, 'match')->matchEnd('allies', 1, 0)->create();

    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    expect($match->fresh()->manual_entry_required)->toBeTrue();

    /** @var MatchResult|null $result */
    $result = MatchResult::where('match_id', $match->id)->first();
    expect($result)->not->toBeNull();
    expect($result->source)->toBe('rcon');
    expect($result->allies_score)->toBe(1);
    expect($result->axis_score)->toBe(0);
});

// ---------------------------------------------------------------------------
// Case 4: status flips open → played.
// ---------------------------------------------------------------------------

it('transitions match.status from open to played after CloseMatchJob runs', function (): void {
    rmrSeedPlayer('111');
    rmrSeedPlayer('222');
    $match = GameMatch::factory()->create(['status' => 'open']);

    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->matchEnd('allies', 3, 2)->create();

    expect($match->fresh()->status)->toBe('open');

    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    expect($match->fresh()->status)->toBe('played');
});

// ---------------------------------------------------------------------------
// Case 5: MatchPlayerStat rows materialise (aggregator chained).
// ---------------------------------------------------------------------------

it('materialises MatchPlayerStat rows via the chained aggregator', function (): void {
    $alice = rmrSeedPlayer('111');
    $bob = rmrSeedPlayer('222');
    $match = GameMatch::factory()->create(['status' => 'open']);

    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('222', '111')->create();
    MatchEvent::factory()->for($match, 'match')->matchEnd()->create();

    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    expect(MatchPlayerStat::where('match_id', $match->id)->count())->toBe(2);

    /** @var MatchPlayerStat $aliceStat */
    $aliceStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();

    /** @var MatchPlayerStat $bobStat */
    $bobStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $bob->id)
        ->first();

    expect($aliceStat->kills)->toBe(2);
    expect($aliceStat->deaths)->toBe(1);
    expect($bobStat->kills)->toBe(1);
    expect($bobStat->deaths)->toBe(2);
});
