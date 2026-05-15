<?php

declare(strict_types=1);

/*
| Source: 09-08-PLAN.md task 1 — turns the Wave 0 RED stub GREEN.
|
| Verifies AppServiceProvider::boot() flips Eloquent strict mode in non-
| production environments per RESEARCH Pattern 6 + SC-4.
|
| Strict-mode behaviour is asserted at the ORM-static level (no DB needed):
|   1. Model::preventsLazyLoading() === true (non-prod).
|   2. Model::preventsAccessingMissingAttributes() === true (non-prod).
|   3. The source code in AppServiceProvider gates the flag on
|      `! $this->app->isProduction()` so production runtime stays relaxed.
|
| Runtime behaviour (LazyLoadingViolationException on a real lazy load) is
| asserted indirectly by the rest of the Phase 1-9 suite — every test that
| previously surfaced a lazy load is now GREEN only because explicit
| eager-loads / loadMissing calls were added in this plan.
*/

use Illuminate\Database\Eloquent\Model;

it('enables Model::shouldBeStrict in non-production (testing env)', function (): void {
    expect(app()->isProduction())->toBeFalse();
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('enables preventAccessingMissingAttributes alongside preventLazyLoading', function (): void {
    // shouldBeStrict() flips BOTH preventLazyLoading + preventAccessingMissingAttributes
    // (+ preventSilentlyDiscardingAttributes) — locking the full strict trio.
    expect(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('gates the strict-mode flag on `! isProduction()` so production stays relaxed', function (): void {
    // Source-level assertion — we cannot toggle APP_ENV mid-test without
    // re-bootstrapping the container. Read the provider source and confirm
    // the production guard is present and intact.
    $source = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    expect($source)->toContain('Model::shouldBeStrict(! $this->app->isProduction())');
});
