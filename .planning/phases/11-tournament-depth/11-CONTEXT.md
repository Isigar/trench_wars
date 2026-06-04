# Phase 11: Tournament depth - Context

**Gathered:** 2026-06-04
**Status:** Ready for planning
**Mode:** Orchestrator-authored (skip_discuss); ELO model confirmed by user = Clan Elo. Extends the Phase 6 tournament engine.

<domain>
## Phase Boundary

**Goal:** Swiss tournaments self-advance, standings use the correct tiebreaker, seeding uses ELO-derived ratings, and stage configuration is more flexible.

Requirements / SCs:
- **TOUR-01** — When the final match of a Swiss round has its result recorded, the next round generates automatically and the tournament page reflects it, with NO admin action.
- **TOUR-02** — Admin can choose "by rank" seeding; the bracket seeds by ELO-derived rating rather than signup order.
- **TOUR-03** — Swiss standings show median Buchholz alongside plain Buchholz; the tiebreaker is visible on the public bracket view.
- **TOUR-04** — Admin can set a different `GameMatchType` on an individual tournament stage (overriding the tournament default); matches in that stage are created with the overridden type.

In scope: extend existing Phase-6 services. OUT of scope: per-player skill Elo (deferred); changing bracket-generation algorithms; WebSocket live updates (TOUR-V2-01, v2).
</domain>

<decisions>
## Implementation Decisions (pre-resolved)

### TOUR-02 — Clan Elo (user-confirmed)
1. **Schema:** add `clans.elo_rating` (integer, NOT NULL, default 1500). Optionally `clans.elo_matches_count` (integer default 0) for provisional-rating display. Fillable + cast on Clan model.
2. **EloRatingService** (new, app/Services/): standard Elo, K=32, base 1500. `applyResult(Clan $winner, Clan $loser, bool $draw = false): void` — computes expected scores `E = 1/(1+10^((Rb-Ra)/400))`, updates both within a DB::transaction (winner score 1 / loser 0, or 0.5/0.5 on draw), rounds to int. Idempotent guard so the same bracket result isn't applied twice (see hook).
3. **Hook point:** the two clans in a competitive match are known only for TOURNAMENT bracket matches (v1 GameMatch has `host_clan_id` only, no `away_clan_id` — D-09-04-F). So Elo updates fire from the bracket-resolution path: extend `BracketAdvancementService` (Phase 6) — when a bracket resolves with a `winner_participant_id`, resolve participant_a/participant_b → their clans and call `EloRatingService::applyResult`. Idempotency: only apply when the bracket transitions to resolved (guard on a not-yet-rated marker, e.g. a `rated_at` column on tournament_brackets, or check the bracket wasn't already counted). Scrims do NOT affect Elo in v1 (no away clan recorded).
4. **Seeding:** extend `TournamentSeedingService` `by_rank` strategy (currently `created_at` proxy, D-06-05-B) to order participants by their clan's `elo_rating` DESC, tiebreak `created_at` ASC for determinism. With no Elo history all clans are 1500 → by_rank deterministically degrades to the current created_at behavior (no regression). Keep the existing `reseed`/`canReseed` semantics (D-06-05-A).

### TOUR-01 — Swiss auto-advance
5. The Swiss next-round generator already exists (`generateNextRound`, currently admin-click via a Filament HeaderAction — D-06-11-C). Add automatic triggering: when a tournament bracket result is recorded (the same BracketAdvancementService path), detect whether the current Swiss round/stage is fully resolved (every bracket in the round has a winner) and, if so, call `generateNextRound` automatically. Idempotent (don't regenerate an already-generated round; guard on existing next-round brackets). Only for Swiss stages (`stage_type='swiss'`). The TournamentObserver outbound announce (tournament_announce_update) should fire so the public page reflects the new round. Keep the admin HeaderAction as a manual fallback (e.g. visible only when auto-advance hasn't fired / for recovery).

### TOUR-03 — Median Buchholz
6. Extend `StandingsCalculatorService` (D-06-09-H ships plain Buchholz only). Add median Buchholz = sum of each opponent's score EXCLUDING the single highest and single lowest opponent score (standard median-Buchholz / "Buchholz Cut 1" definition — for <3 opponents it equals plain Buchholz). Store on `tournament_standings` (add `median_buchholz` numeric column) alongside the existing buchholz. Apply it in the wipe-and-recompute pass (D-06-09-B). Surface it: `PublicTournamentData` / standings DTO carries it; the Vue standings/bracket view (Phase 6 plan 06-12 SVG renderer / standings table) shows a Median-Buchholz column. Tiebreak order: points → Buchholz → median Buchholz (FIFA points scheme stays, D-06-09-F).

### TOUR-04 — Stage GameMatchType override
7. Add nullable `tournament_stages.game_match_type_id` (FK → game_match_types, nullOnDelete). When `BracketMatchMaterialiserService` materializes a bracket → GameMatch, use `stage.game_match_type_id ?? tournament.default_game_match_type_id` (the materialiser currently uses the tournament default — D-06-06-E throws when null). Admin sets it via Filament (TournamentStage resource/relation) — a Select scoped to the tournament's game (cross-game guard, mirror Phase 3 Pattern 3). i18n for the field label.
</decisions>

<code_context>
## Existing Code Insights (extend, don't rewrite)
- `app/Services/BracketAdvancementService.php` — THE hook point for TOUR-01 (auto-advance) + TOUR-02 (Elo). It already runs when bracket results propagate; resolves participant→clan via TournamentBracket + TournamentParticipant.
- `app/Services/Brackets/` (SwissGenerator etc.) — `generateNextRound` for Swiss (D-06-11-C). Round-complete detection.
- `app/Services/StandingsCalculatorService.php` — wipe-and-recompute (D-06-09-B); plain Buchholz (D-06-09-H); FIFA points (D-06-09-F). Add median Buchholz here.
- `app/Services/TournamentSeedingService.php` — `by_rank` currently created_at proxy (D-06-05-B); reseed/canReseed (D-06-05-A). Point it at clan elo_rating.
- `app/Services/BracketMatchMaterialiserService.php` — materializes bracket→GameMatch; tournament default match type (D-06-06-E). Add stage override.
- `app/Models/{Tournament,TournamentBracket,TournamentParticipant,TournamentStage,TournamentStanding,Clan}.php` — TournamentParticipant→Clan; bracket participant_a_id/participant_b_id/winner_participant_id.
- DTOs: `PublicTournamentData`, BracketNodeData/standings (Phase 6 plan 06-10/06-12) — add median_buchholz; regenerate shared-types.
- Filament: TournamentResource + stage/standings relation managers (Phase 6 plan 06-11).
- Migrations: Phase 2 partial-unique idiom (raw DB::statement) if any conditional constraint is needed; standard FK migrations otherwise. The doutmsg CHECK extension pattern for any new outbound kind (not expected here).

## Verification (Docker available — run real gates)
- `make pest` (new + full), `make pint`, `make phpstan`, `make artisan ARGS="migrate:fresh --seed"`, `(cd apps/web && node_modules/.bin/vue-tsc --noEmit)`, shared-types regen. Bot suite should remain regressionless (no bot changes expected this phase).
- Elo math + median-Buchholz + auto-advance round-detection are pure-logic → strong unit-test candidates (TDD).
</code_context>

<specifics>
## Specific Ideas
- Idempotency is the recurring risk: Elo must apply exactly once per bracket result; auto-advance must generate each Swiss round exactly once. Use explicit guards (a `rated_at`/`advanced` marker or an existence check), and test the double-fire case.
- by_rank with all-equal Elo must produce the SAME order as the current created_at proxy (no regression to existing Phase 6 tests).
- Median Buchholz for a player/clan with <3 opponents == plain Buchholz (test the small-N edge).
</specifics>

<deferred>
## Deferred Ideas
- Per-player skill Elo (SC says "player ratings"; v1.1 ships clan-level — documented scoping decision).
- Elo from non-tournament scrims (needs an away_clan_id on GameMatch — v2).
- WebSocket live round updates (TOUR-V2-01).
- Configurable K-factor / provisional rating periods (v1.1 uses fixed K=32, base 1500).
</deferred>
