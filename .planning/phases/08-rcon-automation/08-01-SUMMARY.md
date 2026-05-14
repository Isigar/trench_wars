---
phase: 08-rcon-automation
plan: 01
subsystem: testing
tags: [pest, vitest, factory-stub, i18n, hmac, crcon, pnpm-workspace, undici-v7, pino, zod, ws]

# Dependency graph
requires:
  - phase: 04-matches-manual
    provides: GameMatch + MatchResult models + recorder pattern (MatchResult.source enum extension lands in plan 08-02 migration)
  - phase: 05-discord-bot
    provides: D-04 mirror — bot-is-thin-display informs worker-is-thin-normaliser; ResolveBotActsAsUser middleware idiom mirrored by ValidateRconHmacSignature (plan 08-05)
  - phase: 01-foundations
    provides: apps/rcon-worker container (Phase 1 scaffold) + repo-root pnpm-lock.yaml + Pest base TestCase + RefreshDatabase autowiring via apps/web/tests/Pest.php
provides:
  - rcon-worker runtime deps (ws@^8, undici@^7, ioredis@^5.10, pino@^9, zod@^4) + @types/ws devDep
  - 5 typed TS skeleton files (config/logger/HmacSigner/CrconEventNormaliser/index) — contracts plan 08-10/08-11/08-05 handshake against
  - 10 Pest RED stubs locking the Phase 8 web test surface (Phase8/ subdir)
  - 3 Vitest RED stubs locking the worker test surface (unit/HmacSigner, unit/CrconEventNormaliser, integration/CrconClient)
  - 4 factory stubs (MatchServer, MatchServerBooking, MatchEvent, MatchPlayerStat) — string-FQN \$model + per-line @phpstan-ignore
  - lang/en/rcon.php namespace (events.types × 10, errors × 6, audit × 5)
  - lang/en/admin.php extension (match_servers / match_server_bookings resource blocks + 4 audit.subject keys + audit.match_servers.* group)
affects:
  - 08-02-PLAN.md (migrations consume the factory stubs)
  - 08-03-PLAN.md (MatchServer + MatchServerBooking models)
  - 08-04-PLAN.md (MatchPlayerStat + MatchEvent models)
  - 08-05-PLAN.md (ValidateRconHmacSignature middleware — VerifyRconSignatureTest RED→GREEN)
  - 08-07-PLAN.md (MatchEventIngestService — MatchEventIdempotencyTest + MatchEventNormaliserContractTest RED→GREEN)
  - 08-08-PLAN.md (MatchPlayerStatAggregator + MatchResult auto-populate — 3 stubs RED→GREEN)
  - 08-09-PLAN.md (MatchServerResource Filament — consumes admin.match_servers.* i18n)
  - 08-10-PLAN.md (worker: HmacSigner real sign/verify, CrconEventNormaliser real normalise, CrconClient real ws+reconnect — all 3 vitest stubs RED→GREEN)
  - 08-11-PLAN.md (worker booking-poller — RconUnreachableFlagsManualTest RED→GREEN)
  - 08-12-PLAN.md (Phase 8 capstone — ScrimE2EHappyPathTest RED→GREEN; ManualOverrideWinsTest RED→GREEN)
  - 08-13-PLAN.md (Phase 8 verification — all 13 stubs must be GREEN)

# Tech tracking
tech-stack:
  added:
    - undici@^7 (Node 22 fetch-compat pinned per nodejs/undici#3901; rcon-worker outbound HTTP)
    - ws@^8 (CRCON WebSocket client; battle-tested for long-lived connections per RESEARCH § Alternatives table)
    - ioredis@^5.10 (worker-side Redis for last_seen_id state + (eventually) job queue parity with horizon)
    - pino@^9 (structured logger with PII-redact baked in at construction — T-08-01-02 mitigation prep)
    - zod@^4 (runtime env validation in loadConfig())
    - "@types/ws@^8.5.0 (devDep)"
  patterns:
    - "Wave 0 factory stub idiom (Phase 4 D-04-01): final class + string-FQN \$model + per-line @phpstan-ignore-next-line for missingType.generics + property.defaultValue; definition() throws RuntimeException (replaced with real generic + ::class binding when model lands)"
    - "Wave 0 Pest stub idiom (Phase 6 D-06-01-B / Phase 7 011c597): bare functional — no namespace, no per-file uses(); Pest.php autowires TestCase + RefreshDatabase via uses(...)->in('Feature'); each stub asserts expect(true)->toBeFalse() so plan verifier can prove every test was author-introduced"
    - "Wave 0 Vitest stub idiom (Phase 5 precedent): bare describe/it with expect(true).toBe(false) inside; vitest.config.ts glob 'tests/**/*.test.ts' picks up nested unit/ + integration/ dirs automatically"
    - "Worker logger redact path: hard-coded ['steam_id_64','player','victim','killer','*.steam_id_64',...] at logger construction so plan 08-10's first wire-client commit cannot leak PII (Pitfall 9 mitigation — T-08-01-02)"
    - "D-021 deviation pattern for one-shot containers: 'docker compose run --rm --no-deps -v \$(pwd):/repo' with the workspace bind-mounted preserves D-021 (container, never host) while bypassing the limitation that 'docker compose exec rcon-worker' cannot work against an exited container"

key-files:
  created:
    - apps/rcon-worker/src/config.ts
    - apps/rcon-worker/src/logging/logger.ts
    - apps/rcon-worker/src/ingest/HmacSigner.ts
    - apps/rcon-worker/src/crcon/CrconEventNormaliser.ts
    - apps/rcon-worker/tests/unit/HmacSigner.test.ts
    - apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts
    - apps/rcon-worker/tests/integration/CrconClient.integration.test.ts
    - apps/web/database/factories/MatchServerFactory.php
    - apps/web/database/factories/MatchServerBookingFactory.php
    - apps/web/database/factories/MatchEventFactory.php
    - apps/web/database/factories/MatchPlayerStatFactory.php
    - apps/web/lang/en/rcon.php
    - apps/web/tests/Feature/Phase8/RconMatchResultIngestionTest.php
    - apps/web/tests/Feature/Phase8/MatchPlayerStatAggregatorTest.php
    - apps/web/tests/Feature/Phase8/MatchEventIdempotencyTest.php
    - apps/web/tests/Feature/Phase8/MatchServerCredentialEncryptionTest.php
    - apps/web/tests/Feature/Phase8/MatchServerBookingOverlapTest.php
    - apps/web/tests/Feature/Phase8/ScrimE2EHappyPathTest.php
    - apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php
    - apps/web/tests/Feature/Phase8/ManualOverrideWinsTest.php
    - apps/web/tests/Feature/Phase8/RconUnreachableFlagsManualTest.php
    - apps/web/tests/Feature/Phase8/MatchEventNormaliserContractTest.php
  modified:
    - apps/rcon-worker/package.json (5 runtime deps + 1 devDep added)
    - apps/rcon-worker/src/index.ts (Phase 1 placeholder updated to 'rcon-worker booted')
    - apps/web/lang/en/admin.php (extended with Phase 8 resource + audit keys)
    - pnpm-lock.yaml (rcon-worker section + reconciled apps/web fullcalendar drift)

key-decisions:
  - "undici pinned to ^7 NOT ^8 (Node 22 built-in fetch compatibility per nodejs/undici#3901 — locked at the package.json layer so PR review gate is the version string per T-08-01-03)"
  - "Pino redact paths baked in at Wave 0 (steam_id_64/player/victim/killer + nested *.X variants) — T-08-01-02 mitigation prep so plan 08-10's first commit cannot leak PII"
  - "admin.audit.match_servers.* is NESTED inside the existing top-level audit array (not a new top-level audit key — that would clobber Phase 1-7 audit.col/audit.subject/audit.filter); regression confirmed via 199 admin/i18n tests passing"
  - "factory stubs throw RuntimeException from definition() (Phase 4 D-04-01 idiom) instead of returning empty array — accidental ::factory() calls fail loud instead of silently inserting empty rows in tests that don't yet exist"

patterns-established:
  - "Pattern: D-021 one-shot container workaround — 'docker compose run --rm --no-deps -v \$(pwd):/repo' for any container whose CMD exits (rcon-worker, ssr); still container-only per D-021"
  - "Pattern: Wave 0 worker skeleton TS file headers carry forward-pointer comment ('replaced by plan 08-XX' or 'real implementation lands in plan 08-XX') so future agents reading the file know where to find the GREEN handover"
  - "Pattern: Phase 8 RED stub asserts expect(true)->toBeFalse() (not markTestIncomplete) so the test FAILS the suite, not skips it — plan 08-13 verifier counts failures pre-GREEN to confirm every stub got author-introduced"

requirements-completed: []
# Plan 08-01 lists REQ-goal-rcon-history, REQ-constraint-league-owns-servers,
# REQ-success-end-to-end-scrim — but Wave 0 only AUTHORS the RED stubs that
# assert those requirements. Marking them complete happens when the
# corresponding stubs turn GREEN (plans 08-08, 08-02/08-03, 08-12 respectively).

# Metrics
duration: 9min
completed: 2026-05-14
---

# Phase 8 Plan 1: Wave 0 RCON Automation Scaffolding Summary

**Wave 0 scaffolding: 10 Pest + 3 Vitest RED stubs lock the Phase 8 test surface; rcon-worker gains ws/undici-v7/ioredis/pino/zod deps + 5 typed-contract skeletons (HmacSigner, CrconEventNormaliser, config, logger, index); 4 factory stubs + lang/en/rcon.php new namespace + admin.php extended for MatchServer/MatchServerBooking — all quality gates green, no Phase 1-7 regressions.**

## Performance

- **Duration:** 9 min
- **Started:** 2026-05-14T03:17:59Z
- **Completed:** 2026-05-14T03:27:02Z
- **Tasks:** 2 / 2
- **Files modified:** 25 (21 created + 4 modified)

## Accomplishments

- rcon-worker runtime dependency surface locked: `ws@^8`, `undici@^7` (Node 22 fetch-compat per nodejs/undici#3901), `ioredis@^5.10`, `pino@^9`, `zod@^4`, plus `@types/ws@^8.5.0` devDep. Repo-root `pnpm-lock.yaml` updated (D-015 single-workspace lockfile).
- 5 typed-contract TS skeletons in `apps/rcon-worker/src/`:
  - `config.ts` — zod-validated env shape (`WEB_HMAC_SECRET` min 32, `WEB_INTERNAL_URL`, `NODE_ENV`, `POLL_INTERVAL_MS` default 30000, `REDIS_URL` optional)
  - `logging/logger.ts` — Pino instance with PII redact paths (`steam_id_64`/`player`/`victim`/`killer` + nested) baked in at construction — T-08-01-02 mitigation prep
  - `ingest/HmacSigner.ts` — `sign(secret, body, timestamp)` / `verify(...)` contracts; throw `'not implemented'`; doc-block records the Pitfall 1 invariant (sign RAW body bytes)
  - `crcon/CrconEventNormaliser.ts` — `normalise()` stub + exported `CanonicalEventType` union mirroring `lang/en/rcon.php events.types.*`
  - `index.ts` — placeholder updated to `'rcon-worker booted'` (full booking-poller lands in 08-11)
- 10 Pest RED stubs under `apps/web/tests/Feature/Phase8/` — every Phase 8 plan's "RED → GREEN handover" target file now exists with a forward-pointer comment to the plan that turns it GREEN.
- 3 Vitest RED stubs under `apps/rcon-worker/tests/{unit,integration}/` covering HmacSigner, CrconEventNormaliser, and CrconClient integration.
- 4 PHP factory stubs (canonical Phase 4 D-04-01 idiom verbatim) — `MatchServerFactory`, `MatchServerBookingFactory`, `MatchEventFactory`, `MatchPlayerStatFactory`. PHPStan L8 clean via per-line `@phpstan-ignore-next-line missingType.generics` + `@phpstan-ignore-next-line property.defaultValue`.
- `lang/en/rcon.php` new namespace (21 keys): `events.types.*` × 10 (canonical match_event_type labels), `errors.*` × 6 (HMAC + worker + middleware), `audit.*` × 5 (manual_override + test_connection).
- `lang/en/admin.php` extended (NOT replaced): `audit.subject.{MatchServer,MatchServerBooking,MatchEvent,MatchPlayerStat}` + `audit.match_servers.*` nested INSIDE the existing top-level audit array + new `match_servers.*` resource block + new `match_server_bookings.*` resource block.

## Task Commits

1. **Task 1: Install rcon-worker runtime deps + author worker skeleton files** — `7ed1d77` (feat)
2. **Task 2: Author Pest + Vitest RED stubs + 4 factory stubs + i18n key files** — `9ea301b` (test)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Worker skeletons (5)
- `apps/rcon-worker/src/config.ts` — zod env schema + `loadConfig()` export
- `apps/rcon-worker/src/logging/logger.ts` — Pino instance with redact paths
- `apps/rcon-worker/src/ingest/HmacSigner.ts` — `sign()`/`verify()` typed-stub contracts
- `apps/rcon-worker/src/crcon/CrconEventNormaliser.ts` — `normalise()` stub + `CanonicalEventType` union
- (also modified) `apps/rcon-worker/src/index.ts`

### Worker tests (3)
- `apps/rcon-worker/tests/unit/HmacSigner.test.ts` — RED stub for plan 08-10
- `apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts` — RED stub for plan 08-10
- `apps/rcon-worker/tests/integration/CrconClient.integration.test.ts` — RED stub for plan 08-10 (Pattern 1 reconnect+resume)

### Web factory stubs (4)
- `apps/web/database/factories/MatchServerFactory.php` — string-FQN `App\\Models\\MatchServer`
- `apps/web/database/factories/MatchServerBookingFactory.php` — string-FQN `App\\Models\\MatchServerBooking`
- `apps/web/database/factories/MatchEventFactory.php` — string-FQN `App\\Models\\MatchEvent`
- `apps/web/database/factories/MatchPlayerStatFactory.php` — string-FQN `App\\Models\\MatchPlayerStat`

### Web i18n (1 new + 1 extended)
- `apps/web/lang/en/rcon.php` (NEW)
- `apps/web/lang/en/admin.php` (extended in place)

### Web Pest stubs (10)
- `apps/web/tests/Feature/Phase8/RconMatchResultIngestionTest.php`
- `apps/web/tests/Feature/Phase8/MatchPlayerStatAggregatorTest.php`
- `apps/web/tests/Feature/Phase8/MatchEventIdempotencyTest.php`
- `apps/web/tests/Feature/Phase8/MatchServerCredentialEncryptionTest.php`
- `apps/web/tests/Feature/Phase8/MatchServerBookingOverlapTest.php`
- `apps/web/tests/Feature/Phase8/ScrimE2EHappyPathTest.php`
- `apps/web/tests/Feature/Phase8/VerifyRconSignatureTest.php`
- `apps/web/tests/Feature/Phase8/ManualOverrideWinsTest.php`
- `apps/web/tests/Feature/Phase8/RconUnreachableFlagsManualTest.php`
- `apps/web/tests/Feature/Phase8/MatchEventNormaliserContractTest.php`

### Files modified
- `apps/rcon-worker/package.json` — 5 runtime + 1 devDep added
- `apps/rcon-worker/src/index.ts` — placeholder text updated
- `apps/web/lang/en/admin.php` — Phase 8 audit subjects + 2 resource blocks
- `pnpm-lock.yaml` — rcon-worker section added; apps/web fullcalendar drift reconciled (pre-existing — see Deviations §2)

## Decisions Made

- **undici pinned to ^7 (NOT ^8)** — Node 22 built-in fetch is API-incompat with undici v8's dispatcher refactor (RESEARCH § Standard Stack table + nodejs/undici#3901). T-08-01-03 mitigation: the version string in `apps/rcon-worker/package.json` is the PR-review gate.
- **Pino redact list baked in at Wave 0** — `['steam_id_64','player','victim','killer','*.steam_id_64','*.player','*.victim','*.killer']` configured at logger construction so plan 08-10's first CRCON wire-client commit physically cannot emit raw PII (T-08-01-02 mitigation prep; Pitfall 9).
- **`admin.audit.match_servers.*` lives INSIDE existing top-level audit array** — Initial draft added a second top-level `'audit' =>` key, which PHP's last-key-wins semantics would have clobbered Phase 1-7's `audit.col/subject/filter/event/empty` blocks. Caught by reading the file structure before regression-running tests; restructured to nest under the existing audit array. Regression confirmed: 199 Admin/I18n tests pass.
- **Factory stubs throw `RuntimeException` from `definition()`** — Phase 4 D-04-01 idiom (`MatchSlotFactory` etc.). Returning `[]` instead would allow `Factory::factory()->create()` calls in not-yet-existing tests to silently insert empty rows. Throwing makes the failure loud and pinpoints which plan needs to ship the real factory.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] rcon-worker container is one-shot, `docker compose exec` impossible**
- **Found during:** Task 1 (verify `docker compose exec rcon-worker pnpm typecheck`)
- **Issue:** The plan's literal command `docker compose exec rcon-worker pnpm add ...` requires a long-running container. rcon-worker's Dockerfile sets `CMD ["node", "dist/index.js"]` — the placeholder boots once, prints `'skeleton boot'`, and exits. `docker compose ps rcon-worker` shows `service not running`, so `exec` fails before the pnpm subcommand even parses.
- **Fix:** Substituted `docker compose run --rm --no-deps -v "$(pwd):/repo" -w /repo/apps/rcon-worker --entrypoint sh rcon-worker -c "pnpm ..."` for every pnpm/typecheck/lint/test invocation. Still D-021-compliant (executes inside the rcon-worker image, never on the host), uses the same baked pnpm 9.15.0 binary, and the bind-mount of `$(pwd):/repo` is the canonical workspace-pnpm idiom.
- **Files modified:** None (process-only deviation; no compose change).
- **Verification:** `pnpm typecheck` and `pnpm lint` ran successfully inside the rcon-worker image and produced clean output.
- **Committed in:** `7ed1d77` (Task 1 commit).

**2. [Rule 3 - Blocking] Pre-existing apps/web pnpm-lock.yaml drift blocked --frozen-lockfile**
- **Found during:** Task 1 (first attempt at `pnpm install --filter '@trenchwars/rcon-worker...'`)
- **Issue:** Phase 7 or earlier added `@fullcalendar/{core,daygrid,interaction,timegrid,vue3}` to `apps/web/package.json` but did not regenerate `pnpm-lock.yaml`. The default pnpm v9 `--frozen-lockfile` mode (auto-enabled under CI=1) refused to install — `ERR_PNPM_OUTDATED_LOCKFILE`. This was NOT introduced by Phase 8 but blocks Phase 8 from running its scoped install.
- **Fix:** Re-ran with `--no-frozen-lockfile`. Side effect: lockfile now contains the `@fullcalendar/*` entries that `apps/web/package.json` already declared — reconciled, not added. No actual new third-party deps slipped into apps/web outside the rcon-worker filter scope.
- **Files modified:** `pnpm-lock.yaml` (apps/web section reconciled; the +178-line diff is rcon-worker deps + this reconciliation).
- **Verification:** `git diff pnpm-lock.yaml | grep ^+` shows only rcon-worker new entries + the pre-declared apps/web fullcalendar entries — no surprise additions.
- **Committed in:** `7ed1d77` (Task 1 commit, lockfile diff included).

---

**Total deviations:** 2 auto-fixed (both Rule 3 — blocking issues).
**Impact on plan:** Both deviations preserve D-021 (containers, never host) and ship the exact `files_modified` set in the plan frontmatter (no new files outside the list; no skipped files inside the list). Lockfile reconciliation is a beneficial side effect — Phase 7's calendar deps now actually resolve.

## Issues Encountered

- **rcon-worker container exit loop** — When initially trying to `docker compose up -d rcon-worker`, the container started → printed `'skeleton boot'` → exited → was restarted by the compose `restart: unless-stopped` policy (inferred via repeated boot log entries). Confirmed via `docker compose ps` that no rcon-worker service was actually running long-term. Resolved by using `docker compose run --rm` for all transient pnpm work (deviation 1).
- **vitest config glob** — `tests/**/*.test.ts` in `vitest.config.ts` correctly picks up the new nested `unit/` and `integration/` directories. Verified via `--reporter=verbose` showing all three new files in the run.

## User Setup Required

None — no external service configuration introduced by Wave 0. Phase 8 secrets (`WEB_HMAC_SECRET`, CRCON RCON credentials) are not consumed by any code path until plan 08-05 (middleware) / 08-09 (Filament resource) / 08-10 (worker wire client).

## Self-Check: PASSED

Verified before finalising:
- **Files created (21):** All 21 files asserted in "Files Created" section verified via `[ -f path ]` checks (script ran on every new path).
- **Files modified (4):** `apps/rcon-worker/package.json`, `apps/rcon-worker/src/index.ts`, `apps/web/lang/en/admin.php`, `pnpm-lock.yaml` — all confirmed via `git log --name-only 7ed1d77 9ea301b`.
- **Commits (2):** `7ed1d77` and `9ea301b` both visible in `git log --oneline -3`.
- **Quality gates re-run before SUMMARY:** `pest --filter=Phase8` → 10 RED. `vitest` → 3 RED + 2 GREEN skeleton. `phpstan` → 0 errors. `pint --test` → 522 PASS. `pest --filter='Admin|I18n'` → 199 PASS (Phase 1-7 regression-free).

## Next Phase Readiness

- **Plan 08-02 (Wave 1 migrations) is unblocked.** All factory stubs exist with the correct string FQNs, so the migration tests will reference factories that exist (PHPStan unblocked).
- **Plan 08-05 (HMAC middleware) is unblocked.** `VerifyRconSignatureTest.php` exists as RED; the worker-side `HmacSigner.ts` contract is committed, giving plan 08-05 a stable wire format to handshake against.
- **Plan 08-10 (worker wire client) is unblocked.** All three vitest stubs exist; `HmacSigner.ts` + `CrconEventNormaliser.ts` typed contracts are committed; logger redact paths are pre-configured so the first commit cannot leak PII (T-08-01-02 mitigation already in place).
- **Plan 08-09 (Filament MatchServerResource) is unblocked.** `admin.match_servers.*` + `admin.match_server_bookings.*` + `admin.audit.match_servers.*` i18n keys all resolve via `trans()`.
- **No blockers.** Phase 1-7 baseline preserved (199 Admin/I18n tests green); CLAUDE.md §3 phpstan-baseline.neon untouched (per-line ignores only).

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
