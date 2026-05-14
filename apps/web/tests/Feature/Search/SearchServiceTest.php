<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\Clan;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use App\Services\SearchService;
use Illuminate\Support\Facades\DB;

/*
| Source: .planning/phases/07-cms/07-08-PLAN.md task 2.
|
| Replaces the Wave 0 RED stub from plan 07-01. Bare Pest functional style
| (Phase 5 D-05-01-C / Phase 7 plan 07-07 precedent) — no namespace, no
| per-file uses(); Pest.php autowires TestCase + RefreshDatabase via
| uses(...)->in('Feature').
|
| The service is resolved through the container so the singleton-scoped
| PlayerPrivacyGate dependency wires automatically. Each it() block creates
| only the rows its assertion needs — the search_vector triggers on each
| INSERT populate the tsvector column without a backfill step.
*/

it('returns an empty SearchResultsData for an empty query and fires no DB queries', function (): void {
    $service = app(SearchService::class);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $result = $service->search('   ');  // whitespace also short-circuits via trim()

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($result->articles)->toBe([]);
    expect($result->clans)->toBe([]);
    expect($result->players)->toBe([]);
    expect($result->totalCount)->toBe(0);
    expect($result->query)->toBe('');  // trimmed
    expect($queries)->toHaveCount(0);   // short-circuit before any SQL
});

it('matches an article by title via Postgres FTS', function (): void {
    $service = app(SearchService::class);
    $category = Category::factory()->create();

    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'title' => ['en' => 'Rifleman strategy primer'],
        'excerpt' => ['en' => 'Frontline basics.'],
    ]);

    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'title' => ['en' => 'Medic tactics overview'],
        'excerpt' => ['en' => 'Healing patterns.'],
    ]);

    $result = $service->search('rifleman');

    expect($result->articles)->toHaveCount(1);
    expect($result->articles[0]->title)->toBe('Rifleman strategy primer');
    expect($result->articles[0]->type)->toBe('article');
});

it('ranks articles by ts_rank descending (higher term frequency ranks first)', function (): void {
    $service = app(SearchService::class);
    $category = Category::factory()->create();

    /*
    | The plan's must_have asked to assert title-match outranks excerpt-only-
    | match. With the Phase 7 plan 07-02 unweighted tsvector (title + excerpt
    | + slug concatenated as ONE vector under the 'simple' text-search config,
    | NO setweight() calls) ts_rank cannot differentiate a title-position match
    | from an excerpt-position match — both produce identical ranks for the
    | same lexeme. To prove ts_rank ordering applies (vs. insertion order), we
    | exercise the path it CAN differentiate: term frequency. The article that
    | contains 'rifleman' twice in the indexed text outranks one that contains
    | it once. Recorded as D-07-08-B deviation in SUMMARY.
    */
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'title' => ['en' => 'Medic loadout'],
        'excerpt' => ['en' => 'Brief medic tips.'],
    ]);

    // Article B: 'rifleman' lexeme appears twice in the vector
    // (title + excerpt). Higher term frequency → higher ts_rank.
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'title' => ['en' => 'Rifleman loadout'],
        'excerpt' => ['en' => 'Rifleman primer for new recruits.'],
    ]);

    // Article C: 'rifleman' lexeme appears once in the vector.
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'title' => ['en' => 'Squad composition'],
        'excerpt' => ['en' => 'Pair a rifleman with a medic.'],
    ]);

    $result = $service->search('rifleman');

    expect($result->articles)->toHaveCount(2);   // article A (medic only) does NOT match
    // ts_rank DESC — Article B (term frequency = 2) leads Article C (term frequency = 1).
    expect($result->articles[0]->title)->toBe('Rifleman loadout');
    expect($result->articles[1]->title)->toBe('Squad composition');
    // ts_rank ordinals: highest rank = articles count, descending — preserves total order.
    expect($result->articles[0]->rank)->toBeGreaterThan($result->articles[1]->rank);
});

it('filters private-tier players from results for an anonymous viewer (T-07-08-03)', function (): void {
    $service = app(SearchService::class);

    $player = Player::factory()->create([
        'display_name' => 'ShadowOperator',
    ]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'private',
    ]);

    $result = $service->search('ShadowOperator', null);

    expect($result->players)->toBe([]);
    expect($result->totalCount)->toBe(0);
});

it('shows a private-tier player to themselves (own-profile bypass)', function (): void {
    $service = app(SearchService::class);

    $user = User::factory()->create();
    $player = Player::factory()->create([
        'user_id' => $user->id,
        'display_name' => 'StealthRecon',
    ]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'private',
    ]);

    $result = $service->search('StealthRecon', $user);

    expect($result->players)->toHaveCount(1);
    expect($result->players[0]->title)->toBe('StealthRecon');
    expect($result->players[0]->type)->toBe('player');
});

it('does NOT throw on punctuation-laden / SQL-injection-shaped queries (Pitfall 2 + T-07-08-01)', function (): void {
    $service = app(SearchService::class);

    // plainto_tsquery sanitises stray operators — these all return safely.
    expect(fn () => $service->search('AC/DC'))->not->toThrow(Exception::class);
    expect(fn () => $service->search('foo;DROP TABLE bar'))->not->toThrow(Exception::class);
    expect(fn () => $service->search('!!!@#$%^&*()'))->not->toThrow(Exception::class);
    expect(fn () => $service->search("' OR 1=1 --"))->not->toThrow(Exception::class);
});

it('excludes draft articles from results (T-07-08-05)', function (): void {
    $service = app(SearchService::class);
    $category = Category::factory()->create();

    Article::factory()->for($category, 'category')->create([
        'status' => 'draft',
        'title' => ['en' => 'Unreleased secret leak strategy'],
    ]);
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'title' => ['en' => 'Public secret tips'],
    ]);

    $result = $service->search('secret');

    expect($result->articles)->toHaveCount(1);
    expect($result->articles[0]->title)->toBe('Public secret tips');
});

it('matches a clan by name via FTS and produces clan-type results', function (): void {
    $service = app(SearchService::class);

    Clan::factory()->create([
        'name' => 'The Phantom Brigade',
        'tag' => 'PHB1',
        'slug' => 'phantom-brigade',
        'description' => ['en' => 'An elite squad of riflemen.'],
    ]);

    $result = $service->search('phantom');

    expect($result->clans)->toHaveCount(1);
    expect($result->clans[0]->type)->toBe('clan');
    expect($result->clans[0]->title)->toBe('The Phantom Brigade');
    expect($result->clans[0]->slug)->toBe('phantom-brigade');
});

it('PlayerPrivacyGate::canShowInSearch honours the community tier (visible to any logged-in viewer)', function (): void {
    $gate = app(PlayerPrivacyGate::class);
    $player = Player::factory()->create();
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'community',
    ]);
    $viewer = User::factory()->create();

    expect($gate->canShowInSearch($player, null))->toBeFalse();   // guest
    expect($gate->canShowInSearch($player, $viewer))->toBeTrue();  // logged-in
});
