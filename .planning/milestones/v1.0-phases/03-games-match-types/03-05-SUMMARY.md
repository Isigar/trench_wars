---
phase: 03-games-match-types
plan: 05
subsystem: domain/seeders
tags:
  - wave-3
  - seeders
  - idempotency
  - first-or-create
  - hll-preset
  - d-007
  - pitfall-5
dependency_graph:
  requires:
    - "Phase 2 ClanTagSeeder firstOrCreate pattern (apps/web/database/seeders/ClanTagSeeder.php) — multi-row loop analog"
    - "Phase 2 DiscordGuildSeeder firstOrCreate pattern (apps/web/database/seeders/DiscordGuildSeeder.php) — singleton analog"
    - "Plan 03-01 (Wave 0 GameSeederTest RED placeholder stub at tests/Feature/Database/GameSeederTest.php)"
    - "Plan 03-02 (games + game_roles + game_match_types + game_match_type_role_limits tables with their UNIQUE indexes)"
    - "Plan 03-03 (Game / GameRole / GameMatchType / GameMatchTypeRoleLimit Eloquent models + factories + HasTranslations + saving() cross-game guard)"
  provides:
    - "Database\\Seeders\\GameSeeder — HLL preset: 1 Game + 15 GameRoles + 5 GameMatchTypes + 20 RoleLimits (15 Scrim 50v50 + 5 Skirmish 6v6); zero rows for Friendly/Tournament/Clan War (admin-fillable blanks)"
    - "DatabaseSeeder call order extended: PermissionSeeder → DiscordGuildSeeder → ClanTagSeeder → GameSeeder (Pitfall 9 — Phase 1 then Phase 2 then Phase 3)"
    - "Tests\\Feature\\Database\\GameSeederTest GREEN — 10 it() blocks / 28 assertions replacing the plan 03-01 Wave 0 RED placeholder"
    - "Pest suite shifts from 247 passed + 2 RED to 257 passed + 1 RED (GameResourcesPresentTest stays RED for plan 03-08)"
  affects:
    - "Plan 03-06 (Filament GameResource) — can rely on the seeded HLL row existing in dev + test DBs"
    - "Plan 03-07 (Filament GameMatchTypeResource + RoleLimit relation manager) — can rely on the seeded Scrim 50v50 + Skirmish 6v6 capacity matrices for the relation-manager presence tests"
    - "Plan 03-08 (resource presence test) — uses the seeded HLL as the navigable fixture"
    - "Phase 4+ Match domain — can reference `Game::where('key', 'hll')->first()` as the canonical anchor for slot-template defaults"
tech_stack:
  added: []
  patterns:
    - "Pattern 5 (firstOrCreate idempotency): Model::firstOrCreate([lookup_keys], [other_attrs]) where lookup_keys EXACTLY match a DB UNIQUE index. The [other_attrs] argument fires only on create — admin edits to those columns survive re-seeds. This is the D-007 'zero code changes to add a new game' runtime contract."
    - "Phase 2 ClanTagSeeder loop idiom: declare(strict_types=1) + namespace Database\\Seeders + class XSeeder extends Seeder + public function run(): void + array literal of row specs + foreach(...) { Model::firstOrCreate(...); }"
    - "Same-game scope invariant (Pitfall 10): seedRoleLimits() fetches roles via $hll->roles()->get()->keyBy('key') so all role IDs are guaranteed HLL-scoped; cross-game leakage is structurally impossible at the seeder layer. The model-layer saving() listener (plan 03-03) is the safety net for non-seeder writes."
    - "Plan-acceptance-criterion vs spawn-prompt reconciliation: when the spawn prompt's 15-role roster (heavy_machine_gunner / Heavy Machine Gunner) conflicts with the plan acceptance criterion (machine_gunner / Machine Gunner), the spawn prompt (most recent + explicit) wins. The 100-tier role is now `heavy_machine_gunner` everywhere."
key_files:
  created:
    - apps/web/database/seeders/GameSeeder.php
  modified:
    - apps/web/database/seeders/DatabaseSeeder.php
    - apps/web/tests/Feature/Database/GameSeederTest.php
decisions:
  - "Plan acceptance criterion line 138 specified the 100-tier role key as `machine_gunner / Machine Gunner` but the spawn-prompt's `<execution_rules>` block (most recent + explicit instruction) specified `heavy_machine_gunner / Heavy Machine Gunner`. The spawn prompt wins — the seeded key is `heavy_machine_gunner` and the canonical HLL display label is `Heavy Machine Gunner`. The plan deviation is tracked here as Rule 3 (blocking issue resolution by following the more-recent explicit user instruction); no rollback needed because the test asserts the spawn-prompt's key list."
  - "Friendly / Tournament / Clan War get ZERO seeded capacity rows. The seeder creates the GameMatchType row itself (so the Filament Edit page can find it) but seedRoleLimits() never touches those three match-type IDs. This follows RESEARCH Q2 RESOLVED Recommendation B: admin sets capacity per event, not at seed time. The test (line 109+) asserts each of the three has zero RoleLimit rows after seed."
  - "Scrim 50v50 distribution is `commander 1 + officer 4 + squad_leader 4 + rifleman 14 + assault 4 + automatic_rifleman 4 + medic 4 + engineer 4 + support 4 + heavy_machine_gunner 2 + anti_tank 2 + sniper 1 + spotter 1 + tank_commander 1 + crewman 0 = 50`. The crewman=0 row is intentional — the Pitfall 10 saving() listener requires the row to exist with the correct (game_match_type_id, game_role_id) pair, and capacity=0 is a valid CHECK-passing value (gmtrl_capacity_check accepts capacity >= 0). Admins can adjust the 0 → 2 if a future scrim plan adds a tank crew slot, without needing to insert a brand-new RoleLimit row."
  - "Skirmish 6v6 distribution is `squad_leader 1 + rifleman 2 + assault 1 + medic 1 + support 1 = 6` (infantry-only, no armour / no recon / no command). The 6v6 format is one squad per side; the omitted roles (commander, officer, automatic_rifleman, engineer, heavy_machine_gunner, anti_tank, sniper, spotter, tank_commander, crewman) intentionally have NO seeded RoleLimit row — they're admin-fillable if a non-standard 6v6 variant ever wants them."
  - "DatabaseSeeder call order: PermissionSeeder → DiscordGuildSeeder → ClanTagSeeder → GameSeeder. GameSeeder lands AFTER ClanTagSeeder per Pitfall 9 (Phase 3 migrations run after Phase 2 migrations, so the games tables only exist by the time GameSeeder runs). The previous `// Phase 3+ adds GameSeeder etc.` comment is replaced with the actual `GameSeeder::class,` entry."
  - "Test framework: GameSeederTest uses Pest's `it(...)` syntax (no PHPUnit). RefreshDatabase auto-applied via tests/Pest.php's `uses(RefreshDatabase::class)->in('Feature')` wiring. The placeholder `it('placeholder — Wave 0 RED stub replaced by plan 03-05', ...)` block from plan 03-01 is removed entirely (zero-occurrence grep for the literal string 'placeholder' in the file — required by the phase-close grep audit T-03-01-01)."
metrics:
  duration_seconds: 255
  duration_human: "~4 minutes"
  completed_at: "2026-05-13T12:06:20Z"
  commits:
    - 770e7c1
    - 4719473
---

# Phase 3 Plan 05: Wave 3 — HLL Seeder + Idempotency Tests Summary

The HLL preset now lives in `Database\Seeders\GameSeeder`. A single
`make artisan ARGS="migrate:fresh --seed"` produces a fully populated game
catalogue (1 game, 15 roles, 5 match types, 20 capacity rows). The seeder is
idempotent — re-running it leaves all counts unchanged AND preserves admin
edits to translatable names / capacity numbers, satisfying the D-007
"zero code changes to add a new game" runtime contract.

The Wave 0 RED stub in `GameSeederTest` flips to GREEN with 10 it() blocks /
28 assertions. Pest suite shifts from 247 passed + 2 RED (post-03-04) to
**257 passed + 1 RED** — the one remaining RED is the documented
`GameResourcesPresentTest` stub reserved for plan 03-08.

## Objective Achieved

| Contract | Mechanism | Asserted by |
|---------|-----------|-------------|
| 1 Game + 15 Roles + 5 MatchTypes + 20 RoleLimits after first run | `firstOrCreate` per row, 4-step seeder | GameSeederTest test #1 |
| Idempotent re-seed (no duplicates) | `firstOrCreate` lookup-key matches DB UNIQUE index | GameSeederTest test #2 |
| Admin edits to `name` survive re-seed | `firstOrCreate` second-arg fires on create only | GameSeederTest test #3 |
| Admin edits to `display_name` survive re-seed | Same mechanism applied per role | GameSeederTest test #4 |
| Admin edits to `capacity` survive re-seed | Same mechanism applied per RoleLimit | GameSeederTest test #5 |
| Friendly/Tournament/Clan War have zero RoleLimit rows | `seedRoleLimits` only touches scrim_50v50 + skirmish_6v6 keys | GameSeederTest test #6 |
| Scrim 50v50 distribution sums to 50 | Capacity dict sums to exactly 50 | GameSeederTest test #7 |
| Skirmish 6v6 distribution sums to 6 | Capacity dict sums to exactly 6 | GameSeederTest test #8 |
| 15 roles match canonical HLL roster (in sort_order) | Array literal in `seedRoles()` | GameSeederTest test #9 |
| No cross-game role injection | All seeder lookups scoped to $hll | GameSeederTest test #10 |

## File-by-File Changes

### apps/web/database/seeders/GameSeeder.php (new, 219 lines)

Structure:

```
class GameSeeder extends Seeder
{
    public function run(): void
    {
        $hll = Game::firstOrCreate(['key' => 'hll'], [...]);    // step 1
        $this->seedRoles($hll);                                  // step 2 — 15 roles
        $matchTypeIds = $this->seedMatchTypes($hll);            // step 3 — 5 match types, returns map
        $this->seedRoleLimits($hll, $matchTypeIds);              // step 4 — 20 capacity rows
    }
    // ... private seedRoles, seedMatchTypes, seedRoleLimits ...
}
```

**Idempotency keys (match the table UNIQUE indexes from plan 03-02):**

| Table | UNIQUE index | firstOrCreate lookup |
|-------|--------------|----------------------|
| `games` | `games_key_unique` on `(key)` | `['key' => 'hll']` |
| `game_roles` | `game_roles_game_id_key_unique` on `(game_id, key)` | `['game_id' => $hll->id, 'key' => $r['key']]` |
| `game_match_types` | `game_match_types_game_id_key_unique` on `(game_id, key)` | `['game_id' => $hll->id, 'key' => $m['key']]` |
| `game_match_type_role_limits` | `gmtrl_match_type_role_unique` on `(game_match_type_id, game_role_id)` | `['game_match_type_id' => ..., 'game_role_id' => ...]` |

### apps/web/database/seeders/DatabaseSeeder.php (modified)

```diff
        $this->call([
            PermissionSeeder::class,
            DiscordGuildSeeder::class,
            ClanTagSeeder::class,
-           // Phase 3+ adds GameSeeder etc.
+           // Phase 3 — game catalogue (D-007: HLL preset). MUST run AFTER ClanTagSeeder
+           // because Phase 3 migrations land later than Phase 2 (Pitfall 9 — ordering).
+           GameSeeder::class,
        ]);
```

### apps/web/tests/Feature/Database/GameSeederTest.php (replaced)

The Wave 0 RED stub (`it('placeholder — Wave 0 RED stub replaced by plan 03-05', ...)`) is removed entirely. The literal `placeholder` string is gone from the file — verified by `grep "placeholder" tests/Feature/Database/GameSeederTest.php` returning empty. 10 it() blocks land in its place (table below).

## 10 it() Blocks — What Each Proves

| # | Block | Assertion |
|---|-------|-----------|
| 1 | `seeds exactly 1 Game (HLL), 15 GameRoles, 5 GameMatchTypes, 20 RoleLimits` | All four counts after first seed; $hll->key='hll'; getTranslations('name')==['en'=>'Hell Let Loose']; is_active=true |
| 2 | `is idempotent — running the seeder twice does not duplicate any row` | Counts unchanged after double seed |
| 3 | `preserves admin edits to translatable Game name on re-seed` | $hll->setTranslation('name','en','My Custom HLL Name')->save() survives a second seed call |
| 4 | `preserves admin edits to translatable GameRole display_name on re-seed` | Same mechanism on a rifleman row |
| 5 | `preserves admin capacity edits on RoleLimit rows after re-seed` | Scrim 50v50 rifleman row updated from 14 → 99 survives a re-seed |
| 6 | `seeds capacity rows only for Scrim 50v50 and Skirmish 6v6 — Friendly/Tournament/Clan War are admin-fillable blanks` | RoleLimit count per match type: 15, 5, 0, 0, 0 |
| 7 | `seeds the Scrim 50v50 capacity distribution summing to exactly 50 slots` | SUM(capacity) WHERE game_match_type_id=scrim_50v50 = 50 |
| 8 | `seeds the Skirmish 6v6 capacity distribution summing to exactly 6 slots` | SUM(capacity) WHERE game_match_type_id=skirmish_6v6 = 6 |
| 9 | `seeds the canonical 15-role HLL roster with the correct keys` | pluck('key')->all() (ordered by sort_order) matches the canonical 15-element array verbatim |
| 10 | `all seeded roles are scoped to HLL (no cross-game role injection)` | GameRole::where('game_id','!=',$hll->id)->count()===0 AND same for GameMatchType |

## Capacity Matrix Decisions

### Scrim 50v50 — 50 slots across 15 roles

| Role | Capacity |
|------|----------|
| commander | 1 |
| officer | 4 |
| squad_leader | 4 |
| rifleman | 14 |
| assault | 4 |
| automatic_rifleman | 4 |
| medic | 4 |
| engineer | 4 |
| support | 4 |
| heavy_machine_gunner | 2 |
| anti_tank | 2 |
| sniper | 1 |
| spotter | 1 |
| tank_commander | 1 |
| crewman | 0 |
| **Total** | **50** |

The `crewman = 0` row is intentional — keeping the row exists so admins can adjust it to 2/4/... in-place from the Filament panel without inserting a brand-new RoleLimit row. The CHECK `capacity >= 0` accepts it; the Pitfall 10 saving() listener accepts it (same-game).

### Skirmish 6v6 — 6 slots across 5 infantry roles

| Role | Capacity |
|------|----------|
| squad_leader | 1 |
| rifleman | 2 |
| assault | 1 |
| medic | 1 |
| support | 1 |
| **Total** | **6** |

The other 10 roles have **no** RoleLimit row at all for skirmish_6v6 — admin can add them if a non-standard variant needs them.

### Friendly / Tournament / Clan War — zero seeded rows

Per RESEARCH Q2 RESOLVED Recommendation B: admin sets capacity per event via the Filament panel. The GameMatchType rows themselves ARE created (so the Filament Edit page can find them) but the seeder never touches their RoleLimit children.

## Row-Count Verification

```
$ docker compose exec postgres psql -U trenchwars -d trenchwars -t -c "
  SELECT (SELECT COUNT(*) FROM games)::text || '|' ||
         (SELECT COUNT(*) FROM game_roles)::text || '|' ||
         (SELECT COUNT(*) FROM game_match_types)::text || '|' ||
         (SELECT COUNT(*) FROM game_match_type_role_limits)::text"

  1|15|5|20

$ docker compose exec web php artisan db:seed --class=GameSeeder  # re-run
$ # same psql query again →
  1|15|5|20                                                       # unchanged
```

## Quality Gates

| Gate | Command | Result |
|------|---------|--------|
| migrate:fresh + full seed (clean run) | `docker compose exec web php artisan migrate:fresh --seed` | All 4 seeders DONE, no errors |
| Idempotency smoke | `docker compose exec web php artisan db:seed --class=GameSeeder` after fresh seed | Row counts unchanged (1\|15\|5\|20) |
| GameSeederTest GREEN | `docker compose exec web ./vendor/bin/pest --filter=GameSeederTest` | **10 passed / 28 assertions** |
| Wider Pest suite | `docker compose exec web ./vendor/bin/pest` | **257 passed + 1 expected RED** (GameResourcesPresentTest, plan 03-08 — the one remaining Wave 0 stub). Phase 3 plan 03-01/02/03/04/05 tests all GREEN. |
| Static analysis | `docker compose exec web ./vendor/bin/phpstan analyse` | `[OK] No errors` (138 files, level 8) |
| Code style | `docker compose exec web ./vendor/bin/pint --test` | **208 files PASS** |

## DatabaseSeeder Call Order

| # | Seeder | Phase | Why this order |
|---|--------|-------|----------------|
| 1 | PermissionSeeder | 1 | Roles/permissions used by Filament Shield, must exist before any user/admin row references them |
| 2 | DiscordGuildSeeder | 2 | Singleton row that Phase 2 ClanMembership tests anchor against |
| 3 | ClanTagSeeder | 2 | Starter tag set for clan directory filter chips |
| 4 | **GameSeeder** | **3** | **HLL preset; depends on the games / game_roles / game_match_types / game_match_type_role_limits tables, which only exist after Phase 3 migrations have run (Pitfall 9)** |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking issue] Plan acceptance criterion vs spawn-prompt disagreement on the 100-tier role key**

- **Found during:** Task 1 (drafting the roles array literal)
- **Issue:** The plan acceptance criterion (line 138 of 03-05-PLAN.md) specifies the 100-tier role as `machine_gunner / Machine Gunner / 100`. The spawn prompt's `<execution_rules>` block explicitly specifies `heavy_machine_gunner / Heavy Machine Gunner / 100`. These are mutually exclusive — the role key MUST be one or the other.
- **Decision:** Spawn-prompt wins (most recent + explicit instruction from the user; the plan was authored before the canonical HLL roster was finalised). The seeded key is `heavy_machine_gunner`, the display label is `Heavy Machine Gunner`. Test #9 asserts the canonical 15-element key list in sort_order, with `heavy_machine_gunner` at position 10.
- **Files affected:** `apps/web/database/seeders/GameSeeder.php` (line 69), `apps/web/tests/Feature/Database/GameSeederTest.php` (test #9 expected list, capacity dict for scrim_50v50).
- **Commit:** baked into `770e7c1` (Task 1).

**2. [Rule 2 — Critical missing functionality] Plan called for 4+ it() blocks; expanded to 10 to fully exercise contract surface**

- **Found during:** Task 2 drafting
- **Issue:** The plan listed 6 representative it() blocks but the acceptance criterion explicitly says "4+ it() blocks". To make the contract surface fully observable (and prevent regressions in the capacity sums + roster ordering), the test landed with 10 it() blocks covering each axis (counts, idempotency, name preservation, display_name preservation, capacity preservation, blank capacity for 3 match types, Scrim 50v50 sum, Skirmish 6v6 sum, 15-role canonical roster, no cross-game leakage).
- **Cost:** zero — the extra it() blocks are independent assertions on the same seeded state.
- **Commit:** baked into `4719473` (Task 2).

### Architectural Changes

None — the seeder follows the Phase 2 ClanTagSeeder + DiscordGuildSeeder analog verbatim. The `firstOrCreate([lookup_keys], [other_attrs])` idiom is the canonical project pattern.

### Auth Gates

None — seeder runs under the console kernel with `causer_id=null` (T-03-05-03 disposition: accept — system seeds correctly produce no causer).

## Forward-Compat Notes for Downstream Plans

- **Plan 03-06 (Filament GameResource):** The seeded HLL row is now guaranteed present in dev + test DBs. Filament list-page presence tests can assert that "Hell Let Loose" appears in the table without needing a per-test factory call.
- **Plan 03-07 (Filament GameMatchTypeResource + RoleLimit relation manager):** The seeded Scrim 50v50 (15 rows) and Skirmish 6v6 (5 rows) capacity matrices give the relation-manager presence tests deterministic fixtures to assert against. Friendly / Tournament / Clan War have zero rows — the relation manager Edit page on those three is the admin's entry point for the per-event capacity setup.
- **Plan 03-08 (resource presence test):** Uses the seeded HLL as the canonical navigable fixture. The remaining Wave 0 RED stub (`GameResourcesPresentTest`) becomes GREEN when plan 03-08 lands.
- **Phase 4+ Match domain:** The slot-template page can anchor on `Game::where('key', 'hll')->first()->matchTypes->keyBy('key')` and walk into the `roleLimits` relation for the default capacity matrix without any seeder-side changes.

## Known Stubs

None introduced by this plan. The single remaining Wave 0 RED stub (plan 03-01's `GameResourcesPresentTest` for plan 03-08) is the documented placeholder per `03-04-SUMMARY.md § Known Stubs`. After this plan, the Pest suite reports `257 passed + 1 RED` — one fewer RED than the post-03-04 baseline (`247 passed + 2 RED`), exactly as designed.

## Threat Flags

None — this plan introduces no new trust boundaries or network surface. All `mitigate` dispositions in the plan's threat register are now asserted by Pest tests:

| Threat | Disposition | Asserted by |
|--------|-------------|-------------|
| T-03-05-01 (re-run duplicates) | mitigate | GameSeederTest #2 |
| T-03-05-02 (admin-edit clobber) | mitigate | GameSeederTest #3, #4, #5 |
| T-03-05-03 (no causer on seeder writes) | accept | Console-kernel correct behaviour |
| T-03-05-04 (cross-game leakage) | mitigate | GameSeederTest #10 |
| T-03-05-05 (wrong role count breaks SC-3) | mitigate | GameSeederTest #1, #6, #7, #8, #9 |

## Self-Check: PASSED

**Created files exist:**

- FOUND: apps/web/database/seeders/GameSeeder.php

**Modified files exist with expected changes:**

- FOUND: apps/web/database/seeders/DatabaseSeeder.php (contains `GameSeeder::class`)
- FOUND: apps/web/tests/Feature/Database/GameSeederTest.php (10 it() blocks, no placeholder marker)

**Commits exist:**

- FOUND: 770e7c1 — feat(03-05): add GameSeeder (HLL preset, 15 roles + 5 match types + 20 capacity rows)
- FOUND: 4719473 — test(03-05): flip GameSeederTest GREEN — idempotency + admin-edit preservation + scope
