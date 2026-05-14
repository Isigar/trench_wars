<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
| Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 08-01. Exercises the composite UNIQUE on
| match_events.(match_id, crcon_stream_id) installed by plan 08-02 migration.
|
| Threat mitigated: T-08-04-01 (replayed CRCON event from buggy worker reconnect).
|
| Idempotency semantics covered:
|   1. First INSERT (match A, stream-id "X") → succeeds.
|   2. Second INSERT (match A, stream-id "X") → REJECTED with UNIQUE violation.
|   3. INSERT (match B, stream-id "X") → succeeds (composite key, not stream alone).
|   4. Two INSERTs with NULL stream-id under the same match → BOTH succeed
|      (Postgres UNIQUE treats NULL ≠ NULL, permitting pre-CRCON synthetic events
|      such as manual_error rows that have no upstream stream id).
*/

it('inserts a match_event with a fresh crcon_stream_id', function (): void {
    $match = GameMatch::factory()->create();

    $event = MatchEvent::factory()->kill('111', '222')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => '1711657986-0',
    ]);

    expect($event->exists)->toBeTrue();
    expect($event->crcon_stream_id)->toBe('1711657986-0');
});

it('rejects a duplicate (match_id, crcon_stream_id) with a UNIQUE violation', function (): void {
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->kill('111', '222')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => '1711657986-0',
    ]);

    $threw = false;
    try {
        MatchEvent::factory()->kill('333', '444')->create([
            'match_id' => $match->id,
            'crcon_stream_id' => '1711657986-0',
        ]);
    } catch (UniqueConstraintViolationException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('match_events_match_stream_unique');
    }
    expect($threw)->toBeTrue();
});

it('allows the same crcon_stream_id under a different match (composite UNIQUE)', function (): void {
    $matchA = GameMatch::factory()->create();
    $matchB = GameMatch::factory()->create();

    $eventA = MatchEvent::factory()->kill('111', '222')->create([
        'match_id' => $matchA->id,
        'crcon_stream_id' => '1711657986-7',
    ]);

    $eventB = MatchEvent::factory()->kill('333', '444')->create([
        'match_id' => $matchB->id,
        'crcon_stream_id' => '1711657986-7',
    ]);

    expect($eventA->exists)->toBeTrue();
    expect($eventB->exists)->toBeTrue();
    expect($eventA->id)->not->toBe($eventB->id);
});

it('allows multiple NULL crcon_stream_id rows under the same match (NULL ≠ NULL in Postgres UNIQUE)', function (): void {
    $match = GameMatch::factory()->create();

    $eventOne = MatchEvent::factory()->manualError('unreachable')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => null,
    ]);

    $eventTwo = MatchEvent::factory()->manualError('auth_failed')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => null,
    ]);

    expect($eventOne->exists)->toBeTrue();
    expect($eventTwo->exists)->toBeTrue();

    // sanity: two distinct rows persisted under the same match with null stream id
    $count = DB::table('match_events')
        ->where('match_id', $match->id)
        ->whereNull('crcon_stream_id')
        ->count();
    expect($count)->toBe(2);
});
