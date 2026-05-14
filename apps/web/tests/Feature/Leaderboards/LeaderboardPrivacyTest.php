<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-06-PLAN.md task 2.
|
| GREEN replacement for the Wave 0 stub (plan 09-01). Asserts SC-2 + D-018:
|   - /leaderboards is a public surface (200 for guests).
|   - LeaderboardEntryData::fromQueryResult enforces PlayerPrivacyGate
|     show_stats → is_anonymous=true rows render anonymous name + no player_id.
|   - clan-tier privacy: same-clan viewer sees the row; cross-clan viewer
|     gets the anonymised row.
|   - window + game query params propagate to the service.
|   - throttle:public-api middleware is attached to /leaderboards.
|
| Mitigation alignment:
|   T-09-06-01 (privacy bypass)  — covered by Test 2 + Test 3.
|   T-09-06-05 (public-api DoS)  — covered by Test 8.
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // Service caches under tags(['leaderboards']) — flush between tests so the
    // array driver memory doesn't bleed across cases.
    Cache::tags(['leaderboards'])->flush();
});

/**
 * Helper — build a Player with a PlayerPrivacy row at the given tier + flags,
 * and a single stat row in the past 7d so the player ranks on the 7d window.
 *
 * @param  array<string, mixed>  $privacyState
 */
function makeLeaderboardPlayer(array $privacyState = ['show_stats' => true], int $kills = 30): Player
{
    $user = User::factory()->create();
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state($privacyState), 'privacy')
        ->create();

    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)
        ->create(['kills' => $kills, 'deaths' => 5]);

    return $player;
}

// ─── Test 1 — show_stats=true renders the player normally ──────────────────

it('renders player name when show_stats=true (public tier)', function (): void {
    $player = makeLeaderboardPlayer([
        'show_to' => 'public',
        'show_stats' => true,
    ]);

    $this->get('/leaderboards')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Leaderboards/Index', false)
                ->has('players', 1)
                ->where('players.0.is_anonymous', false)
                ->where('players.0.player_id', $player->id)
        );
});

// ─── Test 2 — show_stats=false renders anonymously (D-018 critical) ────────

it('renders anonymously when show_stats=false (D-018, T-09-06-01)', function (): void {
    makeLeaderboardPlayer([
        'show_to' => 'public',
        'show_stats' => false,
    ]);

    $this->get('/leaderboards')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.is_anonymous', true)
                ->where('players.0.player_id', '')
                ->where('players.0.player_name', __('leaderboards.anonymous_player'))
                ->where('players.0.clan_name', null)
        );
});

// ─── Test 3 — clan-tier privacy: same-clan visible, cross-clan anonymous ──

it('respects viewer tier for show_to=clan players (same clan visible, cross-clan anonymous)', function (): void {
    // Target player at clan tier, in clanA.
    $targetUser = User::factory()->create();
    $targetPlayer = Player::factory()->for($targetUser)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan', 'show_stats' => true]), 'privacy')
        ->create();

    $clanA = Clan::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $targetUser->id,
        'clan_id' => $clanA->id,
        'left_at' => null,
    ]);

    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($targetPlayer)->create(['kills' => 30, 'deaths' => 5]);

    // Viewer in same clan (clanA).
    $sameClanUser = User::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $sameClanUser->id,
        'clan_id' => $clanA->id,
        'left_at' => null,
    ]);

    // Viewer in DIFFERENT clan (clanB).
    $clanB = Clan::factory()->create();
    $crossClanUser = User::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $crossClanUser->id,
        'clan_id' => $clanB->id,
        'left_at' => null,
    ]);

    // Same-clan viewer sees identity.
    Cache::tags(['leaderboards'])->flush();
    $this->actingAs($sameClanUser)
        ->get('/leaderboards')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.is_anonymous', false)
                ->where('players.0.player_id', $targetPlayer->id)
        );

    // Cross-clan viewer sees anonymous.
    Cache::tags(['leaderboards'])->flush();
    $this->actingAs($crossClanUser)
        ->get('/leaderboards')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.is_anonymous', true)
                ->where('players.0.player_id', '')
        );
});

// ─── Test 4 — guest visitor only sees public+show_stats=true rows ─────────

it('renders public+show_stats=true rows for an unauthenticated visitor; non-public tier is anonymised', function (): void {
    // Visible (public + show_stats=true).
    $visiblePlayer = makeLeaderboardPlayer([
        'show_to' => 'public',
        'show_stats' => true,
    ], kills: 100);

    // Anonymised (clan tier without a viewer means tier check fails AND
    // show_stats check also fails for the guest — DTO sets is_anonymous=true).
    makeLeaderboardPlayer([
        'show_to' => 'clan',
        'show_stats' => true,
    ], kills: 50);

    $response = $this->get('/leaderboards')->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->has('players', 2)
            ->where('players.0.is_anonymous', false)
            ->where('players.0.player_id', $visiblePlayer->id)
            ->where('players.1.is_anonymous', true)
            ->where('players.1.player_id', '')
    );
});

// ─── Test 5 — public route returns 200 for guests ─────────────────────────

it('returns 200 for an unauthenticated visitor (route is public)', function (): void {
    $this->get('/leaderboards')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->component('Leaderboards/Index', false)
        );
});

// ─── Test 6 — window=30d filter propagates ────────────────────────────────

it('applies the window filter from the query string', function (): void {
    $recent = makeLeaderboardPlayer(['show_to' => 'public', 'show_stats' => true], kills: 30);

    // A player whose only stats are 20 days old — visible on 30d, hidden on 7d.
    $oldUser = User::factory()->create();
    $oldPlayer = Player::factory()->for($oldUser)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public', 'show_stats' => true]), 'privacy')
        ->create();
    $oldMatch = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(20)]);
    MatchPlayerStat::factory()->forMatch($oldMatch)->forPlayer($oldPlayer)->create(['kills' => 99, 'deaths' => 1]);

    // 7d window — only the recent player.
    Cache::tags(['leaderboards'])->flush();
    $this->get('/leaderboards?window=7d')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.player_id', $recent->id)
                ->where('filters.window', '7d')
        );

    // 30d window — both visible, old player ranks first by kills.
    Cache::tags(['leaderboards'])->flush();
    $this->get('/leaderboards?window=30d')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 2)
                ->where('players.0.player_id', $oldPlayer->id)
                ->where('filters.window', '30d')
        );
});

// ─── Test 7 — game filter propagates ──────────────────────────────────────

it('applies the game filter from the query string', function (): void {
    $gameA = Game::factory()->create();
    $gameB = Game::factory()->create();
    $typeA = GameMatchType::factory()->create(['game_id' => $gameA->id]);
    $typeB = GameMatchType::factory()->create(['game_id' => $gameB->id]);

    $matchA = GameMatch::factory()->for($typeA, 'gameMatchType')
        ->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    $matchB = GameMatch::factory()->for($typeB, 'gameMatchType')
        ->create(['scheduled_at' => Carbon::now()->subDays(1)]);

    $playerA = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public', 'show_stats' => true]), 'privacy')
        ->create();
    $playerB = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public', 'show_stats' => true]), 'privacy')
        ->create();
    MatchPlayerStat::factory()->forMatch($matchA)->forPlayer($playerA)->create(['kills' => 30, 'deaths' => 1]);
    MatchPlayerStat::factory()->forMatch($matchB)->forPlayer($playerB)->create(['kills' => 80, 'deaths' => 1]);

    Cache::tags(['leaderboards'])->flush();
    $this->get('/leaderboards?game=' . $gameA->id)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.player_id', $playerA->id)
                ->where('filters.game', $gameA->id)
        );
});

// ─── Test 8 — throttle:public-api is attached to the route ────────────────

it('attaches throttle:public-api middleware to /leaderboards (T-09-06-05)', function (): void {
    $route = Route::getRoutes()->getByName('leaderboards.index');

    expect($route)->not->toBeNull();
    expect($route?->gatherMiddleware())->toContain('throttle:public-api');
});

// ─── Test 9 — top-clans pane also renders (smoke test) ────────────────────

it('renders the top-clans pane alongside top-players', function (): void {
    $targetUser = User::factory()->create();
    $player = Player::factory()->for($targetUser)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public', 'show_stats' => true]), 'privacy')
        ->create();
    $clan = Clan::factory()->create();
    ClanMembership::factory()->create(['user_id' => $targetUser->id, 'clan_id' => $clan->id, 'left_at' => null]);

    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 40, 'deaths' => 2]);

    $this->get('/leaderboards')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->has('clans', 1)
                ->where('clans.0.clan_id', $clan->id)
        );
});

// ─── Test 10 — invalid window query rejected ──────────────────────────────

it('rejects an invalid window value via request validation', function (): void {
    $this->get('/leaderboards?window=1y')
        ->assertSessionHasErrors('window');
});
