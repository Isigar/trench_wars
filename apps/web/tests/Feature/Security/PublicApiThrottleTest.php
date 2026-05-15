<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-11-PLAN.md task 1 — turns the Wave 0
| stub from plan 09-01 GREEN.
|
| Covers SC-5 (rate limit) — the public-api throttle middleware is wired onto
| the public JSON endpoints + GET /search, capping anonymous scraping at
| 30 req/min/IP (T-09-11-01 mitigation; T-09-11-02 mitigation via TrustProxies).
|
| The test suite covers FOUR observable behaviours:
|   1. /clans.json admits 30 requests in one minute from a single IP.
|   2. The 31st request from the same IP returns 429.
|   3. Two distinct IPs do NOT share the same bucket.
|   4. Every relevant public route (clans.json, players.json, events/feed.json,
|      search, leaderboards) carries the named throttle:public-api middleware.
|   5. The Filament /admin namespace does NOT carry throttle:public-api
|      (admin uses panel guards instead — defence-in-depth so a future change
|      that flips a sensitive admin route to public does not silently drop the
|      authentication gate).
|
| RateLimiter::clear takes the SAME key the limiter resolver emits
| (`'ip:' . $request->ip()` for public-api). beforeEach clears the buckets for
| both fixture IPs so prior tests cannot push us over the 30/min cap.
*/

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

const PUBLIC_API_FIXTURE_IP_PRIMARY = '203.0.113.7';
const PUBLIC_API_FIXTURE_IP_SECONDARY = '198.51.100.42';

beforeEach(function (): void {
    RateLimiter::clear('ip:' . PUBLIC_API_FIXTURE_IP_PRIMARY);
    RateLimiter::clear('ip:' . PUBLIC_API_FIXTURE_IP_SECONDARY);
});

it('allows up to 30 requests per minute to /clans.json from a single IP', function (): void {
    for ($i = 1; $i <= 30; $i++) {
        $response = $this->withServerVariables(['REMOTE_ADDR' => PUBLIC_API_FIXTURE_IP_PRIMARY])
            ->get('/clans.json');

        expect($response->getStatusCode())->toBe(
            200,
            sprintf('Request %d/30 should be admitted; got %d', $i, $response->getStatusCode()),
        );
    }
});

it('returns 429 on the 31st request to /clans.json from the same IP within one minute', function (): void {
    for ($i = 1; $i <= 30; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => PUBLIC_API_FIXTURE_IP_PRIMARY])
            ->get('/clans.json')
            ->assertStatus(200);
    }

    $this->withServerVariables(['REMOTE_ADDR' => PUBLIC_API_FIXTURE_IP_PRIMARY])
        ->get('/clans.json')
        ->assertStatus(429);
});

it('keys the public-api throttle per IP — distinct IPs do not share the bucket', function (): void {
    for ($i = 1; $i <= 30; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => PUBLIC_API_FIXTURE_IP_PRIMARY])
            ->get('/clans.json')
            ->assertStatus(200);
    }

    // Primary IP is now spent; a different IP must still be admitted.
    $this->withServerVariables(['REMOTE_ADDR' => PUBLIC_API_FIXTURE_IP_SECONDARY])
        ->get('/clans.json')
        ->assertStatus(200);
});

it('attaches throttle:public-api to every plan-09-11 public JSON / search route', function (): void {
    $expected = [
        'clans.json',
        'players.json',
        'events.feed',
        'search.index',
        'leaderboards.index',
    ];

    foreach ($expected as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("Route {$name} must be registered");

        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('throttle:public-api');
    }
});

it('does NOT attach throttle:public-api to /admin routes (Filament uses panel guards)', function (): void {
    // Pick any admin route — the Filament dashboard is registered as
    // filament.admin.pages.dashboard. The contract: admin pages MUST NOT
    // carry the public-api throttle (panel guard handles authentication).
    $route = Route::getRoutes()->getByName('filament.admin.pages.dashboard');

    if ($route === null) {
        // Resource-name varies; fall back to any /admin route.
        foreach (Route::getRoutes() as $candidate) {
            if (str_starts_with($candidate->uri(), 'admin')) {
                $route = $candidate;
                break;
            }
        }
    }

    expect($route)->not->toBeNull('At least one /admin route must be registered')
        ->and($route->gatherMiddleware())
        ->not->toContain('throttle:public-api');
});
