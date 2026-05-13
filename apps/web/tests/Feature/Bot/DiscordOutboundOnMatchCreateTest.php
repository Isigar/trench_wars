<?php

declare(strict_types=1);

/*
| Source: 05-05-PLAN.md task 3 + 05-VALIDATION.md Per-Plan Coverage Map.
| Replaces the Wave 0 RED stub (commit 242e78f) flipped GREEN in plan 05-05.
|
| SC-3 — MatchObserver inserts a pending discord_outbound_messages row of type
| match_announce on GameMatch create (or status transition) with the host clan's
| discord_announce_channel_id + payload containing the match embed shape.
|
| Coverage map:
|   1. public match + non-null channel → outbound row created
|   2. private match → NO outbound row
|   3. match without channel id → NO outbound row
|   4. payload shape (match_id, status, scheduled_at iso, host_clan_id,
|      slot_summary with totals + filled counts)
|   5. status transition open → locked fires update outbound row
|   6. status transition * → cancelled fires update outbound row
|   7. non-status update (title-only) does NOT fire outbound row
|   8. prior_sent_message_id propagates when a sent announce row exists
|   9. prior_sent_message_id is null when no prior sent row exists
|  10. causer_user_id captured via auth()->id() (Filament-attributed flow)
|  11. causer_user_id null when triggered without request context (CLI seeder)
|
| Pitfall 12 / D-04-08-B: every transition uses $match->update() / $match->save()
| or the MatchStatusService — never GameMatch::query()->update() which bypasses
| model events.
*/

use App\Models\Clan;
use App\Models\DiscordOutboundMessage;
use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchStatusService;

/**
 * Helper: a Clan with discord_announce_channel_id set.
 */
function clanWithAnnounceChannel(string $channelId = '999000111222333444'): Clan
{
    return Clan::factory()->create([
        'discord_announce_channel_id' => $channelId,
    ]);
}

/**
 * Helper: a Clan without discord_announce_channel_id (null channel).
 */
function clanWithoutAnnounceChannel(): Clan
{
    return Clan::factory()->create([
        'discord_announce_channel_id' => null,
    ]);
}

it('creates a pending discord_outbound_messages row when a public match is created on a clan with discord_announce_channel_id', function (): void {
    $clan = clanWithAnnounceChannel('123450000000000001');

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);

    $rows = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->status)->toBe('pending')
        ->and($rows->first()->channel_id)->toBe('123450000000000001')
        ->and($rows->first()->payload['kind'])->toBe('match_announce_new')
        ->and($rows->first()->payload['match_id'])->toBe($match->id);
});

it('does NOT create an outbound row when match.is_public=false', function (): void {
    $clan = clanWithAnnounceChannel();

    GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => false,
        'status' => 'draft',
    ]);

    expect(DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->count())->toBe(0);
});

it('does NOT create an outbound row when hostClan.discord_announce_channel_id is null', function (): void {
    $clan = clanWithoutAnnounceChannel();

    GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);

    expect(DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->count())->toBe(0);
});

it('does NOT create an outbound row when host_clan_id is null', function (): void {
    GameMatch::factory()->create([
        'host_clan_id' => null,
        'is_public' => true,
        'status' => 'open',
    ]);

    expect(DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->count())->toBe(0);
});

it('payload contains match_id + status + scheduled_at iso + host_clan_id + slot_summary with totals + filled counts', function (): void {
    $clan = clanWithAnnounceChannel();
    $occupant = User::factory()->create();

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => '2026-06-15 20:00:00',
        'title' => ['en' => 'Friday Night Skirmish'],
    ]);

    // Seed 3 slots for one role: 2 unfilled + 1 filled
    $role = GameRole::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => null,
    ]);
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 1,
        'occupant_user_id' => null,
    ]);
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 2,
        'occupant_user_id' => $occupant->id,
    ]);

    // Re-trigger observer by touching status (or just re-create payload — simplest: fresh observer build)
    // The match was created BEFORE slots existed, so the original payload is empty. Trigger a status
    // transition to fire updated() with the slots present.
    app(MatchStatusService::class)->transition($match, 'locked', User::factory()->create());

    $updateRow = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->where('payload->kind', 'match_announce_update')
        ->orderByDesc('created_at')
        ->first();

    expect($updateRow)->not->toBeNull()
        ->and($updateRow->payload['match_id'])->toBe($match->id)
        ->and($updateRow->payload['status'])->toBe('locked')
        ->and($updateRow->payload['scheduled_at'])->toBe('2026-06-15T20:00:00+00:00')
        ->and($updateRow->payload['host_clan_id'])->toBe($clan->id)
        ->and($updateRow->payload['host_clan_name'])->toBe($clan->name)
        ->and($updateRow->payload['title'])->toBe('Friday Night Skirmish')
        ->and($updateRow->payload['slot_summary'])->toHaveCount(1)
        ->and($updateRow->payload['slot_summary'][0]['role_id'])->toBe($role->id)
        ->and($updateRow->payload['slot_summary'][0]['total'])->toBe(3)
        ->and($updateRow->payload['slot_summary'][0]['filled'])->toBe(1);
});

it('writes an update outbound row when status transitions from open to locked', function (): void {
    $clan = clanWithAnnounceChannel();

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);
    expect(DiscordOutboundMessage::query()->count())->toBe(1); // initial announce

    app(MatchStatusService::class)->transition($match, 'locked', User::factory()->create());

    $rows = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->orderBy('created_at')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->payload['kind'])->toBe('match_announce_new')
        ->and($rows[1]->payload['kind'])->toBe('match_announce_update')
        ->and($rows[1]->payload['status'])->toBe('locked');
});

it('writes an update outbound row when status transitions to cancelled', function (): void {
    $clan = clanWithAnnounceChannel();

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);

    app(MatchStatusService::class)->transition($match, 'cancelled', User::factory()->create());

    $rows = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->orderBy('created_at')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[1]->payload['kind'])->toBe('match_announce_update')
        ->and($rows[1]->payload['status'])->toBe('cancelled');
});

it('does NOT write outbound row on non-status updates (e.g., title change only)', function (): void {
    $clan = clanWithAnnounceChannel();

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
        'title' => ['en' => 'Original Title'],
    ]);
    expect(DiscordOutboundMessage::query()->count())->toBe(1); // initial announce

    // Title-only edit — observer.updated fires, but wasChanged('status') is false
    $match->setTranslation('title', 'en', 'Renamed Title');
    $match->save();

    expect(DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->count())->toBe(1); // still 1 — no update row
});

it('outbound update row includes prior_sent_message_id when a sent announce row exists', function (): void {
    $clan = clanWithAnnounceChannel();

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);

    // Simulate bot delivery — flip the original announce row to sent with a Discord message id
    $original = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->firstOrFail();
    $original->update([
        'status' => 'sent',
        'sent_message_id' => '987654321098765432',
    ]);

    // Now transition status → observer's updated() looks up the sent row and propagates the id
    app(MatchStatusService::class)->transition($match, 'locked', User::factory()->create());

    $updateRow = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->where('payload->kind', 'match_announce_update')
        ->firstOrFail();

    expect($updateRow->payload['prior_sent_message_id'])->toBe('987654321098765432');
});

it('outbound update row prior_sent_message_id is null when no prior sent announce exists', function (): void {
    $clan = clanWithAnnounceChannel();

    $match = GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);
    // Original announce row stays in status=pending (never marked sent).

    app(MatchStatusService::class)->transition($match, 'locked', User::factory()->create());

    $updateRow = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->where('payload->kind', 'match_announce_update')
        ->firstOrFail();

    expect($updateRow->payload['prior_sent_message_id'])->toBeNull();
});

it('causer_user_id is auth->id() when called during a Filament admin request flow', function (): void {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $clan = clanWithAnnounceChannel();

    GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);

    $row = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->firstOrFail();

    expect($row->causer_user_id)->toBe($admin->id);
});

it('causer_user_id is null when triggered without a request context (CLI seeder, etc.)', function (): void {
    // No actingAs — auth()->id() returns null (matches CLI / seeder flow)
    $clan = clanWithAnnounceChannel();

    GameMatch::factory()->create([
        'host_clan_id' => $clan->id,
        'is_public' => true,
        'status' => 'open',
    ]);

    $row = DiscordOutboundMessage::query()
        ->where('message_type', 'match_announce')
        ->firstOrFail();

    expect($row->causer_user_id)->toBeNull();
});
