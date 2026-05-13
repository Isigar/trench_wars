<?php

declare(strict_types=1);

/*
| Source: 06-12-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers GET /tournaments/{slug}:
|   - Renders Inertia 'Tournaments/Show' component.
|   - Private (is_public=false) tournaments return 404 (T-06-12-03 non-disclosure).
|   - Tournament prop hydrates from PublicTournamentData::fromModel (id, slug,
|     format, status, nodes, edges, standings, participants, etag, last_modified_at).
|   - Slug route binding picks up the tournament by slug column.
|
| Privacy: D-018 — clan names + tournament titles are public.
*/

use App\Models\Tournament;
use Inertia\Testing\AssertableInertia as Assert;

it('renders Tournaments/Show with PublicTournamentData props for guest visitors', function (): void {
    $tournament = Tournament::factory()->create([
        'is_public' => true,
        'slug' => 'open-2026',
    ]);

    $this->get(route('tournaments.show', $tournament))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Show', false)
                ->has('tournament')
                ->has('tournament.id')
                ->where('tournament.id', $tournament->id)
                ->where('tournament.slug', 'open-2026')
                ->has('tournament.nodes')
                ->has('tournament.edges')
                ->has('tournament.etag')
        );
});

it('returns 404 for non-public (is_public=false) tournaments', function (): void {
    $tournament = Tournament::factory()->create([
        'is_public' => false,
        'slug' => 'private-cup',
    ]);

    $this->get(route('tournaments.show', $tournament))
        ->assertStatus(404);
});

it('returns 404 for non-existent tournament slug', function (): void {
    $this->get('/tournaments/does-not-exist')
        ->assertStatus(404);
});

it('binds the tournament by slug, not by id', function (): void {
    $tournament = Tournament::factory()->create([
        'is_public' => true,
        'slug' => 'fall-classic',
    ]);

    $this->get('/tournaments/fall-classic')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Tournaments/Show', false)
                ->where('tournament.slug', 'fall-classic')
        );

    // The id-shaped path should NOT match the slug route.
    $this->get("/tournaments/{$tournament->id}")
        ->assertStatus(404);
});
