<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\User;
use App\Services\MatchStatusService;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 04-04-PLAN.md Task 1 — replaces Wave 0 RED stub.
|
| Covers the MatchStatusService state machine (RESEARCH.md Pattern 4):
|   draft -> open | cancelled
|   open  -> locked | played | cancelled
|   locked -> played | cancelled
|   played + cancelled are terminal
|
| Threat refs T-04-04-01 (invalid transition) and T-04-04-02 (audit trail) are
| asserted at the service layer here; the DB-layer matches_status_check CHECK
| constraint is asserted in MatchModelTest (plan 04-03).
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch. Tests import
| `use App\Models\GameMatch;` directly — no `match($x)` expressions appear
| here so the alias-on-import pattern is not needed.
*/

// ---------------------------------------------------------------------------
// Happy paths — every allowed transition
// ---------------------------------------------------------------------------

it('allows draft -> open transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'draft']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'open', $causer);

    expect($match->fresh()->status)->toBe('open');
});

it('allows draft -> cancelled transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'draft']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'cancelled', $causer);

    expect($match->fresh()->status)->toBe('cancelled');
});

it('allows open -> locked transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'locked', $causer);

    expect($match->fresh()->status)->toBe('locked');
});

it('allows open -> played transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'played', $causer);

    expect($match->fresh()->status)->toBe('played');
});

it('allows open -> cancelled transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'cancelled', $causer);

    expect($match->fresh()->status)->toBe('cancelled');
});

it('allows locked -> played transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'locked']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'played', $causer);

    expect($match->fresh()->status)->toBe('played');
});

it('allows locked -> cancelled transition', function (): void {
    $match = GameMatch::factory()->create(['status' => 'locked']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'cancelled', $causer);

    expect($match->fresh()->status)->toBe('cancelled');
});

// ---------------------------------------------------------------------------
// Rejected transitions — terminal states + invalid moves
// ---------------------------------------------------------------------------

it('rejects played -> open transition (terminal)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'played']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'open', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('played');
});

it('rejects played -> cancelled transition (terminal)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'played']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'cancelled', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('played');
});

it('rejects cancelled -> open transition (terminal)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'cancelled']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'open', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('cancelled');
});

it('rejects cancelled -> draft transition (terminal)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'cancelled']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'draft', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('cancelled');
});

it('rejects open -> draft transition (no backward)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'draft', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('open');
});

it('rejects locked -> open transition (no backward)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'locked']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'open', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('locked');
});

it('rejects transition to an unknown status string', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'completed', $causer))
        ->toThrow(DomainException::class);

    expect($match->fresh()->status)->toBe('open');
});

it('rejects transition from a current status not in the ALLOWED_TRANSITIONS keyset', function (): void {
    // Construct a transient GameMatch instance with a synthetic status that bypasses
    // the DB CHECK (object never persisted at the bogus value). The service must reject.
    $match = GameMatch::factory()->create(['status' => 'open']);
    $match->status = 'unknown_status';
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'played', $causer))
        ->toThrow(DomainException::class);
});

// ---------------------------------------------------------------------------
// Activity log emission (T-04-04-02 mitigation)
// ---------------------------------------------------------------------------

it('writes an activity log row on transition with from/to properties', function (): void {
    $match = GameMatch::factory()->create(['status' => 'draft']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'open', $causer);

    $activity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('from'))->toBe('draft');
    expect($activity->properties->get('to'))->toBe('open');
});

it('writes the causer user_id to the activity log row', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    app(MatchStatusService::class)->transition($match, 'locked', $causer);

    $activity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($causer->id);
    expect($activity->causer_type)->toBe(User::class);
});

it('does not write an activity log row when the transition is rejected', function (): void {
    $match = GameMatch::factory()->create(['status' => 'played']);
    $causer = User::factory()->create();

    $countBefore = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('description', 'Match status transition')
        ->count();

    try {
        app(MatchStatusService::class)->transition($match, 'open', $causer);
    } catch (DomainException) {
        // expected
    }

    $countAfter = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('description', 'Match status transition')
        ->count();

    expect($countAfter)->toBe($countBefore);
});

it('uses the localized matches.status.error.invalid_transition message on rejection', function (): void {
    $match = GameMatch::factory()->create(['status' => 'played']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchStatusService::class)->transition($match, 'open', $causer))
        ->toThrow(DomainException::class, 'Cannot transition match status from played to open.');
});
