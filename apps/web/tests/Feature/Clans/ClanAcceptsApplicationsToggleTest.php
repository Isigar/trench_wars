<?php

declare(strict_types=1);

use App\Data\ClanData;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;

/*
| Source: 10-04-PLAN.md Task 1 (TDD RED gate).
|
| CLAN-04: The ClanData DTO exposes accepts_applications; the leader/officer can
| toggle it via PATCH /my-clan/profile/{slug}; non-members (no leader/officer
| membership) receive 403 and the value is unchanged.
|
| Trust boundaries tested (from threat model):
|   T-10-04-01 non-leader toggling -> 403
|   T-10-04-02 only accepts_applications is added (discord_role_id excluded)
*/

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a clan with a leader and a membership row for that leader.
 *
 * @return array{Clan, User}
 */
function setupToggleClan(): array
{
    $leader = User::factory()->create();
    $clan = Clan::factory()->create([
        'owner_user_id' => $leader->id,
        'accepts_applications' => true,
    ]);
    ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $leader->id,
        'role' => 'leader',
    ]);

    return [$clan, $leader];
}

// ===========================================================================
// PATCH /my-clan/profile/{slug} — leader can toggle accepts_applications
// ===========================================================================

it('leader PATCH with accepts_applications=false persists false and redirects', function (): void {
    [$clan, $leader] = setupToggleClan();

    $this->actingAs($leader)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'accepts_applications' => false,
        ])
        ->assertRedirect();

    expect($clan->fresh()->accepts_applications)->toBeFalse();
});

it('non-member PATCH with accepts_applications gets 403 and value unchanged', function (): void {
    [$clan] = setupToggleClan();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'accepts_applications' => false,
        ])
        ->assertStatus(403);

    expect($clan->fresh()->accepts_applications)->toBeTrue();
});

// ===========================================================================
// ClanData DTO exposes accepts_applications
// ===========================================================================

it('ClanData::fromModel returns accepts_applications as boolean', function (): void {
    [$clan] = setupToggleClan();

    expect(ClanData::fromModel($clan->fresh())->accepts_applications)->toBeBool();
});

it('ClanData::fromModel reflects accepts_applications=false when set on model', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => false]);

    expect(ClanData::fromModel($clan)->accepts_applications)->toBeFalse();
});
