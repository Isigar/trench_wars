<?php

declare(strict_types=1);

/*
| Source: plan 05-06 task 2 — REPLACES the Wave 0 RED stub (05-01 task 2).
|
| Covers SC-4 dispatch matrix: ClanMembershipObserver hooks fire SyncDiscordRolesJob
| with the correct action= for every relevant lifecycle event. Uses Queue::fake()
| to capture dispatch intent without executing the job — this file tests the
| observer wiring, not handle() (see SyncDiscordRolesJobTest for handle()).
|
| 8 it() blocks covering the full observer matrix:
|  1. dispatches with action=add when ClanMembership is created with left_at=null
|  2. does NOT dispatch when ClanMembership is created with left_at NOT NULL (historical seed)
|  3. dispatches with action=remove when ClanMembership.left_at is updated null → NOT NULL
|  4. dispatches with action=add when ClanMembership.left_at reverts NOT NULL → null (re-join)
|  5. dispatches with action=remove on ClanMembership::delete (hard delete)
|  6. does NOT dispatch when user.discord_id is empty
|  7. does NOT dispatch when clan.discord_role_id is empty
|  8. does NOT dispatch on a non-left_at update (e.g. role change)
*/

use App\Jobs\SyncDiscordRolesJob;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('dispatches with action=add when ClanMembership is created with left_at=null', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000010']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000010']);

    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    Queue::assertPushed(
        SyncDiscordRolesJob::class,
        fn (SyncDiscordRolesJob $job): bool => $job->action === 'add'
            && $job->membershipId === $membership->id,
    );
});

it('does NOT dispatch when ClanMembership is created with left_at NOT NULL (historical seed)', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000011']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000011']);

    ClanMembership::factory()->for($user)->for($clan)->create([
        'joined_at' => now()->subYear(),
        'left_at' => now()->subMonth(),
    ]);

    Queue::assertNotPushed(SyncDiscordRolesJob::class);
});

it('dispatches with action=remove when ClanMembership.left_at is updated null -> NOT NULL', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000012']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000012']);

    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    // Re-fake to clear the add dispatch from created()
    Queue::fake();

    $membership->update(['left_at' => now()]);

    Queue::assertPushed(
        SyncDiscordRolesJob::class,
        fn (SyncDiscordRolesJob $job): bool => $job->action === 'remove'
            && $job->membershipId === $membership->id,
    );
});

it('dispatches with action=add when ClanMembership.left_at reverts NOT NULL -> null (re-join)', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000013']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000013']);

    // Start with a left_at-set row so the create() does NOT dispatch.
    $membership = ClanMembership::factory()->for($user)->for($clan)->create([
        'joined_at' => now()->subMonth(),
        'left_at' => now()->subWeek(),
    ]);

    // Re-fake to drop any prior assertions (the create() above should have been a no-op).
    Queue::fake();

    $membership->update(['left_at' => null]);

    Queue::assertPushed(
        SyncDiscordRolesJob::class,
        fn (SyncDiscordRolesJob $job): bool => $job->action === 'add'
            && $job->membershipId === $membership->id,
    );
});

it('dispatches with action=remove on ClanMembership::delete (hard delete)', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000014']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000014']);

    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);
    $membershipId = $membership->id;

    // Re-fake to clear the add dispatch from created()
    Queue::fake();

    $membership->delete();

    Queue::assertPushed(
        SyncDiscordRolesJob::class,
        fn (SyncDiscordRolesJob $job): bool => $job->action === 'remove'
            && $job->membershipId === $membershipId,
    );
});

it('does NOT dispatch when user.discord_id is empty', function (): void {
    $user = User::factory()->create(['discord_id' => '']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000015']);

    ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    Queue::assertNotPushed(SyncDiscordRolesJob::class);
});

it('does NOT dispatch when clan.discord_role_id is empty', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000016']);
    $clan = Clan::factory()->create(['discord_role_id' => null]);

    ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    Queue::assertNotPushed(SyncDiscordRolesJob::class);
});

it('does NOT dispatch on a non-left_at update (e.g. role change)', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000017']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000017']);

    $membership = ClanMembership::factory()->for($user)->for($clan)->create([
        'left_at' => null,
        'role' => 'member',
    ]);

    // Re-fake to clear the add dispatch from created()
    Queue::fake();

    $membership->update(['role' => 'officer']);

    Queue::assertNotPushed(SyncDiscordRolesJob::class);
});
