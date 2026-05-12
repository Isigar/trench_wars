<?php

declare(strict_types=1);

/*
| Wave 2 implementation — replaces Wave 0 RED stub.
| Covers REQ-goal-public-profiles: PublicPlayerData withholds sections using
| Optional::create() so withheld fields are ABSENT from toArray() output (not null).
| "absent ≠ null" rule from RESEARCH.md Security Domain.
| See .planning/phases/02-clans-tags/02-VALIDATION.md Per-Task Verification Map.
*/

use App\Data\PublicPlayerData;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --------------------------------------------------------------------------
// Helper — create a player with full privacy row and optional viewer
// --------------------------------------------------------------------------

/**
 * @param  array<string, mixed>  $privacyState
 */
function makePlayerWithPrivacy(array $privacyState = [], ?User $viewerUser = null): array
{
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $privacy = PlayerPrivacy::factory()->create(
        array_merge(['player_id' => $player->id], $privacyState)
    );
    $player->setRelation('privacy', $privacy);
    $player->setRelation('user', $user);

    return [$player, $user, $viewerUser];
}

// --------------------------------------------------------------------------
// absent-vs-null: core security property
// --------------------------------------------------------------------------

it('omits discordTag from toArray when show_discord_tag=false', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy(['show_discord_tag' => false], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    expect(array_key_exists('discordTag', $arr))->toBeFalse();
});

it('includes discordTag in toArray when show_discord_tag=true', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy(['show_discord_tag' => true], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    expect(array_key_exists('discordTag', $arr))->toBeTrue();
});

it('omits clanHistory when show_clan_history=false', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy(['show_clan_history' => false], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    expect(array_key_exists('clanHistory', $arr))->toBeFalse();
});

it('omits matchHistory when show_match_history=false', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy(['show_match_history' => false], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    expect(array_key_exists('matchHistory', $arr))->toBeFalse();
});

it('omits stats when show_stats=false', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy(['show_stats' => false], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    expect(array_key_exists('stats', $arr))->toBeFalse();
});

// --------------------------------------------------------------------------
// Own profile bypass: all sections always present regardless of flags
// --------------------------------------------------------------------------

it('includes all sections on own profile regardless of flags', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $privacy = PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_discord_tag' => false,
        'show_clan_history' => false,
        'show_match_history' => false,
        'show_stats' => false,
        'show_real_name' => false,
    ]);
    $player->setRelation('privacy', $privacy);
    $player->setRelation('user', $user);

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $user, $gate);
    $arr = $dto->toArray();

    // Own profile viewer sees everything
    expect($arr['isOwnProfile'])->toBeTrue();
    expect(array_key_exists('discordTag', $arr))->toBeTrue();
    expect(array_key_exists('clanHistory', $arr))->toBeTrue();
    expect(array_key_exists('matchHistory', $arr))->toBeTrue();
    expect(array_key_exists('stats', $arr))->toBeTrue();
});

// --------------------------------------------------------------------------
// Bio serialization
// --------------------------------------------------------------------------

it('serializes bio as Record<string,string> via translatable column', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->create([
        'user_id' => $user->id,
        'bio' => ['en' => 'Hello world', 'cs' => 'Ahoj svete'],
    ]);
    $privacy = PlayerPrivacy::factory()->create(['player_id' => $player->id]);
    $player->setRelation('privacy', $privacy);
    $player->setRelation('user', $user);

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $user, $gate);
    $arr = $dto->toArray();

    expect($arr['bio'])->toBeArray();
    expect($arr['bio']['en'])->toBe('Hello world');
    expect($arr['bio']['cs'])->toBe('Ahoj svete');
});

// --------------------------------------------------------------------------
// isOwnProfile field
// --------------------------------------------------------------------------

it('isOwnProfile=true when viewer is the player.user_id', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    $privacy = PlayerPrivacy::factory()->create(['player_id' => $player->id]);
    $player->setRelation('privacy', $privacy);
    $player->setRelation('user', $user);

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $user, $gate);

    expect($dto->isOwnProfile)->toBeTrue();
});

it('isOwnProfile=false when viewer is a different user', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy([], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);

    expect($dto->isOwnProfile)->toBeFalse();
});

// --------------------------------------------------------------------------
// currentClan handling
// --------------------------------------------------------------------------

it('currentClan is null when player has no active membership', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy([], User::factory()->create());

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    // currentClan key IS present in the array, just null (not Optional)
    expect(array_key_exists('currentClan', $arr))->toBeTrue();
    expect($arr['currentClan'])->toBeNull();
});

// --------------------------------------------------------------------------
// countryCode — not gated (always visible per UI-SPEC)
// --------------------------------------------------------------------------

it('countryCode is included by default — no flag controls it (UI-SPEC)', function (): void {
    [$player,, $viewer] = makePlayerWithPrivacy([], User::factory()->create());

    // Force a known country_code
    $player->country_code = 'CZ';

    $gate = new PlayerPrivacyGate;
    $dto = PublicPlayerData::fromPlayer($player, $viewer, $gate);
    $arr = $dto->toArray();

    expect(array_key_exists('countryCode', $arr))->toBeTrue();
    expect($arr['countryCode'])->toBe('CZ');
});
