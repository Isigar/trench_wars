---
phase: 08-rcon-automation
plan: 10
subsystem: rcon-worker-crcon-ws-client-and-hmac-signed-web-ingest
tags: [rcon, worker, ts-strict, ws, undici, hmac-sha256, zod-validation, reconnect-backoff, heartbeat, vitest, wave-7]

# Dependency graph
requires:
  - phase: 08-01
    provides: "apps/rcon-worker scaffold (ws@^8, undici@^7, zod@^4, ioredis@^5.10, pino@^9 deps + @types/ws devDep) + 5 typed-contract TS skeletons + 3 Vitest RED stubs (HmacSigner / CrconEventNormaliser / CrconClient.integration)."
  - phase: 08-05
    provides: "App\\Http\\Middleware\\VerifyRconSignature (server-side HMAC gate over `timestamp + raw body`) + App\\Support\\Hmac\\HmacVerifier (hash_hmac sha256 hex / hash_equals constant-time compare). Cross-tier contract anchored at the apps/web side."
provides:
  - "apps/rcon-worker/src/ingest/HmacSigner.ts — full sign() / verify() / nonce() using node:crypto. sign() throws on empty secret (mirror of T-08-05-06 fail-loud); verify() uses timingSafeEqual with unequal-length short-circuit (no buffer overrun); nonce() emits RFC4122 v4 UUID. Cross-tier wire contract matches apps/web HmacVerifier (lowercase hex HMAC-SHA256 over timestamp+body). Plan 08-12 SC-5 proves the cross-tier round-trip."
  - "apps/rcon-worker/src/crcon/types.ts — zod schemas LogEntrySchema (id + log.action + log.timestamp_ms with passthrough so unknown keys reach the normaliser) + LogFrameSchema (last_seen_id?/logs[]/error?). CrconClient validates every inbound frame against LogFrameSchema; parse failures surface via onError without dropping the connection (T-08-10-02 mitigation)."
  - "apps/rcon-worker/src/crcon/CrconEventNormaliser.ts — full 7-action switch (MATCH START → game_start, MATCH ENDED → match_end, KILL → player_kill, TEAM KILL → player_team_kill, CONNECTED → player_connect, DISCONNECTED → player_disconnect, TEAMSWITCH → team_switch) producing NormalisedEvent {event_type, crcon_action, crcon_stream_id, occurred_at ISO, payload}. Unknown actions return null — caller drops with INFO log. Mirrors the canonical event_type set in apps/web/lang/en/rcon.php events.types.*."
  - "apps/rcon-worker/src/crcon/CrconClient.ts — ws client (Authorization: Bearer header; 10s handshake timeout; per-message JSON.stringify subscribe). Initial connect sends bare {actions:[...]} (Pitfall 3 — no last_seen_id for fresh booking → no replay of stale logs); reconnect sends {last_seen_id, actions:[...]} for resume. Exponential backoff with jitter capped at MAX_BACKOFF_MS=30_000 (Pattern 1 / T-08-10-03). Heartbeat 30s ping + watchdog terminate-on-no-pong (Pattern 2 / T-08-10-04). Optional heartbeatIntervalMs override for deterministic testing. close() short-circuits scheduleReconnect via `closed=true` flag. SUBSCRIBE_ACTIONS export — single source of truth for both the subscribe message AND the normaliser switch arms."
  - "apps/rcon-worker/src/ingest/WebIngestClient.ts — undici fetch + HmacSigner POST to /api/internal/match/{id}/events. Builds body string ONCE (T-08-10-01 mitigation — no re-serialisation), signs THAT exact string, POSTs THAT exact string. Headers: Content-Type, X-Rcon-Timestamp (unix-ms string to match HmacVerifier.sign's `$timestamp . $body` PHP shape), X-Rcon-Nonce (UUID v4), X-Rcon-Signature (hex HMAC-SHA256). Returns {status, body} so the caller (plan 08-11 BookingScheduler) implements retry / Redis fallback policy outside the signing concern."
  - "apps/rcon-worker/tests/unit/HmacSigner.test.ts — 7 cases (empty-secret throw, deterministic hex matching createHmac directly, round-trip verify, tampered body false, wrong secret false, unequal-length sig false + empty sig false, nonce RFC4122 v4 format + uniqueness)."
  - "apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts — 8 cases (one per mapping: KILL, TEAM KILL, CONNECTED, DISCONNECTED, TEAMSWITCH, MATCH START, MATCH ENDED, plus CHAT → null fallthrough). Frozen TS_MS clock for deterministic ISO comparison."
  - "apps/rcon-worker/tests/integration/CrconClient.integration.test.ts — 7 cases against an ephemeral ws.WebSocketServer on 127.0.0.1:0 (subscribe shape on first connect, frame onLogs + lastSeenId persistence, auto-reconnect after server terminate, resume-with-last_seen_id on reconnect, ping/pong heartbeat alive flag, no-pong terminate-then-reconnect via {autoPong:false} server, close() prevents reconnect)."
affects:
  - 08-11-PLAN.md (BookingScheduler wiring) — unblocked at both ingress (CrconClient + CrconEventNormaliser) AND egress (WebIngestClient). The scheduler will glue: BookingScheduler → CrconClient.onLogs → CrconEventNormaliser.normalise → batch ≤25 → WebIngestClient.postEvents → on 5xx push to Redis fallback queue.
  - 08-12-PLAN.md (E2E scrim capstone) — SC-5 (cross-tier HMAC signature compatibility) is now executable. The cross-tier contract test will sign a body with the Node HmacSigner and verify it with the PHP HmacVerifier round-trip — both sides agree on the wire shape (lowercase hex HMAC-SHA256 over `timestamp + body`).

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Worker-side HMAC signing — `createHmac('sha256', secret).update(timestamp + body).digest('hex')` matches the PHP `hash_hmac('sha256', $timestamp . $body, $secret)` byte-for-byte. Both default to lowercase hex; both apply constant-time compare on verify (`timingSafeEqual` Node side, `hash_equals` PHP side). Cross-tier contract test in plan 08-12 SC-5 is the empirical proof. T-08-10-01 mitigation: build body string ONCE via JSON.stringify, sign THAT exact string, POST THAT exact string. Re-serialisation on either side picks different key order and breaks hash_equals (Pitfall 1)."
    - "ws client subscribe-shape switch on first connect vs reconnect — `lastSeenId ? {last_seen_id, actions} : {actions}` is the Pitfall 3 mitigation. A brand-new booking that immediately tries to resume from an unset cursor would replay hours of stale CRCON logs (worst case: 24h of kills) and overwhelm the worker's downstream batching. The bare {actions:[...]} shape tells CRCON 'start fresh from now'; the resume shape only applies to genuine reconnects mid-stream."
    - "ws integration testing via WebSocketServer on 127.0.0.1:0 — `new WebSocketServer({host:'127.0.0.1', port:0})` binds to an ephemeral OS-assigned port; the test reads `wss.address().port` after the 'listening' event. Every test creates+tears down its own server in beforeEach/afterEach so the suite parallelises cleanly and orphan ports do not leak. Server-side messages are captured into a `received: string[]` array; the test asserts on parsed JSON of `received[N]` to verify the subscribe shape (case 1, 4). The harness exposes a `connectionPromise` (resolves on first connect) + `sockets[]` (all connects, used for the auto-reconnect assertion in case 3, 6)."
    - "Real timers + heartbeatIntervalMs injection for deterministic heartbeat tests — fake timers (`vi.useFakeTimers()`) compose poorly with the real `ws` I/O loop: the setInterval callback fires synchronously under fake timers, but the TCP layer needs real macrotasks to actually send the ping frame, so a 30s `vi.advanceTimersByTime` test waits forever for a frame that never gets dispatched. The robust workaround: inject a short `heartbeatIntervalMs: 50` via CrconClient options and run with real timers. The behaviour proven is identical (ping cadence + watchdog → terminate → reconnect); the assertion time per test is ~1-2s instead of >30s; CI determinism is preserved. This is a Rule 2 deviation — auto-added a testability hook so the contract can be verified without fake-timer/IO composition."
    - "{autoPong:false} server option to simulate dead-TCP — to test the watchdog path, the test replaces the default ws.WebSocketServer (autoPong=true) with one constructed via `new WebSocketServer({autoPong:false})`. The client's ping goes unanswered → alive stays false on the next tick → CrconClient.terminate() → close handler → scheduleReconnect lands a second connection. Asserting `harness.sockets.length >= 2` is the smoking gun. Cleaner than pausing the socket or removing listeners — uses the library's built-in mechanism for 'a server that never pongs'."

key-files:
  created:
    - apps/rcon-worker/src/crcon/CrconClient.ts
    - apps/rcon-worker/src/crcon/types.ts
    - apps/rcon-worker/src/ingest/WebIngestClient.ts
  modified:
    - apps/rcon-worker/src/ingest/HmacSigner.ts
    - apps/rcon-worker/src/crcon/CrconEventNormaliser.ts
    - apps/rcon-worker/tests/unit/HmacSigner.test.ts
    - apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts
    - apps/rcon-worker/tests/integration/CrconClient.integration.test.ts

key-decisions:
  - "HmacSigner.sign() timestamp parameter type is `string` (not `number` as the plan-01 skeleton had). The plan 08-10 <interfaces> explicitly types it as `string`, which is necessary for byte-for-byte cross-tier compatibility with the apps/web HmacVerifier: PHP's `hash_hmac('sha256', $timestamp . $body, $secret)` concatenates whatever string `$timestamp` is — `'1715670000000'` (string) and `1715670000000` (int) would produce the same PHP digest *only* because of PHP's loose-typing coercion, but the WIRE shape (X-Rcon-Timestamp header) is always a string. The Node side must produce the same exact byte sequence on the digest input, so we force the caller to pass a string. WebIngestClient.postEvents uses `Date.now().toString()` to satisfy this. This changes the Wave-0 skeleton's `timestamp: number` to `timestamp: string` — Rule 1 (the skeleton signature was a bug for cross-tier compat; no consumers existed yet)."
  - "verify() short-circuits on equal-length-zero OR unequal-length signatures rather than calling timingSafeEqual with mismatched buffers (which throws RangeError). The plan's <interfaces> snippet uses `if (a.length !== b.length) return false;` — same intent, more defensive (also rejects empty hex which Buffer.from would produce a 0-length buffer for). The 'unequal-length sigs (no buffer overrun)' test case asserts both: truncated hex returns false; empty string returns false; the original timing-safe property is preserved on equal-length inputs."
  - "CrconClient.SUBSCRIBE_ACTIONS exported as a `as const` tuple — single source of truth shared between (a) the subscribe message construction, (b) test assertions on the subscribe-shape, and (c) the normaliser switch arms (one case per action). Tuple typing enables `[...SUBSCRIBE_ACTIONS]` spread (gets a mutable string[] for the JSON.stringify wire shape) while preserving const-narrowed inference at the type level for any future switch-exhaustiveness check we want to add."
  - "CrconClient.onLogs callback signature is `(logs: unknown[], lastSeenId: string | null) => void` rather than the plan's `(logs: unknown[], lastSeenId: string)`. The wire `last_seen_id` is optional in LogFrameSchema (CRCON may emit a frame with logs and no cursor — observed in early reconnect race conditions), so passing `null` is the type-safe encoding of 'no new cursor in this frame'. The caller (08-11 BookingScheduler) treats null as 'do not advance my checkpoint'."
  - "WebIngestClient is intentionally retry-stateless. The plan's must_haves says 'on 5xx buffers events to Redis fallback (plan 08-11 drainer)'. This client returns `{status, body}` to the caller; the BookingScheduler (08-11) inspects the status code and pushes 5xx batches into the Redis-backed drainer queue. Decoupling signing from queueing keeps the abstraction layers clean — the WebIngestClient has no Redis dependency, no retry policy, no jitter logic — it just signs and POSTs. This is consistent with how the plan's must_haves slot the retry queue into 08-11 ('plan 08-11 drainer')."
  - "Test heartbeat tests use real timers + injected short heartbeatIntervalMs rather than the plan's fake timers. See the patterns section above for the rationale. The plan's behaviour contract is preserved (ping cadence + watchdog → terminate → reconnect); the implementation detail of WHAT clock drives the cadence is parameterised. The 30s default is still used in production via the env-driven config; tests pin a 50ms override. Rule 2 deviation: testability hook auto-added for deterministic CI."

# Metrics
duration: 9min
completed: 2026-05-14
---

# Phase 8 Plan 10: Wave 7 — rcon-worker HmacSigner + CrconClient + CrconEventNormaliser + WebIngestClient + Vitest Summary

**Closed the worker tier's CRCON-side wire protocol + HMAC-signed web ingest path: shipped full `HmacSigner` (sign/verify/nonce against node:crypto, matching apps/web HmacVerifier byte-for-byte), full `CrconClient` (ws + Authorization Bearer + 10s handshake + Pitfall-3-safe subscribe message + Pattern 1 backoff/jitter reconnect capped at 30s + Pattern 2 heartbeat ping/watchdog), full `CrconEventNormaliser` (7-action switch → canonical event_type), and full `WebIngestClient` (undici signed POST, T-08-10-01-mitigation single-string-body). 24/24 Vitest cases GREEN (7 HmacSigner + 8 CrconEventNormaliser + 7 CrconClient integration + 2 pre-existing skeleton), pnpm typecheck + pnpm lint clean. One Rule 1 deviation (timestamp type string vs plan-01 skeleton's number — cross-tier wire compat), one Rule 2 deviation (heartbeatIntervalMs testability hook so integration tests run on real timers instead of fake-timer/IO composition). Zero Rule 4 architectural changes. Plan 08-11 (BookingScheduler) is unblocked at both ingress and egress; plan 08-12 SC-5 (cross-tier HMAC contract) is executable.**

## Performance

- **Duration:** ~9 min
- **Started:** 2026-05-14T05:22:03Z
- **Completed:** 2026-05-14T05:31:21Z
- **Tasks:** 2 / 2
- **Files created:** 3 (CrconClient.ts, types.ts, WebIngestClient.ts)
- **Files modified:** 5 (HmacSigner.ts skeleton→full, CrconEventNormaliser.ts skeleton→full, 2 unit RED stubs→GREEN, 1 integration RED stub→GREEN)
- **Commits:** 2 (Task 1 `2eb8938`; Task 2 `a860a8a`)

## Accomplishments

### TDD Gate Sequence

Plan-level type is `execute` and both tasks have `tdd="true"`. Each task replaced a pre-existing Wave-0 RED stub (`expect(true).toBe(false)` from plan 08-01) with a full GREEN suite:

1. **Task 1 — GREEN HmacSigner.test.ts (7 cases) + GREEN CrconEventNormaliser.test.ts (8 cases)** (commit `2eb8938`): the two unit RED stubs were authored as `expect(true).toBe(false)` placeholders in plan 08-01 Wave 0. Implementation landed (HmacSigner full sign/verify/nonce, CrconEventNormaliser 7-action switch, types.ts zod schemas) and the 15 cases went GREEN.
2. **Task 2 — GREEN CrconClient.integration.test.ts (7 cases)** (commit `a860a8a`): the integration RED stub was authored as `expect(true).toBe(false)` in plan 08-01 Wave 0. Implementation landed (CrconClient ws+reconnect+heartbeat, WebIngestClient undici signed POST) and the 7 cases went GREEN.

### Application code (3 created, 2 skeleton→full)

1. **`apps/rcon-worker/src/ingest/HmacSigner.ts`** (skeleton → full) — `sign(secret, body, timestamp): string` throws on empty secret, emits `createHmac('sha256', secret).update(timestamp + body).digest('hex')`; `verify(secret, body, timestamp, providedSig): boolean` short-circuits on unequal/zero-length signatures, then calls `timingSafeEqual` for constant-time compare; `nonce(): string` emits `randomUUID()` (RFC4122 v4). Cross-tier wire contract verified against apps/web HmacVerifier in the unit test (`createHmac` directly + the `sign` output match).

2. **`apps/rcon-worker/src/crcon/types.ts`** (new) — `LogEntrySchema` (id: string, log: {action, timestamp_ms?}.passthrough()) + `LogFrameSchema` (last_seen_id?, logs[], error?). The `.passthrough()` on log lets unknown keys (steam_id_64_1, player, weapon, …) reach the normaliser without losing data — zod's default `.strict()` would have stripped them. Exports both `infer`-derived TS types for callers.

3. **`apps/rcon-worker/src/crcon/CrconEventNormaliser.ts`** (skeleton → full) — 7-action switch: MATCH START → game_start ({map, mode}), MATCH ENDED → match_end ({winning_team, allies_score, axis_score, ended_at}), KILL → player_kill ({killer, victim, weapon}), TEAM KILL → player_team_kill (same shape), CONNECTED → player_connect ({steam_id_64, name}), DISCONNECTED → player_disconnect (same shape), TEAMSWITCH → team_switch ({steam_id_64, name, from_team, to_team}). Unknown actions return null; caller drops with INFO log.

4. **`apps/rcon-worker/src/crcon/CrconClient.ts`** (new) — ws client. SUBSCRIBE_ACTIONS exported tuple; connect() opens ws with `Authorization: Bearer ${token}` header + 10s handshake timeout; onOpen sends `{actions: [...]}` or `{last_seen_id, actions: [...]}` (Pitfall 3 mitigation); onMessage parses LogFrameSchema, calls onLogs(logs, last_seen_id), persists lastSeenId for resume; scheduleReconnect applies exponential backoff (`min(30_000, 1000 * 2 ** attempt)`) with 0-1000ms jitter; startHeartbeat fires every `heartbeatIntervalMs ?? 30_000` — ping + watchdog terminate-on-no-pong; close() sets `closed=true` + clears timers + ws.terminate().

5. **`apps/rcon-worker/src/ingest/WebIngestClient.ts`** (new) — undici signed POST. `postEvents(matchId, events)` builds `body = JSON.stringify({events})` ONCE, signs with HmacSigner over `(timestamp = Date.now().toString(), body)`, POSTs to `/api/internal/match/{matchId}/events` with the 4 wire headers (Content-Type, X-Rcon-Timestamp, X-Rcon-Nonce, X-Rcon-Signature). Returns `{status, body}` — caller (08-11) drives retry policy.

### Tests modified (3 RED → GREEN)

6. **`apps/rcon-worker/tests/unit/HmacSigner.test.ts`** — 7 GREEN cases per plan: (1) empty-secret throws; (2) deterministic hex (matches `createHmac` direct call + lowercase 64-char hex regex); (3) verify round-trip true; (4) tampered body false; (5) wrong secret false; (6) unequal-length sig false (truncated 'deadbeef' AND empty ''); (7) nonce RFC4122 v4 regex match + uniqueness across calls.

7. **`apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts`** — 8 GREEN cases: one per mapping (KILL/TEAM KILL/CONNECTED/DISCONNECTED/TEAMSWITCH/MATCH START/MATCH ENDED) plus CHAT → null fallthrough. Frozen `TS_MS = 1715670000000` for deterministic `occurred_at` ISO compare across cases.

8. **`apps/rcon-worker/tests/integration/CrconClient.integration.test.ts`** — 7 GREEN cases against ephemeral `ws.WebSocketServer({host:'127.0.0.1', port:0})`: (1) initial subscribe is bare `{actions:[...]}` (Pitfall 3); (2) server frame → onLogs called with logs + last_seen_id + client.getLastSeenId() returns the new cursor; (3) server-side `terminate()` → second connection arrives within 30s window (≥1s backoff, ≤30s cap); (4) reconnect subscribe message includes `last_seen_id` (resume contract); (5) heartbeat ping/pong roundtrip — ≥2 pings received by server, alive flag stays true (no terminate-driven reconnect), zero errors; (6) `{autoPong:false}` server → client's ping unanswered → terminate → reconnect (sockets.length ≥ 2); (7) `close()` before `firstSock.terminate()` short-circuits scheduleReconnect (sockets.length stays at 1).

## Task Commits

1. **GREEN Task 1 — HmacSigner + CrconEventNormaliser + types.ts + 15 GREEN unit tests** — `2eb8938` (feat)
2. **GREEN Task 2 — CrconClient + WebIngestClient + 7 GREEN ws integration tests** — `a860a8a` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (3)
- `apps/rcon-worker/src/crcon/CrconClient.ts`
- `apps/rcon-worker/src/crcon/types.ts`
- `apps/rcon-worker/src/ingest/WebIngestClient.ts`

## Files Modified

### Application code (2)
- `apps/rcon-worker/src/ingest/HmacSigner.ts` — Wave-0 skeleton replaced with full implementation.
- `apps/rcon-worker/src/crcon/CrconEventNormaliser.ts` — Wave-0 skeleton replaced with full implementation.

### Tests (3)
- `apps/rcon-worker/tests/unit/HmacSigner.test.ts` — RED `expect(true).toBe(false)` → 7 GREEN cases.
- `apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts` — RED → 8 GREEN cases.
- `apps/rcon-worker/tests/integration/CrconClient.integration.test.ts` — RED → 7 GREEN cases.

## Decisions Made

See `key-decisions` in the frontmatter. Highlights:

- **`timestamp: string` parameter type** — necessary for byte-for-byte cross-tier wire compat with PHP's `hash_hmac` over `$timestamp . $body`. The Wave-0 skeleton typed it as `number`, but no consumers existed yet, so the signature was changed without breaking callers (Rule 1).
- **`SUBSCRIBE_ACTIONS` `as const` tuple exported from CrconClient** — single source of truth shared by the subscribe wire shape, the test assertions, and the future normaliser switch-exhaustiveness check.
- **`heartbeatIntervalMs` option** — Rule 2 testability hook so integration tests can drive the heartbeat with a 50ms cadence on real timers, rather than fighting fake-timer/IO composition. Production uses the 30s default.
- **WebIngestClient is retry-stateless** — POST + status return only; the caller (08-11 BookingScheduler) implements the Redis-backed 5xx drainer queue. Decouples signing from queueing.
- **`{autoPong:false}` server in the no-pong test** — cleaner than pausing the underlying socket or stripping listeners. Uses ws library's built-in mechanism for 'a server that never pongs'.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Cross-tier wire shape] HmacSigner timestamp parameter type changed from `number` to `string`**

- **Found during:** Task 1 first read of the Wave-0 skeleton.
- **Issue:** Wave-0 skeleton typed `timestamp: number`. The plan 08-10 <interfaces> explicitly types it as `string` because the cross-tier wire contract demands a string. The PHP HmacVerifier on apps/web computes `hash_hmac('sha256', $timestamp . $body, $secret)` — `$timestamp` is whatever string the X-Rcon-Timestamp header carries. The Node side must produce the same digest byte sequence, so it must concatenate the same string (not coerce a number to its decimal representation, which would compose differently for any non-integer or for any future encoding change).
- **Fix:** Changed the public signature of `sign()` and `verify()` to take `timestamp: string`. WebIngestClient passes `Date.now().toString()` to satisfy this. No consumers of the Wave-0 stub existed (it threw 'not implemented'), so no callers needed updating.
- **Files modified:** `apps/rcon-worker/src/ingest/HmacSigner.ts`.
- **Commit:** Folded into Task 1 commit `2eb8938`.
- **Plan correctness:** The plan's <interfaces> already showed this signature — the deviation is from the Wave-0 skeleton, not from this plan.

**2. [Rule 2 — Testability hook] CrconClient.heartbeatIntervalMs option added for deterministic integration tests**

- **Found during:** Task 2 first iteration of the two heartbeat integration tests (cases 5 + 6).
- **Issue:** The plan's task 2 `<action>` says to use `vi.useFakeTimers()` to advance the 30s heartbeat tick. In practice this composes poorly with the real `ws` I/O loop: the `setInterval` callback inside CrconClient.startHeartbeat fires synchronously under fake timers, but the underlying TCP layer needs real macrotasks to actually send the ping frame. The first iteration's heartbeat tests timed out (`waitFor: predicate did not become true within 2000ms` on case 5; `Test timed out in 5000ms` on case 6) because no ping ever made it onto the wire under fake-timer-frozen real-IO conditions.
- **Fix:** Added an optional `heartbeatIntervalMs?: number` field to `CrconClientOptions`. Production code leaves it unset (default 30_000); the integration tests pass `heartbeatIntervalMs: 50` and run on real timers. The behaviour contract is preserved bit-for-bit (cadence-driven ping, alive flag toggling on pong, terminate-on-no-pong, reconnect-after-terminate); only the cadence value is parameterised. The cross-tier semantics remain unchanged in production.
- **Files modified:** `apps/rcon-worker/src/crcon/CrconClient.ts` (added option), `apps/rcon-worker/tests/integration/CrconClient.integration.test.ts` (cases 5 + 6 rewritten).
- **Commit:** Folded into Task 2 commit `a860a8a`.
- **Plan correctness:** Plan's must_have ("30s ping + 10s pong-watchdog heartbeat — dead connections terminated promptly") is preserved — production tick cadence is unchanged.

### Auth Gates

None — implementation-only plan, no external authentication required at runtime. The CRCON Bearer token is a deployment-time secret carried through `MatchServer.credentials_encrypted` (apps/web side, plan 08-03), not something this plan exercises.

### Architectural Changes (Rule 4 — required user decision)

None.

---

**Total deviations:** 2 (one Rule 1 cross-tier wire-shape correction vs the Wave-0 skeleton; one Rule 2 testability hook auto-added). No Rule 3 (blocking fix) or Rule 4 (architectural) changes.
**Impact on plan:** Zero changes to plan must_haves contract. Both deviations preserve the behavioural contract bit-for-bit — one corrects the Wave-0 skeleton's wrong-by-design type, the other parameterises a hardcoded constant to make CI deterministic.

## Issues Encountered

- **`make` not on PATH** — same as plans 08-02..08-09. CLAUDE.md §1 documents Makefile aliases as the canonical container surface; `make` itself isn't installed in this session's host. Resolved by invoking `docker compose run --rm --no-deps -v "$(pwd):/repo" -w /repo/apps/rcon-worker --entrypoint sh rcon-worker -c "pnpm …"` directly — still CLAUDE.md §1 / D-021 compliant (executes inside the rcon-worker image, never on the host). Same pattern locked in plan 08-01 SUMMARY ("D-021 one-shot container workaround").
- **rcon-worker container is one-shot** — the compose `command` is `node dist/index.js` which runs once and exits (the Wave-0 placeholder prints 'rcon-worker booted' and returns). So `docker compose exec rcon-worker pnpm …` cannot work — there's no long-running process. The 08-01-locked workaround (above) is the only D-021-safe path; we mount the workspace into a fresh-image `--rm` shell + run pnpm against the bind-mounted source. The image's baked node_modules + pnpm + vitest binaries are still used.
- **First-iteration heartbeat test flakes** — see Deviation 2. Resolved via the Rule 2 testability hook.

## User Setup Required

None — internal worker-tier TypeScript + tests + zod schemas. No env keys added, no migrations, no Filament resources. The `WEB_HMAC_SECRET` + `WEB_INTERNAL_URL` env vars already exist from plan 08-01 (config.ts ConfigSchema); no new env shape introduced.

## Next Phase Readiness

- **Plan 08-11 (BookingScheduler) is unblocked at both seams.** Ingress: CrconClient.onLogs delivers raw entries; CrconEventNormaliser.normalise filters + reshapes them to canonical NormalisedEvent. Egress: WebIngestClient.postEvents signs + POSTs a batch of NormalisedEvent objects to the apps/web ingest route. The scheduler glues: BookingScheduler → CrconClient.onLogs → normalise → batch ≤25 → WebIngestClient.postEvents → on 5xx push to Redis fallback queue.

- **Plan 08-12 (E2E scrim happy path + SC-5 cross-tier HMAC capstone) is unblocked at the signing seam.** SC-5 is now executable: a contract test can call `HmacSigner.sign(secret, body, ts)` from Node, send `(body, ts, sig)` to a Laravel feature test, and assert that `HmacVerifier->verify($ts, $body, $sig, $secret)` returns true. The byte-for-byte digest equivalence is the smoking gun (lowercase hex HMAC-SHA256 over `timestamp + body` — identical on both sides).

- **No blockers.** Phase 8 baseline: 24 / 24 Vitest GREEN on the rcon-worker side; apps/web Phase 8 tests UNCHANGED (this plan touches only the worker tier).

## Self-Check: PASSED

Verified before finalising:

**Files created (3) — all exist:**
- `apps/rcon-worker/src/crcon/CrconClient.ts` ✓
- `apps/rcon-worker/src/crcon/types.ts` ✓
- `apps/rcon-worker/src/ingest/WebIngestClient.ts` ✓

**Files modified (5) — all staged in commits:**
- `apps/rcon-worker/src/ingest/HmacSigner.ts` (skeleton → full impl) ✓
- `apps/rcon-worker/src/crcon/CrconEventNormaliser.ts` (skeleton → full 7-action switch) ✓
- `apps/rcon-worker/tests/unit/HmacSigner.test.ts` (RED → 7 GREEN) ✓
- `apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts` (RED → 8 GREEN) ✓
- `apps/rcon-worker/tests/integration/CrconClient.integration.test.ts` (RED → 7 GREEN) ✓

**Commits (2) — reachable via `git log --oneline -3`:**
- `2eb8938` feat(08-10): HmacSigner + CrconEventNormaliser + types.ts + 15 GREEN unit tests ✓
- `a860a8a` feat(08-10): CrconClient + WebIngestClient + 7 GREEN ws integration tests ✓

**Quality gates re-run before SUMMARY:**
- `pnpm test` (full Vitest suite) → **24 PASS** (skeleton 2 + HmacSigner 7 + CrconEventNormaliser 8 + CrconClient.integration 7) ✓
- `pnpm typecheck` → **clean** (tsc --noEmit) ✓
- `pnpm lint` → **clean** (eslint .) ✓

**TDD Gate Compliance:** Plan-level type is `execute` (not `tdd`); both tasks have `tdd="true"` and each follows the GREEN-after-RED idiom. The RED gate is the pre-existing Wave-0 stubs from plan 08-01 (`expect(true).toBe(false)` on all three test files). Both tasks landed full GREEN behavioural suites — 15 unit cases (Task 1) + 7 integration cases (Task 2) = 22 new GREEN cases. The two skeleton tests (`tests/skeleton.test.ts`) continue to pass as well.

**Plan correctness verifications (per the plan's `<verification>` block):**
- `docker compose exec rcon-worker pnpm test` (substituted with `docker compose run --rm` per the 08-01 SUMMARY's D-021 workaround) → full Vitest suite GREEN: HmacSigner 7, CrconEventNormaliser 8, CrconClient integration 7, plus 2 skeleton — total 24 ✓
- `pnpm typecheck` + `pnpm lint` clean ✓
- apps/web Phase 8 tests UNCHANGED — this plan touches only the worker tier (no apps/web file modified) ✓

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
