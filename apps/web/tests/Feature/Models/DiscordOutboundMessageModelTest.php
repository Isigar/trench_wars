<?php

declare(strict_types=1);

use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/05-discord-bot-v1/05-02-PLAN.md task 2 <interfaces> enumeration.
| Replaces the Wave 0 RED stub (commit 242e78f).
| SC-3 — DiscordOutboundMessage durable outbox model contract:
|   UUID PK, channel_id text, message_type enum (match_announce|role_sync|generic),
|   payload JSONB, status enum (pending|dispatching|sent|failed), attempts int,
|   last_error nullable, sent_message_id nullable, causer_user_id nullable FK users.id
|   nullOnDelete, backoff_until nullable timestamptz. LogsActivity append-only (D-012).
*/

it('persists with all fillable fields populated', function (): void {
    $causer = User::factory()->create();

    $row = DiscordOutboundMessage::create([
        'channel_id' => '123456789012345678',
        'message_type' => 'match_announce',
        'status' => 'pending',
        'payload' => ['kind' => 'placeholder'],
        'attempts' => 0,
        'last_error' => null,
        'sent_message_id' => null,
        'causer_user_id' => $causer->id,
        'backoff_until' => null,
    ]);

    expect($row->exists)->toBeTrue();
    expect($row->id)->toBeString();
    expect($row->channel_id)->toBe('123456789012345678');
    expect($row->message_type)->toBe('match_announce');
    expect($row->status)->toBe('pending');
    expect($row->attempts)->toBe(0);
    expect($row->causer_user_id)->toBe($causer->id);
});

it('casts payload to array and back on retrieval', function (): void {
    $row = DiscordOutboundMessage::factory()->create([
        'payload' => ['kind' => 'match_announce', 'match_id' => 'abc-123', 'embed' => ['title' => 'Friday Scrim']],
    ]);

    $reloaded = DiscordOutboundMessage::findOrFail($row->id);
    expect($reloaded->payload)->toBeArray();
    expect($reloaded->payload)->toMatchArray([
        'kind' => 'match_announce',
        'match_id' => 'abc-123',
    ]);
    expect($reloaded->payload['embed']['title'])->toBe('Friday Scrim');
});

it('casts attempts to integer', function (): void {
    $row = DiscordOutboundMessage::factory()->create(['attempts' => 3]);

    expect($row->attempts)->toBeInt()->toBe(3);
});

it('casts backoff_until to Carbon datetime', function (): void {
    $when = now()->addMinutes(15);
    $row = DiscordOutboundMessage::factory()->create(['backoff_until' => $when]);

    $reloaded = DiscordOutboundMessage::findOrFail($row->id);
    expect($reloaded->backoff_until)->toBeInstanceOf(Carbon::class);
    expect($reloaded->backoff_until->timestamp)->toBe($when->timestamp);
});

it('scopePending returns only status=pending rows', function (): void {
    DiscordOutboundMessage::factory()->pending()->create();
    DiscordOutboundMessage::factory()->dispatching()->create();
    DiscordOutboundMessage::factory()->sent()->create();
    DiscordOutboundMessage::factory()->failed()->create();

    $pending = DiscordOutboundMessage::query()->pending()->get();

    expect($pending)->toHaveCount(1);
    expect($pending->first()->status)->toBe('pending');
});

it('scopeDispatchable excludes status=dispatching rows', function (): void {
    DiscordOutboundMessage::factory()->dispatching()->create();
    DiscordOutboundMessage::factory()->sent()->create();
    DiscordOutboundMessage::factory()->failed()->create();

    expect(DiscordOutboundMessage::query()->dispatchable()->count())->toBe(0);
});

it('scopeDispatchable excludes pending rows with backoff_until in the future', function (): void {
    DiscordOutboundMessage::factory()->create([
        'status' => 'pending',
        'backoff_until' => now()->addMinutes(10),
    ]);

    expect(DiscordOutboundMessage::query()->dispatchable()->count())->toBe(0);
});

it('scopeDispatchable includes pending rows with backoff_until in the past', function (): void {
    DiscordOutboundMessage::factory()->create([
        'status' => 'pending',
        'backoff_until' => now()->subMinutes(5),
    ]);

    expect(DiscordOutboundMessage::query()->dispatchable()->count())->toBe(1);
});

it('scopeDispatchable includes pending rows with null backoff_until', function (): void {
    DiscordOutboundMessage::factory()->pending()->create(['backoff_until' => null]);

    expect(DiscordOutboundMessage::query()->dispatchable()->count())->toBe(1);
});

it('rejects an unknown message_type via CHECK constraint', function (): void {
    expect(fn () => DiscordOutboundMessage::factory()->create(['message_type' => 'foobar']))
        ->toThrow(QueryException::class);
});

it('rejects an unknown status via CHECK constraint', function (): void {
    expect(fn () => DiscordOutboundMessage::factory()->create(['status' => 'banana']))
        ->toThrow(QueryException::class);
});

it('accepts each valid message_type enum value', function (): void {
    foreach (['match_announce', 'role_sync', 'generic'] as $type) {
        $row = DiscordOutboundMessage::factory()->create(['message_type' => $type]);
        expect($row->message_type)->toBe($type);
    }
});

it('accepts each valid status enum value', function (): void {
    foreach (['pending', 'dispatching', 'sent', 'failed'] as $status) {
        $row = DiscordOutboundMessage::factory()->create(['status' => $status]);
        expect($row->status)->toBe($status);
    }
});

it('nullifies causer_user_id when the causer User is deleted (nullOnDelete)', function (): void {
    $causer = User::factory()->create();
    $row = DiscordOutboundMessage::factory()->create(['causer_user_id' => $causer->id]);

    $causer->forceDelete();

    $reloaded = DiscordOutboundMessage::findOrFail($row->id);
    expect($reloaded->causer_user_id)->toBeNull();
});

it('exposes causer BelongsTo relation', function (): void {
    $causer = User::factory()->create();
    $row = DiscordOutboundMessage::factory()->create(['causer_user_id' => $causer->id]);

    expect($row->causer?->id)->toBe($causer->id);
});

it('logs activity on create (D-012)', function (): void {
    $row = DiscordOutboundMessage::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

it('logs activity on status transition (D-012)', function (): void {
    $row = DiscordOutboundMessage::factory()->pending()->create();

    $row->update(['status' => 'dispatching', 'attempts' => 1]);

    $activity = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $row->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    /** @var array<string, mixed> $changes */
    $changes = $activity?->attribute_changes?->toArray() ?? [];
    expect($changes['attributes']['status'] ?? null)->toBe('dispatching');
    expect($changes['old']['status'] ?? null)->toBe('pending');
});
