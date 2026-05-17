---
phase: 08-rcon-automation
plan: 08
subsystem: match-player-stat-aggregator-and-close-match-job-handle-and-match-result-upsert-from-rcon
tags: [rcon, aggregator, match-result, manual-override, idempotency, audit-log, seeder, postgres, wave-5, tdd, gate-closed]

# Dependency graph
requires:
  - phase: 08-04
    provides: "MatchEvent / MatchPlayerStat / MatchResult models + composite UNIQUE (match_id, crcon_stream_id) on match_events; MatchEventFactory state methods (kill / teamKill / matchEnd) for deterministic test fixtures"
  - phase: 08-07
    provides: "App\\Services\\Rcon\\MatchEventIngestService dispatches CloseMatchJob on match_end; App\\Jobs\\Rcon\\CloseMatchJob (Wave-5 placeholder with empty handle()) — this plan fills the body"
  - phase: 08-02
    provides: "match_results.source text DEFAULT 'manual' + CHECK in (manual, rcon); matches.manual_entry_required boolean DEFAULT false"
  - phase: 04-09
    provides: "App\\Services\\MatchResultService::upsert (Phase 4 admin entry path); MatchStatusService transition state-machine (open|locked → played)"
provides:
  - "App\\Services\\Rcon\\MatchPlayerStatAggregator — final, stateless, container-resolvable; aggregate(GameMatch): int returns rows upserted. Rolls up 5 player-related event types (player_kill, player_team_kill, player_connect, player_disconnect, team_switch) into match_player_stats via updateOrCreate keyed on (match_id, player_id). Idempotent (re-runs yield identical state). Orphan events (steam_id_64 with no Player row) silently skipped per Pitfall 5"
  - "App\\Services\\MatchResultService::upsertFromRcon(GameMatch, array): MatchResult — extension on the Phase 4 service. Manual-override invariant: existing source='manual' rows are returned unchanged + audit-log entry written via activity()->withProperties()->log() with properties.event='rcon.arrived_but_manual_locked' and would_have_set scores. Causer = SYSTEM_RCON_WORKER user. Atomic with MatchStatusService transition to 'played'"
  - "App\\Jobs\\Rcon\\CloseMatchJob::handle filled — was Wave-5 placeholder from plan 08-07. Re-resolves GameMatch via findOrFail($matchId), invokes aggregator, looks up latest match_end event. Missing match_end → manual_entry_required=true + early return (no MatchResult). Zero kills + match_end → manual_entry_required=true AND best-effort MatchResult write. winner_clan_id stays null (round-1 cannot map team→clan deterministically; admin curates)"
  - "Database\\Seeders\\RconWorkerSystemUserSeeder — idempotent firstOrCreate keyed on discord_id='SYSTEM_RCON_WORKER' + email='rcon-worker@system.trenchwars'. Mirrors Phase 5 BotServiceUserSeeder idiom (D-02-04). No roles, no Filament access — attribution causer only (T-08-08-03 accept: no privilege)"
  - "DatabaseSeeder appended with RconWorkerSystemUserSeeder::class after BotServiceUserSeeder (paired singletons; plan-01 seeders contract)"
  - "Migration 2026_05_16_100700_add_steam_id_64_to_players_table — players.steam_id_64 text nullable + UNIQUE constraint. Required so MatchPlayerStatAggregator's Player::firstWhere('steam_id_64', $sid) lookup resolves CRCON payloads to Player rows (must_haves.key_links #3). Auto-fix: plan referenced the column but no migration shipped it"
  - "App\\Models\\Player::$fillable extended with steam_id_64"
  - "lang/en/rcon.php — rcon.audit.automated_from_crcon i18n key added (default notes value written by upsertFromRcon when caller omits notes)"
affects:
  - 08-09-PLAN.md (RconUnreachableFlagsManualTest — depends on matches.manual_entry_required flag flipping; plan 08-08 ships the flip from the CloseMatchJob side; plan 08-09 ships the flip from the worker TestConnection side)
  - 08-10-PLAN.md (worker outbound normaliser TS mirror — must emit canonical match_end payload shape so this plan's aggregator + CloseMatchJob can read allies_score / axis_score off the payload)
  - 08-12-PLAN.md (E2E scrim happy path — exercises full ingest → aggregate → MatchResult chain end-to-end via signedJsonPost; ScrimE2EHappyPathTest is still RED until 08-12 lands)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-event switch-based accumulator inside DB::transaction — MatchPlayerStatAggregator iterates match_events with a switch over the 5 player-related event types, accumulating per-steam-id-64 counters in a local array, then upserts at the end. The wrapping `DB::transaction` becomes a SAVEPOINT/RELEASE pair under RefreshDatabase (Pest tests) and a real BEGIN/COMMIT in production — same semantic guarantee. Idempotency from updateOrCreate by (match_id, player_id) composite UNIQUE makes CloseMatchJob retryable end-to-end (Horizon retry → identical end state)."
    - "Manual-override invariant via early-return + audit-log write — MatchResultService::upsertFromRcon checks `$existing && $existing->source === 'manual'` BEFORE the updateOrCreate. On hit: writes activity()->withProperties()->log() with the would-have-set scores then returns the existing row unmodified. This is the integrity boundary for the RCON-vs-admin trust conflict (D-019). The properties.event='rcon.arrived_but_manual_locked' string is the grep target for the admin audit-log view (plan 01-13). D-04-12-A: explicit `->withProperties()` is the only path that populates Activity::$properties in this codebase — the LogsActivity trait alone won't capture would_have_set for a 'no-op skip' since no create/update/delete event fires."
    - "Sentinel-email service-user resolution — MatchResultService::upsertFromRcon resolves the SYSTEM_RCON_WORKER user via `User::where('email', 'rcon-worker@system.trenchwars')->firstOrFail()`. firstOrFail (NOT firstOrCreate) by design: a missing user means the seeder never ran, which is a deployment/CI failure not a runtime recoverable. The seeder is wired into DatabaseSeeder so `php artisan migrate:fresh --seed` produces the user; tests opt-in via `\$this->seed(RconWorkerSystemUserSeeder::class)` in beforeEach (RefreshDatabase doesn't auto-run seeders)."
    - "Primitive-ID job constructors + method-injected services in handle() — CloseMatchJob takes `readonly string \$matchId` (Phase 5 SyncDiscordRolesJob idiom — Redis-serialised payloads must not carry Eloquent models because the row may be deleted between dispatch and handle, throwing ModelNotFoundException on rehydrate). handle() method-injects MatchPlayerStatAggregator + MatchResultService via Laravel's container resolver — same pattern as Phase 4 controllers. Tests pass `new CloseMatchJob(\$match->id)` and `->handle(app(Aggregator::class), app(ResultService::class))` directly to exercise the real services without Bus::fake."
    - "DB-side DEFAULT for source column tested via ->fresh() — Phase 4 upsert() doesn't write match_results.source explicitly, so the Eloquent in-memory model's `->source` attribute is null after updateOrCreate. The Postgres DEFAULT 'manual' (migration 08-02 task 2) populates the column at the DB layer. ManualOverrideWinsTest case 1+2 use `->fresh()->source` to assert the default kicked in. This is a Laravel-Eloquent-vs-DB-default reality check, NOT a behaviour bug — the manual-override gate (`$existing->source === 'manual'`) works correctly because it reads via `MatchResult::where(...)->first()` which retrieves from DB."

key-files:
  created:
    - apps/web/app/Services/Rcon/MatchPlayerStatAggregator.php
    - apps/web/database/seeders/RconWorkerSystemUserSeeder.php
    - apps/web/database/migrations/2026_05_16_100700_add_steam_id_64_to_players_table.php
  modified:
    - apps/web/app/Services/MatchResultService.php
    - apps/web/app/Jobs/Rcon/CloseMatchJob.php
    - apps/web/database/seeders/DatabaseSeeder.php
    - apps/web/app/Models/Player.php
    - apps/web/lang/en/rcon.php
    - apps/web/tests/Feature/Phase8/MatchPlayerStatAggregatorTest.php
    - apps/web/tests/Feature/Phase8/RconMatchResultIngestionTest.php
    - apps/web/tests/Feature/Phase8/ManualOverrideWinsTest.php

key-decisions:
  - "Auto-fixed Rule 3 blocker: plan referenced `Player::firstWhere('steam_id_64', ...)` as the aggregator's player-resolution lookup, but the `players` table had NO `steam_id_64` column (verified empirically — the Phase 1 players migration omits it; no later phase added it). Added migration `2026_05_16_100700_add_steam_id_64_to_players_table` shipping `players.steam_id_64 text nullable UNIQUE`. Nullable because existing P1/Phase-2 onboarding flows didn't collect Steam IDs; UNIQUE because one Steam ID maps to at most one Player (Postgres allows multiple NULL under UNIQUE). text (NOT bigint) matches the wire shape from CRCON + match_events payloads (no integer overflow, no string<->int conversion at the lookup layer). 08-13 plan documents the round-1 mitigation: admins backfill Steam IDs out-of-band before scrim acceptance; orphan events silently skipped per Pitfall 5."
  - "Auto-fixed Rule 1 plan-code bug: plan's <interfaces> RconWorkerSystemUserSeeder block wrote `'slug' => 'rcon-worker', 'is_admin' => false` — neither column exists on the `users` table (verified against migration 2026_05_03_100000_create_users_table). Replaced with the canonical Phase 5 BotServiceUserSeeder idiom: discord_id sentinel ('SYSTEM_RCON_WORKER') + username + email + locale, all fillable on User. T-08-08-03 disposition (accept: no privilege) is preserved — the user has no roles, no permissions, no Filament access, attribution-only."
  - "Auto-fixed Rule 2 missing-i18n-key: plan's <interfaces> upsertFromRcon referenced `__('rcon.audit.automated_from_crcon')` as the default notes value for RCON-sourced rows, but the key didn't exist in lang/en/rcon.php (only 'manual_override_wins', 'rcon_arrived_locked', 'test_connection_*' lived in the audit group). Added the key with copy 'Automated result from CRCON — populated by the RCON worker.' D-013 CI gate (NoHardcodedStringsTest, plan 08-12) requires every t()/__() to resolve from day one — this prevents a future failure."
  - "ManualOverrideWinsTest case 3 simulates the admin-override path via `$rconResult->update(['source' => 'manual', ...])` directly rather than via MatchResultService::upsert. Reason: Phase 4's upsert() doesn't write the source column explicitly (it's not in the upsert's fillable array), so calling upsert on an existing source='rcon' row leaves source='rcon' (updateOrCreate doesn't touch unspecified columns). The intended behaviour — admin override flips source to 'manual' — requires explicit source='manual' in the write. The Filament admin Resource (plan 04-09 ResultRelationManager) writes source='manual' explicitly when an admin saves; we simulate that wire shape here. Documented in the test's inline comment block to prevent confusion for future readers."
  - "ManualOverrideWinsTest case 4 (block subsequent RCON after manual override) seeds a SECOND match_end event with a later occurred_at and fresh crcon_stream_id (factory auto-increments). Reason: re-running CloseMatchJob on the same match_end event would be absorbed by the composite UNIQUE (match_id, crcon_stream_id) at the MatchEvent layer — no new event lands. To simulate CRCON late-delivery (a fresh log entry after the admin override), we synthesise a second match_end with `occurred_at = first->occurred_at + 5 minutes`. The aggregator's `latest('occurred_at')` lookup grabs the new event, but the manual-override gate in upsertFromRcon blocks the write."
  - "All test files use file-scoped helper functions (rmrSeedPlayer, mowSeedPlayer, mowRunCloseJob, seedPlayer) rather than Pest closure-state properties (`\$this->`). Reason: Pest's PendingCalls\\TestCall surface doesn't carry typed properties — accessing `\$this->normaliser` (or any custom prop) trips PHPStan with 'Access to undefined property TestCall::\$X'. Identical reasoning + same pattern as plan 08-07 SUMMARY's MatchEventNormaliserContractTest. The helpers are file-scoped (not promoted to Tests\\Support) because they're test-data builders local to one file's wire shape, not production-mirrored behaviour."
  - "CloseMatchJob uses primitive `is_int($payload['allies_score'])` checks rather than blind cast `(int) \$payload['allies_score']`. Reason: the MatchEventNormaliser (plan 08-07) doesn't strict-validate the match_end payload's allies_score/axis_score types — the normaliser permits missing keys (round_end is the strict counterpart). A malformed CRCON payload with `allies_score='3'` (string) would silently coerce to 0 via blind cast; the typed guard surfaces the bad shape as null which CloseMatchJob writes as null (NULL in match_results.allies_score is valid per migration 04-02 — scores are nullable). T-08-08-05 (zero-kill low-confidence) handles the orthogonal failure mode."

# Metrics
duration: 9min
completed: 2026-05-14
---

# Phase 8 Plan 8: Wave 5 — MatchPlayerStatAggregator + CloseMatchJob handle + RconWorkerSystemUserSeeder + auto MatchResult Summary

**Closed the result-side of the RCON pipeline: rolled up `match_events` into `match_player_stats` via `MatchPlayerStatAggregator`, filled the empty `CloseMatchJob::handle()` from plan 08-07 with `aggregator + MatchResultService::upsertFromRcon + status flip`, and wired the SYSTEM_RCON_WORKER causer user via an idempotent seeder. The manual-override invariant (D-019, T-08-08-01) is enforced: existing `source='manual'` rows are returned unchanged with an `activity_log` `rcon.arrived_but_manual_locked` audit entry capturing the would-have-set scores. Project regression 1087 → 1103 PASS (+16 new GREEN: 7 aggregator + 5 ingestion + 4 manual-override), 5 → 2 FAIL (closed 3 Wave-0 RED stubs; remaining 2 are scheduled for plans 08-09 RconUnreachable + 08-12 ScrimE2E). 3 plan-code auto-fixes applied: added the missing `players.steam_id_64` column the plan's lookup pattern required, replaced the seeder's non-existent `slug`/`is_admin` columns with the canonical Phase 5 BotServiceUserSeeder idiom, and added the missing `rcon.audit.automated_from_crcon` i18n key.**

## Performance

- **Duration:** ~9 min
- **Started:** 2026-05-14T04:50:19Z
- **Completed:** 2026-05-14T04:59:19Z (approximately)
- **Tasks:** 2 / 2
- **Files created:** 3 (1 service + 1 seeder + 1 migration)
- **Files modified:** 7 (1 service extended + 1 job filled + 1 seeder-index + 1 model fillable + 1 i18n + 3 test stubs → GREEN suites)
- **Commits:** 2 (Task 1 `66b5f09`; Task 2 `0b236d2`)

## Accomplishments

### TDD Gate Sequence

Plan-level type is `execute` (not `tdd`), but both tasks have `tdd="true"` and each follows the GREEN-after-RED idiom — the Wave-0 RED stubs (`expect(true)->toBeFalse()`) from plan 08-01 are the canonical RED gate.

1. **GREEN Task 1** (commit `66b5f09`): authored `MatchPlayerStatAggregator` + `RconWorkerSystemUserSeeder` + migration; appended seeder to `DatabaseSeeder`; replaced Wave-0 RED stub in `MatchPlayerStatAggregatorTest` with 7 GREEN cases. Pre-commit verifies: 7 PASS, PHPStan L8 0 errors, Pint clean (auto-fixed `fully_qualified_strict_types`).
2. **GREEN Task 2** (commit `0b236d2`): extended `MatchResultService` with `upsertFromRcon`, filled `CloseMatchJob::handle()`, added `rcon.audit.automated_from_crcon` i18n key, replaced 2 Wave-0 RED stubs with 5+4=9 GREEN cases. Pre-commit verifies: 19 PASS (own suite + Phase 4 MatchResultServiceTest 9 + MatchAuditLogTest 1 — no regression), PHPStan L8 0 errors, Pint clean.

Both tasks satisfy the implicit RED gate via the pre-existing Wave-0 stubs.

### Application code (3 created, 5 modified)

1. **`App\Services\Rcon\MatchPlayerStatAggregator`** — `final`, stateless, container-resolvable. `aggregate(GameMatch): int` iterates `match_events` with a `switch` over the 5 player-related event types (`player_kill`, `player_team_kill`, `player_connect`, `player_disconnect`, `team_switch`), accumulating per-steam-id-64 counters in a local array `$perPlayer`. After the loop, for each accumulator slot it `Player::firstWhere('steam_id_64', $sid)` — null lookup → orphan event silently skipped (Pitfall 5); hit → `MatchPlayerStat::updateOrCreate([match_id, player_id], [...])` upsert. `score` derived = `kills × 100` (CRCON `/ws/logs` does not emit per-player score). `weapons_used` jsonb is a per-weapon histogram `{K98: 3, GARAND: 1, ...}`. Wrapped in `DB::transaction` (SAVEPOINT under RefreshDatabase / BEGIN-COMMIT in production).

2. **`App\Services\MatchResultService::upsertFromRcon`** (new method on existing service) — Phase 4 `upsert` and the new method coexist; the file gained ~80 lines including class const `RCON_WORKER_EMAIL` + the new method's PHPDoc + body. Manual-override gate: early-return on `$existing && $existing->source === 'manual'` after writing `activity()->performedOn($existing)->withProperties([event, would_have_set])->log(__('rcon.audit.rcon_arrived_locked'))`. On non-manual path: resolves SYSTEM_RCON_WORKER via `User::where('email', RCON_WORKER_EMAIL)->firstOrFail()`, `updateOrCreate(['match_id' => $match->id], [...with source='rcon'])`, then atomic `MatchStatusService::transition($match, 'played', $rconUser)` skipped when already terminal.

3. **`App\Jobs\Rcon\CloseMatchJob::handle()`** (filled from plan 08-07 placeholder) — method-injects `MatchPlayerStatAggregator` + `MatchResultService` via Laravel's container. 6-step algorithm: (1) `GameMatch::findOrFail($this->matchId)`; (2) `$aggregator->aggregate($match)`; (3) lookup latest `match_end` event — null → `manual_entry_required=true` + return; (4) count `player_kill` events — zero → `manual_entry_required=true` (low confidence, but proceed); (5) build `$resultData` from payload with strict-typed `is_int` guards; (6) `$resultService->upsertFromRcon($match, $resultData)`. `winner_clan_id` stays null (round-1 cannot map team→clan deterministically; admin curates).

4. **`Database\Seeders\RconWorkerSystemUserSeeder`** — idempotent `User::firstOrCreate(['discord_id' => 'SYSTEM_RCON_WORKER'], [...])` with username/email/locale. Mirrors Phase 5 `BotServiceUserSeeder` exactly (D-02-04 idiom). T-08-08-03 disposition (accept): no roles, no Filament access, attribution-only.

5. **`Database\Seeders\DatabaseSeeder`** (modified) — appended `RconWorkerSystemUserSeeder::class` after `BotServiceUserSeeder` (paired singletons; bot user + RCON worker user run in the same seeder pass).

6. **`App\Models\Player`** (modified) — `$fillable` extended with `steam_id_64` so the Player factory's `create(['steam_id_64' => ...])` writes the column.

7. **Migration `2026_05_16_100700_add_steam_id_64_to_players_table`** — `players.steam_id_64 text nullable UNIQUE` (named `players_steam_id_64_unique`). Postgres allows multiple NULLs under UNIQUE; only populated rows are deduplicated. Down migration drops the UNIQUE then the column.

8. **`lang/en/rcon.php`** (modified) — added `audit.automated_from_crcon` key with copy "Automated result from CRCON — populated by the RCON worker."

### Tests modified (3 files: RED→GREEN, 3→16 cases)

9. **`tests/Feature/Phase8/MatchPlayerStatAggregatorTest`** — Wave-0 RED stub (`expect(true)->toBeFalse()`) replaced with 7 GREEN cases: (1) two players + 5 kills, accurate kill/death counts; (2) team kill bumps killer.team_kills only (deaths not incremented on team_kill); (3) idempotency (re-run yields identical counts); (4) orphan event with no Player row silently skipped; (5) weapons_used jsonb histogram captures K98:3; (6) score = kills × 100; (7) empty match → 0 rows, no exceptions.

10. **`tests/Feature/Phase8/RconMatchResultIngestionTest`** — Wave-0 RED stub replaced with 5 GREEN cases: (1) 10 kills + match_end → MatchResult source=rcon, scores from payload, recorded_by=rcon-worker; (2) missing match_end → manual_entry_required=true, no MatchResult; (3) zero kills + match_end → manual_entry_required=true AND best-effort MatchResult written (allies/axis scores still captured); (4) status flips open → played; (5) MatchPlayerStat rows materialise (aggregator chained).

11. **`tests/Feature/Phase8/ManualOverrideWinsTest`** — Wave-0 RED stub replaced with 4 GREEN cases: (1) admin manual upsert + RCON match_end arrival → MatchResult unchanged (id, source=manual, winner_clan_id, scores, notes, recorded_by all preserved); (2) audit-log entry with description=`rcon.arrived_but_manual_locked` translation + properties.event + properties.would_have_set scores; (3) admin override flips source to manual (simulates Filament Resource path which writes source='manual' explicitly); (4) subsequent RCON match_end (fresh event, later occurred_at) is blocked — source stays manual, scores stay manual values.

## Task Commits

1. **GREEN Task 1 — MatchPlayerStatAggregator + RconWorkerSystemUserSeeder + 7-case GREEN test + steam_id_64 migration** — `66b5f09` (feat)
2. **GREEN Task 2 — CloseMatchJob handle + MatchResultService::upsertFromRcon + 5+4 GREEN tests + i18n audit key** — `0b236d2` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (1)
- `apps/web/app/Services/Rcon/MatchPlayerStatAggregator.php`

### Database (2)
- `apps/web/database/seeders/RconWorkerSystemUserSeeder.php`
- `apps/web/database/migrations/2026_05_16_100700_add_steam_id_64_to_players_table.php`

## Files Modified

### Application code (3)
- `apps/web/app/Services/MatchResultService.php` — `upsertFromRcon()` appended; class constant `RCON_WORKER_EMAIL` added; Phase 4 `upsert()` unchanged
- `apps/web/app/Jobs/Rcon/CloseMatchJob.php` — `handle()` body filled (was empty Wave-5 placeholder from plan 08-07)
- `apps/web/app/Models/Player.php` — `$fillable` extended with `steam_id_64`

### Database / config (2)
- `apps/web/database/seeders/DatabaseSeeder.php` — appended `RconWorkerSystemUserSeeder` after `BotServiceUserSeeder`
- `apps/web/lang/en/rcon.php` — added `audit.automated_from_crcon` key

### Tests (3)
- `apps/web/tests/Feature/Phase8/MatchPlayerStatAggregatorTest.php` — Wave-0 RED → 7-case GREEN
- `apps/web/tests/Feature/Phase8/RconMatchResultIngestionTest.php` — Wave-0 RED → 5-case GREEN
- `apps/web/tests/Feature/Phase8/ManualOverrideWinsTest.php` — Wave-0 RED → 4-case GREEN

## Decisions Made

See `key-decisions` in the frontmatter. Highlights:

- **Added `players.steam_id_64` migration** (Rule 3 auto-fix): plan's lookup pattern required the column; the Phase 1 migration omitted it; no prior Phase 8 plan added it.
- **Seeder uses BotServiceUserSeeder idiom** (Rule 1 auto-fix): plan's pseudo-code used non-existent `slug`/`is_admin` columns; rewrote with the canonical discord_id sentinel pattern.
- **Added `rcon.audit.automated_from_crcon` i18n key** (Rule 2 auto-fix): plan referenced it but didn't ship it.
- **Manual-override gate via early-return + activity() audit** — see tech-stack patterns. D-04-12-A: explicit `->withProperties()` is the only path to populated `Activity::$properties`.
- **Primitive-ID job + method-injected services** — Phase 5 SyncDiscordRolesJob idiom; primitive `$matchId` avoids queue-payload-outlives-row hazard; method injection lets tests use real services without Bus::fake.
- **File-scoped test helper functions** (rmrSeedPlayer/mowSeedPlayer/mowRunCloseJob/seedPlayer) — avoids PHPStan TestCall property errors that closure-state properties (`$this->normaliser`) would cause.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocker] Missing `players.steam_id_64` column**

- **Found during:** Task 1 first iteration (would have thrown "column does not exist" on the aggregator's `firstWhere`).
- **Issue:** Plan's `<interfaces>` MatchPlayerStatAggregator block + `must_haves.key_links #3` both reference `Player::firstWhere('steam_id_64', $steamId)` as the canonical player-resolution lookup, but `players` table had no such column. Phase 1 migration `2026_05_03_100100_create_players_table` ships `id, user_id, slug, display_name, avatar_source, avatar_path, bio, country_code, timestamps, deleted_at` — no Steam ID column. No later phase added one (verified via grep across all migrations).
- **Fix:** Added migration `2026_05_16_100700_add_steam_id_64_to_players_table` shipping `players.steam_id_64 text NULL UNIQUE`. Nullable because existing rows had no Steam ID; UNIQUE because one Steam ID maps to at most one Player (D-002 corollary); text matches the wire shape from CRCON / match_events payloads (no integer overflow on 64-bit Steam IDs).
- **Files modified/created:** `apps/web/database/migrations/2026_05_16_100700_add_steam_id_64_to_players_table.php` (new), `apps/web/app/Models/Player.php` ($fillable extended).
- **Commit:** Folded into Task 1 commit `66b5f09`.
- **Plan correctness:** The plan's lookup pattern is sound — orphan-event-silently-skipped (Pitfall 5) is the intentional design for league-server-but-not-league-roster players. The schema gap is a planning oversight; the auto-fix preserves the must_haves contract bit-for-bit.

**2. [Rule 1 — Bug in plan code] RconWorkerSystemUserSeeder fillable uses non-existent columns**

- **Found during:** Task 1 first iteration of the seeder write (User mass-assignment would have silently dropped `slug`+`is_admin` from the array).
- **Issue:** Plan's `<interfaces>` RconWorkerSystemUserSeeder block uses `'slug' => 'rcon-worker', 'is_admin' => false`. The `users` table migration (`2026_05_03_100000_create_users_table.php`) has columns: `id, discord_id, username, email (citext), avatar_url, locale, last_login_at, left_community_at, remember_token, timestamps`. Neither `slug` nor `is_admin` exists. The User model's `$fillable` does not include them either.
- **Fix:** Rewrote the seeder using the canonical Phase 5 `BotServiceUserSeeder` idiom: `User::firstOrCreate(['discord_id' => 'SYSTEM_RCON_WORKER'], ['username' => 'RCON Worker', 'email' => 'rcon-worker@system.trenchwars', 'locale' => 'en'])`. T-08-08-03 disposition (accept: no privilege) is preserved without `is_admin` because the user simply has no roles assigned via Spatie permission — equivalent threat coverage.
- **Files modified:** `apps/web/database/seeders/RconWorkerSystemUserSeeder.php` (the only authoritative copy).
- **Commit:** Folded into Task 1 commit `66b5f09`.
- **Plan correctness:** The seeder semantics (idempotent service-user singleton, attribution-only) are preserved exactly; only the fillable shape changes to match the real users schema. Documented in the seeder's class docblock.

**3. [Rule 2 — Missing critical functionality] `rcon.audit.automated_from_crcon` i18n key**

- **Found during:** Task 2 first iteration of `MatchResultService::upsertFromRcon`.
- **Issue:** Plan's `<interfaces>` upsertFromRcon block calls `__('rcon.audit.automated_from_crcon')` as the default `notes` value when caller omits it. The key did NOT exist in `lang/en/rcon.php` (only `manual_override_wins`, `rcon_arrived_locked`, `test_connection_*` lived under `audit`). Calling `__()` on a missing key returns the key string itself — the row would land with `notes='rcon.audit.automated_from_crcon'` (literal key text) in production. D-013 CI gate (NoHardcodedStringsTest, slated for plan 08-12) will fail on missing keys.
- **Fix:** Added the key under `audit.automated_from_crcon` with English copy "Automated result from CRCON — populated by the RCON worker."
- **Files modified:** `apps/web/lang/en/rcon.php`.
- **Commit:** Folded into Task 2 commit `0b236d2`.
- **Plan correctness:** The plan's notes-default behaviour is preserved; only the missing localisation key is added.

### Auth Gates

None — implementation-only plan, no external service authentication required.

### Architectural Changes (Rule 4 — required user decision)

None.

---

**Total deviations:** 3 (all Rule 1/2/3 auto-fixes; no Rule 4 architectural decisions).
**Impact on plan:** Zero changes to plan must_haves contract. The schema gap (Rule 3) is the largest deviation but the resulting `players.steam_id_64 text nullable UNIQUE` column is exactly the lookup target the plan's `<interfaces>` always assumed. The other two fixes are purely correctness (seeder shape + i18n key).

## Issues Encountered

- **`make` not on PATH** (same as plans 08-02..08-07). CLAUDE.md §1 documents Makefile aliases as the canonical container surface, but `make` itself isn't installed in this session's host. Resolved by invoking the underlying `docker compose exec -T web ./vendor/bin/…` commands directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Pest/Pint/PHPStan ran inside the web container).
- **Schema-vs-cast PHPStan inference miss** — first iteration of the aggregator had `if (! is_array($payload)) { continue; }` which tripped PHPStan with "is_array() with string will always evaluate to false". Larastan's schema-aware inference sees the underlying jsonb column as string-typed; the `'array'` cast on MatchEvent isn't reflected in the inferred return type. Fixed with an explicit `@var array<string, mixed> $payload` annotation (no behavioural change; the cast still runs at runtime).
- **Eloquent in-memory model vs DB DEFAULT semantics** — first iteration of ManualOverrideWinsTest case 1 asserted `$manualResult->source` directly (post-upsert), which is `null` because Phase 4 upsert() doesn't write source explicitly and updateOrCreate doesn't reload after writing. The DB row has the DEFAULT 'manual' (Postgres CHECK enforces). Fixed by asserting `$manualResult->fresh()->source` — refresh() pulls the column back. The manual-override gate itself works correctly because it reads via `MatchResult::where(...)->first()` which retrieves the DB-side value.
- **Pint auto-fixes** — both task commits triggered Pint's `fully_qualified_strict_types` + `ordered_imports` rules, which Pint auto-applied during the verification step. No manual intervention needed; the auto-fixes were folded into the per-task commit without changing semantics.

## User Setup Required

None — internal service + seeder + migration + i18n key + tests. The new migration runs automatically on `php artisan migrate` (next deploy or `migrate:fresh --seed` in test/dev). The new seeder runs on `db:seed` (also automatic in CI's `migrate:fresh --seed`).

## Next Phase Readiness

- **Plan 08-09 (RconUnreachableFlagsManualTest GREEN — worker TestConnection failure flips `manual_entry_required=true`) is unblocked.** This plan ships the `manual_entry_required` flag-flip from the CloseMatchJob side (missing match_end OR zero kills); plan 08-09 ships the same flip from the worker TestConnection side. The `manual_entry_required` boolean + partial index from migration 08-02 are already in place.

- **Plan 08-10 (apps/rcon-worker outbound normaliser TS mirror) is unblocked.** The aggregator + CloseMatchJob both read `payload.allies_score` / `payload.axis_score` (typed as int via `is_int` guard) off the `match_end` event. The TS-side CrconEventNormaliser MUST emit those keys as integers (NOT strings) — drift surfaces as null scores in MatchResult, which is graceful but loses fidelity. Plan 08-10 owner: replicate the strict type check on the TS side.

- **Plan 08-12 (E2E scrim happy path) is unblocked at the result-write seam.** The full chain ingest → aggregate → MatchResult is operational; plan 08-12's `ScrimE2EHappyPathTest` (still RED) will replay a recorded CRCON event stream through `signedJsonPost` against `/api/internal/match/{match}/events` and assert the end-state `MatchResult` matches the recorded match outcome.

- **No blockers.** Phase 8 baseline: **plan 08-07 → 1087 PASS / 5 FAIL; plan 08-08 → 1103 PASS / 2 FAIL.** Net change: **+16 PASS** (7 aggregator + 5 ingestion + 4 manual-override GREEN), **−3 FAIL** (3 Wave-0 RED stubs closed). The 2 remaining FAILs are:
  - `RconUnreachableFlagsManualTest` → plan 08-09 (worker failure handling)
  - `ScrimE2EHappyPathTest` → plan 08-12 (Phase 8 capstone E2E)

## Self-Check: PASSED

Verified before finalising:

**Files created (3) — all exist:**
- `apps/web/app/Services/Rcon/MatchPlayerStatAggregator.php` ✓
- `apps/web/database/seeders/RconWorkerSystemUserSeeder.php` ✓
- `apps/web/database/migrations/2026_05_16_100700_add_steam_id_64_to_players_table.php` ✓

**Files modified (8) — all staged in commits:**
- `apps/web/app/Services/MatchResultService.php` (upsertFromRcon appended) ✓
- `apps/web/app/Jobs/Rcon/CloseMatchJob.php` (handle filled) ✓
- `apps/web/database/seeders/DatabaseSeeder.php` (seeder list extended) ✓
- `apps/web/app/Models/Player.php` ($fillable extended) ✓
- `apps/web/lang/en/rcon.php` (automated_from_crcon key) ✓
- `apps/web/tests/Feature/Phase8/MatchPlayerStatAggregatorTest.php` (RED→7 GREEN) ✓
- `apps/web/tests/Feature/Phase8/RconMatchResultIngestionTest.php` (RED→5 GREEN) ✓
- `apps/web/tests/Feature/Phase8/ManualOverrideWinsTest.php` (RED→4 GREEN) ✓

**Commits (2) — reachable via `git log --oneline -3`:**
- `66b5f09` feat(08-08): MatchPlayerStatAggregator + RconWorkerSystemUserSeeder + 7-case GREEN test ✓
- `0b236d2` feat(08-08): CloseMatchJob handle + MatchResultService::upsertFromRcon + GREEN ingestion + manual-override tests ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter='RconMatchResultIngestionTest|ManualOverrideWinsTest|MatchResultUpsert|MatchResultService'` → **19 PASS, 79 assertions** ✓
- `pest --filter='MatchPlayerStatAggregatorTest'` → **7 PASS, 24 assertions** ✓
- `pest tests/Feature/Phase8` → **59 PASS, 2 FAIL** (2 remaining = Wave-0 RED stubs scheduled for 08-09 + 08-12) ✓
- Full project `pest` → **1103 PASS, 2 FAIL** (1087 → 1103 = +16 PASS; 5 → 2 FAIL = −3 RED closed; **0 regressions**) ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` (touched files) → **PASS** ✓

**TDD Gate Compliance:** Both `tdd="true"` tasks landed GREEN behavioural suites that replaced pre-existing Wave-0 RED stubs from plan 08-01. Plan-level type is `execute` (not `tdd`); per-task TDD discipline IS satisfied for both tasks via the existing RED stubs.

**Plan correctness verifications (per the plan's `<verification>` block):**
- All Phase 8 GREEN tests still GREEN (RconMatchResultIngestion, MatchPlayerStatAggregator, ManualOverrideWins, MatchEventNormaliserContract, MatchEventIngestService, MatchEventIdempotency, MatchServerCredentialEncryption, MatchServerBookingOverlap, VerifyRconSignature, InternalApiRoutesPresent) ✓
- `make pest --exclude=Phase8` regression: full project 1103 PASS / 2 FAIL — the 2 FAILs are both inside Phase 8 (the Wave-0 RED stubs scheduled for 08-09/08-12) ✓
- ScrimE2EHappyPathTest still RED (plan 08-12 owns) ✓
- RconUnreachableFlagsManualTest still RED (plan 08-09 owns) ✓
- PHPStan L8 clean ✓
- Pint clean ✓

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
