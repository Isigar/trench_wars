<?php

declare(strict_types=1);

/*
| Source: 09-08-PLAN.md task 2 — turns the Wave 0 RED stub GREEN (SC-4).
|
| Locks the per-request query budget for /clans (clan directory). With
| 10 clans on a page, the controller fires exactly 5 queries:
|
|   1. SELECT count(*) FROM clans         (paginate total)
|   2. SELECT * FROM clans LIMIT 20       (page contents)
|   3. SELECT clan_tags JOIN clan_clan_tag WHERE clan_id IN (...)
|        — `with('tags')` eager-load
|   4. SELECT clan_memberships WHERE clan_id IN (...) AND left_at IS NULL
|        — `with('activeMembers')` eager-load
|   5. SELECT * FROM clan_tags             (filter dropdown)
|
| Budget = 8 per RESEARCH "Target query budgets per public page". 5 < 8 so
| we lock the budget at the plan value (≤8) to allow headroom for future
| eager-load expansions (e.g. `with('owner')` or `with('game')`) without
| an immediate budget bump.
|
| With a tag-filter (?tag=eu) the controller additionally fires one
| ClanTag::where('slug',...)->firstOrFail() query — still 6 ≤ 8.
*/

use App\Models\Clan;
use App\Models\ClanTag;
use Illuminate\Support\Facades\DB;

const CLANS_BUDGET = 8;

it('renders /clans under ' . CLANS_BUDGET . ' queries on cold load with 10 clans', function (): void {
    Clan::factory()->count(10)->create(['status' => 'active']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/clans');
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(CLANS_BUDGET);
});

it('renders /clans under ' . CLANS_BUDGET . ' queries with tag filter applied', function (): void {
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    Clan::factory()->count(10)->create(['status' => 'active'])
        ->each(fn (Clan $c) => $c->tags()->attach($tag->id));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/clans?tag=eu');
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(CLANS_BUDGET);
});

it('renders /clans under ' . CLANS_BUDGET . ' queries when paginated to page 2', function (): void {
    Clan::factory()->count(30)->create(['status' => 'active']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/clans?page=2');
    DB::disableQueryLog();

    $response->assertStatus(200);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(CLANS_BUDGET);
});

it('renders /clans under ' . CLANS_BUDGET . ' queries with empty database', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get('/clans');
    DB::disableQueryLog();

    $response->assertStatus(200);
    // Empty: paginate count + clan_tags dropdown. No clan-row hydration
    // queries fire because the with([...]) eager-loads short-circuit on
    // an empty parent set.
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(CLANS_BUDGET);
});
