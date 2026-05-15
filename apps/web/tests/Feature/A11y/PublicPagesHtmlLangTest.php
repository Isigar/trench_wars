<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-10-PLAN.md task 1 — turns the Wave 0
| stub from plan 09-01 GREEN.
|
| Covers SC-5 (WCAG 2.1 AA) round-1 deliverable: every public Inertia page
| advertises the active locale to screen readers + assistive tech via
| <html lang="..."> in the root Blade view (apps/web/resources/views/app.blade.php).
|
| The root Blade emits `<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">`
| (plan 01-06). Round 1 ships EN only (D-013 + config('i18n.available_locales')=['en']),
| so every public route MUST render `lang="en"` in the HTML body.
|
| Public route matrix per plan 09-10 + Pitfall 11 (axe-core CI scans the same set):
|   /                                — home
|   /clans                           — clan directory
|   /matches                         — match calendar
|   /tournaments                     — tournament directory
|   /blog                            — articles index (route name BlogIndexController)
|   /events                          — events calendar
|   /leaderboards                    — leaderboards index
|
| NOTE: plan literal lists `/articles` but the route registered in
| apps/web/routes/web.php (plan 07-09) is `/blog` (Phase 7 D-07-09-x — the
| `/blog` slug is the public-facing surface; `Articles/Index` is just the
| Inertia component name). We test the actual route per Pitfall 11 (a11y
| validation must match the URL surface that axe-core scans in CI).
|
| Bare Pest functional style (Pest.php autowires TestCase + RefreshDatabase).
*/

it('renders <html lang="en"> on / (homepage)', function (): void {
    $response = $this->get('/');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="en"> on /clans (directory)', function (): void {
    $response = $this->get('/clans');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="en"> on /matches (calendar)', function (): void {
    $response = $this->get('/matches');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="en"> on /tournaments (directory)', function (): void {
    $response = $this->get('/tournaments');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="en"> on /blog (articles index)', function (): void {
    $response = $this->get('/blog');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="en"> on /events (calendar)', function (): void {
    $response = $this->get('/events');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="en"> on /leaderboards (index)', function (): void {
    $response = $this->get('/leaderboards');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});
