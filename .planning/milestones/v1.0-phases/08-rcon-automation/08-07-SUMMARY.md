---
phase: 08-rcon-automation
plan: 07
subsystem: rcon-event-ingest-service-and-close-match-job-placeholder
tags: [rcon, ingest, idempotency, unique-violation, savepoint, postgres, queue, horizon, bus-dispatch, defence-in-depth, normaliser, tdd, wave-5]

# Dependency graph
requires:
  - phase: 08-04
    provides: MatchEvent model + composite UNIQUE (match_id, crcon_stream_id) on match_events table (the DB-level invariant that the ingest service relies on for replay absorption)
  - phase: 08-06
    provides: "App\\Http\\Controllers\\Internal\\MatchEventsController Wave-4 shim labelled TODO(plan 08-07) for ingest service injection; App\\Http\\Requests\\Internal\\StoreMatchEventsRequest array-shape validator; App\\Data\\Internal\\MatchEventInputData wire DTO; Tests\\Support\\SignsRconRequests trait for HMAC test harness"
provides:
  - "App\\Services\\Rcon\\MatchEventNormaliser — defence-in-depth payload-shape gate per event_type; throws InvalidArgumentException on malformed worker payload; permissive for game_start/round_start/manual_error (generic-game model leeway per D-007); strict for player_kill / player_team_kill / player_connect / player_disconnect / team_switch / round_end / match_end"
  - "App\\Services\\Rcon\\MatchEventIngestService — final, container-resolvable, constructor-injects MatchEventNormaliser; ingest(GameMatch $match, array $events) returns {batch_id, accepted_count, skipped_count}; per-event SAVEPOINT-scoped MatchEvent::create() so UNIQUE absorb scopes to the offending event (Postgres 25P02 trap defused); dispatches CloseMatchJob once per ingest when batch contains match_end"
  - "App\\Jobs\\Rcon\\CloseMatchJob — Wave 5 placeholder; ShouldQueue + Dispatchable + Queueable + InteractsWithQueue + SerializesModels traits; readonly string \$matchId constructor (NOT GameMatch instance — primitive ID avoids 'queue payload outlives row' hazard); empty handle() body — plan 08-08 fills with MatchResult upsert + manual-override gate"
  - "App\\Http\\Controllers\\Internal\\MatchEventsController — refactored: shim removed, now method-injects MatchEventIngestService; response shape gains skipped_count (additive — InternalApiRoutesPresentTest case 6 stays GREEN because toHaveKeys is non-strict)"
affects:
  - 08-08-PLAN.md (CloseMatchJob::handle body — must read GameMatch by \$matchId, invoke MatchPlayerStatAggregator, upsert MatchResult with source='rcon', honour manual-override lock; RconMatchResultIngestionTest still RED until 08-08 lands)
  - 08-10-PLAN.md (worker outbound normaliser TS mirror — must emit canonical payload shapes per the 11-case web contract this plan locked; any drift surfaces as InvalidArgumentException → 500 → operator alert)
  - 08-12-PLAN.md (E2E scrim happy path — exercises the full ingest pathway end-to-end; uses MatchEventIngestService through the HMAC-protected /events endpoint)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Postgres SAVEPOINT idempotency pattern via `DB::transaction(fn () => Model::create(...))` nested inside the wrapping RefreshDatabase transaction. Without the savepoint, a UNIQUE violation raises `SQLSTATE 25P02 — current transaction is aborted` and every subsequent statement in the batch fails until COMMIT/ROLLBACK closes the outer transaction. Laravel's DB::transaction detects nesting and issues `SAVEPOINT trans2 / RELEASE / ROLLBACK TO` pairs instead of `BEGIN / COMMIT`, scoping the abort to the per-event critical section. The catch block sits OUTSIDE the DB::transaction closure — the savepoint has already been rolled back by the time the catch runs, so the next iteration's INSERT starts fresh. This is the canonical Postgres idiom for `INSERT ... ON CONFLICT DO NOTHING`-style ingest when you also need to count skipped rows (which raw `ON CONFLICT` does not surface). Source: Postgres docs § 13.4 Subtransactions; Laravel docs § Database Transactions."
    - "Bus::fake() + ::dispatch(new Job(...)) — the canonical Laravel queue test idiom for asserting job dispatch WITHOUT actually queuing the work. Bus::fake() is set in beforeEach; the test then asserts via `Bus::assertDispatched(JobClass::class, fn ($job) => $job->prop === expected)` for positive cases and `Bus::assertNotDispatched(JobClass::class)` for negative cases. The closure-based assert lets us pin payload constructor args (here: `\$job->matchId === \$match->id`) without re-running the job. Reused from plan 05-06 SyncDiscordRolesJob test patterns; the trait stack on CloseMatchJob (Dispatchable + Queueable + InteractsWithQueue + SerializesModels) matches Phase 5's job idiom exactly so Horizon retry behaviour will be uniform across the two job types in plan 08-08."
    - "`final class` services + constructor injection — D-04-09-D idiom for non-Mockery-testable service surfaces. Final prevents subclass-based test doubles (the Mockery partial-mock anti-pattern that breaks behavioural coverage), and the constructor takes its collaborators (here: MatchEventNormaliser) so the container handles wiring. Tests pass `new MatchEventIngestService(new MatchEventNormaliser)` directly — no Mockery needed, no `\$this->mock()` calls. This also gives PHPStan-clean construction without requiring `@property` annotations on the test class (a hazard surfaced during Task 1's first pass on the contract test). Matches Phase 4 / Phase 5 service idioms (MatchSignupService, MatchResultService, BracketMatchMaterialiserService all `final` for the same reason)."
    - "Permissive-versus-strict normaliser per event_type — D-007 generic-game-model leeway. game_start / round_start / manual_error payload shapes vary by game (HLL ships {map, mode} and {round_number}; Phase 3+ games may add fields) and by operator (manual_error gets arbitrary diagnostic keys). Strict validation here would couple the normaliser to HLL specifics, defeating the point of the generic Game/Role/MatchType tables. Strict validation IS applied to the remaining 7 types (kill, team_kill, connect, disconnect, team_switch, round_end, match_end) because their payload shapes are game-agnostic (steam_id_64 is universal across HLL, future games using Steam-backed identity, etc.) and downstream aggregators (MatchPlayerStatAggregator, plan 08-08) depend on those keys."
    - "Primitive-ID job constructors (`readonly string \$matchId` instead of `GameMatch \$match`) — canonical Laravel queue idiom for jobs that may be deferred to Redis. SerializesModels would otherwise serialise the model via `__sleep` and re-hydrate via `__wakeup` on dequeue, which throws ModelNotFoundException if the row was deleted between dispatch and handle. Primitive IDs let `handle()` re-query at execution time and gracefully exit on missing row. Identical reasoning + same readonly-property syntax as Phase 5's SyncDiscordRolesJob (membershipId / discord_user_id / discord_role_id all primitives). The `readonly` keyword enforces immutability per PHP 8.2+ semantics — the job can't accidentally mutate its constructor args during handle()."

key-files:
  created:
    - apps/web/app/Services/Rcon/MatchEventNormaliser.php
    - apps/web/app/Services/Rcon/MatchEventIngestService.php
    - apps/web/app/Jobs/Rcon/CloseMatchJob.php
    - apps/web/tests/Feature/Phase8/MatchEventIngestServiceTest.php
  modified:
    - apps/web/app/Http/Controllers/Internal/MatchEventsController.php
    - apps/web/tests/Feature/Phase8/MatchEventNormaliserContractTest.php

key-decisions:
  - "Per-event INSERT wrapped in `DB::transaction(fn () => MatchEvent::create(...))` rather than a single batch INSERT or a single outer transaction. Reason: composite UNIQUE absorb requires the failing INSERT's effects to roll back independently of its peers. A single outer transaction would either (a) abort the whole batch on the first duplicate (defeating the absorb), or (b) require the catch to live BEFORE the COMMIT which is impossible in PHP-style try/catch without an explicit SAVEPOINT. Laravel's nested-transaction implementation already emits SAVEPOINT/RELEASE/ROLLBACK TO inside an outer transaction (and BEGIN/COMMIT when standalone), so the same code works in production (no outer transaction → BEGIN; one savepoint per event) AND in `RefreshDatabase` tests (outer transaction → SAVEPOINT per event). The plan body's pseudo-code used a bare try/catch without DB::transaction; that pseudo-code would have failed on Postgres in test mode with SQLSTATE 25P02 (we verified empirically during Task 2). Documented in the service's class docblock + this SUMMARY's tech-stack patterns."
  - "Response shape gains `skipped_count` (was `{batch_id, accepted_count}` in the plan 08-06 shim; now `{batch_id, accepted_count, skipped_count}`). Justification: the must_haves.truths #1 truth explicitly mentions accepted/skipped counts, AND the worker needs to log skipped_count to detect when its retry layer is doing useful work versus spinning. The change is ADDITIVE — InternalApiRoutesPresentTest case 6 uses `toHaveKeys(['batch_id', 'accepted_count'])` which passes for the new shape (the matcher tolerates extra keys). The TS DTO consumed by the worker (App.Data.Internal.MatchEventInputData) is INPUT-only; we don't ship an `MatchEventIngestResponseData` DTO in this plan because the worker's response handler treats the body opaquely and the wire shape lives in the controller docblock + 6 ingest test cases. If a future plan needs a TS-typed response we can extract it then."
  - "Three permissive event types (game_start, round_start, manual_error) — see tech-stack patterns. Adding strict validation here would prematurely couple the generic-game model (D-007) to HLL-specific payload shapes. The downstream MatchPlayerStatAggregator (plan 08-08) only reads kill / team_kill / connect / disconnect payloads which ARE strict-validated. The result-shape events (round_end, match_end) ARE strict-validated because the CloseMatchJob (plan 08-08) will read winning_team / allies_score / axis_score off them to populate MatchResult."
  - "`InvalidArgumentException` bubbles up to the controller (NOT caught + rewritten to a 422). Justification: a payload shape miss is a WORKER BUG (the worker should have emitted canonical shapes per the TS-mirror normaliser in plan 08-10), not a client validation error. The 500 status code triggers operator alert via the standard Laravel exception handler + Sentry path. Catching here and returning 422 would mask the bug. Threat-register correspondence: T-08-07-01 disposition `mitigate` — two-layer validation surfaces the miss as an unhandled exception. Documented in the service's class docblock and the controller's docblock."
  - "CloseMatchJob constructor takes `readonly string \$matchId` (NOT a GameMatch instance) — see tech-stack patterns. Reused exactly from Phase 5's SyncDiscordRolesJob — same `readonly` keyword, same primitive-ID rationale. The plan body's `<interfaces>` block used `public string \$matchId` (non-readonly); we tightened to `readonly` because PHP 8.4 supports it and immutability of job args is a correctness guarantee (handle() can re-query the row but can't mutate the dispatch payload)."
  - "11-case normaliser contract test uses `(new MatchEventNormaliser)->validate(...)` inline rather than a `\$this->normaliser` Pest closure-state property (the plan's pseudo-code suggested via beforeEach). Reason: PHPStan can't infer types for properties hung off Pest's PendingCalls\\TestCall surface; the first pass tripped 14 PHPStan errors of the form 'Access to an undefined property TestCall::\$normaliser'. The class is stateless so per-test construction is free. The 6-case ingest test uses the same pattern for the same reason."
  - "`function kEvent(...)` and `function neventBase(...)` helper functions live at file scope (not closures, not Pest helpers). Reason: Pest's `beforeAll` / `beforeEach` execute in TestCall scope where `\$this` would type-infer to TestCall, not the test class — function-scope helpers stay PHPStan-clean. Each helper has a fully-typed signature + a PHPDoc array shape so the call sites get inferred shapes for the returned event envelopes. The helpers are file-scoped (one per test file) rather than promoted to Tests\\Support\\ because they're test-data builders, not behaviour the production code mirrors — keeping them local prevents `tests/Support` from becoming a junk drawer of one-off data shapes."

# Metrics
duration: 9min
completed: 2026-05-14
---

# Phase 8 Plan 7: Wave 5 — MatchEventIngestService + MatchEventNormaliser + CloseMatchJob placeholder + controller refactor Summary

**Replaced the plan 08-06 controller shim with a real `MatchEventIngestService` that persists `match_events` rows idempotently via per-event Postgres SAVEPOINTs (composite UNIQUE absorbs worker replays without poisoning the batch), and authored the web-side `MatchEventNormaliser` as a defence-in-depth payload-shape gate per `event_type`. Shipped `CloseMatchJob` as a Wave-5 placeholder (empty `handle()` — plan 08-08 fills with `MatchResult` upsert) so the ingest service's `Bus::dispatch(new CloseMatchJob($match->id))` on `match_end` resolves a real class. Project regression 1070 → 1087 PASS (+17 new GREEN: 11 normaliser + 6 ingest), 6 → 5 FAIL (closed 1 Wave-0 RED stub; remaining 5 are scheduled for plans 08-08..08-13).**

## Performance

- **Duration:** 9 min 8 s
- **Started:** 2026-05-14T~12:00:00Z (Task 1 commit precursor)
- **Completed:** 2026-05-14T~12:09:08Z (Task 2 commit + verifications)
- **Tasks:** 2 / 2
- **Files created:** 4 (1 service + 1 placeholder job + 1 ingest test + 1 normaliser service; note the test for normaliser was a REPLACE of the Wave-0 RED stub rather than a strict creation, so it appears under files-modified)
- **Files modified:** 2 (1 controller refactor + 1 test stub → 11-case GREEN suite replacement)
- **Commits:** 2 (Task 1 `6f95989`; Task 2 `c6e98be`)

## Accomplishments

### TDD Gate Sequence

This plan has 2 `tdd="true"` tasks. Both followed the GREEN-after-RED pattern because the Wave-0 RED stubs from plan 08-01 already existed in `tests/Feature/Phase8/`:

1. **GREEN Task 1** (commit `6f95989`): authored `App\Services\Rcon\MatchEventNormaliser` + replaced the 1-line Wave-0 RED stub at `tests/Feature/Phase8/MatchEventNormaliserContractTest.php` with the 11-case behavioural suite. Pre-commit verifies: 11 PASS, PHPStan L8 0 errors, Pint clean.
2. **GREEN Task 2** (commit `c6e98be`): authored `App\Services\Rcon\MatchEventIngestService` + `App\Jobs\Rcon\CloseMatchJob` + refactored `App\Http\Controllers\Internal\MatchEventsController` + authored `tests/Feature/Phase8/MatchEventIngestServiceTest` (6 cases). Pre-commit verifies: 6 ingest cases PASS, 8 plan-08-06 routes cases STILL GREEN, 4 plan-08-04 idempotency cases STILL GREEN, 11 normaliser cases STILL GREEN (29 total under filter), PHPStan L8 0 errors, Pint clean.

Both tasks satisfy the implicit RED gate via the pre-existing Wave-0 stubs (`expect(true)->toBeFalse()`) in plan 08-01's seeding; this plan's job was to bring the RED stubs GREEN.

### Application code (3 created, 1 modified)

1. **`App\Services\Rcon\MatchEventNormaliser`** — `final` defence-in-depth payload-shape validator. `validate(array $event): MatchEventInputData` runs a `match($type)` over the 10 canonical event_types, dispatching to private per-shape assertion methods. Throws `InvalidArgumentException` with descriptive messages on payload misses. Hydrates the typed DTO via `MatchEventInputData::from($event)` on success. Permissive for `game_start` / `round_start` / `manual_error` (D-007 generic-game leeway).

2. **`App\Services\Rcon\MatchEventIngestService`** — `final`, container-resolvable, constructor-injects `MatchEventNormaliser`. `ingest(GameMatch $match, array $events): array` returns `{batch_id, accepted_count, skipped_count}`. Per-event SAVEPOINT-scoped `MatchEvent::create()` via `DB::transaction(fn () => ...)`. Catches `UniqueConstraintViolationException` per event → increments `skipped_count`. On `match_end` arrival, dispatches `CloseMatchJob` via `Bus::dispatch(new CloseMatchJob($match->id))` once per ingest call. `InvalidArgumentException` from the normaliser bubbles up (NOT caught — worker-bug surface trigger per T-08-07-01).

3. **`App\Jobs\Rcon\CloseMatchJob`** — `final` placeholder. `ShouldQueue` + standard 4 traits (`Dispatchable` + `InteractsWithQueue` + `Queueable` + `SerializesModels`). Constructor: `readonly string $matchId`. `handle()` body intentionally empty — plan 08-08 fills with `MatchResult` upsert + manual-override gate. The class exists so `Bus::fake()` in `MatchEventIngestServiceTest` can `assertDispatched(CloseMatchJob::class, fn ($job) => $job->matchId === $match->id)`.

4. **`App\Http\Controllers\Internal\MatchEventsController`** (modified) — Wave-4 shim removed. Now method-injects `MatchEventIngestService` via Laravel's auto-resolution: `public function store(StoreMatchEventsRequest $request, GameMatch $match, MatchEventIngestService $service): JsonResponse`. Returns `response()->json($service->ingest(...), 202)`. The `TODO(plan 08-07)` comment from the shim is deleted; the docblock now describes the real wire contract including the new `skipped_count` field and the 500-on-`InvalidArgumentException` surface.

### Tests created (1 file, 6 cases)

5. **`tests/Feature/Phase8/MatchEventIngestServiceTest`** — 6 cases all GREEN:
   1. **persists 3 fresh events with accepted_count=3 and skipped_count=0** — verifies the happy path: `match_events` table gains 3 rows, response counts match.
   2. **absorbs a fully-duplicated second batch with skipped_count=3** — first ingest 3/0; second ingest of the same events 0/3; row count stays at 3 (UNIQUE absorb on each).
   3. **handles a mixed-collision batch (3 fresh + 2 duplicates) with accepted=3 skipped=2** — seeds 2 events, then ingests 5 events of which 2 collide; verifies the savepoint isolation works mid-batch.
   4. **dispatches CloseMatchJob when a batch contains a match_end event** — `Bus::fake()` + `Bus::assertDispatched(CloseMatchJob::class, fn ($job) => $job->matchId === $match->id)`.
   5. **does NOT dispatch CloseMatchJob when the batch has no match_end event** — `Bus::assertNotDispatched(CloseMatchJob::class)`.
   6. **bubbles InvalidArgumentException on malformed payload and persists events before the bad index** — verifies T-08-07-02 partial-commit semantics: good events before the bad index ARE persisted; the bad event throws; events after the bad index never run (loop aborted). Worker resend can resume via UNIQUE absorb.

### Tests modified (1 file: RED→GREEN, 1→11 cases)

6. **`tests/Feature/Phase8/MatchEventNormaliserContractTest`** — Wave-0 RED stub (`expect(true)->toBeFalse()`) replaced with 11 GREEN cases covering: game_start permissive, round_start permissive, player_kill happy, player_kill missing weapon (throws), player_team_kill happy, player_connect happy, player_disconnect missing steam_id_64 (throws), team_switch happy, round_end happy, match_end happy, unknown event_type (throws). Matches the 11-case behaviour list from plan 08-07 task 1.

## Task Commits

1. **GREEN Task 1 — MatchEventNormaliser + 11-case contract test** — `6f95989` (feat)
2. **GREEN Task 2 — MatchEventIngestService + CloseMatchJob placeholder + controller refactor + 6-case ingest test** — `c6e98be` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (3)
- `apps/web/app/Services/Rcon/MatchEventNormaliser.php`
- `apps/web/app/Services/Rcon/MatchEventIngestService.php`
- `apps/web/app/Jobs/Rcon/CloseMatchJob.php`

### Tests (1)
- `apps/web/tests/Feature/Phase8/MatchEventIngestServiceTest.php`

## Files Modified

### Application code (1)
- `apps/web/app/Http/Controllers/Internal/MatchEventsController.php` — shim removed; injects `MatchEventIngestService`; response shape gains `skipped_count`

### Tests (1)
- `apps/web/tests/Feature/Phase8/MatchEventNormaliserContractTest.php` — Wave-0 RED stub replaced with 11-case GREEN behavioural suite

## Decisions Made

See `key-decisions` in the frontmatter above. Highlights:

- **Per-event `DB::transaction(fn () => MatchEvent::create(...))`** — the savepoint isolation defuses Postgres SQLSTATE 25P02 ("current transaction is aborted") when running inside RefreshDatabase. The plan body's pseudo-code used a bare try/catch which would have broken in tests; auto-fixed (Rule 1 — bug in the plan-specified flow).
- **Response shape adds `skipped_count`** — additive; `toHaveKeys` non-strict; matches must_haves.truths #1.
- **`InvalidArgumentException` bubbles to 500** — worker bug, not client error. T-08-07-01 disposition.
- **`CloseMatchJob` constructor takes `readonly string $matchId`** — canonical primitive-ID job idiom (matches Phase 5 SyncDiscordRolesJob).
- **Three permissive event types** — D-007 generic-game leeway; the 7 strict-validated types are game-agnostic and aggregator-relevant.
- **Inline `(new MatchEventNormaliser)->validate(...)`** in tests — avoids PHPStan-unfriendly Pest closure-state properties.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Per-event INSERT must wrap in `DB::transaction()` for Postgres SAVEPOINT semantics**

- **Found during:** Task 2 first test-run iteration
- **Issue:** The plan's pseudo-code (`<interfaces>` block) wraps each `MatchEvent::create()` in a bare `try { ... } catch (UniqueConstraintViolationException) { ... }`. On Postgres inside the RefreshDatabase wrapping transaction, the first UNIQUE collision raises SQLSTATE 25P02 ("current transaction is aborted, commands ignored until end of transaction block") and every subsequent INSERT in the loop fails. The catch absorbed the UNIQUE violation but the next iteration's INSERT immediately threw `QueryException` with state 25P02, breaking case 2 (full-replay absorb) and case 3 (mixed-collision batch) of the ingest test.
- **Fix:** Wrapped the per-event INSERT in `DB::transaction(function () use ($match, $dto): void { MatchEvent::create([...]); })`. Laravel's nested-transaction implementation issues `SAVEPOINT trans2 / RELEASE / ROLLBACK TO` pairs inside the wrapping RefreshDatabase transaction (and standalone `BEGIN/COMMIT` outside tests), so the abort scope shrinks to the per-event critical section. Catch sits OUTSIDE the closure; by the time the catch runs, the savepoint has been rolled back and the next loop iteration's INSERT starts fresh.
- **Files modified:** `apps/web/app/Services/Rcon/MatchEventIngestService.php` (added `use Illuminate\Support\Facades\DB`; wrapped INSERT in `DB::transaction`; updated class docblock with the Postgres SAVEPOINT explanation).
- **Commit:** Folded into Task 2 commit `c6e98be` (the fix happened in-task; no separate commit).
- **Plan correctness:** The plan's `<interfaces>` pseudo-code is wrong on Postgres in test mode. This auto-fix preserves the must_haves.truths #1 behaviour (idempotent batch ingest; one duplicate doesn't poison nine real events) under the project's actual DB stack (D-016: Postgres 16). Documented exhaustively in the service's class docblock for future maintainers.

### Auth Gates

None — implementation-only plan, no external service authentication required.

### Architectural Changes (Rule 4 — required user decision)

None.

---

**Total deviations:** 1 (one Rule 1 auto-fix for a plan-pseudo-code Postgres incompatibility).
**Impact on plan:** None — the fix is internal-only (service implementation detail) and preserves the must_haves contract bit-for-bit. The response shape, idempotency guarantees, dispatch behaviour, and per-event partial-commit semantics all match the plan exactly.

## Issues Encountered

- **`make` not on PATH (same as plans 08-02..08-06).** CLAUDE.md §1 documents Makefile aliases as the canonical container surface, but `make` itself wasn't installed in this session's host. Resolved by invoking the underlying `docker compose exec -T web ./vendor/bin/…` commands directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Pest/Pint/PHPStan ran inside the web container).
- **Postgres SQLSTATE 25P02 trap in the plan's pseudo-code** — surfaced in test iteration 1 of Task 2; auto-fixed via DB::transaction nesting (see Deviations above).
- **PHPStan property-not-found on Pest TestCall surface** — surfaced in test iteration 1 of Task 1; auto-fixed by inlining `new MatchEventNormaliser` per test instead of hanging a `$this->normaliser` property off Pest's beforeEach (see key-decisions above).

## User Setup Required

None — internal service + placeholder job, no env vars or external services introduced.

## Next Phase Readiness

- **Plan 08-08 (CloseMatchJob handle body + MatchResult upsert + ManualOverrideWinsTest GREEN + RconMatchResultIngestionTest GREEN) is unblocked.** The `CloseMatchJob` placeholder is wired to ingest dispatch; plan 08-08 fills the empty `handle()` with:
  1. `$match = GameMatch::find($this->matchId); if (!$match) return;` (canonical missing-row guard).
  2. Invoke `MatchPlayerStatAggregator` over `$match->events()->orderBy('occurred_at')->get()` to derive per-player stats.
  3. Upsert `MatchResult` with `source='rcon'`, honouring the manual-override lock (if a `MatchResult` row already exists with `source='manual'` AND `locked=true`, log audit + skip — per `audit.rcon_arrived_locked` i18n key from plan 08-04).
  4. Dispatch `MatchPlayerStat::insert(...)` rows from the aggregator output.

  The 6 ingest test cases already pin the dispatch contract — plan 08-08 just needs to add `Bus::assertDispatched`-style coverage on the job's behaviour AND deploy a real queue-worker happy path via `Queue::fake()` toggle.

- **Plan 08-10 (apps/rcon-worker outbound normaliser, TS side) is unblocked.** The 11-case web-side normaliser contract IS the canonical wire shape the worker must emit. The plan 08-10 author should copy the per-event-type assertion list verbatim into `apps/rcon-worker/src/crcon/CrconEventNormaliser.ts` so any drift surfaces at compile time on both sides.

- **Plan 08-12 (E2E scrim happy path) is unblocked at the ingest seam.** The full HMAC-protected pathway from plan 08-06 + the persistence layer from plan 08-07 is in place; plan 08-12's `ScrimE2EHappyPathTest` (still RED — `expect(true)->toBeFalse()`) will replay a recorded CRCON event stream through `signedJsonPost` against `/api/internal/match/{match}/events` and assert the end-state `MatchResult` matches the recorded match outcome.

- **No blockers.** Phase 8 baseline: **plan 08-06 → 1070 PASS / 6 FAIL; plan 08-07 → 1087 PASS / 5 FAIL.** Net change: **+17 PASS** (11 normaliser GREEN + 6 ingest GREEN), **-1 FAIL** (the MatchEventNormaliserContractTest RED stub closed). The 5 remaining FAILs are all `expect(true)->toBeFalse()` Wave-0 RED stubs:
  - `ManualOverrideWinsTest` → plan 08-08
  - `MatchPlayerStatAggregatorTest` → plan 08-08
  - `RconMatchResultIngestionTest` → plan 08-08
  - `RconUnreachableFlagsManualTest` → plan 08-09
  - `ScrimE2EHappyPathTest` → plan 08-12

## Self-Check: PASSED

Verified before finalising:

**Files created (4) — all exist:**
- `apps/web/app/Services/Rcon/MatchEventNormaliser.php` ✓
- `apps/web/app/Services/Rcon/MatchEventIngestService.php` ✓
- `apps/web/app/Jobs/Rcon/CloseMatchJob.php` ✓
- `apps/web/tests/Feature/Phase8/MatchEventIngestServiceTest.php` ✓

**Files modified (2) — all staged in commits:**
- `apps/web/app/Http/Controllers/Internal/MatchEventsController.php` (shim removed, service injected) ✓
- `apps/web/tests/Feature/Phase8/MatchEventNormaliserContractTest.php` (RED→GREEN 11-case suite) ✓

**Commits (2) — reachable via `git log --oneline -3`:**
- `6f95989` feat(08-07): MatchEventNormaliser + GREEN 11-case contract test ✓
- `c6e98be` feat(08-07): MatchEventIngestService + CloseMatchJob placeholder + controller refactor ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter='MatchEventIngestServiceTest|InternalApiRoutesPresentTest|MatchEventIdempotencyTest|MatchEventNormaliserContractTest'` → **29 PASS, 77 assertions** ✓
- `pest tests/Feature/Phase8` → **43 PASS, 5 FAIL** (5 remaining = Wave-0 RED stubs scheduled for 08-08/08-09/08-12) ✓
- Full project `pest` → **1087 PASS, 5 FAIL** (1070 → 1087 = +17 PASS; 6 → 5 FAIL = -1 RED closed; **0 regressions**) ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` (touched files) → **PASS** ✓

**TDD Gate Compliance:** Both `tdd="true"` tasks landed GREEN behavioural suites that replaced pre-existing Wave-0 RED stubs from plan 08-01 (RED gate satisfied at the plan-01 seeding boundary; this plan delivered the GREEN gate). Plan-level type is `execute` rather than `tdd`, so the plan-wide RED-then-GREEN-then-REFACTOR commit sequence isn't required; per-task TDD discipline IS satisfied for both tasks.

**Plan correctness verifications (per the plan's <verification> block):**
- MatchEventNormaliserContractTest: 11 PASS ✓
- MatchEventIngestServiceTest: 6 PASS ✓
- MatchEventIdempotencyTest: 4 PASS (still GREEN from plan 08-04) ✓
- InternalApiRoutesPresentTest: 8 PASS (still GREEN from plan 08-06 after controller refactor) ✓
- VerifyRconSignatureTest: GREEN (still GREEN from plan 08-05; not re-run in filter but covered by full-Phase8 43-PASS) ✓
- RconMatchResultIngestionTest: still RED (correct — plan 08-08 owns) ✓
- ScrimE2EHappyPathTest: still RED (correct — plan 08-12 owns) ✓

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
