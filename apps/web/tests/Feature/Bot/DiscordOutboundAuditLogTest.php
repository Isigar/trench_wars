<?php

declare(strict_types=1);

/*
| Source: 05-12-PLAN.md task 2 — audit-log integration coverage for the
| DiscordOutboundMessage state machine (D-012 + CLAUDE.md §6 append-only).
|
| The DiscordOutboundMessage model wires Spatie's LogsActivity trait (plan 05-02)
| with logFillable + logOnlyDirty. This test verifies the trait actually fires
| for EVERY relevant state transition (T-05-12-04 mitigation: no controller
| bypasses the trait via raw DB::update).
|
| Five state transitions covered:
|   1. CREATE: brand-new row -> 'created' event
|   2. CLAIM:  status=pending  -> status=dispatching   (via BotApiOutboundController::pending)
|   3. SENT:   status=dispatching -> status=sent       (via markSent endpoint)
|   4. FAIL/RETRY: dispatching attempts<3 -> pending   (via markFailed endpoint)
|   5. FAIL/TERM:  dispatching attempts=3 -> failed    (via markFailed endpoint)
*/

use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Build a bot Sanctum token bearer-auth header set with the abilities required
 * for the outbound ack endpoints (read + write-outbound). No X-Bot-Acts-As-User
 * because the outbound delivery cycle is the bot acting as itself, not on
 * behalf of a human (plan 05-04 BotApiOutboundController commentary).
 *
 * @return array<string, string>
 */
function botOutboundAuditHeaders(): array
{
    $bot = User::factory()->create(['discord_id' => '900000000000000060']);
    $token = $bot->createToken(
        name: 'bot-audit-test',
        abilities: ['bot:read', 'bot:write-outbound'],
        expiresAt: now()->addDays(30),
    );

    return [
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ];
}

it('writes activity_log row on DiscordOutboundMessage create', function (): void {
    $row = DiscordOutboundMessage::factory()->pending()->create();

    $activity = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('DiscordOutboundMessage created');
});

it('writes activity_log row on pending -> dispatching transition (BotApiOutboundController::pending)', function (): void {
    $row = DiscordOutboundMessage::factory()->pending()->create();

    $beforeCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    // Hit the real claim endpoint (Pattern 4 atomic claim with lockForUpdate).
    $this->withHeaders(botOutboundAuditHeaders())
        ->getJson('/api/bot/outbound-messages?status=pending&limit=20')
        ->assertOk();

    $afterCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1);

    $fresh = $row->fresh();
    expect($fresh->status)->toBe('dispatching')
        ->and($fresh->attempts)->toBe(1);
});

it('writes activity_log row on dispatching -> sent transition (markSent)', function (): void {
    $row = DiscordOutboundMessage::factory()->dispatching()->create();

    $beforeCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    $this->withHeaders(botOutboundAuditHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/sent", [
            'sent_message_id' => '111222333444555666',
        ])
        ->assertOk();

    $afterCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1)
        ->and($row->fresh()->status)->toBe('sent');
});

it('writes activity_log row on dispatching -> failed terminal transition (markFailed attempts >= 3)', function (): void {
    $row = DiscordOutboundMessage::factory()->dispatching()->state([
        'attempts' => 3,
    ])->create();

    $beforeCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    $this->withHeaders(botOutboundAuditHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/failed", [
            'last_error' => 'Discord 403 — bot kicked from server',
        ])
        ->assertOk();

    $afterCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1)
        ->and($row->fresh()->status)->toBe('failed');
});

it('writes activity_log row on dispatching -> pending backoff retry transition (markFailed attempts < 3)', function (): void {
    $row = DiscordOutboundMessage::factory()->dispatching()->state([
        'attempts' => 1,
    ])->create();

    $beforeCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    $this->withHeaders(botOutboundAuditHeaders())
        ->postJson("/api/bot/outbound-messages/{$row->id}/failed", [
            'last_error' => 'Discord 503 — try again',
        ])
        ->assertOk();

    $afterCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1);

    $fresh = $row->fresh();
    expect($fresh->status)->toBe('pending')
        ->and($fresh->backoff_until)->not->toBeNull();
});
