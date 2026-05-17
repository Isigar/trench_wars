---
phase: 08-rcon-automation
plan: 02
subsystem: database-schema
tags: [migrations, postgres, btree-gist, exclude-constraint, check-constraint, rcon, idempotency, defence-in-depth]

# Dependency graph
requires:
  - phase: 04-matches-manual
    provides: matches + match_results tables (Phase 8 ALTERs them — adds source enum + manual_entry_required flag)
  - phase: 05-discord-bot-v1
    provides: discord_outbound_messages table + doutmsg_message_type_chk CHECK (Phase 8 extends with match_result_announce)
  - phase: 08-01
    provides: 4 factory stubs + Phase8 RED stubs + lang/en/rcon.php — this plan lands the migrations they reference
provides:
  - match_servers table (UUID PK + credentials_encrypted jsonb — encrypted:array cast lands in plan 08-03)
  - match_server_bookings table with btree_gist extension + EXCLUDE USING gist (T-08-02-01 — DB-tier overlap prevention)
  - match_events table with composite UNIQUE (match_id, crcon_stream_id) (T-08-02-03 — idempotent re-ingest)
  - match_player_stats table with composite UNIQUE (match_id, player_id) (Pitfall 4 — aggregator upsert idempotent)
  - match_results.source enum column (RESEARCH Gap A1 closed)
  - matches.manual_entry_required boolean + partial index (D-019 manual-entry path)
  - discord_outbound message_type CHECK extended for match_result_announce
affects:
  - 08-03-PLAN.md (MatchServer + MatchServerBooking models — encrypted:array cast + tstzrange Eloquent helpers consume this schema)
  - 08-04-PLAN.md (MatchPlayerStat + MatchEvent models — composite UNIQUEs key the upsert pattern)
  - 08-05-PLAN.md (ValidateRconHmacSignature middleware — relies on /api/internal/match/{id}/events landing on this match_events table)
  - 08-07-PLAN.md (MatchEventIngestService — turns MatchEventIdempotencyTest GREEN by relying on the composite UNIQUE)
  - 08-08-PLAN.md (MatchResultService::upsertFromRcon — turns ManualOverrideWinsTest GREEN by relying on match_results.source CHECK; MatchPlayerStatAggregatorService relies on (match_id, player_id) UNIQUE)
  - 08-09-PLAN.md (Filament MatchServerResource — consumes match_servers schema; manual_entry_required widget reads partial index)
  - 08-11-PLAN.md (booking-poller — flips matches.manual_entry_required on RCON failure path; RconUnreachableFlagsManualTest turns GREEN)
  - 08-12-PLAN.md (Phase 8 capstone — ScrimE2EHappyPathTest exercises all 6 migrations end-to-end)

# Tech tracking
tech-stack:
  added:
    - "Postgres btree_gist extension (CREATE EXTENSION IF NOT EXISTS inside match_server_bookings migration — Phase 1 Pitfall 5 idiom)"
  patterns:
    - "EXCLUDE USING gist on (scalar WITH =, tstzrange(...) WITH &&) WHERE status='active' — DB-tier composite overlap prevention with cancellation-frees-slot semantics (mirrors Phase 2 D-009 partial-UNIQUE pattern for clan memberships)"
    - "Half-open [) tstzrange — back-to-back bookings sharing an endpoint don't conflict (e.g. 18:00 end + 18:00 start both allowed)"
    - "Composite UNIQUE (match_id, foreign_id) for idempotent upsert keys — pattern reused for match_events (crcon_stream_id) AND match_player_stats (player_id); both protect against worker reconnect double-write"
    - "ALTER-CHECK extension idiom for enum-as-CHECK columns: DROP CONSTRAINT IF EXISTS + ADD CONSTRAINT with full new IN-list (Postgres has no ALTER CONSTRAINT … MODIFY) — Phase 5/6/7/8 all use this same shape"
    - "Partial index on boolean flag column `WHERE flag = true` — only flagged rows queried by admin dashboard widget; index stays small (matches.manual_entry_required pattern, will repeat in any future is_X boolean flag scenario)"
    - "Defence-in-depth CHECK on enum-like text columns (last_test_status, status, source, event_type) — the database CHECK is the last line of defence if the service layer is bypassed (T-08-02-02 + Phase 4/5/6/7 precedent)"

key-files:
  created:
    - apps/web/database/migrations/2026_05_16_100000_create_match_servers_table.php
    - apps/web/database/migrations/2026_05_16_100100_create_match_server_bookings_table.php
    - apps/web/database/migrations/2026_05_16_100200_create_match_player_stats_table.php
    - apps/web/database/migrations/2026_05_16_100300_create_match_events_table.php
    - apps/web/database/migrations/2026_05_16_100400_add_source_and_manual_entry_required_to_matches_and_results.php
    - apps/web/database/migrations/2026_05_16_100500_extend_discord_outbound_message_types_for_match_result_announce.php
  modified: []
  # Schema-level changes only — the 4 factory stubs + 10 Pest RED stubs from plan 08-01 remain
  # untouched. Migrations alter columns/constraints on existing tables (match_results, matches,
  # discord_outbound_messages) but do NOT modify any source file.

key-decisions:
  - "Two CHECK constraints on match_server_bookings (status enum + reserved_to > reserved_from) instead of relying on the EXCLUDE alone. Defence-in-depth: the EXCLUDE blocks overlapping ACTIVE bookings, but a malformed inverted range (e.g. reserved_to < reserved_from from a Filament form bug) would silently bypass the EXCLUDE check (Postgres only invokes the index on validly-ordered ranges). The range_check CHECK is the second line of defence."
  - "match_events.ingested_at uses `useCurrent()` (DEFAULT now() at the DB layer) — NOT app-layer timestamps. The aggregator (plan 08-08) replays the event stream in temporal order using occurred_at, so ingested_at is purely for ops/debugging (when did web see this event vs when CRCON observed it?). DB-side default removes the chance of the worker forgetting to send it."
  - "Indexes added for query workloads NOT mentioned in the plan but obviously needed: msb_server_window_idx (server_id, reserved_from) supports calendar-grid queries; msb_match_idx (match_id) supports the match-detail page query; match_events_aggregator_idx (match_id, event_type, occurred_at) supports the aggregator scan pattern (Rule 2 — missing critical functionality)."
  - "down() does NOT drop btree_gist extension. Other tables added in later phases may consume it; CREATE EXTENSION IF NOT EXISTS on up() is idempotent. Dropping the extension on rollback would cause silent breakage if a Phase 9+ migration depends on it. Same posture as the 0001 enable_postgres_extensions migration leaves uuid-ossp/citext in place across rollbacks."

# Metrics
duration: 10min
completed: 2026-05-14
---

# Phase 8 Plan 2: Wave 1 — Migrations Summary

**6 Postgres migrations land the Phase 8 schema floor: match_servers, match_server_bookings (with btree_gist EXCLUDE — back-to-back OK, cancelled-frees-slot), match_events + match_player_stats (composite UNIQUEs for idempotent upsert), match_results.source enum (RESEARCH Gap A1 closed — Phase 4 did NOT ship it), matches.manual_entry_required + partial index, and discord_outbound CHECK extended for match_result_announce. migrate:fresh + functional EXCLUDE/CHECK probes all GREEN; Phase 1-7 regression: 936 PASS.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-05-14T03:31:04Z
- **Completed:** 2026-05-14T03:41:04Z
- **Tasks:** 2 / 2
- **Files created:** 6 (all in `apps/web/database/migrations/`)
- **Files modified:** 0
- **Commits:** 2 (task 1: `ab5c1b9`; task 2: `cc57b5a`)

## Accomplishments

### Tables created (4 new)

1. **`match_servers`** — UUID PK + `gen_random_uuid()` default; `name`, `host`, `port_rcon`, `region`, `credentials_encrypted` jsonb (Laravel `encrypted:array` cast lands in plan 08-03 model — T-08-02-04 mitigation); `is_active` flag; `last_test_at` + `last_test_status` ('ok'|'error' CHECK) + `last_test_error` for Filament "Test Connection" widget.
2. **`match_server_bookings`** — `CREATE EXTENSION IF NOT EXISTS btree_gist` inside the migration (Phase 1 Pitfall 5 / Pitfall 7 idiom). FK matrix: `match_id` cascadeOnDelete, `server_id` restrictOnDelete. **EXCLUDE constraint `match_server_bookings_no_overlap`**: `EXCLUDE USING gist (server_id WITH =, tstzrange(reserved_from, reserved_to, '[)') WITH &&) WHERE (status = 'active')` — T-08-02-01 mitigation. Half-open `[)` ⇒ back-to-back bookings allowed; partial `WHERE status='active'` ⇒ cancelled bookings free their slot. Plus secondary CHECKs: status enum + `reserved_to > reserved_from` (defence-in-depth — see Decisions). Indexes on `(server_id, reserved_from)` + `(match_id)` for calendar + match-detail queries.
3. **`match_player_stats`** — composite UNIQUE `mps_match_player_unique (match_id, player_id)` for idempotent aggregator upsert (Pitfall 4 — aggregator runs ONCE on match_end, NOT per event). Non-negative CHECK on all four counters (kills/deaths/team_kills/score). FK matrix: `match_id` cascadeOnDelete, `player_id` restrictOnDelete (preserve stat history).
4. **`match_events`** — composite UNIQUE `match_events_match_stream_unique (match_id, crcon_stream_id)` for T-08-02-03 idempotent re-ingest. CHECK on `event_type` with all 10 canonical values mirroring `lang/en/rcon.php events.types.*` (Wave 0 plan 08-01). Aggregator index `(match_id, event_type, occurred_at)` for plan 08-08 scan-by-type-in-temporal-order pattern. `ingested_at` defaults to `now()` at DB layer (Decision §2).

### Tables altered (3 existing)

5. **`match_results.source`** added — TEXT DEFAULT `'manual'` + CHECK in (`'manual'`, `'rcon'`). **RESEARCH Gap A1 closed**: CONTEXT.md claimed Phase 4 shipped this column; file inspection of `2026_05_14_100300_create_match_results_table.php` proves it did not. T-08-02-02 defence-in-depth: `MatchResultService::upsertFromRcon` (plan 08-08) refuses to overwrite rows where `source='manual'`. Existing Phase 4 rows backfilled to `'manual'` automatically by DEFAULT clause.
6. **`matches.manual_entry_required`** added — BOOLEAN DEFAULT `false` + partial index `matches_manual_entry_required_idx (manual_entry_required) WHERE manual_entry_required = true`. D-019: flipped TRUE when worker reports RCON unreachable for a played match (plan 08-11). Partial index keeps small because only the flagged rows are ever queried by the admin dashboard widget.
7. **`discord_outbound_messages.doutmsg_message_type_chk`** extended — appends `'match_result_announce'` to the existing 7-value list (`match_announce`, `role_sync`, `generic`, `bracket_result_announce`, `tournament_announce`, `tournament_announce_update`, `article_announce` → +`match_result_announce`). down() restores the Phase 7 baseline verbatim.

### Verification probes (all GREEN)

**Structural probes (Task 1):**
- `SELECT extname FROM pg_extension WHERE extname='btree_gist'` → `btree_gist` ✓
- 4 tables present via `information_schema.tables` ✓
- `\d match_server_bookings` shows EXCLUDE constraint with correct expression ✓
- Both composite UNIQUEs (`match_events_match_stream_unique`, `mps_match_player_unique`) visible ✓
- All 5 CHECK constraints (`match_servers_last_test_status_check`, `match_server_bookings_status_check`, `match_server_bookings_range_check`, `match_events_type_check`, `match_player_stats_nonneg_check`) visible with correct definitions ✓

**Functional probes (Task 1 — EXCLUDE semantics):**
- Overlapping booking on same active server → `exclusion_violation` raised ✓
- Back-to-back booking (start == prior end) → accepted (half-open `[)` works) ✓
- Cancelled booking does NOT block new overlap (partial `WHERE status='active'` works) ✓

**Structural + functional probes (Task 2):**
- `match_results.source` default `'manual'::text` ✓
- `matches.manual_entry_required` default `false` ✓
- `match_results_source_check` accepts `'manual'` and `'rcon'`, rejects `'evil'` with `check_violation` ✓
- `doutmsg_message_type_chk` accepts `'match_result_announce'`, rejects `'bogus_type'` with `check_violation` ✓
- Partial index `matches_manual_entry_required_idx` listed in `\d matches` ✓

**Regression (Phase 1-7):**
- `pest --filter='Admin|I18n'` → **199 passed** (565 assertions) ✓
- Full feature suite → **936 passed**, 10 failed (all 10 failures are Phase 8 Wave 0 RED stubs asserting `expect(true)->toBeFalse()` — expected; will turn GREEN in plans 08-05..08-12) ✓

**Quality gates:**
- Pint → 6 files PASS (one auto-fix applied during Task 1: multi-line concat alignment in `match_server_bookings` migration normalised to canonical Laravel preset; same again during Task 2 for `extend_discord_outbound_message_types_for_match_result_announce`) ✓
- PHPStan L8 → 0 errors on all 6 files ✓

## Task Commits

1. **Task 1: Create match_servers + match_server_bookings + match_events + match_player_stats migrations** — `ab5c1b9` (feat)
2. **Task 2: Add source enum + manual_entry_required + extend discord_outbound CHECK** — `cc57b5a` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Migrations (6)
- `apps/web/database/migrations/2026_05_16_100000_create_match_servers_table.php`
- `apps/web/database/migrations/2026_05_16_100100_create_match_server_bookings_table.php` (contains `CREATE EXTENSION IF NOT EXISTS btree_gist` + EXCLUDE constraint)
- `apps/web/database/migrations/2026_05_16_100200_create_match_player_stats_table.php`
- `apps/web/database/migrations/2026_05_16_100300_create_match_events_table.php`
- `apps/web/database/migrations/2026_05_16_100400_add_source_and_manual_entry_required_to_matches_and_results.php`
- `apps/web/database/migrations/2026_05_16_100500_extend_discord_outbound_message_types_for_match_result_announce.php`

## Decisions Made

- **Two CHECK constraints on match_server_bookings (status + range), not just EXCLUDE.** The EXCLUDE only blocks overlapping ACTIVE bookings on validly-ordered ranges. A malformed inverted range (e.g. `reserved_to < reserved_from` from a buggy Filament form) would silently bypass the EXCLUDE check because Postgres won't even invoke the gist index on an empty range. `match_server_bookings_range_check` is the second line of defence.
- **`match_events.ingested_at` defaults to `now()` at the DB layer, NOT app-layer.** The aggregator (plan 08-08) replays the event stream in temporal order using `occurred_at`. `ingested_at` is purely for ops/debugging (web-saw-event vs CRCON-observed-event). DB-side default eliminates the chance of the worker forgetting to populate it.
- **Added 3 indexes the plan did not explicitly mandate (Rule 2 — missing critical functionality).** `msb_server_window_idx (server_id, reserved_from)` supports calendar-grid queries by server in plan 08-09 Filament. `msb_match_idx (match_id)` supports match-detail page queries. `match_events_aggregator_idx (match_id, event_type, occurred_at)` is the plan's own task-1 spec (`<behavior>`) but listed under "index ..." not in the plan's `<artifacts>` block — explicitly making it visible here.
- **down() does NOT drop btree_gist extension.** Other tables added in later phases may consume it. `CREATE EXTENSION IF NOT EXISTS` on up() is idempotent. Mirrors the 0001 enable_postgres_extensions posture (uuid-ossp/citext stay across rollbacks).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Plan `<interfaces>` referenced non-existent constraint name + wrong baseline enum list**
- **Found during:** Task 2 (authoring `2026_05_16_100500_extend_discord_outbound_message_types_for_match_result_announce.php`)
- **Issue:** The plan's `<interfaces>` block (line 113-115) shows the CHECK extension idiom with constraint name `discord_outbound_messages_message_type_check` and a 6-value baseline `(clan_announce, match_announce, signup_open, signup_reminder, tournament_announce, article_announce)`. Neither matches reality:
  - The actual constraint name is `doutmsg_message_type_chk` (set by the original Phase 5 migration `2026_05_13_170625_create_discord_outbound_messages_table.php` line 66).
  - The actual 7-value baseline (as of `2026_05_15_120400_..._article_announce.php`) is `(match_announce, role_sync, generic, bracket_result_announce, tournament_announce, tournament_announce_update, article_announce)`. The plan's list contains values (`clan_announce`, `signup_open`, `signup_reminder`) that NEVER existed in this constraint.
  - Authoring the migration literally per the plan would have failed at runtime with `ERROR: constraint ... does not exist` on the DROP step, and after re-creating with the wrong baseline would have broken all existing rows that use values like `bracket_result_announce`, `role_sync`, etc.
- **Fix:** Aligned with on-disk reality. Used the correct constraint name (`doutmsg_message_type_chk`) and the actual 7-value baseline as the `down()` target. Same Rule 1 deviation pattern Phase 7 plan 07-02 followed (its plan also referenced a non-existent baseline value `match_announce_update` — see comment block in `2026_05_15_120400_..._article_announce.php`).
- **Files modified:** `apps/web/database/migrations/2026_05_16_100500_extend_discord_outbound_message_types_for_match_result_announce.php` (the file authored from the corrected idiom).
- **Verification:** Functional probe — `match_result_announce` accepted, `bogus_type` rejected with `check_violation`. Phase 1-7 regression: 199 + 936 PASS.
- **Committed in:** `cc57b5a` (Task 2 commit; commit message + the migration's `NOTE:` doc-block both call out the deviation).

**2. [Rule 2 - Missing critical functionality] Added indexes the plan listed in `<behavior>` but omitted from `<artifacts>`**
- **Found during:** Task 1 (authoring `match_server_bookings` + `match_events`).
- **Issue:** Plan's task 1 `<behavior>` says match_events needs an "index (match_id, event_type, occurred_at) for aggregator query" but the index appears nowhere in `<artifacts>` or `must_haves`. Similarly, `match_server_bookings` needs supporting indexes for the obvious admin query workloads — `server_id` for "show bookings for this server" and `match_id` for "show booking for this match" — but neither is in the plan text. Without them, Postgres has to seq-scan + filter for every Filament query in plan 08-09.
- **Fix:** Added 3 indexes inside the new migrations:
  - `msb_server_window_idx (server_id, reserved_from)` on `match_server_bookings`
  - `msb_match_idx (match_id)` on `match_server_bookings`
  - `match_events_aggregator_idx (match_id, event_type, occurred_at)` on `match_events`
- **Files modified:** Inline in the original migration files (not a separate migration).
- **Verification:** `\d match_server_bookings` and `\d match_events` show all 3 indexes.
- **Committed in:** `ab5c1b9` (Task 1 commit).

**3. [Rule 2 - Missing critical functionality] Added `match_server_bookings_range_check` for inverted-range defence-in-depth**
- **Found during:** Task 1 (designing EXCLUDE constraint).
- **Issue:** Plan specifies the EXCLUDE constraint but does not require a separate CHECK on `reserved_to > reserved_from`. The EXCLUDE blocks overlap among *validly-ordered* ranges only — Postgres treats an empty `tstzrange(b, a)` where `b > a` as the empty range, which never overlaps with anything, so an inverted-range row (e.g. from a buggy Filament form posting `reserved_from = 2026-05-20`, `reserved_to = 2026-05-19`) would slip past the EXCLUDE silently.
- **Fix:** Added `match_server_bookings_range_check CHECK (reserved_to > reserved_from)` — defence-in-depth, mirrors the `match_results_scores_nonneg_check` (Phase 4) pattern.
- **Files modified:** Inline in `2026_05_16_100100_create_match_server_bookings_table.php`.
- **Verification:** Constraint visible via `pg_get_constraintdef`; not tested behaviourally in this plan but blocks the documented attack class.
- **Committed in:** `ab5c1b9` (Task 1 commit).

### Auth Gates

None — schema-only plan, no external service authentication required.

### Architectural Changes (Rule 4 — required user decision)

None — all deviations are Rule 1 (bug-fix) or Rule 2 (missing-critical) auto-fixes within the plan's stated scope.

---

**Total deviations:** 3 auto-fixed (1 Rule 1 — bug; 2 Rule 2 — missing-critical).
**Impact on plan:** All 6 migrations land with the exact filenames the plan specified. All `must_haves.truths` + `must_haves.artifacts` met. The Rule 1 deviation strengthens the plan: aligning with the actual on-disk Phase 5-7 baseline preserves Phase 5/6/7 message types (would have been clobbered if implemented literally). The Rule 2 deviations strengthen the plan: added indexes + range CHECK close T-08-02-01 inverted-range escape + improve plan 08-09 admin query performance.

## Issues Encountered

- **`make` is not on PATH** (CLAUDE.md §1 documents the Makefile aliases as the canonical container-only command surface, but `make` itself was not installed in this session's host environment). Resolved by invoking the underlying `docker compose exec web ...` commands directly — still D-021-compliant (all commands ran inside the web/postgres containers, never on the host).
- **Pint multi-line concat formatting**: authored migrations used `.` at end-of-prior-line for multi-line string concatenation; Laravel preset Pint moves `.` to start-of-line. Auto-fixed both times (Task 1 `match_server_bookings`, Task 2 `extend_discord_outbound_message_types_for_match_result_announce`). No semantic change.

## User Setup Required

None — purely schema migrations. Existing Phase 4 rows in `match_results` are backfilled to `source='manual'` automatically by the DEFAULT clause; existing rows in `matches` are backfilled to `manual_entry_required=false` automatically. discord_outbound CHECK extension is additive (no existing rows have invalid types after the DROP+ADD round-trip).

## Next Phase Readiness

- **Plan 08-03 (MatchServer + MatchServerBooking models) is unblocked.** `match_servers` + `match_server_bookings` tables exist with the exact columns the model casts target — `credentials_encrypted` jsonb is ready for `encrypted:array` cast, `reserved_from`/`reserved_to` are `timestamptz` ready for Carbon hydration, `MatchServerCredentialEncryptionTest` + `MatchServerBookingOverlapTest` (both currently RED) can turn GREEN with model + factory wiring.
- **Plan 08-04 (MatchEvent + MatchPlayerStat models) is unblocked.** Both tables exist with composite UNIQUEs; factory stubs from plan 08-01 will fill out their `definition()` against this schema.
- **Plan 08-07 (MatchEventIngestService) is unblocked.** `match_events.crcon_stream_id` UNIQUE collision is the idempotent-write key; `MatchEventIdempotencyTest` RED stub can turn GREEN.
- **Plan 08-08 (MatchResultService + MatchPlayerStatAggregator) is unblocked.** `match_results.source` CHECK is the DB-tier guard for `ManualOverrideWinsTest`; `(match_id, player_id)` UNIQUE keys the aggregator upsert.
- **Plan 08-11 (booking-poller manual-entry path) is unblocked.** `matches.manual_entry_required` column + partial index ready for `RconUnreachableFlagsManualTest`.
- **No blockers.** Phase 1-7 baseline preserved (199 Admin/I18n + 926 non-Phase8 feature tests green); zero schema regressions on existing tables (only ADD COLUMN + extend CHECK, no DROP); no CLAUDE.md violations.

## Self-Check: PASSED

Verified before finalising:

**Files created (6):** Confirmed via filesystem checks — all 6 migration files exist at the paths declared in `files_modified` frontmatter.

**Commits (2):** Both `ab5c1b9` and `cc57b5a` visible in `git log --oneline -3`. No deletions in either commit (`git diff --diff-filter=D --name-only` returns empty for both).

**Quality gates re-run before SUMMARY:**
- `php artisan migrate:fresh --seed` → all 42 migrations apply clean (including the 6 new) ✓
- `pest --filter='Admin|I18n'` → 199 PASS ✓
- `pest` full feature suite → 936 PASS, 10 FAIL (10 = Phase 8 Wave 0 RED stubs; expected — they assert `expect(true)->toBeFalse()` until later plans wire them GREEN) ✓
- `pint --test database/migrations/2026_05_16_*` → 6 files PASS ✓
- `phpstan analyse database/migrations/2026_05_16_*` → 0 errors ✓

**Behavioural probes re-run before SUMMARY:**
- EXCLUDE: overlap rejected ✓; back-to-back accepted ✓; cancelled-then-overlap accepted ✓
- source CHECK: 'manual' + 'rcon' accepted ✓; 'evil' rejected ✓
- discord_outbound CHECK: 'match_result_announce' accepted ✓; 'bogus_type' rejected ✓
- 4 tables present, btree_gist installed, partial index visible ✓

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
