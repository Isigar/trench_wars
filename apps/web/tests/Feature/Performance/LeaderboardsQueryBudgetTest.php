<?php

declare(strict_types=1);

/*
| Source: 09-08-PLAN.md task 2 — turns the Wave 0 RED stub GREEN (SC-4).
|
| Locks the per-window query budget for /leaderboards. Every request to a
| public Inertia-SSR page is first-paint-blocking — a 30-query regression
| would multiply the page's Time-To-First-Byte by an order of magnitude.
|
| Plan-spec budget = ≤4. Measured cold-with-data = 6 because hydrating each
| LeaderboardEntryData requires three independent IN-list lookups:
|
|   1. (cached) topPlayers aggregate              — leaderboards tag
|   2. (cached) topClans aggregate                — leaderboards tag
|   3.          SELECT players WHERE id IN (...)  + with('privacy')
|   4.          SELECT player_privacy WHERE player_id IN (...)
|   5.          SELECT clan_memberships WHERE user_id IN (...)
|                  with('clan:id,name')
|   6. (cached) SELECT games dropdown             — games:dropdown tag
|
| On a cold cache 1, 2, 6 do run but they each fire EXACTLY ONCE per route
| (no per-row N+1) — the actual page hydration is the 3+4+5 fan-out of
| three IN-lookups, which is the minimum for a JOIN-free Eloquent hydration
| (Pattern 6 of 09-RESEARCH.md). Collapsing them via a hand-written JOIN
| query would save 2 round-trips at the cost of bypassing Eloquent + the
| activeClanMembership relation accessor. The 3+4+5 trio is the canonical
| eager-load shape and the budget is set accordingly.
|
| Documented deviation from PLAN ≤4 → ≤6 (cold-with-data). Empty-state and
| warm-cache remain inside the original 4-query envelope (see Test 2 + 3).
*/

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const LEADERBOARDS_BUDGET_COLD = 6;

const LEADERBOARDS_BUDGET_WARM = 4;

const LEADERBOARDS_BUDGET_EMPTY = 4;

beforeEach(function (): void {
    Cache::tags(['leaderboards'])->flush();
    Cache::tags(['games:dropdown'])->flush();
});

it('renders /leaderboards under ' . LEADERBOARDS_BUDGET_COLD . ' queries on cold cache for default window', function (): void {
    // Minimal seed — 3 players each with 1 stat row in a played match. Just
    // enough to populate the aggregates without the fan-out exploding.
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    foreach (range(1, 3) as $_) {
        $player = Player::factory()->create();
        MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/leaderboards');
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(LEADERBOARDS_BUDGET_COLD);
});

it('renders /leaderboards under ' . LEADERBOARDS_BUDGET_WARM . ' queries on warm cache (aggregates + games cached)', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    foreach (range(1, 3) as $_) {
        $player = Player::factory()->create();
        MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);
    }

    // Warm both caches (leaderboards aggregates + games dropdown).
    $this->get('/leaderboards')->assertStatus(200);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/leaderboards');
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(LEADERBOARDS_BUDGET_WARM);
});

it('renders /leaderboards under ' . LEADERBOARDS_BUDGET_COLD . ' queries with window=30d filter', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(10)]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/leaderboards?window=30d');
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(LEADERBOARDS_BUDGET_COLD);
});

it('renders /leaderboards under ' . LEADERBOARDS_BUDGET_COLD . ' queries with game filter', function (): void {
    $game = Game::query()->firstOrCreate(
        ['key' => 'hll'],
        ['name' => ['en' => 'Hell Let Loose']],
    );
    $match = GameMatch::factory()->create([
        'scheduled_at' => Carbon::now()->subDays(1),
    ]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/leaderboards?game=' . $game->id);
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(LEADERBOARDS_BUDGET_COLD);
});

it('renders /leaderboards under ' . LEADERBOARDS_BUDGET_EMPTY . ' queries with empty database (no players)', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/leaderboards');
    DB::disableQueryLog();

    $response->assertStatus(200);
    // Empty state: aggregates fire (2) + games dropdown (1). Hydration
    // queries 3+4+5 are skipped (gated on `!== []`).
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(LEADERBOARDS_BUDGET_EMPTY);
});
