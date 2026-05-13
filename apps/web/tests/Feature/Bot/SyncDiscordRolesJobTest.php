<?php

declare(strict_types=1);

/*
| Source: plan 05-06 task 2 — REPLACES the Wave 0 RED stub (05-01 task 2).
|
| Covers SC-4 job-side: SyncDiscordRolesJob::handle() writes a pending
| `role_sync` outbound row with the canonical payload shape that plan 05-11's
| bot worker consumes. handle() is exercised DIRECTLY (no Queue::fake) — this
| file tests the job body, not the dispatch matrix (see
| SyncDiscordRolesJobDispatchTest for that).
|
| 7 it() blocks per plan <interfaces> enumeration:
|  1. writes a pending role_sync outbound row when handle() succeeds
|  2. payload contains discord_user_id + discord_role_id + action + membership_id + clan_id + user_id
|  3. returns early without writing when ClanMembership has been hard-deleted between dispatch and handle
|  4. returns early without writing when user.discord_id is empty (defensive)
|  5. returns early without writing when clan.discord_role_id is empty (defensive)
|  6. declares $tries=5 + backoff [1,5,15,60,300] (Horizon canonical retry contract)
|  7. writes causer_user_id from the constructor parameter
|
| Payload-key naming deviation noted: the plan's <interfaces> block specified
| `user_discord_id`/`role_discord_id` but the codebase (factory + echo
| suppression controller) uses `discord_user_id`/`discord_role_id`. These
| tests follow the codebase convention — see SyncDiscordRolesJob class docblock.
*/

use App\Jobs\SyncDiscordRolesJob;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\DiscordOutboundMessage;
use App\Models\User;

it('writes a pending role_sync outbound row when handle() succeeds', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000001']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000001']);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    // Reset outbound table — observer.created() already wrote one row when the
    // factory ran. We're testing handle() in isolation here, so wipe to a baseline.
    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membership->id, 'add', $user->id);
    $job->handle();

    expect(DiscordOutboundMessage::where('message_type', 'role_sync')->count())->toBe(1);

    $row = DiscordOutboundMessage::where('message_type', 'role_sync')->firstOrFail();
    expect($row->status)->toBe('pending')
        ->and($row->channel_id)->toBe(''); // Guilds API — no channel
});

it('payload contains discord_user_id + discord_role_id + action + membership_id + clan_id + user_id', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000002']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000002']);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membership->id, 'remove', $user->id);
    $job->handle();

    $row = DiscordOutboundMessage::where('message_type', 'role_sync')->firstOrFail();

    expect($row->payload['discord_user_id'])->toBe('100000000000000002')
        ->and($row->payload['discord_role_id'])->toBe('200000000000000002')
        ->and($row->payload['action'])->toBe('remove')
        ->and($row->payload['membership_id'])->toBe($membership->id)
        ->and($row->payload['clan_id'])->toBe($clan->id)
        ->and($row->payload['user_id'])->toBe($user->id);
});

it('returns early without writing when ClanMembership has been hard-deleted between dispatch and handle', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000003']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000003']);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);
    $membershipId = $membership->id;

    // Hard-delete to simulate the race condition between dispatch and handle.
    $membership->delete();

    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membershipId, 'add', $user->id);
    $job->handle();

    expect(DiscordOutboundMessage::where('message_type', 'role_sync')->count())->toBe(0);
});

it('returns early without writing when user.discord_id is empty (defensive)', function (): void {
    // User has no discord_id (unreachable in production after Phase 1 OAuth, but defensive).
    $user = User::factory()->create(['discord_id' => '']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000004']);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membership->id, 'add', $user->id);
    $job->handle();

    expect(DiscordOutboundMessage::where('message_type', 'role_sync')->count())->toBe(0);
});

it('returns early without writing when clan.discord_role_id is empty (defensive)', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000005']);
    // Clan has no discord_role_id (admin hasn't bound it yet).
    $clan = Clan::factory()->create(['discord_role_id' => null]);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membership->id, 'add', $user->id);
    $job->handle();

    expect(DiscordOutboundMessage::where('message_type', 'role_sync')->count())->toBe(0);
});

it('declares $tries=5 + backoff [1,5,15,60,300] (Horizon canonical retry contract)', function (): void {
    $job = new SyncDiscordRolesJob('00000000-0000-0000-0000-000000000000', 'add', null);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([1, 5, 15, 60, 300]);
});

it('writes causer_user_id from the constructor parameter', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000007']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000007']);
    $causer = User::factory()->create(['discord_id' => '300000000000000007']);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membership->id, 'add', $causer->id);
    $job->handle();

    $row = DiscordOutboundMessage::where('message_type', 'role_sync')->firstOrFail();
    expect($row->causer_user_id)->toBe($causer->id);
});

it('writes null causer_user_id when constructor receives null (CLI/seeder flow)', function (): void {
    $user = User::factory()->create(['discord_id' => '100000000000000008']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000008']);
    $membership = ClanMembership::factory()->for($user)->for($clan)->create(['left_at' => null]);

    DiscordOutboundMessage::query()->delete();

    $job = new SyncDiscordRolesJob($membership->id, 'add', null);
    $job->handle();

    $row = DiscordOutboundMessage::where('message_type', 'role_sync')->firstOrFail();
    expect($row->causer_user_id)->toBeNull();
});
