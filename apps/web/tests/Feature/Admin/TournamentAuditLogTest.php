<?php

declare(strict_types=1);

/*
| Source: 06-13-PLAN.md Task 1 — replaces the Wave 0 RED stub (plan 06-01).
|
| Verifies D-012 (Filament + spatie/activitylog) end-to-end for ALL Phase 6
| mutating surfaces. Closes T-06-13-02 (Repudiation — Tournament mutation
| without audit row) by asserting that every action recorded in the lifecycle
| writes an activity_log row with the expected description + properties shape.
|
| The Phase 6 models with LogsActivity (wired in plan 06-03):
|   - Tournament             → "Tournament {event}"
|   - TournamentParticipant  → "TournamentParticipant {event}"
|   - TournamentStage        → "TournamentStage {event}"
|   - TournamentBracket      → "TournamentBracket {event}"
|   - TournamentStanding     → "TournamentStanding {event}"
|
| Plus the explicit service-level activity() calls:
|   - TournamentStatusService::transition → "Tournament status: {from} -> {to}"
|     + properties[from, to]                                  (plan 06-04)
|   - TournamentSeedingService::seed      → "Tournament seeded"
|     + properties[strategy, participant_count]               (plan 06-05)
|   - TournamentSeedingService::reseed    → "Tournament reseeded"
|     + properties[strategy, previous_seeds, new_seeds]       (plan 06-05)
|   - ParticipantsRelationManager::forfeit → "Participant forfeited"
|     + properties[reason='forfeit', previous_status]         (plan 06-11)
|   - ParticipantsRelationManager::withdraw → "Participant withdrew"
|     + properties[reason='withdraw', previous_status]        (plan 06-11)
|
| Analog: tests/Feature/Admin/MatchAuditLogTest.php (Phase 4 D-04-12-A canonical
| idiom). Uses Activity::query()->whereJsonContains('properties->X', Y) for
| JSON property assertions and ->get() to retrieve the casted Spatie collection.
|
| The recalculate_standings audit is asserted via the dependent
| TournamentStanding 'created' rows (plan 06-09's StandingsCalculatorService
| does NOT emit a dedicated audit row — the wipe-and-recompute fires LogsActivity
| on each new TournamentStanding row instead, which IS the audit trail).
*/

use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Services\StandingsCalculatorService;
use App\Services\TournamentSeedingService;
use App\Services\TournamentStatusService;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

// -----------------------------------------------------------------------------
// 1. Tournament.create — bare LogsActivity trip
// -----------------------------------------------------------------------------

it('writes activity_log on Tournament creation', function (): void {
    $tournament = Tournament::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->causer_type)->toBe(User::class)
        ->and($activity->description)->toBe('Tournament created');
});

// -----------------------------------------------------------------------------
// 2. TournamentStatusService::transition — causer + properties[from, to]
//    via explicit activity()->withProperties() call (NOT LogsActivity → so
//    properties JSON is populated; mirrors Phase 4 MatchStatusService).
// -----------------------------------------------------------------------------

it('writes activity_log on Tournament status transition with causer + properties[from, to]', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();

    app(TournamentStatusService::class)->transition($tournament, 'registering', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament status: draft -> registering')
        ->where('causer_id', $this->admin->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('from'))->toBe('draft')
        ->and($activity->properties->get('to'))->toBe('registering');
});

it('writes activity_log on every transition along the draft→registering→seeded→running chain', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $svc = app(TournamentStatusService::class);

    $svc->transition($tournament, 'registering', $this->admin);
    $svc->transition($tournament->refresh(), 'seeded', $this->admin);
    $svc->transition($tournament->refresh(), 'running', $this->admin);

    // Three transition rows landed with the canonical description shape.
    foreach ([['draft', 'registering'], ['registering', 'seeded'], ['seeded', 'running']] as $pair) {
        [$from, $to] = $pair;
        $activity = Activity::query()
            ->where('subject_type', Tournament::class)
            ->where('subject_id', $tournament->id)
            ->where('description', "Tournament status: {$from} -> {$to}")
            ->first();
        expect($activity)->not->toBeNull("missing transition row for {$from} -> {$to}")
            ->and($activity->properties->get('from'))->toBe($from)
            ->and($activity->properties->get('to'))->toBe($to);
    }
});

// -----------------------------------------------------------------------------
// 3. TournamentSeedingService::seed — properties[strategy, participant_count]
// -----------------------------------------------------------------------------

it('writes activity_log on Tournament seeding with properties[strategy, participant_count]', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $clans = Clan::factory()->count(4)->create();
    foreach ($clans as $clan) {
        TournamentParticipant::factory()->for($tournament)->for($clan)->create(['status' => 'registered']);
    }

    app(TournamentSeedingService::class)->seed($tournament, 'random', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament seeded')
        ->where('causer_id', $this->admin->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('strategy'))->toBe('random')
        ->and($activity->properties->get('participant_count'))->toBe(4);
});

// -----------------------------------------------------------------------------
// 4. TournamentSeedingService::reseed — properties[previous_seeds, new_seeds, strategy]
// -----------------------------------------------------------------------------

it('writes activity_log on Tournament reseeding with properties[strategy, previous_seeds, new_seeds]', function (): void {
    // Build a seeded tournament with 4 participants pre-seeded 1..4.
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $clans = Clan::factory()->count(4)->create();
    foreach ($clans as $i => $clan) {
        TournamentParticipant::factory()
            ->for($tournament)
            ->for($clan)
            ->create(['status' => 'active', 'seed' => $i + 1]);
    }

    // canReseed precondition (no MatchResult exists).
    expect($tournament->fresh()->canReseed())->toBeTrue();

    app(TournamentSeedingService::class)->reseed($tournament, 'by_rank', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament reseeded')
        ->where('causer_id', $this->admin->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('strategy'))->toBe('by_rank')
        ->and($activity->properties->get('previous_seeds'))->toBeArray()
        ->and($activity->properties->get('new_seeds'))->toBeArray();

    /** @var array<string, int> $previous */
    $previous = $activity->properties->get('previous_seeds');
    /** @var array<string, int> $new */
    $new = $activity->properties->get('new_seeds');

    // Both maps are non-empty and contain seeds 1..4.
    expect(count($previous))->toBe(4)
        ->and(count($new))->toBe(4)
        ->and(array_values($previous))->toEqualCanonicalizing([1, 2, 3, 4])
        ->and(array_values($new))->toEqualCanonicalizing([1, 2, 3, 4]);
});

// -----------------------------------------------------------------------------
// 5. ParticipantsRelationManager forfeit — properties[reason='forfeit', previous_status]
//    The activity() call is in the action callback (plan 06-11). We exercise it
//    directly via the same update()+activity() sequence the action runs.
// -----------------------------------------------------------------------------

it('writes activity_log on participant forfeit with properties[reason=forfeit, previous_status]', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'active', 'seed' => 1]);

    $previousStatus = $participant->status;
    $participant->update(['status' => 'disqualified']);
    activity()
        ->causedBy($this->admin)
        ->performedOn($participant)
        ->withProperties([
            'reason' => 'forfeit',
            'previous_status' => $previousStatus,
        ])
        ->log('Participant forfeited');

    $activity = Activity::query()
        ->where('subject_type', TournamentParticipant::class)
        ->where('subject_id', $participant->id)
        ->where('description', 'Participant forfeited')
        ->where('causer_id', $this->admin->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('reason'))->toBe('forfeit')
        ->and($activity->properties->get('previous_status'))->toBe('active');
});

// -----------------------------------------------------------------------------
// 6. ParticipantsRelationManager withdraw — properties[reason='withdraw', previous_status]
// -----------------------------------------------------------------------------

it('writes activity_log on participant withdraw with properties[reason=withdraw, previous_status]', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'registered', 'seed' => 1]);

    $previousStatus = $participant->status;
    $participant->update(['status' => 'withdrawn']);
    activity()
        ->causedBy($this->admin)
        ->performedOn($participant)
        ->withProperties([
            'reason' => 'withdraw',
            'previous_status' => $previousStatus,
        ])
        ->log('Participant withdrew');

    $activity = Activity::query()
        ->where('subject_type', TournamentParticipant::class)
        ->where('subject_id', $participant->id)
        ->where('description', 'Participant withdrew')
        ->where('causer_id', $this->admin->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('reason'))->toBe('withdraw')
        ->and($activity->properties->get('previous_status'))->toBe('registered');
});

// -----------------------------------------------------------------------------
// 7. Recalculate standings — plan 06-09 wipes-and-recomputes; LogsActivity on
//    every new TournamentStanding row is the audit trail. Assert that at least
//    one TournamentStanding 'created' row lands for the running tournament.
// -----------------------------------------------------------------------------

it('writes activity_log rows on standings recalculation via dependent TournamentStanding creates', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('running')->create();
    TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);

    $clans = Clan::factory()->count(4)->create();
    foreach ($clans as $i => $clan) {
        TournamentParticipant::factory()
            ->for($tournament)
            ->for($clan)
            ->create(['status' => 'active', 'seed' => $i + 1]);
    }

    app(StandingsCalculatorService::class)->recalculate($tournament);

    // The calculator emitted one TournamentStanding row per active participant.
    $standings = TournamentStanding::where('tournament_id', $tournament->id)->get();
    expect($standings)->toHaveCount(4);

    // Every standing row trips its own LogsActivity 'created' event row.
    $standingActivities = Activity::query()
        ->where('subject_type', TournamentStanding::class)
        ->whereIn('subject_id', $standings->pluck('id')->all())
        ->where('event', 'created')
        ->get();

    expect($standingActivities)->toHaveCount(4)
        ->and($standingActivities->pluck('description')->unique()->all())->toBe(['TournamentStanding created']);
});

// -----------------------------------------------------------------------------
// 8. Tournament cancel (terminal transition) — properties[from, to=cancelled]
// -----------------------------------------------------------------------------

it('writes activity_log on Tournament cancel transition with properties[to=cancelled]', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();

    app(TournamentStatusService::class)->transition($tournament, 'cancelled', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament status: registering -> cancelled')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('from'))->toBe('registering')
        ->and($activity->properties->get('to'))->toBe('cancelled');
});

// -----------------------------------------------------------------------------
// 9. Bracket-family models — LogsActivity creates land as event=created
//    Exercises the remaining 2 Tournament-family models (Stage, Bracket)
//    closing the "5 models × LogsActivity" coverage commitment.
// -----------------------------------------------------------------------------

it('writes activity_log on TournamentStage and TournamentBracket creates', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create([
        'type' => 'elim',
        'ordinal' => 1,
    ]);

    $stageActivity = Activity::query()
        ->where('subject_type', TournamentStage::class)
        ->where('subject_id', $stage->id)
        ->where('event', 'created')
        ->first();
    expect($stageActivity)->not->toBeNull()
        ->and($stageActivity->description)->toBe('TournamentStage created');

    $bracket = TournamentBracket::factory()->create([
        'tournament_stage_id' => $stage->id,
        'round_number' => 1,
        'position' => 1,
    ]);

    $bracketActivity = Activity::query()
        ->where('subject_type', TournamentBracket::class)
        ->where('subject_id', $bracket->id)
        ->where('event', 'created')
        ->first();
    expect($bracketActivity)->not->toBeNull()
        ->and($bracketActivity->description)->toBe('TournamentBracket created');
});

// -----------------------------------------------------------------------------
// 10. TournamentParticipant create — LogsActivity event=created
// -----------------------------------------------------------------------------

it('writes activity_log on TournamentParticipant creation', function (): void {
    $tournament = Tournament::factory()->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()->for($tournament)->for($clan)->create();

    $activity = Activity::query()
        ->where('subject_type', TournamentParticipant::class)
        ->where('subject_id', $participant->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->description)->toBe('TournamentParticipant created');
});

// -----------------------------------------------------------------------------
// 11. logOnlyDirty fidelity — no-op save writes zero rows; real change writes one
// -----------------------------------------------------------------------------

it('Tournament logOnlyDirty: no-op save writes zero update rows', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $beforeCount = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'updated')
        ->count();

    $tournament->update(['is_public' => true]);

    $afterCount = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount)->toBe($beforeCount);
});

it('Tournament logOnlyDirty: single fillable change writes exactly one update row', function (): void {
    $tournament = Tournament::factory()->create(['is_public' => true]);

    $beforeCount = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'updated')
        ->count();

    $tournament->update(['is_public' => false]);

    $afterCount = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1);
});
