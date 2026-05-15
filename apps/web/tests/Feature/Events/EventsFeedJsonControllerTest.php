<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-09-PLAN.md task 2.
|
| Replaces the Wave 0 RED stub from plan 07-01.
|
| Covers Pattern 7 polymorphic FullCalendar feed: GET /events/feed.json returns
| Event rows whose eventable_type is one of GameMatch / Tournament / Article in
| the same response, shaped via CalendarEventData. Validates EventsFeedRequest
| (start+end required date, end after start, 90-day max range). Rate-limit
| (throttle:60,1) returns 429 on the 61st request. ->limit(1000) caps the
| feed size even for large windows. is_public=false filters at controller layer.
*/

use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // Plan 09-11 — replaced throttle:60,1 with the named throttle:public-api
    // (30/min by IP). Clear BOTH bucket keys so this file remains robust to
    // future re-tunes of either limiter; clearing a non-existent key is a no-op.
    RateLimiter::clear(sha1('throttle:60,1'));
    RateLimiter::clear('ip:127.0.0.1');
});

it('returns 422 with missing start param', function (): void {
    $this->getJson('/events/feed.json')->assertStatus(422);
});

it('returns 422 with missing end param', function (): void {
    $this->getJson('/events/feed.json?start=2026-06-01')->assertStatus(422);
});

it('returns 422 when end is before start', function (): void {
    $this->getJson('/events/feed.json?start=2026-06-01&end=2026-05-01')->assertStatus(422);
});

it('returns 422 when end is more than 90 days after start (T-07-09-04)', function (): void {
    $this->getJson('/events/feed.json?start=2026-01-01&end=2026-06-01')->assertStatus(422);
});

it('returns 3 event types in a single response (match + tournament + article)', function (): void {
    $category = Category::factory()->create();

    /*
    | All three eventable types auto-create their Event row via their observers
    | (ArticleObserver / MatchObserver / TournamentObserver — Pattern 7 trigger).
    | We set the timestamp fields the observers project into events.starts_at
    | so the rows land inside the test window, then re-query the response to
    | confirm the polymorphic feed UNIONs them transparently.
    */

    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'scheduled_at' => '2026-06-10 12:00:00',
        'published_at' => '2026-06-10 12:00:00',
        'title' => ['en' => 'Article event'],
    ]);

    // GameMatch — MatchObserver::saved auto-creates Event(starts_at = scheduled_at).
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => '2026-06-15 18:00:00',
        'title' => ['en' => 'Match event'],
    ]);

    // Tournament — TournamentObserver::saved auto-creates Event(starts_at = starts_at ?? now()).
    Tournament::factory()->create([
        'is_public' => true,
        'starts_at' => '2026-06-20 14:00:00',
        'title' => ['en' => 'Tournament event'],
    ]);

    $response = $this->getJson('/events/feed.json?start=2026-06-01&end=2026-06-30');

    $response->assertStatus(200);
    $rows = $response->json();
    expect($rows)->toHaveCount(3);

    $types = collect($rows)->pluck('type')->sort()->values()->all();
    expect($types)->toBe(['article', 'match', 'tournament']);

    // Color palette (Open Question 6 LOCKED).
    $byType = collect($rows)->keyBy('type');
    expect($byType['match']['color'])->toBe('#3B82F6');
    expect($byType['tournament']['color'])->toBe('#8B5CF6');
    expect($byType['article']['color'])->toBe('#10B981');
});

it('excludes is_public=false events from the feed (T-07-09-07)', function (): void {
    // Public match — MatchObserver auto-creates Event(is_public=true).
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => '2026-06-15 18:00:00',
        'title' => ['en' => 'Public match'],
    ]);

    // Private match — MatchObserver short-circuits is_public=false (does NOT
    // create an Event row at all per the saved() observer logic). To simulate
    // a non-public event in the calendar window directly, manually flip the
    // auto-created event row's is_public to false via Event::query()->update.
    $hiddenMatch = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => '2026-06-16 18:00:00',
        'title' => ['en' => 'Hidden match'],
    ]);
    Event::query()
        ->where('eventable_type', $hiddenMatch->getMorphClass())
        ->where('eventable_id', $hiddenMatch->id)
        ->update(['is_public' => false]);

    $response = $this->getJson('/events/feed.json?start=2026-06-01&end=2026-06-30');

    $response->assertStatus(200);
    $rows = $response->json();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['title'])->toBe('Public match');
});

it('emits ISO-8601 start timestamps with explicit UTC offset (Pitfall 11)', function (): void {
    // GameMatch — MatchObserver writes Event.starts_at = scheduled_at, ends_at = null.
    // The Pitfall 11 mitigation is the Carbon::toIso8601String emit at the DTO layer,
    // not the underlying observer behavior — so the start field alone proves the
    // ISO-8601 offset format is in place. (ends_at is null for matches per observer.)
    GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => '2026-06-15 18:00:00',
        'title' => ['en' => 'Iso match'],
    ]);

    $response = $this->getJson('/events/feed.json?start=2026-06-01&end=2026-06-30');
    $response->assertStatus(200);

    $row = $response->json()[0];
    // Carbon::toIso8601String always emits an offset like +00:00.
    expect($row['start'])->toMatch('/T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/');
});

it('rate-limits at 30 req/min/IP via throttle:public-api (plan 09-11 — replaces throttle:60,1)', function (): void {
    // Plan 09-11 replaced the inline throttle:60,1 with the named SC-5 throttle:public-api
    // (30/min by IP) to harmonise the public-JSON throttle matrix across phases.
    // T-09-11-01 mitigation; T-07-09-01 carries forward through the new named limiter.
    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/events/feed.json?start=2026-06-01&end=2026-06-30')->assertStatus(200);
    }

    // 31st request hits the throttle cap.
    $this->getJson('/events/feed.json?start=2026-06-01&end=2026-06-30')->assertStatus(429);
});
