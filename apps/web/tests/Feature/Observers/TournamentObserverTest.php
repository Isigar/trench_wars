<?php

declare(strict_types=1);

/*
| Wave 5 GREEN — replaces Wave 0 RED stub from plan 06-01.
| Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 2 +
|         06-RESEARCH.md § TournamentObserver polymorphic Event sync.
|
| Covers the three TournamentObserver side-effects:
|   1. saved()   — Event upsert on public + non-cancelled; delete otherwise
|   2. created() — tournament_announce outbound row on public create
|   3. updated() — tournament_announce_update only when status changes
|
| Pitfall 7 mitigation (T-06-10-02): updated() gates on wasChanged('status');
| title/max_participants/etc. edits MUST NOT enqueue outbound rows.
*/

use App\Models\DiscordOutboundMessage;
use App\Models\Event;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Event polymorphic sync — saved() invariants
// ---------------------------------------------------------------------------

it('upserts an Event row on Tournament save when is_public=true and status != cancelled', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);

    $event = Event::where('eventable_type', Tournament::class)
        ->where('eventable_id', $tournament->id)
        ->first();

    expect($event)->not->toBeNull();
    assert($event !== null);
    expect($event->is_public)->toBeTrue()
        ->and($event->eventable_type)->toBe(Tournament::class);
});

it('does NOT create an Event row when a private tournament is saved', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => false, 'status' => 'draft']);

    expect(Event::where('eventable_type', Tournament::class)
        ->where('eventable_id', $tournament->id)
        ->count())->toBe(0);
});

it('deletes the Event row when the tournament transitions to cancelled', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);
    expect(Event::where('eventable_type', Tournament::class)
        ->where('eventable_id', $tournament->id)
        ->count())->toBe(1);

    $tournament->update(['status' => 'cancelled']);

    expect(Event::where('eventable_type', Tournament::class)
        ->where('eventable_id', $tournament->id)
        ->count())->toBe(0);
});

it('deletes the Event row when is_public flips to false', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);
    expect(Event::where('eventable_type', Tournament::class)
        ->where('eventable_id', $tournament->id)
        ->count())->toBe(1);

    $tournament->update(['is_public' => false]);

    expect(Event::where('eventable_type', Tournament::class)
        ->where('eventable_id', $tournament->id)
        ->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// created() — Discord outbound announce on public create
// ---------------------------------------------------------------------------

it('enqueues a tournament_announce outbound row when a public tournament is created', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);

    $outbound = DiscordOutboundMessage::where('message_type', 'tournament_announce')->first();

    expect($outbound)->not->toBeNull();
    assert($outbound !== null);
    /** @var array<string, mixed> $payload */
    $payload = $outbound->payload;
    expect($outbound->status)->toBe('pending')
        ->and($payload['tournament_id'])->toBe($tournament->id)
        ->and($payload['tournament_slug'])->toBe($tournament->slug)
        ->and($payload['is_public'])->toBeTrue();
});

it('does NOT enqueue tournament_announce when is_public=false on create', function (): void {
    Tournament::factory()->create(['is_public' => false, 'status' => 'draft']);

    expect(DiscordOutboundMessage::where('message_type', 'tournament_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// updated() — wasChanged('status') Pitfall 7 gate
// ---------------------------------------------------------------------------

it('enqueues tournament_announce_update when status changes on a public tournament', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);
    // Wipe the create-time announce so the assertion is unambiguous.
    DiscordOutboundMessage::query()->delete();

    $tournament->update(['status' => 'registering']);

    $outbound = DiscordOutboundMessage::where('message_type', 'tournament_announce_update')->first();
    expect($outbound)->not->toBeNull();
    assert($outbound !== null);
    /** @var array<string, mixed> $payload */
    $payload = $outbound->payload;
    expect($outbound->status)->toBe('pending')
        ->and($payload['status'])->toBe('registering');
});

it('does NOT enqueue tournament_announce_update on non-status updates (Pitfall 7)', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true, 'status' => 'draft']);
    DiscordOutboundMessage::query()->delete();

    $tournament->update(['max_participants' => 16]);

    expect(DiscordOutboundMessage::where('message_type', 'tournament_announce_update')->count())->toBe(0);
});

it('does NOT enqueue tournament_announce_update for a private tournament status change', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => false, 'status' => 'draft']);
    DiscordOutboundMessage::query()->delete();

    $tournament->update(['status' => 'registering']);

    expect(DiscordOutboundMessage::where('message_type', 'tournament_announce_update')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// payload shape sanity check (D-06-10 buildTournamentAnnounce contract)
// ---------------------------------------------------------------------------

it('writes a tournament_announce payload with the canonical shape (D-06-10 contract)', function (): void {
    $tournament = Tournament::factory()->create([
        'is_public' => true,
        'status' => 'registering',
        'format' => 'single_elimination',
        'max_participants' => 8,
    ]);

    /** @var DiscordOutboundMessage $outbound */
    $outbound = DiscordOutboundMessage::where('message_type', 'tournament_announce')->firstOrFail();
    /** @var array<string, mixed> $payload */
    $payload = $outbound->payload;

    expect($payload)->toHaveKeys([
        'kind',
        'tournament_id',
        'tournament_slug',
        'title',
        'format',
        'status',
        'starts_at',
        'ends_at',
        'organiser_user_id',
        'max_participants',
        'is_public',
    ])
        ->and($payload['kind'])->toBe('tournament_announce')
        ->and($payload['format'])->toBe('single_elimination')
        ->and($payload['max_participants'])->toBe(8);
});
