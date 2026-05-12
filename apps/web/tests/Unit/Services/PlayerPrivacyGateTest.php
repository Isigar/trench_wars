<?php

declare(strict_types=1);

/*
| Wave 2 implementation — replaces Wave 0 RED stub.
| Covers REQ-goal-public-profiles: PlayerPrivacyGate applies all 4 show_to tiers
| and per-section flags; own-profile bypass always grants access.
| See .planning/phases/02-clans-tags/02-VALIDATION.md Per-Task Verification Map.
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --------------------------------------------------------------------------
// `private` tier — only own profile passes
// --------------------------------------------------------------------------

it('private tier: passesTier returns false for guest', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, null))->toBeFalse();
});

it('private tier: passesTier returns false for authenticated community user', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewer))->toBeFalse();
});

it('private tier: passesTier returns false for same-clan member', function (): void {
    $clan = Clan::factory()->create();

    $playerUser = User::factory()->create();
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create(['user_id' => $playerUser->id]);

    $viewerUser = User::factory()->create();
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $playerUser->id]);
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $viewerUser->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewerUser))->toBeFalse();
});

it('private tier: passesTier returns true for own profile (bypass)', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create(['user_id' => $user->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $user))->toBeTrue();
});

// --------------------------------------------------------------------------
// `community` tier — any authenticated user passes (Pitfall 4)
// --------------------------------------------------------------------------

it('community tier: passesTier returns false for guest', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'community']), 'privacy')
        ->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, null))->toBeFalse();
});

it('community tier: passesTier returns true for any authenticated user (no role required)', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'community']), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewer))->toBeTrue();
});

// --------------------------------------------------------------------------
// `clan` tier — same active clan required
// --------------------------------------------------------------------------

it('clan tier: passesTier returns false for guest', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, null))->toBeFalse();
});

it('clan tier: passesTier returns false for auth user with no membership', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewer))->toBeFalse();
});

it('clan tier: passesTier returns false for auth user in different clan', function (): void {
    $clan1 = Clan::factory()->create();
    $clan2 = Clan::factory()->create();

    $playerUser = User::factory()->create();
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create(['user_id' => $playerUser->id]);

    $viewerUser = User::factory()->create();
    ClanMembership::factory()->create(['clan_id' => $clan1->id, 'user_id' => $playerUser->id]);
    ClanMembership::factory()->create(['clan_id' => $clan2->id, 'user_id' => $viewerUser->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewerUser))->toBeFalse();
});

it('clan tier: passesTier returns true for auth user in same clan as player', function (): void {
    $clan = Clan::factory()->create();

    $playerUser = User::factory()->create();
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create(['user_id' => $playerUser->id]);

    $viewerUser = User::factory()->create();
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $playerUser->id]);
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $viewerUser->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewerUser))->toBeTrue();
});

it('clan tier: passesTier returns true for own profile (bypass)', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'clan']), 'privacy')
        ->create(['user_id' => $user->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $user))->toBeTrue();
});

// --------------------------------------------------------------------------
// `public` tier — everyone passes
// --------------------------------------------------------------------------

it('public tier: passesTier returns true for guest', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public']), 'privacy')
        ->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, null))->toBeTrue();
});

it('public tier: passesTier returns true for authenticated user', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'public']), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->passesTier($player, $viewer))->toBeTrue();
});

// --------------------------------------------------------------------------
// Per-section flags
// --------------------------------------------------------------------------

it('allowsSection: show_discord_tag=false blocks foreign viewer', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_discord_tag' => false]), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->allowsSection($player, $viewer, 'show_discord_tag'))->toBeFalse();
});

it('allowsSection: show_clan_history=false blocks foreign viewer', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_clan_history' => false]), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->allowsSection($player, $viewer, 'show_clan_history'))->toBeFalse();
});

it('allowsSection: show_stats=false blocks foreign viewer', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_stats' => false]), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->allowsSection($player, $viewer, 'show_stats'))->toBeFalse();
});

it('allowsSection: own profile always passes regardless of flag value', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state([
            'show_discord_tag'   => false,
            'show_clan_history'  => false,
            'show_match_history' => false,
            'show_stats'         => false,
            'show_real_name'     => false,
        ]), 'privacy')
        ->create(['user_id' => $user->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->allowsSection($player, $user, 'show_discord_tag'))->toBeTrue();
    expect($gate->allowsSection($player, $user, 'show_clan_history'))->toBeTrue();
    expect($gate->allowsSection($player, $user, 'show_match_history'))->toBeTrue();
    expect($gate->allowsSection($player, $user, 'show_stats'))->toBeTrue();
    expect($gate->allowsSection($player, $user, 'show_real_name'))->toBeTrue();
});

it('allowsSection: returns false defensively when no privacy row exists', function (): void {
    $player = Player::factory()->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->allowsSection($player, $viewer, 'show_discord_tag'))->toBeFalse();
});

it('allowsSection: throws InvalidArgumentException for unknown flag', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory(), 'privacy')
        ->create();
    $viewer = User::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect(fn () => $gate->allowsSection($player, $viewer, 'show_unknown_field'))
        ->toThrow(\InvalidArgumentException::class);
});

// --------------------------------------------------------------------------
// isOwnProfile helper
// --------------------------------------------------------------------------

it('isOwnProfile: returns true when viewer is the player owning user', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->isOwnProfile($user, $player))->toBeTrue();
});

it('isOwnProfile: returns false for guest (null viewer)', function (): void {
    $player = Player::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->isOwnProfile(null, $player))->toBeFalse();
});

it('isOwnProfile: returns false for different authenticated user', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->create();

    $gate = new PlayerPrivacyGate;

    expect($gate->isOwnProfile($user, $player))->toBeFalse();
});

// --------------------------------------------------------------------------
// viewerInSameClan edge cases
// --------------------------------------------------------------------------

it('viewerInSameClan: returns false when left_at is set (inactive membership)', function (): void {
    $clan = Clan::factory()->create();

    $playerUser = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $playerUser->id]);

    $viewerUser = User::factory()->create();
    // Viewer has left the clan
    ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $viewerUser->id,
        'left_at' => now(),
    ]);
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $playerUser->id]);

    $gate = new PlayerPrivacyGate;

    expect($gate->viewerInSameClan($viewerUser, $player))->toBeFalse();
});
