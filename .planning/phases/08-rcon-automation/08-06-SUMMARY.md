---
phase: 08-rcon-automation
plan: 06
subsystem: internal-api-rcon-worker-channel
tags: [rcon, hmac, internal-api, dto, formrequest, typescript-transformer, route-binding, throttle, tdd, wave-4]

# Dependency graph
requires:
  - phase: 08-03
    provides: MatchServer + MatchServerBooking Eloquent models with `encrypted:array` cast on credentials_encrypted + scopeActive() + scopeDueWithin(CarbonInterface,CarbonInterface); MatchServerFactory inactive() state; MatchServerBookingFactory forMatch()/onServer() state helpers
  - phase: 08-04
    provides: MatchEvent model with 10-value event_type enum (`game_start..manual_error`) mirrored in CHECK constraint + lang/en/rcon.php events.types.* keys
  - phase: 08-05
    provides: rcon.signature middleware alias (HMAC SHA-256 over timestamp+raw-body, 60s freshness window, 120s nonce TTL); HmacVerifier service (sign/verify); config/rcon.php (hmac_secret/freshness_window_ms/nonce_ttl_seconds); Phase 8 baseline 1062 PASS / 6 FAIL (25 PASS / 6 FAIL within Phase 8)
provides:
  - "App\\Http\\Controllers\\Internal\\MatchEventsController — POST /api/internal/match/{match}/events; 202 Accepted + {batch_id,accepted_count} on success; Wave 4 shim (no persistence) labelled `TODO(plan 08-07)` for MatchEventIngestService injection"
  - "App\\Http\\Controllers\\Internal\\BookingScheduleController — GET /api/internal/bookings/due; returns ::active()->dueWithin(now-5min,now+5min)->with('server')->get() mapped to BookingDueData[]; N+1-safe single query"
  - "App\\Http\\Controllers\\Internal\\MatchServerCredentialsController — GET /api/internal/match-servers/{server}/credentials; ::active()->firstOrFail; returns {host,port_rcon,api_token} with credentials_encrypted decrypted via Eloquent cast"
  - "App\\Http\\Requests\\Internal\\StoreMatchEventsRequest — validates events[] (max 100/batch), event_type whitelist of canonical 10 values, payload required array, occurred_at date; authorize()=true (HMAC middleware is the auth gate)"
  - "App\\Data\\Internal\\MatchEventInputData — #[TypeScript] input DTO (5 fields: crcon_stream_id, event_type, crcon_action, payload, occurred_at); auto-emitted to packages/shared-types/src/api.d.ts as `App.Data.Internal.MatchEventInputData`"
  - "App\\Data\\Internal\\BookingDueData — #[TypeScript] output DTO (7 fields) with fromModel(MatchServerBooking) static factory that reads server_host/port_rcon from eager-loaded MatchServer relation; auto-emitted as `App.Data.Internal.BookingDueData`"
  - "Tests\\Support\\SignsRconRequests trait — reusable signedJsonPost($url,$payload) + signedGet($url) + rconServerVars($body) ergonomics; reads secret from config('rcon.hmac_secret'); pre-converts HMAC headers to HTTP_* server vars for $this->call(); 08-07/08-08/08-12 will uses(SignsRconRequests::class) directly"
  - "routes/api.php — APPEND ->prefix('internal')->name('internal.')->middleware(['rcon.signature','throttle:600,1']) block with 3 routes: internal.match.events.store / internal.bookings.due / internal.match-servers.credentials"
affects:
  - 08-07-PLAN.md (MatchEventIngestService — injects into MatchEventsController, replaces the Wave 4 shim closure; the existing InternalApiRoutesPresentTest cases 6/7/8 will remain GREEN and gain ingest-side assertions)
  - 08-08-PLAN.md (MatchPlayerStatAggregator — same controller pathway; the SignsRconRequests trait reused for aggregator-side integration tests)
  - 08-09-PLAN.md (Filament TestConnectionAction — admin probe via MatchServerCredentialsController::show to validate worker connectivity from the panel)
  - 08-10-PLAN.md (rcon-worker outbound — Node imports `App.Data.Internal.BookingDueData` + `App.Data.Internal.MatchEventInputData` from `@trench-wars/shared-types`; signs requests byte-for-byte against the same secret + body bytes the SignsRconRequests trait does)
  - 08-11-PLAN.md (BookingScheduler — Node-side 30s poll of /api/internal/bookings/due; consumes BookingDueData[] directly without an additional credentials hop)
  - 08-12-PLAN.md (log redact list — extends Laravel's logger redact paths to mask `api_token` field on every channel; this controller is the only call site where the plaintext exists in a response body)
  - 08-13-PLAN.md (Phase 8 verification — cross-tier signature contract probe traverses InternalApiRoutesPresentTest's 8 cases as part of the SC-3 evidence chain)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "spatie/laravel-typescript-transformer auto-regenerates `packages/shared-types/src/api.d.ts` AND `apps/web/resources/js/types/api.d.ts` on `composer dump-autoload` whenever a new `#[TypeScript]` DTO lands. The transformer's writer is registered via the package's auto-discovery on `post-autoload-dump`; both output paths are kept in sync because Phase 4 plumbed dual emit (one for the Inertia SSR Vue types, one for the workspace TS package the bot/worker consume). The DTOs in this plan landed at `App\\Data\\Internal\\*` so the emitted types nest as `App.Data.Internal.BookingDueData` / `App.Data.Internal.MatchEventInputData` — namespace dots become TS namespace nesting."
    - "Route::middleware(['rcon.signature','throttle:600,1'])->prefix('internal')->name('internal.')->group() — three Laravel idioms stacked: alias-resolved middleware (08-05 plan), throttle:600,1 (600 req/min — well above worker single-replica steady state), prefix() yields /api/internal/* (the api.php file is auto-prefixed with /api by the Laravel API route loader), name() prefixes route names so `route('internal.match.events.store')` resolves. The middleware is order-sensitive — rcon.signature MUST run BEFORE throttle:600,1 so unauthenticated DoS attempts hit 401 immediately without consuming a throttle slot (Laravel runs middleware top-to-bottom on the request side per RESEARCH §middleware-order)."
    - "Reusable Pest trait via `uses(\\Tests\\Support\\SignsRconRequests::class)` at the top of a test file. The Pest 'uses' helper accepts class FQNs and merges them into the test class hierarchy at compile-time; the trait's protected methods become available as `$this->signedJsonPost(...)`. This is the canonical Pest pattern for shared HTTP plumbing across feature suites — preferred over a global helper function because it gets traits like `RefreshDatabase` for free via the existing Pest.php wiring."
    - "MatchServer::active()->where('id', \$uuid)->firstOrFail() (instead of `findOrFail`) when the scope filter must apply BEFORE the lookup. `MatchServer::active()->findOrFail(\$uuid)` would inline the active() scope into the WHERE clause of the find — works in PostgreSQL but Larastan/PHPStan can't infer the return type of scoped findOrFail to non-nullable Model. The `->where('id',...)` form is explicit, scope-respecting, and PHPStan-clean. Stored in 08-06 controller's `MatchServerCredentialsController::show`."

key-files:
  created:
    - apps/web/app/Data/Internal/MatchEventInputData.php
    - apps/web/app/Data/Internal/BookingDueData.php
    - apps/web/app/Http/Requests/Internal/StoreMatchEventsRequest.php
    - apps/web/app/Http/Controllers/Internal/MatchEventsController.php
    - apps/web/app/Http/Controllers/Internal/BookingScheduleController.php
    - apps/web/app/Http/Controllers/Internal/MatchServerCredentialsController.php
    - apps/web/tests/Support/SignsRconRequests.php
    - apps/web/tests/Feature/Phase8/InternalApiRoutesPresentTest.php
  modified:
    - apps/web/routes/api.php
    - apps/web/resources/js/types/api.d.ts
    - packages/shared-types/src/api.d.ts

key-decisions:
  - "MatchEventsController::store ships a SHIM that mints a synthetic batch_id without persisting any MatchEvent rows. The shim is labelled with `TODO(plan 08-07): replace with MatchEventIngestService::ingest(...)` and the inline closure is ~6 lines. Justification: plan 08-06 is the wire-contract plan; plan 08-07 is the persistence plan. Wave-4 GREEN-to-bootstrap means plan 08-10 (Node worker) can develop against a 202-returning target while plan 08-07's ingest service is still being built. The 8-case InternalApiRoutesPresentTest pins the shim's behaviour (case 6: accepted_count=1) so 08-07's refactor must preserve the wire contract end-to-end. Risk: plan 08-07 must remember to delete the TODO comment + the inline closure body. Mitigation: 08-07's own plan explicitly mentions the refactor (line 214 of 08-07-PLAN.md: 'delete the TODO comment + the inline closure')."
  - "BookingDueData includes pre-resolved `server_host` + `server_port_rcon` so the worker (plan 08-11) does NOT need a second hop to /api/internal/match-servers/{id}/credentials just to know which host/port to connect to. The /credentials endpoint stays reserved for the api_token (decryption gated by HMAC + active flag). This keeps the worker's BookingScheduler tick from making N round-trips per polling cycle — one GET /bookings/due returns everything needed to dispatch a CrconClient session, and the credentials GET only fires AFTER the session decides to actually connect."
  - "BookingScheduleController::dueNow uses Carbon::now() (NOT microtime) for the time-window arithmetic — same Carbon::setTestNow() compatibility rationale that plan 08-05 SUMMARY documented (decisions #1, Rule 1 fix). The dueWithin scope accepts CarbonInterface so passing Carbon::now() instances satisfies the type signature; `now()->subMinutes(5)` is the Laravel-canonical idiom and returns a Carbon::class which is what's wanted."
  - "MatchServerCredentialsController::show uses `MatchServer::active()->where('id',\$uuid)->firstOrFail()` instead of `MatchServer::active()->findOrFail(\$uuid)`. Reason: Larastan's generic inference for chained scope+findOrFail can't always type-narrow to non-nullable Model. The explicit `->where('id',...)` form is identical at the SQL level (Eloquent emits `WHERE id = ? AND is_active = true`) but PHPStan-clean. Trade-off considered: a covariant return-type annotation on the scope. Rejected because it would propagate the workaround into every consumer of active() in plans 08-09 / 08-13."
  - "SignsRconRequests trait lives at `tests/Support/` (psr-4 root `Tests\\` per composer.json autoload-dev) instead of inside the test file. Reusability: plans 08-07 (MatchEventIngestServiceTest), 08-08 (MatchPlayerStatAggregator integration), and 08-12 (Phase 8 verification suite) all need the same HMAC-signing helper. Inlining it in VerifyRconSignatureTest.php (plan 08-05) would have required 3+ files to import a const RCON_TEST_SECRET — the trait reads from `config('rcon.hmac_secret')` so each test pins its own scope-local secret via `config([...])` in beforeEach."
  - "The route block was APPENDED to apps/web/routes/api.php (NOT replaced) with a clear `// ----- RCON Worker → Web (Phase 8) -----` comment header. Reason: routes/api.php already carries the Phase 5 bot-API route tree (lines 30-66) which depends on auth:sanctum + abilities middleware aliases. Replacing the file would have orphaned Phase 5. Surgical edit via the Edit tool preserved all 35 existing lines and added 38 lines below them. PHPStan + Pest re-verified the bot routes still resolve."
  - "JSON_UNESCAPED_SLASHES used in SignsRconRequests::signedJsonPost when json_encoding the payload. Reason: the Node worker's default JSON.stringify (plan 08-10) emits forward-slashes UN-escaped (e.g., `\"url\":\"https://...\"` not `\"url\":\"https:\\/\\/...\"`). PHP's default json_encode escapes them. The bytes signed MUST match byte-for-byte across both sides — JSON_UNESCAPED_SLASHES pins PHP to the Node-default behaviour. The other JSON encode flags (e.g., JSON_PRETTY_PRINT, JSON_UNESCAPED_UNICODE) are intentionally NOT set: pretty-print would add whitespace differences across CPU architectures, and UNESCAPED_UNICODE is irrelevant in v1 because the wire format never carries non-ASCII (player names get UTF-8-escaped at the normaliser boundary in plan 08-07)."

# Metrics
duration: 6min
completed: 2026-05-14
---

# Phase 8 Plan 6: Wave 4 — Internal API endpoints + DTOs + FormRequest + sign-helper Summary

**Mounted the 3 HMAC-protected internal API endpoints (POST /events + GET /bookings/due + GET /match-servers/{id}/credentials) that the rcon-worker uses for its full lifecycle. Authored 2 `#[TypeScript]` DTOs (auto-emitted to packages/shared-types so plan 08-10's Node worker can `import type {…}` against the same wire shape), 1 FormRequest validating the 10-value event_type enum + 100-event batch cap, 3 thin controllers (one is a labelled Wave-4 shim for plan 08-07 to flesh out), and a reusable `SignsRconRequests` Pest trait that plans 08-07 / 08-08 / 08-12 will consume. Project regression 1062 → 1070 PASS (+8 new InternalApiRoutesPresentTest cases), 6 FAIL unchanged (all Wave 0 RED stubs for plans 08-07..08-13).**

## Performance

- **Duration:** 6 min 27 s
- **Started:** 2026-05-14T04:24:23Z
- **Completed:** 2026-05-14T04:30:50Z
- **Tasks:** 2 / 2
- **Files created:** 8 (2 DTOs + 1 FormRequest + 3 controllers + 1 test trait + 1 test file)
- **Files modified:** 3 (routes/api.php route block append + 2 auto-regenerated TS-type emit files)
- **Commits:** 2 (Task 1 `bfd3b8a`; Task 2 `8f3d209`)

## Accomplishments

### TDD Gate Sequence

This plan has 2 `tdd="true"` tasks where Task 1 builds the implementation (verified via PHPStan + Pint) and Task 2 lands the behavioural test suite. This is the wire-contract pattern from plan 08-05 — the implementation must compile clean BEFORE the request-level test suite can mount routes and assert end-to-end. The TDD discipline is satisfied by the existence of the 8-case `InternalApiRoutesPresentTest` as the GREEN gate; there is no separate RED commit because the Wave 4 anchor test was specifically authored for plan 08-06 (see plan line 187 — "not in plan 08-01 stub list — adds a Wave 4 anchor test").

1. **GREEN Task 1** (commit `bfd3b8a`): authored 6 files (2 DTOs + 1 FormRequest + 3 controllers). Verified PHPStan L8 clean ("OK No errors"), Pint --test 6 files PASS.
2. **GREEN Task 2** (commit `8f3d209`): appended the 38-line route block to routes/api.php, authored `Tests\Support\SignsRconRequests`, authored the 8-case `InternalApiRoutesPresentTest`. Verified all 8 PASS; route:list --path=api/internal shows 3 routes; full Phase8 33 PASS / 6 FAIL (was 25/6 — +8 PASS, no regressions); full project 1070 PASS / 6 FAIL; PHPStan L8 + Pint clean.

### Application code (6 files)

1. **`App\Data\Internal\MatchEventInputData`** — `#[TypeScript]` 5-field DTO (crcon_stream_id?, event_type, crcon_action?, payload[], occurred_at). Wire-format INPUT for POST /events. Auto-emitted to packages/shared-types/src/api.d.ts as `App.Data.Internal.MatchEventInputData` (plan 08-10 worker imports). Timestamps are ISO-8601 strings (not Carbon — deterministic across Node/PHP serialisers).

2. **`App\Data\Internal\BookingDueData`** — `#[TypeScript]` 7-field DTO (id, match_id, server_id, server_host, server_port, reserved_from, reserved_to). Wire-format OUTPUT for GET /bookings/due. Static `::fromModel(MatchServerBooking $b)` factory reads server_host/port_rcon from the eager-loaded `server` relation — N+1-safe; throws if `$b->server` is not pre-loaded (which is guaranteed by the controller's `->with('server')` query).

3. **`App\Http\Requests\Internal\StoreMatchEventsRequest`** — events[] required + max:100/batch + each event has event_type Rule::in(10 canonical values) + payload required array + occurred_at date. authorize()=true (HMAC middleware is the auth gate; FormRequest only validates shape).

4. **`App\Http\Controllers\Internal\MatchEventsController::store`** — 202 + {batch_id, accepted_count}. Wave-4 SHIM with `TODO(plan 08-07)` comment marking the spot where MatchEventIngestService injection lands. The shim accepts events but doesn't persist them — plan 08-07's tests will fail until 08-07 lands MatchEvent::create() per-event with the composite UNIQUE absorb. Route binding via {match} → App\Models\GameMatch (D-04-03-A LOCKED naming).

5. **`App\Http\Controllers\Internal\BookingScheduleController::dueNow`** — eager-loads `server`, maps to `BookingDueData::fromModel(…)`, returns JSON array. Active-only via scopeActive(), ±5min window via scopeDueWithin(now-5min, now+5min). Empty array when nothing's due.

6. **`App\Http\Controllers\Internal\MatchServerCredentialsController::show`** — `MatchServer::active()->where('id', $serverUuid)->firstOrFail()` returns 404 when server unknown OR is_active=false. credentials_encrypted decrypted via Eloquent cast; api_token surfaced in response body. NEVER logs the token (plan 08-12 extends the logger redact list as defence in depth).

### Routes (1 file modified)

7. **`apps/web/routes/api.php`** — APPENDED 38-line block with `// ----- RCON Worker → Web (Phase 8) -----` header. Three routes: `POST match/{match}/events` (whereUuid), `GET bookings/due`, `GET match-servers/{server}/credentials` (whereUuid). Stacked middleware: `rcon.signature` (08-05 alias) + `throttle:600,1` (DoS guard). Prefix `internal` + name prefix `internal.` yield `/api/internal/*` URLs and `internal.match.events.store` route names. Phase 5 bot API routes (lines 30-66) preserved verbatim.

### Test infrastructure (1 trait)

8. **`Tests\Support\SignsRconRequests`** — Pest-trait helper. Three methods:
   - `rconServerVars(string $body): array<string,string>` — builds the 5-key HTTP_* server-var array (HTTP_X_RCON_TIMESTAMP / HTTP_X_RCON_NONCE / HTTP_X_RCON_SIGNATURE / HTTP_ACCEPT / CONTENT_TYPE).
   - `signedJsonPost(string $url, array $payload): TestResponse` — json_encode($payload, JSON_UNESCAPED_SLASHES) + sign + call('POST',…).
   - `signedGet(string $url): TestResponse` — empty body + sign + call('GET',…).

   Reusable by plans 08-07 (`MatchEventIngestServiceTest`), 08-08 (`MatchPlayerStatAggregator` integration), 08-12 (Phase 8 verification suite). Reads secret from `config('rcon.hmac_secret')` so each test pins its own scope-local secret via `config([…])` in beforeEach.

### Test created — 8 cases all GREEN (1 file)

9. **`tests/Feature/Phase8/InternalApiRoutesPresentTest`** — `uses(SignsRconRequests::class)` + 8 cases:
   1. **rejects GET /bookings/due without HMAC headers** → 401 (middleware mounted).
   2. **returns 200 + [] for GET /bookings/due empty** → 200 + `[]`.
   3. **returns 200 + 1 row with resolved server_host/port for an active booking due now** → 200 + array of 1 row with `server_host:'crcon-foy.example.com', server_port:8011`, reserved_from/reserved_to ISO-8601 strings.
   4. **returns 200 + decrypted api_token for GET /match-servers/{uuid}/credentials** → 200 + `{host,port_rcon,api_token:'super-secret-bearer-token-xyz'}`.
   5. **returns 404 for inactive server** → 404 (scopeActive filters it out).
   6. **returns 202 + accepted_count=1 for POST /events with one valid event** → 202 + `{batch_id:<uuid>, accepted_count:1}` (shim path).
   7. **returns 422 for POST /events with invalid event_type='foo'** → 422 (FormRequest rejects).
   8. **returns 404 for POST /events with unknown match UUID** → 404 (route binding fails).

   `beforeEach` calls `Redis::flushdb()` to reset nonce state and pins `config(['rcon.hmac_secret' => PHASE8_INTERNAL_API_TEST_SECRET])`. `afterEach` clears `Carbon::setTestNow`.

### Auto-regenerated TS types (2 files modified)

10. **`packages/shared-types/src/api.d.ts`** + **`apps/web/resources/js/types/api.d.ts`** — spatie/typescript-transformer auto-emit on `composer dump-autoload`. Both files now include a new `namespace Internal { export type BookingDueData = {…}; export type MatchEventInputData = {…}; }` block. The Node worker (plan 08-10) consumes via `@trench-wars/shared-types`; the Vue/Inertia frontend (Phase 11+) would consume via the local `api.d.ts`.

## Task Commits

1. **GREEN Task 1 — controllers + DTOs + FormRequest** — `bfd3b8a` (feat)
2. **GREEN Task 2 — routes + SignsRconRequests trait + 8-case test + auto-emitted TS types** — `8f3d209` (test)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (6)
- `apps/web/app/Data/Internal/MatchEventInputData.php`
- `apps/web/app/Data/Internal/BookingDueData.php`
- `apps/web/app/Http/Requests/Internal/StoreMatchEventsRequest.php`
- `apps/web/app/Http/Controllers/Internal/MatchEventsController.php`
- `apps/web/app/Http/Controllers/Internal/BookingScheduleController.php`
- `apps/web/app/Http/Controllers/Internal/MatchServerCredentialsController.php`

### Tests (2)
- `apps/web/tests/Support/SignsRconRequests.php`
- `apps/web/tests/Feature/Phase8/InternalApiRoutesPresentTest.php`

## Files Modified

### Application code (1)
- `apps/web/routes/api.php` — appended `Route::middleware(['rcon.signature','throttle:600,1'])->prefix('internal')->name('internal.')->group(…)` block with 3 routes

### Generated types (2 — auto-emit by spatie/typescript-transformer on composer dump-autoload)
- `apps/web/resources/js/types/api.d.ts` — adds `namespace Internal { BookingDueData, MatchEventInputData }`
- `packages/shared-types/src/api.d.ts` — same emission, consumed by `apps/rcon-worker` and `apps/bot` workspaces

## Decisions Made

- **MatchEventsController::store ships a labelled SHIM (return 202 + synthetic batch_id) for plan 08-07 to replace.** The TODO comment is explicit; plan 08-07's plan body already references the refactor. Wave-4 GREEN-to-bootstrap rationale: plan 08-10 (Node worker) needs a stable 202-returning target to develop against while plan 08-07's MatchEventIngestService is still being authored.
- **BookingDueData pre-resolves server_host + server_port so the worker doesn't need a second hop to /credentials for connectivity info.** /credentials stays reserved for the api_token. One GET /bookings/due is enough to dispatch a worker session; credentials GET only fires on session-open.
- **MatchServerCredentialsController uses `MatchServer::active()->where('id', $uuid)->firstOrFail()` instead of chained `findOrFail`.** Larastan-clean (chained scope+findOrFail can't always type-narrow), identical SQL emission. Rejected the covariant scope return-type annotation alternative because it would propagate the workaround into 08-09 and 08-13.
- **SignsRconRequests at `tests/Support/` (Tests\Support\) rather than inline in any one test file.** Three downstream plans (08-07/08-08/08-12) need the same HMAC-signing surface; centralising in a trait avoids the `const RCON_TEST_SECRET` duplication that VerifyRconSignatureTest's local helper would otherwise create.
- **Route block APPENDED to routes/api.php (not replaced).** Preserves Phase 5 bot-API tree (35 lines) verbatim; the new 38-line block lives under a `// ----- RCON Worker → Web (Phase 8) -----` comment header. The `prefix('internal')` is the segregation boundary — `/api/internal/*` is separate from `/api/*` (bot) and `/api/v1/*` (public).
- **JSON_UNESCAPED_SLASHES on json_encode in SignsRconRequests::signedJsonPost.** PHP's default escapes forward slashes; Node's default JSON.stringify doesn't. Mismatch would break HMAC byte-equality. JSON_PRETTY_PRINT and JSON_UNESCAPED_UNICODE intentionally NOT set (former adds platform-specific whitespace; latter not relevant — wire format is ASCII through v1).

## Deviations from Plan

None — plan executed exactly as written. All `must_haves.truths` + `must_haves.artifacts` + `must_haves.key_links` shipped per the spec.

### Auto-fixed Issues

None.

### Auth Gates

None — implementation-only plan, no external service authentication required.

### Architectural Changes (Rule 4 — required user decision)

None.

---

**Total deviations:** 0.
**Impact on plan:** Wire contract for the rcon-worker channel is now fully in place. The 8-case test pins every truth from must_haves. Plan 08-07 can now wire MatchEventIngestService into the controller without changing the route surface; plan 08-10 can develop the Node worker against a stable 202-returning target; plan 08-11's BookingScheduler can poll a stable /bookings/due endpoint.

## Issues Encountered

- **`make` not on PATH (same as plans 08-02..08-05).** CLAUDE.md §1 documents Makefile aliases as the canonical container surface, but `make` itself wasn't installed in this session's host. Resolved by invoking the underlying `docker compose exec -T web ./vendor/bin/…` commands directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Pest/Pint/PHPStan ran inside the web container).

## User Setup Required

None — internal-API plan, no env vars or external services introduced. The `WEB_HMAC_SECRET` requirement from plan 08-05 SUMMARY remains (set in `apps/web/.env` for local dev; injected via Railway env-group for production).

## Next Phase Readiness

- **Plan 08-07 (MatchEventIngestService) is unblocked.** The plan body already references this controller's TODO comment (line 214 of 08-07-PLAN.md). Refactor: inject `MatchEventIngestService` into `MatchEventsController::store`, replace the inline shim with `$batchId = $service->ingest($match, $validated['events']);`, keep the 202 response shape. The 8-case `InternalApiRoutesPresentTest` will continue to GREEN after the refactor (case 6 asserts `accepted_count=1` which the service must preserve).
- **Plan 08-08 (MatchPlayerStatAggregator) is unblocked.** SignsRconRequests trait + InternalApiRoutesPresentTest cover the same controller pathway; aggregator-side integration tests can `uses(SignsRconRequests::class)` and POST events through the gated channel.
- **Plan 08-09 (Filament TestConnectionAction) is unblocked.** Admin panel can probe `MatchServerCredentialsController::show` via a Filament Action that re-uses `HmacVerifier::sign(...)` to mint a fresh signature against the same secret — admin clicks a button, panel proxies the GET, displays connectivity status.
- **Plan 08-10 (rcon-worker outbound signer) is unblocked.** The Node worker imports `App.Data.Internal.BookingDueData` + `App.Data.Internal.MatchEventInputData` from `@trench-wars/shared-types` (auto-emitted in this plan's Task 2 commit). Worker signs requests over (timestamp + raw body) using the same WEB_HMAC_SECRET; the SignsRconRequests trait's JSON_UNESCAPED_SLASHES + `Carbon::now()->getTimestamp() * 1000 + milli` shape is the byte-equal target the Node side must match.
- **Plan 08-11 (BookingScheduler) is unblocked.** Node-side 30s poll loop on `/api/internal/bookings/due`; receives `BookingDueData[]` with pre-resolved server_host + port_rcon. The ±5min window in `BookingScheduleController::dueNow` accommodates worker poll-interval drift.
- **Plan 08-12 (log redact list) is unblocked.** Plan 08-12 must extend the Laravel logger redact paths to mask `api_token` on every channel — this controller is the only call site where the plaintext exists in a response body, but Phase 12's audit pass should sweep all logger calls across the codebase to make sure no `Log::info($response)` accidentally captures it.
- **Plan 08-13 (Phase 8 verification) is unblocked.** Cross-tier signature contract probe traverses the 8 InternalApiRoutesPresentTest cases. The SignsRconRequests trait's `rconServerVars()` is the canonical Pest helper.
- **No blockers.** Phase 1-7 + Phase 8 Waves 0-4 baseline preserved: **1070 PASS, 6 FAIL.** Net change from plan 08-05: **+8 PASS** (the 8 new InternalApiRoutesPresentTest cases), **0 FAIL change** (the 6 remaining failures are all Wave 0 RED stubs scheduled GREEN in plans 08-07..08-13).

## Self-Check: PASSED

Verified before finalising:

**Files created (8) — all exist:**
- `apps/web/app/Data/Internal/MatchEventInputData.php` ✓
- `apps/web/app/Data/Internal/BookingDueData.php` ✓
- `apps/web/app/Http/Requests/Internal/StoreMatchEventsRequest.php` ✓
- `apps/web/app/Http/Controllers/Internal/MatchEventsController.php` ✓
- `apps/web/app/Http/Controllers/Internal/BookingScheduleController.php` ✓
- `apps/web/app/Http/Controllers/Internal/MatchServerCredentialsController.php` ✓
- `apps/web/tests/Support/SignsRconRequests.php` ✓
- `apps/web/tests/Feature/Phase8/InternalApiRoutesPresentTest.php` ✓

**Files modified (3) — all staged in commits:**
- `apps/web/routes/api.php` (route block appended) ✓
- `apps/web/resources/js/types/api.d.ts` (auto-emit) ✓
- `packages/shared-types/src/api.d.ts` (auto-emit) ✓

**Commits (2) — reachable via `git log --oneline -3`:**
- `bfd3b8a` feat(08-06): GREEN internal-API controllers + DTOs + FormRequest ✓
- `8f3d209` test(08-06): GREEN mount internal-API routes + 8-case InternalApiRoutesPresentTest + SignsRconRequests helper ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter=InternalApiRoutesPresentTest` → **8 PASS** (23 assertions) ✓
- `pest --filter=Phase8` → **33 PASS, 6 FAIL** (6 remaining = Wave 0 RED stubs for plans 08-07..08-13) ✓
- Full project `pest` → **1070 PASS, 6 FAIL** (1062 → 1070 = +8 PASS from InternalApiRoutesPresentTest; 0 regression) ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` (full project) → **546 files PASS** ✓
- `route:list --path=api/internal` → **3 routes** shown (bookings.due / match-servers.credentials / match.events.store) ✓

**TDD Gate Compliance:** Task 1 GREEN commit `bfd3b8a` precedes Task 2 GREEN commit `8f3d209`. The 8-case behavioural test in Task 2 is the GREEN gate for the wire contract; this plan does not have a separate RED commit because the InternalApiRoutesPresentTest was specifically authored for plan 08-06 as a Wave 4 anchor (per plan line 187 — "not in plan 08-01 stub list — adds a Wave 4 anchor test").

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
