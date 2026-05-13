<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/04-matches-manual/04-03-PLAN.md task 3.
| Analog: apps/web/tests/Feature/Models/ClanMembershipModelTest.php (partial UNIQUE pattern).
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
|
| THE SECURITY-CRITICAL PHASE-4 ASSERTION lives in this file: the partial UNIQUE
| `match_slots_one_occupancy_per_user` (D-009 analog: one slot per user per match).
| If this test fails, MatchSignupService idempotency loses its DB-layer defense.
*/

it('creates a valid slot via factory', function (): void {
    $slot = MatchSlot::factory()->create();
    expect($slot->exists)->toBeTrue();
});

it('enforces composite UNIQUE (match_id, game_role_id, slot_index)', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();

    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
    ]);

    expect(fn () => MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
    ]))->toThrow(QueryException::class);
});

it('blocks a user occupying two slots in the same match (partial UNIQUE)', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $user = User::factory()->create();

    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => $user->id,
        'confirmed_at' => now(),
    ]);

    $slotB = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 1,
        'occupant_user_id' => null,
    ]);

    expect(fn () => $slotB->update(['occupant_user_id' => $user->id, 'confirmed_at' => now()]))
        ->toThrow(QueryException::class);
});

it('allows multiple unoccupied slots in the same match (partial UNIQUE skips NULLs)', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();

    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => null,
    ]);
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 1,
        'occupant_user_id' => null,
    ]);

    expect(MatchSlot::where('match_id', $match->id)->count())->toBe(2);
});

it('allows a user to occupy slots in different matches', function (): void {
    $matchA = GameMatch::factory()->create();
    $matchB = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $user = User::factory()->create();

    MatchSlot::factory()->create([
        'match_id' => $matchA->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => $user->id,
    ]);
    $slotInB = MatchSlot::factory()->create([
        'match_id' => $matchB->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => $user->id,
    ]);

    expect($slotInB->fresh()->occupant_user_id)->toBe($user->id);
});

it('exposes match, role, and occupantUser BelongsTo relations', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $user = User::factory()->create();

    $slot = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => $user->id,
    ]);

    expect($slot->match?->id)->toBe($match->id);
    expect($slot->role?->id)->toBe($role->id);
    expect($slot->occupantUser?->id)->toBe($user->id);
});

it('cascades on parent match delete', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $slot = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
    ]);
    $slotId = $slot->id;

    $match->delete();

    expect(MatchSlot::where('id', $slotId)->exists())->toBeFalse();
});

it('vacates the slot when the occupant user is deleted (FK nullOnDelete)', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $user = User::factory()->create();
    $slot = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => $user->id,
    ]);

    $user->delete();

    expect($slot->fresh()->occupant_user_id)->toBeNull();
});

it('logs activity on create (D-012)', function (): void {
    $slot = MatchSlot::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->where('subject_id', $slot->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
