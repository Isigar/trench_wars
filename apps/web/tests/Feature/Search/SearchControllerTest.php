<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-09-PLAN.md task 2.
|
| Replaces the Wave 0 RED stub from plan 07-01.
|
| Covers SC-4 (Postgres FTS public surface): GET /search?q= renders an Inertia
| 'Search/Results' page; SearchRequest enforces min:2 + regex; query echo is
| auto-escaped by Inertia/Vue; PlayerPrivacyGate at SearchService layer
| filters private-tier players; throttle:60,1 caps abuse (T-07-09-01).
*/

use App\Models\Article;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // Plan 09-11 — replaced throttle:60,1 with the named throttle:public-api
    // (30/min by IP). Clear BOTH bucket keys so this file remains robust to
    // future re-tunes of either limiter.
    RateLimiter::clear(sha1('throttle:60,1'));
    RateLimiter::clear('ip:127.0.0.1');
});

it('returns 422 with missing q param', function (): void {
    $this->get('/search')->assertStatus(302); // redirects back with errors on web routes
});

it('returns 422 with q shorter than 2 chars', function (): void {
    // Web route validation redirects back with session errors; assert the
    // validation message rather than a JSON 422 status.
    $this->get('/search?q=a')
        ->assertStatus(302)
        ->assertSessionHasErrors('q');
});

it('returns 422 when q contains disallowed characters (T-07-09-03)', function (): void {
    // The regex blocks < > since they are not in the allowed set.
    $this->get('/search?q=<script>')
        ->assertStatus(302)
        ->assertSessionHasErrors('q');
});

it('returns Inertia Search/Results component for a valid q', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Phantom strategy primer'],
    ]);

    $this->get('/search?q=phantom')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Results', false)
                ->has('results')
                ->where('query', 'phantom')
                ->has('results.articles')
                ->has('results.clans')
                ->has('results.players')
        );
});

it('escapes the echoed query in the response body (T-07-09-06)', function (): void {
    /*
    | The SearchRequest regex blocks `<` and `>` so a raw <script> payload never
    | reaches the controller. To demonstrate that the echo path is escape-safe
    | even for allowed chars, we send an apostrophe-bearing query. Inertia
    | renders its props payload into a `<div id="app" data-page="...">` Blade
    | attribute via htmlspecialchars(..., ENT_QUOTES) — double-encoded so:
    |   - the surrounding JSON's double quotes become `&quot;`
    |   - the apostrophe inside the value becomes `&#039;`
    | Asserting both substrings appear together (and the raw `'<script>` does
    | NOT) proves the echo path cannot be used to inject HTML.
    */
    $response = $this->get("/search?q=O'Brien");
    $response->assertStatus(200);

    $body = $response->getContent();
    expect($body)->toBeString();

    // Confirm the apostrophe is HTML-encoded inside the data-page payload.
    expect($body)->toContain('O&#039;Brien');
    // Confirm the JSON wrapper is HTML-encoded (data-page is htmlspecialchars'd).
    expect($body)->toContain('&quot;query&quot;');
    // Negative — raw script tags or unescaped apostrophes adjacent to query MUST NOT appear.
    expect($body)->not->toContain('<script>O');
});

it('integrates PlayerPrivacyGate from SearchService (private-tier player hidden from anonymous)', function (): void {
    $player = Player::factory()->create([
        'display_name' => 'ShadowOperator',
    ]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'private',
    ]);

    $this->get('/search?q=ShadowOperator')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Results', false)
                ->has('results.players', 0)
        );
});

it('rate-limits at 30 req/min/IP via throttle:public-api (plan 09-11 — replaces throttle:60,1)', function (): void {
    // Plan 09-11 replaced the inline throttle:60,1 with the named SC-5 throttle:public-api
    // (30/min by IP) to harmonise the public-JSON throttle matrix across phases.
    // T-09-11-01 mitigation; T-07-09-01 carries forward through the new named limiter.
    for ($i = 0; $i < 30; $i++) {
        $this->get('/search?q=phantom')->assertStatus(200);
    }

    // 31st request hits the throttle cap.
    $this->get('/search?q=phantom')->assertStatus(429);
});
