<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-11 (AppServiceProvider::configureRateLimiting
| registers `public-api` and `report-abuse` named limiters). Asserts intent of SC-5
| (rate limit) — RateLimiter::for('public-api') definition is present and limits 30/min.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1360): "RateLimiter::for('public-api') defined; 30/min by IP".
*/

test('Wave 0 stub: RateLimiter::for(public-api) is registered with 30/min by IP', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-11');
})->skip('Wave 0 stub — turned GREEN in plan 09-11');

test('Wave 0 stub: RateLimiter::for(report-abuse) is registered with 5/hour per user', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-11');
})->skip('Wave 0 stub — turned GREEN in plan 09-11');
