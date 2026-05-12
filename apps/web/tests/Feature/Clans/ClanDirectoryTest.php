<?php

declare(strict_types=1);

/*
| Source: 02-07-PLAN.md Task 2 — replaces Wave 0 stub.
| Covers REQ-tenancy-multi-clan: GET /clans returns 200 without auth; contains clan data.
| See .planning/phases/02-clans-tags/02-VALIDATION.md Per-Task Verification Map.
*/

use App\Models\Clan;
use App\Models\ClanTag;
use Inertia\Testing\AssertableInertia as Assert;

it('renders Inertia Clans/Index page with clan list', function (): void {
    Clan::factory()->count(3)->create(['status' => 'active']);

    $this->get('/clans')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Index')
                ->has('clans', 3)
        );
});

it('filters by ?tag=eu', function (): void {
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    $taggedClan1 = Clan::factory()->create(['status' => 'active']);
    $taggedClan1->tags()->attach($tag);
    $taggedClan2 = Clan::factory()->create(['status' => 'active']);
    $taggedClan2->tags()->attach($tag);
    Clan::factory()->create(['status' => 'active']); // untagged

    $this->get('/clans?tag=eu')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Index')
                ->has('clans', 2)
        );
});

it('searches by ?q=name', function (): void {
    Clan::factory()->create(['name' => 'Banana Brigade', 'status' => 'active']);
    Clan::factory()->create(['name' => 'Steel Wolves', 'status' => 'active']);
    Clan::factory()->create(['name' => 'Iron Eagles', 'status' => 'active']);

    $this->get('/clans?q=banana')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Index')
                ->has('clans', 1)
        );
});

it('excludes suspended and disbanded clans from public directory', function (): void {
    Clan::factory()->count(2)->create(['status' => 'active']);
    Clan::factory()->create(['status' => 'suspended']);

    $this->get('/clans')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Index')
                ->has('clans', 2)
        );
});

it('paginates 20 per page', function (): void {
    Clan::factory()->count(25)->create(['status' => 'active']);

    $this->get('/clans')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Index')
                ->has('clans', 20)
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 2)
                ->where('pagination.perPage', 20)
        );
});
