<?php

declare(strict_types=1);

/*
| Source: plan 05-04 task 3 — replaces Wave 0 RED stub (05-01 task 2).
|
| Covers SC-3 (outbound ack/fail transitions) — RESEARCH Pattern 4:
| POST /api/bot/outbound-messages/{id}/sent transitions dispatching -> sent;
| POST /api/bot/outbound-messages/{id}/failed transitions dispatching -> pending
| (retry, with backoff_until) or -> failed (terminal, attempts >= 3).
|
| 5 it() blocks per plan <interfaces> enumeration:
|  1. markSent transitions dispatching -> sent + stores sent_message_id
|  2. markSent on non-dispatching row returns 422
|  3. markFailed with attempts < 3 -> pending + backoff_until per [1,5,15,60,300]
|  4. markFailed with attempts >= 3 -> failed (terminal)
|  5. markFailed on non-dispatching row returns 422
*/

use App\Models\DiscordOutboundMessage;
use App\Models\User;

/**
 * Build a write-outbound bot token.
 */
function botOutboundAckHeaders(): array
{
    $bot = User::factory()->create(['discord_id' => '900000000000000050']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:write-outbound'],
        expiresAt: now()->addDays(30),
    );

    return [
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ];
}

it('markSent transitions dispatching -> sent + stores sent_message_id', function (): void {
    $row = DiscordOutboundMessage::factory()->dispatching()->create();

    $this->withHeaders(botOutboundAckHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/sent", [
            'sent_message_id' => '999888777666555444',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.sent_message_id', '999888777666555444');

    $fresh = DiscordOutboundMessage::findOrFail($row->id);
    expect($fresh->status)->toBe('sent')
        ->and($fresh->sent_message_id)->toBe('999888777666555444')
        ->and($fresh->last_error)->toBeNull();
});

it('markSent on non-dispatching row returns 422', function (): void {
    $row = DiscordOutboundMessage::factory()->sent()->create();

    $this->withHeaders(botOutboundAckHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/sent", [
            'sent_message_id' => '999888777666555444',
        ])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'outbound_not_dispatching',
            'message' => __('bot.errors.outbound_not_dispatching'),
        ]);
});

it('markFailed with attempts < 3 transitions to pending + sets backoff_until via [1,5,15,60,300] schedule', function (): void {
    // Row currently has attempts=2 (just-dispatched, second attempt). markFailed
    // should index schedule[2-1] = 5 seconds.
    $row = DiscordOutboundMessage::factory()->dispatching()->state([
        'attempts' => 2,
    ])->create();

    $this->withHeaders(botOutboundAckHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/failed", [
            'last_error' => 'Discord 503 Service Unavailable',
        ])
        ->assertOk();

    $fresh = DiscordOutboundMessage::findOrFail($row->id);
    expect($fresh->status)->toBe('pending')
        ->and($fresh->last_error)->toBe('Discord 503 Service Unavailable')
        ->and($fresh->backoff_until)->not->toBeNull();

    // Carbon::diffInSeconds returns a signed float — use absolute value to
    // assert backoff_until is ~5 seconds in the future (the [1,5,15,60,300][1]
    // schedule entry for attempts=2 -> index 1).
    $diffSeconds = abs($fresh->backoff_until->diffInSeconds(now()));
    expect($diffSeconds)->toBeGreaterThanOrEqual(4)
        ->and($diffSeconds)->toBeLessThanOrEqual(6);
});

it('markFailed with attempts >= 3 transitions to failed (terminal)', function (): void {
    $row = DiscordOutboundMessage::factory()->dispatching()->state([
        'attempts' => 3,
    ])->create();

    $this->withHeaders(botOutboundAckHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/failed", [
            'last_error' => 'Permanent failure: bot kicked from server',
        ])
        ->assertOk();

    $fresh = DiscordOutboundMessage::findOrFail($row->id);
    expect($fresh->status)->toBe('failed')
        ->and($fresh->last_error)->toBe('Permanent failure: bot kicked from server');
});

it('markFailed on non-dispatching row returns 422', function (): void {
    $row = DiscordOutboundMessage::factory()->pending()->create();

    $this->withHeaders(botOutboundAckHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/failed", [
            'last_error' => 'Trying to ack a pending row should fail',
        ])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'outbound_not_dispatching',
            'message' => __('bot.errors.outbound_not_dispatching'),
        ]);
});
