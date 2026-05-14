<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\MatchPlayerStat;
use App\Models\MatchResult;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/*
| Source: .planning/phases/09-polish/09-05-PLAN.md task 2.
|
| GREEN replacement for the Wave 0 stub. Asserts SC-2/SC-4 — every observer
| that can change a leaderboard aggregate flushes the `leaderboards` tag.
|
| Three observer paths covered:
|   1. MatchResultObserver::created — every result invalidates.
|   2. MatchResultObserver::updated — only score-bearing column changes invalidate.
|   3. MatchPlayerStatObserver::saved — every stat write invalidates.
|   4. ClanMembershipObserver::created/updated — membership flip invalidates
|      (D-09-05-D current-snapshot semantics: clan attribution changes when
|      the active membership flips).
|
| Each test pre-populates the cache tag, fires the model event, and asserts
| the cache key is gone.
*/

beforeEach(function (): void {
    Cache::tags(['leaderboards'])->flush();
});

function primeLeaderboardCache(): void
{
    Cache::tags(['leaderboards', 'lb:players:7d'])->put('lb:players:7d:all:25', 'sentinel', 600);
    Cache::tags(['leaderboards', 'lb:clans:7d'])->put('lb:clans:7d:all:25', 'sentinel', 600);
}

it('flushes leaderboards tag when MatchResult is created', function (): void {
    primeLeaderboardCache();
    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBe('sentinel');

    MatchResult::factory()->create();

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
    expect(Cache::tags(['leaderboards', 'lb:clans:7d'])->get('lb:clans:7d:all:25'))->toBeNull();
});

it('flushes leaderboards tag when MatchResult.allies_score is updated', function (): void {
    $result = MatchResult::factory()->create();
    primeLeaderboardCache();
    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBe('sentinel');

    $result->update(['allies_score' => 99]);

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
});

it('flushes leaderboards tag when MatchResult.axis_score is updated', function (): void {
    $result = MatchResult::factory()->create();
    primeLeaderboardCache();

    $result->update(['axis_score' => 99]);

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
});

it('flushes leaderboards tag when MatchResult.winner_clan_id changes', function (): void {
    $result = MatchResult::factory()->create();
    $newWinner = Clan::factory()->create();
    primeLeaderboardCache();

    $result->update(['winner_clan_id' => $newWinner->id]);

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
});

it('does NOT flush leaderboards tag when only MatchResult.notes is updated', function (): void {
    $result = MatchResult::factory()->create();
    primeLeaderboardCache();

    $result->update(['notes' => 'unrelated annotation']);

    // Cache should be intact — notes is not a score-bearing column.
    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBe('sentinel');
    expect(Cache::tags(['leaderboards', 'lb:clans:7d'])->get('lb:clans:7d:all:25'))->toBe('sentinel');
});

it('flushes leaderboards tag when MatchPlayerStat is created', function (): void {
    primeLeaderboardCache();

    MatchPlayerStat::factory()->create(['kills' => 5, 'deaths' => 2]);

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
});

it('flushes leaderboards tag when MatchPlayerStat is updated', function (): void {
    $stat = MatchPlayerStat::factory()->create(['kills' => 5, 'deaths' => 2]);
    primeLeaderboardCache();

    $stat->update(['kills' => 50]);

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
});

it('flushes leaderboards tag when ClanMembership is created (active)', function (): void {
    primeLeaderboardCache();
    $clan = Clan::factory()->create();
    $user = User::factory()->create();

    ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'left_at' => null,
    ]);

    expect(Cache::tags(['leaderboards', 'lb:clans:7d'])->get('lb:clans:7d:all:25'))->toBeNull();
});

it('flushes leaderboards tag when ClanMembership.left_at flips (member leaves)', function (): void {
    $clan = Clan::factory()->create();
    $user = User::factory()->create();
    $membership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $user->id,
        'joined_at' => now()->subYear(),
        'left_at' => null,
    ]);
    primeLeaderboardCache();

    $membership->update(['left_at' => now()]);

    expect(Cache::tags(['leaderboards', 'lb:clans:7d'])->get('lb:clans:7d:all:25'))->toBeNull();
});
