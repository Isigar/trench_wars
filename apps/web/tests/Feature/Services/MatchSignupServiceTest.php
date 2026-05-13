<?php

declare(strict_types=1);

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\CapacityExceededException;
use App\Exceptions\MatchNotOpenException;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSignupService;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 04-06-PLAN.md Task 1 — replaces Wave 0 RED stub.
|
| Covers MatchSignupService::signup() — the SINGLE production write path to
| match_slots.occupant_user_id (D-010 row-locked transactional capacity).
|
| Guard order asserted here (Pattern 2 — verbatim):
|   1. status === 'open'                 → else MatchNotOpenException
|   2. tag access (covered in MatchSignupTagRestrictedTest — kept separate)
|   3. one-slot-per-user-per-match       → else AlreadySignedUpException
|   4. occupied < total capacity         → else CapacityExceededException
|   5. claim lowest-index empty slot     → atomically writes occupant + confirmed_at
|
| The concurrency edge (two parallel signups for the last slot) lives in
| MatchSignupConcurrencyTest. The tag-access enumeration (Pattern 5) lives
| in MatchSignupTagRestrictedTest.
|
| NAMING NOTE (D-04-03-A): the Match model is `GameMatch`. Tests import
| `use App\Models\GameMatch;` directly — no alias-on-import needed because
| this file contains zero `match($x)` expressions (D-04-04-C / D-04-05-B
| canonical idiom).
*/

/**
 * Build a same-game (match, role) fixture with `slotCapacity` empty slots
 * already materialised. Returns [$match, $role].
 *
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildSignupFixture(int $slotCapacity = 4, string $status = 'open'): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create(['status' => $status]);

    for ($i = 0; $i < $slotCapacity; $i++) {
        MatchSlot::factory()->create([
            'match_id' => $match->id,
            'game_role_id' => $role->id,
            'slot_index' => $i,
            'occupant_user_id' => null,
            'confirmed_at' => null,
            'sort_order' => 0,
        ]);
    }

    return [$match, $role];
}

// ---------------------------------------------------------------------------
// Happy path — claim lowest-index slot; atomic occupant + confirmed_at write
// ---------------------------------------------------------------------------

it('signs up a user to the lowest-index empty slot', function (): void {
    [$match, $role] = buildSignupFixture(4);
    $user = User::factory()->create();

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    expect($slot->slot_index)->toBe(0)
        ->and($slot->occupant_user_id)->toBe($user->id)
        ->and($slot->confirmed_at)->not->toBeNull();
});

it('writes confirmed_at to NOW on success', function (): void {
    [$match, $role] = buildSignupFixture(2);
    $user = User::factory()->create();

    $before = now()->subSecond();
    $slot = app(MatchSignupService::class)->signup($match, $user, $role);
    $after = now()->addSecond();

    // PHPStan's view of MatchSlot::$confirmed_at is `string|null` because the
    // model lacks @property annotations even though `'datetime'` is in $casts.
    // Pull the typed Carbon instance via the query builder's value() to keep
    // the assertions strict-mode clean.
    $confirmedAt = MatchSlot::where('id', $slot->id)->value('confirmed_at');
    expect($confirmedAt)->toBeInstanceOf(Carbon::class);
    expect($confirmedAt->greaterThanOrEqualTo($before))->toBeTrue();
    expect($confirmedAt->lessThanOrEqualTo($after))->toBeTrue();
});

it('claims the lowest-numbered empty slot_index, deterministic', function (): void {
    [$match, $role] = buildSignupFixture(5);

    // Pre-fill slots 0, 1, 2 — manual occupant writes via the factory so we
    // can prove the service deterministically picks slot 3 next.
    $preUsers = User::factory()->count(3)->create();
    foreach ($preUsers as $i => $preUser) {
        MatchSlot::where('match_id', $match->id)
            ->where('game_role_id', $role->id)
            ->where('slot_index', $i)
            ->update(['occupant_user_id' => $preUser->id, 'confirmed_at' => now()]);
    }

    $user = User::factory()->create();
    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    expect($slot->slot_index)->toBe(3);
});

it('updates match_slots.occupant_user_id atomically with confirmed_at', function (): void {
    [$match, $role] = buildSignupFixture(2);
    $user = User::factory()->create();

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);
    $fresh = MatchSlot::findOrFail($slot->id);

    expect($fresh->occupant_user_id)->toBe($user->id);
    expect($fresh->confirmed_at)->not->toBeNull();
});

it('writes an activity log entry on slot update', function (): void {
    [$match, $role] = buildSignupFixture(2);
    $user = User::factory()->create();

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->where('subject_id', $slot->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Guard 1 — status === 'open'
// ---------------------------------------------------------------------------

it('throws MatchNotOpenException when match.status is draft', function (): void {
    [$match, $role] = buildSignupFixture(4, 'draft');
    $user = User::factory()->create();

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(MatchNotOpenException::class);
});

it('throws MatchNotOpenException when match.status is locked', function (): void {
    [$match, $role] = buildSignupFixture(4, 'locked');
    $user = User::factory()->create();

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(MatchNotOpenException::class);
});

// ---------------------------------------------------------------------------
// Guard 3 — idempotency / one-slot-per-user-per-match
// ---------------------------------------------------------------------------

it('throws AlreadySignedUpException on second signup for same user same match', function (): void {
    [$match, $role] = buildSignupFixture(4);
    $user = User::factory()->create();

    // First signup succeeds.
    app(MatchSignupService::class)->signup($match, $user, $role);

    // Second signup (same role) throws.
    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(AlreadySignedUpException::class);
});

it('throws AlreadySignedUpException when user attempts to occupy a different role in the same match', function (): void {
    // The idempotency check is one-slot-per-user-per-MATCH (any role) — not per role.
    [$match, $roleA] = buildSignupFixture(2);

    // Add a second role to the same match (same game).
    /** @var Game $game */
    $game = $roleA->game;
    $roleB = GameRole::factory()->for($game)->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $roleB->id,
        'slot_index' => 0,
        'occupant_user_id' => null,
        'confirmed_at' => null,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();
    app(MatchSignupService::class)->signup($match, $user, $roleA);

    // Second signup on a DIFFERENT role still throws — one slot per match.
    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $roleB))
        ->toThrow(AlreadySignedUpException::class);
});

// ---------------------------------------------------------------------------
// Guard 4 — capacity exceeded
// ---------------------------------------------------------------------------

it('throws CapacityExceededException when role is full', function (): void {
    [$match, $role] = buildSignupFixture(2);

    // Fill both slots manually with two different users.
    $preUsers = User::factory()->count(2)->create();
    foreach ($preUsers as $i => $preUser) {
        MatchSlot::where('match_id', $match->id)
            ->where('game_role_id', $role->id)
            ->where('slot_index', $i)
            ->update(['occupant_user_id' => $preUser->id, 'confirmed_at' => now()]);
    }

    // Third user attempts signup — role is at capacity (2/2).
    $user = User::factory()->create();
    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(CapacityExceededException::class);
});

it('throws CapacityExceededException with the localized capacity_full message', function (): void {
    [$match, $role] = buildSignupFixture(1);
    $preUser = User::factory()->create();
    MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->update(['occupant_user_id' => $preUser->id, 'confirmed_at' => now()]);

    $user = User::factory()->create();
    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(CapacityExceededException::class, 'Sorry — that role is full.');
});

// ---------------------------------------------------------------------------
// Guard order — status check fires BEFORE capacity check
// ---------------------------------------------------------------------------

it('checks status BEFORE capacity (cheap-first guard order)', function (): void {
    [$match, $role] = buildSignupFixture(1, 'locked');
    // Even though the role is empty (would otherwise succeed), status='locked'
    // wins because guard 1 fires first.
    $user = User::factory()->create();

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(MatchNotOpenException::class);
});
