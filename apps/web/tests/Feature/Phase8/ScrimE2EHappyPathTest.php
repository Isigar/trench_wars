<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\DiscordOutboundMessage;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\MatchPlayerStat;
use App\Models\MatchResult;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchSignupService;
use App\Services\MatchSlotMaterialiserService;
use Database\Factories\MatchEventFactory;
use Database\Seeders\RconWorkerSystemUserSeeder;
use Illuminate\Support\Facades\Redis;
use Tests\Support\SignsRconRequests;

/*
| Source: .planning/phases/08-rcon-automation/08-12-PLAN.md task 1 +
|         <interfaces> SC-5 ScrimE2EHappyPathTest block.
|
| **SC-5 CAPSTONE — REQ-success-end-to-end-scrim.** Replaces the Wave 0 RED stub
| (plan 08-01). One Pest test drives the full round-1 happy path end-to-end
| inside the apps/web tier:
|
|   1. Seed: two clans (Wolves, Hawks), two Discord-OAuth users + Player rows
|      per clan with steam_id_64 populated (Pitfall 5 — match_player_stats keys
|      on Player.id which is FK-resolved from steam_id_64 by the aggregator).
|   2. Schedule a scrim match (HLL 1v1 friendly-style) — game_match_type with
|      4 slots in one role, host_clan_id = clanA, status='open'.
|      MatchSlotMaterialiserService materialises 4 empty slots.
|   3. Sign up all 4 players via MatchSignupService::signup (THE production
|      write path to match_slots.occupant_user_id — D-010).
|   4. Register a MatchServer + create a MatchServerBooking covering the
|      reserved_from / reserved_to window.
|   5. Drive the worker's wire contract — POST canonical CRCON events via the
|      HMAC-signed /api/internal/match/{match}/events endpoint (sync queue
|      driver runs CloseMatchJob inline because phpunit.xml pins QUEUE_CONNECTION=sync).
|   6. Assert the final state:
|      - match.status === 'played'
|      - match.manual_entry_required === false
|      - MatchResult row with source='rcon', allies/axis scores from match_end
|      - MatchPlayerStat row per player with correct kill/death counts
|      - DiscordOutboundMessage row with message_type='match_result_announce'
|        (payload.match_id == match.id)
|
| **HMAC secret pinning** — same idiom as InternalApiRoutesPresentTest: pin
| `config('rcon.hmac_secret')` in beforeEach so SignsRconRequests::rconServerVars
| signs over a known secret regardless of host env.
|
| **Sync queue driver** — phpunit.xml pins QUEUE_CONNECTION='sync' so the
| CloseMatchJob dispatched by MatchEventIngestService when it sees a `match_end`
| event runs INLINE in the same request lifecycle. No Bus::dispatchSync needed.
|
| **Threat coverage:**
|   - REQ-success-end-to-end-scrim — mechanically proven inside one test.
|   - T-08-12-01 — assert match_result_announce payload contains no Steam IDs.
|   - T-08-12-02 — first save() lands ONE outbound row (idempotency guard
|     covered explicitly in RconBotResultAnnounceTest case 4).
*/

uses(SignsRconRequests::class);

const SC5_HMAC_SECRET = 'sc-5-capstone-hmac-secret-plan-08-12';

beforeEach(function (): void {
    config(['rcon.hmac_secret' => SC5_HMAC_SECRET]);
    Redis::flushdb();
    $this->seed(RconWorkerSystemUserSeeder::class);
});

/**
 * Provision a {User, Player} pair pinned to `$steamId` + linked to `$clan`.
 *
 * @return array{0: Player, 1: User}
 */
function sc5SeedRosterMember(string $steamId, Clan $clan): array
{
    /** @var User $user */
    $user = User::factory()->create();

    /** @var Player $player */
    $player = Player::factory()->create([
        'user_id' => $user->id,
        'steam_id_64' => $steamId,
    ]);

    // Roster attachment via active ClanMembership keeps the tag-access path
    // permissive (MatchAccessRule allowlist is empty in this scrim, so empty
    // memberships would already pass — but seeding the membership matches the
    // production happy-path shape end-to-end).
    $clan->memberships()->create([
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now()->subDay(),
        'left_at' => null,
    ]);

    return [$player, $user];
}

it('SC-5: two clans complete the full round-1 happy path end-to-end', function (): void {
    // -------------------------------------------------------------------
    // 1. Seed clans + rosters (2 players each, all with Steam IDs).
    // -------------------------------------------------------------------
    $clanA = Clan::factory()->create([
        'name' => 'Wolves',
        'discord_announce_channel_id' => '987654321000000001',
    ]);
    $clanB = Clan::factory()->create([
        'name' => 'Hawks',
        'discord_announce_channel_id' => '987654321000000002',
    ]);

    // Use realistic 17-digit Steam IDs so the leak-check (substring search on
    // the JSON-encoded payload) is robust against false negatives from short
    // numeric tokens overlapping UUIDs / timestamps / score fields.
    [$alice, $aliceUser] = sc5SeedRosterMember('76561198000000111', $clanA);
    [$bob,   $bobUser] = sc5SeedRosterMember('76561198000000222', $clanA);
    [$carl,  $carlUser] = sc5SeedRosterMember('76561198000000333', $clanB);
    [$dave,  $daveUser] = sc5SeedRosterMember('76561198000000444', $clanB);

    // -------------------------------------------------------------------
    // 2. Game catalogue: HLL preset + scrim match type with 4-slot role.
    // -------------------------------------------------------------------
    $game = Game::factory()->create(['key' => 'hll_sc5']);
    $rifleRole = GameRole::factory()->for($game)->create(['key' => 'rifle']);
    $scrim = GameMatchType::factory()->for($game)->create(['key' => 'scrim_4v4_sc5']);

    GameMatchTypeRoleLimit::create([
        'game_match_type_id' => $scrim->id,
        'game_role_id' => $rifleRole->id,
        'capacity' => 4,
        'sort_order' => 0,
    ]);

    // -------------------------------------------------------------------
    // 3. Create + materialise the scrim match (host = Wolves).
    // -------------------------------------------------------------------
    /** @var GameMatch $match */
    $match = GameMatch::factory()
        ->for($scrim, 'gameMatchType')
        ->create([
            'host_clan_id' => $clanA->id,
            'status' => 'open',
            'scheduled_at' => now()->addHour(),
        ]);

    app(MatchSlotMaterialiserService::class)->materialise($match);

    // -------------------------------------------------------------------
    // 4. Sign up all 4 players via the production write path.
    // -------------------------------------------------------------------
    $signup = app(MatchSignupService::class);
    $signup->signup($match, $aliceUser, $rifleRole);
    $signup->signup($match, $bobUser, $rifleRole);
    $signup->signup($match, $carlUser, $rifleRole);
    $signup->signup($match, $daveUser, $rifleRole);

    // -------------------------------------------------------------------
    // 5. Provision a server + booking covering the match window.
    // -------------------------------------------------------------------
    $server = MatchServer::factory()->create();
    MatchServerBooking::factory()
        ->forMatch($match)
        ->onServer($server)
        ->create([
            'reserved_from' => now()->subMinutes(5),
            'reserved_to' => now()->addMinutes(90),
        ]);

    // -------------------------------------------------------------------
    // 6. Drive the wire contract: POST canonical CRCON events over HMAC.
    //    Sync queue driver runs CloseMatchJob inline (phpunit.xml pinning).
    // -------------------------------------------------------------------
    $events = [
        MatchEventFactory::new()->gameStart()->wireMake(),
        // Alice → Carl (allies → axis).
        MatchEventFactory::new()->kill('76561198000000111', '76561198000000333', 'KARABINER 98K')->wireMake(),
        // Alice → Dave (allies → axis).
        MatchEventFactory::new()->kill('76561198000000111', '76561198000000444', 'KARABINER 98K')->wireMake(),
        // Bob → Carl (allies → axis).
        MatchEventFactory::new()->kill('76561198000000222', '76561198000000333', 'M1 GARAND')->wireMake(),
        // Carl → Bob (axis → allies; single axis kill).
        MatchEventFactory::new()->kill('76561198000000333', '76561198000000222', 'MP 40')->wireMake(),
        // match_end allies 3, axis 1.
        MatchEventFactory::new()->matchEnd('allies', 3, 1)->wireMake(),
    ];

    $response = $this->signedJsonPost(
        "/api/internal/match/{$match->id}/events",
        ['events' => $events],
    );

    expect($response->getStatusCode())->toBe(202);

    // -------------------------------------------------------------------
    // 7. Assert end-state.
    // -------------------------------------------------------------------
    $match->refresh();
    expect($match->status)->toBe('played');
    expect($match->manual_entry_required)->toBeFalse();

    /** @var MatchResult|null $result */
    $result = MatchResult::where('match_id', $match->id)->first();
    expect($result)->not->toBeNull();
    expect($result->source)->toBe('rcon');
    expect($result->allies_score)->toBe(3);
    expect($result->axis_score)->toBe(1);

    // Per-player stats — 4 distinct players represented.
    expect(MatchPlayerStat::where('match_id', $match->id)->count())->toBe(4);

    /** @var MatchPlayerStat $aliceStats */
    $aliceStats = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $alice->id)
        ->first();
    expect($aliceStats->kills)->toBe(2);
    expect($aliceStats->deaths)->toBe(0);

    /** @var MatchPlayerStat $bobStats */
    $bobStats = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $bob->id)
        ->first();
    expect($bobStats->kills)->toBe(1);
    expect($bobStats->deaths)->toBe(1);

    /** @var MatchPlayerStat $carlStats */
    $carlStats = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $carl->id)
        ->first();
    expect($carlStats->kills)->toBe(1);
    expect($carlStats->deaths)->toBe(2);

    /** @var MatchPlayerStat $daveStats */
    $daveStats = MatchPlayerStat::where('match_id', $match->id)
        ->where('player_id', $dave->id)
        ->first();
    expect($daveStats->kills)->toBe(0);
    expect($daveStats->deaths)->toBe(1);

    // -------------------------------------------------------------------
    // 8. Discord match_result_announce outbound row landed exactly once.
    // -------------------------------------------------------------------
    $announceRows = DiscordOutboundMessage::query()
        ->where('message_type', 'match_result_announce')
        ->whereJsonContains('payload->match_id', $match->id)
        ->get();

    expect($announceRows)->toHaveCount(1);

    /** @var DiscordOutboundMessage $announce */
    $announce = $announceRows->first();
    expect($announce->status)->toBe('pending');
    expect($announce->channel_id)->toBe($clanA->discord_announce_channel_id);

    /** @var array<string, mixed> $payload */
    $payload = $announce->payload;
    expect($payload['kind'])->toBe('match_result_announce');
    expect($payload['match_id'])->toBe($match->id);
    expect($payload['allies_score'])->toBe(3);
    expect($payload['axis_score'])->toBe(1);
    expect($payload['mvps'])->toBeArray();
    // Top-3 MVPs ordered by (kills - deaths) DESC.
    // Alice: 2-0 = +2 ; Bob: 1-1 = 0 ; Carl: 1-2 = -1 ; Dave: 0-1 = -1.
    expect($payload['mvps'])->toHaveCount(3);

    // T-08-12-01 — payload MUST NOT leak Steam IDs.
    $payloadJson = (string) json_encode($payload);
    expect(str_contains($payloadJson, '76561198000000111'))->toBeFalse();
    expect(str_contains($payloadJson, '76561198000000222'))->toBeFalse();
    expect(str_contains($payloadJson, '76561198000000333'))->toBeFalse();
    expect(str_contains($payloadJson, '76561198000000444'))->toBeFalse();
});
