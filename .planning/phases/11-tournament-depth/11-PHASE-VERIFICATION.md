---
phase: 11-tournament-depth
verified: 2026-06-04T00:00:00Z
status: human_needed
score: 4/4 must-haves verified
overrides_applied: 0
human_verification:
  - test: "Visit a live Swiss tournament's public standings page at /tournaments/{slug}. Confirm the Median Buchholz column appears when format=swiss. Confirm it is absent on non-Swiss standings views (e.g., round-robin or single-elimination tournament)."
    expected: "Swiss standings table shows a 'Median Buchholz' column alongside 'Buchholz'; non-Swiss standings show no such column."
    why_human: "showMedianBuchholz computed = format === 'swiss' gates a v-if column. vue-tsc PASS proves type-safety but not browser render. No screenshot or Playwright test covers this."
  - test: "In the Filament admin panel, open a tournament, navigate to the Stages tab, click Edit on a stage, and inspect the 'Game Match Type Override' Select dropdown. Confirm only match types belonging to the tournament's own game appear. Confirm match types from other games are absent."
    expected: "The Select shows only match types scoped to the tournament's game (Pattern 3 cross-game guard). Types from other games do not appear in the dropdown."
    why_human: "StagesRelationManagerOverrideTest probes the options closure directly (unit approach) rather than through the full Livewire form render. Pixel rendering of the scoped Select in a browser session has not been observed."
---

# Phase 11: Tournament Depth — Verification Report

**Phase Goal:** Swiss tournaments self-advance, standings use the correct tiebreaker,
seeding uses ELO-derived ratings, stage config is more flexible.
**Verified:** 2026-06-04
**Status:** human_needed (all logic VERIFIED; 2 pixel-render items need browser walkthrough)
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SC-1: Swiss auto-advance fires from bracket-result path (no admin action); premature-completion guard prevents completing after a non-final round | VERIFIED | `MatchResultObserver::created()` calls `BracketAdvancementService::advance()` unconditionally on insert. Step 5b of `advance()` detects `stage->type === 'swiss-round'`, checks all brackets decided, then calls `SwissGenerator::generateNextRound()` guarded by exhaustion (currentRound < totalRounds) and existence (nextRoundExists) checks. `allBracketsComplete()` has an explicit Swiss guard: if any swiss-round stage has match_id=null brackets (unmaterialised next round), returns false. All 4 SwissAutoAdvanceTest cases green: auto-generate, exhaustion guard, premature-completion guard, idempotency. |
| 2 | SC-2: by_rank seeds by clan elo_rating DESC; all-1500 case reproduces pre-Phase-11 order; Elo applied exactly once per bracket (rated_at guard); byes skipped | VERIFIED | `TournamentSeedingService::orderByRank()` sorts by `$ratingB <=> $ratingA` (DESC) with created_at DESC tiebreak (D-11-03-A). `EloRatingService::applyResult()` uses K=32, DB::transaction lockForUpdate. `BracketAdvancementService` step 5a: `if ($loserParticipant !== null && $bracket->rated_at === null)` — bye skip + rated_at idempotency guard. All 2 new TournamentSeedingServiceTest cases + 3 EloAdvancementHookTest cases + 4 EloRatingServiceTest cases green. |
| 3 | SC-3: Median Buchholz computed (Buchholz Cut-1, <3-opponent edge = plain); surfaced on public standings view (typed field, not as-any); column present | VERIFIED (logic+types); PENDING pixel render | `SwissStandingsCalculator` third pass: `if (count($opponentScores) >= 3)` drops lowest + highest via `array_shift` + `array_pop`, else passes through unchanged. Writes `median_buchholz` to `TournamentStanding::create`. `StandingsTable.vue` line 30: `const showMedianBuchholz = computed<boolean>(() => props.format === 'swiss')`. Line 67: `<th v-if="showMedianBuchholz">{{ t('tournaments.standings.tiebreak_median_buchholz') }}</th>`. Line 83: `<td v-if="showMedianBuchholz">{{ row.median_buchholz }}</td>`. `row.median_buchholz` typed as `number` from `TournamentStandingData` in api.d.ts line 374 — not `as any`. i18n key `tiebreak_median_buchholz` present in lang/en/tournaments.php line 209. 2 SwissMedianBuchholzTest cases green. |
| 4 | SC-4: materialiser uses stage override ?? tournament default; null-default-null-override throws RuntimeException; Filament Select is cross-game scoped | VERIFIED (logic+wiring); PENDING pixel render | `BracketMatchMaterialiserService::materialiseFor()` lines 145-153: `$stageOverrideId = ($stage !== null) ? $stage->game_match_type_id : null; $effectiveMatchTypeId = $stageOverrideId ?? $t->default_game_match_type_id; if ($effectiveMatchTypeId === null) { throw new RuntimeException(...'stage has no override'...); }`. `StagesRelationManager` Select options closure: `$game->matchTypes()->orderBy('key')->get()->mapWithKeys(...)` — scoped to tournament->game. 3 StageMatchTypeOverrideTest + 3 StagesRelationManagerOverrideTest cases green. |

**Score:** 4/4 truths verified (logic and type-safety); 2 pixel-render items need human confirmation.

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Services/BracketAdvancementService.php` | Swiss auto-advance + Elo hook wired in advance() | VERIFIED | Steps 5a (Elo rated_at guard + skip byes) and 5b (Swiss auto-advance with exhaustion + existence guards) present. allBracketsComplete() has Swiss premature-completion guard. |
| `app/Services/EloRatingService.php` | K=32, DB::transaction lockForUpdate, activity log | VERIFIED | All three present. elo_matches_count incremented on both clans. |
| `app/Services/TournamentSeedingService.php` | orderByRank uses elo_rating DESC, created_at DESC tiebreak | VERIFIED | usort comparator: `$ratingB <=> $ratingA` primary, `$b->created_at <=> $a->created_at` tiebreak. Loads clan relation via loadMissing. |
| `app/Services/Standings/SwissStandingsCalculator.php` | Buchholz Cut-1 third pass; writes median_buchholz to TournamentStanding | VERIFIED | Third pass at lines 162-177. `count($opponentScores) >= 3` gate correct. TournamentStanding::create includes median_buchholz field. |
| `app/Services/BracketMatchMaterialiserService.php` | Stage override ?? tournament default; RuntimeException on all-null | VERIFIED | `$stageOverrideId ?? $t->default_game_match_type_id` at line 147. RuntimeException thrown at line 149-153 with message containing 'stage has no override'. |
| `resources/js/components/tournaments/StandingsTable.vue` | showMedianBuchholz computed; v-if column with typed row.median_buchholz | VERIFIED | showMedianBuchholz = format === 'swiss'. Column header and data cell both gated on v-if. row.median_buchholz typed as number (not as any). |
| `packages/shared-types/src/api.d.ts` | TournamentStandingData.median_buchholz: number | VERIFIED | Line 374: `median_buchholz: number,` |
| `app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` | game_match_type_id Select scoped to tournament->game->matchTypes | VERIFIED | Options closure at lines 70-84 uses `$livewire->getOwnerRecord()->game->matchTypes()`. ordinal/type/name remain disabled. |
| `app/Observers/MatchResultObserver.php` | advance() called on created + updated without admin action | VERIFIED | `created()` calls `app(BracketAdvancementService::class)->advance($result)` when winner_clan_id !== null. MatchResult::booted() registers observer. |
| Phase 11 migrations (5 files) | elo_rating, rated_at, median_buchholz, game_match_type_id on stages | VERIFIED | All 4 migration files present: add_elo_rating_to_clans (default 1500), add_rated_at_to_tournament_brackets, add_median_buchholz_to_tournament_standings (decimal 8,2 default 0), add_game_match_type_id_to_tournament_stages (nullable FK). |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| MatchResultObserver::created() | BracketAdvancementService::advance() | app() resolution on winner_clan_id != null | WIRED | Direct call in observer; observer registered via static::observe() in MatchResult::booted() |
| BracketAdvancementService step 5a | EloRatingService::applyResult() | app() resolution, loserParticipant != null AND bracket->rated_at === null | WIRED | rated_at stamped inside same DB::transaction (T-11-03-01) |
| BracketAdvancementService step 5b | SwissGenerator::generateNextRound() | app() resolution, stage->type === 'swiss-round', roundComplete, currentRound < totalRounds, !nextRoundExists | WIRED | All three guards verified in code |
| BracketMatchMaterialiserService | stage->game_match_type_id ?? tournament->default_game_match_type_id | null-coalescing assignment at materialiseFor() line 147 | WIRED | Override path and fallback path both tested |
| StandingsTable.vue | row.median_buchholz (TournamentStandingData) | v-if="showMedianBuchholz" computed (format === 'swiss') | WIRED | Typed field, not as-any; api.d.ts regenerated |

---

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| StandingsTable.vue | row.median_buchholz | SwissStandingsCalculator::compute() writes to TournamentStanding; controller passes standings to Inertia prop | Yes — DB write in TournamentStanding::create(['median_buchholz' => ...]) at calculator line 222 | FLOWING |
| BracketAdvancementService (Elo hook) | $winnerClan->elo_rating / $loserClan->elo_rating | EloRatingService::applyResult() re-fetches under lockForUpdate; updates elo_rating + elo_matches_count | Yes — real DB update on Clan rows | FLOWING |

---

### Behavioral Spot-Checks

Step 7b: SKIPPED — Docker is unavailable in this WSL environment. All logic verified via static analysis and test code inspection. Quality gates (Pest 1365 passed, PHPStan L8, vue-tsc) confirmed in executor PHASE-VERIFICATION.md.

---

### Probe Execution

Step 7c: No probe-*.sh files declared or present for Phase 11. SKIPPED.

---

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|-------------|--------|----------|
| TOUR-01 | 11-01 (scaffold), 11-03 (impl) | Swiss auto-advance on round-complete | SATISFIED | SwissAutoAdvanceTest 4 cases; BracketAdvancementService step 5b wired to MatchResultObserver |
| TOUR-02 | 11-01 (schema), 11-02 (Elo), 11-03 (seeding + hook) | by_rank seeds by Elo; Elo applied once per bracket | SATISFIED | TournamentSeedingServiceTest 2 new cases; EloAdvancementHookTest 3 cases; EloRatingServiceTest 4 cases |
| TOUR-03 | 11-01 (schema+DTO), 11-02 (calculator), 11-05 (public view+types) | Median Buchholz visible on public standings | SATISFIED (logic+types); PENDING pixel render | SwissMedianBuchholzTest 2 cases; StandingsTable.vue v-if column; api.d.ts typed field |
| TOUR-04 | 11-01 (schema), 11-04 (materialiser+Filament) | Per-stage GameMatchType override | SATISFIED (logic+wiring); PENDING pixel render | StageMatchTypeOverrideTest 3 cases; StagesRelationManagerOverrideTest 3 cases |

---

### Anti-Patterns Found

None blocking. Files inspected:
- BracketAdvancementService.php: `channel_id => ''` in DiscordOutboundMessage::create at line 256 — this is an accepted pattern from Phase 6 (resolved at dispatch time by the bot renderer per plan 05-11). Not a Phase 11 introduction.
- No TBD/FIXME/XXX markers found in Phase 11 files.
- No empty return stubs or hardcoded empty arrays in data-path code.

---

### Human Verification Required

#### 1. Median Buchholz column — public Swiss standings page

**Test:** Open a Swiss tournament's public standings page at `/tournaments/{slug}` in a browser.
**Expected:** The standings table shows a "Median Buchholz" column (labelled via `tournaments.standings.tiebreak_median_buchholz`) alongside the "Buchholz" column. On a non-Swiss tournament's standings page (e.g., round-robin or single-elimination), no such column appears.
**Why human:** `showMedianBuchholz = computed(() => props.format === 'swiss')` gates a `v-if` column. `vue-tsc --noEmit` PASS proves type-correctness but not browser rendering. No Playwright or screenshot test covers this column's visibility.

#### 2. Filament StagesRelationManager — cross-game-scoped Select

**Test:** In the Filament admin panel, open a tournament (e.g., one belonging to Game A), navigate to the Stages tab, and click Edit on any stage. Inspect the "Game Match Type Override" Select dropdown.
**Expected:** Only match types belonging to the tournament's own game appear in the Select. Match types from other games are absent. The ordinal, type, and name fields are disabled (read-only) in the modal.
**Why human:** `StagesRelationManagerOverrideTest` probes the options closure directly rather than mounting the Livewire form in a full browser session. The pixel rendering of the scoped Select in a real browser has not been verified.

---

### Gaps Summary

No blocking gaps. All four SC truths are verified at the logic and type-safety level. Two items are deferred to human browser verification because they require pixel-level confirmation that vue-tsc and Livewire unit tests cannot provide. Neither item has a defect in the underlying implementation — both are rendering confirmation requirements only.

---

_Verified: 2026-06-04_
_Verifier: Claude (gsd-verifier) — goal-backward verification_
