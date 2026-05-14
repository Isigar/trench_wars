<?php

declare(strict_types=1);

use App\Models\DiscordOutboundMessage;
use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\MatchStartingSoon;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/*
| Source: .planning/phases/09-polish/09-03-PLAN.md task 2.
|
| Wave 0 stub turned GREEN. Asserts:
|   - SC-1 (DiscordChannel writes a discord_outbound_messages row).
|   - Pitfall 3 LOCKED (D-004: web never POSTs to discord.com directly;
|     Http::fake + Http::assertNothingSent is the architectural choke point).
|   - Misconfigured Notification (no toDiscord) throws RuntimeException loud.
*/

it('writes a discord_outbound_messages row on send (user_dm message_type)', function (): void {
    $user = User::factory()->create(['discord_id' => '987654321098765432']);
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 60);
    $channel = new DiscordChannel;

    $before = DiscordOutboundMessage::query()->count();
    $row = $channel->send($user, $notification);
    $after = DiscordOutboundMessage::query()->count();

    expect($after - $before)->toBe(1);
    expect($row)
        ->not->toBeNull()
        ->and($row?->message_type)->toBe('user_dm')
        ->and($row?->status)->toBe('pending')
        ->and($row?->attempts)->toBe(0)
        ->and($row?->causer_user_id)->toBe($user->id);

    expect($row?->payload)
        ->toBeArray()
        ->toHaveKey('recipient_id', '987654321098765432')
        ->toHaveKey('embed_title')
        ->toHaveKey('embed_description')
        ->toHaveKey('cta_url');
});

it('makes ZERO outbound HTTP calls (Pitfall 3 LOCKED — D-004 compliance)', function (): void {
    Http::fake();

    $user = User::factory()->create();
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 15);
    (new DiscordChannel)->send($user, $notification);

    Http::assertNothingSent();
});

it('throws RuntimeException when notification lacks toDiscord method', function (): void {
    $user = User::factory()->create();

    // Anonymous Notification class WITHOUT toDiscord — DiscordChannel must
    // refuse delivery loudly rather than silently swallow.
    $bogus = new class extends Notification {};

    $channel = new DiscordChannel;

    expect(fn () => $channel->send($user, $bogus))
        ->toThrow(RuntimeException::class, 'must declare toDiscord');
});

it('persists channel_id as empty string when notification omits one (bot resolves DM at dispatch)', function (): void {
    $user = User::factory()->create(['discord_id' => '111122223333444455']);
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 60);
    $row = (new DiscordChannel)->send($user, $notification);

    expect($row?->channel_id)->toBe('');
});
