<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\DiscordOutboundMessage;
use App\Models\GameMatch;
use App\Models\MatchPlayerStat;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchResultService;
use Database\Seeders\RconWorkerSystemUserSeeder;

/*
| Source: .planning/phases/08-rcon-automation/08-12-PLAN.md task 2 behaviour list.
|
| Covers the MatchResultObserver::maybeAnnounceRconResult branch (added in
| this plan's Task 1). Five cases:
|
|   1. source='rcon' + host_clan_id set + announce channel set
|      → DiscordOutboundMessage row created with message_type='match_result_announce'
|        and payload.embeds[0].fields names score/winner/MVPs.
|   2. source='manual' (admin entry) → NO match_result_announce row
|      (manual entries do not auto-announce; admin chooses when to announce).
|   3. source='rcon' + host_clan_id=null → NO row (can't resolve channel).
|   4. Re-running upsertFromRcon (RCON twice or admin tweak after RCON) → exactly
|      ONE outbound row exists (idempotency guard inside the observer).
|   5. Top-3 MVP ranking by (kills - deaths) DESC — fixture with 4 players,
|      assert order in payload.mvps.
|
| The observer's `alreadyAnnounced` check uses `whereJsonContains payload->match_id`
| because discord_outbound_messages has no match_id column (the Phase 5 outbox is
| match-agnostic — Phase 8 plan 08-12 added match_result_announce as a new
| message_type but did NOT add a match_id FK).
*/

beforeEach(function (): void {
    $this->seed(RconWorkerSystemUserSeeder::class);
});

/**
 * Provision a {User, Player} pair pinned to `$steamId` (for MatchPlayerStat
 * FK linkage in MVP-ranking cases).
 */
function rbraSeedPlayer(string $steamId): Player
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
// Case 1: source='rcon' + host_clan_id set + channel → outbox row landed.
// ---------------------------------------------------------------------------

it('enqueues a match_result_announce outbox row when RCON result lands on a host clan with an announce channel', function (): void {
    $hostClan = Clan::factory()->create([
        'discord_announce_channel_id' => '111000000000000001',
    ]);
    $match = GameMatch::factory()->create([
        'host_clan_id' => $hostClan->id,
        'status' => 'open',
    ]);

    app(MatchResultService::class)->upsertFromRcon($match, [
        'allies_score' => 3,
        'axis_score' => 1,
        'winner_clan_id' => null,
        'recorded_at' => now(),
    ]);

    $rows = DiscordOutboundMessage::query()
        ->where('message_type', 'match_result_announce')
        ->whereJsonContains('payload->match_id', $match->id)
        ->get();

    expect($rows)->toHaveCount(1);

    /** @var DiscordOutboundMessage $row */
    $row = $rows->first();
    expect($row->status)->toBe('pending');
    expect($row->channel_id)->toBe($hostClan->discord_announce_channel_id);

    /** @var array<string, mixed> $payload */
    $payload = $row->payload;
    expect($payload['kind'])->toBe('match_result_announce');
    expect($payload['allies_score'])->toBe(3);
    expect($payload['axis_score'])->toBe(1);

    /** @var array<int, array<string, mixed>> $embeds */
    $embeds = $payload['embeds'];
    expect($embeds)->toHaveCount(1);

    $fields = $embeds[0]['fields'];
    expect($fields)->toBeArray();
    /** @var array<int, array<string, mixed>> $fields */
    $fieldNames = array_map(fn (array $f): string => (string) $f['name'], $fields);
    expect($fieldNames)->toContain(__('rcon.embed.match_result.score'));
    expect($fieldNames)->toContain(__('rcon.embed.match_result.winner'));
    expect($fieldNames)->toContain(__('rcon.embed.match_result.mvps'));
});

// ---------------------------------------------------------------------------
// Case 2: source='manual' (admin path) → NO match_result_announce row.
// ---------------------------------------------------------------------------

it('does NOT enqueue a match_result_announce row when admin enters a manual result', function (): void {
    $hostClan = Clan::factory()->create([
        'discord_announce_channel_id' => '111000000000000002',
    ]);
    $match = GameMatch::factory()->create([
        'host_clan_id' => $hostClan->id,
        'status' => 'open',
    ]);
    $causer = User::factory()->create();

    // Phase 4 upsert path — source defaults to 'manual' (per migration 08-02 DEFAULT).
    app(MatchResultService::class)->upsert($match, [
        'allies_score' => 2,
        'axis_score' => 1,
        'winner_clan_id' => null,
        'recorded_at' => now(),
    ], $causer);

    $count = DiscordOutboundMessage::query()
        ->where('message_type', 'match_result_announce')
        ->count();
    expect($count)->toBe(0);
});

// ---------------------------------------------------------------------------
// Case 3: source='rcon' but host_clan_id=null → NO outbox row.
// ---------------------------------------------------------------------------

it('does NOT enqueue when source=rcon but host_clan_id is null', function (): void {
    $match = GameMatch::factory()->create([
        'host_clan_id' => null,
        'status' => 'open',
    ]);

    app(MatchResultService::class)->upsertFromRcon($match, [
        'allies_score' => 1,
        'axis_score' => 0,
        'recorded_at' => now(),
    ]);

    expect(MatchResult::where('match_id', $match->id)->first()?->source)->toBe('rcon');

    $count = DiscordOutboundMessage::query()
        ->where('message_type', 'match_result_announce')
        ->whereJsonContains('payload->match_id', $match->id)
        ->count();
    expect($count)->toBe(0);
});

// ---------------------------------------------------------------------------
// Case 4: Idempotency — upsertFromRcon twice yields exactly ONE outbound row.
// ---------------------------------------------------------------------------

it('enqueues exactly ONE match_result_announce row even when upsertFromRcon runs twice', function (): void {
    $hostClan = Clan::factory()->create([
        'discord_announce_channel_id' => '111000000000000004',
    ]);
    $match = GameMatch::factory()->create([
        'host_clan_id' => $hostClan->id,
        'status' => 'open',
    ]);

    $service = app(MatchResultService::class);
    $service->upsertFromRcon($match, [
        'allies_score' => 3,
        'axis_score' => 1,
        'recorded_at' => now(),
    ]);
    // Second call — could be a worker retry OR admin tweaking scores via the
    // RCON path (rare; admin usually goes through ::upsert which flips
    // source='manual' and triggers the manual-override lock).
    $service->upsertFromRcon($match, [
        'allies_score' => 4,
        'axis_score' => 2,
        'recorded_at' => now(),
    ]);

    $rows = DiscordOutboundMessage::query()
        ->where('message_type', 'match_result_announce')
        ->whereJsonContains('payload->match_id', $match->id)
        ->get();

    expect($rows)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Case 5: MVP ranking ordered by (kills - deaths) DESC.
// ---------------------------------------------------------------------------

it('orders MVPs by (kills - deaths) DESC and truncates to top 3', function (): void {
    $hostClan = Clan::factory()->create([
        'discord_announce_channel_id' => '111000000000000005',
    ]);
    $match = GameMatch::factory()->create([
        'host_clan_id' => $hostClan->id,
        'status' => 'open',
    ]);

    // Four players with distinct kill/death spreads.
    //   alice: K=5 D=1 → +4   (rank 1)
    //   bob:   K=3 D=0 → +3   (rank 2)
    //   carl:  K=4 D=2 → +2   (rank 3)
    //   dave:  K=0 D=3 → -3   (rank 4 — excluded from top 3)
    $alice = rbraSeedPlayer('76561198000000511');
    $bob = rbraSeedPlayer('76561198000000522');
    $carl = rbraSeedPlayer('76561198000000533');
    $dave = rbraSeedPlayer('76561198000000544');

    $alice->update(['display_name' => 'Alice']);
    $bob->update(['display_name' => 'Bob']);
    $carl->update(['display_name' => 'Carl']);
    $dave->update(['display_name' => 'Dave']);

    MatchPlayerStat::create(['match_id' => $match->id, 'player_id' => $alice->id, 'kills' => 5, 'deaths' => 1]);
    MatchPlayerStat::create(['match_id' => $match->id, 'player_id' => $bob->id, 'kills' => 3, 'deaths' => 0]);
    MatchPlayerStat::create(['match_id' => $match->id, 'player_id' => $carl->id, 'kills' => 4, 'deaths' => 2]);
    MatchPlayerStat::create(['match_id' => $match->id, 'player_id' => $dave->id, 'kills' => 0, 'deaths' => 3]);

    app(MatchResultService::class)->upsertFromRcon($match, [
        'allies_score' => 3,
        'axis_score' => 1,
        'recorded_at' => now(),
    ]);

    /** @var DiscordOutboundMessage $row */
    $row = DiscordOutboundMessage::query()
        ->where('message_type', 'match_result_announce')
        ->whereJsonContains('payload->match_id', $match->id)
        ->firstOrFail();

    /** @var array<string, mixed> $payload */
    $payload = $row->payload;
    /** @var array<int, array<string, mixed>> $mvps */
    $mvps = $payload['mvps'];

    expect($mvps)->toHaveCount(3);
    expect($mvps[0]['username'])->toBe('Alice');
    expect($mvps[0]['kills'])->toBe(5);
    expect($mvps[0]['deaths'])->toBe(1);
    expect($mvps[1]['username'])->toBe('Bob');
    expect($mvps[2]['username'])->toBe('Carl');

    // Dave is the negative-spread tail — must NOT appear.
    $usernames = array_map(fn (array $m): string => (string) $m['username'], $mvps);
    expect($usernames)->not->toContain('Dave');
});
