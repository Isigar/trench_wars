<?php

declare(strict_types=1);

/*
| Source: 06-12-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers GET /tournaments:
|   - Renders Inertia 'Tournaments/Index' component.
|   - is_public=false tournaments are excluded from the listing.
|   - status NOT IN (draft, cancelled) — only visible lifecycle stages surface.
|   - tournaments prop is a list of {id, slug, title, format, status, ...} rows.
|
| Privacy: D-018 — tournament titles + format are public; no per-tournament auth.
*/

use App\Models\Tournament;
use Inertia\Testing\AssertableInertia as Assert;

it('renders Tournaments/Index Inertia component for guest visitors', function (): void {
    Tournament::factory()->count(3)->create(['is_public' => true, 'status' => 'running']);

    $this->get(route('tournaments.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Index', false)
                ->has('tournaments', 3)
        );
});

it('excludes private (is_public=false) tournaments from the public listing', function (): void {
    Tournament::factory()->count(2)->create(['is_public' => true, 'status' => 'running']);
    Tournament::factory()->count(3)->create(['is_public' => false, 'status' => 'running']);

    $this->get(route('tournaments.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Index', false)
                ->has('tournaments', 2)
        );
});

it('excludes draft tournaments from the public listing', function (): void {
    Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);
    Tournament::factory()->create(['is_public' => true, 'status' => 'registering']);
    Tournament::factory()->create(['is_public' => true, 'status' => 'running']);
    Tournament::factory()->create(['is_public' => true, 'status' => 'completed']);

    $this->get(route('tournaments.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Index', false)
                ->has('tournaments', 3)
        );
});

it('excludes cancelled tournaments from the public listing', function (): void {
    Tournament::factory()->create(['is_public' => true, 'status' => 'cancelled']);
    Tournament::factory()->create(['is_public' => true, 'status' => 'running']);

    $this->get(route('tournaments.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Index', false)
                ->has('tournaments', 1)
        );
});

it('returns each tournament row with the public shape (id, slug, title, format, status)', function (): void {
    Tournament::factory()->create([
        'is_public' => true,
        'status' => 'running',
        'slug' => 'open-2026',
        'format' => 'single_elimination',
    ]);

    $this->get(route('tournaments.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Index', false)
                ->has('tournaments.0.id')
                ->has('tournaments.0.slug')
                ->where('tournaments.0.slug', 'open-2026')
                ->has('tournaments.0.title')
                ->where('tournaments.0.format', 'single_elimination')
                ->where('tournaments.0.status', 'running')
        );
});
