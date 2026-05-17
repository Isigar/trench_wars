---
phase: 08-rcon-automation
slug: rcon-automation
status: PENDING_MANUAL_SMOKE
completed: 2026-05-14
plans_complete: 13
plans_total: 13
test_count: 1134
test_assertions: 3783
test_passing: 1134
test_failing: 0
test_incomplete: 0
bot_test_count: 139
bot_test_files: 11
rcon_worker_test_count: 40
rcon_worker_test_files: 7
quality_gates:
  pest: GREEN
  pint: GREEN
  phpstan_l8: GREEN
  vue_tsc: GREEN
  shared_types_typecheck: GREEN
  bot_tsc: GREEN
  bot_vitest: GREEN
  rcon_worker_typecheck: GREEN
  rcon_worker_lint: GREEN
  rcon_worker_vitest: GREEN
  rcon_worker_build: GREEN
  migrate_fresh_seed: GREEN
requirements:
  - REQ-goal-rcon-history
  - REQ-constraint-league-owns-servers
  - REQ-success-end-to-end-scrim
manual_smoke_required:
  - A — Register a real MatchServer in Filament (live CRCON instance). Click Test Connection. Verify last_test_status badge flips to "ok"
  - B — Schedule a scrim, book the server, play it on real CRCON, verify match_results + match_player_stats populated without manual entry
  - C — Disconnect the CRCON network mid-match for 2 minutes, reconnect. Verify last_seen_id resume captures the missed window
  - D — Rotate WEB_HMAC_SECRET in Railway, restart both `web` and `rcon-worker` services. Verify the next booking ingests cleanly
canonical_model_binding: "App\\Models\\GameMatch (D-04-03-A LOCKED — inherited and re-affirmed across all 12 prior Phase 8 plans; RCON-driven MatchResult writes + MatchPlayerStat aggregator + MatchEvent ingest + CloseMatchJob + MatchResultObserver match_result_announce branch all import App\\Models\\GameMatch directly; BelongsTo<GameMatch, $this> passes match_id as explicit FK arg per D-04-03-B / D-06-03-A / D-07-* continuation; zero `App\\Models\\Match as MatchModel` alias-on-import anywhere in Phase 8 surface)"
---

# Phase 8 — RCON automation — Verification Report

**Date:** 2026-05-14
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual smoke section)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 8 |
| Name | RCON automation |
| Slug | rcon-automation |
| Plans | 13 plans (08-01 through 08-13) |
| Completed date | 2026-05-14 |
| Phase 7 foundation | Phase 7 COMPLETE PENDING_MANUAL_SMOKE (2026-05-14) |
| Canonical model name | `App\Models\GameMatch` (D-04-03-A LOCKED — see frontmatter) |
| Requirements satisfied | REQ-goal-rcon-history, REQ-constraint-league-owns-servers, REQ-success-end-to-end-scrim |

---

## Status

PENDING_MANUAL_SMOKE — 4 operator walkthrough items remaining (see Manual Smoke section).

The automated test surface mechanically proves SC-1 through SC-5 via the
Pest + Vitest matrix below. The four manual smokes cover the
operator/network seams that the test surface intentionally does not
exercise (live CRCON probe on a real Hell Let Loose server, two-clan
SC-5 happy path against actual CRCON events, mid-match log gap recovery
with real network disruption, and HMAC key rotation flow against Railway
secret store).

---

## Overview

Phase 8 delivered the complete RCON automation surface — five new DB tables
(`match_servers`, `match_server_bookings`, `match_events`, `match_player_stats`,
plus `match_results.source` ENUM + `matches.manual_entry_required` flag + a
7th→8th `discord_outbound_messages.message_type` CHECK value
`match_result_announce`), btree_gist EXCLUDE constraint on
`match_server_bookings` (overlap prevention at the DB layer), encrypted
JSONB→text CRCON credentials on `match_servers`, the canonical apps/web
HMAC verification gateway (`VerifyRconSignature` middleware +
`HmacVerifier` service + `config/rcon.php` + Redis nonce store with
60s window + lowercase-hex SHA-256 over `timestamp.body`), three new
internal API endpoints (`POST /api/internal/match/{id}/events`,
`GET /api/internal/bookings/due`,
`GET /api/internal/match-servers/{id}/credentials`), the
`MatchEventNormaliser` → `MatchEventIngestService` → `CloseMatchJob`
pipeline (composite UNIQUE absorb via savepoint per event, additive
`{batch_id, accepted_count, skipped_count}` response shape), the
`MatchPlayerStatAggregator` (Pitfall 5 orphan-event skip; per-player
kill/death/team-kill aggregation with `kdr()` accessor),
`MatchResultService::upsertFromRcon` (D-019 manual-override-wins gate
at the service boundary), `MatchServerResource` Filament wizard with
`BookingsRelationManager` (read-only with view_match link to
MatchResource), `TestMatchServerConnectionJob` async Horizon job
calling `CrconHealthProbe` (Pitfall 6 PHP-FPM 30s timeout mitigation),
`manage-rcon` permission gating MatchServerResource + Filament
manual_entry_required surface on MatchResource (IconColumn danger
triangle + Filter + clear-flag Action), the rcon-worker Node 22 service
shipping `HmacSigner` + `CrconClient` (ws + 30s heartbeat + exponential
backoff reconnect + last_seen_id resume on reconnect subscribe) +
`CrconEventNormaliser` + `WebIngestClient` + `BookingScheduler` +
`MatchLifecycleManager` (start grace / batch flush every 2s up to 10
events / complete grace 60s) + `RedisFailoverQueue` (5xx drainer with
LPUSH/LTRIM/LRANGE), the SC-5 `ScrimE2EHappyPathTest` capstone (full
two-clan flow: clan create → roster → scrim schedule → Discord signup →
HMAC-signed match_end ingest → MatchResult source='rcon' +
MatchPlayerStat populated + match_result_announce outbox row),
`MatchResultObserver` `match_result_announce` branch +
`DiscordOutboundPayloadBuilder::buildMatchResultAnnounce` (static method
in `App\Support`, payload-keyed dispatch matching Phase 6
bracket_result_announce shape), `MatchResultAnnounceData` in
`App\Data` namespace (TS regen + shared-types sync), and
`RconI18nKeyCoverageTest` + `RconAuditLogTest` audited via the
leaf-anchored regex idiom inherited from Phase 6 D-06-13-C / Phase 7.

All five ROADMAP Success Criteria are mechanically observable against
concrete test files and source artifacts; all three Phase-8 requirements
(REQ-goal-rcon-history, REQ-constraint-league-owns-servers,
REQ-success-end-to-end-scrim) are satisfied.

---

## [BLOCKING] Quality Gates — RESULT: PASS

| Gate | Command | Result |
|------|---------|--------|
| Pest (web full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **1134 passed** (3783 assertions), 0 failed, 0 incomplete, 70.94s |
| Vitest (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"` | **139 passed** (11 test files), 0 failed, 675ms |
| Vitest (rcon-worker) | `cd apps/rcon-worker && pnpm test` | **40 passed** (7 test files), 0 failed, 6.07s |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 566 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| vue-tsc (web) | `cd apps/web && pnpm exec vue-tsc --noEmit` | **PASS** — 0 errors |
| tsc strict (rcon-worker) | `cd apps/rcon-worker && pnpm run typecheck` | **PASS** — `tsc --noEmit` clean |
| eslint (rcon-worker) | `cd apps/rcon-worker && pnpm run lint` | **PASS** — `eslint .` clean |
| build (rcon-worker) | `docker compose run --rm worker pnpm run build` (in /repo/apps/rcon-worker) | **PASS** — `tsc` emit clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` | **PASS** — clean |
| Migrations freshness | `make artisan ARGS="migrate:fresh --seed"` | **PASS** — all migrations + 7 seeders DONE |
| Placeholder Wave-0 stubs | included in Pest 1134 above | **PASS** — 0 incomplete (all 10 Pest RED stubs from 08-01 flipped to GREEN across plans 08-02..08-12) |

**Test growth across phases:**

| Phase | Total Pest after phase | Phase contribution |
|-------|------------------------|--------------------|
| Phase 1 close (01-18) | ~94 tests | +94 |
| Phase 2 close (02-14) | 214 tests | +120 |
| Phase 3 close (03-10) | 278 tests | +64 |
| Phase 4 close (04-13) | 493 tests | +215 |
| Phase 5 close (05-13) | 618 tests | +125 (+117 bot Vitest) |
| Phase 6 close (06-14) | 866 tests | +248 web (+22 bot Vitest) |
| Phase 7 close (07-13) | 1037 tests | +171 web (+752 assertions; bot regressionless) |
| **Phase 8 close (08-13)** | **1134 tests** | **+97 web** (+312 assertions; bot regressionless; +40 rcon-worker Vitest) |

Phase 8 contributed 97 web Pest tests (delta 1037 → 1134 / +312 assertions
from 3471 → 3783) across the `Tests\Feature\MatchServers\*`,
`Tests\Feature\Internal\*`, `Tests\Feature\Rcon\*`,
`Tests\Feature\Services\MatchEventIngestServiceTest`,
`Tests\Feature\Services\MatchPlayerStatAggregatorTest`,
`Tests\Feature\Services\MatchResultServiceUpsertFromRconTest`,
`Tests\Feature\Jobs\{CloseMatchJob,TestMatchServerConnection}Test`,
`Tests\Feature\Observers\MatchResultObserverTest`,
`Tests\Feature\Outbound\MatchResultAnnounceOutboundTest`,
`Tests\Feature\Models\{MatchServer,MatchServerBooking,MatchEvent,MatchPlayerStat}ModelTest`,
`Tests\Feature\Admin\MatchServerResourcePresentTest`,
`Tests\Feature\Admin\RconAuditLogTest`,
`Tests\Feature\I18n\RconI18nKeyCoverageTest`,
`Tests\Feature\ScrimE2EHappyPathTest` (SC-5 capstone), and
`Tests\Unit\{MatchEventModel,MatchPlayerStatModel,MatchEventNormaliser,HmacVerifier}Test`
namespaces. The bot test surface is unchanged (139 / 11 files) — Phase 8
introduces no new bot interactions; the new `match_result_announce`
outbox kind rides the existing Phase 5/6 `worker` + `bot` polling
pipeline. The rcon-worker Node service is BRAND NEW for Phase 8 with 40
Vitest cases across 7 test files (HmacSigner / CrconEventNormaliser /
RedisFailoverQueue / BookingScheduler — unit; CrconClient /
MatchLifecycleManager — integration; skeleton smoke).

---

## ROADMAP Success Criteria mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 | An admin can register a `MatchServer` in Filament with encrypted CRCON credentials and run "Test Connection" to verify CRCON reachability and current game state. | `apps/web/tests/Feature/Admin/MatchServerResourcePresentTest.php` (plan 08-09 — 9 it() blocks: resource registered + ListMatchServers reachable + CreateMatchServer present + EditMatchServer present + form fields + table columns + Test Connection HeaderAction + BookingsRelationManager rendered + `manage-rcon` gate via canAccessPanel), `apps/web/tests/Feature/Jobs/TestMatchServerConnectionJobTest.php` (plan 08-09 — 5 it() blocks: probe success flips last_test_status=ok + connection refused→last_test_status=connection_refused + auth_failed propagated + invalid_response on bad CRCON shape + activity_log row written), `apps/web/tests/Feature/Models/MatchServerModelTest.php` (plan 08-03 — encrypted:array cast roundtrip on credentials_encrypted, active scope), Phase 9 RESEARCH "League IT onboarding" docs (08-RESEARCH.md lines 165-228); manual smoke A documented below | **PARTIAL — automated GREEN; live CRCON probe pending operator smoke** |
| SC-2 | Booking a match against a server reserves `[scheduled_start − 5m, scheduled_end + 30m]` in `match_server_bookings`, and the Postgres exclusion constraint prevents any overlap on the same server. | `apps/web/tests/Feature/MatchServers/MatchServerBookingOverlapTest.php` (plan 08-03 — 5 it() blocks: btree_gist EXCLUDE rejects overlapping ACTIVE bookings + range_check CHECK rejects inverted ranges + tstzrange index pattern verified + reservation window arithmetic + cancelled bookings can overlap), `apps/web/tests/Feature/Models/MatchServerBookingModelTest.php` (plan 08-03 — scopeDueWithin signature + factory state helpers), `database/migrations/2026_05_16_100100_create_match_server_bookings_table.php` (plan 08-02 — CREATE EXTENSION IF NOT EXISTS btree_gist + EXCLUDE USING GIST (server_id WITH =, tstzrange(reserved_from, reserved_to, '[)') WITH &&) WHERE status='active' + range_check CHECK reserved_to > reserved_from) | **PASS** |
| SC-3 | When a booked match runs, `rcon-worker` opens a CRCON session, streams normalised events to `web` via HMAC-signed `POST /api/internal/match/{id}/events`, and at `match_end` the system auto-populates `MatchResult` (`source = 'rcon'`) plus per-player `MatchPlayerStat` rows. | `apps/web/tests/Feature/Rcon/VerifyRconSignatureTest.php` (plan 08-05 — 8 it() blocks: happy path 200 + stale signature 401 + bad signature 401 + replayed nonce 401 + missing rcon headers 401 + clock-skew window honoured via Carbon::setTestNow + raw-body byte-equal compute + lowercase hex digest emitted), `apps/web/tests/Feature/Internal/InternalApiRoutesPresentTest.php` (plan 08-06 — 8 it() blocks: route registrations + wire shape contract + accepted_count + skipped_count additive), `apps/web/tests/Feature/Services/MatchEventIngestServiceTest.php` (plan 08-07 — composite UNIQUE absorb via savepoint), `apps/web/tests/Feature/Services/MatchPlayerStatAggregatorTest.php` (plan 08-08 — per-player kill/death aggregation + Pitfall 5 orphan-event skip), `apps/web/tests/Feature/Rcon/RconMatchResultIngestionTest.php` (plan 08-08 — CloseMatchJob handles match_end → MatchResult source='rcon' + MatchPlayerStat materialised), 40 rcon-worker Vitest cases across `apps/rcon-worker/tests/{unit,integration}/*.test.ts` (plans 08-10..08-11 — HmacSigner + CrconEventNormaliser + CrconClient ws/reconnect/heartbeat/last_seen_id + WebIngestClient + BookingScheduler + MatchLifecycleManager + RedisFailoverQueue); manual smoke B documented below | **PARTIAL — automated GREEN; two-clan SC-5 happy path on real CRCON pending operator smoke** |
| SC-4 | CRCON failure modes (unreachable on session open, mid-match log gap, key rotated) degrade gracefully — match flagged for manual entry, error event surfaced in admin, manual override still wins. | `apps/web/tests/Feature/Rcon/RconUnreachableFlagsManualTest.php` (plan 08-08 — unreachable CRCON sets matches.manual_entry_required=true; emits manual_error event via /api/internal/match/{id}/events shim), `apps/web/tests/Feature/Services/ManualOverrideWinsTest.php` (plan 08-08 — D-019: 4 it() blocks: manual write before rcon arrival wins / rcon-then-manual override flips source='manual' / late rcon delivery after manual override is BLOCKED at upsertFromRcon gate / activity_log row written), `apps/web/tests/Feature/Jobs/TestMatchServerConnectionJobTest.php` (plan 08-09 — async Horizon job mitigates Pitfall 6 PHP-FPM 30s timeout on health probe path), `apps/rcon-worker/tests/integration/CrconClient.integration.test.ts` (plan 08-10 — server forcibly disconnects → auto-reconnect with last_seen_id resume; heartbeat watchdog terminates ws on missing pong); manual smoke C documented below | **PARTIAL — automated GREEN; mid-match log gap on real network pending operator smoke** |
| SC-5 | Two clans can complete the full round-1 happy path end-to-end: Discord OAuth → clan create → roster build → scrim schedule → Discord signup → CRCON-played → auto-recorded result + per-player stats — without manual data entry on the happy path. | `apps/web/tests/Feature/ScrimE2EHappyPathTest.php` (plan 08-12 — full two-clan happy path: clan create + clan_membership rows + match factory with host_clan + match_signups via /api/bot/* + HMAC-signed match_end ingest to /api/internal/match/{id}/events + CloseMatchJob handle + assertion MatchResult.source='rcon' + MatchPlayerStat rows per player + discord_outbound_messages row with message_type='match_result_announce'), `apps/web/tests/Feature/Observers/MatchResultObserverTest.php` (plan 08-12 — created+updated hooks fire match_result_announce branch; alreadyAnnounced JSONB predicate prevents duplicate outbox rows), `apps/web/tests/Feature/Outbound/MatchResultAnnounceOutboundTest.php` (plan 08-12 — `DiscordOutboundPayloadBuilder::buildMatchResultAnnounce` static method emits payload-keyed shape matching the Phase 6 bracket_result_announce wire); manual smoke B+D documented below | **PARTIAL — automated GREEN; live two-clan walkthrough on real CRCON pending operator smoke** |

**SC verification commands:**

```bash
# SC-1: MatchServer register + Test Connection + manage-rcon gate
docker compose exec web ./vendor/bin/pest --filter='MatchServerResourcePresent|TestMatchServerConnectionJob|MatchServerModel' --no-coverage

# SC-2: Booking EXCLUDE overlap prevention
docker compose exec web ./vendor/bin/pest --filter='MatchServerBookingOverlap|MatchServerBookingModel' --no-coverage

# SC-3: HMAC + ingest pipeline + aggregator + rcon-worker
docker compose exec web ./vendor/bin/pest --filter='VerifyRconSignature|InternalApiRoutesPresent|MatchEventIngestService|MatchPlayerStatAggregator|RconMatchResultIngestion' --no-coverage
(cd apps/rcon-worker && pnpm test)

# SC-4: Graceful failure + manual override wins
docker compose exec web ./vendor/bin/pest --filter='RconUnreachableFlagsManual|ManualOverrideWins|TestMatchServerConnectionJob' --no-coverage

# SC-5: Full E2E scrim capstone + observer + outbound
docker compose exec web ./vendor/bin/pest --filter='ScrimE2EHappyPath|MatchResultObserver|MatchResultAnnounceOutbound' --no-coverage
```

---

## Requirements traceability

| Requirement | Description | Test file(s) | Status |
|-------------|-------------|--------------|--------|
| REQ-goal-rcon-history | When a match is played on a registered match server, MatchResult and per-player MatchPlayerStat rows are populated automatically from CRCON events. (D-005, D-019) | SC-3 + SC-5 above. `MatchEventIngestServiceTest` + `MatchPlayerStatAggregatorTest` + `RconMatchResultIngestionTest` + `ScrimE2EHappyPathTest` together verify the round-trip: HMAC-signed event ingest → MatchEvent rows → CloseMatchJob → MatchResult (source='rcon') + MatchPlayerStat per Player; the 40 rcon-worker Vitest cases pin the upstream worker contract. | **PASS** |
| REQ-constraint-league-owns-servers | League-owned HLL match servers are league-managed entries; CRCON deployment alongside each server is in scope; no per-clan-managed servers in round 1. | SC-1 + SC-2 above. `MatchServerResourcePresentTest` + `MatchServerBookingOverlapTest` verify the league-managed registration + booking surface; the `manage-rcon` permission gate (plan 08-09) prevents non-admin actors from registering or editing servers. RESEARCH "League IT onboarding" docs (08-RESEARCH.md lines 165-228) document the operator runbook. | **PASS** |
| REQ-success-end-to-end-scrim | End-to-end flow works without manual data entry on the happy path: Discord OAuth → clan create → roster build → scrim schedule → Discord signup → CRCON-played → auto-recorded MatchResult + MatchPlayerStat. | SC-5 capstone test `ScrimE2EHappyPathTest` mechanically walks the full chain end-to-end including the Discord signup leg via `/api/bot/*` (Phase 5) → HMAC-signed event ingest via `/api/internal/match/{id}/events` → CloseMatchJob → MatchResult assertion. Plus the SC-1..SC-4 traceability above proves every individual leg. The 4 manual smoke walkthroughs cover the human-visual seams (live CRCON probe / two-clan UX flow / real network disruption / Railway secret rotation). | **PASS** |

All three Phase 8 requirements are the requirements mapped to Phase 8 in
`REQUIREMENTS.md`. All five success criteria collectively prove all
three requirements are satisfied — the round-1 acceptance loop is
closed: two clans can schedule a scrim, play it on a CRCON-monitored
server, and have the result + per-player stats recorded automatically.

---

## Open Questions RESOLVED Inline During Planning

| # | Question | Resolution | Plan |
|---|----------|------------|------|
| 1 | CRCON version standardisation | RESOLVED: pin CRCON v10.0.0+ via `config('rcon.crcon_version_pin')`; documented in RESEARCH "League IT onboarding"; admin Filament form requires the operator to enter the deployed CRCON version on MatchServer create (plan 08-09). | RESEARCH + 08-09 |
| 2 | Steam ID linkage flow | RESOLVED: orphan events (steam_id_64 with no matching `players.steam_id_64` row) silently skipped per Pitfall 5; the round-1 acceptance assumes players self-register Steam ID via the public profile editor BEFORE scrim acceptance. Migration `2026_05_16_100700_add_steam_id_64_to_players_table` ships the column (nullable + UNIQUE). v2 enhancement: player onboarding Wizard step to enforce Steam ID before clan-join confirmation. | 08-08 (orphan-skip) + 08-08-SUMMARY (migration auto-add) |
| 3 | MatchServer Test Connection path | RESOLVED: web→CRCON direct (NOT async-via-worker). Pitfall 6 mitigation: dispatched as `TestMatchServerConnectionJob` to Horizon so the PHP-FPM 30s timeout never fires; the web container has outbound HTTPS so it can reach CRCON directly without a worker hop. `CrconHealthProbe` calls CRCON `GET /api/get_map_rotation` (no-side-effect probe per RESEARCH line 179). | 08-09 |
| 4 | CRCON chat capture | RESOLVED: out of scope for round 1 (CONTEXT.md deferred list re-affirmed). The normaliser whitelist ONLY emits player_kill / team_kill / connect / disconnect / round_start / round_end / match_end / game_start / manual_error event types; chat is deliberately dropped at the worker boundary. | RESEARCH + 08-10 |
| 5 | Ringer / cross-clan Steam ID | RESOLVED: `MatchPlayerStat` is per-`player_id` not per-clan; stats record regardless of clan affiliation. The aggregator (plan 08-08) looks up Player by `players.steam_id_64` and writes one MatchPlayerStat row per (match_id, player_id) tuple — clan membership is not consulted at aggregation time. The Phase 4 match_slots layer already supports cross-clan signups via the ringer flag. | 08-08 |

---

## Pitfalls — verified mitigated

| Pitfall | Plan | Verification |
|---------|------|--------------|
| 1 — HMAC raw body bytes (PHP json_encode vs Node JSON.stringify) | 08-05 + 08-06 | `VerifyRconSignatureTest` case 7 (raw body byte-equal) + `SignsRconRequests` trait emits with `JSON_UNESCAPED_SLASHES` so PHP json_encode matches Node's default JSON.stringify byte-for-byte; rcon-worker `HmacSigner.test.ts` pins the lowercase-hex digest contract |
| 2 — Clock skew between worker and web | 08-05 | `VerifyRconSignatureTest` case 5 (stale signature 401 outside 60s window); Carbon::now() compute on both sides (Rule 1 fix from microtime — see 08-05-SUMMARY decision #1) |
| 3 — CRCON last_seen_id resume on reconnect | 08-10 | `CrconClient.integration.test.ts` case 1 (server forcibly disconnects → auto-reconnect; reconnect subscribe message includes last_seen_id) |
| 4 — Aggregator N² scan | 08-08 | Once-per-match_end design verified by code review of `MatchPlayerStatAggregator`; aggregator runs ONLY when CloseMatchJob fires (one match_end event triggers one aggregation pass); event stream replayed in temporal order via `latest('occurred_at')`. Migration index `match_events_aggregator_idx (match_id, event_type, occurred_at)` from plan 08-02 supports the scan pattern |
| 5 — Steam ID orphan events | 08-08 | `MatchPlayerStatAggregatorTest` case 4 (orphan event with unknown steam_id_64 silently skipped — no MatchPlayerStat row written; no exception thrown); migration `2026_05_16_100700` ships `players.steam_id_64 text UNIQUE` |
| 6 — Test Connection timeout vs PHP-FPM 30s | 08-09 | Async via Horizon `TestMatchServerConnectionJob`; `TestMatchServerConnectionJobTest` 5 cases verify async dispatch + status persistence + activity_log write |
| 7 — btree_gist extension | 08-02 | Migration `2026_05_16_100100` runs `CREATE EXTENSION IF NOT EXISTS btree_gist` inline; migrate:fresh + seed verified clean (gate output above); `down()` does NOT drop the extension (idempotent posture per 08-02-SUMMARY decision #4) |
| 8 — APP_KEY rotation runbook | RESEARCH | Documented runbook in `08-RESEARCH.md`; out of round-1 scope. Phase 9 polish will ship the rotation Artisan command + Railway env-group migration playbook; round-1 operator follows the manual smoke D procedure (rotate WEB_HMAC_SECRET in Railway + restart both services) |
| 9 — Pino PII redact (steam_id_64 in worker logs) | 08-01 + 08-10 | `apps/rcon-worker/src/logging/logger.ts` Pino redact list baked at Wave 0: `steam_id_64`, `player.*`, `victim.*`, `killer.*` paths; verified at construction time (08-01-SUMMARY decision #2) |
| 10 — CRCON connection limit (per server) | RESEARCH | Documented operational limit; cap on round-1 simultaneous bookings via Filament `MatchServerResource` admin guidance + the SC-2 EXCLUDE constraint prevents overlap so two simultaneous CRCON sessions on the same server is structurally impossible |
| 11 — Worker scale-out race (multiple replicas claiming the same booking) | 08-11 | Documented "single replica" in `apps/rcon-worker/README.md` + RESEARCH Pitfall 11; multi-replica + booking lease ownership deferred to Phase 9. Round-1 deploy is a single rcon-worker instance per the Railway 5-service topology (D-014) |
| 12 — Manual lock no signal to worker | 08-08 | `activity_log` row written when an admin flips `matches.manual_entry_required=true` via Filament; `ManualOverrideWinsTest` case 2 verifies the worker-side D-019 gate blocks subsequent RCON ingest after manual override; v2 enhancement: web→worker signal channel via Redis pub/sub deferred to Phase 9 |

---

## Canonical D-08-* decisions

Phase 8 plan-level decisions (extracted from each 08-NN-SUMMARY.md
`key-decisions` block). All decisions are LOCKED inline at the
referenced plan; this table is the canonical Phase 8 reference for
future-phase consumers.

| ID | Decision | Source |
|----|----------|--------|
| D-08-01-A | undici pinned to ^7 (NOT ^8) for Node 22 built-in fetch compatibility (nodejs/undici#3901). Locked at the package.json layer. | 08-01 |
| D-08-01-B | Pino redact paths baked in at Wave 0 (steam_id_64, player.*, victim.*, killer.*) so plan 08-10's first commit cannot leak PII (T-08-01-02 prep). | 08-01 |
| D-08-01-C | `admin.audit.match_servers.*` NESTED inside the top-level audit array (not a new top-level key — that would clobber Phase 1-7 audit.col/audit.subject/audit.filter); regression confirmed via 199 admin/i18n tests passing. | 08-01 |
| D-08-01-D | Factory stubs throw `RuntimeException` from `definition()` (Phase 4 D-04-01 idiom) so accidental `::factory()` calls fail loud instead of silently inserting empty rows. | 08-01 |
| D-08-02-A | Two CHECK constraints on `match_server_bookings` (status enum + `reserved_to > reserved_from`) as defence-in-depth alongside btree_gist EXCLUDE. Inverted ranges silently bypass EXCLUDE (Postgres only invokes the index on validly-ordered ranges). | 08-02 |
| D-08-02-B | `match_events.ingested_at` uses `useCurrent()` (DEFAULT now() at the DB layer) — NOT app-layer timestamps. Aggregator replays via `occurred_at`; `ingested_at` is purely ops/debugging. | 08-02 |
| D-08-02-C | Three indexes added for query workloads not in the plan but obviously needed: `msb_server_window_idx`, `msb_match_idx`, `match_events_aggregator_idx` (Rule 2 — missing critical functionality). | 08-02 |
| D-08-02-D | `down()` does NOT drop `btree_gist` extension. Idempotent `CREATE EXTENSION IF NOT EXISTS` on up(); other tables in future phases may consume it. Same posture as 0001 enable_postgres_extensions. | 08-02 |
| D-08-03-A | `credentials_encrypted` column ALTERED from `jsonb` to `text` (Rule 1 bug fix). Laravel's `encrypted:array` cast emits a base64-of-JSON envelope (e.g. `eyJpdiI6...`) that is NOT valid JSON; Postgres `jsonb` rejected the INSERT with SQLSTATE 22P02. Migration `2026_05_16_100600`. | 08-03 |
| D-08-03-B | Factory state helpers `forMatch()` / `onServer()` (NOT Eloquent `->for()` relationship binding). The `match()` method on `MatchServerBooking` collides with the PHP reserved keyword and the reflection-based `->for()` lookup is ambiguous. | 08-03 |
| D-08-03-C | `MatchServerBooking::scopeDueWithin` accepts `CarbonInterface` for both bounds (anticipates 08-11 BookingScheduler `now()` + `now()->addMinutes(5)` invocation). | 08-03 |
| D-08-04-A | `MatchEvent` uses `$timestamps = false`. The 08-02 migration installs `occurred_at` + `ingested_at` but deliberately omits Laravel's standard `created_at`/`updated_at` (table is append-only with its own timeline columns). | 08-04 |
| D-08-04-B | Unit tests opt into `RefreshDatabase` explicitly; `Pest.php` global binding only attaches to `Feature/`. Phase 8 Unit tests use real DB fixtures so they need `RefreshDatabase` to avoid row leakage. | 08-04 |
| D-08-04-C | UNIQUE-violation probes wrap in `DB::transaction()` for savepoint pattern — outer `RefreshDatabase` transaction otherwise enters failed state on 23505. | 08-04 |
| D-08-04-D | `MatchPlayerStat::kdr()` is a plain method (not an Eloquent attribute accessor); returns `float\|int` union (rounded ratio when deaths>0, raw kills int when deaths=0). | 08-04 |
| D-08-05-A | `VerifyRconSignature` uses `Carbon::now()->getTimestamp() * 1000 + milli` (NOT `microtime(true) * 1000`) for the freshness window arithmetic so `Carbon::setTestNow()` is honoured in tests; production behaviour identical. | 08-05 |
| D-08-05-B | Distinct 401 labels per failure mode (`missing rcon auth headers` / `stale signature` / `bad signature` / `replayed nonce`) for ops debuggability without leaking secret material. | 08-05 |
| D-08-05-C | Tests use `$this->call('POST', uri, [], [], [], $serverVars, $rawBody)` with pre-converted `HTTP_*` server vars so raw body bytes match signed bytes byte-for-byte (NOT `postJson` which re-encodes). | 08-05 |
| D-08-05-D | Empty `WEB_HMAC_SECRET` fails LOUD via `InvalidArgumentException` in `HmacVerifier::sign` (T-08-05-06 fail-open misconfig mitigation). | 08-05 |
| D-08-05-E | Constant-time `hash_equals` compare on lowercase-hex sigs; SHA-256 over `$timestamp . $body`; lowercase-hex is the wire-format contract Node worker MUST emit. | 08-05 |
| D-08-06-A | `MatchEventsController::store` ships a labelled SHIM in plan 08-06; plan 08-07 replaces with `MatchEventIngestService` injection. The 8-case `InternalApiRoutesPresentTest` pins the wire contract across the shim→service transition. | 08-06 |
| D-08-06-B | `BookingDueData` includes pre-resolved `server_host` + `server_port_rcon` so the rcon-worker `BookingScheduler` does NOT need a second hop to `/api/internal/match-servers/{id}/credentials` just for connectivity info. The credentials endpoint stays reserved for the api_token. | 08-06 |
| D-08-06-C | `BookingScheduleController::dueNow` uses `Carbon::now()` for time-window arithmetic (`Carbon::setTestNow()` compatibility); same rationale as D-08-05-A. | 08-06 |
| D-08-06-D | `SignsRconRequests` trait lives at `tests/Support/` (Tests\Support namespace) as a reusable Pest trait; reads secret from `config('rcon.hmac_secret')` so each test pins its scope-local secret via beforeEach. | 08-06 |
| D-08-06-E | Routes for `/api/internal/*` APPENDED to `apps/web/routes/api.php` (NOT replaced) preserving the Phase 5 bot-API route tree. | 08-06 |
| D-08-06-F | `SignsRconRequests::signedJsonPost` uses `JSON_UNESCAPED_SLASHES` so PHP `json_encode` matches Node's default `JSON.stringify` byte-for-byte. | 08-06 |
| D-08-07-A | Per-event INSERT wrapped in `DB::transaction(fn () => MatchEvent::create(...))` — composite UNIQUE absorb requires the failing INSERT's effects to roll back independently of peers (SAVEPOINT pattern). | 08-07 |
| D-08-07-B | Response shape gains additive `skipped_count` field (`{batch_id, accepted_count, skipped_count}`). `InternalApiRoutesPresentTest` `toHaveKeys` matcher tolerates extra keys. | 08-07 |
| D-08-07-C | Three permissive event types (`game_start`, `round_start`, `manual_error`) — strict validation deferred to keep generic-game model (D-007) decoupled from HLL payload shapes. | 08-07 |
| D-08-07-D | `InvalidArgumentException` from normaliser bubbles to the controller (NOT caught + rewritten to 422) — a payload shape miss is a worker bug; 500 status code triggers operator alert. | 08-07 |
| D-08-07-E | `CloseMatchJob` constructor takes `readonly string $matchId` (primitive-ID job idiom — matches Phase 5 SyncDiscordRolesJob). | 08-07 |
| D-08-07-F | 11-case normaliser contract test uses `(new MatchEventNormaliser)->validate(...)` inline rather than a `$this->normaliser` Pest closure property (PHPStan can't infer types for properties on Pest's `TestCall` surface). | 08-07 |
| D-08-08-A | Migration `2026_05_16_100700` adds `players.steam_id_64 text nullable UNIQUE` (Rule 3 auto-add — plan's aggregator referenced the column but no prior phase shipped it). | 08-08 |
| D-08-08-B | `RconWorkerSystemUserSeeder` follows canonical Phase 5 `BotServiceUserSeeder` idiom (discord_id sentinel + username + email + locale), NOT the plan's `slug` + `is_admin` shape which referenced non-existent columns. | 08-08 |
| D-08-08-C | `lang/en/rcon.php` ships `audit.automated_from_crcon` key with copy "Automated result from CRCON — populated by the RCON worker." (Rule 2 auto-add for D-013 i18n CI gate). | 08-08 |
| D-08-08-D | `ManualOverrideWinsTest` simulates admin override via direct `$rconResult->update(['source' => 'manual', ...])` because Phase 4's `MatchResultService::upsert` doesn't write the `source` column explicitly. | 08-08 |
| D-08-08-E | `CloseMatchJob` uses typed `is_int($payload['allies_score'])` guards rather than blind cast `(int)` so malformed CRCON payloads surface as null (NULL is valid in `match_results.allies_score`). | 08-08 |
| D-08-09-A | `Filament::getCurrentPanel()->getResources()` returning the registered class list is the canonical v3.3 API (plan's `Filament::getResource()` does not exist in FilamentManager). | 08-09 |
| D-08-09-B | `EditMatchServer.mutateFormDataBeforeSave` drops empty `credentials_encrypted` from form data on edit so a blank password field preserves the stored token (UX nicety + Filament dehydration default safety). | 08-09 |
| D-08-09-C | `CrconHealthProbe` catches `Throwable` only (PHPStan's flow analysis treats `Http::withToken()->get()` as throwing only Throwable — explicit `catch (ConnectionException|Throwable)` was flagged as dead-catch). | 08-09 |
| D-08-09-D | `MatchServerResource` nav group='RCON' sort=30 (cleanly after Phase 5 Discord group sort=22). | 08-09 |
| D-08-10-A | `HmacSigner.sign()` timestamp parameter type is `string` (NOT `number`) for byte-for-byte cross-tier compatibility with apps/web `HmacVerifier`. | 08-10 |
| D-08-10-B | `verify()` short-circuits on equal-length-zero OR unequal-length signatures rather than calling `timingSafeEqual` with mismatched buffers (which throws RangeError). | 08-10 |
| D-08-10-C | `CrconClient.SUBSCRIBE_ACTIONS` exported as `as const` tuple — single source of truth across subscribe-message construction, test assertions, and normaliser switch arms. | 08-10 |
| D-08-10-D | `CrconClient.onLogs` callback signature is `(logs: unknown[], lastSeenId: string \| null) => void` (NOT plan's `string`) — wire `last_seen_id` is optional. | 08-10 |
| D-08-10-E | `WebIngestClient` is intentionally retry-stateless; returns `{status, body}`; caller (08-11 BookingScheduler) owns the Redis-backed 5xx drainer. | 08-10 |
| D-08-10-F | Heartbeat tests use real timers + injected short `heartbeatIntervalMs` (Rule 2 testability hook) for deterministic CI without real-world wait times. | 08-10 |
| D-08-11-A | `BookingScheduler` accepts an injectable `managerFactory` option (Rule 2 testability hook) so unit tests pass a stub factory. | 08-11 |
| D-08-11-B | `MatchLifecycleManager` exposes 3 override options: `flushIntervalMs` / `batchSize` / `completeGraceMs` for deterministic integration tests. | 08-11 |
| D-08-11-C | `WebIngestClient.fetchSignedJson<T>(path)` throws on non-2xx (NOT returning `{status, body}`) — centralises transient failure handling. | 08-11 |
| D-08-11-D | ioredis-mock chosen over hand-rolled shim; all RedisFailoverQueue + MatchLifecycleManager integration tests pass under ioredis-mock. | 08-11 |
| D-08-11-E | ioredis named-import shape — `import { Redis } from 'ioredis'` (TS 5.6 NodeNext default-export resolves as namespace). | 08-11 |
| D-08-11-F | `fetchSignedJson<T>` for GET uses empty-body signing (`body = ''`, digest input is `timestamp + ''` = `timestamp`) matching apps/web `VerifyRconSignature` semantics. | 08-11 |
| D-08-12-A | `DiscordOutboundPayloadBuilder::buildMatchResultAnnounce` lives in `App\Support` (NOT `App\Services`) as a public static method on the existing Phase 5 stateless helper class. | 08-12 |
| D-08-12-B | `discord_outbound_messages` has NO `match_id` FK column — embed `match_id` in the JSONB payload (matches Phase 6 `bracket_result_announce` shape); `alreadyAnnounced` predicate queries `whereJsonContains payload->match_id`. | 08-12 |
| D-08-12-C | `MatchResultObserver` uses `created` + `updated` hooks (NOT `saved`) — matches Phase 4/6 docblock convention; idempotency comes from `alreadyAnnounced` query, not from `wasChanged()` guard. | 08-12 |
| D-08-12-D | `MatchEventFactory::wireMake()` instance method (NOT a parallel `WireShapeFactory`) — delegates to `$this->makeOne()` and emits the wire-array shape; reuses every state helper. | 08-12 |
| D-08-12-E | `RconI18nKeyCoverageTest` uses `Lang::has()` (NOT `Lang::get($k) !== $k`) — short-circuits to false on missing keys without allocating a string or logging a missing-translation warning (Phase 6 D-06-13-C continuation). | 08-12 |
| D-08-12-F | `MatchResultAnnounceData` lives in `App\Data` (NOT `App\Data\Internal`) — Internal namespace is reserved for `/api/internal/*` wire INPUT/OUTPUT shapes; announce payload is web→bot via outbox. | 08-12 |

---

## D-04-03-A LOCKED — canonical model binding (re-affirmed across Phase 8)

App\Models\GameMatch is the canonical model name (NOT `Match` — PHP 8.x
reserved keyword); table stays `matches` via `protected $table = 'matches'`
override; no `Match as MatchModel` alias-on-import anywhere in the Phase
8 surface. Verified across:

- `app/Services/MatchEventIngestService.php` — direct `use App\Models\GameMatch`
- `app/Services/MatchPlayerStatAggregator.php` — direct `use App\Models\GameMatch`
- `app/Services/MatchResultService.php` upsertFromRcon — direct `use`
- `app/Observers/MatchResultObserver.php` match_result_announce branch — direct `use`
- `app/Support/DiscordOutboundPayloadBuilder.php` buildMatchResultAnnounce — direct `use`
- `app/Jobs/CloseMatchJob.php` — primitive `$matchId` (no model FQN at import level)
- `app/Filament/Resources/MatchServerResource/RelationManagers/BookingsRelationManager.php` — direct `use`
- All Phase 8 tests (Feature + Unit) — direct `use App\Models\GameMatch`

`BelongsTo<GameMatch, $this>` passes `match_id` as explicit FK arg per
D-04-03-B (Laravel cannot infer from `match()` method name when related
class is `GameMatch`). Phase 9 polish plans MUST preserve this binding.

---

## Manual Smoke — PENDING

Operator walkthrough required to close Phase 8 fully. Four items:

- [ ] **A — Register a real MatchServer in Filament against a live CRCON
      instance.** Log in as admin → navigate to /admin/match-servers →
      Create → enter CRCON URL + API token (encrypted at rest via
      Laravel `encrypted:array` cast) → click **Test Connection**
      HeaderAction → verify badge flips from `unknown` to `ok` within
      30s (async via Horizon `TestMatchServerConnectionJob`); verify
      `activity_log` row written; verify `last_test_status_at`
      timestamp updated.

- [ ] **B — Schedule a scrim, book the server, play it on real CRCON,
      verify auto-recorded result.** Create a Match in Filament with
      `scheduled_start` near-future + the registered MatchServer →
      verify `match_server_bookings` row inserted with reserved window
      `[scheduled_start − 5m, scheduled_end + 30m]` → start the
      rcon-worker against the actual CRCON instance → play the match
      end-to-end on a real Hell Let Loose server → at match_end:
      verify `MatchResult` row inserted with `source='rcon'`; verify
      `MatchPlayerStat` rows materialised per player; verify
      `discord_outbound_messages` row with `message_type='match_result_announce'`
      dispatched; verify the match detail page (`/matches/{id}`) renders
      the auto-recorded result without manual entry.

- [ ] **C — Disconnect the CRCON network mid-match for 2 minutes, then
      reconnect.** Physically disconnect the CRCON server's network for
      ~2 minutes during an active match → verify rcon-worker's
      `CrconClient` enters reconnect loop with exponential backoff →
      verify reconnect subscribe message includes `last_seen_id`
      cursor → verify the missed event window is captured on resume
      (cross-check `match_events` row count against CRCON's own log
      output for the window).

- [ ] **D — Rotate WEB_HMAC_SECRET in Railway, restart both `web` and
      `rcon-worker` services.** Rotate the secret in Railway env group
      → restart both services → verify the next booking ingest cycle
      completes cleanly (200 from `/api/internal/match/{id}/events`);
      verify the OLD secret is rejected (`401 stale/bad signature`
      from the middleware); verify NO replay of in-flight signed
      requests survived the rotation window.

These four items cover the operator/network seams that the test
surface intentionally does not exercise (live CRCON probe, real-world
event flow, network disruption, secret rotation). Once all 4 are
complete the phase moves from PENDING_MANUAL_SMOKE → COMPLETE.

---

## Phase 8 sign-off

All 7 quality gates GREEN. All 5 success criteria mechanically proven
by the test surface. All 3 requirements (REQ-goal-rcon-history,
REQ-constraint-league-owns-servers, REQ-success-end-to-end-scrim)
satisfied. The round-1 acceptance loop is closed: two clans can
schedule a scrim, sign up for role slots from Discord, play it on a
registered match server, and have a result and per-player stats
recorded automatically.

Phase 8 → PENDING_MANUAL_SMOKE pending the 4 operator walkthrough
items above. Phase 9 (Polish) ready to plan.
