---
phase: 08-rcon-automation
plan: 04
subsystem: eloquent-models
tags: [eloquent, jsonb-cast, postgres, unique-constraint, check-constraint, logs-activity, factory, tdd]

# Dependency graph
requires:
  - phase: 08-02
    provides: match_events table (UNIQUE on match_id+crcon_stream_id, CHECK on event_type) + match_player_stats table (UNIQUE on match_id+player_id, CHECK on non-negative counters)
  - phase: 08-01
    provides: MatchEventFactory + MatchPlayerStatFactory Wave 0 stubs (this plan replaces them with real definitions) + MatchEventIdempotencyTest Wave 0 RED stub (this plan turns it GREEN)
provides:
  - App\Models\MatchEvent Eloquent model with HasUuids + LogsActivity + payload array cast + scopes ofType/since + $timestamps=false (append-only stream)
  - App\Models\MatchPlayerStat Eloquent model with HasUuids + LogsActivity + integer/array casts + kdr() accessor (division-by-zero safe)
  - MatchEventFactory real definition with 10 state methods covering every canonical match_event_type (game_start, round_start, player_kill, player_team_kill, player_connect, player_disconnect, team_switch, round_end, match_end, manual_error) + auto-incrementing CRCON stream id
  - MatchPlayerStatFactory real definition with forMatch()/forPlayer() state helpers (08-03 idiom)
affects:
  - 08-07-PLAN.md (MatchEventIngestService — relies on the (match_id, crcon_stream_id) UNIQUE constraint to absorb duplicate POSTs as no-ops via UniqueConstraintViolationException catch)
  - 08-08-PLAN.md (MatchPlayerStatAggregator — reads MatchEvent::query()->ofType('player_kill')->ofType('player_team_kill') and writes MatchPlayerStat via updateOrCreate keyed on (match_id, player_id))
  - 08-12-PLAN.md (Discord bot embed — reads MatchPlayerStat + uses kdr() accessor to pick the MVP for the post-match summary)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Append-only Eloquent model with $timestamps=false — when the migration omits Laravel's standard created_at/updated_at and uses domain-specific timeline columns (occurred_at + ingested_at with Postgres DEFAULT now()), the model MUST disable timestamps or Eloquent INSERT errors with SQLSTATE 42703 on missing updated_at. Mirrors the activity_log table convention from Phase 1."
    - "DB::transaction() savepoint wrapping for UNIQUE-violation probes inside RefreshDatabase — the outer RefreshDatabase transaction enters Postgres failed-transaction state on the first 23505 violation and aborts all subsequent queries (SQLSTATE 25P02). Wrapping the failing INSERT in DB::transaction() pins the rollback to the savepoint, leaving the outer transaction healthy for follow-on assertions like updateOrCreate() idempotency."
    - "Static stream-id counter on factory — strictly-increasing CRCON-shaped stream IDs ('1711657986-0', '1711657986-1', ...) generated via static int $counter, mirroring CRCON's per-server monotonic stream contract. Lets tests assert stream IDs by formula without explicit per-create overrides."

key-files:
  created:
    - apps/web/app/Models/MatchEvent.php
    - apps/web/app/Models/MatchPlayerStat.php
    - apps/web/tests/Unit/Phase8/MatchEventModelTest.php
    - apps/web/tests/Unit/Phase8/MatchPlayerStatModelTest.php
  modified:
    - apps/web/database/factories/MatchEventFactory.php
    - apps/web/database/factories/MatchPlayerStatFactory.php
    - apps/web/tests/Feature/Phase8/MatchEventIdempotencyTest.php

key-decisions:
  - "MatchEvent uses $timestamps = false (Rule 1 fix). The 08-02 migration installs occurred_at (timestamptz, set by the CRCON normaliser) + ingested_at (timestamptz DEFAULT now(), set by Postgres on row write) but deliberately omits Laravel's standard timestamps() pair — match_events is append-only with its own timeline columns. Without $timestamps=false the model attempts to INSERT a created_at/updated_at pair and crashes with SQLSTATE 42703 'column updated_at does not exist'. Mirrors the activity_log table convention."
  - "Unit tests opt into RefreshDatabase explicitly. Pest.php's global RefreshDatabase binding only attaches to Feature/ — Unit/ tests are intentionally fast/in-memory by default. Phase 8 Unit tests use real DB fixtures (factory-created GameMatch + Player rows) so they need RefreshDatabase to avoid row leakage across tests within the same file (caught when scopeOfType-then-scopeSince in MatchEventModelTest saw 4 events instead of 2)."
  - "UNIQUE-violation probes wrapped in DB::transaction() (savepoint pattern). Without the savepoint, the Postgres failed-transaction abort propagates to the outer RefreshDatabase transaction and breaks subsequent queries — the spec for MatchPlayerStatModelTest test 1 needs to run updateOrCreate() AFTER catching the UniqueConstraintViolationException, which is only possible if the transaction survives. The Feature/MatchEventIdempotencyTest happens to NOT need this because its UNIQUE-violation case is the last assertion in its `it()` block; the Unit test's combined probe-then-idempotency assertion is what forced the pattern."
  - "kdr() accessor is a plain method, not an Eloquent attribute accessor. The plan spec specifies a method call (`$stat->kdr()`) and the formula is small enough (deaths==0 branch) that an accessor would add ceremony without clarity. Returns float|int union (rounded ratio when deaths>0, raw kills int when deaths=0) per the plan's <behavior>."
  - "Factory state methods take primitive parameters (steam_ids as string), not Player models. The CRCON normalised event payload has steam_id_64 as raw text fields — never Player FKs (the player_kill event from CRCON predates any web Player::class linkage). Factory state methods mirror the wire-shape exactly so downstream MatchEventNormaliserContractTest can assert against the same shape."
  - "Auto-incrementing static counter for stream IDs. CRCON's stream id is '{unix_timestamp_seconds}-{increment}' — the increment is server-monotonic. The factory uses a static counter starting at 0 (fixed unix prefix '1711657986-') so tests that don't override crcon_stream_id get unique values across calls; tests that need a specific id pass it via ->create(['crcon_stream_id' => ...])."

# Metrics
duration: 7min
completed: 2026-05-14
---

# Phase 8 Plan 4: Wave 3 — MatchEvent + MatchPlayerStat Models + Idempotency Tests Summary

**Authored two new Eloquent models (MatchEvent, MatchPlayerStat) with HasUuids + LogsActivity (D-012) + real factories with state methods covering all 10 canonical CRCON event shapes; landed the (match_id, crcon_stream_id) UNIQUE idempotency feature test + two unit test files covering scopes, casts, CHECK constraints, kdr() accessor. 9 of the remaining 8 Phase 8 Wave 0 RED stubs turned GREEN this plan (4 idempotency cases + 4 unit MatchEvent cases + 3 unit MatchPlayerStat cases = 11 new GREEN cases; 1 stub `MatchEventIdempotencyTest` was already Wave 0 from 08-01). Full regression: 1054 PASS, 7 FAIL (all 7 remaining = Wave 0 RED stubs slated GREEN in plans 08-05..08-13).**

## Performance

- **Duration:** 7 min 14 s
- **Started:** 2026-05-14T03:56:03Z
- **Completed:** 2026-05-14T04:03:17Z
- **Tasks:** 2 / 2
- **Files created:** 4 (2 models + 2 unit test files)
- **Files modified:** 3 (2 factories Wave 0 → real + 1 idempotency feature test stub → GREEN)
- **Commits:** 3 (RED `c350813`; GREEN Task 1 `77bc435`; GREEN Task 2 `f358002`)

## Accomplishments

### TDD Gate Sequence

1. **RED** (commit `c350813`): authored 3 new test files (1 idempotency feature + 2 model unit tests) with 11 cases total. Pre-implementation verification: 9 of 11 fail with `Class "App\Models\MatchEvent" not found` / `Class "App\Models\MatchPlayerStat" not found`. 2 cases pass without the model because they probe the DB tier via `DB::table()->insert()` directly (event_type CHECK + negative-kills CHECK).
2. **GREEN Task 1** (commit `77bc435`): authored MatchEvent + MatchPlayerStat models, replaced 2 Wave 0 factory stubs. PHPStan L8 clean, Pint --test clean, tinker probe verifies `MatchEvent::factory()->kill('111','222')->raw()['event_type'] === 'player_kill'`.
3. **GREEN Task 2** (commit `f358002`): iterated on the test fixtures — added `uses(RefreshDatabase::class)` to both Unit tests (Pest.php global binding only targets Feature/) and wrapped the duplicate-INSERT probe in `DB::transaction()` to use a savepoint so the Postgres failed-transaction abort doesn't break subsequent assertions. All 11 cases GREEN.

### Models created (2)

1. **`App\Models\MatchEvent`** — `HasUuids` + `LogsActivity` + `HasFactory`. `$timestamps = false` (append-only stream with domain-specific timeline columns `occurred_at` + `ingested_at`; the 08-02 migration deliberately omits Laravel's standard timestamps pair). Fillable: `match_id`, `event_type`, `crcon_action`, `crcon_stream_id`, `payload`, `occurred_at`. Casts:
   - `payload => array` (jsonb roundtrip — verified by MatchEventModelTest jsonb-roundtrip case)
   - `occurred_at => datetime`, `ingested_at => datetime`
   - **Relations:** `match()` BelongsTo GameMatch with explicit FK `'match_id'` (D-04-03-B; D-04-03-A LOCKED).
   - **Scopes:** `ofType(string $type)` filters by event_type (consumed by plan 08-08 aggregator); `scopeSince(CarbonInterface $when)` filters by `occurred_at >= $when` (consumed by plan 08-07 to ignore pre-booking historical events).

2. **`App\Models\MatchPlayerStat`** — `HasUuids` + `LogsActivity` + `HasFactory`. Standard `created_at`/`updated_at` retained (the 08-02 migration uses `$table->timestamps()`). Fillable: `match_id`, `player_id`, `kills`, `deaths`, `team_kills`, `score`, `role_played`, `weapons_used`. Casts:
   - `kills/deaths/team_kills/score => integer`
   - `weapons_used => array` (jsonb weapon→count map populated by plan 08-08 aggregator)
   - **Relations:** `match()` BelongsTo GameMatch with explicit FK `'match_id'`; `player()` BelongsTo Player with explicit FK `'player_id'`.
   - **Accessor:** `kdr(): float|int` — returns `round(kills/deaths, 2)` when `deaths > 0`, falls back to `kills` (int) when `deaths === 0`. Division-by-zero safe. Used by plan 08-12 bot embed MVP picker.

### Factories upgraded from Wave 0 stubs (2)

3. **`MatchEventFactory`** — definition() defaults to a `player_kill` shape (random two Steam IDs + KARABINER 98K). The static `$streamIdCounter` produces strictly-increasing CRCON-shaped stream IDs (`1711657986-0`, `1711657986-1`, ...) on every call. State methods cover all 10 canonical match_event_type values from RESEARCH § event-shape table (lines 521-534):
   - `gameStart(string $map='Foy', string $mode='Warfare')`
   - `roundStart(int $roundNumber=1)`
   - `kill(string $killerSteam, string $victimSteam, string $weapon='KARABINER 98K')`
   - `teamKill(string $killerSteam, string $victimSteam, string $weapon='M1 GARAND')`
   - `connect(string $steam, string $name='Connecting')`
   - `disconnect(string $steam, string $name='Disconnecting')`
   - `teamSwitch(string $steam, string $name, string $fromTeam='axis', string $toTeam='allies')`
   - `roundEnd(string $winningTeam='allies', int $alliesScore=3, int $axisScore=1)`
   - `matchEnd(string $winningTeam='allies', int $alliesScore=3, int $axisScore=2)`
   - `manualError(string $kind='unreachable', string $detail='CRCON connection refused')` — also pins `crcon_stream_id=null` (web-synthesised events have no upstream CRCON stream id)
   - PHPStan-ignore stubs removed; canonical `@extends Factory<MatchEvent>` generic restored.

4. **`MatchPlayerStatFactory`** — definition: `kills = faker->numberBetween(0, 30)`, `deaths = faker->numberBetween(0, 15)`, `team_kills = 0`, `score = kills * 100`, `role_played = null`, `weapons_used = null`. State helpers:
   - `forMatch(GameMatch $m)` — pin `match_id = $m->id` (bypasses default GameMatch::factory()).
   - `forPlayer(Player $p)` — pin `player_id = $p->id` (bypasses default Player::factory()).
   - PHPStan-ignore stubs removed; canonical `@extends Factory<MatchPlayerStat>` generic restored.

### Tests turned GREEN (11 cases across 3 files)

**MatchEventIdempotencyTest (Feature, 4 cases — replaces Wave 0 stub):**
- Fresh insert with crcon_stream_id `1711657986-0` succeeds.
- Duplicate `(match_id, crcon_stream_id)` on the same match raises `UniqueConstraintViolationException`; message contains `match_events_match_stream_unique`.
- Same crcon_stream_id under a DIFFERENT match succeeds (composite UNIQUE, not stream alone).
- Multiple NULL crcon_stream_id rows under the same match all succeed (Postgres treats `NULL ≠ NULL` in UNIQUE) — permits manual_error rows that have no upstream stream id.

**MatchEventModelTest (Unit, 4 cases — new file):**
- event_type CHECK constraint rejects `'foo'` (DB::table()->insert with out-of-enum value raises QueryException; message contains `match_events_type_check`).
- `scopeOfType('player_kill')` filters across a 3-event fixture (2 kills + 1 connect) → 2 rows returned, all event_type='player_kill'.
- `scopeSince(now()->subHour())` filters out an event with `occurred_at = now()->subDays(2)` and retains an event with `occurred_at = now()->subMinutes(10)`.
- payload jsonb roundtrips through the array cast — set `$event->payload = ['weapon' => 'K98', 'distance_m' => 42]`, save, refresh, read back as array with both keys intact.

**MatchPlayerStatModelTest (Unit, 3 cases — new file):**
- Duplicate `(match_id, player_id)` raises QueryException via `mps_match_player_unique`; subsequent `MatchPlayerStat::updateOrCreate(['match_id'=>..., 'player_id'=>...], $stats)` is idempotent (one row remains, kills updated to 99). DB::transaction() savepoint pattern lets the outer transaction survive the abort.
- CHECK constraint rejects `kills=-1` (DB::table()->insert raises QueryException via `match_player_stats_nonneg_check`).
- `kdr()` accessor returns `2.0` for `kills=20, deaths=10`; returns `5` (int) for `kills=5, deaths=0` (division-by-zero fallback returns kills as-is).

## Task Commits

1. **RED — Phase 8 idempotency + model unit tests authored** — `c350813` (test)
2. **GREEN Task 1 — MatchEvent + MatchPlayerStat models + real factories** — `77bc435` (feat)
3. **GREEN Task 2 — test fixture iteration (RefreshDatabase + savepoint)** — `f358002` (test)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Models (2)
- `apps/web/app/Models/MatchEvent.php`
- `apps/web/app/Models/MatchPlayerStat.php`

### Unit Tests (2)
- `apps/web/tests/Unit/Phase8/MatchEventModelTest.php`
- `apps/web/tests/Unit/Phase8/MatchPlayerStatModelTest.php`

## Files Modified

### Factories (2 — Wave 0 stubs → real)
- `apps/web/database/factories/MatchEventFactory.php`
- `apps/web/database/factories/MatchPlayerStatFactory.php`

### Tests (1 — Wave 0 RED stub → 4 GREEN cases)
- `apps/web/tests/Feature/Phase8/MatchEventIdempotencyTest.php`

## Decisions Made

- **MatchEvent uses `$timestamps = false` (Rule 1 deviation).** The 08-02 migration installs `occurred_at` (timestamptz, set by the CRCON normaliser) + `ingested_at` (timestamptz DEFAULT now(), set by Postgres on row write) but deliberately omits Laravel's standard `timestamps()` pair — `match_events` is append-only with its own timeline columns. Without `$timestamps=false` the model attempts to INSERT a `created_at`/`updated_at` pair and crashes with SQLSTATE 42703 'column updated_at of relation match_events does not exist'. Mirrors the activity_log table convention from Phase 1.
- **Unit tests opt into RefreshDatabase explicitly.** Pest.php's global `RefreshDatabase` binding only attaches to `Feature/` (intentional — Unit tests are fast/in-memory by default). Phase 8 Unit tests use real DB fixtures (factory-created `GameMatch` + `Player` rows) so they need `RefreshDatabase` to avoid row leakage across tests within the same file (caught when scopeOfType-then-scopeSince in MatchEventModelTest saw 4 events instead of 2 due to leakage from the preceding `scopeOfType` test creating 3 events).
- **UNIQUE-violation probes wrapped in `DB::transaction()` (savepoint pattern).** Without the savepoint, the Postgres failed-transaction abort propagates to the outer `RefreshDatabase` transaction and breaks subsequent queries with SQLSTATE 25P02 'current transaction is aborted'. The spec for `MatchPlayerStatModelTest` test 1 needs to run `updateOrCreate()` AFTER catching the `UniqueConstraintViolationException`, which is only possible if the transaction survives. The Feature/`MatchEventIdempotencyTest` happens to NOT need this because its UNIQUE-violation case is the last assertion in its `it()` block; the Unit test's combined probe-then-idempotency assertion is what forced the pattern.
- **`kdr()` accessor is a plain method, not an Eloquent attribute accessor.** The plan spec specifies a method call (`$stat->kdr()`) and the formula is small enough (deaths==0 branch) that a Laravel Attribute would add ceremony without clarity. Returns a `float|int` union (rounded ratio when deaths>0, raw kills int when deaths=0) per the plan's `<behavior>` ("returns 5 for kills=5 deaths=0 (no division-by-zero)").
- **Factory state methods take primitive parameters (steam_ids as string), not Player models.** The CRCON normalised event payload has `steam_id_64` as raw text fields — never Player FKs (the `player_kill` event from CRCON predates any web Player::class linkage; resolution to Player happens in plan 08-08 aggregator via `Player::where('steam_id_64', $steam)`). Factory state methods mirror the wire-shape exactly so downstream `MatchEventNormaliserContractTest` (RED, slated GREEN in 08-07) can assert against the same shape.
- **Auto-incrementing static counter for stream IDs.** CRCON's stream id is `{unix_timestamp_seconds}-{increment}` — the increment is server-monotonic. The factory uses a static counter starting at 0 (fixed unix prefix `'1711657986-'`) so tests that don't override `crcon_stream_id` get unique values across calls; tests that need a specific id pass it via `->create(['crcon_stream_id' => '1711657986-0'])`. Verified by the four idempotency-test cases (each passes its own explicit stream id).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] MatchEvent needed `$timestamps = false`**
- **Found during:** Task 1, first Pest run of MatchEventIdempotencyTest.
- **Issue:** First INSERT raised `SQLSTATE[42703]: Undefined column: 7 ERROR: column "updated_at" of relation "match_events" does not exist`. Eloquent's default `$timestamps = true` makes the model expect `created_at` + `updated_at` columns, but the 08-02 migration deliberately omits Laravel's `$table->timestamps()` pair — `match_events` uses domain-specific timeline columns (`occurred_at` set by CRCON, `ingested_at` defaulted by Postgres).
- **Fix:** Added `public $timestamps = false;` to `MatchEvent` model class with a docblock explaining the append-only-stream rationale.
- **Files modified:** `apps/web/app/Models/MatchEvent.php`.
- **Verification:** All 4 MatchEventIdempotencyTest cases pass; the integration probe (`->create([...])->refresh()`) roundtrips cleanly.
- **Committed in:** `77bc435` (Task 1 GREEN commit — fix made before commit).
- **Why not Rule 4:** This is a model-level annotation matching the existing migration; no schema change, no business logic shift. The migration was already in place pre-plan; the plan's `<behavior>` correctly describes the column inventory without spelling out `$timestamps=false` because it assumed prior knowledge of the migration shape. Same character as plan 08-03's `credentials_encrypted` cast/jsonb mismatch — model-side adjustment to match an existing migration choice.

**2. [Rule 1 - Bug] Unit tests needed explicit `RefreshDatabase` opt-in**
- **Found during:** Task 2, first run of MatchEventModelTest after model was authored.
- **Issue:** The `scopeSince` test asserted "size 1" but got "size 4" — three events from the preceding `scopeOfType` test (run in the same Unit file) leaked into the database because Unit tests run without the global `RefreshDatabase` binding (Pest.php scopes it to `Feature/` only). Subsequent tests saw cumulative state.
- **Fix:** Added `uses(RefreshDatabase::class);` after imports in both `MatchEventModelTest.php` and `MatchPlayerStatModelTest.php`. Canonical Phase 2/4/8 pattern (e.g., `tests/Unit/Services/PlayerPrivacyGateTest.php`).
- **Files modified:** `apps/web/tests/Unit/Phase8/MatchEventModelTest.php`, `apps/web/tests/Unit/Phase8/MatchPlayerStatModelTest.php`.
- **Verification:** All 7 Unit cases (4 + 3) pass cleanly.
- **Committed in:** `f358002` (Task 2 GREEN commit).

**3. [Rule 1 - Bug] `DB::transaction()` savepoint needed inside UNIQUE-violation probe**
- **Found during:** Task 2, second run of MatchPlayerStatModelTest after RefreshDatabase fix.
- **Issue:** The duplicate-INSERT UNIQUE violation case put the outer `RefreshDatabase` transaction into Postgres failed-transaction state (SQLSTATE 25P02) — the subsequent `updateOrCreate()` assertion (specified by the plan's `<behavior>` test 1) failed with "current transaction is aborted, commands ignored until end of transaction block" because no further queries could execute on the aborted outer transaction.
- **Fix:** Wrapped the failing INSERT in `DB::transaction(function () use ($match, $player): void { ... });` so the rollback pins to the inner savepoint, leaving the outer `RefreshDatabase` transaction healthy for the follow-on `updateOrCreate()` + `count()` assertions.
- **Files modified:** `apps/web/tests/Unit/Phase8/MatchPlayerStatModelTest.php`.
- **Verification:** All 3 MatchPlayerStatModelTest cases pass; the combined "rejects duplicate THEN is idempotent under updateOrCreate" assertion path executes end-to-end.
- **Committed in:** `f358002` (Task 2 GREEN commit).
- **Why not Rule 4:** This is a test-side savepoint idiom, not an application-side architectural change. Same Postgres failed-transaction characteristic that any Phase 4/8 UNIQUE-probe must handle when followed by additional queries within the same outer RefreshDatabase transaction. The plan's `<behavior>` correctly describes the test intent (probe-then-idempotency); only the mechanical SQL transaction discipline needed adjustment.

### Auth Gates

None — model-layer plan, no external service authentication required.

### Architectural Changes (Rule 4 — required user decision)

None — all three deviations are Rule 1 test/model-side bug fixes within the plan's stated scope.

---

**Total deviations:** 3 auto-fixed (all Rule 1 — bug).
**Impact on plan:** All 11 test cases turn GREEN; all `must_haves.truths` + `must_haves.artifacts` met. The Rule 1 fixes are paper-cuts that don't change the plan's outcomes — they smooth over the Eloquent/Postgres impedance mismatch the plan didn't explicitly call out (`$timestamps` model annotation, Unit-test RefreshDatabase opt-in, savepoint discipline for combined UNIQUE-then-queries probes).

## Issues Encountered

- **`make` not on PATH (same as plans 08-02, 08-03).** CLAUDE.md §1 documents Makefile aliases as the canonical container surface, but `make` itself wasn't installed in this session's host. Resolved by invoking the underlying `docker compose exec web ...` commands directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Pest/Pint/PHPStan ran inside the web container; nothing on host PHP).

## User Setup Required

None — purely model-layer + test-layer changes. No new env vars, no Discord/RCON credential setup, no service rebuild.

## Next Phase Readiness

- **Plan 08-05 (HMAC signature middleware) is unblocked.** Phase 8 schema (08-02) + factory chain (Wave 0 stubs upgraded to real) are now all in place — middleware tests can mint MatchEvent rows via factory state methods + assert `crcon_stream_id` re-use is rejected at the DB tier.
- **Plan 08-07 (MatchEventIngestService) is unblocked.** The composite UNIQUE `(match_id, crcon_stream_id)` is verified at the DB level and reachable from the ingest service via `try { MatchEvent::create(...); } catch (UniqueConstraintViolationException $e) { /* no-op replay */ }`. The `manual_error` factory state demonstrates the NULL-stream-id pattern for synthetic events.
- **Plan 08-08 (MatchPlayerStatAggregator) is unblocked.** `MatchEvent::query()->ofType('player_kill')->since($matchStart)->get()` is the canonical reading interface; `MatchPlayerStat::updateOrCreate(['match_id'=>..., 'player_id'=>...], $stats)` is the canonical writing interface. The `(match_id, player_id)` UNIQUE means re-aggregating on retry is idempotent.
- **Plan 08-12 (Discord bot embed) is unblocked.** `MatchPlayerStat::kdr()` is the MVP picker's tie-breaker; division-by-zero handling is already verified.
- **No blockers.** Phase 1-7 + Phase 8 Wave 0/1/2 baseline preserved: 1054 PASS, 7 FAIL (all 7 = remaining Wave 0 RED stubs for plans 08-05..08-13). Net change from plan 08-03 baseline: **+11 PASS** (4 idempotency + 4 MatchEventModel + 3 MatchPlayerStatModel), **−1 FAIL** (MatchEventIdempotencyTest stub turned GREEN). Exactly the plan's scope.

## Self-Check: PASSED

Verified before finalising:

**Files created (4):**
- `apps/web/app/Models/MatchEvent.php` ✓
- `apps/web/app/Models/MatchPlayerStat.php` ✓
- `apps/web/tests/Unit/Phase8/MatchEventModelTest.php` ✓
- `apps/web/tests/Unit/Phase8/MatchPlayerStatModelTest.php` ✓

**Files modified (3):**
- `apps/web/database/factories/MatchEventFactory.php` (Wave 0 → real) ✓
- `apps/web/database/factories/MatchPlayerStatFactory.php` (Wave 0 → real) ✓
- `apps/web/tests/Feature/Phase8/MatchEventIdempotencyTest.php` (RED stub → 4 GREEN cases) ✓

**Commits (3) — all reachable via `git log --oneline -5`:**
- `c350813` test(08-04): RED tests for MatchEvent idempotency + MatchEvent/MatchPlayerStat models ✓
- `77bc435` feat(08-04): MatchEvent + MatchPlayerStat models + real factories ✓
- `f358002` test(08-04): GREEN MatchEvent + MatchPlayerStat unit tests + idempotency feature test ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter='MatchEventIdempotencyTest|MatchEventModelTest|MatchPlayerStatModelTest'` → **11 PASS** (27 assertions) ✓
- Full Phase 8 filter `pest --filter='Phase8'` → **17 PASS, 7 FAIL** (all 7 = Wave 0 RED stubs for plans 08-05..08-13) ✓
- Full project regression `pest` → **1054 PASS, 7 FAIL** (same 7 stubs) ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` (full project) → **535 files PASS** ✓

**Behavioural probes:**
- `MatchEvent::factory()->kill('111','222')->raw()['event_type']` returns `'player_kill'` ✓
- `MatchEvent::factory()->manualError()->raw()['crcon_stream_id']` returns `null` ✓
- `(match_id, crcon_stream_id)` UNIQUE rejects duplicate; allows same stream_id on different match ✓
- NULL `crcon_stream_id` rows can stack under the same match (Postgres NULL ≠ NULL in UNIQUE) ✓
- `(match_id, player_id)` UNIQUE rejects duplicate; `updateOrCreate` is idempotent ✓
- Negative kills rejected via `match_player_stats_nonneg_check` ✓
- `event_type` CHECK rejects values outside the 10-value enum ✓
- `kdr()` returns `2.0` for kills=20/deaths=10, `5` (int) for kills=5/deaths=0 ✓
- `payload` jsonb roundtrips ✓

**TDD Gate Compliance:** RED commit (`c350813`) precedes GREEN commits (`77bc435`, `f358002`); 9 of 11 RED tests verified failing with `Class not found` before GREEN implementation (2 of 11 passed RED because they probe DB tier directly via `DB::table()->insert()` without needing the Eloquent model — this is per-spec for those two CHECK probes); all 11 verified passing after each GREEN step.

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
