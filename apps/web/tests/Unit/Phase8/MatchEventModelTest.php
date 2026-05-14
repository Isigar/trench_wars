<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 2.
| Asserts MatchEvent model + DB-tier invariants:
|   1. event_type CHECK rejects values outside the canonical 10-value enum.
|   2. scopeOfType() filters by event_type.
|   3. scopeSince() filters by occurred_at.
|   4. payload jsonb roundtrips through the array cast.
*/

it('rejects an event_type outside the canonical 10-value enum at the DB tier', function (): void {
    $match = GameMatch::factory()->create();

    $threw = false;
    try {
        DB::table('match_events')->insert([
            'id' => Str::uuid()->toString(),
            'match_id' => $match->id,
            'event_type' => 'foo',
            'crcon_action' => null,
            'crcon_stream_id' => null,
            'payload' => json_encode(['detail' => 'bogus']),
            'occurred_at' => now(),
            'ingested_at' => now(),
        ]);
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('match_events_type_check');
    }
    expect($threw)->toBeTrue();
});

it('scopeOfType filters MatchEvent rows by event_type', function (): void {
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->kill('111', '222')->create(['match_id' => $match->id, 'crcon_stream_id' => '1-0']);
    MatchEvent::factory()->kill('333', '444')->create(['match_id' => $match->id, 'crcon_stream_id' => '1-1']);
    MatchEvent::factory()->connect('555', 'Foo')->create(['match_id' => $match->id, 'crcon_stream_id' => '1-2']);

    $kills = MatchEvent::query()->ofType('player_kill')->get();
    expect($kills)->toHaveCount(2);
    expect($kills->pluck('event_type')->unique()->all())->toBe(['player_kill']);
});

it('scopeSince filters MatchEvent rows by occurred_at lower bound', function (): void {
    $match = GameMatch::factory()->create();

    MatchEvent::factory()->kill('111', '222')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => '2-0',
        'occurred_at' => now()->subDays(2),
    ]);
    $recent = MatchEvent::factory()->kill('333', '444')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => '2-1',
        'occurred_at' => now()->subMinutes(10),
    ]);

    $since = MatchEvent::query()->since(now()->subHour())->get();
    expect($since)->toHaveCount(1);
    expect($since->first()->id)->toBe($recent->id);
});

it('roundtrips the jsonb payload through the array cast', function (): void {
    $match = GameMatch::factory()->create();

    $event = MatchEvent::factory()->kill('111', '222')->create([
        'match_id' => $match->id,
        'crcon_stream_id' => '3-0',
    ]);

    $event->payload = ['weapon' => 'K98', 'distance_m' => 42];
    $event->save();
    $event->refresh();

    expect($event->payload)->toBeArray();
    expect($event->payload['weapon'])->toBe('K98');
    expect($event->payload['distance_m'])->toBe(42);
});
