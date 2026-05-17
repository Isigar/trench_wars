---
phase: 08-rcon-automation
plan: 05
subsystem: rcon-hmac-gate
tags: [hmac, sha256, middleware, redis, nonce-store, replay-window, tdd, security, trust-boundary]

# Dependency graph
requires:
  - phase: 08-01
    provides: Wave 0 RED stub at tests/Feature/Phase8/VerifyRconSignatureTest.php (single failing case — `expect(true)->toBeFalse()`); .env.example WEB_HMAC_SECRET= entry (line 62)
  - phase: 08-04
    provides: phase baseline 1054 PASS / 7 FAIL — Wave 3 model layer (MatchEvent + MatchPlayerStat) completed; the rcon.signature gate built here protects the routes that will write to those tables in plan 08-06+
provides:
  - "App\\Support\\Hmac\\HmacVerifier — stateless sign/verify service. sign() returns lowercase hex HMAC-SHA256 over (timestamp + raw body); verify() calls sign() then hash_equals (constant-time). Throws InvalidArgumentException on empty secret (T-08-05-06 fail-loud)."
  - "App\\Http\\Middleware\\VerifyRconSignature — closure-style middleware enforcing 4-stage gate: header presence → 60s freshness (abs(), both directions per Pitfall 2) → HMAC verify → Redis SETNX EX 120 NX nonce single-use. Distinct 401 labels per failure mode for ops debuggability. NEVER logs the signature/secret/expected (Pitfall 9, T-08-05-04)."
  - "config/rcon.php — hmac_secret (env WEB_HMAC_SECRET), freshness_window_ms (60_000), nonce_ttl_seconds (120), crcon_version_pin ('10.0.0' — Open Question 1 RESOLVED)."
  - "bootstrap/app.php — middleware alias 'rcon.signature' registered alongside existing aliases (bot.acts-as, abilities, ability preserved). Mountable via `->middleware('rcon.signature')` in plan 08-06 routes."
affects:
  - 08-06-PLAN.md (Internal RCON ingest routes — mounts every worker→web endpoint behind `->middleware('rcon.signature')` exactly as the alias is registered here)
  - 08-09-PLAN.md (Filament TestConnectionAction — re-uses HmacVerifier to mint a probe signature against the same secret so admin UI can validate worker connectivity end-to-end)
  - 08-10-PLAN.md (rcon-worker outbound signer — the Node worker computes the same HMAC over (timestamp + raw body) using the same shared WEB_HMAC_SECRET; this plan defines the contract the worker MUST follow byte-for-byte)
  - 08-13-PLAN.md (Phase 8 verification — cross-tier signature contract probe verifying worker and web compute identical digests)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pre-transform headers to HTTP_* server vars when test-calling `$this->call()` directly. Laravel's `withHeaders()` stores into `$defaultHeaders` but `call()` does NOT merge them into the Symfony request — only the higher-level helpers (`post`, `json`, `get`, etc.) call `transformHeadersToServerVars()` internally. Raw-body signature gates that can't use those helpers (because they auto-encode bodies) must build the server-vars dict themselves and pass it as the 6th arg to `call()`."
    - "Carbon::now()->getTimestamp() * 1000 + ms instead of microtime(true) * 1000 for freshness-window arithmetic. `Carbon::setTestNow()` in Pest tests pins Carbon::now() to a fake clock but `microtime(true)` reads the real wall clock; using the latter inside a middleware that's asserted-against by Carbon-set timestamps yields a multi-year `abs()` delta and always-stale rejections."
    - "Redis::set facade variadic NX form with @phpstan-ignore-next-line on argument.type,arguments.count. The canonical Laravel idiom `Redis::set(key, val, 'EX', ttl, 'NX')` maps to `SET key val EX <ttl> NX` on the wire (atomic set-if-not-exists with TTL), but PHPStan's phpredis stubs (@mixin \\Redis) declare a stricter 3-arg shape. The real Illuminate\\Redis\\Connections\\PhpRedisConnection::set accepts the variadic form at runtime (vendor file line 82); the ignore comment carries the rationale so future contributors know why."

key-files:
  created:
    - apps/web/app/Support/Hmac/HmacVerifier.php
    - apps/web/app/Http/Middleware/VerifyRconSignature.php
    - apps/web/config/rcon.php
  modified:
    - apps/web/bootstrap/app.php
    - apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php

key-decisions:
  - "VerifyRconSignature uses `Carbon::now()->getTimestamp() * 1000 + milli` instead of `microtime(true) * 1000` for the freshness window arithmetic (Rule 1 deviation). The plan/RESEARCH canonical shape uses microtime, but in Pest tests `Carbon::setTestNow()` only pins Carbon — `microtime()` still reads the real wall clock. The first Pest run had a happy-path failure where the test's timestamp (Carbon-set to 2026-05-14 12:00:00) was compared against `microtime()` (real now around 04:18 UTC) and yielded an ~8h age delta, far outside the 60_000ms window. Using Carbon::now() everywhere keeps the test-now binding honoured AND production behaviour is identical (Carbon::now() defers to system time when setTestNow is unset)."
  - "Distinct 401 labels per failure mode ('missing rcon auth headers' / 'stale signature' / 'bad signature' / 'replayed nonce') intentionally reveal WHICH gate fired. This is an ops-debuggability win — the worker tells you when its clock drifts vs when its secret rotates out — without revealing the secret material itself (Pitfall 9). T-08-05-04 (secret leak via log) is mitigated by NEVER logging `$sig`, the expected signature, or the secret — only the rejection label."
  - "Tests use `$this->call('POST', uri, [], [], [], $serverVars, $rawBody)` instead of `$this->postJson(...)` because the gate signs the RAW body bytes and `postJson` auto-encodes the data array (potentially with different key ordering or whitespace than the bytes signed by `rconServerVars()`). The helper `rconServerVars()` pre-converts our four headers to `HTTP_X_RCON_*` + `CONTENT_TYPE` server vars so they reach Symfony's Request::create unchanged."
  - "HMAC compute via `hash_hmac('sha256', $timestamp . $body, $secret)` and verify via `hash_equals($expected, $providedSig)`. Constant-time compare defeats early-exit timing oracles (T-08-05-02). The lowercase-hex hex digest shape is the wire-format contract — the Node worker (plan 08-10) MUST emit lowercase hex to satisfy the byte-equal `hash_equals` check."
  - "Redis SETNX EX 120 NX via the facade-variadic form with a targeted @phpstan-ignore annotation. The `Redis::connection()->set(...)` path PHPStan-resolves through `@mixin \\Redis` (phpredis's stricter stubs); the abstract Connection class's `set` method has the variadic signature but PHPStan can't see it via the facade. Switched to `Redis::set(...)` with the suppression — it's the documented Laravel idiom and the rationale lives in the comment."
  - "Empty WEB_HMAC_SECRET fails LOUD via InvalidArgumentException in HmacVerifier::sign (T-08-05-06). Without this, hash_hmac('sha256', '...', '') silently produces a stable digest — any caller who guessed `secret=''` would land valid signatures. Fail-loud surfaces the misconfig at the FIRST request, not after an attacker discovers it."

# Metrics
duration: 13min
completed: 2026-05-14
---

# Phase 8 Plan 5: Wave 4 — HMAC middleware + nonce store + replay window Summary

**Authored two new classes (`HmacVerifier` service + `VerifyRconSignature` middleware) + `config/rcon.php` + middleware alias registration; landed the GREEN 8-case Pest feature test that exercises every branch of the CON-arch-rcon-to-web-comm contract — happy path (204), 4 missing/stale/bad-signature/tampered-body rejection modes, replay (Redis SETNX EX 120 NX), AND the Pitfall 2 future-clock-skew case. Phase 8 baseline moves from 17 PASS / 7 FAIL → 25 PASS / 6 FAIL (+8 PASS, −1 FAIL); full project regression 1054 → 1062 PASS, 7 → 6 FAIL.**

## Performance

- **Duration:** 13 min 5 s
- **Started:** 2026-05-14T04:07:32Z
- **Completed:** 2026-05-14T04:20:37Z
- **Tasks:** 2 / 2
- **Files created:** 3 (HmacVerifier service + VerifyRconSignature middleware + config/rcon.php)
- **Files modified:** 2 (bootstrap/app.php for alias + the Wave 0 RED stub upgraded to 8 GREEN cases)
- **Commits:** 2 (GREEN Task 1 `0b8dbaa`; GREEN Task 2 `6c357d1`)

## Accomplishments

### TDD Gate Sequence

1. **RED** — already in place from plan 08-01 (`9ea301b`, `tests/Feature/Phase8/VerifyRconSignatureTest.php` Wave 0 stub asserting `expect(true)->toBeFalse()`). Verified failing at start of plan: `1 failed (1 assertions)` per `pest --filter=VerifyRconSignatureTest`. No new RED commit needed in this plan (Wave 0 stub satisfies the RED gate per phase TDD convention).
2. **GREEN Task 1** (commit `0b8dbaa`): authored `HmacVerifier` + `VerifyRconSignature` + `config/rcon.php` + registered the `rcon.signature` alias. Verified: PHPStan L8 clean, Pint --test 538 files, `php artisan config:show rcon` resolves all 4 keys, `php artisan route:list` boots without errors.
3. **GREEN Task 2** (commit `6c357d1`): replaced the Wave 0 RED stub with 8 GREEN feature-test cases — happy path + 7 rejection modes. All 8 PASS; full regression 1062 PASS / 6 FAIL (the 6 remaining FAIL = Wave 0 RED stubs for plans 08-06..08-13). PHPStan L8 + Pint --test clean.

### Classes created (2)

1. **`App\Support\Hmac\HmacVerifier`** — stateless service (no constructor deps, intentionally trivial to instantiate from anywhere). `sign(string $timestamp, string $body, string $secret): string` → `hash_hmac('sha256', $timestamp . $body, $secret)` lowercase hex digest. `verify(string $timestamp, string $body, string $providedSig, string $secret): bool` → `hash_equals` constant-time compare against `sign($timestamp, $body, $secret)`. `sign()` throws `InvalidArgumentException` when `$secret === ''` (T-08-05-06 fail-loud — without this, hash_hmac silently produces a stable digest with an empty key and any guesser lands valid signatures).

2. **`App\Http\Middleware\VerifyRconSignature`** — 4-stage gate on every gated POST:
   - Header presence: all three of `X-Rcon-Timestamp`, `X-Rcon-Nonce`, `X-Rcon-Signature` MUST be present non-empty strings — 401 `'missing rcon auth headers'`.
   - Freshness window: `abs(Carbon::now_ms - timestamp_ms) <= config('rcon.freshness_window_ms', 60_000)` — 401 `'stale signature'`. The `abs()` (not raw diff) gates BOTH directions of clock skew (Pitfall 2). Carbon::now() (not microtime) so `Carbon::setTestNow()` pins the clock in tests.
   - HMAC verify: `HmacVerifier::verify($timestamp, $request->getContent(), $sig, $secret)` — 401 `'bad signature'`. Body is the RAW bytes from `$request->getContent()` — NEVER `$request->json()` (Pitfall 1; the bytes the worker signed MUST match the bytes web verifies).
   - Nonce single-use: `Redis::set("rcon:nonce:{$nonce}", '1', 'EX', 120, 'NX')` — `true` on success, `false` on replay (key already exists in the 120s TTL window) — 401 `'replayed nonce'`.

   Constructor-injects `HmacVerifier`. NEVER logs `$sig`, the expected signature, or the secret (Pitfall 9 / T-08-05-04). 401 messages are plain English labels — no hex, no diff, no length leakage.

### Configuration created (1)

3. **`apps/web/config/rcon.php`** — 4-key array:
   - `hmac_secret` → `env('WEB_HMAC_SECRET')` (no default — fail-loud at sign time if unset).
   - `freshness_window_ms` → `env('RCON_FRESHNESS_WINDOW_MS', 60_000)` — 60s mirror of the AWS SigV4 norm.
   - `nonce_ttl_seconds` → `env('RCON_NONCE_TTL_SECONDS', 120)` — 2× the freshness window for defence-in-depth (both gates must lapse for a replay to land).
   - `crcon_version_pin` → `env('CRCON_VERSION_PIN', '10.0.0')` — Open Question 1 RESOLVED to v10.0.0; OPERATIONAL pin telling the deploy which CRCON tag to ship, not a runtime negotiation.

### Configuration modified (1)

4. **`apps/web/bootstrap/app.php`** — middleware alias `'rcon.signature' => VerifyRconSignature::class` added alongside existing aliases (`abilities`, `ability`, `bot.acts-as` preserved verbatim). Plan 08-06 routes will mount this via `->middleware('rcon.signature')`.

### Test created — 8 cases all GREEN (1)

5. **`apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php`** — replaces the Wave 0 stub from 08-01:
   - **happy path** — fresh signed request → 204.
   - **missing X-Rcon-Timestamp** → 401 `'missing'`.
   - **missing X-Rcon-Signature** → 401 `'missing'`.
   - **stale timestamp (>60s past)** → 401 `'stale'`.
   - **stale timestamp (>60s future)** → 401 `'stale'` (Pitfall 2 — abs both ways).
   - **wrong secret** → 401 `'bad signature'`.
   - **tampered body** — sign body A, POST body B → 401 `'bad signature'`.
   - **replayed nonce** — 1st request 204; 2nd with SAME nonce within 120s → 401 `'replayed'`.

   `beforeEach` calls `Redis::flushdb()` to reset nonce state, `config(['rcon.hmac_secret' => RCON_TEST_SECRET])` to pin the test-scope secret, and `Route::post(RCON_TEST_ROUTE, fn () => response()->noContent())->middleware('rcon.signature')` to mount a temporary protected echo route. `afterEach` clears `Carbon::setTestNow`. Each case constructs the HMAC manually via `rconServerVars()` (no worker code — the middleware is tested in isolation).

## Task Commits

1. **GREEN Task 1 — HmacVerifier + VerifyRconSignature + config + alias** — `0b8dbaa` (feat)
2. **GREEN Task 2 — VerifyRconSignatureTest 8 cases + Carbon::now() clock fix** — `6c357d1` (test)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (3)
- `apps/web/app/Support/Hmac/HmacVerifier.php`
- `apps/web/app/Http/Middleware/VerifyRconSignature.php`
- `apps/web/config/rcon.php`

## Files Modified

### Application code (1)
- `apps/web/bootstrap/app.php` (alias `rcon.signature` added; existing aliases preserved)

### Tests (1 — Wave 0 RED stub → 8 GREEN cases)
- `apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php`

## Decisions Made

- **`Carbon::now()` instead of `microtime(true)` for the freshness arithmetic (Rule 1 deviation).** Plan/RESEARCH canonical shape used `microtime(true) * 1000`. First Pest run failed the happy-path case with the test-now-pinned timestamp 2026-05-14 12:00:00 compared against real `microtime()` (around 04:18 UTC) → ~8h age delta, far outside the 60_000ms window. `Carbon::now()` defers to system time when `Carbon::setTestNow()` is unset, so production behaviour is identical to using `microtime`. The fix is single-line in the middleware and preserves the full security semantics (still ±60s window, still abs() both directions).
- **Distinct 401 messages per failure mode (not a generic "unauthorized").** Ops debuggability: when the worker fleet starts 401-ing, the message says exactly which gate fired — clock drift vs secret rotation vs replay vs missing header. Pitfall 9 / T-08-05-04 protection is in NEVER logging the signature/expected/secret values — the rejection labels are plain English without secret material.
- **Test uses `$this->call(...)` + pre-converted `HTTP_*` server vars instead of `postJson(...)`.** The gate signs RAW body bytes; `postJson` would `json_encode($data)` internally which might produce different key ordering or whitespace than the hand-crafted body bytes the test signed. The helper `rconServerVars()` pre-transforms the four headers to `HTTP_X_RCON_*` + `CONTENT_TYPE` server vars so they reach Symfony's `Request::create` unchanged. `withHeaders()` is bypassed by `call()` (only higher-level helpers like `post`/`json` apply the conversion — caught when initial test run had `host`/`user-agent`/`accept: text/html` Symfony defaults in the request, not our HMAC headers).
- **Variadic facade form `Redis::set(key, val, 'EX', ttl, 'NX')` with targeted `@phpstan-ignore-next-line argument.type,arguments.count`.** This is the canonical Laravel docs idiom and routes to `Illuminate\Redis\Connections\PhpRedisConnection::set` (the variadic signature at vendor line 82). PHPStan's phpredis stubs (`@mixin \Redis`) declare a stricter 3-arg shape; the ignore comment carries the rationale so future contributors know why the variadic call is intentional. The `setnx + expire` alternative was rejected because it's not atomic — a process crash between `setnx` and `expire` leaves the key un-TTL'd, causing nonce-table unbounded growth (T-08-05-05 drift toward unacceptable).
- **`Accept: application/json` server var (`HTTP_ACCEPT`) added to test-side requests** so Laravel's default exception handler renders `{"message":"..."}` instead of an HTML 401 page. The tests assert against `$response->exception->getMessage()` directly (more precise than substring-matching against an HTML body that might change between Laravel versions).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `microtime(true) * 1000` bypassed `Carbon::setTestNow()` in tests**
- **Found during:** Task 2, first Pest run of the 8-case suite.
- **Issue:** The RESEARCH canonical shape (lines 385-417) used `(int)(microtime(true) * 1000)` to compute now-ms. In Pest tests `Carbon::setTestNow(Carbon::create(2026, 5, 14, 12, 0, 0))` pins Carbon's clock, but `microtime()` continues to read the system wall clock (real time ~04:18 UTC at runtime). The `abs(now_ms - timestamp_ms)` calculation then yields a ~8 hour delta and the middleware rejects every test with `'stale signature'` — including the happy path.
- **Fix:** Replaced `microtime(true) * 1000` with `Carbon::now()->getTimestamp() * 1000 + (int)Carbon::now()->milli`. `Carbon::now()` defers to system time in production (no `setTestNow` binding) so behaviour is identical at runtime; in tests it picks up the pinned clock.
- **Files modified:** `apps/web/app/Http/Middleware/VerifyRconSignature.php`.
- **Verification:** All 8 test cases pass deterministically after the fix; the stale-timestamp tests still correctly reject (since they compute `Carbon::now() ± 61s` which IS outside the window relative to the test-now binding).
- **Committed in:** `6c357d1` (Task 2 GREEN commit — fix and test went in together).
- **Why not Rule 4:** Minor middleware-side adjustment to honour the testing framework's clock-mocking convention. The plan's `<interfaces>` block (lines 88-117) showed the microtime idiom from RESEARCH but didn't account for `Carbon::setTestNow` in the test-suite spec (`<action>` line 188 — "Use `Carbon::setTestNow(...)` for deterministic timestamps"). Production semantics unchanged; security guarantees unchanged.

**2. [Rule 3 - Blocking] Initial test using `withHeaders()` + `$this->call()` lost the HMAC headers**
- **Found during:** Task 2, first Pest run — happy path returned 401 `'missing rcon auth headers'` despite the test setting `withHeaders([X-Rcon-Timestamp, X-Rcon-Nonce, X-Rcon-Signature, Content-Type])`.
- **Issue:** Laravel's `MakesHttpRequests::withHeaders()` stores into `$defaultHeaders` but `$this->call()` does NOT merge them into the Symfony request — only the higher-level helpers (`post`, `json`, `get`, etc.) invoke `transformHeadersToServerVars()`. Request arrived at the middleware with Symfony BrowserKit defaults (`host`, `user-agent: Symfony`, `accept: text/html`, `content-type: application/x-www-form-urlencoded`) instead of our HMAC headers. Caught by adding a debug log to the middleware that dumped `$request->headers->all()`.
- **Fix:** Replaced the `withHeaders(...)` helper with a `rconServerVars()` function that pre-converts the four headers to `HTTP_X_RCON_*` + `CONTENT_TYPE` + `HTTP_ACCEPT` server vars, and passed them as the 6th arg to `$this->call(method, uri, [], [], [], $server, $body)`. The headers now reach Symfony's `Request::create` unchanged.
- **Files modified:** `apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php`.
- **Verification:** Probe via `$request->headers->all()` debug log showed the HMAC headers arriving correctly after the fix.
- **Committed in:** `6c357d1` (Task 2 GREEN commit).
- **Why not Rule 4:** Test-plumbing adjustment. The plan's `<action>` (line 188) said "Each `withHeaders(...)` call constructs the three headers explicitly" — the spirit was "set the HMAC headers explicitly per case", which the new helper does. The mechanical SQL-equivalent of "use the right API for raw-body requests" is the kind of detail planners don't normally spell out; documenting it in the SUMMARY's `tech-stack.patterns` so future raw-body gate tests skip the same pitfall.

### Auth Gates

None — middleware/test-layer plan, no external service authentication required.

### Architectural Changes (Rule 4 — required user decision)

None — both deviations are Rule 1/3 test-plumbing or middleware-side bug fixes within the plan's stated scope.

---

**Total deviations:** 2 auto-fixed (1 Rule 1 — bug, 1 Rule 3 — blocking).
**Impact on plan:** All 8 test cases turn GREEN; all `must_haves.truths` + `must_haves.artifacts` met. The Rule 1/3 fixes are paper-cuts that don't change the plan's outcomes — the security semantics, freshness window, replay TTL, signature byte-format, and alias name all match the spec exactly. The Carbon switch is the single material behavioural difference and only affects clock-mocked tests (production is unchanged).

## Issues Encountered

- **`make` not on PATH (same as plans 08-02..08-04).** CLAUDE.md §1 documents Makefile aliases as the canonical container surface, but `make` itself wasn't installed in this session's host. Resolved by invoking the underlying `docker compose exec -T web ./vendor/bin/...` commands directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Pest/Pint/PHPStan ran inside the web container).
- **PHPStan vs phpredis stubs disagree on `Redis::set` variadic form.** The Laravel-documented `Redis::set(key, val, 'EX', ttl, 'NX')` idiom routes through the `@mixin \Redis` chain and PHPStan complains about argument count + type. Used a targeted `@phpstan-ignore-next-line argument.type,arguments.count` with a multi-line comment explaining the rationale (and pointing to the real runtime signature in `vendor/.../PhpRedisConnection.php:82`).

## User Setup Required

**For local dev — set `WEB_HMAC_SECRET` in `apps/web/.env`:**
```bash
WEB_HMAC_SECRET=$(openssl rand -hex 32)
```
The Phase 8 worker (plans 08-10+) signs every outgoing POST with this secret; the web middleware verifies. Without it, `HmacVerifier::sign()` throws `InvalidArgumentException` (intentional fail-loud — T-08-05-06). The `.env.example` already commits the empty key at line 62 (added in 08-01 Wave 0); a dev who runs `cp .env.example .env` then needs to fill the value.

**For Railway production** — inject `WEB_HMAC_SECRET` via the Railway env-group (D-014). The same value MUST be set on BOTH the `web` service AND the `rcon-worker` service or every signed request will fail verification.

## Next Phase Readiness

- **Plan 08-06 (Internal RCON ingest routes) is unblocked.** Route definition will use `->middleware('rcon.signature')` exactly as the alias is registered here. The endpoint controllers receive only-verified requests; they read `$request->getContent()` for the raw body (already-verified by the middleware before they execute) and `$request->json()` from there.
- **Plan 08-09 (Filament TestConnectionAction) is unblocked.** The admin UI's "test worker connection" button can mint a probe signature via `HmacVerifier::sign($ts, $probeBody, config('rcon.hmac_secret'))` and POST it through the verified channel to confirm the end-to-end gate works without leaving the admin panel.
- **Plan 08-10 (rcon-worker outbound signer) is unblocked.** The Node worker's signing contract is byte-for-byte: `sha256(timestamp_ms_ascii + raw_body_utf8, secret)` as lowercase hex, paired with a UUIDv4 nonce and the millisecond timestamp ASCII string. Plan 08-10 will need a parity test that asserts a known-good `(timestamp, body, secret) → digest` triple round-trips between Node and PHP.
- **Plan 08-13 (Phase 8 verification) is unblocked.** Cross-tier signature contract probe: worker mints signature, web verifies; web mints signature, worker verifies (used in plan 08-09 for the admin probe).
- **No blockers.** Phase 1-7 + Phase 8 Waves 0-4 baseline preserved: **1062 PASS, 6 FAIL.** Net change from plan 08-04: **+8 PASS** (the 8 new VerifyRconSignatureTest cases), **−1 FAIL** (the Wave 0 RED stub turned GREEN). The 6 remaining failures are all Wave 0 RED stubs scheduled GREEN in plans 08-06..08-13.

## Self-Check: PASSED

Verified before finalising:

**Files created (3) — all exist:**
- `apps/web/app/Support/Hmac/HmacVerifier.php` ✓
- `apps/web/app/Http/Middleware/VerifyRconSignature.php` ✓
- `apps/web/config/rcon.php` ✓

**Files modified (2) — both staged in commits:**
- `apps/web/bootstrap/app.php` (alias added) ✓
- `apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php` (Wave 0 stub → 8 GREEN cases) ✓

**Commits (2) — all reachable via `git log --oneline -3`:**
- `0b8dbaa` feat(08-05): GREEN HMAC verifier + VerifyRconSignature middleware + rcon config ✓
- `6c357d1` test(08-05): GREEN VerifyRconSignatureTest (8 cases) + Carbon::now() clock fix ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter=VerifyRconSignatureTest` → **8 PASS** (16 assertions) ✓
- Full Phase 8 filter `pest --filter=Phase8` → **25 PASS, 6 FAIL** (all 6 remaining = Wave 0 RED stubs for plans 08-06..08-13) ✓
- Full project regression `pest` → **1062 PASS, 6 FAIL** ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` (full project) → **538 files PASS** ✓
- `php artisan config:show rcon` → all 4 keys resolve (`hmac_secret`, `freshness_window_ms=60000`, `nonce_ttl_seconds=120`, `crcon_version_pin=10.0.0`) ✓
- `php artisan route:list` → boots without errors (alias registration valid) ✓

**Behavioural probes (tinker, before tests):**
- `HmacVerifier::sign('1778760000000', '{"event":"game_start","map":"Foy"}', 'test-secret-...')` returns `d125c3f44be8e85c477d3f517fd9408ae6df5f7bfcd8942e4416bd5796a1b4eb` (deterministic lowercase hex, 64 chars) ✓
- Same triple verifies via `HmacVerifier::verify(...)` → `true` ✓
- Tampered body (single char flip) → `verify()` returns `false` ✓
- Empty secret → `HmacVerifier::sign('', '', '')` throws `InvalidArgumentException` ✓
- `Redis::set('rcon:nonce:abc', '1', 'EX', 120, 'NX')` first call returns `bool(true)`, second call returns `bool(false)` ✓ (atomic SET-if-not-exists with TTL on the wire)

**TDD Gate Compliance:** RED stub (`9ea301b`, plan 08-01 Wave 0) precedes both GREEN commits in this plan (`0b8dbaa`, `6c357d1`); RED stub verified failing at plan start; all 8 cases verified passing after Task 2.

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
