<?php

declare(strict_types=1);

/*
| Source: 02-07-PLAN.md Task 2 — replaces Wave 0 stub.
| Covers REQ-goal-public-profiles: show_to=private returns 404; show_to=community
| returns 404 for guests; per-section flags strip fields from DTO.
| See .planning/phases/02-clans-tags/02-VALIDATION.md Per-Task Verification Map.
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// ─── private tier ─────────────────────────────────────────────────────────────

it('private tier: guest returns 404', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();

    $this->get("/players/{$player->slug}")->assertStatus(404);
});

it('private tier: same-clan viewer returns 404', function (): void {
    $targetUser = User::factory()->create();
    $targetPlayer = Player::factory()->for($targetUser)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();

    $clan = Clan::factory()->create();
    ClanMembership::factory()->create(['user_id' => $targetUser->id, 'clan_id' => $clan->id, 'left_at' => null]);

    $viewerUser = User::factory()->create();
    Player::factory()->for($viewerUser)->create();
    ClanMembership::factory()->create(['user_id' => $viewerUser->id, 'clan_id' => $clan->id, 'left_at' => null]);

    $this->actingAs($viewerUser)
        ->get("/players/{$targetPlayer->slug}")
        ->assertStatus(404);
});

it('private tier: own profile returns 200', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();

    $this->actingAs($user)
        ->get("/players/{$player->slug}")
        ->assertStatus(200);
});

// ─── community tier ───────────────────────────────────────────────────────────

it('community tier: guest returns 404', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'community']), 'privacy')
        ->create();

    $this->get("/players/{$player->slug}")->assertStatus(404);
});

it('community tier: authenticated user returns 200', function (): void {
    $targetPlayer = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'community']), 'privacy')
        ->create();

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/players/{$targetPlayer->slug}")
        ->assertStatus(200);
});

// ─── clan tier ────────────────────────────────────────────────────────────────

it('clan tier: guest returns 404', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create();

    $this->get("/players/{$player->slug}")->assertStatus(404);
});

it('clan tier: auth user not in same clan returns 404', function (): void {
    $targetUser = User::factory()->create();
    $targetPlayer = Player::factory()->for($targetUser)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create();

    $clan = Clan::factory()->create();
    ClanMembership::factory()->create(['user_id' => $targetUser->id, 'clan_id' => $clan->id, 'left_at' => null]);

    $viewerUser = User::factory()->create();
    // viewer is NOT in the same clan

    $this->actingAs($viewerUser)
        ->get("/players/{$targetPlayer->slug}")
        ->assertStatus(404);
});

it('clan tier: auth user in same clan returns 200', function (): void {
    $targetUser = User::factory()->create();
    $targetPlayer = Player::factory()->for($targetUser)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create();

    $clan = Clan::factory()->create();
    ClanMembership::factory()->create(['user_id' => $targetUser->id, 'clan_id' => $clan->id, 'left_at' => null]);

    $viewerUser = User::factory()->create();
    Player::factory()->for($viewerUser)->create();
    ClanMembership::factory()->create(['user_id' => $viewerUser->id, 'clan_id' => $clan->id, 'left_at' => null]);

    $this->actingAs($viewerUser)
        ->get("/players/{$targetPlayer->slug}")
        ->assertStatus(200);
});

// ─── public tier ─────────────────────────────────────────────────────────────

it('public tier: guest returns 200', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public']), 'privacy')
        ->create();

    $this->get("/players/{$player->slug}")->assertStatus(200);
});

it('public tier: authenticated user returns 200', function (): void {
    $targetPlayer = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public']), 'privacy')
        ->create();

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/players/{$targetPlayer->slug}")
        ->assertStatus(200);
});

it('public tier: own profile returns 200', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public']), 'privacy')
        ->create();

    $this->actingAs($user)
        ->get("/players/{$player->slug}")
        ->assertStatus(200);
});

// ─── per-section: show_discord_tag=false ─────────────────────────────────────

it('per-section: show_discord_tag=false omits discordTag from player prop', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state([
            'show_to' => 'public',
            'show_discord_tag' => false,
        ]), 'privacy')
        ->create();

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/players/{$player->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Players/Show')
                ->missing('player.discordTag')
        );
});

it('per-section: show_discord_tag=true includes discordTag in player prop', function (): void {
    $user = User::factory()->create(['username' => 'warrior_one']);
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state([
            'show_to' => 'public',
            'show_discord_tag' => true,
        ]), 'privacy')
        ->create();

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/players/{$player->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Players/Show')
                ->has('player.discordTag')
        );
});

// ─── per-section: show_clan_history=false ────────────────────────────────────

it('per-section: show_clan_history=false omits clanHistory from player prop', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state([
            'show_to' => 'public',
            'show_clan_history' => false,
        ]), 'privacy')
        ->create();

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/players/{$player->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Players/Show')
                ->missing('player.clanHistory')
        );
});

// ─── own profile: all sections included regardless of flags ──────────────────

it('own profile: all sections included regardless of privacy flags', function (): void {
    $user = User::factory()->create(['username' => 'self_viewer']);
    $player = Player::factory()->for($user)
        ->has(PlayerPrivacy::factory()->state([
            'show_to' => 'private',
            'show_discord_tag' => false,
            'show_clan_history' => false,
            'show_match_history' => false,
            'show_stats' => false,
        ]), 'privacy')
        ->create();

    $this->actingAs($user)
        ->get("/players/{$player->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Players/Show')
                ->has('player.discordTag')
                ->has('player.clanHistory')
                ->where('player.isOwnProfile', true)
        );
});
