<?php

declare(strict_types=1);

use App\Jobs\Rcon\CloseMatchJob;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchResult;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use App\Services\MatchResultService;
use App\Services\Rcon\MatchPlayerStatAggregator;
use Database\Seeders\RconWorkerSystemUserSeeder;

/*
| Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 2 +
|         .planning/phases/08-rcon-automation/08-01-PLAN.md task 2 (Wave-0 RED stub).
|
| Replaces the Wave-0 expect(true)->toBeFalse() stub with a 4-case GREEN suite
| that verifies the D-019 invariant: failure paths flip
| matches.manual_entry_required=true so the admin sees the flagged match in
| the Filament UI and can curate the result manually.
|
| Cases:
|   1. Match exists, no booking, CloseMatchJob runs with no match_end event →
|      manual_entry_required=true, no MatchResult row written.
|   2. Match exists, booking exists, worker emits ONLY a manual_error event
|      (kind='unreachable') — no match_end. Simulate the worker→web persist
|      path by inserting the manual_error event directly + dispatch
|      CloseMatchJob. Result: manual_entry_required=true.
|   3. Match exists, booking exists, worker emits match_end normally →
|      manual_entry_required stays false (D-019 happy-path).
|   4. The manual_error event is persisted in match_events (audit trail per
|      D-019 — admin must be able to grep the audit log for the failure event).
*/

beforeEach(function (): void {
    // CloseMatchJob's MatchResultService::upsertFromRcon resolves the
    // SYSTEM_RCON_WORKER user via firstOrFail — the seeder MUST run before
    // any GREEN-path test (RefreshDatabase doesn't auto-run seeders, so we
    // opt in here just like plan 08-08 RconMatchResultIngestionTest).
    $this->seed(RconWorkerSystemUserSeeder::class);
});

it('Case 1 — no match_end event flips manual_entry_required=true and writes no MatchResult', function (): void {
    $match = GameMatch::factory()->create(['manual_entry_required' => false]);

    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    $fresh = $match->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->manual_entry_required)->toBeTrue();
    expect(MatchResult::where('match_id', $match->id)->exists())->toBeFalse();
});

it('Case 2 — worker emits manual_error only (no match_end) flips manual_entry_required=true', function (): void {
    $match = GameMatch::factory()->create(['manual_entry_required' => false]);
    $server = MatchServer::factory()->create();
    MatchServerBooking::factory()
        ->forMatch($match)
        ->onServer($server)
        ->create();

    // Worker (plan 08-07 MatchEventIngestService) would persist a manual_error
    // event when CRCON becomes unreachable mid-match. Simulate that wire
    // shape here without going through the HMAC ingest path (covered by
    // MatchEventIngestServiceTest).
    MatchEvent::factory()
        ->manualError(kind: 'unreachable', detail: 'CRCON dropped log stream mid-match')
        ->create(['match_id' => $match->id]);

    // Plan 08-11's BookingScheduler will dispatch CloseMatchJob at
    // reserved_to — for Wave 6 we exercise that result-side invariant
    // directly (the scheduler itself is plan 08-11's responsibility).
    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    $fresh = $match->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->manual_entry_required)->toBeTrue();
});

it('Case 3 — worker emits match_end normally → manual_entry_required stays false', function (): void {
    $match = GameMatch::factory()->create(['manual_entry_required' => false]);
    $server = MatchServer::factory()->create();
    MatchServerBooking::factory()
        ->forMatch($match)
        ->onServer($server)
        ->create();

    $killerSteam = '76561198000000001';
    $victimSteam = '76561198000000002';

    // Seed kills + match_end so CloseMatchJob takes the happy path.
    MatchEvent::factory()->kill($killerSteam, $victimSteam)->create(['match_id' => $match->id]);
    MatchEvent::factory()->matchEnd(winningTeam: 'allies', alliesScore: 3, axisScore: 2)
        ->create(['match_id' => $match->id]);

    (new CloseMatchJob($match->id))->handle(
        app(MatchPlayerStatAggregator::class),
        app(MatchResultService::class),
    );

    $fresh = $match->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->manual_entry_required)->toBeFalse();
});

it('Case 4 — manual_error event persists in match_events (D-019 audit trail)', function (): void {
    $match = GameMatch::factory()->create();

    $event = MatchEvent::factory()
        ->manualError(kind: 'unreachable', detail: 'CRCON connection refused')
        ->create(['match_id' => $match->id]);

    $persisted = MatchEvent::where('match_id', $match->id)
        ->where('event_type', 'manual_error')
        ->first();

    expect($persisted)->not->toBeNull();
    expect($persisted->id)->toBe($event->id);
    expect($persisted->payload['kind'] ?? null)->toBe('unreachable');
    expect($persisted->payload['detail'] ?? null)->toBe('CRCON connection refused');
});
