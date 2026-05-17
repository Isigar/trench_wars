---
phase: 06-tournaments-brackets
slug: tournaments-brackets
status: PENDING_MANUAL_SMOKE
completed: 2026-05-14
plans_complete: 14
plans_total: 14
test_count: 866
test_assertions: 2719
test_passing: 866
test_failing: 0
test_incomplete: 0
bot_test_count: 139
bot_test_files: 11
quality_gates:
  pest: GREEN
  pint: GREEN
  phpstan_l8: GREEN
  vue_tsc: GREEN
  shared_types_typecheck: GREEN
  bot_tsc: GREEN
  bot_vitest: GREEN
requirements:
  - REQ-success-tournament-end-to-end
manual_smoke_required:
  - Full single-elim 8-clan run through Filament + public viewing (SC-1)
  - Swiss 6-round dry run with Buchholz tiebreaks visible (SC-2)
  - Bracket SVG rendering at 4 / 7 / 8 / 16 participants (SC-3)
  - Bot announce on bracket creation (live Discord smoke) (SC-3 + SC-4 plumbing)
canonical_model_binding: "App\\Models\\GameMatch (D-04-03-A LOCKED — inherited and re-affirmed across all 13 prior Phase 6 plans; `match` is a PHP 8.x reserved keyword for the `match($x)` expression; class is `GameMatch` while the underlying table remains `matches` via `protected $table` override; direct `use App\\Models\\GameMatch;` import everywhere — zero alias-on-import across the entire Phase 6 surface)"
---

# Phase 6 — Tournaments & brackets — Verification Report

**Date:** 2026-05-14
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual smoke section)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 6 |
| Name | Tournaments & brackets |
| Slug | tournaments-brackets |
| Plans | 14 plans (06-01 through 06-14) |
| Completed date | 2026-05-14 |
| Phase 5 foundation | Phase 5 COMPLETE (2026-05-13) |
| Canonical model name | `App\Models\GameMatch` (D-04-03-A LOCKED — see frontmatter) |
| Requirement satisfied | REQ-success-tournament-end-to-end |

---

## Status

PENDING_MANUAL_SMOKE — 4 operator walkthrough items remaining (see Manual Smoke section).

The automated test surface mechanically proves SC-1 through SC-5 via the Pest
+ Vitest matrix below. The four manual smokes cover the network/visual seams
that the test surface intentionally does not exercise (operator UX walk-through
of the full Filament → public flow, Buchholz tiebreaker eyeballing, bracket SVG
visual fidelity at the canonical participant counts, and a live Discord bot
announce against a real guild).

---

## Overview

Phase 6 delivered the complete Tournaments & brackets surface — five new
DB tables (`tournaments`, `tournament_participants`, `tournament_stages`,
`tournament_brackets`, `tournament_standings`) with the partial UNIQUE +
self-FK + no-cycle CHECK invariants, five Eloquent models with
LogsActivity, a four-strategy bracket generator (single-elim / double-elim
/ round-robin / swiss), the seed → status state machine
(`TournamentStatusService`), the participant→bracket→GameMatch
materialiser chain (`BracketMatchMaterialiserService`), the result→
advancement→standings recompute chain (`MatchResultObserver` →
`BracketAdvancementService` → `StandingsCalculatorService`), eight Spatie
laravel-data DTOs feeding the public surface, a six-tab Filament
TournamentResource with eight HeaderActions and four RelationManagers, the
public Vue 5-tab Show page (`Tournaments/Show.vue`) with custom Vue + SVG
bracket renderer (`BracketCanvas.vue`) and 30-second polling composable
(`useTournamentPolling.ts`), the per-IP-throttled JSON endpoint with ETag
short-circuit, tournament-aware i18n namespace (90+ leaf keys), audit log
integration end-to-end via `TournamentAuditLogTest`, and three new bot
outbound `kind` enums (`tournament_announce`,
`tournament_announce_update`, `bracket_result_announce`) feeding the
Phase 5 outbox + polling worker.

All five ROADMAP Success Criteria are mechanically observable against
concrete test files and source artifacts; REQ-success-tournament-end-to-end
is satisfied.

---

## [BLOCKING] Quality gates — RESULT: PASS

| Gate | Command | Result |
|------|---------|--------|
| Pest (web full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **866 passed** (2719 assertions), 0 failed, 0 incomplete, 48.87s |
| Vitest (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"` | **139 passed** (11 test files), 0 failed, 816ms |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 435 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| tsc strict (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm run typecheck"` | **PASS** — `tsc --noEmit` clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | **PASS** — clean |
| vue-tsc (web) | `docker compose exec web /app/node_modules/.bin/vue-tsc --noEmit` | **PASS** — 0 errors |
| Placeholder Wave-0 stubs | included in Pest 866 above | **PASS** — 0 incomplete |

**Test growth across phases:**

| Phase | Total Pest after phase | Phase contribution |
|-------|------------------------|--------------------|
| Phase 1 close (01-18) | ~94 tests | +94 |
| Phase 2 close (02-14) | 214 tests | +120 |
| Phase 3 close (03-10) | 278 tests | +64 |
| Phase 4 close (04-13) | 493 tests | +215 |
| Phase 5 close (05-13) | 618 tests | +125 (+117 bot Vitest) |
| Phase 6 close (06-14) | **866 tests** | **+248 web** (+22 bot Vitest) |

Phase 6 contributed 248 web Pest tests (delta 618 → 866 / +902 assertions
from 1817 → 2719) across the `Tests\Feature\Tournaments\*`,
`Tests\Feature\Models\Tournament*`, `Tests\Feature\Services\{Tournament,Bracket,Standings}*`,
`Tests\Feature\Observers\{Tournament,MatchResult}*`,
`Tests\Feature\Admin\Tournament*`, `Tests\Feature\I18n\TournamentI18nKeyCoverageTest`
and `Tests\Unit\Data\{Tournament,PublicTournament}DataTest` namespaces,
PLUS one new bot-side Vitest file (`tests/lib/tournamentEmbeds.test.ts` —
22 tests) extending the Phase 5 117 → 139 total.

---

## ROADMAP Success Criteria mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 | An admin can create a tournament, register 8 clans as participants, seed them, and generate a single-elim bracket end-to-end without manual SQL or admin patching. | `apps/web/tests/Feature/Tournaments/TournamentEndToEndTest.php` (SC-1 capstone — 2 tests covering full admin → public flow; plan 06-12), `apps/web/tests/Feature/Admin/TournamentResourceTest.php` (plan 06-11), `apps/web/tests/Feature/Admin/TournamentSeedActionTest.php` (plan 06-11), `apps/web/tests/Feature/Services/BracketGeneratorSingleEliminationTest.php` (plan 06-06 — inner_outer seeding for sizes 2/4/8/16/32 + byes), `apps/web/tests/Feature/Services/BracketMatchMaterialiserServiceTest.php` (plan 06-06 — Pitfall 4 row-locked + partial UNIQUE on match_id); manual smoke A documented below | **PASS** |
| SC-2 | The same workflow is available for round-robin, double-elim, and swiss formats with their respective bracket/round generation rules. | `apps/web/tests/Feature/Services/BracketGeneratorRoundRobinTest.php` (plan 06-07 — circle-method pairings), `apps/web/tests/Feature/Services/BracketGeneratorDoubleEliminationTest.php` (plan 06-07 — W-bracket reuses `SingleEliminationGenerator::layoutInStage` + L-bracket Burton N=8 mapping), `apps/web/tests/Feature/Services/BracketGeneratorSwissTest.php` (plan 06-07 — round 1 random + round 2+ Buchholz-aware pairing + Pitfall 5 `SwissTooFewParticipantsException`); manual smoke B documented below | **PASS** |
| SC-3 | A public visitor can open a tournament page at `/tournaments/{slug}` and switch between Overview, Bracket, Schedule, Standings, and Participants tabs; the bracket renders in custom Vue + SVG components with live polling every 30s during active rounds. | `apps/web/tests/Feature/Tournaments/TournamentShowPageTest.php` (plan 06-12 — Inertia `Tournaments/Show` component renders with `PublicTournamentData` props for guests + 404 paths), `apps/web/tests/Feature/Tournaments/TournamentIndexPageTest.php` (plan 06-12 — public listing excludes private/draft/cancelled), `apps/web/tests/Feature/Tournaments/TournamentPublicJsonControllerTest.php` (plan 06-12 — ETag short-circuit + If-None-Match → 304 + `throttle:60,1` rate limit), `apps/web/tests/Unit/Data/PublicTournamentDataTest.php` (plan 06-10 — node/edge composition + 4-state ladder bye detection); manual smoke C documented below | **PARTIAL — automated GREEN; visual fidelity pending operator smoke** |
| SC-4 | When a bracket match finishes, `winner_participant_id` is recorded and the next bracket pulls participants via `advances_to_bracket_id` automatically; standings recompute with format-appropriate tiebreakers. | `apps/web/tests/Feature/Services/BracketAdvancementServiceTest.php` (plan 06-08 — `from.position % 2 === 1 ? 'a' : 'b'` slot resolve rule + `Tournament::lockForUpdate` inside DB::transaction — Pitfall 6 mitigation), `apps/web/tests/Feature/Observers/MatchResultObserverTest.php` (plan 06-08 — two-hook `created` + `updated` pattern, NOT `saved`; dispatches `BracketAdvancementService::advance` on every MatchResult save with `winner_clan_id` set), `apps/web/tests/Feature/Services/StandingsCalculatorServiceTest.php` (plan 06-09 — 4 strategy variants: single-elim final rank, double-elim composite rank, round-robin FIFA 3/1/0, swiss plain Buchholz), `apps/web/tests/Feature/Tournaments/TournamentEndToEndTest.php` (SC-1 capstone exercises the full advancement chain at end-to-end scale) | **PASS** |
| SC-5 | Admin can reseed (when no matches played in a stage), forfeit, withdraw a participant, and recalculate standings via Filament actions, all audited. | `apps/web/tests/Feature/Admin/TournamentReseedActionTest.php` (plan 06-11 — A4 LOCKED: reseed gated on `Tournament::canReseed()` = no MatchResult rows exist for any bracket-linked match), `apps/web/tests/Feature/Admin/TournamentForfeitActionTest.php` (plan 06-11 — A5 LOCKED: forfeit semantics), `apps/web/tests/Feature/Admin/TournamentWithdrawActionTest.php` (plan 06-11 — A5 LOCKED: withdraw identical forward semantics; only audit reason + status string differ), `apps/web/tests/Feature/Admin/TournamentRecalculateStandingsTest.php` (plan 06-11 — admin recompute action), `apps/web/tests/Feature/Admin/TournamentAuditLogTest.php` (plan 06-13 — LogsActivity for every Tournament + TournamentParticipant + TournamentStanding mutation through real Filament action flow) | **PASS** |

**SC verification commands:**

```bash
# SC-1: End-to-end capstone + admin resource + bracket generation + materialisation
docker compose exec web ./vendor/bin/pest --filter='TournamentEndToEnd|TournamentResource|TournamentSeedAction|BracketGeneratorSingleElimination|BracketMatchMaterialiserService' --no-coverage

# SC-2: Round-robin + double-elim + swiss generators
docker compose exec web ./vendor/bin/pest --filter='BracketGeneratorRoundRobin|BracketGeneratorDoubleElimination|BracketGeneratorSwiss' --no-coverage

# SC-3: Public Show + Index + JSON endpoint with ETag + DTO node/edge composition
docker compose exec web ./vendor/bin/pest --filter='TournamentShowPage|TournamentIndexPage|TournamentPublicJsonController|PublicTournamentData' --no-coverage

# SC-4: Advancement service + MatchResult observer + standings recompute
docker compose exec web ./vendor/bin/pest --filter='BracketAdvancementService|MatchResultObserver|StandingsCalculatorService' --no-coverage

# SC-5: Filament reseed/forfeit/withdraw/recalc admin actions + audit log
docker compose exec web ./vendor/bin/pest --filter='TournamentReseedAction|TournamentForfeitAction|TournamentWithdrawAction|TournamentRecalculateStandings|TournamentAuditLog' --no-coverage
```

---

## Requirements traceability

| Requirement | Description | Test file(s) | Status |
|-------------|-------------|--------------|--------|
| REQ-success-tournament-end-to-end | An 8-clan single-elim tournament can be created, seeded, brackets generated, matches materialised, played, results captured, advancements computed — without admin patching. | All 5 SCs above. The 248-test Phase 6 web Pest contribution (1817 → 2719 assertions) plus the 22-test bot Vitest contribution prove the requirement landed without breaking any prior phase. The `TournamentEndToEndTest::it_SC1_capstone_8_clan_single_elim_end_to_end_through_the_observer_chain` test is the literal verifier of REQ-success-tournament-end-to-end. | **PASS** |

REQ-success-tournament-end-to-end is the single requirement mapped to
Phase 6 in `REQUIREMENTS.md`. All five success criteria collectively prove
this requirement is satisfied — admin can drive an 8-clan single-elim
tournament from create → register → seed → generate brackets → materialise
GameMatches → record results → walk advancements → recompute standings,
without any admin patching, and with full audit log coverage.

---

## Open Questions RESOLVED Inline During Planning

| Q | Topic | Resolution | Where LOCKED |
|---|-------|-----------|--------------|
| A4 | Reseed eligibility threshold | "No MatchResult rows exist for any bracket-linked match" (strictest interpretation) | Plan 06-05 — `Tournament::canReseed()` (D-06-05-A) |
| A5 | Forfeit vs withdraw semantics | IDENTICAL forward semantics (both stop future matches; past results retained); different status strings (`disqualified` vs `withdrawn`) for audit clarity | Plans 06-09 + 06-11 (D-06-11-B) |
| A6 | Swiss next-round trigger | Admin click via Filament HeaderAction `generate_next_swiss_round` (auto-trigger queue deferred to Phase 9 polish) | Plans 06-07 + 06-11 (D-06-07-B + D-06-11-C) |
| A8 | Tournament organiser role | Admin-only via existing Phase 1 `admin-access` permission (`canAccessPanel` gate); separate `tournament.manage` permission deferred to v2 | Plan 06-11 (D-06-11-A) |
| 5 (RESEARCH § Open Questions) | Bot integration kind enums | 3 distinct outbound kinds: `tournament_announce`, `tournament_announce_update`, `bracket_result_announce` | Plans 06-08 + 06-10 + 06-13 (D-06-13-A) |

---

## Pitfall Coverage Matrix

12 pitfalls from `06-RESEARCH.md`; each mapped to a concrete mitigation
test and/or source artifact.

| # | Pitfall | Mitigation (file + plan) | Status |
|---|---------|--------------------------|--------|
| 1 | PHP 8 `match` reserved keyword collision with class name | **D-04-03-A LOCKED continuation** — `App\Models\GameMatch` direct import everywhere in Phase 6 surface (services, observers, DTOs, resources, tests); zero `use App\Models\Match as MatchModel` alias-on-import; `TournamentBracket::match()` relation uses explicit `'match_id'` FK arg per D-04-03-B / D-06-03-A | mitigated |
| 2 | Bracket `round_number` off-by-one when computing `advances_to_position` | `apps/web/app/Services/BracketGenerators/SingleEliminationGenerator.php` — `(int) ceil($p / 2)` formula verified by `BracketGeneratorSingleEliminationTest` (plan 06-06) | mitigated |
| 3 | Bracket generator non-idempotency (regeneration corrupts state) | `BracketsAlreadyGeneratedException` (plan 06-04 D-06-04-B forward-declared) + `Tournament::stages()->exists()` guard in `BracketGeneratorService::generate()` (plan 06-06); covered by `BracketGeneratorSingleEliminationTest::it_throws_when_already_generated` | mitigated |
| 4 | Bracket → GameMatch materialisation race (concurrent admin clicks materialise the same bracket twice) | `BracketMatchMaterialiserService::materialiseFor` wraps the bracket-row lookup in `DB::transaction()` + `TournamentBracket::query()->lockForUpdate()` + partial UNIQUE index on `match_id WHERE NOT NULL` (plans 06-02 + 06-06 — D-06-06-B); covered by `BracketMatchMaterialiserServiceTest::it_is_idempotent_under_concurrent_calls` | mitigated |
| 5 | Swiss never-paired-before infinite loop on too few participants | `SwissTooFewParticipantsException` thrown when `participants_count < 2^ceil(log2(N))` (plan 06-07 — D-06-07-C); covered by `BracketGeneratorSwissTest::it_throws_swiss_too_few_participants` | mitigated |
| 6 | Standings recalculate trampling (concurrent advancement triggers overlap) | `DB::transaction()` + `Tournament::query()->lockForUpdate()` in both `StandingsCalculatorService::recompute` (plan 06-09 — D-06-09-B wipe-and-recompute) and `BracketAdvancementService::advance` (plan 06-08 — D-06-08-B) | mitigated |
| 7 | TournamentObserver double-fire on save() touch | `wasChanged('status')` gate in `TournamentObserver::updated()` body + `is_public` AND-gate (plan 06-10 — D-06-10-B / D-06-10-E); covered by `TournamentObserverTest` | mitigated |
| 8 | Polling endpoint cache staleness (CDN/HTTP cache layer doesn't see in-app mutations) | ETag computed per-request from `tournament.updated_at + sorted(brackets[id:updated_at])` via `sha1()` — NO application-level cache (plans 06-10 + 06-12 — D-06-10-B / D-06-12-A); covered by `TournamentPublicJsonControllerTest::it_emits_an_etag_header_that_matches_the_etag_in_the_body` + `it_returns_304_Not_Modified_when_If_None_Match_matches_the_current_etag` | mitigated |
| 9 | SVG renderer x/y math for double-elim L-bracket layout | `apps/web/resources/js/Components/Tournaments/BracketCanvas.vue` groups bracket nodes by `stage_type` with separate `<g transform="translate(...)">` blocks + cumulative `stageYOffset` accumulator (plan 06-12 — D-06-12-B) | mitigated |
| 10 | i18n key explosion across 4 formats × multiple surfaces | `TournamentI18nKeyCoverageTest` (plan 06-13 — leaf-anchored regex covers 90+ keys: formats × 4, status × 6, participant_status × 4, errors × 8, actions × 36, tabs × 5, empty × 3, stage_types × 6); pre-shipped in `apps/web/lang/en/tournaments.php` (plan 06-01) to prevent NoHardcodedStringsTest + MissingTranslationException mid-execution across plans 06-02..06-13 | mitigated |
| 11 | `tournament_brackets` self-FK cycle (advances_to_bracket_id pointing back at itself) | DB-level CHECK `no_self_advance` covering both `advances_to_bracket_id` and `loser_advances_to_bracket_id` in a single constraint (plan 06-02 — D-06-02-C; NULL != id allowed so un-materialised brackets coexist) + service-level single-hop walk in `BracketAdvancementService` (plan 06-08); enforced by Postgres at write time | mitigated |
| 12 | Forfeit/withdraw timing leaks (already-played matches retroactively rewritten) | A5 LOCKED — identical forward semantics; past matches retain results; only future matches get the forfeit/withdraw treatment (plans 06-09 + 06-11 — D-06-11-B); covered by `TournamentForfeitActionTest` + `TournamentWithdrawActionTest` | mitigated |

---

## RESEARCH Assumptions Status

| # | Assumption | Status |
|---|-----------|--------|
| A1 | Inner_outer ordering for size=8 → [1,8,4,5,2,7,3,6] | VERIFIED via `BracketGeneratorSingleEliminationTest::it_lays_out_size_8_in_canonical_inner_outer_order` (D-06-06-B INNER_OUTER_ORDERINGS hardcode for sizes 2/4/8/16/32; sizes > 32 use recursive `computeInnerOuter()`) |
| A2 | Swiss minimum N for ceil(log2(N)) rounds is 2^rounds | VERIFIED via D-06-07-C / Pitfall 5 mitigation; `BracketGeneratorSwissTest::it_throws_swiss_too_few_participants` |
| A3 | Plain Buchholz tiebreak (no median variant) | LOCKED for v1 per D-06-09-H; median variant deferred to Phase 9 polish |
| A4 | Reseed eligibility = no MatchResult exists | LOCKED in plan 06-05 (D-06-05-A) — `Tournament::canReseed()` returns true ONLY when `status='seeded'` AND no MatchResult rows exist for any bracket-linked match |
| A5 | Forfeit + withdraw identical forward semantics | LOCKED in plans 06-09 + 06-11 (D-06-11-B) |
| A6 | Swiss next-round admin click | LOCKED in plans 06-07 + 06-11 (D-06-07-B / D-06-11-C); auto-trigger queue deferred to Phase 9 |
| A7 | Double-elim L-bracket spread loser pairs across L-bracket halves (N=8 layout) | LOCKED via D-06-07-B; Burton mapping hardcoded for N=8 and cross-checked vs brackets-manager.js Context7 reference |
| A8 | Tournament organiser admin-only | LOCKED in plan 06-11 (D-06-11-A); admin-access permission reused, separate organiser tier deferred to v2 |
| A9 | Single `default_game_match_type_id` per tournament (no per-bracket override) | LOCKED for v1; stage-level override = Phase 9 polish |
| A10 | Bracket-spawned GameMatch.host_clan_id = NULL | LOCKED in plan 06-06 `BracketMatchMaterialiserService` (D-06-06-C / D-06-06-D); both bracket participants are guests |
| A11 | `by_rank` seeding v1 = `tournament_participants.created_at desc` (deterministic proxy for skill rank) | LOCKED in plan 06-05 (D-06-05-B); ELO upgrade = Phase 9 polish |

---

## Canonical Phase 6 Bindings (D-06-* — for Phase 7+ continuation)

| ID | Decision |
|----|----------|
| D-06-01-A | Wave 0 factory stubs adopt the Phase 3 commit 1d4d736 idiom verbatim — string FQN `$model` + per-line `@phpstan-ignore` (missingType.generics + property.defaultValue); plan 06-03 replaces with typed-generic factories once the 5 Tournament models exist |
| D-06-01-B | Wave 0 Pest stubs use bare functional convention (Phase 5 D-05-01-C canonical idiom) — no namespace, no per-file `uses()` call; Pest.php autowires TestCase + RefreshDatabase via `uses(...)->in('Feature')` |
| D-06-01-C | Phase 6 i18n namespace pre-shipped in full (`apps/web/lang/en/tournaments.php`, 90+ leaf keys) rather than incremental per-plan; prevents NoHardcodedStringsTest + MissingTranslationException mid-execution across plans 06-02..06-13 |
| D-06-02-A | Self-FKs on `tournament_brackets` declared in a separate `Schema::table()` block to avoid Laravel ADD PRIMARY KEY ordering quirk |
| D-06-02-B | `tournament_standings` UNIQUE composite is `(stage_id, participant_id)` NOT `(tournament_id, participant_id)` — round-robin stages carry distinct standings per participant |
| D-06-02-C | `no_self_advance` CHECK covers BOTH advance pointers in a single CHECK; NULL != id allowed (NULL not FALSE in Postgres) so un-materialised brackets coexist |
| D-06-03-A | `TournamentBracket::match()` uses explicit FK arg `'match_id'` (D-04-03-B continuation) — auto-inferred `'game_match_id'` doesn't exist on `tournament_brackets`; same rule for `advancesTo`/`loserAdvancesTo` self-FK args |
| D-06-03-B | Phase 6 models use `Spatie\Activitylog\Models\Concerns\LogsActivity` (canonical v5 path), not the older Traits path referenced in the plan's `<interfaces>` sample |
| D-06-03-C | `getActivitylogOptions()` across all 5 Phase 6 models includes `dontLogIfAttributesChangedOnly(['updated_at'])` so timestamp-only touches don't pollute activity_log |
| D-06-04-A | `TournamentStatusService::transition()` signature uses `?User $causer = null` + `Tournament` return type — diverges from Phase 4 `MatchStatusService` (required `User` + `void`) to enable Filament admin actions to omit causer arg + fluent chaining |
| D-06-04-B | `BracketsAlreadyGeneratedException` ships in plan 06-04 (not 06-06) to break circular dependency — plan 06-06 `BracketGeneratorService` imports it from here |
| D-06-04-C | Activity log description uses format-string `'Tournament status: {from} -> {to}'` (more descriptive than Phase 4 static `'Match status transition'`) for visual scan-ability in Filament audit log |
| D-06-05-A | Open Question A4 RESOLVED — `Tournament::canReseed()` returns true ONLY when `status='seeded'` AND no MatchResult rows exist for any bracket-linked match |
| D-06-05-B | `by_rank` v1 uses `tournament_participants.created_at desc` as deterministic proxy for skill rank (RESEARCH Assumption A11; Phase 9 ELO upgrade tracked) |
| D-06-05-C | PHP `match` dispatch on strategy gets explicit `default => throw new InvalidArgumentException` arm — satisfies PHPStan L8 `match.unhandled` + clear runtime error for typo callers |
| D-06-05-D | `reseed()` audit-log `previous_seeds` + `new_seeds` maps keyed by `clan_id` (stable cross-reseed identity; only the seed column is rewritten) |
| D-06-06-A | `BracketGeneratorService` ships all 4 strategies; 3 are stubs (DoubleElim/RoundRobin/Swiss) in plan 06-06 — plan 06-07 only replaces stub bodies, not constructor signature |
| D-06-06-B | `INNER_OUTER_ORDERINGS` hardcodes sizes 2/4/8/16/32; sizes > 32 use recursive `computeInnerOuter()` validated against the hardcoded 32-element case |
| D-06-06-C | `GameMatch` ships single `scheduled_at` column (not `scheduled_start_at` + `scheduled_end_at` as plan scaffold suggested); aligned with actual Phase 4 migration |
| D-06-06-D | A10 LOCKED — bracket-spawned `GameMatch.host_clan_id = NULL`; both participants are guests |
| D-06-06-E | `BracketMatchMaterialiserService` throws `RuntimeException` (not `DomainException`) when `default_game_match_type_id` is null |
| D-06-06-F | Bracket `GameMatch.title` inherits `tournament.getTranslations('title')` — JSONB locales map (D-013) |
| D-06-06-G | Bye-winner round-2 slot rule — odd round-1 position p → `participant_a_id`; even p → `participant_b_id` |
| D-06-07-A | `SingleEliminationGenerator` refactor — extracted `layoutInStage()` public static helper for `DoubleEliminationGenerator` W-bracket reuse |
| D-06-07-B | Burton L-bracket N=8 hardcoded loser-drop mapping verified vs brackets-manager.js |
| D-06-07-C | Pitfall 5 narrows v1 swiss tournaments to powers of 2 (N must equal `2^ceil(log2(N))`) |
| D-06-08-A | Two-hook `MatchResultObserver` pattern (`created` + `updated`, NOT `saved`) — `saved` cannot distinguish create from touch on the pinned Laravel version since `wasRecentlyCreated` stays true forever on the same instance |
| D-06-08-B | `BracketAdvancementService` routes through `Tournament::lockForUpdate` inside `DB::transaction` (Pitfall 6) |
| D-06-08-C | `resolveSlot` rule: `from.position % 2 === 1 ? 'a' : 'b'` (Pattern 7) |
| D-06-08-D | Phase 5 `discord_outbound_messages.message_type` CHECK extended via migration `2026_05_15_100500` to allow `bracket_result_announce` (Postgres drop+recreate idiom) |
| D-06-08-G | `StandingsCalculatorService` ships as no-op stub in plan 06-08 (replaced by plan 06-09); resolved via `app()` lookup at `BracketAdvancementService::advance()` call site to break circular DI cycle |
| D-06-09-A | `TournamentStage::brackets()` default ordering requires `->reorder()` before `->orderByDesc('round_number')` to escape the relationship's default ASC sort |
| D-06-09-B | Wipe-and-recompute strategy for standings (small table) inside `DB::transaction`; rolls back atomically on failure |
| D-06-09-F | Round-robin default points scheme is FIFA 3/1/0; admin override via `tournament.settings.roundrobin_points_per_{win,draw,loss}` (D-06-09-A name update from plan-time D-06-09-A) |
| D-06-09-H | Swiss tiebreaker is plain Buchholz only; median Buchholz variant deferred to Phase 9 |
| D-06-10-A | `PublicTournamentData` composes `BracketNodeData` + `BracketEdgeData` inline; Vue SVG renderer (plan 06-12) receives one DTO + renders entire bracket tree without further API calls; ETag short-circuits unchanged JSON polling responses |
| D-06-10-B | Etag = `sha1(tournament.updated_at | sorted bracket id:updated_at)`. Deterministic across identical-state calls; standings excluded from v1 etag input (Phase 9 polish) |
| D-06-10-C | `BracketNodeData` 4-state ladder bye check FIRST. Single-elim generators auto-set `winner_participant_id = participantA` for byes without materialising a `match_id`; naive completed-first ordering mis-classifies byes |
| D-06-10-E | `TournamentObserver` outbound `channel_id = ''` (empty string), not null. `discord_outbound_messages.channel_id` is text NOT NULL; bot worker resolves the channel at dispatch time. Matches `BracketAdvancementService` convention (plan 06-08) |
| D-06-10-F | `tournament_announce` + `tournament_announce_update` added to `doutmsg_message_type_chk` via migration `2026_05_15_100600`. Same drop+add pattern as plan 06-08's migration for `bracket_result_announce` |
| D-06-10-H | `TournamentModelTest` event `MorphOne` test updated in-place. Pre-existing test was written for plan 06-03 stub state; observer auto-creation now creates the Event row, so manual `Event::create` collides on UNIQUE. Updated to assert auto-created row resolves through `MorphOne` + private no-event invariant |
| D-06-11-A | A8 LOCKED inline — admin-only via existing Phase 1 `admin-access` permission (`canAccessPanel`), NOT a new `tournament.manage` permission |
| D-06-11-B | A5 LOCKED inline — forfeit + withdraw row actions have identical forward semantics; only status string + audit reason differ |
| D-06-11-C | Open Question A6 LOCKED inline — Swiss next-round generation is admin-click via `generate_next_swiss_round` HeaderAction (auto-trigger queue deferred to Phase 9) |
| D-06-11-E | Added `Tournament::brackets()` HasManyThrough relation for `BracketsRelationManager` + future `PublicTournamentData` consumers |
| D-06-12-A | `/tournaments/{slug}.json` under `throttle:60,1`; `If-None-Match` → 304 short-circuit (Pattern 9) |
| D-06-12-B | `BracketCanvas.vue` groups nodes by `stage_type` with separate `<g>` + cumulative `stageYOffset` (Pitfall 9) |
| D-06-12-C | Routes for `/tournaments/{slug}.json` declared BEFORE `/tournaments/{slug}` so Laravel first-match-wins dispatcher captures `.json` suffix correctly |
| D-06-12-D | `config/i18n.php` `shared_namespaces` extended with `matches` + `tournaments` (matches was pre-existing gap) |
| D-06-12-E | SC-1 capstone test walks downstream brackets via iterative `materialiseFor()` loop (`materialiseFirstRound` only handles round 1) |
| D-06-13-A | Bot kinds: 3 distinct enums (`tournament_announce`, `tournament_announce_update`, `bracket_result_announce`) — Open Question 5 LOCKED |
| D-06-13-B | Bot embed builders ship in `apps/bot/src/lib/embeds.ts` (extending Phase 5 single-file convention) |
| D-06-13-C | i18n coverage gate uses leaf-anchored regex so string-concat dynamic keys are excluded from source-grep |

---

## Locked Decisions Honored

### Project-level decisions (PROJECT.md D-### table)

| Decision | Honored | Evidence |
|----------|---------|----------|
| **D-001** Stack: Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 | YES | Phase 6 added no new framework deps; reused Spatie laravel-data + spatie/activitylog from prior phases |
| **D-002** Auth: Discord OAuth only; Discord ID is canonical | YES | Tournament organiser path uses existing admin-access permission (D-06-11-A); zero new auth surface |
| **D-007** Generic Game/Role/MatchType tables; HLL seeded | YES | `Tournament::default_game_match_type_id` resolves via Phase 3 `GameMatchType` model; bracket materialiser inherits the slot template (D-06-06-C / D-06-06-D) |
| **D-010** Match signups row-locked | YES | Phase 4 `MatchSignupService` is reused unchanged for bracket-spawned GameMatch signup flows; D-04-06 5-guard order applies verbatim |
| **D-011** Tournaments first-class round 1 (4 formats) | YES | All 4 formats (single-elim / double-elim / round-robin / swiss) ship with generators + Filament resource + Pest coverage; SC-2 mechanically verified |
| **D-012** Filament + spatie/activitylog audit infra | YES | All 5 Phase 6 models use `LogsActivity` trait (D-06-03-B); admin actions write activity_log rows (verified by `TournamentAuditLogTest` — plan 06-13) |
| **D-013** i18n plumbed; EN at launch; every UI string via `__()` / `t()` | YES | `apps/web/lang/en/tournaments.php` pre-shipped in plan 06-01 (D-06-01-C — 90+ leaf keys); `TournamentI18nKeyCoverageTest` (plan 06-13) audits end-to-end with leaf-anchored regex (D-06-13-C) |
| **D-014** Railway 5 services + Postgres + Redis | YES | No new services; bracket result announcements ride the existing Phase 5 `worker` + `bot` services via the durable `discord_outbound_messages` outbox |
| **D-015** pnpm-workspaces monorepo | YES | Bot embed builders extend `apps/bot/src/lib/embeds.ts` (D-06-13-B); shared-types re-exports unchanged |
| **D-017** No starter kit; hand-rolled | YES | TournamentResource hand-rolled in plan 06-11; no Filament shell-component dependency |
| **D-021** Local dev via docker-compose; host PHP/Postgres/Redis NOT used | YES | Every Phase 6 plan executed via `docker compose exec web ...` (Pest, Pint, PHPStan) + `docker compose run --rm bot ...` (bot Vitest); zero host-PHP invocations |

### D-04-03-A continuation (canonical model name binding into Phase 6+)

**CRITICAL for Phase 7+ executors:** The model class is `App\Models\GameMatch`,
NOT `App\Models\Match`. This is locked by D-04-03-A and re-affirmed across
all 13 prior Phase 6 plans (zero `App\Models\Match as MatchModel`
alias-on-import anywhere in the Phase 6 codebase surface). Phase 7 CMS
plans + Phase 8 RCON plans MUST:

- Import via `use App\Models\GameMatch;` directly (no alias).
- Pass `match_id` as explicit FK arg on every `BelongsTo<GameMatch, $this>` relation method (D-04-03-B / D-06-03-A continuation).
- Use `$this->table = 'matches'` to keep the underlying SQL table name unchanged.
- Reference relation methods by `match()` (PHP allows reserved words as method names — only class names collide).

---

## Pest full suite snapshot

**Executed:** `docker compose exec web ./vendor/bin/pest --no-coverage`

```
Tests:    866 passed (2719 assertions)
Duration: 48.87s
```

**All test classes PASS. 0 failures, 0 skipped, 0 incomplete.**

Phase 6 added the following web Pest test classes (sourced from plans
06-01 through 06-13):

| Test class | Location | Plan source |
|------------|----------|-------------|
| `TournamentModelTest` | `tests/Feature/Models/` | 06-03 |
| `TournamentParticipantModelTest` | `tests/Feature/Models/` | 06-03 |
| `TournamentStageModelTest` | `tests/Feature/Models/` | 06-03 |
| `TournamentBracketModelTest` | `tests/Feature/Models/` | 06-03 |
| `TournamentStandingModelTest` | `tests/Feature/Models/` | 06-03 |
| `TournamentStatusServiceTest` | `tests/Feature/Services/` | 06-04 |
| `TournamentSeedingServiceTest` | `tests/Feature/Services/` | 06-05 |
| `BracketGeneratorSingleEliminationTest` | `tests/Feature/Services/` | 06-06 |
| `BracketGeneratorDoubleEliminationTest` | `tests/Feature/Services/` | 06-07 |
| `BracketGeneratorRoundRobinTest` | `tests/Feature/Services/` | 06-07 |
| `BracketGeneratorSwissTest` | `tests/Feature/Services/` | 06-07 |
| `BracketMatchMaterialiserServiceTest` | `tests/Feature/Services/` | 06-06 |
| `BracketAdvancementServiceTest` | `tests/Feature/Services/` | 06-08 |
| `MatchResultObserverTest` | `tests/Feature/Observers/` | 06-08 |
| `StandingsCalculatorServiceTest` | `tests/Feature/Services/` | 06-09 |
| `TournamentObserverTest` | `tests/Feature/Observers/` | 06-10 |
| `TournamentDataTest` | `tests/Unit/Data/` | 06-10 |
| `PublicTournamentDataTest` | `tests/Unit/Data/` | 06-10 |
| `TournamentResourceTest` | `tests/Feature/Admin/` | 06-11 |
| `TournamentSeedActionTest` | `tests/Feature/Admin/` | 06-11 |
| `TournamentReseedActionTest` | `tests/Feature/Admin/` | 06-11 |
| `TournamentForfeitActionTest` | `tests/Feature/Admin/` | 06-11 |
| `TournamentWithdrawActionTest` | `tests/Feature/Admin/` | 06-11 |
| `TournamentRecalculateStandingsTest` | `tests/Feature/Admin/` | 06-11 |
| `TournamentEndToEndTest` | `tests/Feature/Tournaments/` | 06-12 (SC-1 capstone) |
| `TournamentIndexPageTest` | `tests/Feature/Tournaments/` | 06-12 |
| `TournamentShowPageTest` | `tests/Feature/Tournaments/` | 06-12 |
| `TournamentPublicJsonControllerTest` | `tests/Feature/Tournaments/` | 06-12 |
| `TournamentI18nKeyCoverageTest` | `tests/Feature/I18n/` | 06-13 |
| `TournamentAuditLogTest` | `tests/Feature/Admin/` | 06-13 |

Total: 248 Phase 6 web Pest tests / 902 assertions (delta from Phase 5
close of 618 → 866 / 1817 → 2719).

## Vitest full suite snapshot

**Executed:** `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"`

```
 Test Files  11 passed (11)
      Tests  139 passed (139)
   Duration  816ms
```

Phase 6 added one new bot Vitest file:

| Test file | Tests | Plan source |
|-----------|-------|-------------|
| `tests/lib/tournamentEmbeds.test.ts` | 22 | 06-13 |

Phase 5 baseline retained: `tests/skeleton.test.ts` (2), `tests/lib/customIds.test.ts`
(22), `tests/lib/embeds.test.ts` (20), `tests/commands/match.test.ts` (13),
`tests/commands/clan.test.ts` (9), `tests/commands/profile.test.ts` (5),
`tests/components/rsvpButton.test.ts` (16), `tests/components/signupModal.test.ts`
(11), `tests/services/outbound.test.ts` (11), `tests/events/guildMemberUpdate.test.ts`
(8) = 117 + 22 new = 139 total.

---

## Test Inventory by Category

| Category | Phase 6 Test Files | Phase 6 Test Count | Notes |
|----------|--------------------|--------------------|-------|
| Models | 5 (`Tournament*ModelTest`) | ~25 | All 5 new tables covered (D-06-02-A..C invariants verified) |
| Services | 9 (`Tournament{Status,Seeding}` + `BracketGenerator{Single,Double,Round,Swiss}` + `BracketMatchMaterialiser` + `BracketAdvancement` + `StandingsCalculator`) | ~80 | 4 format generators + advancement chain + standings + seeding + state machine |
| Observers | 2 (`Tournament` + `MatchResult`) | ~15 | Double-fire guards (D-06-08-A two-hook + D-06-10-B wasChanged gate) |
| DTOs / Unit Data | 2 (`Tournament` + `PublicTournament`) | ~15 | Node/edge composition + 4-state ladder bye detection (D-06-10-C) |
| Admin (Filament) | 7 (`TournamentResource` + `TournamentSeed/Reseed/Forfeit/Withdraw/RecalculateStandings/AuditLog`) | ~40 | All 8 HeaderActions + 4 RelationManagers exercised |
| Public (Inertia + JSON) | 4 (`TournamentEndToEnd` + `TournamentIndexPage` + `TournamentShowPage` + `TournamentPublicJsonController`) | ~25 | SC-1 capstone + Inertia component + ETag + throttle:60,1 |
| I18n | 1 (`TournamentI18nKeyCoverageTest`) | ~15 | Leaf-anchored regex against 90+ key tournaments.php namespace |
| Bot Vitest | 1 (`tournamentEmbeds.test.ts`) | 22 | Phase 6 outbound embed surface (3 new kinds — D-06-13-A) |
| **Total** | **31 web + 1 bot** | **248 web + 22 bot = 270** | Δ from Phase 5 close: +248 web (+902 assertions) + 22 bot |

---

## Static analysis snapshot

| Tool | Command | Result |
|------|---------|--------|
| Pint (style) | `./vendor/bin/pint --test` | PASS — 435 files clean |
| PHPStan L8 | `./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | [OK] No errors |
| NoHardcodedStringsTest | included in Pest suite | PASS |
| BotI18nKeyCoverageTest (Phase 5 carry-forward) | included in Pest suite | PASS |
| TournamentI18nKeyCoverageTest (Phase 6 new) | included in Pest suite | PASS |
| vue-tsc | `/app/node_modules/.bin/vue-tsc --noEmit` | PASS — 0 type errors |
| bot tsc strict | `pnpm run typecheck` in apps/bot | PASS — clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | PASS — clean |

**PHPStan baseline note**: `apps/web/phpstan-baseline.neon` continues to
absorb vendor-internal deprecation traces from Filament v3 + PHP 8.4
(RESEARCH Pitfall 9, established in Phase 1). Phase 6 added no new
baseline rows. Current run reports `[OK] No errors`.

---

## Grep gate verification

Run-time invariants from plan 06-14 acceptance criteria:

| Gate | Command | Expected | Actual |
|------|---------|----------|--------|
| `App\Models\GameMatch` direct import in Phase 6 surface | `grep -rc 'use App\\Models\\GameMatch' apps/web/app/Services apps/web/app/Observers` | ≥ 1 each | verified (zero alias-on-import D-04-03-A continuation) |
| TournamentObserver registered on Tournament model | `grep -c 'static::observe(TournamentObserver' apps/web/app/Models/Tournament.php` or `protected $observers` array | ≥ 1 | verified during plan 06-10 |
| MatchResultObserver registered on MatchResult model | `grep -c 'static::observe(MatchResultObserver' apps/web/app/Models/MatchResult.php` | ≥ 1 | verified during plan 06-08 |
| `tournament_brackets` no-cycle CHECK present | psql `\d tournament_brackets` | `CHECK no_self_advance` present | verified during plan 06-02 |
| `tournament_standings` UNIQUE composite | psql `\d tournament_standings` | UNIQUE `(stage_id, participant_id)` | verified during plan 06-02 |
| `tournament_brackets` partial UNIQUE on match_id | psql `\d tournament_brackets` | UNIQUE `match_id WHERE match_id IS NOT NULL` | verified during plan 06-02 |
| `discord_outbound_messages.message_type` CHECK extended | psql `\d discord_outbound_messages` | CHECK allows `tournament_announce` + `tournament_announce_update` + `bracket_result_announce` | verified via plan 06-08 (bracket_result_announce) + plan 06-10 (tournament_announce + tournament_announce_update) migrations |
| Tournament i18n namespace shipped | `wc -l apps/web/lang/en/tournaments.php` | 90+ leaf keys | verified during plan 06-01 |
| `/tournaments/{slug}.json` route declared BEFORE `/tournaments/{slug}` | `grep -n 'tournaments' apps/web/routes/web.php` | .json line precedes non-.json line | verified during plan 06-12 |

All gates PASS.

---

## Must-have traceability

| M# | Must-have | Source | Result |
|----|-----------|--------|--------|
| M1 | All 7 quality gates GREEN: pest + vitest + pint + phpstan + tsc + shared-types + vue-tsc | 06-14 acceptance | PASS — 866/866 + 139/139 + 435 clean + [OK] + clean + clean + clean |
| M2 | shared-types pipeline regressionless | 06-14 acceptance | PASS — `pnpm --filter @trenchwars/shared-types typecheck` clean |
| M3 | 06-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-success-tournament-end-to-end + 12 RESEARCH pitfalls + 5 open questions + 22 D-06-* bindings | 06-14 acceptance | PASS — this document |
| M4 | ROADMAP.md Phase 6 entry updated: 14/14 Complete + Completed date + plan list flips all 14 to [x] | 06-14 acceptance | PASS — see ROADMAP.md surgical edits |
| M5 | REQUIREMENTS.md REQ-success-tournament-end-to-end flipped from Pending → Complete in v1 traceability table | 06-14 acceptance | PASS — see REQUIREMENTS.md surgical edits |
| M6 | STATE.md updated: phase 5 → 6 closed; completed_phases=6; completed_plans=82; percent=67; performance metrics appended; D-06-* bindings appended to Accumulated Decisions | 06-14 acceptance | PASS — see STATE.md surgical edits |
| M7 | Activity log integration verified end-to-end via TournamentAuditLogTest | 06-14 acceptance | PASS — plan 06-13 GREEN |
| M8 | Status flag PENDING_MANUAL_SMOKE for the 4 manual items A-D | 06-14 acceptance | PASS — frontmatter flag set; manual smoke checklist A-D below |

---

## Manual Smoke Checklist (PENDING_MANUAL_SMOKE)

Operator must verify out-of-band against a live Discord guild + production
Railway environment. The automated test suite exercises every contract
via Filament test harness + Inertia component assertions + ETag + DB
invariants + mocked discord.js surfaces; the smokes below cover the
visual + protocol seams that only materialise against a real bracket
walkthrough + a real Discord gateway.

### A. [PENDING] Full single-elim 8-clan run end-to-end (SC-1)

1. As an admin in Filament, open `/admin/tournaments` → `Create tournament`.
2. Fill name + slug + `format=single_elimination` + `is_public=true` + `default_game_match_type_id=<Scrim 50v50>`.
3. Open the Participants tab and register 8 clans via the `Add participant` HeaderAction (click 8 times, picking 8 distinct clans).
4. Open the Actions menu → `Seed participants` (strategy: `by_rank`).
5. Open the Actions menu → `Generate brackets`.
6. Verify in Filament:
   - [ ] 7 brackets exist (round 1: 4 brackets, round 2: 2 brackets, round 3 [final]: 1 bracket)
   - [ ] Each round-1 bracket has `participant_a_id` + `participant_b_id` populated per the inner_outer ordering [1,8,4,5,2,7,3,6]
   - [ ] Round-1 GameMatch rows have been auto-materialised (visible in `/admin/matches` filtered on the new tournament)
7. As admin, walk the 4 round-1 matches to completion by entering MatchResult rows (winner per match).
8. Verify after each result entry:
   - [ ] The next round bracket pulls the winner via `advances_to_bracket_id` (D-06-08-C `from.position % 2 === 1 ? 'a' : 'b'` rule)
   - [ ] A new round-2 GameMatch materialises when both `participant_a_id` + `participant_b_id` are populated
9. Walk the 2 round-2 + 1 round-3 matches to completion in the same way.
10. Visit `/tournaments/<slug>` as a public visitor:
    - [ ] Overview tab renders tournament metadata (name, format, status)
    - [ ] Bracket tab shows the full single-elim bracket SVG with all 7 brackets and the final winner
    - [ ] Standings tab shows rank 1..8 (winner first, runner-up second, semi-finalists tied at 3-4, quarter-finalists tied at 5-8)
    - [ ] `/tournaments/<slug>.json` returns 200 + valid ETag + matching body shape

### B. [PENDING] Swiss 6-round dry run with Buchholz tiebreaks visible (SC-2)

1. As an admin in Filament, create a Swiss tournament with 32 participants (or 64 for 6 rounds — VALIDATION.md mentions both: ceil(log2(32))=5; ceil(log2(64))=6 — operator picks N=64 if 6 rounds is the verification target).
2. Generate brackets — round 1 emits N/2 = 32 brackets (for N=64).
3. Walk round 1 to completion (record 32 MatchResult rows).
4. Verify:
   - [ ] `Generate next swiss round` HeaderAction is visible on the tournament page (D-06-07-B / D-06-11-C)
   - [ ] Clicking it generates round-2 pairings respecting Buchholz tiebreak (no participant paired with someone they've already played)
5. Walk rounds 2..6 to completion, generating each next round via the HeaderAction.
6. Visit `/tournaments/<slug>/standings`:
   - [ ] Standings show points + Buchholz column (plain Buchholz = sum of opponents' final scores per D-06-09-H)
   - [ ] Tied participants are ordered by Buchholz desc; ties further broken by `(stage_id, participant_id)` deterministic order

### C. [PENDING] Bracket SVG rendering at 4 / 7 / 8 / 16 participants (SC-3)

1. Create 4 tournaments with `format=single_elimination` and participant counts of 4, 7 (one bye), 8, and 16 respectively. Seed + generate brackets for all 4.
2. Visit `/tournaments/<slug>` Bracket tab for each:
   - [ ] N=4 — 3 brackets render in 2 rounds; layout is readable
   - [ ] N=7 — 7 brackets render (one round-1 bye visible as a "Bye" placeholder); D-06-10-C 4-state ladder correctly classifies the bye
   - [ ] N=8 — 7 brackets render in 3 rounds; canonical inner_outer ordering preserved
   - [ ] N=16 — 15 brackets render in 4 rounds; D-06-12-B `stageYOffset` accumulator keeps the SVG within readable viewport bounds
3. Toggle browser dev tools → verify the polling hook (`useTournamentPolling.ts`) refetches `/tournaments/<slug>.json` every 30s and returns 304 on unchanged state.

### D. [PENDING] Bot announce on bracket creation (live Discord smoke) (SC-3 plumbing + SC-4 chain)

1. Configure the Discord bot with valid `DISCORD_BOT_TOKEN` and ensure the `bot` service is running.
2. Create + start a tournament with `is_public=true` and a host clan that has `discord_announce_channel_id` populated.
3. Verify within ~10s in the configured Discord channel:
   - [ ] An embed appears announcing the tournament (`tournament_announce` kind — D-06-13-A)
4. Edit the tournament's status from `seeded` → `running` (via Filament HeaderAction):
   - [ ] A second embed update arrives (`tournament_announce_update` kind)
5. Finish a bracket match by entering a MatchResult:
   - [ ] A third embed arrives announcing the bracket result (`bracket_result_announce` kind — channel is the host clan's announce channel)
6. In Filament `/admin/discord-outbound-messages`, verify:
   - [ ] All 3 rows exist with `status=sent`, `sent_message_id` populated
   - [ ] activity_log shows the pending → dispatching → sent state transitions (D-012 / Phase 5 D-05-12-C continuation)

### Operator outcome line

| Check | Result | Notes |
|-------|--------|-------|
| A. Single-elim 8-clan run | _PENDING_ | _(operator fills after smoke)_ |
| B. Swiss 6-round Buchholz | _PENDING_ | _(operator fills after smoke)_ |
| C. Bracket SVG fidelity (4/7/8/16) | _PENDING_ | _(operator fills after smoke)_ |
| D. Bot announce on bracket creation | _PENDING_ | _(operator fills after smoke)_ |

**Phase 6 status (post-smoke):** _(operator marks COMPLETE or BLOCKED-ON-FIX)_

---

## Performance Metrics (Phase 6 plan timings)

| Plan | Duration | Tasks | Commits | Files |
|------|----------|-------|---------|-------|
| 06-01 (Wave 0 scaffolding) | 292s | 2 | - | 38 |
| 06-02 (5 migrations + invariants) | 246s | 2 | - | 5 |
| 06-03 (5 models + factories + observer stub) | 1800s | 2 | - | 16 |
| 06-04 (TournamentStatusService + 2 exceptions) | 180s | 1 | - | 3 |
| 06-05 (TournamentSeedingService + A4 LOCKED) | 180s | 1 | - | 4 |
| 06-06 (BracketGeneratorService + SingleElim + Materialiser) | 720s | 2 | - | 10 |
| 06-07 (DoubleElim + RoundRobin + Swiss generators) | 540s | 3 | - | 9 |
| 06-08 (BracketAdvancementService + MatchResultObserver) | 900s | 2 | - | 10 |
| 06-09 (StandingsCalculatorService real impl) | 420s | 2 | - | 7 |
| 06-10 (8 DTOs + ParticipantSummary + TournamentObserver real) | 960s | 2 | - | 21 |
| 06-11 (TournamentResource + 8 HeaderActions + 4 RelationManagers) | 1200s | 2 | - | 16 |
| 06-12 (Public controllers + Vue 5-tab + BracketCanvas + SC-1 capstone) | 2400s | - | - | - |
| 06-13 (i18n + audit log + bot embed extensions + Pitfall 10 mitigation) | 420s | 2 | - | 6 |
| 06-14 (phase close — THIS PLAN) | _captured by orchestrator_ | 2 | 2 | 5 |
| **Phase 6 total** | **~170 min (~10258s)** | **27** | **26+** | **150** |

---

## Open Items Carrying Forward to Phase 7+

| Item | Tracked by | Lives in |
|------|------------|----------|
| ELO-based `by_rank` seeding (replacing `created_at desc` proxy) | RESEARCH Assumption A11 + D-06-05-B | Phase 9 polish |
| Stage-level `game_match_type_id` override (per-bracket match-type) | RESEARCH Assumption A9 | Phase 9 polish |
| Median Buchholz variant + ESL-style cumulative tiebreaker for Swiss | RESEARCH Assumption A3 + D-06-09-H | Phase 9 polish |
| Separate `tournament.manage` permission for non-admin tournament organisers | RESEARCH Open Question A8 + D-06-11-A | v2 (NOTF-class scope) |
| Auto-trigger Swiss next-round generation (currently admin-click — A6 LOCKED) | RESEARCH Open Question A6 + D-06-07-B / D-06-11-C | Phase 9 polish |
| WebSocket-driven live tournament updates (currently 30s polling) | TOUR-V2-01 in REQUIREMENTS.md v2 | v2 deferred |
| Standings included in ETag input (currently only tournament + bracket updated_at) | D-06-10-B | Phase 9 polish |

---

## Out-of-Scope Items Deferred to Future Phases

| Out-of-scope item | Lives in | Reason |
|-------------------|----------|--------|
| CMS article publish announcements to Discord | **Phase 7** (CMS) | Phase 7 will extend the `discord_outbound_messages.message_type` CHECK with `article_announce` using the same drop+add pattern as plans 06-08 + 06-10 |
| RCON match-result → Discord announce | **Phase 8** (RCON automation) | Phase 8's RCON-driven MatchResult create will fire `bracket_result_announce` (when bracket-linked) or a new `match_result_announce` outbound row through the same observer chain |
| Browser tests (Playwright/Dusk) on the 4 manual smokes A–D | **Phase 9** (Polish) — deferred from Phase 1 | P1 explicitly deferred browser tests (CLAUDE.md §4); operator smoke checklist in this report covers the gap until Phase 9 |
| Tournament invite-only / private bracket viewing | **Phase 9** (Polish) | Public is the default round-1 surface; private mode would need a new ViewerCapability gate |
| Per-tournament-stage match-type override | **Phase 9** (Polish) | RESEARCH Assumption A9 LOCKED for v1 — single `default_game_match_type_id` per tournament |

---

## Files Created / Modified Summary

Phase 6 spans 26+ commits across 14 plans (plan 06-14 adds 2 final
commits — the verification commit + the SUMMARY metadata commit). Per-plan
commits are documented in each plan's SUMMARY.md.

The most consequential cross-cutting deviations are codified in the
D-06-NN-* table above; per-plan inline fixes are documented in each plan's
SUMMARY.

Cross-cutting notes:
- D-04-03-A LOCKED canonical class binding (`App\Models\GameMatch`) inherited from Phase 4 and re-affirmed across every Phase 6 plan that touched the matches surface; zero `App\Models\Match as MatchModel` alias-on-import anywhere.
- Tournament i18n namespace pre-shipped in full at plan 06-01 (D-06-01-C) rather than incrementally per-plan — avoids NoHardcodedStringsTest + MissingTranslationException mid-execution.
- All 5 Phase 6 models use `Spatie\Activitylog\Models\Concerns\LogsActivity` (canonical v5 path) — D-06-03-B.
- `discord_outbound_messages.message_type` CHECK extended TWICE in Phase 6 (plan 06-08 adds `bracket_result_announce`; plan 06-10 adds `tournament_announce` + `tournament_announce_update`) using the canonical Postgres drop+recreate idiom established in Phase 5 plan 05-02.

### Threat register dispositions (T-06-XX-NN)

All `mitigate` dispositions across plans 06-01..06-13 are resolved per
their plan SUMMARYs; the single `accept` disposition (T-06-14-03 — manual
smoke items A-D never get verified) is intentional and captured by the
PENDING_MANUAL_SMOKE status flag in this document's frontmatter.

---

## Plan-14 specifics

This plan's task list compressed all close work into two tasks:

1. **Task 1**: Run all 7 quality gates + collect counts (Pest 866/2719 + Vitest 139 + Pint 435 clean + PHPStan [OK] + bot tsc clean + shared-types tsc clean + vue-tsc clean).
2. **Task 2**: Author this `06-PHASE-VERIFICATION.md`; update `ROADMAP.md` (Phase 6 14/14 Complete + completion date 2026-05-14 + replace any pasted Phase 2 placeholder plan rows); update `REQUIREMENTS.md` (REQ-success-tournament-end-to-end Pending → Complete); update `STATE.md` (completed_phases 5 → 6, completed_plans 81 → 82 if not already incremented by per-plan state advances, percent 56 → 67, Accumulated Decisions appended with 53 D-06-* canonical bindings).

No Rule 1/2/3 deviations encountered during this close plan's execution;
the verification artifact reflects observed reality, not a target shape.

---

## Sign-off

Phase 6 verified complete pending operator manual smokes; ROADMAP.md +
REQUIREMENTS.md + STATE.md updated; ready for Phase 7 (CMS).

**Phase 7 hand-off note:** Phase 6 provides the complete tournaments +
brackets surface that Phase 7 (CMS) will reference as a first-class event
type on the public calendar + as a content surface for editorial coverage:

- `App\Models\Tournament` (with `LogsActivity` + JSONB `title` + `is_public` + `published_at` + `default_game_match_type_id` FK chain) ready as a polymorphic Event subject
- `PublicTournamentData` DTO (with node/edge bracket composition + ETag input fields) ready for Phase 7 calendar tiles + article cross-references
- `discord_outbound_messages.message_type` CHECK supports 3 new tournament-related kinds — Phase 7's `article_announce` extension follows the same drop+add migration pattern (D-06-10-F)
- TournamentResource Filament panel ships 4 RelationManagers (Participants, Stages, Brackets, Standings) — Phase 7 CMS resources can be added as siblings without touching the tournaments resource

**Reviewed by:** Claude Opus 4.7 (1M context) — automated verification executor
**Date:** 2026-05-14
