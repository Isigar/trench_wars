<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use App\Models\User;
use App\Services\Rcon\MatchPlayerStatAggregator;

/*
| Source: .planning/phases/08-rcon-automation/08-08-PLAN.md task 1 (7-case behaviour list).
|
| Replaces the Wave-0 RED stub from plan 08-01. Covers
| {@see \App\Services\Rcon\MatchPlayerStatAggregator::aggregate()} — the
| per-player roll-up that runs ONCE on match_end (Pitfall 4 mitigation).
|
| Cases:
|   1. Two players, 5 kill events → counters correct (Alice 3/2, Bob 2/3).
|   2. One team kill → killer.team_kills=1.
|   3. Aggregator twice → counts NOT doubled (updateOrCreate idempotency).
|   4. Orphan event (steam_id=999, no Player row) silently skipped.
|   5. weapons_used jsonb captures `{K98:3}` for Alice's 3 K98 kills.
|   6. score derived = kills × 100.
|   7. Empty match (no events) → 0 rows upserted, no exceptions.
|
| Helper {@see seedPlayer} provisions a {User, Player} pair pinned to a
| specific steam_id_64. The aggregator's `firstWhere('steam_id_64', $sid)`
| lookup ties the CRCON event stream to the league's Player rows
| (must_haves.key_links #3).
*/

/**
 * Provision a User + Player pair pinned to `$steamId`. Returns the Player
 * row (FK target for the aggregator's MatchPlayerStat upsert).
 */
function seedPlayer(string $steamId, string $displayName): Player
{
    /** @var User $user */
    $user = User::factory()->create();

    /** @var Player $player */
    $player = Player::factory()->create([
        'user_id' => $user->id,
        'display_name' => $displayName,
        'steam_id_64' => $steamId,
    ]);

    return $player;
}

// ---------------------------------------------------------------------------
// Case 1: Two players, 5 kill events — counters correct.
// ---------------------------------------------------------------------------

it('aggregates two players with 5 kill events into accurate kill/death counts', function (): void {
    $alice = seedPlayer('111', 'Alice');
    $bob = seedPlayer('222', 'Bob');
    $match = GameMatch::factory()->create();

    // Alice -> Bob x 3
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    // Bob -> Alice x 2
    MatchEvent::factory()->for($match, 'match')->kill('222', '111')->create();
    MatchEvent::factory()->for($match, 'match')->kill('222', '111')->create();

    $upserted = app(MatchPlayerStatAggregator::class)->aggregate($match);

    expect($upserted)->toBe(2);

    $aliceStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();
    $bobStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $bob->id)
        ->first();

    expect($aliceStat)->not->toBeNull();
    expect($aliceStat->kills)->toBe(3);
    expect($aliceStat->deaths)->toBe(2);

    expect($bobStat)->not->toBeNull();
    expect($bobStat->kills)->toBe(2);
    expect($bobStat->deaths)->toBe(3);
});

// ---------------------------------------------------------------------------
// Case 2: Team kill bumps killer.team_kills only.
// ---------------------------------------------------------------------------

it('records team kills on the killer row only', function (): void {
    $alice = seedPlayer('111', 'Alice');
    seedPlayer('333', 'Charlie');
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->for($match, 'match')->teamKill('111', '333')->create();

    app(MatchPlayerStatAggregator::class)->aggregate($match);

    $aliceStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();

    expect($aliceStat)->not->toBeNull();
    expect($aliceStat->team_kills)->toBe(1);
    // Team kills do NOT increment deaths on the victim per plan <interfaces>
    // switch arm — only player_kill emits deaths.
    expect($aliceStat->kills)->toBe(0);
    expect($aliceStat->deaths)->toBe(0);
});

// ---------------------------------------------------------------------------
// Case 3: Second run is idempotent — counts NOT doubled.
// ---------------------------------------------------------------------------

it('is idempotent — re-running the aggregator yields identical counts (not doubled)', function (): void {
    $alice = seedPlayer('111', 'Alice');
    seedPlayer('222', 'Bob');
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();

    $aggregator = app(MatchPlayerStatAggregator::class);
    $first = $aggregator->aggregate($match);
    $second = $aggregator->aggregate($match);

    expect($first)->toBe($second)->toBe(2);

    $aliceStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();

    expect($aliceStat->kills)->toBe(2);
    expect(MatchPlayerStat::where('match_id', $match->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Case 4: Orphan event (steam_id with no Player row) silently skipped.
// ---------------------------------------------------------------------------

it('silently skips events whose steam_id_64 has no matching Player row', function (): void {
    $alice = seedPlayer('111', 'Alice');
    $match = GameMatch::factory()->create();

    // Real player kill.
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    // Orphan kill — steam_id=999 has NO Player row.
    MatchEvent::factory()->for($match, 'match')->kill('999', '888')->create();

    $upserted = app(MatchPlayerStatAggregator::class)->aggregate($match);

    // Only Alice (steam 111) was resolved; 222/999/888 have no Player → skipped.
    expect($upserted)->toBe(1);

    expect(MatchPlayerStat::where('match_id', $match->id)->count())->toBe(1);
    expect(MatchPlayerStat::where('match_id', $match->id)->where('player_id', $alice->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Case 5: weapons_used jsonb histogram captures per-weapon counts.
// ---------------------------------------------------------------------------

it('captures weapons_used as a per-weapon histogram in the jsonb column', function (): void {
    $alice = seedPlayer('111', 'Alice');
    seedPlayer('222', 'Bob');
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->for($match, 'match')->kill('111', '222', 'K98')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222', 'K98')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222', 'K98')->create();

    app(MatchPlayerStatAggregator::class)->aggregate($match);

    $aliceStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();

    expect($aliceStat->weapons_used)->toBeArray();
    expect($aliceStat->weapons_used)->toBe(['K98' => 3]);
});

// ---------------------------------------------------------------------------
// Case 6: score derived = kills × 100.
// ---------------------------------------------------------------------------

it('derives score = kills × 100 (CRCON does not emit per-player score)', function (): void {
    $alice = seedPlayer('111', 'Alice');
    seedPlayer('222', 'Bob');
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();
    MatchEvent::factory()->for($match, 'match')->kill('111', '222')->create();

    app(MatchPlayerStatAggregator::class)->aggregate($match);

    $aliceStat = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();

    expect($aliceStat->kills)->toBe(4);
    expect($aliceStat->score)->toBe(400);
});

// ---------------------------------------------------------------------------
// Case 7: Empty match — no events, no rows, no exceptions.
// ---------------------------------------------------------------------------

it('upserts zero rows on an empty match (no events) without throwing', function (): void {
    $match = GameMatch::factory()->create();

    $upserted = app(MatchPlayerStatAggregator::class)->aggregate($match);

    expect($upserted)->toBe(0);
    expect(MatchPlayerStat::where('match_id', $match->id)->count())->toBe(0);
});
