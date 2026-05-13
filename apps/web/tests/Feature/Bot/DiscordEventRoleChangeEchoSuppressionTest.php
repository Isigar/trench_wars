<?php

declare(strict_types=1);

/*
| Source: plan 05-04 task 3 — replaces Wave 0 RED stub (05-01 task 2).
|
| Covers SC-4 (Pitfall 10 echo suppression) — POST /api/bot/discord-events/role-change.
|
| 6 it() blocks per plan <interfaces> enumeration:
|  1. returns noop reason=own_echo when matching outbound row was sent within 60s
|  2. returns noop reason=unmapped when discord_id or role_id has no User/Clan
|  3. creates a ClanMembership row when action=add and user+clan resolve
|  4. ends the active ClanMembership when action=remove
|  5. does NOT create a duplicate ClanMembership on a second add (firstOrCreate)
|  6. outbound row sent more than 60s ago does NOT suppress the change
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\DiscordOutboundMessage;
use App\Models\User;

/**
 * Build a reconcile-ability bot token.
 *
 * @return array<string, string>
 */
function botReconcileHeaders(): array
{
    $bot = User::factory()->create(['discord_id' => '900000000000000060']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:reconcile'],
        expiresAt: now()->addDays(30),
    );

    return [
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ];
}

it('returns noop reason=own_echo when matching outbound row was sent within 60s', function (): void {
    $human = User::factory()->create(['discord_id' => '100000000000000060']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000060']);

    // Seed a sent role_sync outbound row matching the inbound event.
    DiscordOutboundMessage::factory()->roleSync()->create([
        'status' => 'sent',
        'sent_message_id' => '999999999999999999',
        'payload' => [
            'discord_user_id' => $human->discord_id,
            'discord_role_id' => $clan->discord_role_id,
            'action' => 'add',
        ],
        'updated_at' => now()->subSeconds(30), // within the 60s echo window
    ]);

    $this->withHeaders(botReconcileHeaders())
        ->postJson('/api/bot/discord-events/role-change', [
            'user_discord_id' => $human->discord_id,
            'role_discord_id' => $clan->discord_role_id,
            'action' => 'add',
        ])
        ->assertOk()
        ->assertJson([
            'action' => 'noop',
            'reason' => 'own_echo',
        ]);

    // Crucially: NO ClanMembership row was created (echo path is a noop).
    expect(ClanMembership::where('user_id', $human->id)->count())->toBe(0);
});

it('returns noop reason=unmapped when discord_id or role_id has no User/Clan row', function (): void {
    $this->withHeaders(botReconcileHeaders())
        ->postJson('/api/bot/discord-events/role-change', [
            'user_discord_id' => '188888888888888888',
            'role_discord_id' => '288888888888888888',
            'action' => 'add',
        ])
        ->assertOk()
        ->assertJson([
            'action' => 'noop',
            'reason' => 'unmapped',
        ]);
});

it('creates a ClanMembership row when action=add and user+clan resolve to existing rows', function (): void {
    $human = User::factory()->create(['discord_id' => '100000000000000061']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000061']);

    $this->withHeaders(botReconcileHeaders())
        ->postJson('/api/bot/discord-events/role-change', [
            'user_discord_id' => $human->discord_id,
            'role_discord_id' => $clan->discord_role_id,
            'action' => 'add',
        ])
        ->assertOk()
        ->assertJsonPath('action', 'created')
        ->assertJsonPath('clan_id', $clan->id);

    expect(ClanMembership::where('user_id', $human->id)
        ->where('clan_id', $clan->id)
        ->whereNull('left_at')
        ->count())->toBe(1);
});

it('ends the active ClanMembership when action=remove', function (): void {
    $human = User::factory()->create(['discord_id' => '100000000000000062']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000062']);

    $membership = ClanMembership::factory()->create([
        'user_id' => $human->id,
        'clan_id' => $clan->id,
        'left_at' => null,
        'joined_at' => now()->subDays(5),
    ]);

    $this->withHeaders(botReconcileHeaders())
        ->postJson('/api/bot/discord-events/role-change', [
            'user_discord_id' => $human->discord_id,
            'role_discord_id' => $clan->discord_role_id,
            'action' => 'remove',
        ])
        ->assertOk()
        ->assertJsonPath('action', 'ended');

    $fresh = ClanMembership::findOrFail($membership->id);
    expect($fresh->left_at)->not->toBeNull();
});

it('does NOT create a duplicate ClanMembership on a second add (firstOrCreate)', function (): void {
    $human = User::factory()->create(['discord_id' => '100000000000000063']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000063']);

    // Pre-existing active membership.
    ClanMembership::factory()->create([
        'user_id' => $human->id,
        'clan_id' => $clan->id,
        'left_at' => null,
    ]);

    $this->withHeaders(botReconcileHeaders())
        ->postJson('/api/bot/discord-events/role-change', [
            'user_discord_id' => $human->discord_id,
            'role_discord_id' => $clan->discord_role_id,
            'action' => 'add',
        ])
        ->assertOk();

    // Still exactly 1 active membership (firstOrCreate idempotency).
    expect(ClanMembership::where('user_id', $human->id)
        ->where('clan_id', $clan->id)
        ->whereNull('left_at')
        ->count())->toBe(1);
});

it('outbound row sent more than 60s ago does NOT suppress the change', function (): void {
    $human = User::factory()->create(['discord_id' => '100000000000000064']);
    $clan = Clan::factory()->create(['discord_role_id' => '200000000000000064']);

    // Seed a sent role_sync outbound row but BACKDATED >60s ago — outside the
    // echo window, so the reconcile MUST proceed.
    DiscordOutboundMessage::factory()->roleSync()->create([
        'status' => 'sent',
        'sent_message_id' => '999999999999999999',
        'payload' => [
            'discord_user_id' => $human->discord_id,
            'discord_role_id' => $clan->discord_role_id,
            'action' => 'add',
        ],
        'updated_at' => now()->subSeconds(90), // outside 60s window
    ]);

    $this->withHeaders(botReconcileHeaders())
        ->postJson('/api/bot/discord-events/role-change', [
            'user_discord_id' => $human->discord_id,
            'role_discord_id' => $clan->discord_role_id,
            'action' => 'add',
        ])
        ->assertOk()
        ->assertJsonPath('action', 'created');

    // ClanMembership was actually created.
    expect(ClanMembership::where('user_id', $human->id)->whereNull('left_at')->count())->toBe(1);
});
