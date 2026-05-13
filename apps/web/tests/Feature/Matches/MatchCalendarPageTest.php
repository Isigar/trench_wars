<?php

declare(strict_types=1);

/*
| Source: 04-10-PLAN.md Task 2 — replaces Wave 0 RED stub.
|
| Covers SC-3 first half: GET /matches is reachable without auth; the visibility
| query layer excludes draft + cancelled + private matches; tag + status filter
| params bind through Eloquent; pagination caps at 20 per page.
|
| Renders Inertia component 'Matches/Index' with three top-level props:
|   matches[]       — PublicMatchData collection
|   pagination{}    — { currentPage, lastPage, total, perPage }
|   activeFilters{} — { dateFrom, dateTo, tag, status }
|
| NAMING NOTE (D-04-03-A): the Match model is `GameMatch`. Tests import
| `use App\Models\GameMatch;` directly per D-04-04-C / D-04-05-B.
*/

use App\Models\ClanTag;
use App\Models\GameMatch;
use App\Models\MatchAccessRule;
use Inertia\Testing\AssertableInertia as Assert;

// ───── Reachability ─────────────────────────────────────────────────────────

it('GET /matches returns 200 without auth', function (): void {
    $this->get('/matches')->assertStatus(200);
});

it('GET /matches renders Inertia Matches/Index with matches + pagination + activeFilters props', function (): void {
    GameMatch::factory()->count(3)->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(2),
    ]);

    $this->get('/matches')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Matches/Index', false) // Vue page lands in plan 04-11; skip existence check.
                ->has('matches', 3)
                ->has('pagination.currentPage')
                ->has('pagination.lastPage')
                ->has('pagination.total')
                ->has('pagination.perPage')
                ->has('activeFilters.dateFrom')
                ->where('activeFilters.tag', null)
                ->where('activeFilters.status', null)
        );
});

// ───── Visibility filters ───────────────────────────────────────────────────

it('GET /matches excludes draft matches from public view', function (): void {
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'draft',
        'scheduled_at' => now()->addDays(1),
    ]);
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);

    $this->get('/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('matches', 1));
});

it('GET /matches excludes cancelled matches from public view', function (): void {
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'cancelled',
        'scheduled_at' => now()->addDays(1),
    ]);
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);

    $this->get('/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('matches', 1));
});

it('GET /matches excludes is_public=false matches from public view', function (): void {
    GameMatch::factory()->create([
        'is_public' => false,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);

    $this->get('/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('matches', 1));
});

it('GET /matches excludes matches with past scheduled_at by default', function (): void {
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->subDays(5),
    ]);
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(5),
    ]);

    $this->get('/matches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('matches', 1));
});

// ───── Filter params ────────────────────────────────────────────────────────

it('GET /matches?tag=eu filters to matches with that access rule tag', function (): void {
    $tagEu = ClanTag::factory()->create(['slug' => 'eu']);
    $tagNa = ClanTag::factory()->create(['slug' => 'na']);

    $matchEu = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);
    MatchAccessRule::factory()->create([
        'match_id' => $matchEu->id,
        'clan_tag_id' => $tagEu->id,
    ]);

    $matchNa = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);
    MatchAccessRule::factory()->create([
        'match_id' => $matchNa->id,
        'clan_tag_id' => $tagNa->id,
    ]);

    $this->get('/matches?tag=eu')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('matches', 1)
                ->where('matches.0.id', $matchEu->id)
                ->where('activeFilters.tag', 'eu')
        );
});

it('GET /matches?tag=unknown returns 404 (clan tag firstOrFail)', function (): void {
    $this->get('/matches?tag=does-not-exist')->assertStatus(404);
});

it('GET /matches?status=locked filters by status', function (): void {
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);
    $locked = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'locked',
        'scheduled_at' => now()->addDays(1),
    ]);

    $this->get('/matches?status=locked')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('matches', 1)
                ->where('matches.0.id', $locked->id)
                ->where('activeFilters.status', 'locked')
        );
});

it('GET /matches?status=draft is rejected by validation (not in allowlist)', function (): void {
    // validator rule: 'status' => 'nullable|in:open,locked,played' — draft is not allowed
    $this->get('/matches?status=draft')->assertStatus(302); // ValidationException redirects back
});

it('GET /matches?date_to filters out matches after the upper bound', function (): void {
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(2),
    ]);
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(10),
    ]);

    $upper = now()->addDays(5)->toDateString();
    $this->get("/matches?date_to={$upper}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('matches', 1));
});

// ───── Pagination ──────────────────────────────────────────────────────────

it('GET /matches paginates 20 per page', function (): void {
    GameMatch::factory()->count(25)->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => now()->addDays(1),
    ]);

    $this->get('/matches')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('matches', 20)
                ->where('pagination.perPage', 20)
                ->where('pagination.total', 25)
                ->where('pagination.lastPage', 2)
        );
});

it('routes are registered with expected names', function (): void {
    expect(route('matches.index'))->toBe(url('/matches'));
});
