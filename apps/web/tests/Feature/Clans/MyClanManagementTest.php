<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 02-09-PLAN.md Task 3 — replaces Wave 0 stub.
|
| Covers SC-3 "profile + members" slice of the My Clan management surface:
|   - Auth gate (guest/no-clan/member-redirect/leader+officer)
|   - POST /clans create flow (atomic, audit-logged, reserved-slug, one-active)
|   - PATCH /my-clan/profile update (Leader/Officer allowed; Member denied)
|   - Mass-assignment guard (discord_role_id silently dropped)
|   - PATCH /my-clan/members/{membership}/role (Leader OK; Officer→Leader denied; Officer→member OK)
|   - DELETE /my-clan/members/{membership} (soft-remove; D-009 history preserved; audit-logged)
|
| Plans 02-10 and 02-11 add invites and applications.
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
function setupClanWithRoles(): array
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
// GET /my-clan — access gate
// ===========================================================================

it('redirects guest to login on GET /my-clan', function (): void {
    $this->get('/my-clan')->assertRedirect('/auth/discord/redirect');
});

it('renders MyClan/Index with null clan for auth user with no membership', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/my-clan')
        ->assertStatus(200)
        ->assertInertia(
            fn ($page) => $page
                ->component('MyClan/Index')
                ->where('membership', null)
                ->where('clan', null)
        );
});

it('redirects member-role user to public clan page on GET /my-clan', function (): void {
    [$clan, $leader, $officer, $member] = setupClanWithRoles();

    $this->actingAs($member)
        ->get('/my-clan')
        ->assertRedirect(route('clans.show', $clan->slug));
});

it('redirects recruit-role user to public clan page on GET /my-clan', function (): void {
    $user = User::factory()->create();
    $clan = Clan::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan->id,
        'role' => 'recruit',
    ]);

    $this->actingAs($user)
        ->get('/my-clan')
        ->assertRedirect(route('clans.show', $clan->slug));
});

it('renders management page for Leader on GET /my-clan', function (): void {
    [$clan, $leader] = setupClanWithRoles();

    $this->actingAs($leader)
        ->get('/my-clan')
        ->assertStatus(200)
        ->assertInertia(
            fn ($page) => $page
                ->component('MyClan/Index')
                ->where('membership.role', 'leader')
                ->has('clan')
                ->where('clan.id', $clan->id)
        );
});

it('renders management page for Officer on GET /my-clan', function (): void {
    [$clan, $leader, $officer] = setupClanWithRoles();

    $this->actingAs($officer)
        ->get('/my-clan')
        ->assertStatus(200)
        ->assertInertia(
            fn ($page) => $page
                ->component('MyClan/Index')
                ->where('membership.role', 'officer')
                ->has('clan')
        );
});

// ===========================================================================
// POST /clans — clan create
// ===========================================================================

it('creates clan + Leader membership atomically and redirects to my-clan on valid POST /clans', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/clans', [
            'name' => 'Ninety-First',
            'tag' => '91st',
            'description' => 'Elite HLL unit.',
            'country_code' => 'GB',
        ])
        ->assertRedirect(route('my-clan.index'));

    expect(Clan::count())->toBe(1);
    $clan = Clan::firstOrFail();
    expect($clan->name)->toBe('Ninety-First');
    expect($clan->owner_user_id)->toBe($user->id);

    expect(ClanMembership::count())->toBe(1);
    $membership = ClanMembership::firstOrFail();
    expect($membership->user_id)->toBe($user->id);
    expect($membership->role)->toBe('leader');
    expect($membership->left_at)->toBeNull();
});

it('writes activity_log entry for Clan create inside the transaction', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/clans', [
            'name' => 'Ironclad',
            'tag' => 'IRC',
        ]);

    $clan = Clan::firstOrFail();

    $activity = Activity::where('subject_type', Clan::class)
        ->where('subject_id', $clan->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->description)->toContain('created');
});

it('returns validation error on name when slug is reserved on POST /clans', function (): void {
    $user = User::factory()->create();

    // 'admin' is in config('clan.reserved_slugs').
    // Inertia redirects back with session errors rather than returning 422 JSON.
    $this->actingAs($user)
        ->post('/clans', [
            'name' => 'Admin',
            'tag' => 'ADM',
        ])
        ->assertSessionHasErrors(['name']);

    // No clan was created.
    expect(Clan::count())->toBe(0);
});

it('returns 409 when actor already has an active membership on POST /clans', function (): void {
    [$clan, $leader] = setupClanWithRoles();

    $this->actingAs($leader)
        ->post('/clans', [
            'name' => 'Second Clan',
            'tag' => 'SEC',
        ])
        ->assertStatus(409);
});

// ===========================================================================
// PATCH /my-clan/profile — profile update
// ===========================================================================

it('Leader can update clan profile via PATCH /my-clan/profile/{clan}', function (): void {
    [$clan, $leader] = setupClanWithRoles();

    $this->actingAs($leader)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'name' => 'Updated Name',
        ])
        ->assertRedirect();

    expect($clan->fresh()->name)->toBe('Updated Name');
});

it('writes activity_log entry when Leader updates clan profile', function (): void {
    [$clan, $leader] = setupClanWithRoles();

    $this->actingAs($leader)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'name' => 'Renamed Clan',
        ]);

    $activity = Activity::where('subject_type', Clan::class)
        ->where('subject_id', $clan->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($leader->id);
    expect($activity->description)->toContain('updated');
});

it('Officer can update clan profile', function (): void {
    [$clan, $leader, $officer] = setupClanWithRoles();

    $this->actingAs($officer)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'name' => 'Officer Updated',
        ])
        ->assertRedirect();

    expect($clan->fresh()->name)->toBe('Officer Updated');
});

it('Member cannot update clan profile (403)', function (): void {
    [$clan, $leader, $officer, $member] = setupClanWithRoles();

    $this->actingAs($member)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'name' => 'Sneaky Update',
        ])
        ->assertStatus(403);
});

it('discord_role_id in profile update request is silently dropped (T-02-05-02)', function (): void {
    [$clan, $leader] = setupClanWithRoles();
    $originalDiscordRoleId = $clan->discord_role_id; // null

    $this->actingAs($leader)
        ->patch(route('my-clan.profile.update', $clan->slug), [
            'name' => 'Legitimate Update',
            'discord_role_id' => 'malicious-snowflake',
        ])
        ->assertRedirect();

    $fresh = $clan->fresh();
    expect($fresh->name)->toBe('Legitimate Update');
    expect($fresh->discord_role_id)->toBe($originalDiscordRoleId);
    expect($fresh->discord_role_id)->toBeNull();
});

// ===========================================================================
// PATCH /my-clan/members/{membership}/role — role change
// ===========================================================================

it('Leader can change a member role to officer', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $this->actingAs($leader)
        ->patch(route('my-clan.members.role', $memberMembership->id), [
            'role' => 'officer',
        ])
        ->assertRedirect();

    expect($memberMembership->fresh()->role)->toBe('officer');
});

it('Officer cannot promote a member to Leader (403)', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $this->actingAs($officer)
        ->patch(route('my-clan.members.role', $memberMembership->id), [
            'role' => 'leader',
        ])
        ->assertStatus(403);

    // Role must be unchanged.
    expect($memberMembership->fresh()->role)->toBe('member');
});

it('Officer can demote a member to recruit', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $this->actingAs($officer)
        ->patch(route('my-clan.members.role', $memberMembership->id), [
            'role' => 'recruit',
        ])
        ->assertRedirect();

    expect($memberMembership->fresh()->role)->toBe('recruit');
});

it('writes activity_log entry on member role change', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $this->actingAs($leader)
        ->patch(route('my-clan.members.role', $memberMembership->id), [
            'role' => 'officer',
        ]);

    $activity = Activity::where('subject_type', ClanMembership::class)
        ->where('subject_id', $memberMembership->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($leader->id);
    expect($activity->description)->toContain('updated');
});

// ===========================================================================
// DELETE /my-clan/members/{membership} — soft-remove (D-009)
// ===========================================================================

it('Leader can soft-remove a member (sets left_at, preserves row)', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $totalBefore = ClanMembership::count();
    $activeBefore = ClanMembership::whereNull('left_at')->count();

    $this->actingAs($leader)
        ->delete(route('my-clan.members.remove', $memberMembership->id))
        ->assertRedirect();

    expect(ClanMembership::count())->toBe($totalBefore);            // row preserved (D-009)
    expect(ClanMembership::whereNull('left_at')->count())->toBe($activeBefore - 1);

    $fresh = $memberMembership->fresh();
    expect($fresh->left_at)->not->toBeNull();
});

it('writes activity_log entry on member remove', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $this->actingAs($leader)
        ->delete(route('my-clan.members.remove', $memberMembership->id));

    $activity = Activity::where('subject_type', ClanMembership::class)
        ->where('subject_id', $memberMembership->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($leader->id);
    expect($activity->description)->toContain('updated');
});

it('Leader cannot remove themselves while still a Leader (403)', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership] = setupClanWithRoles();

    $this->actingAs($leader)
        ->delete(route('my-clan.members.remove', $leaderMembership->id))
        ->assertStatus(403);

    expect($leaderMembership->fresh()->left_at)->toBeNull();
});

it('regular member cannot remove another member (403)', function (): void {
    [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership] = setupClanWithRoles();

    $this->actingAs($member)
        ->delete(route('my-clan.members.remove', $officerMembership->id))
        ->assertStatus(403);

    expect($officerMembership->fresh()->left_at)->toBeNull();
});
