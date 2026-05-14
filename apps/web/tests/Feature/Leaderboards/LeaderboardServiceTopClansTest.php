<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\GameMatch;
use App\Models\MatchPlayerStat;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Support\Carbon;

/*
| Source: .planning/phases/09-polish/09-05-PLAN.md task 1.
|
| GREEN replacement for the Wave 0 stub (plan 09-01). Asserts SC-2 (clan
| leaderboard) — aggregate kills + wins per clan via clan_memberships JOIN.
|
| Plan-vs-reality drift (D-09-05-C):
|   - clan_memberships keys on user_id (not player_id) — join routes through
|     players.user_id → clan_memberships.user_id.
|   - clan_memberships activity is filtered by left_at IS NULL (no boolean
|     `active` column).
|
| Snapshot semantics (D-09-05-D): clan attribution follows CURRENT active
| membership at query time, not membership at time-of-match. v1 schema does
| not snapshot membership; documented in service docblock.
*/

function makePlayerInClan(Clan $clan): Player
{
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create();
    ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $user->id,
        'joined_at' => Carbon::now()->subYear(),
        'left_at' => null,
    ]);

    return $player;
}

it('aggregates kills per clan via active clan_memberships join (D-09-05-C/D)', function (): void {
    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();

    $pA1 = makePlayerInClan($clanA);
    $pA2 = makePlayerInClan($clanA);
    $pB1 = makePlayerInClan($clanB);

    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(2)]);

    MatchPlayerStat::factory()->forMatch($match)->forPlayer($pA1)->create(['kills' => 30, 'deaths' => 5]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($pA2)->create(['kills' => 20, 'deaths' => 5]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($pB1)->create(['kills' => 25, 'deaths' => 5]);

    $rows = app(LeaderboardService::class)->topClans('7d');

    expect($rows)->toHaveCount(2);

    $byClan = collect($rows)->keyBy('clan_id');
    expect((int) $byClan[$clanA->id]->kills)->toBe(50);
    expect((int) $byClan[$clanB->id]->kills)->toBe(25);
});

it('counts wins via match_results.winner_clan_id', function (): void {
    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();
    $pA = makePlayerInClan($clanA);
    $pB = makePlayerInClan($clanB);

    $match1 = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(2)]);
    $match2 = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(3)]);

    MatchPlayerStat::factory()->forMatch($match1)->forPlayer($pA)->create(['kills' => 10, 'deaths' => 1]);
    MatchPlayerStat::factory()->forMatch($match1)->forPlayer($pB)->create(['kills' => 8, 'deaths' => 1]);
    MatchPlayerStat::factory()->forMatch($match2)->forPlayer($pA)->create(['kills' => 15, 'deaths' => 1]);
    MatchPlayerStat::factory()->forMatch($match2)->forPlayer($pB)->create(['kills' => 12, 'deaths' => 1]);

    MatchResult::factory()->create([
        'match_id' => $match1->id,
        'winner_clan_id' => $clanA->id,
        'allies_score' => 5,
        'axis_score' => 0,
    ]);
    MatchResult::factory()->create([
        'match_id' => $match2->id,
        'winner_clan_id' => $clanA->id,
        'allies_score' => 5,
        'axis_score' => 1,
    ]);

    $rows = app(LeaderboardService::class)->topClans('7d');

    $byClan = collect($rows)->keyBy('clan_id');
    expect((int) $byClan[$clanA->id]->wins)->toBe(2);
    expect((int) $byClan[$clanB->id]->wins)->toBe(0);
});

it('filters by window correctly', function (): void {
    $clan = Clan::factory()->create();
    $p = makePlayerInClan($clan);

    $inMatch = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(3)]);
    $outMatch = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(40)]);

    MatchPlayerStat::factory()->forMatch($inMatch)->forPlayer($p)->create(['kills' => 10, 'deaths' => 1]);
    MatchPlayerStat::factory()->forMatch($outMatch)->forPlayer($p)->create(['kills' => 99, 'deaths' => 1]);

    $sevenDay = app(LeaderboardService::class)->topClans('7d');
    expect((int) $sevenDay[0]->kills)->toBe(10);

    $thirtyDay = app(LeaderboardService::class)->topClans('30d');
    expect((int) $thirtyDay[0]->kills)->toBe(10);

    $allTime = app(LeaderboardService::class)->topClans('all');
    expect((int) $allTime[0]->kills)->toBe(109);
});

it('returns at most limit rows for clans', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    for ($i = 0; $i < 5; $i++) {
        $clan = Clan::factory()->create();
        $p = makePlayerInClan($clan);
        MatchPlayerStat::factory()->forMatch($match)->forPlayer($p)->create(['kills' => 10 + $i, 'deaths' => 1]);
    }

    $rows = app(LeaderboardService::class)->topClans('7d', null, 2);

    expect($rows)->toHaveCount(2);
});
