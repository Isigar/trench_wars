<?php

declare(strict_types=1);

use App\Exceptions\DisputeAlreadyOpenException;
use App\Exceptions\InvalidDisputeTransitionException;
use App\Models\GameMatch;
use App\Models\MatchDispute;
use App\Models\User;
use App\Services\DisputeService;
use Database\Seeders\ModeratorRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/09-polish/09-07-PLAN.md task 2 (Wave 5).
|
| Replaces the Wave 0 RED stub. Locks SC-3 invariants for the MatchDispute
| state machine (DisputeService + MatchDisputeResource transition Action):
|
|   open          → under_review
|   under_review  → resolved | rejected
|   rejected      → under_review            (re-open)
|   resolved      → (terminal)
|
| Illegal transitions throw InvalidDisputeTransitionException.
| Duplicate open disputes throw DisputeAlreadyOpenException (partial UNIQUE).
| Every transition emits a match.dispute_transitioned activity_log row.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    $this->moderator = User::factory()->create();
    $this->moderator->assignRole('moderator');

    $this->raiser = User::factory()->create();
    $this->match = GameMatch::factory()->create();

    $this->svc = app(DisputeService::class);
});

// ---------------------------------------------------------------------------
// Happy-path transitions
// ---------------------------------------------------------------------------

it('opens a dispute via DisputeService::open with status=open', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'Initial dispute body — long enough.');

    expect($dispute)->toBeInstanceOf(MatchDispute::class)
        ->and($dispute->status)->toBe('open')
        ->and($dispute->match_id)->toBe($this->match->id)
        ->and($dispute->raised_by_user_id)->toBe($this->raiser->id);

    expect(Activity::query()->where('description', 'match.dispute_opened')->count())->toBe(1);
});

it('transitions open -> under_review via DisputeService', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'open->under_review body.');

    $next = $this->svc->transition($dispute, 'under_review', null, 'Moderator reviewing.', $this->moderator);

    expect($next->status)->toBe('under_review')
        ->and($next->resolved_at)->toBeNull()
        ->and($next->resolved_by_user_id)->toBeNull();
});

it('transitions under_review -> resolved with resolution result_amended', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'under_review->resolved body.');
    $reviewed = $this->svc->transition($dispute, 'under_review', null, 'Initial review.', $this->moderator);

    $resolved = $this->svc->transition($reviewed, 'resolved', 'result_amended', 'Score corrected.', $this->moderator);

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolution)->toBe('result_amended')
        ->and($resolved->resolved_at)->not->toBeNull()
        ->and($resolved->resolved_by_user_id)->toBe($this->moderator->id);
});

it('transitions under_review -> rejected', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'under_review->rejected body.');
    $reviewed = $this->svc->transition($dispute, 'under_review', null, 'Initial review.', $this->moderator);

    $rejected = $this->svc->transition($reviewed, 'rejected', null, 'Insufficient evidence provided.', $this->moderator);

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->resolution)->toBeNull()
        ->and($rejected->resolved_at)->not->toBeNull()
        ->and($rejected->resolved_by_user_id)->toBe($this->moderator->id);
});

it('re-opens a rejected dispute back to under_review', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'rejected->under_review re-open.');
    $reviewed = $this->svc->transition($dispute, 'under_review', null, 'Initial review.', $this->moderator);
    $rejected = $this->svc->transition($reviewed, 'rejected', null, 'Initial rejection.', $this->moderator);

    $reOpened = $this->svc->transition($rejected, 'under_review', null, 'New evidence surfaced.', $this->moderator);

    expect($reOpened->status)->toBe('under_review')
        // resolved_at + resolved_by_user_id MUST be cleared on re-open so a
        // future terminal transition can write fresh values (DisputeService
        // docblock).
        ->and($reOpened->resolved_at)->toBeNull()
        ->and($reOpened->resolved_by_user_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// State-machine rejection cases
// ---------------------------------------------------------------------------

it('rejects invalid transition open -> resolved directly', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'Invalid transition body.');

    expect(fn () => $this->svc->transition($dispute, 'resolved', 'no_action', 'Skip review.', $this->moderator))
        ->toThrow(InvalidDisputeTransitionException::class);
});

it('rejects transition to resolved without a valid resolution', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'Resolution validation body.');
    $reviewed = $this->svc->transition($dispute, 'under_review', null, 'Initial review.', $this->moderator);

    expect(fn () => $this->svc->transition($reviewed, 'resolved', null, 'No resolution.', $this->moderator))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->svc->transition($reviewed, 'resolved', 'unknown_resolution', 'Bad resolution.', $this->moderator))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects transition from resolved to anything (terminal state)', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'Terminal test body.');
    $reviewed = $this->svc->transition($dispute, 'under_review', null, 'Initial review.', $this->moderator);
    $resolved = $this->svc->transition($reviewed, 'resolved', 'no_action', 'No action taken.', $this->moderator);

    expect(fn () => $this->svc->transition($resolved, 'under_review', null, 'Bad reopen.', $this->moderator))
        ->toThrow(InvalidDisputeTransitionException::class);
});

it('blocks duplicate open disputes by the same user on the same match', function (): void {
    $this->svc->open($this->match, $this->raiser, 'First open dispute body.');

    expect(fn () => $this->svc->open($this->match, $this->raiser, 'Second open dispute body.'))
        ->toThrow(DisputeAlreadyOpenException::class);
});

it('allows a different user to open a dispute on the same match', function (): void {
    $this->svc->open($this->match, $this->raiser, 'First user dispute body.');

    $other = User::factory()->create();
    $secondDispute = $this->svc->open($this->match, $other, 'Second user dispute body.');

    expect($secondDispute->raised_by_user_id)->toBe($other->id)
        ->and(MatchDispute::query()->where('match_id', $this->match->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// activity_log row on every transition (T-09-07-07 — Tampering mitigation)
// ---------------------------------------------------------------------------

it('writes an activity_log row on every transition', function (): void {
    $dispute = $this->svc->open($this->match, $this->raiser, 'Audit row test body.');
    $this->svc->transition($dispute, 'under_review', null, 'Audit: into review.', $this->moderator);
    $this->svc->transition($dispute->fresh(), 'resolved', 'no_action', 'Audit: closed.', $this->moderator);

    expect(Activity::query()->where('description', 'match.dispute_transitioned')->count())->toBe(2)
        ->and(Activity::query()->where('description', 'match.dispute_opened')->count())->toBe(1);
});
