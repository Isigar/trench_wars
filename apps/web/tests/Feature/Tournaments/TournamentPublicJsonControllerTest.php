<?php

declare(strict_types=1);

/*
| Source: 06-12-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers GET /tournaments/{slug}.json:
|   - Returns 200 + JSON payload {data, etag, last_modified_at} with ETag header.
|   - 304 Not Modified short-circuit when If-None-Match matches the current etag.
|   - 404 for non-public tournaments (T-06-12-03 non-disclosure).
|   - Rate limit (throttle:60,1) returns 429 after 60 requests in the same minute.
|
| Privacy: PublicTournamentData is privacy-filtered (D-018); no admin-only fields.
|
| Pattern 9 (RESEARCH): ETag deterministically computed from
|   tournament.updated_at + sorted bracket (id:updated_at) — same input order →
|   identical etag.
*/

use App\Models\Tournament;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // Clear the throttle bucket so prior tests can't push us over the 60/min cap.
    RateLimiter::clear(sha1('throttle:60,1'));
});

it('returns 200 with JSON payload shape {data, etag, last_modified_at}', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $response = $this->get(route('tournaments.show.json', $tournament));

    $response->assertOk();
    $response->assertJsonStructure(['data', 'etag', 'last_modified_at']);
    expect($response->headers->get('ETag'))->not->toBeNull();
});

it('emits an ETag header that matches the etag in the body', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $response = $this->get(route('tournaments.show.json', $tournament));

    $response->assertOk();
    $bodyEtag = $response->json('etag');
    $headerEtag = trim((string) $response->headers->get('ETag'), '"');
    expect($bodyEtag)->toBe($headerEtag);
});

it('returns 304 Not Modified when If-None-Match matches the current etag', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $first = $this->get(route('tournaments.show.json', $tournament));
    $first->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $second = $this->withHeaders(['If-None-Match' => $etag])
        ->get(route('tournaments.show.json', $tournament));

    expect($second->status())->toBe(304);
});

it('returns 200 when If-None-Match does not match the current etag', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $response = $this->withHeaders(['If-None-Match' => '"stale-etag-value"'])
        ->get(route('tournaments.show.json', $tournament));

    $response->assertOk();
    $response->assertJsonStructure(['data', 'etag', 'last_modified_at']);
});

it('returns 404 for non-public tournaments', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => false]);

    $this->get(route('tournaments.show.json', $tournament))
        ->assertStatus(404);
});

it('returns the same etag for repeated calls on an unchanged tournament', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $first = $this->get(route('tournaments.show.json', $tournament));
    $second = $this->get(route('tournaments.show.json', $tournament));

    expect($first->json('etag'))->toBe($second->json('etag'));
});

it('rate-limits at 60 req/min/IP via throttle:60,1 middleware', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    // Exhaust the bucket — first 60 requests succeed.
    for ($i = 0; $i < 60; $i++) {
        $this->get(route('tournaments.show.json', $tournament))->assertOk();
    }

    // 61st request hits the throttle cap.
    $this->get(route('tournaments.show.json', $tournament))->assertStatus(429);
});
