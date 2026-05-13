<?php

declare(strict_types=1);

use App\Exceptions\TournamentStatusInvalidTransitionException;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentStatusService;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 06-04-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers the TournamentStatusService state machine (RESEARCH Pattern 1):
|   draft       -> registering | cancelled
|   registering -> seeded      | cancelled
|   seeded      -> running     | registering | cancelled   (reseed back-transition)
|   running     -> completed   | cancelled
|   completed   + cancelled    terminal
|
| Threat refs T-06-04-01 (invalid transition) and T-06-04-02 (audit trail) are
| asserted at the service layer here; the DB-layer tournaments_status_check CHECK
| constraint is asserted in TournamentModelTest (plan 06-03).
|
| Mirrors Phase 4 MatchStatusServiceTest verbatim (D-04-04-A precedent). The only
| structurally new test vs the Phase 4 analog is `seeded -> registering` — the
| reseed back-transition consumed by plan 06-05's TournamentSeedingService::reseed().
*/

// ---------------------------------------------------------------------------
// Happy paths — every allowed transition (7 allowed paths)
// ---------------------------------------------------------------------------

it('allows draft -> registering transition', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    $result = app(TournamentStatusService::class)->transition($tournament, 'registering', $causer);

    expect($tournament->fresh()->status)->toBe('registering');
    expect($result)->toBeInstanceOf(Tournament::class);
});

it('allows draft -> cancelled transition', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'cancelled', $causer);

    expect($tournament->fresh()->status)->toBe('cancelled');
});

it('allows registering -> seeded transition', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'seeded', $causer);

    expect($tournament->fresh()->status)->toBe('seeded');
});

it('allows registering -> cancelled transition', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'cancelled', $causer);

    expect($tournament->fresh()->status)->toBe('cancelled');
});

it('allows seeded -> running transition', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'running', $causer);

    expect($tournament->fresh()->status)->toBe('running');
});

it('allows seeded -> registering transition (reseed back-transition)', function (): void {
    // The unique-to-Phase-6 back-transition. Plan 06-05 TournamentSeedingService::reseed()
    // calls transition($t, 'registering') first, then re-runs seeding. canReseed() (plan 06-05)
    // gates the action visibility.
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'registering', $causer);

    expect($tournament->fresh()->status)->toBe('registering');
});

it('allows seeded -> cancelled transition', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'cancelled', $causer);

    expect($tournament->fresh()->status)->toBe('cancelled');
});

it('allows running -> completed transition', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'completed', $causer);

    expect($tournament->fresh()->status)->toBe('completed');
});

it('allows running -> cancelled transition', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'cancelled', $causer);

    expect($tournament->fresh()->status)->toBe('cancelled');
});

// ---------------------------------------------------------------------------
// Rejected transitions — terminal states + skip + backward moves
// ---------------------------------------------------------------------------

it('rejects completed -> running transition (terminal)', function (): void {
    $tournament = Tournament::factory()->inStatus('completed')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'running', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('completed');
});

it('rejects completed -> cancelled transition (terminal)', function (): void {
    $tournament = Tournament::factory()->inStatus('completed')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'cancelled', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('completed');
});

it('rejects cancelled -> running transition (terminal)', function (): void {
    $tournament = Tournament::factory()->inStatus('cancelled')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'running', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('cancelled');
});

it('rejects cancelled -> draft transition (terminal)', function (): void {
    $tournament = Tournament::factory()->inStatus('cancelled')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'draft', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('cancelled');
});

it('rejects draft -> seeded transition (skip)', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'seeded', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('draft');
});

it('rejects draft -> running transition (skip)', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'running', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('draft');
});

it('rejects registering -> completed transition (skip)', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'completed', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('registering');
});

it('rejects running -> registering transition (no backward from running)', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'registering', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('running');
});

it('rejects transition to an unknown status string', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'open', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);

    expect($tournament->fresh()->status)->toBe('draft');
});

it('rejects transition from a current status not in the ALLOWED keyset', function (): void {
    // Transient Tournament instance with a synthetic current status that bypasses
    // the DB CHECK (object never persisted at the bogus value). Service must reject.
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $tournament->status = 'unknown_status';
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'running', $causer))
        ->toThrow(TournamentStatusInvalidTransitionException::class);
});

// ---------------------------------------------------------------------------
// Activity log emission (T-06-04-02 mitigation)
// ---------------------------------------------------------------------------

it('writes an activity log row on transition with from/to properties', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'registering', $causer);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament status: draft -> registering')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('from'))->toBe('draft');
    expect($activity->properties->get('to'))->toBe('registering');
});

it('writes the causer user_id to the activity log row', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $causer = User::factory()->create();

    app(TournamentStatusService::class)->transition($tournament, 'seeded', $causer);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament status: registering -> seeded')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($causer->id);
    expect($activity->causer_type)->toBe(User::class);
});

it('falls back to auth()->user() when causer is null', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $loggedIn = User::factory()->create();

    $this->actingAs($loggedIn);

    app(TournamentStatusService::class)->transition($tournament, 'registering');

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament status: draft -> registering')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($loggedIn->id);
});

it('does not write an activity log row when the transition is rejected', function (): void {
    $tournament = Tournament::factory()->inStatus('completed')->create();
    $causer = User::factory()->create();

    $countBefore = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'like', 'Tournament status:%')
        ->count();

    try {
        app(TournamentStatusService::class)->transition($tournament, 'running', $causer);
    } catch (TournamentStatusInvalidTransitionException) {
        // expected
    }

    $countAfter = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'like', 'Tournament status:%')
        ->count();

    expect($countAfter)->toBe($countBefore);
});

it('uses the localized tournaments.errors.invalid_transition message on rejection', function (): void {
    $tournament = Tournament::factory()->inStatus('completed')->create();
    $causer = User::factory()->create();

    expect(fn () => app(TournamentStatusService::class)->transition($tournament, 'running', $causer))
        ->toThrow(
            TournamentStatusInvalidTransitionException::class,
            'Tournament status cannot transition from completed to running.',
        );
});

it('returns the Tournament model from a successful transition (fluent chain)', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();
    $causer = User::factory()->create();

    $result = app(TournamentStatusService::class)->transition($tournament, 'registering', $causer);

    expect($result)->toBeInstanceOf(Tournament::class);
    expect($result->id)->toBe($tournament->id);
    expect($result->status)->toBe('registering');
});

// ---------------------------------------------------------------------------
// DomainException subclass identity — defends against bare \DomainException throws
// ---------------------------------------------------------------------------

it('throws the typed exception subclass (not bare DomainException)', function (): void {
    $tournament = Tournament::factory()->inStatus('completed')->create();
    $causer = User::factory()->create();

    try {
        app(TournamentStatusService::class)->transition($tournament, 'running', $causer);
        $this->fail('Expected TournamentStatusInvalidTransitionException was not thrown.');
    } catch (TournamentStatusInvalidTransitionException $e) {
        expect($e)->toBeInstanceOf(TournamentStatusInvalidTransitionException::class);
        expect($e)->toBeInstanceOf(DomainException::class);
    }
});
