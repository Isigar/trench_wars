---
phase: 04-matches-manual
plan: 05
subsystem: matches
tags: [phase-4, wave-3, services, materialiser, snapshot-at-create, db-transaction, idempotency-by-failure]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-4-relational-backbone
    - phase-4-model-layer
    - phase-3-summary
  provides:
    - match-slot-materialiser-service
    - hll-scrim-50v50-invariant-verified
    - assumption-a1-snapshot-fidelity-locked
  affects:
    - apps/web/app/Services/ (1 new)
    - apps/web/tests/Feature/Services/ (1 stub flipped GREEN)
tech_stack:
  added: []
  patterns:
    - snapshot-at-create-materialiser
    - db-transaction-rollback-on-partial-failure
    - idempotency-by-db-unique-constraint
    - loadmissing-eager-defensive
    - phpstan-l8-belongsto-null-guard
key_files:
  created:
    - apps/web/app/Services/MatchSlotMaterialiserService.php
  modified:
    - apps/web/tests/Feature/Services/MatchSlotMaterialiserServiceTest.php
  deleted: []
decisions:
  - id: D-04-05-A
    decision: |
      **Slot snapshot semantics locked (Assumption A1 → invariant).**

      MatchSlot rows store `game_role_id` (FK to `game_roles`) and `sort_order`
      (snapshot value). They do NOT FK back to `game_match_type_role_limits`.
      This is the deliberate Pattern 3 decoupling that allows admins to edit
      a RoleLimit's capacity AFTER materialise without retroactively rewriting
      every open match's slot grid.

      Three test assertions lock this contract:
        1. `snapshots sort_order from roleLimits to slots` — proves the
           sort_order column carries the limit's value, not a live FK.
        2. `snapshots game_role_id (slot survives deletion of the originating
           RoleLimit row)` — deletes the RoleLimit row and confirms slots
           remain intact (FK is to `game_roles`, not `game_match_type_role_limits`).
        3. `does not retroactively rewrite match_slots when a RoleLimit.capacity
           is edited post-materialise` — bumps capacity 4 → 10 and confirms slot
           count stays at 4.

      Binding for plan 04-09 (Filament wizard) and plan 04-13 (match
      detail page): NEVER reload slot data from the live RoleLimit; always
      treat `match_slots` as the canonical capacity snapshot.

  - id: D-04-05-B
    decision: |
      **No alias-on-import (Pitfall 5) — direct `use App\Models\GameMatch;`.**

      Same precedent as MatchStatusService (D-04-04-C). The materialiser file
      contains zero `match($x)` expressions, so the Pitfall 5 defensive alias
      `use App\Models\Match as MatchModel;` is unnecessary. The plan body
      referenced `MatchModel` in its `<interfaces>` section because it was
      authored before D-04-03-A renamed the class — but D-04-03-A explicitly
      supersedes Pitfall 5, and direct `use App\Models\GameMatch;` is the
      canonical Phase 4 idiom.

  - id: D-04-05-C
    decision: |
      **PHPStan L8 null-guard on `$match->gameMatchType` (BelongsTo accessor).**

      Eloquent's BelongsTo accessor is typed `?Model` in PHPStan's view because
      the FK *could* dangle. In practice every `matches` row has a NOT NULL
      `game_match_type_id` (plan 04-02 migration), so the accessor will never
      return null at runtime. PHPStan L8 demands the explicit guard regardless.

      The guard returns 0 (same as the empty-roleLimits happy path) on the
      pathological case rather than throwing — semantically equivalent to
      "matchType has no roleLimits". The plan-body verbatim snippet from
      RESEARCH Pattern 3 omitted this guard; the implementation is
      pattern-equivalent with one extra null-safety branch added (Rule 1
      type-correctness, same precedent as Phase 1 plan 01-13 UserResource
      locale options).
metrics:
  duration_minutes: 4
  completed: 2026-05-13
---

# Phase 4 Plan 05: MatchSlotMaterialiserService Summary

**One-liner:** `App\Services\MatchSlotMaterialiserService::materialise(GameMatch $match): int` lands the snapshot-at-create primitive that writes one `MatchSlot` row per `(game_role_id, slot_index ∈ [0, capacity))` tuple from the matchType's `roleLimits`, all inside `DB::transaction`; snapshot semantics (game_role_id + sort_order frozen at materialise-time; future RoleLimit edits do NOT retroactively rewrite open match_slots) are locked by 3 dedicated assertions, the HLL Scrim 50v50 invariant (50 slots across the canonical 15-role capacity matrix) is verified, and 7 GREEN Pest tests replace the Wave 0 stub.

## What Shipped

### `apps/web/app/Services/MatchSlotMaterialiserService.php` (new — 93 LOC)

| Attribute | Value |
|---|---|
| Class | `final class MatchSlotMaterialiserService` (stateless, container-resolved) |
| Method signature | `public function materialise(GameMatch $match): int` |
| Return | Count of MatchSlot rows inserted (0 if matchType has empty roleLimits) |
| Transaction | `DB::transaction` wraps the entire loop — partial materialisation impossible |
| Eager-load | `$match->loadMissing('gameMatchType.roleLimits')` (defensive — no-op when caller already loaded) |
| Null guard | Explicit `$matchType === null` check returns 0 (PHPStan L8 type-correctness; D-04-05-C) |

#### Method body (verbatim)

```php
public function materialise(GameMatch $match): int
{
    return DB::transaction(function () use ($match): int {
        $match->loadMissing('gameMatchType.roleLimits');
        $count = 0;

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
```

#### Snapshot semantics rationale (Assumption A1 — now D-04-05-A invariant)

- **`slot.game_role_id`** is a FK to `game_roles`, NOT to `game_match_type_role_limits`. Deleting a RoleLimit row leaves the slots intact. The role itself is what the slot conceptually represents.
- **`slot.sort_order`** is a value copy of `roleLimit.sort_order` at materialise-time. The slot's display order is frozen against later RoleLimit edits.
- **No back-reference to the originating RoleLimit row** is intentional. The materialiser is a one-shot translation from "matchType configuration" into "match-specific slot grid"; once written, slots are independent of the configuration table.
- **Outer transaction wrapping** (Pitfall 3) — the CALLER (Filament wizard `CreateMatch::handleRecordCreation` in plan 04-09) wraps `Match::create` + `materialise()` in a single outer transaction. This service's inner `DB::transaction` is defense-in-depth.

### `apps/web/tests/Feature/Services/MatchSlotMaterialiserServiceTest.php` (replaces Wave 0 stub — 232 LOC)

7 `it()` blocks, 19 assertions, all GREEN. The Wave 0 `placeholder` literal is removed (verified via `grep -l 'placeholder'` exit=1).

| # | `it()` name | Setup | Critical assertion |
|---|-------------|-------|--------------------|
| 1 | `produces N slots matching the sum of GameMatchType.roleLimits capacities` | 3 roles, capacities `[2, 3, 1]`, same-game | Total = 6; per-role counts match the capacity matrix |
| 2 | `produces 50 slots for a Scrim 50v50 GameMatchType` | 15 roles, canonical HLL capacity matrix (1+4+4+14+4+4+4+4+4+2+2+1+1+1+0 = 50; includes the zero-capacity `crewman` edge) | Total = 50 (SC-1 invariant verified) |
| 3 | `produces 0 slots when GameMatchType has empty roleLimits` | Bare matchType, no roleLimits | Returns 0; no error |
| 4 | `throws QueryException when called twice on the same Match (idempotency-by-failure)` | 1 role, capacity 1; first call lands cleanly | Second call hits `match_slots_unique_slot` composite UNIQUE → `QueryException` |
| 5 | `snapshots sort_order from roleLimits to slots` | 3 roles with sort_orders `[10, 20, 30]` | Per-role `slot.sort_order` matches the limit's value (A1 part 1) |
| 6 | `snapshots game_role_id (slot survives deletion of the originating RoleLimit row)` | 1 role, capacity 3; delete the RoleLimit AFTER materialise | All 3 slots remain; `slot.game_role_id` still equals the role's id (A1 part 2 — proves FK is to `game_roles`, not `game_match_type_role_limits`) |
| 7 | `does not retroactively rewrite match_slots when a RoleLimit.capacity is edited post-materialise` | 1 role, capacity 4; bump to 10 AFTER materialise | Slot count stays at 4 (A1 part 3 — snapshot-at-create fidelity) |

### Canonical HLL Scrim 50v50 capacity matrix (verified)

The test mirrors `GameSeeder.php` lines 165-181 verbatim — the 15-role distribution that ships with the Phase 3 seeder:

| Role key | Capacity | Notes |
|---|---|---|
| `commander` | 1 | |
| `officer` | 4 | |
| `squad_leader` | 4 | |
| `rifleman` | 14 | Largest slot — backbone infantry |
| `assault` | 4 | |
| `automatic_rifleman` | 4 | |
| `medic` | 4 | |
| `engineer` | 4 | |
| `support` | 4 | |
| `heavy_machine_gunner` | 2 | |
| `anti_tank` | 2 | |
| `sniper` | 1 | |
| `spotter` | 1 | |
| `tank_commander` | 1 | |
| `crewman` | 0 | Zero-capacity edge — exercises the inner `for ($i = 0; $i < 0; $i++)` no-op in the same fixture |
| **Total** | **50** | **SC-1 invariant** |

## Verification

| Gate | Command | Result |
|---|---|---|
| Plan tests | `make pest ARGS="--filter=MatchSlotMaterialiserServiceTest"` | **7 passed, 19 assertions, 1.28s** |
| Full Pest suite | `make pest` | **14 incomplete + 350 passed** (Wave 0 baseline after 04-04 was 15 + 343; exactly +7 GREEN, −1 incomplete — MatchSlotMaterialiserServiceTest flipped) |
| PHPStan L8 (scoped) | `make phpstan` over the 2 new/modified files | **0 errors** |
| PHPStan L8 (full) | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress` | **0 errors** across full project scope |
| Pint test | `make pint --test app/Services/MatchSlotMaterialiserService.php tests/Feature/Services/MatchSlotMaterialiserServiceTest.php` | clean |
| `placeholder` literal removed | `grep -l 'placeholder' tests/Feature/Services/MatchSlotMaterialiserServiceTest.php` | empty (exit=1) |

## Decisions Made

- **D-04-05-A:** Slot snapshot semantics locked — `slot.game_role_id` FKs to `game_roles` (not `game_match_type_role_limits`); `slot.sort_order` is a value snapshot. Verified by 3 dedicated tests.
- **D-04-05-B:** No alias-on-import (Pitfall 5) — direct `use App\Models\GameMatch;` is the canonical Phase 4 idiom per D-04-03-A / D-04-04-C.
- **D-04-05-C:** Explicit null-guard on `$match->gameMatchType` for PHPStan L8 type-correctness; pathological-null returns 0 (semantically equivalent to "empty roleLimits").

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Type-correctness] PHPStan L8 `property.nonObject` on `$match->gameMatchType->roleLimits`**
- **Found during:** Task 1 PHPStan pre-commit gate
- **Issue:** Eloquent BelongsTo accessor is `?Model` in PHPStan's view; the RESEARCH Pattern 3 verbatim snippet omits the null guard.
- **Fix:** Extracted `$matchType = $match->gameMatchType;` and added an `if ($matchType === null) { return 0; }` early return inside the transaction closure. Pathological-null returns 0 (same outcome as empty roleLimits). Same precedent as Phase 1 plan 01-13 UserResource locale-options annotation (PHPStan L8 type correctness is a CI gate per CLAUDE.md §3).
- **Files modified:** `apps/web/app/Services/MatchSlotMaterialiserService.php`
- **Commit:** cdd9cc3

**2. [Rule 1 — Type-correctness] PHPStan L8 `property.nonObject` on `MatchSlot::first()->game_role_id` in test**
- **Found during:** Task 1 PHPStan pre-commit gate (same run)
- **Issue:** `Builder::first()` returns `?Model`, and the test chained `->game_role_id` on the nullable result.
- **Fix:** Switched the assertion from `->first()->game_role_id` to `->value('game_role_id')` — `value()` returns the column value directly (typed `mixed`, not `?Model`), which PHPStan accepts in the `expect()->toBe()` chain.
- **Files modified:** `apps/web/tests/Feature/Services/MatchSlotMaterialiserServiceTest.php`
- **Commit:** cdd9cc3

### Non-deviations (planned ambiguities resolved)

- **Plan body referenced `App\Models\Match` / `MatchModel`:** The plan's `<interfaces>` section used `MatchModel` because it was authored before D-04-03-A. The spawn prompt explicitly bound `GameMatch` (NOT `Match`) for plans 04-04..04-13, and D-04-04-C established the direct-import idiom. Per D-04-05-B, this plan uses `use App\Models\GameMatch;` directly — no alias.

- **Idempotency by failure vs. silent skip:** The plan's `<execution_rules>` offered two options ("throw if slots already exist OR skip — pick one"). Chose **throw via composite UNIQUE** (idempotency-by-DB-constraint). Rationale: the must_haves explicitly call for "Calling materialise() twice on the same Match throws QueryException" + "idempotency-by-failure pattern". A silent skip would require an additional `MatchSlot::where(...)->exists()` check that races with the composite UNIQUE anyway; the DB-layer guard is sufficient and the test asserts the exception path.

- **Use of seeded Phase 3 GameMatchType vs. explicit fixture:** Plan offered both approaches. Chose **explicit construction** — `RefreshDatabase` per-test wipes the seeded HLL fixture, so the canonical 15-role matrix is rebuilt in the test body. This keeps the test self-contained and explicit about the 50-slot invariant source.

## Auth Gates

None — pure service/test work, no auth-bearing operations.

## Known Stubs

9 Wave 0 stubs remain incomplete-by-design (down from 10 after this plan):

| Stub | Flipped GREEN by |
|---|---|
| `Services/MatchSignupServiceTest` + `MatchSignupConcurrencyTest` + `Matches/MatchSignupTagRestrictedTest` | 04-06 |
| `Unit/Data/MatchDataTest` + `PublicMatchDataTest` + `EventDataTest` | 04-07 |
| `Observers/MatchEventSyncTest` | 04-08 |
| `Admin/MatchResourcePresentTest` + `MatchResourceCreateWizardTest` + `MatchAuditLogTest` + `Services/MatchResultServiceTest` | 04-09 |
| `Matches/MatchCalendarPageTest` + `MatchShowPageTest` + `MatchSignupControllerTest` | 04-10 |

`MatchSlotMaterialiserServiceTest` is now fully GREEN. No new accidental stubs introduced.

## Threat Surface Notes

Threat register T-04-05-01..05 fully addressed:

- **T-04-05-01 (slot count drift from RoleLimit edits):** Snapshot-at-create — `slot.game_role_id` + `slot.sort_order` are value snapshots, not FKs to RoleLimit. Verified by the 3 snapshot-fidelity tests (sort_order, game_role_id, capacity edit post-materialise).
- **T-04-05-02 (partial materialisation):** `DB::transaction` wraps the entire loop. Any insert failure rolls back ALL prior inserts. (Not explicitly fault-injected — Laravel's transaction contract is the structural guarantee; same accept-pattern as plan 04-04's transaction integrity test.)
- **T-04-05-03 (duplicate materialise doubles slot count):** Composite UNIQUE `match_slots_unique_slot` on `(match_id, game_role_id, slot_index)` blocks duplicate inserts. Verified by the idempotency-by-failure test.
- **T-04-05-04 (activity log noise — N slot writes = N audit rows):** Accepted. MatchSlot has LogsActivity (plan 04-03). 50 audit rows per HLL Scrim materialise is acceptable for D-012 audit completeness.
- **T-04-05-05 (cross-game role_id):** Mitigated UPSTREAM by `GameMatchTypeRoleLimit::saving()` listener (plan 03-03) — the materialiser reads RoleLimits already validated for same-game pairing. No defensive check needed here.

No new threat-flag surface introduced.

## Commits

| Hash | Task | Files | Highlights |
|---|---|---|---|
| `cdd9cc3` | Task 1 — MatchSlotMaterialiserService + GREEN test | 2 | DB::transaction wrap; loadMissing eager-load; 7 it() blocks GREEN; HLL Scrim 50v50 → 50 slots verified; PHPStan L8 + Pint clean |

## Self-Check: PASSED

- `apps/web/app/Services/MatchSlotMaterialiserService.php` exists (created — 93 LOC, verified by PHPStan analysing the file)
- `apps/web/tests/Feature/Services/MatchSlotMaterialiserServiceTest.php` modified (Wave 0 stub replaced — 232 LOC, 7 `it()` blocks, 0 `markTestIncomplete`, 0 `placeholder` literal — grep exit=1)
- Commit `cdd9cc3` present in `git log --oneline -5`
- `make pest --filter=MatchSlotMaterialiserServiceTest`: 7 passed, 19 assertions
- Full Pest suite: 350 passed (+7 vs plan 04-04 close) / 14 incomplete (−1 vs plan 04-04 close)
- `make phpstan` (full): 0 errors
- `make pint --test` (2 task files): clean
- HLL Scrim 50v50 invariant (50 slots from the canonical 15-role matrix) GREEN
