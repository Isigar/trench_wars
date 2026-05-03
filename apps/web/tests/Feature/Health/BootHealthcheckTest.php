<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BootHealthcheckTest — Wave 0 / Nyquist baseline smoke test
|--------------------------------------------------------------------------
| Source: 01-VALIDATION.md (BootHealthcheckTest entry) + 01-CONTEXT.md
|         Specific Ideas ("CI Pest: Run a single smoke test in P1").
|
| Asserts the application bootstraps end-to-end against postgres + redis,
| and the public landing route serves a 200. This test MUST pass on the
| Laravel default welcome page after plan 04+05; later plans (06, 09, 11, 12)
| extend this file with /admin and OAuth-route assertions.
*/

it('boots and serves the landing route', function (): void {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('reports a healthy app config', function (): void {
    expect(config('app.env'))->toBe('testing');
    expect(config('database.default'))->toBe('pgsql');
});
