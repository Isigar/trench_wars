<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Services\MatchSlotMaterialiserService;
use Illuminate\Database\QueryException;

/*
| Source: 04-05-PLAN.md Task 1 — replaces Wave 0 RED stub.
|
| Covers the MatchSlotMaterialiserService snapshot-at-create primitive
| (RESEARCH.md Pattern 3 + Assumption A1):
|   - Slots are written from GameMatchType.roleLimits at materialise-time.
|   - slot.game_role_id + slot.sort_order are snapshots; future RoleLimit edits
|     do NOT retroactively rewrite match_slots.
|   - All writes inside DB::transaction (partial materialisation impossible).
|   - Idempotency-by-failure: composite UNIQUE on match_slots blocks double-calls.
|
| NAMING NOTE (D-04-03-A): Match model class is `GameMatch`. Tests import
| `use App\Models\GameMatch;` directly — no `match($x)` expressions appear
| here so the Pitfall 5 alias-on-import is not needed (D-04-04-C idiom).
*/

/**
 * Build a same-game (matchType, role) fixture and create a Match attached to it.
 * Returns [$game, $matchType, $match]. Test caller seeds RoleLimit rows on the
 * returned matchType.
 *
 * @return array{0: Game, 1: GameMatchType, 2: GameMatch}
 */
function buildMaterialiserFixture(): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create();

    return [$game, $matchType, $match];
}

// ---------------------------------------------------------------------------
// Happy path — generic capacity matrix
// ---------------------------------------------------------------------------

it('produces N slots matching the sum of GameMatchType.roleLimits capacities', function (): void {
    [$game, $matchType, $match] = buildMaterialiserFixture();

    // 3-role mini matrix: capacities [2, 3, 1] = 6 total slots.
    $capacities = [2, 3, 1];
    $roles = [];
    foreach ($capacities as $i => $capacity) {
        $role = GameRole::factory()->for($game)->create(['sort_order' => $i]);
        GameMatchTypeRoleLimit::factory()->create([
            'game_match_type_id' => $matchType->id,
            'game_role_id' => $role->id,
            'capacity' => $capacity,
            'sort_order' => $i,
        ]);
        $roles[] = $role;
    }

    $count = app(MatchSlotMaterialiserService::class)->materialise($match);

    expect($count)->toBe(6)
        ->and(MatchSlot::where('match_id', $match->id)->count())->toBe(6);

    // Per-role breakdown matches the capacity matrix.
    expect(MatchSlot::where('match_id', $match->id)->where('game_role_id', $roles[0]->id)->count())->toBe(2)
        ->and(MatchSlot::where('match_id', $match->id)->where('game_role_id', $roles[1]->id)->count())->toBe(3)
        ->and(MatchSlot::where('match_id', $match->id)->where('game_role_id', $roles[2]->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Happy path — HLL Scrim 50v50 invariant (SC-1 first half)
// ---------------------------------------------------------------------------

it('produces 50 slots for a Scrim 50v50 GameMatchType', function (): void {
    [$game, $matchType, $match] = buildMaterialiserFixture();

    // Canonical HLL Scrim 50v50 capacity matrix (verbatim from GameSeeder.php
    // lines 165-181). 15 roles, capacities sum to 50 (crewman is 0 — exercises
    // the zero-capacity edge case in the same fixture).
    $matrix = [
        'commander' => 1,
        'officer' => 4,
        'squad_leader' => 4,
        'rifleman' => 14,
        'assault' => 4,
        'automatic_rifleman' => 4,
        'medic' => 4,
        'engineer' => 4,
        'support' => 4,
        'heavy_machine_gunner' => 2,
        'anti_tank' => 2,
        'sniper' => 1,
        'spotter' => 1,
        'tank_commander' => 1,
        'crewman' => 0,
    ];

    $sortOrder = 0;
    foreach ($matrix as $roleKey => $capacity) {
        $role = GameRole::factory()->for($game)->create([
            'key' => $roleKey,
            'sort_order' => $sortOrder,
        ]);
        GameMatchTypeRoleLimit::factory()->create([
            'game_match_type_id' => $matchType->id,
            'game_role_id' => $role->id,
            'capacity' => $capacity,
            'sort_order' => $sortOrder,
        ]);
        $sortOrder++;
    }

    $count = app(MatchSlotMaterialiserService::class)->materialise($match);

    expect($count)->toBe(50)
        ->and(MatchSlot::where('match_id', $match->id)->count())->toBe(50);
});

// ---------------------------------------------------------------------------
// Edge case — empty roleLimits (Friendly / Tournament / Clan War seeded pattern)
// ---------------------------------------------------------------------------

it('produces 0 slots when GameMatchType has empty roleLimits', function (): void {
    [, , $match] = buildMaterialiserFixture();

    // No roleLimits seeded — matchType is a blank "admin fills via Filament" type
    // (Friendly / Tournament / Clan War in Phase 3 GameSeeder).
    $count = app(MatchSlotMaterialiserService::class)->materialise($match);

    expect($count)->toBe(0)
        ->and(MatchSlot::where('match_id', $match->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Idempotency-by-failure — composite UNIQUE blocks double-call
// ---------------------------------------------------------------------------

it('throws QueryException when called twice on the same Match (idempotency-by-failure)', function (): void {
    [$game, $matchType, $match] = buildMaterialiserFixture();

    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 1,
        'sort_order' => 0,
    ]);

    // First call lands cleanly.
    app(MatchSlotMaterialiserService::class)->materialise($match);
    expect(MatchSlot::where('match_id', $match->id)->count())->toBe(1);

    // Second call hits match_slots_unique_slot composite UNIQUE on the same
    // (match_id, game_role_id, slot_index=0) tuple.
    expect(fn () => app(MatchSlotMaterialiserService::class)->materialise($match))
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// Snapshot fidelity — sort_order frozen at materialise-time
// ---------------------------------------------------------------------------

it('snapshots sort_order from roleLimits to slots', function (): void {
    [$game, $matchType, $match] = buildMaterialiserFixture();

    // Three roles with non-trivial sort_orders [10, 20, 30].
    $roleA = GameRole::factory()->for($game)->create();
    $roleB = GameRole::factory()->for($game)->create();
    $roleC = GameRole::factory()->for($game)->create();

    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $roleA->id,
        'capacity' => 1,
        'sort_order' => 10,
    ]);
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $roleB->id,
        'capacity' => 1,
        'sort_order' => 20,
    ]);
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $roleC->id,
        'capacity' => 1,
        'sort_order' => 30,
    ]);

    app(MatchSlotMaterialiserService::class)->materialise($match);

    expect(MatchSlot::where('match_id', $match->id)->where('game_role_id', $roleA->id)->value('sort_order'))->toBe(10)
        ->and(MatchSlot::where('match_id', $match->id)->where('game_role_id', $roleB->id)->value('sort_order'))->toBe(20)
        ->and(MatchSlot::where('match_id', $match->id)->where('game_role_id', $roleC->id)->value('sort_order'))->toBe(30);
});

// ---------------------------------------------------------------------------
// Snapshot rationale — game_role_id is the FK, NOT game_match_type_role_limit_id
// (Pattern 3 deliberate decoupling)
// ---------------------------------------------------------------------------

it('snapshots game_role_id (slot survives deletion of the originating RoleLimit row)', function (): void {
    [$game, $matchType, $match] = buildMaterialiserFixture();

    $role = GameRole::factory()->for($game)->create();
    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 3,
        'sort_order' => 0,
    ]);

    app(MatchSlotMaterialiserService::class)->materialise($match);
    expect(MatchSlot::where('match_id', $match->id)->count())->toBe(3);

    // Delete the originating RoleLimit row. Because slot.game_role_id FKs to
    // game_roles (NOT to game_match_type_role_limits), the slots must survive.
    $limit->delete();

    expect(MatchSlot::where('match_id', $match->id)->count())->toBe(3)
        ->and(MatchSlot::where('match_id', $match->id)->value('game_role_id'))->toBe($role->id);
});

// ---------------------------------------------------------------------------
// Snapshot rationale — RoleLimit.capacity edit AFTER materialise does NOT
// retroactively change existing match_slots (Assumption A1 fidelity)
// ---------------------------------------------------------------------------

it('does not retroactively rewrite match_slots when a RoleLimit.capacity is edited post-materialise', function (): void {
    [$game, $matchType, $match] = buildMaterialiserFixture();

    $role = GameRole::factory()->for($game)->create();
    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 4,
        'sort_order' => 0,
    ]);

    app(MatchSlotMaterialiserService::class)->materialise($match);
    expect(MatchSlot::where('match_id', $match->id)->where('game_role_id', $role->id)->count())->toBe(4);

    // Admin bumps capacity 4 → 10 AFTER the materialise call. The open match's
    // slot grid must be FROZEN at 4 (snapshot-at-create semantics).
    $limit->update(['capacity' => 10]);

    expect(MatchSlot::where('match_id', $match->id)->where('game_role_id', $role->id)->count())->toBe(4);
});
