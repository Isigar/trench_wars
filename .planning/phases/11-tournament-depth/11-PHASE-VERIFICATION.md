---
phase: 11-tournament-depth
slug: tournament-depth
status: COMPLETE
completed: 2026-06-04
plans_complete: 5
plans_total: 5
test_count_web: 1365
test_assertions_web: 4802
test_passing_web: 1365
test_failing_web: 0
bot_test_count: 190
bot_test_files: 15
quality_gates:
  migrate_fresh_seed: GREEN
  pest: GREEN
  pint: GREEN
  phpstan_l8: GREEN
  vue_tsc: GREEN
  bot_vitest: GREEN
  bot_tsc: GREEN
  bot_eslint: GREEN
requirements: [TOUR-01, TOUR-02, TOUR-03, TOUR-04]
---

# Phase 11 — Tournament Depth — Verification Report

**Date:** 2026-06-04
**Phase status:** COMPLETE (all automated gates PASS; two items are PENDING_MANUAL_SMOKE for pixel verification)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 11 |
| Name | Tournament depth |
| Slug | tournament-depth |
| Plans | 5 plans (11-01 through 11-05) |
| Completed date | 2026-06-04 |
| Phase 10 foundation | Phase 10 COMPLETE 2026-06-04 (1335 tests) |
| Requirements satisfied | TOUR-01, TOUR-02, TOUR-03, TOUR-04 |

---

## Status

COMPLETE — all 8 quality gates GREEN on a fresh schema (migrate:fresh --seed).
All four ROADMAP success criteria are mechanically proven by named, runnable tests.
Two items are PENDING_MANUAL_SMOKE: the Filament stage override Select UI (TOUR-04)
and the public median Buchholz column render (TOUR-03) — automated coverage proves
logic and type-safety but not operator pixel walkthrough.

---

## Overview

Phase 11 delivered four tournament-depth features:

- **Schema markers** (11-01): `clans.elo_rating` (default 1500) + `elo_matches_count`,
  `tournament_brackets.rated_at`, `tournament_standings.median_buchholz`,
  `tournament_stages.game_match_type_id` FK + `TournamentStage::gameMatchType BelongsTo` +
  `TournamentStandingData.median_buchholz` DTO field + 4 RED test scaffolds (Plan 11-01).

- **EloRatingService + Median Buchholz** (11-02): `EloRatingService` (K=32, DB::transaction
  lockForUpdate, elo_matches_count increment, activity log); `SwissStandingsCalculator`
  extended with Buchholz Cut-1 third tiebreaker (drop highest + lowest opponent score
  when >=3 opponents) — 4 EloRatingServiceTest cases + 2 SwissMedianBuchholzTest cases GREEN.

- **Advancement hooks + by_rank seeding** (11-03): Elo hook (rated_at-guarded, once per
  bracket) and Swiss auto-advance (idempotent, exhaustion-guarded, premature-completion
  guard in `allBracketsComplete()`) wired into `BracketAdvancementService::advance()`;
  `TournamentSeedingService::orderByRank()` rewritten to sort by `elo_rating DESC, created_at DESC`.
  EloAdvancementHookTest (3 cases) + 3 new SwissAutoAdvanceTest cases + 2 new
  TournamentSeedingServiceTest cases all GREEN. Premature-completion guard (BLOCKER-class
  finding) proven by dedicated test.

- **Stage-level match type override** (11-04): `BracketMatchMaterialiserService` resolves
  effective match type as `stage.game_match_type_id ?? tournament.default_game_match_type_id`;
  `StagesRelationManager` gains cross-game-scoped `game_match_type_id` Select + EditAction +
  TextColumn (Pattern 3). StageMatchTypeOverrideTest (3 cases) + StagesRelationManagerOverrideTest
  (3 cases) GREEN.

- **Public standings + shared-types regen** (11-05): `StandingsTable.vue` adds
  `showMedianBuchholz` computed (`format === 'swiss'`) gating a Median Buchholz column;
  `tournaments.standings.tiebreak_median_buchholz` i18n key added; `typescript:transform`
  regenerated → `TournamentStandingData.median_buchholz: number` lands in `api.d.ts`;
  `packages/shared-types/src/api.d.ts` synced; `vue-tsc --noEmit` passes.

Web suite grew from 1335 (Phase 10 close) to 1365 (+30 tests, +78 assertions).

---

## [BLOCKING] Quality Gates — RESULT: PASS

All gates run on a fresh schema (`migrate:fresh --seed`) on 2026-06-04.

| Gate | Command | Result |
|------|---------|--------|
| Schema durability | `make artisan ARGS="migrate:fresh --seed"` | **PASS** — all migrations + all seeders |
| Pest (web full suite) | `make pest` | **1365 passed** (4802 assertions), 0 failed, 87.39s |
| Pint | `make pint ARGS="--test"` | **PASS** — 674 files clean |
| PHPStan L8 | `make phpstan` | **[OK] No errors** |
| vue-tsc | `docker compose exec web node_modules/.bin/vue-tsc --noEmit` | **PASS** (no output) |
| Bot Vitest | `cd apps/bot && node_modules/.bin/vitest run` | **190 passed** (15 files), 0 failed, 961ms |
| Bot tsc | `cd apps/bot && node_modules/.bin/tsc --noEmit` | **PASS** (no output) |
| Bot ESLint | `cd apps/bot && node_modules/.bin/eslint .` | **PASS** (no output) |

**Test growth across phases:**

| Phase | Total Pest after phase | Phase contribution |
|-------|------------------------|--------------------|
| Phase 10 close (10-07) | 1335 tests (4724 assertions) | +32 web |
| **Phase 11 close (11-05)** | **1365 tests (4802 assertions)** | **+30 web (+78 assertions)** |

Bot Vitest surface: 190 tests / 15 files (unchanged — no bot changes in Phase 11).

---

## ROADMAP Success Criteria Mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 / TOUR-01 | When the final match of a Swiss round has its result recorded, the next round is generated automatically and the tournament page reflects the new round without any admin action | `apps/web/tests/Feature/Services/SwissAutoAdvanceTest.php` — 4 cases: (1) "recording final round-1 result auto-generates round 2" (original + new round stage count 1→2); (2) "auto-advance does not generate a round past the final swiss round" (exhaustion guard); (3) "tournament does NOT auto-complete after a non-final swiss round resolves" (premature-completion guard — BLOCKER finding in plan 11-03); (4) "re-firing advance() on already-advanced bracket creates no duplicate stage" (idempotency). Hooks wired in plan 11-03 (`BracketAdvancementService::advance()` step 5b). | **PASS** |
| SC-2 / TOUR-02 | When seeding a tournament bracket, an admin can choose "by rank" and the bracket is seeded by ELO-derived player rating rather than signup order | `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php` — 2 new cases: "by_rank orders participants by clan elo_rating desc (high elo = seed 1)" (1800 vs 1200 → 1800 gets seed 1 regardless of creation order); "by_rank with all clans at 1500 matches created_at desc order (D-11-03-A no-regression)" (4 distinct created_at, all elo=1500 → newest = seed 1); all 16 existing cases still GREEN. `apps/web/tests/Feature/Services/EloAdvancementHookTest.php` — 3 cases proving EloRatingService integration: first result moves elo_rating + stamps rated_at; re-fire advance() is idempotent; bye bracket leaves rated_at null. EloRatingService implemented in plan 11-02 (K=32 formula, DB::transaction lockForUpdate); by_rank rewritten in plan 11-03. | **PASS** |
| SC-3 / TOUR-03 | Swiss standings show median Buchholz alongside plain Buchholz, and the tiebreaker column is visible on the public bracket view | `apps/web/tests/Feature/Services/SwissMedianBuchholzTest.php` — 2 cases: "median Buchholz calculator writes the column (not stuck at 0)"; "median Buchholz with fewer than 3 opponents equals plain Buchholz" (edge case). Implemented in plan 11-02 (Buchholz Cut-1 in SwissStandingsCalculator: drop highest + lowest opponent score when >=3 opponents). Public view: `apps/web/resources/js/components/tournaments/StandingsTable.vue` — `showMedianBuchholz` computed (format === 'swiss') gates a second tiebreak column rendering `row.median_buchholz`; typed field (not `as any`); `tournaments.standings.tiebreak_median_buchholz` i18n key. `api.d.ts` regenerated; `vue-tsc --noEmit` PASS. **PENDING_MANUAL_SMOKE**: visual pixel verification of the column rendering on the public /tournaments/{slug} page with a live Swiss tournament. | **PASS (automated); PENDING_MANUAL_SMOKE (pixel)** |
| SC-4 / TOUR-04 | An admin can set a different GameMatchType on an individual tournament stage (overriding the tournament-level default), and matches in that stage are created with the overridden type | `apps/web/tests/Feature/Services/StageMatchTypeOverrideTest.php` — 3 cases: "materialiser uses stage.game_match_type_id when set" (override path); "materialiser falls back to tournament.default_game_match_type_id when stage override is null" (default path); "materialiser throws RuntimeException when both stage override and tournament default are null" (all-null guard). `apps/web/tests/Feature/Admin/StagesRelationManagerOverrideTest.php` — 3 cases: "Select options are scoped to the tournament's game only" (cross-game guard — Pattern 3); "EditAction persists game_match_type_id on save" (persist test); "ordinal, type, and name remain read-only" (invariant). Implemented in plan 11-04. **PENDING_MANUAL_SMOKE**: visual walkthrough of the Filament StagesRelationManager Edit modal showing the scoped Select. | **PASS (automated); PENDING_MANUAL_SMOKE (UI)** |

---

## Idempotency and No-Regression Evidence

| Evidence item | Test | Plan | Status |
|---------------|------|------|--------|
| Double-fire Elo (re-firing advance on already-rated bracket leaves elo_rating + rated_at unchanged) | `EloAdvancementHookTest` — "Re-fire advance() on already-rated bracket: ratings unchanged" | 11-03 | **PASS** |
| Double-fire round (re-firing advance on already-advanced bracket creates no duplicate stage) | `SwissAutoAdvanceTest` — "re-firing advance() on an already-advanced bracket creates no duplicate stage" | 11-03 | **PASS** |
| All-1500 no-regression (D-11-03-A: by_rank with all clans at 1500 matches pre-Phase-11 sortByDesc('created_at') output) | `TournamentSeedingServiceTest` — "by_rank with all clans at 1500 matches created_at desc order (D-11-03-A no-regression)" | 11-03 | **PASS** |
| Premature Swiss completion guard (tournament does NOT auto-complete after non-final round resolves) | `SwissAutoAdvanceTest` — "tournament does NOT auto-complete after a non-final swiss round resolves" | 11-03 | **PASS** |

---

## SC Verification Commands

```bash
# SC-1: Swiss auto-advance
docker compose exec web ./vendor/bin/pest --filter='SwissAutoAdvanceTest' --no-coverage

# SC-2: Elo seeding + ELO hook
docker compose exec web ./vendor/bin/pest --filter='TournamentSeedingServiceTest|EloAdvancementHookTest|EloRatingServiceTest' --no-coverage

# SC-3: Median Buchholz logic (automated)
docker compose exec web ./vendor/bin/pest --filter='SwissMedianBuchholzTest' --no-coverage

# SC-3: Median Buchholz public view type-safety
docker compose exec web node_modules/.bin/vue-tsc --noEmit

# SC-4: Stage override (materialiser + Filament admin)
docker compose exec web ./vendor/bin/pest --filter='StageMatchTypeOverrideTest|StagesRelationManagerOverrideTest' --no-coverage
```

---

## Requirements Traceability

| Requirement | Plan(s) | Test file(s) | Status |
|-------------|---------|-------------|--------|
| TOUR-01 | 11-01 (scaffold), 11-03 (implementation) | SwissAutoAdvanceTest | **Complete** |
| TOUR-02 | 11-01 (schema), 11-02 (EloRatingService), 11-03 (seeding + hook) | EloRatingServiceTest, EloAdvancementHookTest, TournamentSeedingServiceTest | **Complete** |
| TOUR-03 | 11-01 (schema + DTO), 11-02 (calculator), 11-05 (public view + types) | SwissMedianBuchholzTest, vue-tsc (StandingsTable.vue) | **Complete** |
| TOUR-04 | 11-01 (schema), 11-04 (materialiser + Filament) | StageMatchTypeOverrideTest, StagesRelationManagerOverrideTest | **Complete** |

---

## Manual Smoke Items

The following items require operator browser verification. Automated gates prove logic + type-safety; pixel rendering requires a live browser session.

| # | Item | TOUR SC | How to verify |
|---|------|---------|---------------|
| A | Median Buchholz column visible on public Swiss standings view (`/tournaments/{slug}`) — column appears only for swiss format, absent for other formats | SC-3 / TOUR-03 | Visit a public Swiss tournament page; confirm the "Median Buchholz" column appears in the standings table alongside "Buchholz" |
| B | Filament `StagesRelationManager` EditAction opens a modal with `Game Match Type Override` Select scoped to the tournament's game only — no cross-game types appear in dropdown | SC-4 / TOUR-04 | Open an admin tournament, go to Stages tab, click Edit on a stage — confirm the Select shows only match types for the tournament's game |

---

## Phase 11 Sign-off

All 8 quality gates GREEN on `migrate:fresh --seed`. All four ROADMAP success criteria
mechanically proven by named, runnable tests. TOUR-01..04 requirements satisfied.
Two manual smoke items remain (pixel verification — no blocking defects).

Phase 11 closes COMPLETE. v1.1 continues with Phase 12 (Notifications & bot polish).
