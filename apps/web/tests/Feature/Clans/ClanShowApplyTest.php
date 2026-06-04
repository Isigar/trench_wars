<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Source: 10-06-PLAN.md Task 1 — eligibility-matrix props on ClanShowController.
|
| Tests the three viewer-state props passed to Inertia:
|   - acceptsApplications  — reflects clan.accepts_applications
|   - viewerIsActiveMember — true when the authed user has an active (left_at IS NULL) clan membership
|   - viewerHasPendingApplication — true when a pending application for THIS clan exists for the authed user
|
| Eligibility matrix:
|   guest            → props default to clan-reflecting + false/false
|   eligible authed  → accepts=true, member=false, pending=false
|   active member    → viewerIsActiveMember=true
|   pending app      → viewerHasPendingApplication=true
|   declined app     → viewerHasPendingApplication=false (only pending-status triggers flag)
|   not accepting    → acceptsApplications=false
*/

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create an active clan with accepts_applications toggle.
 */
function makeClanForApply(bool $acceptsApplications = true): Clan
{
    return Clan::factory()->create([
        'status' => 'active',
        'accepts_applications' => $acceptsApplications,
    ]);
}

// ---------------------------------------------------------------------------
// Guest visitor
// ---------------------------------------------------------------------------

it('guest sees acceptsApplications=true, viewerIsActiveMember=false, viewerHasPendingApplication=false for open clan', function (): void {
    $clan = makeClanForApply(true);

    $this->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('acceptsApplications', true)
                ->where('viewerIsActiveMember', false)
                ->where('viewerHasPendingApplication', false)
        );
});

it('guest sees acceptsApplications=false for closed clan', function (): void {
    $clan = makeClanForApply(false);

    $this->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('acceptsApplications', false)
                ->where('viewerIsActiveMember', false)
                ->where('viewerHasPendingApplication', false)
        );
});

// ---------------------------------------------------------------------------
// Eligible authenticated viewer (no clan membership, no prior application)
// ---------------------------------------------------------------------------

it('eligible authed viewer: accepts=true, member=false, pending=false', function (): void {
    $clan = makeClanForApply(true);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('acceptsApplications', true)
                ->where('viewerIsActiveMember', false)
                ->where('viewerHasPendingApplication', false)
        );
});

// ---------------------------------------------------------------------------
// viewerIsActiveMember = true (active membership in ANY clan)
// ---------------------------------------------------------------------------

it('authed viewer who is an active member of any clan: viewerIsActiveMember=true', function (): void {
    $clan = makeClanForApply(true);
    $viewer = User::factory()->create();

    // Viewer has an active membership in a DIFFERENT clan.
    $otherClan = Clan::factory()->create(['status' => 'active']);
    ClanMembership::factory()->create([
        'user_id' => $viewer->id,
        'clan_id' => $otherClan->id,
        'role' => 'member',
        'left_at' => null,
    ]);

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerIsActiveMember', true)
                ->where('viewerHasPendingApplication', false)
        );
});

it('authed viewer whose membership has left_at set is NOT counted as active member', function (): void {
    $clan = makeClanForApply(true);
    $viewer = User::factory()->create();

    // Viewer has a historical (left) membership.
    ClanMembership::factory()->create([
        'user_id' => $viewer->id,
        'clan_id' => $clan->id,
        'role' => 'member',
        'left_at' => now()->subDay(),
    ]);

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerIsActiveMember', false)
        );
});

// ---------------------------------------------------------------------------
// viewerHasPendingApplication = true
// ---------------------------------------------------------------------------

it('authed viewer with a pending application to THIS clan: viewerHasPendingApplication=true', function (): void {
    $clan = makeClanForApply(true);
    $viewer = User::factory()->create();

    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $viewer->id,
        'status' => 'pending',
    ]);

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerHasPendingApplication', true)
                ->where('viewerIsActiveMember', false)
        );
});

// ---------------------------------------------------------------------------
// Declined application does NOT set viewerHasPendingApplication (pending-only guard)
// ---------------------------------------------------------------------------

it('authed viewer whose only application to this clan was declined: viewerHasPendingApplication=false', function (): void {
    $clan = makeClanForApply(true);
    $viewer = User::factory()->create();

    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $viewer->id,
        'status' => 'declined',
        'decided_at' => now()->subHour(),
        'decided_by' => $clan->owner_user_id,
    ]);

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerHasPendingApplication', false)
                ->where('viewerIsActiveMember', false)
        );
});

// ---------------------------------------------------------------------------
// Cancelled application does NOT set viewerHasPendingApplication
// ---------------------------------------------------------------------------

it('authed viewer with a cancelled application: viewerHasPendingApplication=false', function (): void {
    $clan = makeClanForApply(true);
    $viewer = User::factory()->create();

    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $viewer->id,
        'status' => 'cancelled',
        'decided_at' => now()->subHour(),
    ]);

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerHasPendingApplication', false)
        );
});

// ---------------------------------------------------------------------------
// Pending application to a DIFFERENT clan does NOT set the flag for THIS clan
// ---------------------------------------------------------------------------

it('authed viewer with pending application to a different clan: viewerHasPendingApplication=false for target clan', function (): void {
    $clan = makeClanForApply(true);
    $otherClan = Clan::factory()->create(['status' => 'active']);
    $viewer = User::factory()->create();

    // Pending application to a different clan.
    ClanApplication::factory()->create([
        'clan_id' => $otherClan->id,
        'applicant_user_id' => $viewer->id,
        'status' => 'pending',
    ]);

    $this->actingAs($viewer)
        ->get(route('clans.show', $clan->slug))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerHasPendingApplication', false)
        );
});
