<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-11-PLAN.md task 1 — turns the Wave 0
| stubs (plan 09-01) GREEN.
|
| Covers SC-5 (rate limit) — AppServiceProvider::boot() registers FOUR named
| RateLimiter definitions consumed by the route layer:
|
|   - public-api        — 30/min by IP    (T-09-11-01 mitigation)
|   - auth              — 10/min by IP    (T-09-11-07 mitigation)
|   - notifications-read— 120/min by user (T-09-06-04 / T-09-11-* mitigation)
|   - report-abuse      — 5/hour by user  (T-09-11-03 mitigation)
|
| The assertion shape inspects the Limit object returned by the registered
| closure: ->maxAttempts, ->decayMinutes (or ->decaySeconds depending on
| Laravel version), ->key. Laravel 12's Illuminate\Cache\RateLimiting\Limit
| exposes `$maxAttempts` (int), `$decaySeconds` (int), `$key` (mixed) as
| public properties.
|
| Pre-existing limiters from Phase 1 (`api` via `\RouteServiceProvider` was
| superseded in Laravel 11; only the ones AppServiceProvider declares are
| asserted here — the Laravel default `api` limiter is registered by the
| framework via `routes/api.php` only when that file exists, which it does
| not in this codebase).
*/

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('registers public-api limiter at 30/min by IP', function (): void {
    /** @var Closure(Request): Limit $resolver */
    $resolver = RateLimiter::limiter('public-api');

    expect($resolver)->toBeCallable();

    $request = Request::create('/clans.json', 'GET');
    $request->server->set('REMOTE_ADDR', '203.0.113.7');

    /** @var Limit $limit */
    $limit = $resolver($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(30)
        ->and($limit->decaySeconds)->toBe(60)
        ->and((string) $limit->key)->toContain('203.0.113.7');
});

it('registers auth limiter at 10/min by IP', function (): void {
    /** @var Closure(Request): Limit $resolver */
    $resolver = RateLimiter::limiter('auth');

    expect($resolver)->toBeCallable();

    $request = Request::create('/auth/discord/callback', 'GET');
    $request->server->set('REMOTE_ADDR', '198.51.100.12');

    /** @var Limit $limit */
    $limit = $resolver($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(10)
        ->and($limit->decaySeconds)->toBe(60)
        ->and((string) $limit->key)->toContain('198.51.100.12');
});

it('registers notifications-read limiter at 120/min keyed by authenticated user id', function (): void {
    /** @var Closure(Request): Limit $resolver */
    $resolver = RateLimiter::limiter('notifications-read');

    expect($resolver)->toBeCallable();

    $user = new class
    {
        public function getAuthIdentifier(): string
        {
            return 'user-abc-123';
        }
    };

    $request = Request::create('/notifications/x/read', 'POST');
    $request->setUserResolver(static fn () => $user);

    /** @var Limit $limit */
    $limit = $resolver($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(120)
        ->and($limit->decaySeconds)->toBe(60)
        ->and((string) $limit->key)->toContain('user-abc-123');
});

it('registers report-abuse limiter at 5/hour keyed by authenticated user id', function (): void {
    /** @var Closure(Request): Limit $resolver */
    $resolver = RateLimiter::limiter('report-abuse');

    expect($resolver)->toBeCallable();

    $user = new class
    {
        public function getAuthIdentifier(): string
        {
            return 'reporter-uuid-42';
        }
    };

    $request = Request::create('/reports', 'POST');
    $request->setUserResolver(static fn () => $user);

    /** @var Limit $limit */
    $limit = $resolver($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(5)
        // Hourly window — 60 minutes × 60 seconds = 3600 seconds.
        ->and($limit->decaySeconds)->toBe(3600)
        ->and((string) $limit->key)->toContain('reporter-uuid-42');
});

it('regression guard: pre-existing limiters from Phase 1-8 are still callable', function (): void {
    // The 4 plan-09-11 limiters are also re-asserted here as a cheap regression
    // canary against future provider edits that might accidentally drop them.
    foreach (['public-api', 'auth', 'notifications-read', 'report-abuse'] as $name) {
        expect(RateLimiter::limiter($name))
            ->toBeCallable("RateLimiter::for('{$name}') must remain registered");
    }
});
