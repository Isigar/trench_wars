<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchSlot;
use Illuminate\Support\Facades\DB;

/**
 * Source: 04-05-PLAN.md Task 1 + 04-RESEARCH.md Pattern 3 (snapshot-at-create
 * materialiser) + Assumption A1 (RoleLimit edits do NOT retroactively rewrite
 * match_slots).
 *
 * Materialises GameMatchType.roleLimits into MatchSlot rows at Match-create time.
 * For every (game_role_id, capacity, sort_order) tuple in the matchType's roleLimits,
 * exactly `capacity` MatchSlot rows are inserted with slot_index ∈ [0, capacity).
 *
 *   Slot snapshot semantics (D-04-05-A — locks Assumption A1):
 *     - slot.game_role_id is a snapshot of the role at materialise-time. The slot
 *       does NOT FK back to game_match_type_role_limits, so admin edits to the
 *       RoleLimit's capacity AFTER materialise do NOT retroactively change existing
 *       match_slots. Future matches use the new capacity; open matches are frozen.
 *     - slot.sort_order is a snapshot of roleLimit.sort_order at materialise-time.
 *       Same rationale — open matches preserve the original grid ordering.
 *
 *   Idempotency-by-DB-constraint (T-04-05-03 mitigation):
 *     - The match_slots composite UNIQUE (match_id, game_role_id, slot_index)
 *       (plan 04-02) blocks duplicate inserts. Calling materialise() twice on the
 *       same Match throws QueryException on the second call — the caller is
 *       responsible for never invoking twice. This is defense-in-depth, not the
 *       primary idempotency guarantee.
 *
 *   Transaction semantics (T-04-05-02 mitigation):
 *     - All writes inside DB::transaction. A partial materialisation (e.g., 30 of
 *       50 inserts before an FK error) rolls back ALL prior inserts — no orphan
 *       slot grid is ever persisted.
 *
 *   Outer transaction (Pitfall 3 — RESEARCH):
 *     - The CALLER (Filament wizard `CreateMatch::handleRecordCreation` in plan
 *       04-09) wraps BOTH Match::create AND this service call in a SINGLE OUTER
 *       DB::transaction. Laravel nests transactions via savepoints — the outer
 *       commit waits for the inner commit; an inner rollback also rolls back the
 *       outer transaction. This service's own DB::transaction is defense-in-depth
 *       so a non-Filament caller that forgets the outer wrap still rolls back a
 *       partial materialisation.
 *
 *   Naming note (D-04-03-A LOCKED): the Match model is `App\Models\GameMatch`
 *   (class `Match` is a PHP 8.4 parse error — `match` is a reserved keyword).
 *   Table stays `matches`; FK columns stay `match_id`. This service uses GameMatch
 *   directly — no `match($x)` expressions appear here so the Pitfall 5
 *   alias-on-import pattern is not needed (canonical Phase 4 idiom per D-04-04-C).
 *
 * Threat refs:
 *   - T-04-05-01 (slot count drift from RoleLimit edits) — mitigated by snapshot
 *   - T-04-05-02 (partial materialisation) — mitigated by DB::transaction
 *   - T-04-05-03 (duplicate materialise doubles slot count) — mitigated by composite UNIQUE
 *   - T-04-05-04 (activity log noise — N slots = N entries) — accepted (D-012 completeness)
 *   - T-04-05-05 (cross-game role_id) — mitigated upstream by RoleLimit saving() guard (plan 03-03)
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class MatchSlotMaterialiserService
{
    /**
     * Materialise the slot grid for a freshly-created Match.
     *
     * Reads `$match->gameMatchType->roleLimits` (eager-loading if absent) and
     * inserts one MatchSlot per (game_role_id, slot_index ∈ [0, capacity)) tuple.
     *
     * Snapshot semantics: slot.game_role_id + slot.sort_order are frozen at
     * materialise-time; later RoleLimit edits do NOT affect existing match_slots.
     *
     * Idempotency: NOT idempotent. The match_slots composite UNIQUE
     * (match_id, game_role_id, slot_index) blocks duplicate inserts; calling
     * twice on the same Match throws QueryException on the second call. Callers
     * MUST never invoke twice (the Filament wizard's outer transaction guarantees
     * exactly-once per Match::create).
     *
     * @return int Count of MatchSlot rows inserted (0 if matchType has empty roleLimits).
     */
    public function materialise(GameMatch $match): int
    {
        return DB::transaction(function () use ($match): int {
            $match->loadMissing('gameMatchType.roleLimits');
            $count = 0;

            // BelongsTo accessor is nullable in PHPStan's view; in practice the
            // caller (Filament wizard or Phase 4 factory chain) always sets a
            // non-null gameMatchType — every GameMatch row's `game_match_type_id`
            // is NOT NULL at the DB layer (plan 04-02 migration). Empty
            // roleLimits is a valid edge case (admin-fillable blank type) and
            // is handled by the foreach iterating zero rows.
            $matchType = $match->gameMatchType;
            if ($matchType === null) {
                return 0;
            }

            foreach ($matchType->roleLimits as $limit) {
                for ($i = 0; $i < $limit->capacity; $i++) {
                    MatchSlot::create([
                        'match_id' => $match->id,
                        'game_role_id' => $limit->game_role_id,
                        'slot_index' => $i,
                        'sort_order' => $limit->sort_order,
                    ]);
                    $count++;
                }
            }

            return $count;
        });
    }
}
