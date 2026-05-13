---
phase: 06-tournaments-brackets
plan: 13
subsystem: i18n+audit+bot
tags: [i18n, audit, spatie-activitylog, discord, embeds, vitest, pest, sc3, sc5, pitfall-10]

requires:
  - phase: 06-tournaments-brackets
    provides: TournamentObserver + DiscordOutboundPayloadBuilder::buildTournamentAnnounce + buildBracketResult; TournamentStatusService + TournamentSeedingService; ParticipantsRelationManager forfeit/withdraw audit shape; StandingsCalculatorService dependent-row idiom
  - phase: 05-discord-bot-v1
    provides: apps/bot/src/lib/embeds.ts canonical EmbedBuilder factories; apps/bot/src/services/render.ts dispatcher; OutboundRow.message_type discriminated union
  - phase: 04-matches-manual
    provides: MatchAuditLogTest D-04-12-A canonical Activity::query+whereJsonContains idiom
  - phase: 01-foundations
    provides: lang/en/{tournaments,admin,common}.php skeletons; NoHardcodedStringsTest scaffold

provides:
  - i18n key coverage gate — Pitfall 10 mitigation (TournamentI18nKeyCoverageTest)
  - audit-log integration test for all 5 Phase 6 LogsActivity models + 5 service-level activity() rows
  - apps/bot/src/lib/embeds.ts:buildTournamentAnnounceEmbed (locale-aware, defensive)
  - apps/bot/src/lib/embeds.ts:buildBracketResultEmbed (winner-resolution + URL)
  - apps/bot/src/services/render.ts dispatch branches for tournament_announce, tournament_announce_update, bracket_result_announce
  - apps/bot/src/types/apiContracts.ts:OutboundRow.message_type union extended by 3 kinds
  - 22 new Vitest specs in apps/bot/tests/lib/tournamentEmbeds.test.ts
affects: [06-14]

tech-stack:
  added: []
  patterns:
    - "Pattern 1: Source-grep i18n coverage with leaf-segment regex (rejects string-concat dynamic keys like 'tournaments.formats.' . $var) — concrete leaves are tested separately via expected-key resolution against a hardcoded 100+ key list."
    - "Pattern 2: Service-level activity()->withProperties() emits 'Tournament status: from -> to' rows where the JSON properties carry the canonical state-transition payload (D-04-04-A pattern reused for Tournament lifecycle)."
    - "Pattern 3: Reseed audit asymmetry — TournamentSeedingService::reseed emits BOTH (a) 2 status transition rows (seeded→registering→seeded) AND (b) a 'Tournament reseeded' row carrying previous_seeds + new_seeds maps for delta reconstruction."
    - "Pattern 4: Wipe-and-recompute standings audit trail — StandingsCalculatorService does NOT emit a dedicated activity row; instead each new TournamentStanding row trips its own LogsActivity 'created' event. The set of standing rows IS the audit trail."
    - "Pattern 5: Bot embed locale fallback chain — title[locale] ?? title.en ?? 'Tournament' covers Spatie translatable JSONB title maps with missing locales gracefully."
    - "Pattern 6: 3 distinct outbound kinds (tournament_announce, tournament_announce_update, bracket_result_announce) — per-kind dispatch in render.ts; admin filterable via Filament DiscordOutboundMessageResource."

key-files:
  created:
    - apps/bot/tests/lib/tournamentEmbeds.test.ts
  modified:
    - apps/web/tests/Feature/I18n/TournamentI18nKeyCoverageTest.php
    - apps/web/tests/Feature/Admin/TournamentAuditLogTest.php
    - apps/bot/src/lib/embeds.ts
    - apps/bot/src/services/render.ts
    - apps/bot/src/types/apiContracts.ts

key-decisions:
  - "D-06-13-A: TournamentI18nKeyCoverageTest uses a leaf-anchored regex `[a-z0-9_.]*[a-z0-9_]` (terminating in a non-dot character class) so string-concat dynamic key patterns `__('tournaments.formats.' . $var . '.label')` are deliberately NOT matched by the source-grep test — those are covered by the expected-key resolution test (#1) against a hardcoded 100+ leaf-key list. This split avoids false positives without sacrificing coverage."
  - "D-06-13-B: Bot embed builders ship in `apps/bot/src/lib/embeds.ts` (extending the existing Phase 5 module) NOT in `apps/bot/src/services/embeds/tournament.ts` (plan <files> aspirational path). The Phase 5 canonical layout uses `lib/` for stateless presentation helpers and `services/` for stateful pipeline components — embed builders are stateless, hence `lib/`. The execution_rules prompt locked this choice explicitly: 'tournament_announce + bracket_result_announce embeds in apps/bot/src/lib/embeds.ts'."
  - "D-06-13-C: Open Question 5 LOCKED inline — 3 distinct OutboundRow message_type values (tournament_announce, tournament_announce_update, bracket_result_announce) over a single unified 'tournament_event' kind with payload.kind discriminator. Distinct kinds yield (a) per-kind dispatch logic in render.ts, (b) easy admin debugging via Filament DiscordOutboundMessageResource filter, (c) parallels the Phase 5 match_announce_new vs match_announce_update pairing already in place."
  - "D-06-13-D: tournament_announce_update is rendered as a fresh post (no edit-prior-message semantics) on the bot side, in contrast to match_announce_update which DOES edit. Rationale: tournament lifecycle is verbose (5 status transitions on the happy path) and individual status flips warrant their own announcement messages. Match status updates are higher-frequency and a single message thread reads better."
  - "D-06-13-E: WEB_URL env var falls back to 'http://localhost' when unset. Tests assert URL CONTAINS the slug path (e.g. '/tournaments/open-2026') but do NOT assert the host portion — this honours T-06-13-05 (env var may not be set in CI) without weakening the contract."
  - "D-06-13-F: Standings recalculation audit is captured via the dependent TournamentStanding 'created' rows (wipe-and-recompute idiom from plan 06-09) rather than a dedicated 'Standings recalculated' activity row. Rationale: the calculator already emits N rows (one per active participant), each tripping LogsActivity individually. A dedicated wrapper row would duplicate signal without adding info."

requirements-completed: [REQ-success-tournament-end-to-end]

duration: ~7min
completed: 2026-05-14
---

# Phase 6 Plan 13: i18n + Audit Log + Bot Embed Extensions Summary

**2 GREEN Pest test files (replacing Wave 0 RED stubs for Pitfall 10 + D-012 enforcement) + 1 bot embed module extension + 1 render.ts dispatcher amendment + 22 Vitest specs.**

## Performance

- **Duration:** ~7min
- **Started:** 2026-05-14
- **Completed:** 2026-05-14
- **Tasks:** 2
- **Files created:** 1
- **Files modified:** 5
- **Commits:** 2 (per task) + this metadata commit

## Accomplishments

- **TournamentI18nKeyCoverageTest** — Pitfall 10 mitigation gate is GREEN. Two specs:
  1. **Expected-key resolution** — 100+ hardcoded leaf keys across `tournaments.*` (16 format + 12 status + 4 participant_status + 10 errors + 36 actions + 5 tabs + 3 empty + 6 stage_types + 1 nav + 8 directory + 12 show + 9 standings + 2 participants) AND `admin.tournament*.*` (3 resource + 2 section + 14 fields + 10 action labels + 3 materialise_next_round leaves + 2 reseed leaves + 8 child resource × 2 + 22 child fields). Every key must resolve to a non-empty string.
  2. **Source-grep round-trip** — every concrete `t()` / `__()` call in Phase 6 Vue (`pages/Tournaments/*.vue`, `components/tournaments/*.vue`) + Filament source (`TournamentResource.php`, `Pages/*.php`, `RelationManagers/*.php`) is regex-extracted (leaf-anchored, no trailing dot) and resolution-checked.
- **TournamentAuditLogTest** — D-012 audit trail integration test is GREEN. 13 specs covering all 7 Phase 6 mutating surfaces:
  - Tournament `create` + `updated` events + `logOnlyDirty` fidelity
  - `TournamentStatusService::transition` writes 'Tournament status: from -> to' rows with `properties[from, to]`
  - Status chain `draft → registering → seeded → running` audited as 3 distinct rows
  - Cancel terminal transition (`registering → cancelled`) audited
  - `TournamentSeedingService::seed` writes 'Tournament seeded' with `properties[strategy, participant_count]`
  - `TournamentSeedingService::reseed` writes 'Tournament reseeded' with `properties[strategy, previous_seeds, new_seeds]` maps
  - Participant `forfeit` writes 'Participant forfeited' (subject=TournamentParticipant) with `properties[reason='forfeit', previous_status]`
  - Participant `withdraw` writes 'Participant withdrew' with `properties[reason='withdraw', previous_status]`
  - Standings recalculation audit via dependent `TournamentStanding` 'created' rows (one per active participant)
  - `TournamentStage` + `TournamentBracket` + `TournamentParticipant` LogsActivity creates
- **apps/bot/src/lib/embeds.ts** — extended with 2 new exports + 2 payload type contracts:
  - `buildTournamentAnnounceEmbed(payload, locale='en')` — 3 inline base fields (Format/Status/Max participants) + optional `Starts at` rendered as `<t:UNIX_TS:F>` Discord timestamp tag; locale-aware title fallback chain `title[locale] → title.en → 'Tournament'`; URL composes from `WEB_URL` env (fallback `'http://localhost'`) + slug.
  - `buildBracketResultEmbed(payload)` — title `Round N — Match P`; description `**<winner>** defeated <loser>` with correct loser-side resolution; defensive `'Result pending'` when winner is null.
- **apps/bot/src/services/render.ts** — dispatcher extended with 2 new branches:
  - `tournament_announce` + `tournament_announce_update` → `renderTournamentAnnounce` (fresh post; no edit-prior)
  - `bracket_result_announce` → `renderBracketResultAnnounce`
  - Both honour `allowed_mentions:{parse:[]}` (T-05-10-07 + T-05-11-05 mention abuse mitigation).
- **apps/bot/tests/lib/tournamentEmbeds.test.ts** — 22 Vitest specs in 2 describe blocks covering happy path, locale fallback, null-defensive defaults, URL composition with empty slug, footer presence/absence, timestamp tag rendering, malformed date string guard, and winner/loser direction inversion.

## Task Commits

1. **Task 1: TournamentI18nKeyCoverageTest + TournamentAuditLogTest RED→GREEN** — `86e02a8` (test)
2. **Task 2: Bot tournament + bracket-result embeds + render dispatch + Vitest** — `71a6d78` (feat)

## Files Created/Modified

### Tests (Pest — 2 modified; Vitest — 1 created)
- `apps/web/tests/Feature/I18n/TournamentI18nKeyCoverageTest.php` — 12-line Wave 0 RED stub → 357-line GREEN coverage gate (Pitfall 10).
- `apps/web/tests/Feature/Admin/TournamentAuditLogTest.php` — 13-line Wave 0 RED stub → 313-line GREEN audit coverage (D-012).
- `apps/bot/tests/lib/tournamentEmbeds.test.ts` — NEW; 22 specs.

### Bot source (3 modified)
- `apps/bot/src/lib/embeds.ts` — +185 lines; 2 new exports + 2 payload type contracts + Pitfall 10 narrative.
- `apps/bot/src/services/render.ts` — +95 lines; 2 new dispatch branches + 2 render functions; import-list extended.
- `apps/bot/src/types/apiContracts.ts` — `OutboundRow.message_type` union extended by 3 kinds.

## Decisions Made

See frontmatter `key-decisions` D-06-13-A through D-06-13-F. Six inline decisions, all consistent with phase-level decisions and prior plans:
- D-06-13-A: leaf-anchored regex (dynamic keys handled by expected list, not source grep).
- D-06-13-B: embed builders live in `lib/`, not `services/embeds/` (Phase 5 convention; execution_rules override).
- D-06-13-C: 3 distinct outbound kinds (Open Question 5 LOCKED inline — consistent with plans 06-08 + 06-10).
- D-06-13-D: `tournament_announce_update` is a fresh post (no edit-prior semantics; differs from match_announce_update by design).
- D-06-13-E: WEB_URL fallback to localhost; tests assert path containment not host.
- D-06-13-F: Standings recalc audit via dependent TournamentStanding rows (no dedicated wrapper row).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] Plan `<files>` referenced `apps/bot/src/services/embeds/tournament.ts` but Phase 5 convention places embed builders in `apps/bot/src/lib/embeds.ts`.**
- **Found during:** Task 2 (reading plan + Phase 5 codebase).
- **Issue:** The plan's `<files>` block named `apps/bot/src/services/embeds/tournament.ts` — a directory that does not exist. Phase 5 plan 05-10 (canonical embed-builder pattern) ships `apps/bot/src/lib/embeds.ts` (single file, all exports). The execution_rules section in the orchestrator prompt confirmed: `"tournament_announce + bracket_result_announce embeds in apps/bot/src/lib/embeds.ts"`.
- **Fix:** Extended the existing `apps/bot/src/lib/embeds.ts` with 2 new exports + 2 payload type contracts. Test file landed at `apps/bot/tests/lib/tournamentEmbeds.test.ts` to mirror the source layout (Phase 5 `tests/lib/embeds.test.ts` analog).
- **Files affected:** `apps/bot/src/lib/embeds.ts` (extended), `apps/bot/tests/lib/tournamentEmbeds.test.ts` (created in `tests/lib/`).
- **Committed in:** `71a6d78` (Task 2).

**2. [Rule 2 — Missing Critical] Plan `<interfaces>` regex matched dynamic-key string-concat patterns as if they were complete keys.**
- **Found during:** Task 1 (first run of TournamentI18nKeyCoverageTest source-grep spec).
- **Issue:** First-draft regex `/(?:\bt|__)\(\s*(["\'])((?:prefix)[a-z0-9_.]+)\1/i` captured strings like `'tournaments.formats.'` (with trailing dot) from `__('tournaments.formats.' . $record->format . '.label')` — i.e., the LITERAL portion of a string-concat expression. The capture had a trailing dot, was clearly NOT a real key, and crashed the resolution check with 4 false positives.
- **Fix:** Anchored the captured key body's last character class to `[a-z0-9_]` (not `[a-z0-9_.]`), so trailing dots are excluded. Dynamic-key callers are NOT scanned by the source-grep — those are covered by the expected-key resolution test (test #1) against a hardcoded list of 100+ concrete leaves.
- **Files affected:** `apps/web/tests/Feature/I18n/TournamentI18nKeyCoverageTest.php`.
- **Verification:** Both specs in `TournamentI18nKeyCoverageTest` GREEN; 6 assertions across the 2 specs.
- **Committed in:** `86e02a8` (Task 1).

**3. [Rule 2 — Missing Critical] OutboundRow.message_type union did not enumerate the 3 new Phase 6 kinds.**
- **Found during:** Task 2 (extending render.ts switch).
- **Issue:** Phase 5 ships `OutboundRow.message_type: 'match_announce' | 'role_sync' | 'generic'`. The render.ts switch needed to discriminate on `tournament_announce`, `tournament_announce_update`, `bracket_result_announce` — but those values would have been narrowed to `never` by TypeScript without an explicit union extension.
- **Fix:** Extended `OutboundRow.message_type` to a 6-kind union (`match_announce | role_sync | tournament_announce | tournament_announce_update | bracket_result_announce | generic`). No tests broke (the web side's `message_type` column is `text NOT NULL`, no runtime enum, no DB CHECK on the value set yet — that lands in plan 06-14 verification).
- **Files affected:** `apps/bot/src/types/apiContracts.ts`.
- **Committed in:** `71a6d78` (Task 2).

---

**Total deviations:** 3 auto-fixed (Rule 2/3 — all on the critical path).
**Impact on plan:** All three fixes preserved the plan's stated outputs; no scope creep.

## Issues Encountered

None blocking. The full Pest suite (866 tests, 2719 assertions) GREEN; full bot Vitest suite (139 tests across 11 test files) GREEN; PHPStan level 8 clean; Pint clean across all 435 files.

## Open Question Resolution

**Open Question 5 — Bot integration kind enums — LOCKED inline (D-06-13-C):**

3 distinct outbound `message_type` values:
- `tournament_announce` — fired by `TournamentObserver::created` on public Tournament create (plan 06-10).
- `tournament_announce_update` — fired by `TournamentObserver::updated` ONLY when `wasChanged('status')` AND `is_public` (plan 06-10 + Pitfall 7 mitigation).
- `bracket_result_announce` — fired by `BracketAdvancementService::advance` once per resolved bracket (plan 06-08).

Rationale: per-kind dispatch in `render.ts`; admin debug filter on `DiscordOutboundMessageResource.message_type`; parallels Phase 5 `match_announce_new` vs `match_announce_update`.

## User Setup Required

None — no external service configuration required. `WEB_URL` is optional (falls back to `'http://localhost'`).

## Next Phase Readiness

- **Plan 06-14 (phase verification):** Ready. SC-5 audit trail coverage end-to-end GREEN. SC-3 bot rendering complete. Pitfall 10 mitigation gated by CI. The verifier will run the SC-1..SC-5 traceability matrix against this plan's outputs.
- All RED Wave 0 stubs flipped to GREEN; nothing remains for the phase except the verifier write-up (06-14).

## Known Stubs

None. Every new symbol resolves real implementations:
- `buildTournamentAnnounceEmbed` + `buildBracketResultEmbed` are pure, deterministic builders consumed by `renderTournamentAnnounce` + `renderBracketResultAnnounce`.
- All 100+ expected i18n keys resolve to non-empty strings (any future gap will fail TournamentI18nKeyCoverageTest in CI).

## Verification Results

| Gate | Command | Result |
|---|---|---|
| Pest (this plan's 2 files) | `docker compose exec web ./vendor/bin/pest tests/Feature/I18n/TournamentI18nKeyCoverageTest.php tests/Feature/Admin/TournamentAuditLogTest.php` | **15 passed** (2+13), 55 assertions |
| Pest (full suite) | `docker compose exec web ./vendor/bin/pest` | **866 passed**, 2719 assertions, 48.82s |
| Pint --test (full) | `docker compose exec web ./vendor/bin/pint --test` | **PASS 435 files** |
| PHPStan level 8 | `docker compose exec web ./vendor/bin/phpstan analyse` | **No errors** (287 files analysed) |
| Bot Vitest | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm test'` | **139 passed** (11 test files, 22 new + 117 Phase 5 baseline) |
| Bot tsc | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm typecheck'` | clean |
| Bot ESLint | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm lint'` | clean |

## Self-Check: PASSED

- [x] `apps/web/tests/Feature/I18n/TournamentI18nKeyCoverageTest.php` exists (replaces Wave 0 stub)
- [x] `apps/web/tests/Feature/Admin/TournamentAuditLogTest.php` exists (replaces Wave 0 stub)
- [x] `apps/bot/src/lib/embeds.ts` exports `buildTournamentAnnounceEmbed` + `buildBracketResultEmbed` + payload types
- [x] `apps/bot/src/services/render.ts` dispatch branches for 3 new kinds present
- [x] `apps/bot/src/types/apiContracts.ts` OutboundRow.message_type union includes the 3 new kinds
- [x] `apps/bot/tests/lib/tournamentEmbeds.test.ts` exists with 22 specs
- [x] Commit `86e02a8` (Task 1) exists in `git log --all`
- [x] Commit `71a6d78` (Task 2) exists in `git log --all`
- [x] All 15 new Pest specs (2 i18n + 13 audit) GREEN
- [x] All 22 new Vitest specs GREEN
- [x] Phase 5 baseline 117 bot tests still GREEN (no regressions)
- [x] Full Pest suite 866 GREEN (no regressions)
- [x] Pint clean
- [x] PHPStan level 8 clean
- [x] Bot tsc + eslint clean

---
*Phase: 06-tournaments-brackets*
*Completed: 2026-05-14*
