<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanInvite;
use App\Models\ClanMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 02-10-PLAN.md Task 2 — replaces Wave 0 stub.
|
| Covers REQ-tenancy-multi-clan: ClanInvite state machine transitions
| pending -> accepted | declined | revoked
|
| NOTE on automatic expiry: P2 has no scheduler wired.
| The expires_at column is stored but no background job transitions invites
| to 'expired'. Invites with a future expires_at remain 'pending' in DB.
| This is documented and accepted (RESEARCH.md Pattern 6 / 02-10-PLAN.md threat model).
|
| NOTE on duplicate-pending enforcement: there is no DB-level unique index on
| (clan_id, invited_user_id, status='pending'). Uniqueness is enforced at the
| service layer (ClanInviteService::sendInvite). Tests verify the service gate.
*/

// ---------------------------------------------------------------------------
// Shared setup
// ---------------------------------------------------------------------------

/**
 * Create a clan with a leader, officer, and regular member.
 * Returns [$clan, $leader, $officer, $member].
 *
 * @return array{Clan, User, User, User, ClanMembership, ClanMembership, ClanMembership}
 */
function setupInviteClan(): array
{
    $leader = User::factory()->create();
    $officer = User::factory()->create();
    $member = User::factory()->create();

    $clan = Clan::factory()->create(['owner_user_id' => $leader->id]);

    $leaderMembership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $leader->id,
        'role' => 'leader',
    ]);
    $officerMembership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $officer->id,
        'role' => 'officer',
    ]);
    $memberMembership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    return [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership];
}

// ===========================================================================
// POST /my-clan/invites — send invite
// ===========================================================================

it('Leader sends invite to user with no active membership: invite row created with status=pending', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $this->actingAs($leader)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
            'message' => 'Welcome to the clan!',
        ])
        ->assertRedirect();

    $invite = ClanInvite::where('clan_id', $clan->id)
        ->where('invited_user_id', $invitee->id)
        ->first();

    expect($invite)->not->toBeNull();
    expect($invite->status)->toBe('pending');
    expect($invite->inviting_user_id)->toBe($leader->id);
    expect($invite->message)->toBe('Welcome to the clan!');
});

it('Leader sends invite: activity_log entry is written', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $this->actingAs($leader)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
        ]);

    $invite = ClanInvite::where('clan_id', $clan->id)
        ->where('invited_user_id', $invitee->id)
        ->firstOrFail();

    $activity = Activity::where('subject_type', ClanInvite::class)
        ->where('subject_id', $invite->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($leader->id);
});

it('Leader cannot invite user with an active membership (422)', function (): void {
    [$clan, $leader] = setupInviteClan();

    // Create a second clan and give the invitee an active membership there.
    $otherClan = Clan::factory()->create();
    $invitee = User::factory()->create();
    ClanMembership::factory()->create([
        'clan_id' => $otherClan->id,
        'user_id' => $invitee->id,
        'role' => 'member',
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
        ])
        ->assertSessionHasErrors(['invited_user_id']);

    expect(ClanInvite::count())->toBe(0);
});

it('Leader cannot send duplicate pending invite to same invitee (422)', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    // First invite — should succeed.
    $this->actingAs($leader)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
        ])
        ->assertRedirect();

    expect(ClanInvite::count())->toBe(1);

    // Second invite to same user — should fail (duplicate pending).
    $this->actingAs($leader)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
        ])
        ->assertSessionHasErrors(['invited_user_id']);

    expect(ClanInvite::count())->toBe(1);
});

it('Officer can send an invite', function (): void {
    [$clan, $leader, $officer] = setupInviteClan();
    $invitee = User::factory()->create();

    $this->actingAs($officer)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
        ])
        ->assertRedirect();

    expect(ClanInvite::where('clan_id', $clan->id)->count())->toBe(1);
});

it('Member cannot send an invite (403)', function (): void {
    [$clan, $leader, $officer, $member] = setupInviteClan();
    $invitee = User::factory()->create();

    $this->actingAs($member)
        ->post(route('my-clan.invites.store'), [
            'invited_user_id' => $invitee->id,
        ])
        ->assertStatus(403);

    expect(ClanInvite::count())->toBe(0);
});

it('Guest POST /my-clan/invites redirects to login', function (): void {
    $invitee = User::factory()->create();

    $this->post(route('my-clan.invites.store'), [
        'invited_user_id' => $invitee->id,
    ])->assertRedirect('/auth/discord/redirect');
});

// ===========================================================================
// POST /invites/{invite}/accept — invitee accepts
// ===========================================================================

it('Invitee accepts pending invite: status=accepted, ClanMembership created with role=recruit', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($invitee)
        ->post(route('invites.accept', $invite->id))
        ->assertRedirect(route('my-clan.index'));

    expect($invite->fresh()->status)->toBe('accepted');
    expect($invite->fresh()->decided_at)->not->toBeNull();

    $membership = ClanMembership::where('user_id', $invitee->id)
        ->where('clan_id', $clan->id)
        ->whereNull('left_at')
        ->first();

    expect($membership)->not->toBeNull();
    expect($membership->role)->toBe('recruit');
    expect($membership->invited_by)->toBe($leader->id);
});

it('Acceptance creates membership atomically (D-009 index rolls back invite on second concurrent accept)', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    // First invite: pending.
    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    // Give invitee an active membership elsewhere BEFORE accepting to simulate
    // a race condition where they joined another clan between receiving the invite
    // and clicking accept.
    $otherClan = Clan::factory()->create();
    ClanMembership::factory()->create([
        'clan_id' => $otherClan->id,
        'user_id' => $invitee->id,
        'role' => 'member',
    ]);

    $this->actingAs($invitee)
        ->post(route('invites.accept', $invite->id))
        ->assertSessionHasErrors(['invite']);

    // Invite must remain pending — the transaction rolled back.
    expect($invite->fresh()->status)->toBe('pending');

    // No duplicate membership should have been created in $clan.
    expect(ClanMembership::where('user_id', $invitee->id)->where('clan_id', $clan->id)->count())->toBe(0);
});

it('Non-invitee cannot accept the invite (403)', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();
    $otherUser = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($otherUser)
        ->post(route('invites.accept', $invite->id))
        ->assertStatus(403);

    expect($invite->fresh()->status)->toBe('pending');
    expect(ClanMembership::where('user_id', $invitee->id)->count())->toBe(0);
});

it('Already-accepted invite cannot be re-accepted (422)', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'accepted',
        'decided_at' => now(),
    ]);

    $this->actingAs($invitee)
        ->post(route('invites.accept', $invite->id))
        ->assertSessionHasErrors(['invite']);
});

// ===========================================================================
// POST /invites/{invite}/decline — invitee declines
// ===========================================================================

it('Invitee declines pending invite: status=declined, no membership created', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($invitee)
        ->post(route('invites.decline', $invite->id))
        ->assertRedirect();

    expect($invite->fresh()->status)->toBe('declined');
    expect($invite->fresh()->decided_at)->not->toBeNull();
    expect(ClanMembership::where('user_id', $invitee->id)->count())->toBe(0);
});

// ===========================================================================
// DELETE /my-clan/invites/{invite} — Leader/Officer revokes
// ===========================================================================

it('Leader revokes their clan\'s pending invite: status=revoked', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($leader)
        ->delete(route('my-clan.invites.destroy', $invite->id))
        ->assertRedirect();

    expect($invite->fresh()->status)->toBe('revoked');
    expect($invite->fresh()->decided_at)->not->toBeNull();
});

it('Officer can revoke a pending invite', function (): void {
    [$clan, $leader, $officer] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($officer)
        ->delete(route('my-clan.invites.destroy', $invite->id))
        ->assertRedirect();

    expect($invite->fresh()->status)->toBe('revoked');
});

it('Leader cannot revoke another clan\'s invite (403)', function (): void {
    [$clan, $leader] = setupInviteClan();

    // Another clan with its own invite.
    $otherLeader = User::factory()->create();
    $otherClan = Clan::factory()->create(['owner_user_id' => $otherLeader->id]);
    ClanMembership::factory()->create([
        'clan_id' => $otherClan->id,
        'user_id' => $otherLeader->id,
        'role' => 'leader',
    ]);
    $otherInvitee = User::factory()->create();
    $otherInvite = ClanInvite::factory()->create([
        'clan_id' => $otherClan->id,
        'inviting_user_id' => $otherLeader->id,
        'invited_user_id' => $otherInvitee->id,
        'status' => 'pending',
    ]);

    // $leader from first clan attempts to revoke the other clan's invite.
    $this->actingAs($leader)
        ->delete(route('my-clan.invites.destroy', $otherInvite->id))
        ->assertStatus(403);

    expect($otherInvite->fresh()->status)->toBe('pending');
});

// ===========================================================================
// Expiry — no automatic transition in P2
// ===========================================================================

it('Invite with future expires_at remains pending (no automatic expiry job in P2)', function (): void {
    // NOTE: P2 ships no scheduler/cron. expires_at is stored but NOT automatically
    // processed. This test documents that contract — invites do NOT expire at DB level.
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    // Re-fetch from DB — status should still be pending.
    expect($invite->fresh()->status)->toBe('pending');
});

// ===========================================================================
// Invitee-facing surface on /my-clan — the only in-product entry point to act
// on a received invite (reachability-audit gap #2).
// ===========================================================================

it('surfaces a pending received invite to the invitee on GET /my-clan with clan + inviter display fields', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create(); // no clan of their own

    ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
        'message' => 'Join us!',
    ]);

    $this->actingAs($invitee)
        ->get('/my-clan')
        ->assertStatus(200)
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('MyClan/Index', false)
                ->where('clan', null)
                ->has('received_invites', 1)
                ->where('received_invites.0.clan_name', $clan->name)
                ->where('received_invites.0.clan_slug', $clan->slug)
                ->where('received_invites.0.inviter_username', $leader->username)
                ->where('received_invites.0.message', 'Join us!')
        );
});

it('does not surface declined/accepted/revoked invites in received_invites', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    foreach (['declined', 'accepted', 'revoked'] as $status) {
        ClanInvite::factory()->create([
            'clan_id' => $clan->id,
            'inviting_user_id' => $leader->id,
            'invited_user_id' => $invitee->id,
            'status' => $status,
            'decided_at' => now(),
        ]);
    }

    $this->actingAs($invitee)
        ->get('/my-clan')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('received_invites', 0));
});

it('lets the invitee accept their invite from the /my-clan surface and join the clan', function (): void {
    [$clan, $leader] = setupInviteClan();
    $invitee = User::factory()->create();

    $invite = ClanInvite::factory()->create([
        'clan_id' => $clan->id,
        'inviting_user_id' => $leader->id,
        'invited_user_id' => $invitee->id,
        'status' => 'pending',
    ]);

    // The surface posts to the same route the buttons use.
    $this->actingAs($invitee)
        ->post(route('invites.accept', $invite->id))
        ->assertRedirect(route('my-clan.index'));

    expect($invite->fresh()->status)->toBe('accepted');
    expect(
        ClanMembership::where('user_id', $invitee->id)->where('clan_id', $clan->id)->whereNull('left_at')->exists()
    )->toBeTrue();

    // The invite is no longer pending, so it would not be surfaced again.
    expect(ClanInvite::where('invited_user_id', $invitee->id)->where('status', 'pending')->exists())->toBeFalse();
});
