<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanInvite;
use App\Models\ClanMembership;
use App\Models\User;
use App\Services\ClanInviteService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Source: 02-14-PLAN.md Task 1 — final Wave-0 stub replacement.
|
| Covers D-009 from the INTEGRATION layer (distinct from ClanMembershipModelTest
| which covers the DB constraint at the model/factory level).
|
| These tests exercise the partial unique index via service-layer flows and
| ensure the constraint is durable across migrate:fresh cycles.
|
| SC-5: "A player has at most one active ClanMembership (enforced by partial unique
| index), and membership history is preserved when they leave or move clans."
*/

// ---------------------------------------------------------------------------
// Test 1: Concurrent dual-membership via invite acceptance
//
// Scenario: a user has an accepted invite for clan A (making them an active
// member). A second pending invite for clan B exists. Attempting to accept the
// second invite via the service should be blocked by the service-layer guard
// (D-009 enforcement in ClanInviteService::accept). We then bypass the service
// guard and try a raw DB insert to prove the partial unique index also fires at
// the DB layer, providing defence-in-depth.
// ---------------------------------------------------------------------------

it('rejects a second active membership when the first exists (service layer defence)', function (): void {
    $user = User::factory()->create();
    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();
    $inviterA = User::factory()->create();
    $inviterB = User::factory()->create();

    // Create an accepted membership for clan A (simulate first invite accepted).
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clanA->id,
        'left_at' => null,
    ]);

    // Create a pending invite for clan B.
    /** @var ClanInvite $inviteB */
    $inviteB = ClanInvite::factory()->create([
        'clan_id' => $clanB->id,
        'invited_user_id' => $user->id,
        'inviting_user_id' => $inviterB->id,
        'status' => 'pending',
    ]);

    // Service-layer guard must throw a DomainException (invitee_in_clan).
    $service = app(ClanInviteService::class);
    expect(fn () => $service->accept($inviteB, $user))
        ->toThrow(\DomainException::class);

    // Only the first membership should exist.
    expect(ClanMembership::where('user_id', $user->id)->whereNull('left_at')->count())->toBe(1);
});

it('rejects second active membership at the DB layer (partial unique index defence-in-depth)', function (): void {
    $user = User::factory()->create();
    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();

    // First active membership.
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clanA->id,
        'left_at' => null,
    ]);

    // Bypass service layer — raw insert must be rejected by the partial unique index.
    expect(fn () => DB::transaction(function () use ($user, $clanB): void {
        ClanMembership::create([
            'clan_id' => $clanB->id,
            'user_id' => $user->id,
            'role' => 'recruit',
            'joined_at' => now(),
            'left_at' => null,
        ]);
    }))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// Test 2: Membership history is preserved when a player leaves and joins again
//
// Simulates the full leave-then-accept-new-invite flow through the service layer.
// ---------------------------------------------------------------------------

it('membership history persists when player leaves a clan and joins another', function (): void {
    $user = User::factory()->create();
    $leader = User::factory()->create();
    $clanA = Clan::factory()->create(['owner_user_id' => $leader->id]);
    $clanB = Clan::factory()->create();

    // Player joins clan A via direct membership (bypassing invite for simplicity).
    $firstMembership = ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clanA->id,
        'role' => 'member',
        'left_at' => null,
    ]);

    // Player leaves clan A — left_at is set (history preserved, D-009).
    $firstMembership->update(['left_at' => now()]);

    // Player is now eligible to join clan B.
    /** @var ClanInvite $inviteB */
    $inviteB = ClanInvite::factory()->create([
        'clan_id' => $clanB->id,
        'invited_user_id' => $user->id,
        'inviting_user_id' => $leader->id,
        'status' => 'pending',
    ]);

    $service = app(ClanInviteService::class);
    $secondMembership = $service->accept($inviteB, $user);

    // History: both membership rows exist.
    expect(ClanMembership::where('user_id', $user->id)->count())->toBe(2);

    // Only the new membership is active (left_at is null).
    expect(ClanMembership::where('user_id', $user->id)->whereNull('left_at')->count())->toBe(1);
    expect($secondMembership->clan_id)->toBe($clanB->id);

    // First membership has a non-null left_at (history row).
    $firstMembership->refresh();
    expect($firstMembership->left_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 3: Partial unique index survives migrate:fresh
//
// Ensures the migration creating the partial unique index is durable.
// After a fresh migrate cycle the constraint must still reject a second active
// membership for the same user.
// ---------------------------------------------------------------------------

it('partial unique index survives a migrate:fresh cycle', function (): void {
    // Run migrate:fresh to reset the database completely.
    // RefreshDatabase (used in Pest.php) wraps each test in a transaction, but
    // this test explicitly calls artisan migrate:fresh to verify migration durability.
    $this->artisan('migrate:fresh', ['--force' => true, '--seed' => false]);

    $user = User::factory()->create();
    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();

    // First active membership.
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clanA->id,
        'left_at' => null,
    ]);

    // Second active membership for same user must be rejected.
    expect(fn () => ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clanB->id,
        'left_at' => null,
    ]))->toThrow(QueryException::class);
});
