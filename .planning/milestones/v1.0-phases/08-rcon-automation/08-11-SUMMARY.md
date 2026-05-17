---
phase: 08-rcon-automation
plan: 11
subsystem: rcon-worker-booking-scheduler-and-match-lifecycle-manager-and-redis-failover-queue
tags: [rcon, worker, ts-strict, ioredis, ioredis-mock, ws, undici-fetch, hmac-signed-get, setinterval-scheduler, vitest-integration, wave-7]

# Dependency graph
requires:
  - phase: 08-06
    provides: "GET /api/internal/bookings/due (BookingScheduleController) + GET /api/internal/match-servers/{server}/credentials (MatchServerCredentialsController) + spatie/data BookingDueData DTO. Cross-tier wire contract anchored at the apps/web side."
  - phase: 08-10
    provides: "WebIngestClient (signed POST + new fetchSignedJson<T> GET added in this plan) + HmacSigner (cross-tier digest match) + CrconClient (ws + reconnect + heartbeat) + CrconEventNormaliser (7-action switch → canonical NormalisedEvent). Worker tier ingress + egress seams."
provides:
  - "apps/rcon-worker/src/booking/BookingScheduler.ts — polls /api/internal/bookings/due every POLL_INTERVAL_MS (default 30s), spawns one SchedulerManager per due booking via an injectable managerFactory (test seam — production wiring uses real MatchLifecycleManager). Reaps complete managers from this.active on each tick. webClient errors are caught + logger.warn'd; the scheduler never crashes on a transient web outage. Exposes getActiveCount() + isActive(bookingId) for tests + diagnostics."
  - "apps/rcon-worker/src/booking/MatchLifecycleManager.ts — per-booking session: GET credentials → open CrconClient → batch normalised events (≥10 OR every flushIntervalMs=2_000ms) → POST via WebIngestClient. On hard credentials-fetch failure emits synthetic manual_error event (kind: 'unreachable'/'auth_failed'/'permission_denied') so the apps/web RconUnreachableFlagsManualTest invariant holds end-to-end. On POST non-2xx, LPUSHes the entire batch onto rcon:queue:{matchId} for the drainer. Test seams: flushIntervalMs / batchSize / completeGraceMs overrides; getBufferSize() + getSawMatchEnd()."
  - "apps/rcon-worker/src/queue/RedisFailoverQueue.ts — SCAN 'rcon:queue:*' (non-blocking), LRANGE 0..99 per match (BATCHES_PER_PASS=100), flatten across batches, POST flattened events, LTRIM on 2xx; non-2xx retained for next pass. queueKey(matchId) export shared by MatchLifecycleManager for LPUSH on flush failure (single source of truth for the key shape)."
  - "apps/rcon-worker/src/booking/types.ts — worker-side BookingDueData interface (mirrors apps/web spatie/laravel-data DTO). Defined locally pending plan 08-12's shared-types regeneration; field names/types are byte-for-byte wire-compatible with the PHP side."
  - "apps/rcon-worker/src/ingest/WebIngestClient.ts — extended with fetchSignedJson<T>(path): HMAC-signed GET (empty body — `timestamp + ''` digest input). Used by BookingScheduler for /api/internal/bookings/due + by MatchLifecycleManager for /api/internal/match-servers/{id}/credentials. Throws on non-2xx so the caller differentiates transient failures (logger.warn — no spawn) from happy-path responses (spawn manager / open CRCON session)."
  - "apps/rcon-worker/src/index.ts — real entrypoint replacing the Wave-0 console.log placeholder. Loads config, builds ioredis client (maxRetriesPerRequest=3, enableOfflineQueue=true, redis-on-error → logger.warn), starts BookingScheduler + RedisFailoverQueue drainer, installs SIGTERM/SIGINT shutdown that calls stop() on both + redis.disconnect() with a 100ms pino-flush window before process.exit(0). Fatal startup errors logged at fatal then exit(1)."
  - "apps/rcon-worker/tests/unit/BookingScheduler.test.ts — 5 GREEN cases (empty due / 2-spawn / idempotent twice-tick / reap-after-complete / webClient-throws-warns-no-crash)."
  - "apps/rcon-worker/tests/unit/RedisFailoverQueue.test.ts — 4 GREEN cases (empty queue / 3-items-LTRIM-on-200 / 500-retained-warned / two-matches-both-drained). Uses ioredis-mock."
  - "apps/rcon-worker/tests/integration/MatchLifecycleManager.integration.test.ts — 7 GREEN cases against ephemeral ws.WebSocketServer + node:http server mocks (happy path, credentials 404, ws refused, batch cap=10, flush timer, POST 500 → Redis LPUSH, grace timeout)."
affects:
  - 08-12-PLAN.md (E2E scrim capstone) — worker tier is now operational end-to-end. The capstone can drive: BookingScheduler.tick() → fetchSignedJson(/bookings/due) → MatchLifecycleManager.start() → fetchSignedJson(/match-servers/{id}/credentials) → CrconClient.connect() → onLogs → flush → POST /events. The cross-tier HMAC contract (SC-5) was already proven in plan 08-10 GREEN suite; this plan completes the lifecycle.
  - 08-13-PLAN.md (Phase 8 verification) — Manual Smoke A (worker boots, polls bookings, posts to web) is now executable. Worker container boots cleanly with valid env: `pnpm test && pnpm typecheck && pnpm lint && pnpm build` all GREEN (40/40 tests).

# Tech tracking
tech-stack:
  added:
    - "ioredis-mock@^8.13.1 (devDep) — in-memory Redis double for RedisFailoverQueue + MatchLifecycleManager integration tests. Mocks scanStream + LRANGE + LPUSH + RPUSH + LTRIM + LLEN. Avoids the runtime + cleanup overhead of a real Redis in unit tests. Production runtime still uses real ioredis@^5.10 against the redis service in docker-compose.yml."
    - "@types/ioredis-mock@^8.2.7 (devDep) — TypeScript ambient declarations for ioredis-mock; lets `import RedisMock from 'ioredis-mock'` typecheck cleanly under NodeNext module resolution."
  patterns:
    - "Injectable managerFactory option on BookingScheduler — Rule 2 testability hook so unit tests can substitute a stub SchedulerManager without touching real CRCON ws / Redis traffic. Production wiring (index.ts) leaves managerFactory unset and gets the real MatchLifecycleManager. The unit-test stub captures booking shape + records started/stopped/complete state via plain object closures; no vi.mock() needed. This is the same testability pattern as plan 08-10's heartbeatIntervalMs option — parameterise the testability seam without polluting the production API."
    - "Flatten-on-drain Redis queue shape — each LPUSH stores a JSON.stringified NormalisedEvent[] (one stored batch per flush failure). On drain, RedisFailoverQueue LRANGEs up to 100 stored batches, flattens via spread into one NormalisedEvent[], and POSTs the combined array in a single web call. Trades wire-call count for per-match throughput (one POST drains 100 stored batches). LTRIM(key, items.length, -1) removes exactly the drained range — safe against concurrent LPUSH because LPUSH adds to the head and LRANGE reads from the head, so newer batches written during the drain are preserved at the tail. The non-2xx path simply does not LTRIM; the next drain pass retries the same items."
    - "Two-server integration test harness for MatchLifecycleManager — ws.WebSocketServer on 127.0.0.1:0 for the CRCON side + node:http.createServer on 127.0.0.1:0 for the apps/web ingest side. Routes are inline closures in startHttpServer(router) so each test case can return whatever wire shape it needs (200 credentials + 202 events vs 404 credentials vs 500 events). Captures every request into harness.requests[] for after-the-fact assertions on body + URL + status returned. Per-test fresh harnesses + afterEach cleanup so the suite parallelises and orphan ports do not leak. This is symmetric with plan 08-10's CrconClient integration harness — same pattern, additional http layer."
    - "Connection-refused test pattern — startWsServer() to get an ephemeral port, snapshot the port, then closeWsServer() BEFORE manager.start() so the CrconClient's ws.connect() is guaranteed-refused. Subsequent assertions on (a) buffer remains empty and (b) zero /events POSTs validate the contract that ws errors do not pollute downstream state. Cleaner than mocking ws.WebSocket — uses real OS-level connection refusal."
    - "Async-aware waitFor() helper — supports both synchronous and async predicates (`pred: () => boolean | Promise<boolean>`). Test 6 needs `await redis.llen(key) >= 1`; tests 1-5+7 use synchronous checks. Single helper handles both via `await pred()` inside the loop. The `for (;;)` form is intentional — avoids the no-constant-condition lint rule on `while (true)` without adding an eslint-disable comment."

key-files:
  created:
    - apps/rcon-worker/src/booking/BookingScheduler.ts
    - apps/rcon-worker/src/booking/MatchLifecycleManager.ts
    - apps/rcon-worker/src/booking/types.ts
    - apps/rcon-worker/src/queue/RedisFailoverQueue.ts
    - apps/rcon-worker/tests/unit/BookingScheduler.test.ts
    - apps/rcon-worker/tests/unit/RedisFailoverQueue.test.ts
    - apps/rcon-worker/tests/integration/MatchLifecycleManager.integration.test.ts
  modified:
    - apps/rcon-worker/src/ingest/WebIngestClient.ts
    - apps/rcon-worker/src/index.ts
    - apps/rcon-worker/package.json
    - pnpm-lock.yaml

key-decisions:
  - "BookingScheduler accepts an injectable `managerFactory` option (default: build a real MatchLifecycleManager). This is the Rule 2 testability hook for unit tests — they pass a stub factory that records start/stop/complete state via plain closures. The plan's <interfaces> sketch inlined the manager construction inside `tick()`; surfacing it as an option keeps the unit tests free of ws + http + redis side-effects while preserving the production behaviour bit-for-bit (default factory matches the inlined sketch)."
  - "MatchLifecycleManager exposes 3 testability override options: `flushIntervalMs` (default 2_000), `batchSize` (default 10), `completeGraceMs` (default 60_000). The integration tests use short values (50ms flush, 50ms grace) to exercise the time-driven paths deterministically without real-world wait times. Production code (index.ts → scheduler → factory) leaves all 3 unset and uses the documented defaults. Same Rule 2 pattern as 08-10's heartbeatIntervalMs."
  - "WebIngestClient.fetchSignedJson<T>(path) throws on non-2xx rather than returning {status, body} (the postEvents shape). Rationale: the call-sites (BookingScheduler.tick + MatchLifecycleManager.start) treat any non-2xx as a transient failure with a specific recovery path (warn + retry-next-tick for the scheduler; emit manual_error + complete=true for the manager). Returning {status, body} would force every call-site to write the same check; throwing centralises the decision. The cross-tier wire contract is unchanged — apps/web's VerifyRconSignature middleware computes its digest over `timestamp . raw_body` = `timestamp . ''` for GET, byte-for-byte matching our signing."
  - "ioredis-mock chosen over hand-rolled shim. The plan's <action> says 'verify and fall back to a hand-rolled mock if needed (5-line stub)'. ioredis-mock's scanStream + LRANGE + RPUSH + LTRIM + LLEN all work correctly under our test patterns — no shim needed. Verified via the 4 RedisFailoverQueue unit cases + the 7-case MatchLifecycleManager integration test (which exercises the LPUSH path through real ioredis-mock instances)."
  - "ioredis named-import shape — `import { Redis } from 'ioredis'` (not `import Redis from 'ioredis'`). Default-export `Redis` is the class but TS 5.6 + NodeNext `moduleResolution` reports the default-export as a namespace (not a constructable). Named-import works in both prod runtime (esModuleInterop=true) and TS typecheck. Aligns with the existing `import type { Redis }` shape we use in the field types."
  - "fetchSignedJson<T> for GET uses empty-body signing — `body = ''`, digest input is `timestamp + ''` = `timestamp`. This matches the apps/web VerifyRconSignature middleware semantics: it computes its expected digest over `timestamp . raw_body`, and for GET `raw_body` is the empty request body (per node:http + nginx + Laravel default behaviour). The X-Rcon-Nonce + X-Rcon-Timestamp uniqueness defends against replay; the empty-body shape is intentional and documented in the call-site comments."

# Metrics
duration: 10min
completed: 2026-05-14
---

# Phase 8 Plan 11: Wave 7 — rcon-worker BookingScheduler + MatchLifecycleManager + RedisFailoverQueue + index.ts Summary

**Wired the worker tier into a complete booking-driven lifecycle: BookingScheduler polls `/api/internal/bookings/due` every 30s and spawns one `MatchLifecycleManager` per due booking; each manager fetches per-server credentials via `/api/internal/match-servers/{id}/credentials`, opens a CRCON `/ws/logs` session via `CrconClient`, batches normalised events (≥10 per batch OR every 2s), POSTs to `/api/internal/match/{id}/events` via the existing signed `WebIngestClient.postEvents`, and falls back to `RedisFailoverQueue` (LPUSH to `rcon:queue:{matchId}`) on non-2xx for asynchronous retry. Synthetic `manual_error` events are emitted at session-open hard failures so the apps/web RconUnreachableFlagsManualTest invariant holds end-to-end. The new `index.ts` entrypoint replaces the Wave-0 placeholder — graceful SIGTERM/SIGINT shutdown calls stop() on both scheduler + drainer + redis.disconnect() with a 100ms pino-flush window. 40/40 Vitest cases GREEN (24 pre-existing + 5 BookingScheduler unit + 4 RedisFailoverQueue unit + 7 MatchLifecycleManager integration); pnpm typecheck + pnpm lint + pnpm build all clean; container boots cleanly with valid env and demonstrates the warn-on-500-no-crash deviation rule against a stub-less apps/web. Zero Rule 1, three Rule 2 testability hooks (managerFactory + flushIntervalMs/batchSize/completeGraceMs + async waitFor()). Zero Rule 4 architectural changes.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-05-14T05:35:22Z
- **Completed:** 2026-05-14T05:44:54Z
- **Tasks:** 2 / 2
- **Files created:** 7 (3 src + 1 type + 3 tests)
- **Files modified:** 4 (WebIngestClient, index.ts, package.json, pnpm-lock.yaml)
- **Commits:** 2 (Task 1 `9cb1f82`; Task 2 `ef6bb30`)

## Accomplishments

### TDD Gate Sequence

Plan-level type is `execute` and both tasks have `tdd="true"`. Per the plan's TDD instruction (`pnpm test 2>&1 | grep -qE 'passed|✓'`), we authored implementation + tests in the same commit per task (the worker side has no pre-existing RED stubs for these files — plan 08-01 Wave 0 only scaffolded the CRCON-side stubs that 08-10 already turned GREEN). Each task's tests went GREEN on the first iteration after one minor ioredis-named-import + lint warning cleanup.

1. **Task 1 — GREEN BookingScheduler + RedisFailoverQueue + WebIngestClient.fetchSignedJson + 9 unit tests** (commit `9cb1f82`): tests authored + implementation written in lockstep; verified GREEN via `pnpm vitest run tests/unit/BookingScheduler tests/unit/RedisFailoverQueue` (5 + 4 = 9 cases) plus full-suite re-run (33/33 with the 24 pre-existing).
2. **Task 2 — GREEN MatchLifecycleManager + index.ts entrypoint + 7 integration tests** (commit `ef6bb30`): full integration suite uses ephemeral ws + http servers per test, no global state, parallel-safe. Verified GREEN via `pnpm test && pnpm typecheck && pnpm lint && pnpm build` (40/40, all gates clean).

### Application code (3 created, 2 modified, 1 stub→full)

1. **`apps/rcon-worker/src/booking/BookingScheduler.ts`** (new) — `tick()` polls `/api/internal/bookings/due` via `webClient.fetchSignedJson<BookingDueData[]>`, spawns one manager per due booking through an injectable `managerFactory` (test seam), reaps complete managers from `this.active`. webClient errors caught + logger.warn'd. `start()` runs an immediate first tick + sets up the 30s interval; `stop()` clears the interval + calls `.stop()` on every active manager + clears the Map. Exposes `getActiveCount()` + `isActive(bookingId)` for tests + diagnostics.

2. **`apps/rcon-worker/src/booking/MatchLifecycleManager.ts`** (new) — full implementation per the plan's `<interfaces>`. `start()`: fetches credentials, opens CrconClient with `ws://{host}:{port_rcon}/ws/logs`, installs flushTimer + completeTimer. `onLogs()` normalises each raw entry, buffers, triggers `flush()` at `buffer.length >= batchSize`. `flush()` POSTs the entire buffer (splice) — on non-2xx it LPUSHes the JSON.stringified batch to `rcon:queue:{matchId}`. `emitManualError(kind, detail)` produces a synthetic NormalisedEvent (event_type=manual_error, crcon_action=SYNTHETIC, deterministic stream_id) and POSTs it — used at credentials-fetch failure (kind=unreachable). `tryComplete()` is scheduled for `reserved_to + completeGraceMs - Date.now()` and sets complete=true regardless of `sawMatchEnd`. 3 testability overrides (`flushIntervalMs`, `batchSize`, `completeGraceMs`) + 2 test-only getters (`getBufferSize`, `getSawMatchEnd`).

3. **`apps/rcon-worker/src/booking/types.ts`** (new) — worker-side `BookingDueData` interface mirroring the apps/web spatie/data DTO (id / match_id / server_id / server_host / server_port / reserved_from-ISO / reserved_to-ISO). Defined locally pending plan 08-12's shared-types regeneration; field names + types are byte-for-byte wire-compatible.

4. **`apps/rcon-worker/src/queue/RedisFailoverQueue.ts`** (new) — `drain()` uses `redis.scanStream({ match: 'rcon:queue:*', count: 50 })`, for each key reads up to 100 stored batches via LRANGE, flattens via JSON.parse + spread into a single NormalisedEvent[], POSTs to `/events`, LTRIMs `[0..items.length-1]` on 2xx. Malformed JSON in a stored batch is logged + dropped (so a single corrupt entry can't block the queue forever). Public `queueKey(matchId): string` export shared with MatchLifecycleManager — single source of truth for the `rcon:queue:{matchId}` shape.

5. **`apps/rcon-worker/src/ingest/WebIngestClient.ts`** (modified) — added `fetchSignedJson<T>(path: string): Promise<T>`. HMAC-signed GET (body=''), throws on non-2xx, parses + casts JSON to T. Production call-sites: BookingScheduler for `/bookings/due`; MatchLifecycleManager for `/match-servers/{id}/credentials`.

6. **`apps/rcon-worker/src/index.ts`** (full replacement — placeholder → real boot) — loads config, builds ioredis client (maxRetriesPerRequest=3, enableOfflineQueue=true, redis-on-error → logger.warn), starts BookingScheduler + RedisFailoverQueue, logs `rcon-worker started` with poll interval + URLs. SIGTERM/SIGINT handler clears intervals, stops managers, disconnects redis, exits 0 with a 100ms pino-flush window. Fatal startup errors logged at fatal level + exit 1.

### Tests created (3 — all GREEN)

7. **`apps/rcon-worker/tests/unit/BookingScheduler.test.ts`** — 5 cases: (1) empty due → no managers spawned; (2) 2 due → 2 managers in active, all started; (3) tick() ×2 with same bookings → still 2 managers (idempotent); (4) manager.isComplete=true + empty due → manager reaped + .stop() called; (5) webClient throws → logger.warn called, no crash. Uses vi.fn + plain-closure stub manager (no vi.mock — keeps the test surface tiny and explicit).

8. **`apps/rcon-worker/tests/unit/RedisFailoverQueue.test.ts`** — 4 cases: (1) empty queue → no postEvents calls; (2) 3 stored batches → flattened into one POST, LTRIM clears the key on 202; (3) 500 → items retained, logger.warn with 'non-2xx' tag; (4) two matches → both drained in one pass. Uses ioredis-mock for in-memory Redis (no real container dep).

9. **`apps/rcon-worker/tests/integration/MatchLifecycleManager.integration.test.ts`** — 7 cases against per-test ephemeral ws.WebSocketServer + node:http server: (1) happy path 5 events incl match_end → /events POST with all 5 + sawMatchEnd=true; (2) credentials 404 → synthetic manual_error POSTed with kind=unreachable + complete=true; (3) ws connection refused (port pre-closed) → buffer empty + zero /events POSTs; (4) 12 events in one frame → batch cap flush at 10 (entire buffer posted); (5) flush timer (100ms) with 3 events → POST'd; (6) /events 500 → batch LPUSHed to `rcon:queue:{matchId}` (verified via ioredis-mock); (7) reserved_to elapsed + completeGraceMs=50ms grace → complete=true without match_end.

## Task Commits

1. **GREEN Task 1 — BookingScheduler + RedisFailoverQueue + fetchSignedJson + 9 GREEN unit tests** — `9cb1f82` (feat)
2. **GREEN Task 2 — MatchLifecycleManager integration tests + index.ts entrypoint (7 GREEN)** — `ef6bb30` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (4)
- `apps/rcon-worker/src/booking/BookingScheduler.ts`
- `apps/rcon-worker/src/booking/MatchLifecycleManager.ts`
- `apps/rcon-worker/src/booking/types.ts`
- `apps/rcon-worker/src/queue/RedisFailoverQueue.ts`

### Tests (3)
- `apps/rcon-worker/tests/unit/BookingScheduler.test.ts`
- `apps/rcon-worker/tests/unit/RedisFailoverQueue.test.ts`
- `apps/rcon-worker/tests/integration/MatchLifecycleManager.integration.test.ts`

## Files Modified

### Application code (2)
- `apps/rcon-worker/src/ingest/WebIngestClient.ts` — added `fetchSignedJson<T>(path)`.
- `apps/rcon-worker/src/index.ts` — placeholder console.log → full boot path.

### Project metadata (2)
- `apps/rcon-worker/package.json` — added `ioredis-mock` + `@types/ioredis-mock` to devDependencies.
- `pnpm-lock.yaml` — lockfile updated for the new devDeps.

## Decisions Made

See `key-decisions` in the frontmatter. Highlights:

- **`managerFactory` option on BookingScheduler** — Rule 2 testability hook. Production wiring uses the default factory (real MatchLifecycleManager); unit tests pass a closure-based stub. Same pattern as 08-10's `heartbeatIntervalMs`.
- **3 testability overrides on MatchLifecycleManager** (`flushIntervalMs` / `batchSize` / `completeGraceMs`). Production defaults are 2_000ms / 10 / 60_000ms per the plan; tests use shorter values so the time-driven paths exercise deterministically without real-world waits.
- **`fetchSignedJson<T>` throws on non-2xx** rather than returning `{status, body}` like `postEvents`. Centralises the transient-failure check at one site instead of repeating it at every call-site (scheduler + manager).
- **ioredis-mock chosen over hand-rolled shim** — the plan permitted either, but ioredis-mock satisfies the scanStream + LRANGE + LPUSH + LTRIM + LLEN surface natively; no shim needed.
- **ioredis named-import** — `import { Redis } from 'ioredis'` rather than `import Redis from 'ioredis'` for TS 5.6 + NodeNext compatibility (the default-export is reported as a namespace, not constructable). Aligns with the `import type { Redis }` shape already used in field types.
- **fetchSignedJson signs over empty body** — matches the apps/web VerifyRconSignature middleware's `timestamp . raw_body` digest semantics for GET requests where the body is empty.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 — Testability hook] BookingScheduler.managerFactory option added**

- **Found during:** Task 1 first iteration of BookingScheduler.test.ts.
- **Issue:** The plan's `<interfaces>` BookingScheduler sketch inlines the MatchLifecycleManager construction inside `tick()`. Unit tests would then need to mock both CrconClient + WebIngestClient + ioredis + the http layer just to assert on scheduler-level behaviour (spawn count, idempotency, reap). That's a heavy mock surface for a test that only cares about the scheduler's state machine.
- **Fix:** Surfaced the manager construction as an injectable `managerFactory` option. Production wiring (index.ts) leaves it unset and gets the default factory (which builds a real `MatchLifecycleManager` with the same constructor args the inlined sketch would have produced). Unit tests pass a closure-based stub that records start/stop/complete state via plain object closures. The production behaviour contract is preserved bit-for-bit; only the construction site is parameterised.
- **Files modified:** `apps/rcon-worker/src/booking/BookingScheduler.ts`.
- **Commit:** Folded into Task 1 commit `9cb1f82`.

**2. [Rule 2 — Testability hooks] MatchLifecycleManager `flushIntervalMs` / `batchSize` / `completeGraceMs` overrides**

- **Found during:** Task 2 first iteration of MatchLifecycleManager.integration.test.ts.
- **Issue:** The plan's defaults are 2_000ms flush / batch=10 / 60_000ms grace. Test case 7 ("reserved_to elapses without match_end → tryComplete after grace") would require a 60-second test (or a real-time wait + sleep+timer hack) under those defaults; case 4 ("12 events → flush at 10") and case 5 ("flush 2s timer fires") would each spend 2 wall-clock seconds before postEvents fires; case 1 (happy path) would be a 2-second test too. Integration suite wall-clock time would balloon to >70s for a single test pass.
- **Fix:** Added 3 optional override fields to `MatchLifecycleManagerOptions`: `flushIntervalMs` (default 2_000), `batchSize` (default 10), `completeGraceMs` (default 60_000). The integration tests pass 50ms flush + 50ms grace for the time-driven paths; the batch-cap test sets a high flushIntervalMs (60_000) so only the batch-cap path can fire; the timer test sets batchSize=50 so only the timer path can fire. Production code (index.ts → scheduler → factory) leaves all 3 unset and uses the plan's documented defaults. The behaviour contract is preserved exactly — only the cadence/threshold values are parameterised.
- **Files modified:** `apps/rcon-worker/src/booking/MatchLifecycleManager.ts`.
- **Commit:** Folded into Task 2 commit `ef6bb30`.
- **Plan correctness:** The plan's must_have ("≤10 per batch or every 2s") + ("60s grace") values are unchanged in production.

**3. [Rule 2 — Test helper async predicate] waitFor() supports async predicates**

- **Found during:** Task 2 case 6 (LPUSH-on-500) — the predicate needs `await redis.llen(key) >= 1`.
- **Issue:** Plan 08-10's CrconClient.integration.test.ts has a synchronous `waitFor(pred: () => boolean)` helper. Copying that helper verbatim would force test case 6 to busy-poll redis from outside the predicate (or worse, hardcode a setTimeout).
- **Fix:** Generalised `waitFor()` to accept `pred: () => boolean | Promise<boolean>` — the loop does `await pred()` and handles both signatures. The synchronous tests (1-5, 7) remain unchanged in their predicate shape; case 6 passes an async closure.
- **Files modified:** `apps/rcon-worker/tests/integration/MatchLifecycleManager.integration.test.ts`.
- **Commit:** Folded into Task 2 commit `ef6bb30`.
- **Plan correctness:** Test helper change only; no production-code impact.

### Auth Gates

None — implementation-only plan. The HMAC signing path is internal to the worker + apps/web; no human authentication touched.

### Architectural Changes (Rule 4 — required user decision)

None.

---

**Total deviations:** 3 (all Rule 2 testability hooks). Zero Rule 1, 3, or 4 changes.
**Impact on plan:** Zero changes to plan must_haves contract. All 3 deviations are additive testability seams — production behaviour preserved bit-for-bit. The injectable factory + override options are the same pattern as plan 08-10's `heartbeatIntervalMs` (testability hook auto-added so CI is deterministic).

## Issues Encountered

- **`make` not on PATH** — same as plans 08-02..08-10. CLAUDE.md §1 documents Makefile aliases as the canonical container surface; `make` itself isn't installed in this session's host. Resolved by invoking `docker compose run --rm --no-deps -v "$(pwd):/repo" -w /repo/apps/rcon-worker --entrypoint sh rcon-worker -c "pnpm …"` directly — still CLAUDE.md §1 / D-021 compliant (executes inside the rcon-worker image, never on the host). Plan 08-01 SUMMARY locked this as the "D-021 one-shot container workaround".
- **rcon-worker container is one-shot** — same constraint as 08-10. The compose `command` is `node dist/index.js` which now runs the real boot, polls `/bookings/due`, gets a 500 (apps/web env lacks WEB_HMAC_SECRET), warns, and keeps polling — so the container is now actually long-running and could in principle be `docker compose exec`'d. We continue to use `--rm` one-shot containers for tests to avoid environment coupling, but the production boot path now keeps the process alive.
- **First-iteration typecheck failure on `import Redis from 'ioredis'`** — TS 5.6 + NodeNext reports the default export as a namespace (not a constructable). Fixed by switching to `import { Redis } from 'ioredis'` (named import). Single-line fix; no test impact.
- **First-iteration lint warning** — an `eslint-disable-next-line no-constant-condition` directive on a `while (true)` loop was flagged as unused by the eslint config (no `no-constant-condition` rule was active to disable). Replaced with `for (;;)` form which doesn't trip the rule in the first place.

## User Setup Required

None — internal worker-tier TypeScript + tests. No env keys added, no migrations, no Filament resources. The existing `WEB_HMAC_SECRET` + `WEB_INTERNAL_URL` + `REDIS_URL` env shape from config.ts (plans 08-01 + 08-10) is what the new index.ts consumes. The compose service definition is already correct — no docker-compose.yml change needed.

## Manual Smoke Result

Per the plan's `<verification>` block: "Worker container can boot via `docker compose up rcon-worker` without crashing (manual one-line smoke)". Executed with valid env:

```bash
docker compose run --rm --no-deps \
  -e WEB_HMAC_SECRET="$(printf 'a%.0s' {1..32})" \
  -e WEB_INTERNAL_URL='http://web-nginx' \
  rcon-worker node dist/index.js
```

Observed log lines:
- `{"level":30,...,"pollIntervalMs":30000,"webUrl":"http://web-nginx","redisUrl":"redis://redis:6379","msg":"rcon-worker started"}` — boot succeeded.
- `{"level":40,...,"err":"fetchSignedJson: /api/internal/bookings/due returned status 500","msg":"booking poll failed"}` — immediate first poll returned 500 (apps/web env lacks matching HMAC secret in this smoke), worker logged at warn and continued — exactly the deviation rule under test (`webClient throws → logger.warn called; no crash`).

The container stayed up between the boot line and the SIGTERM signal; healthcheck `pgrep node` would have passed.

## Next Phase Readiness

- **Plan 08-12 (E2E scrim happy path) is unblocked end-to-end.** Worker tier can drive: BookingScheduler.tick → fetchSignedJson(/bookings/due) → spawn MatchLifecycleManager → fetchSignedJson(/match-servers/{id}/credentials) → CrconClient.connect (ws://host:port/ws/logs, Authorization: Bearer) → onLogs → normalise → batch ≤10 OR every 2s → WebIngestClient.postEvents (signed POST) → on 5xx LPUSH to rcon:queue:{matchId} → RedisFailoverQueue.drain retries.

- **Plan 08-13 (Phase 8 verification) Manual Smoke A is now executable.** The worker boot smoke above is the half of Manual Smoke A that exercises the Node side; the other half (apps/web ingest path) was already in place from plans 08-05 to 08-08.

- **No blockers.** Phase 8 baseline updated: 40/40 Vitest GREEN on the rcon-worker side (24 pre-existing from 08-10 + 16 new from this plan: 5 BookingScheduler unit + 4 RedisFailoverQueue unit + 7 MatchLifecycleManager integration). apps/web Phase 8 tests UNCHANGED (this plan touches only the worker tier).

## Self-Check: PASSED

Verified before finalising:

**Files created (7) — all exist:**
- `apps/rcon-worker/src/booking/BookingScheduler.ts` ✓
- `apps/rcon-worker/src/booking/MatchLifecycleManager.ts` ✓
- `apps/rcon-worker/src/booking/types.ts` ✓
- `apps/rcon-worker/src/queue/RedisFailoverQueue.ts` ✓
- `apps/rcon-worker/tests/unit/BookingScheduler.test.ts` ✓
- `apps/rcon-worker/tests/unit/RedisFailoverQueue.test.ts` ✓
- `apps/rcon-worker/tests/integration/MatchLifecycleManager.integration.test.ts` ✓

**Files modified (4) — all staged in commits:**
- `apps/rcon-worker/src/ingest/WebIngestClient.ts` (added fetchSignedJson) ✓
- `apps/rcon-worker/src/index.ts` (placeholder → real boot) ✓
- `apps/rcon-worker/package.json` (added ioredis-mock + @types/ioredis-mock) ✓
- `pnpm-lock.yaml` ✓

**Commits (2) — reachable via `git log --oneline -3`:**
- `9cb1f82` feat(08-11): BookingScheduler + RedisFailoverQueue + fetchSignedJson + 9 GREEN unit tests ✓
- `ef6bb30` feat(08-11): MatchLifecycleManager integration tests + index.ts entrypoint (7 GREEN) ✓

**Quality gates re-run before SUMMARY:**
- `pnpm test` (full Vitest suite) → **40 PASS** (skeleton 2 + HmacSigner 7 + CrconEventNormaliser 8 + RedisFailoverQueue 4 + BookingScheduler 5 + MatchLifecycleManager.integration 7 + CrconClient.integration 7) ✓
- `pnpm typecheck` → **clean** (tsc --noEmit) ✓
- `pnpm lint` → **clean** (eslint .) ✓
- `pnpm build` → **clean** (tsc → dist/) ✓

**TDD Gate Compliance:** Plan-level type is `execute` (not `tdd`); both tasks have `tdd="true"` and each landed a feat() commit covering implementation + tests in lockstep. The new code under this plan had no pre-existing Wave-0 RED stubs (the only Wave-0 stubs were the CRCON-side tests that plan 08-10 already turned GREEN). Each task's tests went GREEN on first run after one minor ioredis-named-import + lint warning cleanup. Total: 16 new GREEN cases (5+4+7).

**Plan correctness verifications (per the plan's `<verification>` block):**
- `pnpm test` (substituted with `docker compose run --rm` per the 08-01 / 08-10 D-021 workaround) → 40/40 GREEN ✓
- `pnpm typecheck && pnpm lint && pnpm build` clean ✓
- apps/web Phase 8 tests UNCHANGED — this plan touches only the worker tier (no apps/web file modified) ✓
- Worker container boots via `docker compose up rcon-worker` without crashing — verified manually above (warn-on-500-no-crash deviation rule under live test) ✓

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
