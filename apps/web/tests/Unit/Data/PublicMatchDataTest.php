<?php

declare(strict_types=1);

/*
| Wave 4 implementation — replaces Wave 0 RED stub from plan 04-01.
| Covers REQ-goal-match-workflows: PublicMatchData strips admin-only fields
| (organiser_user_id, server_address) and PublicMatchOccupantData applies
| PlayerPrivacyGate server-side per Pattern 7 — displayName/playerSlug withheld
| when the gate denies; clanTag always shown (D-008: clan tags public).
| Threat refs: T-04-07-01 (privacy bypass), T-04-07-03 (admin-field leak).
| See .planning/phases/04-matches-manual/04-07-PLAN.md task 3.
*/

use App\Data\PublicMatchData;
use App\Data\PublicMatchOccupantData;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --------------------------------------------------------------------------
// PublicMatchData admin-field stripping (T-04-07-03).
// --------------------------------------------------------------------------

it('strips organiser_user_id from PublicMatchData', function (): void {
    $match = GameMatch::factory()->create();

    $arr = PublicMatchData::fromModel($match->fresh())->toArray();

    expect(array_key_exists('organiser_user_id', $arr))->toBeFalse();
});

it('strips server_address from PublicMatchData', function (): void {
    $match = GameMatch::factory()->create(['server_address' => '203.0.113.7:27015']);

    $arr = PublicMatchData::fromModel($match->fresh())->toArray();

    expect(array_key_exists('server_address', $arr))->toBeFalse();
});

it('preserves title translatable JSONB on PublicMatchData', function (): void {
    $match = GameMatch::factory()->create();
    $match->setTranslation('title', 'en', 'Public Skirmish');
    $match->save();

    $dto = PublicMatchData::fromModel($match->fresh());

    expect($dto->title)->toBe(['en' => 'Public Skirmish']);
});

// --------------------------------------------------------------------------
// PublicMatchOccupantData::forEmptySlot — no occupant.
// --------------------------------------------------------------------------

it('PublicMatchOccupantData::forEmptySlot returns null for displayName/playerSlug/clanTag and isViewer=false', function (): void {
    $slot = MatchSlot::factory()->create(['occupant_user_id' => null]);

    $dto = PublicMatchOccupantData::forEmptySlot($slot);

    expect($dto->slotId)->toBe($slot->id)
        ->and($dto->displayName)->toBeNull()
        ->and($dto->playerSlug)->toBeNull()
        ->and($dto->clanTag)->toBeNull()
        ->and($dto->clanSlug)->toBeNull()
        ->and($dto->isViewer)->toBeFalse();
});

// --------------------------------------------------------------------------
// PublicMatchOccupantData::fromMatchSlot privacy strip path (T-04-07-01).
// show_match_history=false → displayName + playerSlug withheld.
// --------------------------------------------------------------------------

it('PublicMatchOccupantData::fromMatchSlot returns displayName=null when privacy.show_match_history=false', function (): void {
    $occupantUser = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $occupantUser->id]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'public',
        'show_match_history' => false,
    ]);

    $slot = MatchSlot::factory()->create(['occupant_user_id' => $occupantUser->id]);
    $viewer = User::factory()->create();

    $dto = PublicMatchOccupantData::fromMatchSlot($slot, $viewer, app(PlayerPrivacyGate::class));

    expect($dto->displayName)->toBeNull()
        ->and($dto->playerSlug)->toBeNull();
});

// --------------------------------------------------------------------------
// D-008: clan tag stays present even when privacy gate withholds name+slug.
// --------------------------------------------------------------------------

it('PublicMatchOccupantData::fromMatchSlot returns clanTag !== null even when displayName is null (D-008)', function (): void {
    $occupantUser = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $occupantUser->id]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'public',
        'show_match_history' => false,
    ]);

    $clan = Clan::factory()->create(['tag' => 'EU', 'slug' => 'eu-team']);
    ClanMembership::factory()->create([
        'user_id' => $occupantUser->id,
        'clan_id' => $clan->id,
        'left_at' => null,
    ]);

    $slot = MatchSlot::factory()->create(['occupant_user_id' => $occupantUser->id]);
    $viewer = User::factory()->create();

    $dto = PublicMatchOccupantData::fromMatchSlot($slot, $viewer, app(PlayerPrivacyGate::class));

    expect($dto->displayName)->toBeNull()  // gated off
        ->and($dto->clanTag)->toBe('EU')   // always public per D-008
        ->and($dto->clanSlug)->toBe('eu-team');
});

// --------------------------------------------------------------------------
// Happy path — privacy allows: displayName + slug emitted.
// --------------------------------------------------------------------------

it('PublicMatchOccupantData::fromMatchSlot emits displayName when privacy allows', function (): void {
    $occupantUser = User::factory()->create(['username' => 'alice']);
    $player = Player::factory()->create([
        'user_id' => $occupantUser->id,
        'display_name' => 'Alice the Tactician',
        'slug' => 'alice-tactician',
    ]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'public',
        'show_match_history' => true,
    ]);

    $slot = MatchSlot::factory()->create(['occupant_user_id' => $occupantUser->id]);
    $viewer = User::factory()->create();

    $dto = PublicMatchOccupantData::fromMatchSlot($slot, $viewer, app(PlayerPrivacyGate::class));

    expect($dto->displayName)->toBe('Alice the Tactician')
        ->and($dto->playerSlug)->toBe('alice-tactician');
});

// --------------------------------------------------------------------------
// isViewer flag — viewer === occupant.
// --------------------------------------------------------------------------

it('PublicMatchOccupantData::fromMatchSlot sets isViewer=true when viewer === occupant', function (): void {
    $occupantUser = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $occupantUser->id]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'private',         // even private — owner sees self
        'show_match_history' => false,
    ]);

    $slot = MatchSlot::factory()->create(['occupant_user_id' => $occupantUser->id]);

    // Viewer IS the occupant — own-profile bypass should set isViewer=true and
    // surface displayName via the gate's isOwnProfile() bypass.
    $dto = PublicMatchOccupantData::fromMatchSlot($slot, $occupantUser, app(PlayerPrivacyGate::class));

    expect($dto->isViewer)->toBeTrue()
        ->and($dto->displayName)->not->toBeNull();  // own-profile bypass
});

it('PublicMatchOccupantData::fromMatchSlot sets isViewer=false when viewer is different user', function (): void {
    $occupantUser = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $occupantUser->id]);
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'public',
        'show_match_history' => true,
    ]);

    $slot = MatchSlot::factory()->create(['occupant_user_id' => $occupantUser->id]);
    $viewer = User::factory()->create();

    $dto = PublicMatchOccupantData::fromMatchSlot($slot, $viewer, app(PlayerPrivacyGate::class));

    expect($dto->isViewer)->toBeFalse();
});

// --------------------------------------------------------------------------
// Slot meta passthrough — slotId / gameRoleId / slotIndex preserved.
// --------------------------------------------------------------------------

it('PublicMatchOccupantData::fromMatchSlot preserves slot metadata from MatchSlot model', function (): void {
    $role = GameRole::factory()->create();
    $occupantUser = User::factory()->create();
    Player::factory()->create(['user_id' => $occupantUser->id]);

    $slot = MatchSlot::factory()->create([
        'occupant_user_id' => $occupantUser->id,
        'game_role_id' => $role->id,
        'slot_index' => 7,
    ]);

    $dto = PublicMatchOccupantData::fromMatchSlot($slot, null, app(PlayerPrivacyGate::class));

    expect($dto->slotId)->toBe($slot->id)
        ->and($dto->gameRoleId)->toBe($role->id)
        ->and($dto->slotIndex)->toBe(7);
});
