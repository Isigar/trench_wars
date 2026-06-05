<?php

declare(strict_types=1);

/*
| Self-service profile-privacy editor (D-018, REQ-goal-public-profiles).
|
| Closes the HIGH reachability gap where the only write surface for player
| privacy tiers was the admin-gated Filament PlayerResource — a regular member
| could never change their own show_to tier or section toggles. These tests
| assert the member-facing /account/privacy GET+POST exist, persist only the
| caller's own row, validate the tier enum, and require auth.
*/

use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the privacy editor with the user\'s current settings', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create();
    PlayerPrivacy::factory()->for($player)->create([
        'show_to' => 'clan',
        'show_stats' => false,
    ]);

    $this->actingAs($user)
        ->get('/account/privacy')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Account/Privacy', false)
                ->where('privacy.show_to', 'clan')
                ->where('privacy.show_stats', false)
                ->where('privacy.show_clan_history', true)
                ->has('tiers', 4)
                ->has('sections', 5)
        );
});

it('lets a member update their own global tier and section toggles', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create();
    $privacy = PlayerPrivacy::factory()->for($player)->create([
        'show_to' => 'community',
        'show_real_name' => false,
        'show_match_history' => true,
    ]);

    $this->actingAs($user)
        ->post('/account/privacy', [
            'show_to' => 'public',
            'show_real_name' => true,
            'show_discord_tag' => true,
            'show_clan_history' => true,
            'show_match_history' => false,
            'show_stats' => true,
        ])
        ->assertRedirect();

    $privacy->refresh();
    expect($privacy->show_to)->toBe('public')
        ->and($privacy->show_real_name)->toBeTrue()
        ->and($privacy->show_match_history)->toBeFalse();
});

it('updates ONLY the authenticated user\'s privacy row, never another player\'s', function (): void {
    $me = User::factory()->create();
    $myPlayer = Player::factory()->for($me)->create();
    PlayerPrivacy::factory()->for($myPlayer)->create(['show_to' => 'community']);

    $other = User::factory()->create();
    $otherPlayer = Player::factory()->for($other)->create();
    $otherPrivacy = PlayerPrivacy::factory()->for($otherPlayer)->create(['show_to' => 'community']);

    $this->actingAs($me)
        ->post('/account/privacy', [
            'show_to' => 'private',
            'show_real_name' => false,
            'show_discord_tag' => false,
            'show_clan_history' => false,
            'show_match_history' => false,
            'show_stats' => false,
        ])
        ->assertRedirect();

    // The other player's row is untouched.
    expect($otherPrivacy->refresh()->show_to)->toBe('community');
    expect($myPlayer->privacy->refresh()->show_to)->toBe('private');
});

it('rejects an invalid global tier', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create();
    PlayerPrivacy::factory()->for($player)->create();

    $this->actingAs($user)
        ->post('/account/privacy', [
            'show_to' => 'everyone',
            'show_real_name' => true,
            'show_discord_tag' => true,
            'show_clan_history' => true,
            'show_match_history' => true,
            'show_stats' => true,
        ])
        ->assertSessionHasErrors('show_to');
});

it('requires authentication', function (): void {
    $this->get('/account/privacy')->assertRedirect();
    $this->post('/account/privacy', [])->assertRedirect();
});
