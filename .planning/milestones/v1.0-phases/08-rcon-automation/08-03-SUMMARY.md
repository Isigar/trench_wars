---
phase: 08-rcon-automation
plan: 03
subsystem: eloquent-models
tags: [eloquent, encrypted-cast, postgres, exclude-constraint, logs-activity, factory, tdd]

# Dependency graph
requires:
  - phase: 08-02
    provides: match_servers + match_server_bookings + match_results.source + matches.manual_entry_required schema (this plan teaches Eloquent how to read/write them)
  - phase: 08-01
    provides: MatchServerFactory + MatchServerBookingFactory Wave 0 stubs (this plan replaces them with real definitions) + Phase8 RED test stubs (this plan turns 2 of 10 GREEN)
provides:
  - App\Models\MatchServer Eloquent model with encrypted:array cast on credentials_encrypted (T-08-03-01 mitigation)
  - App\Models\MatchServerBooking Eloquent model with active scope + dueWithin scope (plan 08-11 BookingScheduler consumer)
  - MatchServerFactory real definition() + inactive() state (replaces Wave 0 stub)
  - MatchServerBookingFactory real definition() + forMatch()/onServer()/overlapping() state helpers (replaces Wave 0 stub)
  - MatchResult.source + isManual()/isRcon() accessors (plan 08-08 upsertFromRcon source guard)
  - GameMatch.manual_entry_required fillable + boolean cast (plan 08-11 RCON-unreachable manual-entry flag, D-019)
  - alter_match_servers_credentials_encrypted_to_text migration (Rule 1 fix — jsonb column rejected Laravel's ciphertext envelope)
affects:
  - 08-04-PLAN.md (MatchEvent + MatchPlayerStat models — same idiom: HasUuids + LogsActivity + HasFactory + real factories)
  - 08-08-PLAN.md (MatchResultService::upsertFromRcon — consumes MatchResult.source + isManual() guard)
  - 08-09-PLAN.md (Filament MatchServerResource + MatchServerBookingResource — both Eloquent models ready for Filament wire-up)
  - 08-11-PLAN.md (BookingScheduler — consumes MatchServerBooking::dueWithin scope + flips GameMatch.manual_entry_required on RCON failure)
  - 08-12-PLAN.md (ScrimE2EHappyPathTest capstone — exercises end-to-end booking → ingest → result flow)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Laravel encrypted:array cast on text column — stores Crypt::encryptString envelope (base64 of {iv,value,mac,tag} JSON) as a single raw string; canonical Laravel docs pattern (verified via Context7 /websites/laravel_12_x)"
    - "Eloquent factory state helpers `forX(Model)/onY(Model)` that pin FK to caller-controlled parent — bypasses default factory parent for tests that demand explicit pairing (e.g. EXCLUDE-overlap tests need exact same server, different match)"
    - "Half-open `[)` tstzrange EXCLUDE constraint behavioural coverage: 4 test cases (reject-overlap, accept-back-to-back, accept-after-cancel, accept-different-server) — mirrors Postgres docs §8.17 range semantics"
    - "Plan-mandated cast vs migration column type mismatch — Rule 1 deviation: ALTER COLUMN ... TYPE text USING expr::text to align jsonb→text without data loss (column unused outside test fixtures)"

key-files:
  created:
    - apps/web/app/Models/MatchServer.php
    - apps/web/app/Models/MatchServerBooking.php
    - apps/web/database/migrations/2026_05_16_100600_alter_match_servers_credentials_encrypted_to_text.php
  modified:
    - apps/web/database/factories/MatchServerFactory.php
    - apps/web/database/factories/MatchServerBookingFactory.php
    - apps/web/tests/Feature/Phase8/MatchServerCredentialEncryptionTest.php
    - apps/web/tests/Feature/Phase8/MatchServerBookingOverlapTest.php
    - apps/web/app/Models/MatchResult.php
    - apps/web/app/Models/GameMatch.php

key-decisions:
  - "ALTER credentials_encrypted from jsonb to text (Rule 1 bug fix). Laravel's encrypted:array cast produces a base64-of-JSON envelope (e.g. eyJpdiI6...) that is NOT valid JSON content — Postgres jsonb rejected the INSERT with SQLSTATE 22P02 invalid input syntax for type json. The plan's must_haves explicitly mandate the encrypted:array cast on this column, so the column type must change to accommodate it. No data migration needed — column had no production rows (only test fixtures, and migrate:fresh resets the test DB)."
  - "Factory state helpers forMatch/onServer (NOT for() relationship binding). The Eloquent factory ->for($model) convention requires the relation name on the model and overrides the FK by name lookup. For tests that need explicit FK pairing (e.g. two bookings on the SAME server with DIFFERENT matches to deliberately trigger the EXCLUDE constraint), explicit state helpers reading $model->id is clearer and avoids the relation-name reflection ambiguity around MatchServerBooking::match() (which collides with the PHP reserved keyword)."
  - "scopeDueWithin signature accepts CarbonInterface for both bounds — anticipates plan 08-11 BookingScheduler invocation pattern (now() and now()->addMinutes(5)). Avoids the string-vs-Carbon foot-gun and lets Eloquent's where binding handle the cast."
  - "Test 'overlapping' state helper added per plan spec but UNUSED in this plan's tests (which build conflict ranges via explicit timestamps). Kept because plan 08-04+ downstream tests (MatchEventIngestService, RconUnreachableFlagsManualTest) may want quick collision setup."

# Metrics
duration: 8min
completed: 2026-05-14
---

# Phase 8 Plan 3: Wave 2 — MatchServer + MatchServerBooking Models + Encrypted Casts Summary

**Authored two new Eloquent models with Laravel `encrypted:array` cast on RCON credentials + LogsActivity (D-012 audit trail) + real factories with state helpers; extended MatchResult.source and GameMatch.manual_entry_required with TDD RED→GREEN. 6 Phase 8 Wave 0 RED stubs now GREEN (2 of 10); full regression suite 1043 PASS. One ALTER migration auto-added (Rule 1) — Laravel's encrypted cast envelope is not valid JSON, so column type changes jsonb→text.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-05-14T03:44:58Z
- **Completed:** 2026-05-14T03:53:00Z
- **Tasks:** 2 / 2
- **Files created:** 3 (2 models + 1 ALTER migration)
- **Files modified:** 6 (2 factories real-definition + 2 RED tests turned GREEN + 2 existing models extended)
- **Commits:** 3 (RED `8816892`; GREEN Task 1 `e0bf729`; Task 2 `0465d92`)

## Accomplishments

### TDD Gate Sequence

1. **RED** (commit `8816892`): replaced 2 Wave 0 stub test files with 6 real assertions; verified all 6 fail with `Class "App\Models\MatchServer" not found`.
2. **GREEN Task 1** (commit `e0bf729`): authored MatchServer + MatchServerBooking models, replaced 2 Wave 0 factory stubs, landed Rule 1 ALTER migration; all 6 Phase 8 model tests pass.
3. **GREEN Task 2** (commit `0465d92`): extended MatchResult.source + GameMatch.manual_entry_required; full 1043-test regression confirms no Phase 1-7 fallout.

### Models created (2)

1. **`App\Models\MatchServer`** — `HasUuids` + `LogsActivity` + `HasFactory`. Fillable: `name, host, port_rcon, region, credentials_encrypted, is_active, last_test_at, last_test_status, last_test_error`. Casts:
   - `credentials_encrypted => encrypted:array` (T-08-03-01 mitigation — Laravel encrypter envelopes the value at rest)
   - `is_active => boolean`, `last_test_at => datetime`, `port_rcon => integer`
   - **Relations:** `bookings()` HasMany MatchServerBooking on `server_id`.
   - **Scope:** `active()` filters `is_active=true` (Filament server-picker dropdown predicate).
2. **`App\Models\MatchServerBooking`** — `HasUuids` + `LogsActivity` + `HasFactory`. Fillable: `match_id, server_id, reserved_from, reserved_to, status`. Casts:
   - `reserved_from/reserved_to => datetime`
   - **Relations:** `match()` BelongsTo GameMatch with explicit FK `'match_id'` (D-04-03-B); `server()` BelongsTo MatchServer with explicit FK `'server_id'`.
   - **Scopes:** `active()` filters `status='active'`; `dueWithin(CarbonInterface $from, CarbonInterface $to)` for plan 08-11 BookingScheduler (half-open overlap query mirroring the EXCLUDE constraint).

### Factories upgraded from Wave 0 stubs (2)

3. **`MatchServerFactory`** — definition: `name = 'Server '.faker->unique()->numerify('##')`, `host = 'crcon-<word>.example.com'`, `port_rcon = 8010..8210` (unique offset), `region` randomly from `['eu-central','us-east','us-west','ap-southeast']`, `credentials_encrypted = ['api_token' => 'fake-bearer-'.Str::random(40)]`, `is_active=true`. State `inactive()` flips `is_active=false`. PHPStan-ignore stubs removed; canonical `@extends Factory<MatchServer>` generic restored.
4. **`MatchServerBookingFactory`** — definition: `reserved_from = now()+1h`, `reserved_to = now()+3h`, `status='active'`. State helpers:
   - `forMatch(GameMatch $m)` — pin `match_id = $m->id` (bypasses default GameMatch::factory()).
   - `onServer(MatchServer $s)` — pin `server_id = $s->id`.
   - `overlapping(MatchServerBooking $other)` — copy `$other`'s `reserved_from`/`reserved_to` to deliberately trigger EXCLUDE in tests.

### Models extended (2)

5. **`MatchResult`** — fillable adds `'source'` between `'notes'` and `'recorded_by_user_id'`. No cast (text column, default `'manual'` is fine). New accessors `isManual()` + `isRcon()` for plan 08-08 `MatchResultService::upsertFromRcon` source guard.
6. **`GameMatch`** — fillable adds `'manual_entry_required'` after `'is_public'`. Cast `'manual_entry_required' => 'boolean'`. Consumed by plan 08-11 booking-poller when RCON is unreachable (D-019).

### Rule 1 deviation migration (1)

7. **`2026_05_16_100600_alter_match_servers_credentials_encrypted_to_text.php`** — `ALTER COLUMN credentials_encrypted TYPE text USING credentials_encrypted::text`. Plan 08-02 declared the column as `jsonb` but Laravel's `encrypted:array` cast writes a raw base64-encoded envelope (not JSON), which Postgres rejected with `SQLSTATE 22P02 invalid input syntax for type json`. The plan's `must_haves.truths` mandate the `encrypted:array` cast, so the column type must change to accommodate it. down() reverts to `jsonb`.

### Tests turned GREEN (6 cases across 2 files)

**MatchServerCredentialEncryptionTest** (2 cases):
- Roundtrip via cast — `credentials_encrypted = ['api_token'=>'test-token-123']` → reload → `$server->credentials_encrypted['api_token'] === 'test-token-123'`.
- Ciphertext at rest — raw column read via `DB::table('match_servers')->value(...)` does NOT contain plaintext; base64-decoded payload matches Laravel envelope shape (`"iv":"..."`, `"value":"..."`, `"mac":"..."`).

**MatchServerBookingOverlapTest** (4 cases):
- Overlap rejected — booking A `[10:00,12:00]` + booking B `[11:00,13:00]` same server → `QueryException` containing `match_server_bookings_no_overlap`.
- Back-to-back accepted — booking A `[10:00,12:00]` + booking C `[12:00,14:00]` same server → succeeds (half-open `[)` interval).
- Cancelled frees slot — cancel booking A, then booking D `[10:30,11:30]` same server → succeeds.
- Different server accepted — identical window on a second server → succeeds (server_id WITH = predicate).

## Task Commits

1. **RED — replace Phase 8 stub tests with real assertions** — `8816892` (test)
2. **GREEN Task 1 — MatchServer + MatchServerBooking models + real factories + ALTER migration** — `e0bf729` (feat)
3. **GREEN Task 2 — extend MatchResult.source + GameMatch.manual_entry_required** — `0465d92` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Models (2)
- `apps/web/app/Models/MatchServer.php`
- `apps/web/app/Models/MatchServerBooking.php`

### Migrations (1)
- `apps/web/database/migrations/2026_05_16_100600_alter_match_servers_credentials_encrypted_to_text.php`

## Files Modified

### Factories (2 — Wave 0 stubs → real)
- `apps/web/database/factories/MatchServerFactory.php`
- `apps/web/database/factories/MatchServerBookingFactory.php`

### Existing models extended (2)
- `apps/web/app/Models/MatchResult.php` — added `source` to $fillable + isManual()/isRcon() accessors
- `apps/web/app/Models/GameMatch.php` — added `manual_entry_required` to $fillable + boolean cast

### Tests (2 — Wave 0 RED stubs → GREEN)
- `apps/web/tests/Feature/Phase8/MatchServerCredentialEncryptionTest.php`
- `apps/web/tests/Feature/Phase8/MatchServerBookingOverlapTest.php`

## Decisions Made

- **ALTER credentials_encrypted from `jsonb` to `text` (Rule 1).** Laravel's `encrypted:array` cast produces a base64-of-JSON envelope as a raw string, which Postgres rejects on a `jsonb` column. The plan's `must_haves.truths` explicitly mandate the cast; therefore the column type must change. No data migration concern — column had no production rows (only test fixtures). Documented in the migration's docblock + this Summary.
- **Factory state helpers `forMatch()`/`onServer()` (not Eloquent `->for()` relation binding).** Eloquent's `->for($model)` requires a relation-name lookup and would invoke either `match()` or `server()` reflectively. Explicit state helpers reading `$model->id` are clearer for tests that deliberately pair the SAME server with DIFFERENT matches (the EXCLUDE-overlap scenarios). Also sidesteps any cleverness around the PHP-reserved-keyword `match()` relation method.
- **`scopeDueWithin` takes `CarbonInterface` arguments.** Plan 08-11 BookingScheduler invokes this with `now()` + `now()->addMinutes(5)`; typing the params as `CarbonInterface` documents the contract and lets Eloquent's binding handle the SQL cast.
- **`overlapping()` factory state added but unused in this plan's tests.** Kept per plan spec (`<behavior>` line 138) since plans 08-04+ may use it; doesn't add maintenance cost.
- **No `MatchResult` cast for `source`.** Plain `text` with DB DEFAULT `'manual'` + CHECK constraint. The `isManual()/isRcon()` accessors front-load the string-comparison so callers (notably plan 08-08 `upsertFromRcon`) read semantically rather than via raw `=== 'manual'`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] credentials_encrypted column type mismatch (jsonb → text)**
- **Found during:** Task 1, first GREEN run (after authoring MatchServer model + factory, the credential roundtrip test failed with `SQLSTATE 22P02 invalid input syntax for type json`).
- **Issue:** Plan 08-02 created `match_servers.credentials_encrypted` as `jsonb`, but Laravel's `encrypted:array` cast (which plan 08-03 mandates per `must_haves.truths`) writes the envelope as a raw base64-encoded string (e.g. `eyJpdiI6...`). Postgres `jsonb` rejected this on INSERT because the envelope is not valid JSON content.
- **Fix:** Authored `2026_05_16_100600_alter_match_servers_credentials_encrypted_to_text.php`: `ALTER COLUMN credentials_encrypted TYPE text USING credentials_encrypted::text`. down() reverts to jsonb.
- **Files modified:** new migration file (one).
- **Verification:** `php artisan migrate:fresh --env=testing` runs all 43 migrations clean; `MatchServerCredentialEncryptionTest` round-trip + ciphertext-at-rest cases both PASS.
- **Committed in:** `e0bf729` (Task 1 GREEN commit).
- **Why not Rule 4:** This is a surgical column-type change with no production data, no schema restructure, no business-logic shift. The plan's stated outcome (encrypted:array cast roundtrip) is achievable only via this fix. Same character as plan 07-02's deviation pattern (correct on-disk migration filename + values vs plan's claim).

**2. [Rule 1 - Bug] PHPStan flagged `@phpstan-ignore-next-line` as a literal directive inside a docblock**
- **Found during:** Task 1, PHPStan run on new factory.
- **Issue:** The factory docblock prose explained "the per-line `@phpstan-ignore-next-line` annotations are removed" — PHPStan parsed the literal `@phpstan-ignore-next-line` substring as an unmatched ignore directive on line 17.
- **Fix:** Reworded the docblock prose to "the per-line PHPStan ignore annotations" (no `@`-prefixed substring).
- **Files modified:** `apps/web/database/factories/MatchServerFactory.php` (docblock only — code unchanged).
- **Verification:** PHPStan re-run reports `[OK] No errors`.
- **Committed in:** `e0bf729` (same Task 1 GREEN commit — fix made before commit).

**3. [Rule 1 - Bug] Pint concat_space style fix on factory**
- **Found during:** Task 1, Pint `--test` run after authoring MatchServerFactory.
- **Issue:** Concat operator `.` was written without surrounding spaces (e.g. `'Server '.fake()->...`); Laravel preset Pint normalises to `'Server ' . fake()->...`.
- **Fix:** Pint auto-fix applied (`docker compose exec web ./vendor/bin/pint database/factories/MatchServerFactory.php`).
- **Files modified:** `apps/web/database/factories/MatchServerFactory.php` (style only — semantics unchanged).
- **Verification:** Pint `--test` re-run passes.
- **Committed in:** `e0bf729`.

### Auth Gates

None — model-layer plan, no external service authentication required. The encrypted cast uses `APP_KEY` (already set in `.env.testing` and dev `.env`).

### Architectural Changes (Rule 4 — required user decision)

None — all deviations are Rule 1 bug fixes within the plan's stated scope.

---

**Total deviations:** 3 auto-fixed (all Rule 1 — bug).
**Impact on plan:** All 6 test cases turn GREEN; all `must_haves.truths` + `must_haves.artifacts` met. The Rule 1 column-type fix strengthens the plan — without it, the plan-mandated `encrypted:array` cast is unworkable, and Phase 8-04/08/09 downstream models would all hit the same wall. Fixing it here closes a latent Phase 8 design bug that 08-02 didn't catch (probe was schema-only, never wrote an encrypted value).

## Issues Encountered

- **`make` not on PATH.** Same as plan 08-02: CLAUDE.md §1 documents Makefile aliases as the canonical container surface, but `make` itself wasn't installed in this session's host. Resolved by invoking the underlying `docker compose exec web ...` commands directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Composer/Pest/Pint/PHPStan ran inside the web container; nothing on host PHP).
- **PHPStan literal-directive false positive in docblocks** — see Deviation 2. Worth knowing for future plans: never write `@phpstan-ignore-next-line` literally inside prose, even in `*` line comments — the rule is positional but the scanner is text-based.

## User Setup Required

None — purely model-layer changes. No new env vars, no Discord/RCON credential setup, no service rebuild.

## Next Phase Readiness

- **Plan 08-04 (MatchEvent + MatchPlayerStat models) is unblocked.** Schema (plan 08-02) and idiom (this plan: HasUuids + LogsActivity + HasFactory + real factory with state helpers) are both available. The MatchEventFactory + MatchPlayerStatFactory Wave 0 stubs are next in line for the same Wave-0-to-real transition that this plan applied to MatchServer/MatchServerBooking.
- **Plan 08-08 (MatchResultService::upsertFromRcon) is unblocked.** `MatchResult::isManual()` / `isRcon()` accessors are the source guard for `ManualOverrideWinsTest` (currently RED — slated GREEN in plan 08-08).
- **Plan 08-09 (Filament MatchServerResource) is unblocked.** Both Eloquent models are LogsActivity-enabled, factory-backed, and have public scopes (`active()`, `dueWithin()`). Filament v3 resource generation can target them directly.
- **Plan 08-11 (BookingScheduler manual-entry flag) is unblocked.** `MatchServerBooking::scopeDueWithin()` is the scheduler-query interface; `GameMatch.manual_entry_required` boolean cast is the flag the scheduler flips on RCON failure (`RconUnreachableFlagsManualTest` currently RED).
- **No blockers.** Phase 1-7 + Phase 8 Wave 0/Wave 1 baseline preserved: 1043 PASS, 8 FAIL (all 8 = Phase 8 Wave 0 RED stubs `expect(true)->toBeFalse()` for plans 08-05..08-13). Net: 2 RED stubs turned GREEN (MatchServerCredentialEncryptionTest + MatchServerBookingOverlapTest), exactly the plan's scope.

## Self-Check: PASSED

Verified before finalising:

**Files created (3):**
- `apps/web/app/Models/MatchServer.php` ✓
- `apps/web/app/Models/MatchServerBooking.php` ✓
- `apps/web/database/migrations/2026_05_16_100600_alter_match_servers_credentials_encrypted_to_text.php` ✓

**Files modified (6):**
- `apps/web/database/factories/MatchServerFactory.php` (Wave 0 → real) ✓
- `apps/web/database/factories/MatchServerBookingFactory.php` (Wave 0 → real) ✓
- `apps/web/tests/Feature/Phase8/MatchServerCredentialEncryptionTest.php` (RED stub → 2 GREEN cases) ✓
- `apps/web/tests/Feature/Phase8/MatchServerBookingOverlapTest.php` (RED stub → 4 GREEN cases) ✓
- `apps/web/app/Models/MatchResult.php` (+source fillable, +isManual/isRcon) ✓
- `apps/web/app/Models/GameMatch.php` (+manual_entry_required fillable + boolean cast) ✓

**Commits (3) — all reachable via `git log --oneline -5`:**
- `8816892` test(08-03): RED tests for MatchServer encrypted cast + MatchServerBooking overlap ✓
- `e0bf729` feat(08-03): MatchServer + MatchServerBooking models + real factories ✓
- `0465d92` feat(08-03): extend MatchResult.source + GameMatch.manual_entry_required ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter='MatchServerCredentialEncryptionTest|MatchServerBookingOverlapTest|MatchResultModel|MatchEventSync'` → **22 PASS** (46 assertions) ✓
- Full feature suite → **1043 PASS**, 8 FAIL (all 8 = Phase 8 Wave 0 RED stubs unchanged from prior plan baseline) ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` (full project) → **531 files PASS** ✓

**Behavioural probes:**
- credentials_encrypted roundtrip works via cast ✓
- raw column contains Laravel ciphertext envelope (not plaintext) ✓
- EXCLUDE rejects overlapping ACTIVE bookings on same server ✓
- back-to-back bookings sharing an endpoint accepted ✓
- cancelled-then-overlap accepted ✓
- different-server identical-window accepted ✓
- MatchResult::isManual() / isRcon() accessors return expected booleans ✓
- GameMatch.manual_entry_required hydrates as boolean ✓

**TDD Gate Compliance:** RED commit (`8816892`) precedes GREEN commits (`e0bf729`, `0465d92`); RED tests verified failing before GREEN implementation; GREEN verified passing after each.

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
