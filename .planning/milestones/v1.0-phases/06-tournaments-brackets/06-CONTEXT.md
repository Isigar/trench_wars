---
phase: 6
phase_name: Tournaments & brackets
gathered: 2026-05-13
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 6: Tournaments & brackets — Context

<domain>
## Phase Boundary

Deliver tournaments as a first-class round-1 capability — formats, bracket generation, public bracket views, standings, and admin tooling for forfeits/withdrawals.

**Success Criteria** (from ROADMAP):
1. Admin creates a tournament, registers 8 clans as participants, seeds them, generates a single-elim bracket end-to-end (no manual SQL).
2. Same workflow available for round-robin, double-elim, and swiss formats (4 total formats per D-011).
3. Public visitor opens `/tournaments/{slug}` and switches between Overview / Bracket / Schedule / Standings / Participants tabs; bracket renders in custom Vue + SVG with 30s live polling.
4. Bracket match finish records `winner_participant_id`; next bracket pulls participants via `advances_to_bracket_id` automatically; standings recompute with format-appropriate tiebreakers.
5. Admin can reseed (stage with no matches played), forfeit, withdraw a participant, recalculate standings via Filament actions — all audited.

**Depends on**: Phase 5 (Discord announce), Phase 4 (GameMatch), Phase 3 (GameMatchType)

**Requirements**: REQ-success-tournament-end-to-end

</domain>

<decisions>
## Implementation Decisions

### Locked Decisions Relevant to Phase 6
- **D-011** Tournaments first-class in round 1 — 4 formats (single-elim, double-elim, round-robin, swiss).
- **D-007** Generic game model — TournamentFormat is generic; HLL is just one game.
- **D-012** Filament covers every domain entity — TournamentResource + ParticipantResource + BracketResource.
- **D-013** Translatable name/description on Tournament (JSONB).
- **D-018** Player privacy via PlayerPrivacyGate where standings show MVPs.

### Claude's Discretion
- Single-elim bracket math (bye placement when not power-of-2 participants).
- Swiss pairing algorithm (recommend: Buchholz tiebreak; round count = ceil(log2(N))).
- Double-elim: losers bracket structure (Burton variant standard).
- SVG bracket renderer: server-side data + client-side SVG (Vue+computed layout).
- Live polling: tournament/{slug}.json endpoint polling every 30s vs Inertia partial reloads.

### Conventions Inherited
- Pest 4 (web), Vitest (bot — minimal use here).
- Pint + PHPStan L8 + tsc strict + vue-tsc.
- LogsActivity on all 5+ new models (Tournament, TournamentParticipant, TournamentStage, TournamentBracket, TournamentStanding).
- spatie/laravel-data + typescript-generate.
- Bot integration: tournament announce + result announce via discord_outbound_messages (Phase 5 infrastructure).

### Naming Binding (D-04-03-A LOCKED — propagated)
- `App\Models\GameMatch` (NOT `Match`) — PHP 8.4 parse error.
- Use `GameMatch` everywhere in tournament-bracket-match links.

</decisions>

<code_context>
## Existing Code Insights

To be gathered by researcher. Relevant prior work:
- Phase 3: Game/GameMatchType primitives (Tournament has format-aware GameMatchType).
- Phase 4: GameMatch (bracket nodes own GameMatch references).
- Phase 4: events polymorphic table (Tournament will live on calendar).
- Phase 5: discord_outbound_messages (announce on bracket creation + match result).

</code_context>

<specifics>
## Specific Ideas

**Tables (likely):**
- `tournaments` — id, slug, game_id, format (enum), title (JSONB), description (JSONB), status (draft/open/registering/seeded/running/completed/cancelled), starts_at, ends_at, max_participants, settings (JSONB for format-specific config), timestamps
- `tournament_participants` — id, tournament_id, clan_id, seed (int nullable), status (registered/active/withdrawn/disqualified), placement (final rank nullable), timestamps; unique (tournament_id, clan_id)
- `tournament_stages` — id, tournament_id, type (group/elim/swiss-round), ordinal, name, settings JSONB, timestamps
- `tournament_brackets` — id, stage_id, round_number, position, participant_a_id (nullable), participant_b_id (nullable), match_id (nullable fk to matches), winner_participant_id (nullable), advances_to_bracket_id (nullable self-fk), timestamps
- `tournament_standings` — id, tournament_id, stage_id, participant_id, wins, losses, draws, points, tiebreak_score, rank (int), timestamps; unique (stage_id, participant_id)
- Optional: `tournament_swiss_rounds` if swiss needs round-specific scheduling

**Services:**
- `TournamentSeedingService` — seed by rank/random/manual.
- `BracketGeneratorService` — strategy per format (SingleElim, DoubleElim, RoundRobin, Swiss).
- `BracketAdvancementService` — on GameMatch winner declared via Phase 4 MatchResultService, propagate winner_participant_id + advance.
- `StandingsCalculatorService` — format-appropriate tiebreakers (head-to-head, Buchholz, etc).

**Routes:**
- `/tournaments` index, `/tournaments/{slug}` detail (5-tab Vue page), `/tournaments/{slug}.json` polling endpoint.
- Admin filament resources for Tournament + Participants + Brackets.

**Filament:**
- TournamentResource with Stage RelationManager and Standings table; bulk actions for seed/reseed/forfeit/withdraw.

</specifics>

<deferred>
## Deferred Ideas

- Spectator chat / live commentary feed (Phase 7+).
- Per-tournament prize pool tracking (Phase 8/9).
- Skill-based seeding using historical ELO (Phase 8).
- Cross-tournament leaderboard across seasons (Phase 9).
- RCON-driven autobracket-advance via score (Phase 8).

</deferred>
