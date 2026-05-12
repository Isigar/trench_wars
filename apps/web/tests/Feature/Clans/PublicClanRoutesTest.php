<?php

declare(strict_types=1);

/*
| Source: 02-07-PLAN.md Task 2 — replaces Wave 0 stub.
| Covers REQ-goal-public-profiles: /clans, /clans/{slug}, /players/{slug} are
| reachable without auth; private player profile returns 404.
| See .planning/phases/02-clans-tags/02-VALIDATION.md Per-Task Verification Map.
*/

use App\Models\Clan;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;

it('GET /clans returns 200 without auth', function (): void {
    $this->get('/clans')->assertStatus(200);
});

it('GET /clans/{slug} returns 200 for active clan without auth', function (): void {
    $clan = Clan::factory()->create(['status' => 'active']);

    $this->get("/clans/{$clan->slug}")->assertStatus(200);
});

it('GET /clans/{slug} returns 404 for non-active clan', function (): void {
    $clan = Clan::factory()->create(['status' => 'suspended']);

    $this->get("/clans/{$clan->slug}")->assertStatus(404);
});

it('GET /players/{slug} for show_to=public returns 200 without auth', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public']), 'privacy')
        ->create();

    $this->get("/players/{$player->slug}")->assertStatus(200);
});

it('routes are registered with expected names', function (): void {
    expect(route('clans.index'))->toBe(url('/clans'));
    expect(route('clans.show', ['clan' => 'test-clan']))->toBe(url('/clans/test-clan'));
    expect(route('players.show', ['player' => 'test-player']))->toBe(url('/players/test-player'));
});

it('GET /players/{slug} for show_to=private returns 404 without auth', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()
        ->for($user)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();

    $this->get("/players/{$player->slug}")->assertStatus(404);
});
