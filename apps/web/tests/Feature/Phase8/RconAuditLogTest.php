<?php

declare(strict_types=1);

use App\Jobs\Rcon\TestMatchServerConnectionJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\Rcon\CrconHealthProbe;
use Database\Seeders\RconWorkerSystemUserSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/08-rcon-automation/08-12-PLAN.md task 2 (D-012 compliance).
|
| Verifies that every Phase 8 write surface fires the expected activity_log row.
| The Phase 1-7 audit infrastructure (spatie/activitylog + Filament admin UI in
| plan 01-13) is the canonical surface; this test pins the Phase 8 entries:
|
| Cases:
|   1. MatchServer::factory()->create() → activity_log row with
|      subject_type='App\Models\MatchServer', event='created'.
|   2. TestMatchServerConnectionJob updates last_test_status → an updated event
|      lands on the MatchServer subject (LogsActivity captures the fillable diff).
|   3. MatchResultService::upsertFromRcon writes a MatchResult row whose audit
|      `description` mentions 'MatchResult' (the model's setDescriptionForEvent
|      callback). Threaded with __('rcon.audit.automated_from_crcon') notes.
|   4. Manual-override-locks-RCON path writes an activity_log row with
|      properties.event='rcon.arrived_but_manual_locked' (D-04-12-A —
|      explicit withProperties channel; ManualOverrideWinsTest case 2 also
|      asserts this — we re-assert here in a Phase-8-audit-focused suite to
|      keep CI fanout localised when the path regresses).
|   5. MatchServerBooking::factory()->create() → activity_log row with
|      event='created'.
*/

beforeEach(function (): void {
    $this->seed(RconWorkerSystemUserSeeder::class);
});

// ---------------------------------------------------------------------------
// Case 1: MatchServer create → activity_log entry.
// ---------------------------------------------------------------------------

it('logs a MatchServer created activity row when a server is provisioned', function (): void {
    $server = MatchServer::factory()->create();

    $log = Activity::query()
        ->where('subject_type', MatchServer::class)
        ->where('subject_id', $server->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('MatchServer');
});

// ---------------------------------------------------------------------------
// Case 2: TestConnection job updates MatchServer → updated activity row.
// ---------------------------------------------------------------------------

it('logs a MatchServer updated activity row when TestMatchServerConnectionJob updates last_test_status', function (): void {
    Http::fake([
        '*' => Http::response(['result' => 'ok'], 200),
    ]);

    $server = MatchServer::factory()->create([
        'credentials_encrypted' => ['api_token' => 'test-token'],
        'last_test_status' => null,
        'last_test_error' => null,
    ]);

    // Snapshot the 'updated' count before the job — factory()->create() never
    // emits an 'updated' event, but other observers might. The post-job count
    // must increase by ≥1 for THIS server.
    $beforeUpdatedCount = Activity::query()
        ->where('subject_type', MatchServer::class)
        ->where('subject_id', $server->id)
        ->where('event', 'updated')
        ->count();

    (new TestMatchServerConnectionJob($server->id))->handle(app(CrconHealthProbe::class));

    $afterUpdatedCount = Activity::query()
        ->where('subject_type', MatchServer::class)
        ->where('subject_id', $server->id)
        ->where('event', 'updated')
        ->count();

    expect($afterUpdatedCount)->toBeGreaterThan($beforeUpdatedCount);

    $server->refresh();
    expect($server->last_test_status)->toBe('ok');
});

// ---------------------------------------------------------------------------
// Case 3: upsertFromRcon writes a MatchResult activity row.
// ---------------------------------------------------------------------------

it('logs a MatchResult created activity row when upsertFromRcon lands a new row', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);

    app(MatchResultService::class)->upsertFromRcon($match, [
        'allies_score' => 3,
        'axis_score' => 1,
        'recorded_at' => now(),
    ]);

    $result = MatchResult::where('match_id', $match->id)->firstOrFail();

    $log = Activity::query()
        ->where('subject_type', MatchResult::class)
        ->where('subject_id', $result->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
    // The MatchResult model's setDescriptionForEvent emits "MatchResult {event}".
    expect($log->description)->toContain('MatchResult');

    // Default notes string is __('rcon.audit.automated_from_crcon') (plan 08-08).
    expect($result->notes)->toBe(__('rcon.audit.automated_from_crcon'));
});

// ---------------------------------------------------------------------------
// Case 4: Manual override locks → activity_log row with explicit event property.
// ---------------------------------------------------------------------------

it('logs an activity row with properties.event=rcon.arrived_but_manual_locked when RCON arrives after a manual result', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    // Step 1 — admin enters manual result (source='manual' by DB DEFAULT).
    app(MatchResultService::class)->upsert($match, [
        'allies_score' => 5,
        'axis_score' => 5,
        'recorded_at' => now(),
    ], $causer);

    // Step 2 — RCON job arrives later; service refuses to overwrite +
    // emits the audit-trail row.
    app(MatchResultService::class)->upsertFromRcon($match, [
        'allies_score' => 3,
        'axis_score' => 1,
        'recorded_at' => now(),
    ]);

    $log = Activity::query()
        ->where('properties->event', 'rcon.arrived_but_manual_locked')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toBe(__('rcon.audit.rcon_arrived_locked'));

    /** @var array<string, mixed> $properties */
    $properties = $log->properties->toArray();
    expect($properties['event'])->toBe('rcon.arrived_but_manual_locked');
    expect($properties['would_have_set']['allies_score'])->toBe(3);
    expect($properties['would_have_set']['axis_score'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Case 5: MatchServerBooking create → activity_log entry.
// ---------------------------------------------------------------------------

it('logs a MatchServerBooking created activity row when a booking is provisioned', function (): void {
    $server = MatchServer::factory()->create();
    $match = GameMatch::factory()->create();
    $booking = MatchServerBooking::factory()->forMatch($match)->onServer($server)->create();

    $log = Activity::query()
        ->where('subject_type', MatchServerBooking::class)
        ->where('subject_id', $booking->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
});
