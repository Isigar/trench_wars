<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use Illuminate\Database\QueryException;

/*
| Source: .planning/phases/08-rcon-automation/08-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 08-01. Exercises the Postgres EXCLUDE
| USING gist constraint installed by plan 08-02 migration.
|
| Threat mitigated: T-08-02-01 (double-booking same server).
|
| EXCLUDE semantics covered:
|   - Same server, overlapping active windows → REJECTED (exclusion_violation).
|   - Back-to-back bookings sharing an endpoint → ACCEPTED (half-open `[)` range).
|   - Cancelled booking → does NOT block (partial WHERE status='active' clause).
|   - Different server, identical window → ACCEPTED (server_id WITH = predicate).
*/

it('rejects overlapping bookings on the same server with EXCLUDE violation', function (): void {
    $server = MatchServer::factory()->create();
    $matchA = GameMatch::factory()->create();
    $matchB = GameMatch::factory()->create();

    MatchServerBooking::factory()
        ->onServer($server)
        ->forMatch($matchA)
        ->create([
            'reserved_from' => '2026-06-01 10:00:00+00',
            'reserved_to' => '2026-06-01 12:00:00+00',
            'status' => 'active',
        ]);

    expect(function () use ($server, $matchB): void {
        MatchServerBooking::factory()
            ->onServer($server)
            ->forMatch($matchB)
            ->create([
                'reserved_from' => '2026-06-01 11:00:00+00',
                'reserved_to' => '2026-06-01 13:00:00+00',
                'status' => 'active',
            ]);
    })->toThrow(QueryException::class, 'match_server_bookings_no_overlap');
});

it('accepts back-to-back bookings sharing an endpoint (half-open [) range)', function (): void {
    $server = MatchServer::factory()->create();
    $matchA = GameMatch::factory()->create();
    $matchC = GameMatch::factory()->create();

    MatchServerBooking::factory()
        ->onServer($server)
        ->forMatch($matchA)
        ->create([
            'reserved_from' => '2026-06-02 10:00:00+00',
            'reserved_to' => '2026-06-02 12:00:00+00',
            'status' => 'active',
        ]);

    $bookingC = MatchServerBooking::factory()
        ->onServer($server)
        ->forMatch($matchC)
        ->create([
            'reserved_from' => '2026-06-02 12:00:00+00',
            'reserved_to' => '2026-06-02 14:00:00+00',
            'status' => 'active',
        ]);

    expect($bookingC->exists)->toBeTrue();
});

it('allows a new overlapping booking once the previous one is cancelled', function (): void {
    $server = MatchServer::factory()->create();
    $matchA = GameMatch::factory()->create();
    $matchD = GameMatch::factory()->create();

    $bookingA = MatchServerBooking::factory()
        ->onServer($server)
        ->forMatch($matchA)
        ->create([
            'reserved_from' => '2026-06-03 10:00:00+00',
            'reserved_to' => '2026-06-03 12:00:00+00',
            'status' => 'active',
        ]);

    $bookingA->update(['status' => 'cancelled']);

    $bookingD = MatchServerBooking::factory()
        ->onServer($server)
        ->forMatch($matchD)
        ->create([
            'reserved_from' => '2026-06-03 10:30:00+00',
            'reserved_to' => '2026-06-03 11:30:00+00',
            'status' => 'active',
        ]);

    expect($bookingD->exists)->toBeTrue();
    expect($bookingD->status)->toBe('active');
});

it('allows the same window on a different server (server_id WITH = predicate)', function (): void {
    $serverOne = MatchServer::factory()->create();
    $serverTwo = MatchServer::factory()->create();
    $matchA = GameMatch::factory()->create();
    $matchE = GameMatch::factory()->create();

    MatchServerBooking::factory()
        ->onServer($serverOne)
        ->forMatch($matchA)
        ->create([
            'reserved_from' => '2026-06-04 10:00:00+00',
            'reserved_to' => '2026-06-04 12:00:00+00',
            'status' => 'active',
        ]);

    $bookingE = MatchServerBooking::factory()
        ->onServer($serverTwo)
        ->forMatch($matchE)
        ->create([
            'reserved_from' => '2026-06-04 10:00:00+00',
            'reserved_to' => '2026-06-04 12:00:00+00',
            'status' => 'active',
        ]);

    expect($bookingE->exists)->toBeTrue();
});
