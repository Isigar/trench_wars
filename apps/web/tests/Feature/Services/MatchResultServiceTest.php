<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\User;
use App\Services\MatchResultService;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 04-09-PLAN.md Task 3 — replaces Wave 0 RED stub.
|
| Covers MatchResultService::upsert (RESEARCH.md Pattern 4 + Pattern 3 service
| pattern verbatim from <interfaces>):
|   - First call writes a new MatchResult row.
|   - Second call updates the same row (updateOrCreate keyed on match_id UNIQUE).
|   - First call atomically transitions match.status open|locked -> played via
|     MatchStatusService::transition; second call SKIPS the transition (terminal
|     state — Pattern 4 disallows played -> played).
|   - All writes inside DB::transaction (partial state impossible — T-04-09-04).
|   - Draft matches CANNOT receive a result (draft -> played not in ALLOWED_TRANSITIONS;
|     valid path is draft -> open -> played per MatchStatusService).
|   - activity_log captures the result-write event AND the status transition event.
|
| NAMING NOTE (D-04-03-A): Match model class is `GameMatch`. Tests import
| `use App\Models\GameMatch;` directly — no `match($x)` expressions appear
| here so the Pitfall 5 alias-on-import is not needed.
*/

// ---------------------------------------------------------------------------
// upsert create + update paths
// ---------------------------------------------------------------------------

it('writes a new MatchResult on first call (upsert create path)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();
    $winner = Clan::factory()->create();

    $result = app(MatchResultService::class)->upsert($match, [
        'winner_clan_id' => $winner->id,
        'allies_score' => 5,
        'axis_score' => 3,
        'notes' => 'tight game',
    ], $causer);

    expect($result)->toBeInstanceOf(MatchResult::class)
        ->and($result->match_id)->toBe($match->id)
        ->and($result->winner_clan_id)->toBe($winner->id)
        ->and($result->allies_score)->toBe(5)
        ->and($result->axis_score)->toBe(3)
        ->and($result->notes)->toBe('tight game')
        ->and($result->recorded_by_user_id)->toBe($causer->id);

    expect(MatchResult::where('match_id', $match->id)->count())->toBe(1);
});

it('updates the existing MatchResult on second call (upsert update path)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    // First call seeds the row.
    $first = app(MatchResultService::class)->upsert($match, [
        'allies_score' => 1,
        'axis_score' => 1,
    ], $causer);

    // Second call overwrites scores + adds notes.
    $second = app(MatchResultService::class)->upsert($match, [
        'allies_score' => 7,
        'axis_score' => 2,
        'notes' => 'updated after review',
    ], $causer);

    expect($second->id)->toBe($first->id)
        ->and($second->allies_score)->toBe(7)
        ->and($second->axis_score)->toBe(2)
        ->and($second->notes)->toBe('updated after review');

    // No duplicate row landed — match_id UNIQUE enforced at DB layer.
    expect(MatchResult::where('match_id', $match->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Atomic status flip side-effect
// ---------------------------------------------------------------------------

it('flips match.status from open to played on first result write', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    expect($match->fresh()->status)->toBe('open');

    app(MatchResultService::class)->upsert($match, [
        'allies_score' => 4,
        'axis_score' => 4,
    ], $causer);

    expect($match->fresh()->status)->toBe('played');
});

it('flips match.status from locked to played on first result write', function (): void {
    $match = GameMatch::factory()->create(['status' => 'locked']);
    $causer = User::factory()->create();

    app(MatchResultService::class)->upsert($match, [
        'allies_score' => 3,
        'axis_score' => 5,
    ], $causer);

    expect($match->fresh()->status)->toBe('played');
});

it('does NOT re-transition status when match is already played', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    // First call: open -> played, writes 1 transition activity row.
    app(MatchResultService::class)->upsert($match, ['allies_score' => 1], $causer);

    $transitionRowsAfterFirst = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->count();

    expect($transitionRowsAfterFirst)->toBe(1);

    // Second call: status is already 'played'; service must SKIP MatchStatusService::transition
    // (terminal-state guard) — no new activity row, no DomainException.
    app(MatchResultService::class)->upsert($match, ['allies_score' => 99], $causer);

    $transitionRowsAfterSecond = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->count();

    expect($transitionRowsAfterSecond)->toBe(1);
    expect($match->fresh()->status)->toBe('played');
});

// ---------------------------------------------------------------------------
// Audit trail (T-04-09-07)
// ---------------------------------------------------------------------------

it('writes a status-transition activity row with causer on result creation', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    app(MatchResultService::class)->upsert($match, ['allies_score' => 2], $causer);

    $transitionActivity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->latest('id')
        ->first();

    expect($transitionActivity)->not->toBeNull();
    expect($transitionActivity->causer_id)->toBe($causer->id);
    expect($transitionActivity->causer_type)->toBe(User::class);
    expect($transitionActivity->properties->get('from'))->toBe('open');
    expect($transitionActivity->properties->get('to'))->toBe('played');
});

it('writes a MatchResult-created activity row via LogsActivity', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $causer = User::factory()->create();

    app(MatchResultService::class)->upsert($match, [
        'allies_score' => 6,
        'axis_score' => 6,
    ], $causer);

    $resultActivity = Activity::query()
        ->where('subject_type', MatchResult::class)
        ->where('description', 'MatchResult created')
        ->latest('id')
        ->first();

    expect($resultActivity)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Transaction rollback / pre-condition guard
// ---------------------------------------------------------------------------

it('throws DomainException when attempting result entry on a draft match (no valid transition)', function (): void {
    // Draft -> played is not in MatchStatusService::ALLOWED_TRANSITIONS; the valid path
    // is draft -> open -> played. The service surfaces the underlying DomainException.
    $match = GameMatch::factory()->create(['status' => 'draft']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchResultService::class)->upsert($match, [
        'allies_score' => 1,
        'axis_score' => 1,
    ], $causer))
        ->toThrow(DomainException::class);

    // Rolls back: no MatchResult row remains; status stays at 'draft'.
    expect(MatchResult::where('match_id', $match->id)->exists())->toBeFalse();
    expect($match->fresh()->status)->toBe('draft');
});

it('rejects upsert when match is already cancelled (terminal state, no path to played)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'cancelled']);
    $causer = User::factory()->create();

    expect(fn () => app(MatchResultService::class)->upsert($match, [
        'allies_score' => 1,
    ], $causer))
        ->toThrow(DomainException::class);

    expect(MatchResult::where('match_id', $match->id)->exists())->toBeFalse();
    expect($match->fresh()->status)->toBe('cancelled');
});
