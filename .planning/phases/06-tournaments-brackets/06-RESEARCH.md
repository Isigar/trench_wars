# Phase 6: Tournaments & brackets — Research

**Researched:** 2026-05-13
**Domain:** Multi-format tournament platform (single-elim, double-elim, round-robin, swiss) with public bracket viewer + Filament admin actions + Discord outbound integration
**Confidence:** HIGH (stack reuse + standard algorithms); MEDIUM (hand-rolled bracket generators) — see Confidence breakdown

## Summary

Phase 6 lands tournaments as a first-class round-1 capability on top of the Phase 1–5 platform. The work is **schema design (5 tables) + 4 hand-rolled bracket generators + 2 service-layer state machines (Tournament lifecycle + Bracket advancement) + a Vue/SVG public renderer + Filament admin actions + audit integration + Discord outbound bridge**. None of it requires new third-party libraries — every primitive (Filament v3.3.50, spatie/laravel-data 4.22, LogsActivity, MatchSignupService, DiscordOutboundMessage outbox, MatchSlotMaterialiserService, MatchObserver→Event sync) already ships and is proven by 618 passing Pest tests.

The four bracket-generator algorithms are well-trodden academic territory — single-elim seeding ("inner_outer mirror/fold" + bye distribution), round-robin (circle method), swiss (Buchholz tiebreak + ceil(log2(N)) rounds), double-elim (Burton variant with W/L bracket + grand final). We hand-roll them as PHP services because (a) the only mature Node alternative — `brackets-manager.js` `[VERIFIED: Context7 /drarig29/brackets-manager.js]` — would force a cross-process surface we don't need (the Laravel side owns the data; the bot is a thin display layer per D-004) and (b) the algorithms are static enough that a one-time port is cheaper than a permanent vendor integration. `brackets-manager.js` is still the best reference implementation for cross-checking our output.

The public bracket view emits a JSON document (nodes + edges) from a `/tournaments/{slug}.json` polling endpoint every 30s — explicitly chosen over WebSockets per `REQ-non-goals-round-1` (CON-tournament-public-view), but the data shape is structured so a future v2 WebSocket upgrade is a transport swap, not a payload rewrite.

**Primary recommendation:** Ship 5 tables (`tournaments`, `tournament_participants`, `tournament_stages`, `tournament_brackets`, `tournament_standings`) + 1 polymorphic `Event` row per Tournament + reuse the existing `GameMatch` materialiser/observer/Discord outbox chain. Hand-roll the four bracket generators behind a `BracketGeneratorService` strategy interface. Use Filament v3 native `Action::action()` with `requiresConfirmation()` for the seed/reseed/forfeit/withdraw/recalculate admin actions. Keep every domain mutation inside `DB::transaction()` with `lockForUpdate()` on the parent `Tournament` row (mirrors D-04-06 row-lock idiom verbatim).

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

From CONTEXT.md `<decisions>` block (Locked Decisions Relevant to Phase 6):

- **D-011** Tournaments first-class in round 1 — 4 formats (single-elim, double-elim, round-robin, swiss).
- **D-007** Generic game model — TournamentFormat is generic; HLL is just one game (FK `tournaments.game_id` → `games.id`).
- **D-012** Filament covers every domain entity — `TournamentResource` + `ParticipantResource` + `BracketResource` (or RelationManagers under TournamentResource — researcher recommendation: RelationManagers for Participants/Stages/Brackets/Standings).
- **D-013** Translatable name/description on Tournament (JSONB) — same pattern as `clans.name`, `matches.title`.
- **D-018** Player privacy via `PlayerPrivacyGate` where standings show MVPs.

### Naming Binding (LOCKED across Phase 4–5 — propagated)

- **D-04-03-A** — `App\Models\GameMatch` (NOT `App\Models\Match` — `match` is a PHP 8.x reserved keyword for the `match($x) {}` expression). Class name `GameMatch`, table stays `matches` via `protected $table = 'matches'`.
- **D-04-03-B** — Every `BelongsTo<GameMatch, $this>` passes `match_id` as an explicit FK arg (Laravel cannot infer `match_id` from a relation method named `match()` when the related class is `GameMatch`).
- **Phase 6 binding:** bracket-match materialisation MUST import `App\Models\GameMatch` directly; zero alias-on-import; new `tournament_brackets.match_id` FK column references the `matches` table.

### Claude's Discretion

Verbatim from CONTEXT.md `<decisions>` block:

- Single-elim bracket math (bye placement when not power-of-2 participants) — researcher recommendation: standard inner_outer mirror/fold + distribute byes to top seeds (high seed gets the bye).
- Swiss pairing algorithm — researcher recommendation: classic Buchholz tiebreak (sum of opponents' scores); round count = `ceil(log2(N))`.
- Double-elim — researcher recommendation: Burton variant (W-bracket → L-bracket drop chain + grand final with optional reset match). Standard cross-bracket mapping.
- SVG bracket renderer — researcher recommendation: server-side computes `BracketNodeDTO` + `BracketEdgeDTO` arrays; Vue computes layout via simple x/y mapping (round_number × column_width, position × row_height); hand-rolled SVG `<line>` + `<rect>` + `<text>`; no third-party Vue/SVG bracket library.
- Live polling — researcher recommendation: `/tournaments/{slug}.json` endpoint returning the full bracket payload + last-modified timestamp; Vue polls every 30s with `setInterval` + diff-skip if `etag`/`last_modified` unchanged. **Inertia partial reloads NOT recommended** (Inertia's partial reload requires the route's controller to compute Inertia props; a pure JSON endpoint is simpler and bypasses the Inertia overhead).

### Conventions Inherited

Verbatim from CONTEXT.md `<decisions>` block (Conventions Inherited):

- Pest 4 (web), Vitest (bot — minimal use here).
- Pint + PHPStan L8 + tsc strict + vue-tsc.
- LogsActivity on all 5+ new models (`Tournament`, `TournamentParticipant`, `TournamentStage`, `TournamentBracket`, `TournamentStanding`).
- spatie/laravel-data + typescript-generate.
- Bot integration: tournament announce + result announce via `discord_outbound_messages` (Phase 5 infrastructure).

### Deferred Ideas (OUT OF SCOPE)

Verbatim from CONTEXT.md `<deferred>` block:

- Spectator chat / live commentary feed (Phase 7+).
- Per-tournament prize pool tracking (Phase 8/9).
- Skill-based seeding using historical ELO (Phase 8).
- Cross-tournament leaderboard across seasons (Phase 9).
- RCON-driven autobracket-advance via score (Phase 8).
- WebSocket live tournament updates — explicit v2 (REQUIREMENTS.md TOUR-V2-01).
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-success-tournament-end-to-end | An 8-clan single-elim tournament can be created, seeded, brackets generated, matches materialised, played, results captured, advancements computed — without admin patching. | Schema design (§ Standard Stack: 5 tables) covers entity model; `BracketGeneratorService` strategy interface (§ Architecture Patterns) covers all 4 formats including 8-clan single-elim; `BracketAdvancementService` (§ Architecture Patterns) covers result→advancement chain; Filament admin actions (§ Architecture Patterns) cover the no-SQL admin path; reuses Phase 4 `MatchSlotMaterialiserService` for materialising bracket→match. |
</phase_requirements>

---

## Project Constraints (from CLAUDE.md)

Direct extractions from `./CLAUDE.md` that the Phase 6 planner must enforce verbatim. All have the same authority as a locked PROJECT.md decision.

| # | Directive | Phase 6 Impact |
|---|-----------|----------------|
| C1 | Container-only commands (D-021 LOCKED) — all `composer`, `php`, `php artisan`, `pnpm`, `npm`, `node`, `vite` run inside containers via `make ...` or `docker compose exec web ...`. Host PHP is 8.3 with broken intl. | Every Phase 6 plan command MUST be in-container form. No `php artisan migrate` from host. |
| C2 | Stack versions (D-001 LOCKED): Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3.3.50 + spatie/laravel-data 4.22.1. | Phase 6 ships ZERO new vendor packages — every primitive reuses existing stack. |
| C3 | Pint (write) / `make pint ARGS="--test"` (CI gate); PHPStan L8 (`make phpstan`); both CI gates failing → blocks merge. | Every Phase 6 plan's task list ends with a `make pint --test && make phpstan` checkpoint per the established Phase 1–5 idiom. |
| C4 | Pest (NOT PHPUnit syntax) — `it()` / `test()` / `expect()`. Feature in `apps/web/tests/Feature/`; Unit in `apps/web/tests/Unit/`. Wave 0 test scaffolding precedes implementation. | Phase 6 ships ~12 new Pest test classes (one per new service + one per new model + one per new Filament resource action); every plan starts with a Wave 0 RED stub task. |
| C5 | Paths — Laravel app lives in `apps/web/` (D-015 LOCKED); composer stays inside `apps/web/`. | All migrations live in `apps/web/database/migrations/`; all models in `apps/web/app/Models/`; all services in `apps/web/app/Services/`; all DTOs in `apps/web/app/Data/`. |
| C6 | i18n (D-013 LOCKED) — every UI string via `__()` (PHP/Blade) or `t()` (Vue). Hardcoded strings = CI failure (`NoHardcodedStringsTest`). | Phase 6 ships `apps/web/lang/en/tournaments.php` + extends `apps/web/lang/en/admin.php` with `tournament` + `tournament_participant` + `tournament_bracket` + `tournament_standing` namespaces. |
| C7 | Translatable user content uses `spatie/laravel-translatable` JSONB columns keyed by locale. | `tournaments.title` + `tournaments.description` are JSONB; `Tournament::$translatable = ['title','description']`. Vue reads via `getTranslations()` DTO factory pattern (Phase 3 idiom). |
| C8 | Filament covers every domain entity (D-012). Resources land in their owning phase. | Phase 6 ships `TournamentResource` + 4 RelationManagers (Participants, Stages, Brackets, Standings) + audit tabs + bulk actions. |
| C9 | Generic game model (D-007) — Tournament FKs `game_id` → `games.id`; no game-specific hardcoding. | `BracketGeneratorService` strategy is format-keyed (single_elim/double_elim/round_robin/swiss), NOT game-keyed. HLL is one game's preset. |
| C10 | One active ClanMembership per player (D-009). | Bracket-match signups REUSE `MatchSignupService` (Phase 4 plan 04-06) verbatim — D-009 + D-010 row-lock invariants come for free. |
| C11 | Spatie permission guard must match Filament's panel guard (`web`) — `default_guard => 'web'` in `config/permission.php`. | Phase 6 introduces 4 new permissions: `tournament.view`, `tournament.manage` (admin organiser), `tournament.organize` (per-tournament organiser limited scope), `tournament.audit`. All bind to `web` guard. |
| C12 | Activity log writes append-only via `LogsActivity` trait. Filament admin UI never exposes edit/delete on `activity_log` rows. | All 5 new Phase 6 models use `LogsActivity`. Filament `TournamentResource::HeaderActions` Re-seed/Forfeit/Withdraw actions write explicit `activity()->withProperties(...)` rows (D-04-12-A pattern). |
| C13 | LogsActivity does NOT populate `properties.attributes` empirically — `withProperties()` is the ONLY path to populated properties JSON (D-04-12-A). | Phase 6 tests for state transitions MUST read `properties` from explicit `withProperties()` calls; do not assert against attribute_changes (Phase 5 D-05-02-C — that's a separate column). |
| C14 | NO Filament BulkActions on activity_log; Filament EditAction in Phase 4 closed pattern uses `$model->save()` (not query()->update()) so Phase 4 MatchObserver fires. | Phase 6 admin actions MUST use `$model->save()` / fluent updates; never `Tournament::query()->update(...)` (would bypass observers; cf. Phase 4 Pitfall 12). |
| C15 | Phase 4 `App\Models\GameMatch` canonical class binding (D-04-03-A LOCKED) — direct `use App\Models\GameMatch;` import everywhere; zero alias-on-import. | Phase 6 `TournamentBracket::match()` relation passes `match_id` explicitly; `BracketAdvancementService` imports `GameMatch` directly. |

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Tournament lifecycle state machine (draft → registering → seeded → running → completed/cancelled) | Backend (`TournamentStatusService`) | — | Single serialisation point per tournament; mirrors `MatchStatusService` Phase 4 idiom |
| Bracket generation (4 formats) | Backend (`BracketGeneratorService` strategy) | — | Pure computation over participants → bracket DAG; deterministic, easily unit-tested |
| Bracket advancement on GameMatch result | Backend (`BracketAdvancementService` invoked from `MatchResultService` hook OR observer) | — | Triggers off existing `MatchResult` write path; preserves Phase 4 single-source-of-truth |
| Standings calculation (format-specific tiebreakers) | Backend (`StandingsCalculatorService` strategy) | — | Reads matches/brackets, writes denormalised `tournament_standings` rows |
| Public tournament page | Frontend Server (Inertia controller `TournamentShowController`) | Browser (Vue tabbed view) | SSR-renders initial paint per CLAUDE.md frontend goals; client takes over |
| Live polling JSON endpoint | Backend (`TournamentPublicJsonController`) | Browser (Vue setInterval) | Pure JSON — separate from Inertia route to avoid SSR overhead on poll |
| SVG bracket rendering | Browser (Vue `<BracketCanvas>` component with computed layout) | — | Pure client work after data load; hand-rolled SVG primitives |
| Filament admin actions (seed/reseed/forfeit/withdraw/recalculate) | Backend (Filament `Action` + Service) | — | Standard Filament v3 patterns; modal-confirmed; service-backed |
| Tournament Discord announce | Backend (`TournamentObserver` → `discord_outbound_messages` row) | Bot polling worker (Phase 5) | Reuses Phase 5 outbox; Phase 6 only adds `kind=tournament_announce` |
| Polymorphic Event sync (Tournament → calendar) | Backend (`TournamentObserver` extends Phase 4 pattern) | — | Reuses `events` table polymorphic `eventable_*` FKs; `MorphOne<Event, $this>` relation |
| TypeScript type generation | Backend (`spatie/laravel-typescript-transformer` `:generate` Artisan command) | Frontend (`api.d.ts` + `packages/shared-types`) | Existing Phase 1 plan 01-15 pipeline; Phase 6 emits 8+ new DTOs |
| RBAC | Spatie permission (web guard) | Filament policies | Same pattern as Phase 4 MatchResource |

---

## Standard Stack

All packages below are ALREADY installed (composer.json verified 2026-05-13). Phase 6 introduces ZERO new third-party packages.

### Core (no new installs)

| Library | Version (verified) | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^12.0` `[VERIFIED: apps/web/composer.json]` | App framework | D-001 LOCKED |
| `filament/filament` | `v3.3.50` `[VERIFIED: docker compose exec web composer show filament/filament]` | Admin panel (TournamentResource + 4 RelationManagers) | D-012 LOCKED |
| `spatie/laravel-data` | `4.22.1` `[VERIFIED: docker compose exec web composer show spatie/laravel-data]` | DTOs + TS type generation | D-020 LOCKED |
| `spatie/laravel-typescript-transformer` | `^3.0` `[VERIFIED: apps/web/composer.json]` | api.d.ts emission | D-020 LOCKED |
| `spatie/laravel-activitylog` | `^5.0` `[VERIFIED: apps/web/composer.json]` | LogsActivity audit | D-012 LOCKED |
| `spatie/laravel-translatable` | `^6.14` `[VERIFIED: apps/web/composer.json]` | JSONB translatable name/description | D-013 LOCKED |
| `spatie/laravel-permission` | `^7.4` `[VERIFIED: apps/web/composer.json]` | RBAC for tournament organiser role | D-012 LOCKED |
| `inertiajs/inertia-laravel` | `^2.0` `[VERIFIED: apps/web/composer.json]` | Server↔Vue protocol | D-001 LOCKED |
| `pestphp/pest` | `^4.7` `[VERIFIED: apps/web/composer.json]` | Test framework | C4 |
| `larastan/larastan` | `^3.9` `[VERIFIED: apps/web/composer.json]` | PHPStan L8 wrapper | C3 |
| `laravel/pint` | `^1.29` `[VERIFIED: apps/web/composer.json]` | Code style | C3 |

### Supporting (already installed, no Phase 6 install step)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `App\Services\MatchSlotMaterialiserService` | Phase 4 plan 04-05 | Materialise GameMatch slot grid from RoleLimit | Bracket→Match flow: when bracket pairs participants, create `GameMatch` → call this service to spawn slot grid |
| `App\Services\MatchSignupService` | Phase 4 plan 04-06 | Row-locked signup with 5-guard sequence | Bracket-match signups reuse it verbatim — no parallel implementation |
| `App\Services\MatchResultService` | Phase 4 plan 04-09 | Upsert MatchResult + auto-flip status to `played` | Bracket advancement reads `MatchResult.winner_clan_id` after this service writes |
| `App\Services\MatchStatusService` | Phase 4 plan 04-04 | State transitions on GameMatch | Tournament status machine mirrors this pattern |
| `App\Models\DiscordOutboundMessage` | Phase 5 plan 05-02 | Durable outbox row | Extend `message_type` enum with `tournament_announce` + `bracket_result_announce` |
| `App\Observers\MatchObserver` | Phase 4 plan 04-08 + Phase 5 plan 05-05 | Polymorphic Event sync + Discord outbound write | TournamentObserver mirrors the pattern verbatim |
| `App\Models\Event` | Phase 4 plan 04-02 | Polymorphic calendar event | `Tournament::event() MorphOne<Event, $this>` — no new column |
| `App\Data\PublicMatchData` + `MatchData` | Phase 4 plan 04-07 | Public-safe match DTOs with privacy gate integration | Bracket page reuses these for the per-bracket match summary |

### Alternatives Considered (and rejected for Phase 6)

| Instead of (rejected) | Could Use | Tradeoff |
|------------|-----------|----------|
| Hand-rolled PHP `BracketGeneratorService` | `brackets-manager.js` `[VERIFIED: Context7 /drarig29/brackets-manager.js]` (Node lib, 4 formats — single_elim, double_elim, round_robin; swiss via plugin) | Mature reference impl with 80+ code snippets in Context7 docs. **Rejected because** (a) Cross-process surface unnecessary — Laravel owns the data, bot is thin display layer per D-004; (b) Adds an HTTP hop or queue surface for what is pure computation; (c) Storage shape mismatch (it owns its own tables); (d) PHP impl is ~400–600 LOC across 4 generators per published examples. **Use as reference for our PHP impl** — cross-check our output against its test vectors. |
| Vue bracket component package (e.g., `react-tournament-brackets`, `kamilwylegala/vue-tournament-bracket`) | Hand-rolled SVG in `<BracketCanvas>` | Vue tournament-bracket libs are Vue 2 era or React-only `[VERIFIED: WebSearch — kamilwylegala/vue-tournament-bracket is for Vue 2, react-tournament-brackets is React]`. Hand-rolling SVG is ~200 LOC and gives us full control over privacy markers, status color tokens, dark/light theme variables from Phase 1 plan 01-08. |
| `spatie/laravel-model-states` for tournament status | Plain string column + service-layer state machine | spatie/laravel-model-states is mature `[VERIFIED: WebSearch — Filament Model States plugin]` but Phase 4 chose the simpler "string column + CHECK constraint + dedicated service" idiom (D-04-04-A/B). Phase 6 mirrors verbatim — saves cognitive load and consistency with Match/MatchResult patterns. |
| Inertia partial reloads (`Inertia::partial(['tournament.bracket'])`) for polling | Standalone `/tournaments/{slug}.json` endpoint | Partial reloads invoke the Inertia controller which costs full SSR-frame overhead. A pure JSON endpoint costs ~5–10ms vs ~50–80ms for the Inertia route. At 30s poll × N viewers, the difference matters. |
| WebSocket live updates (Pusher/Reverb) | 30s JSON polling | Explicit out-of-scope per REQUIREMENTS.md TOUR-V2-01 + CON-tournament-public-view. |

### Version verification

All packages verified in-container against the installed lockfile on 2026-05-13:

```bash
docker compose exec web composer show filament/filament    # v3.3.50
docker compose exec web composer show spatie/laravel-data  # 4.22.1
```

No new packages installed in Phase 6; reusing the Phase 1–5 stack verbatim.

---

## Architecture Patterns

### System Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          ADMIN (Filament panel)                           │
└──────────────────────┬───────────────────────────────────────────────────┘
                       │ TournamentResource
                       ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  TournamentService → TournamentStatusService                              │
│      ├─ createDraft()                                                     │
│      ├─ openRegistration()  [draft→registering]                           │
│      ├─ seed()              [registering→seeded] (via TournamentSeedingService)│
│      ├─ start()             [seeded→running] (calls BracketGeneratorService)│
│      ├─ complete()          [running→completed]                           │
│      └─ cancel()            [* → cancelled]                               │
└──────────────────────┬───────────────────────────────────────────────────┘
                       │ format strategy dispatch
                       ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  BracketGeneratorService (strategy interface — 4 implementations)         │
│      SingleEliminationGenerator   — inner_outer seed + bye placement      │
│      DoubleEliminationGenerator   — W bracket + L bracket + grand final   │
│      RoundRobinGenerator          — circle method (N-1 rounds, N/2 m/round)│
│      SwissGenerator (round-by-round) — ceil(log2(N)) rounds; Buchholz     │
│      ↓                                                                    │
│  Writes: tournament_stages, tournament_brackets (round_number, position,  │
│   participant_a_id?, participant_b_id?, advances_to_bracket_id?)          │
└──────────────────────┬───────────────────────────────────────────────────┘
                       │ when admin runs "Start tournament" or per swiss round
                       ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  BracketMatchMaterialiserService                                          │
│      For each tournament_bracket without match_id yet:                    │
│        - Create GameMatch (organiser=tournament.created_by, hostClan=null)│
│        - Call existing MatchSlotMaterialiserService (Phase 4)             │
│        - Set tournament_brackets.match_id = match.id                      │
│        - GameMatch status = 'open' (transitions via MatchStatusService)   │
└──────────────────────┬───────────────────────────────────────────────────┘
                       │ players sign up via web or Discord bot
                       ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  Phase 4 chain (reused verbatim):                                         │
│    MatchSignupService (row-locked) → MatchResultService (auto status→played)│
└──────────────────────┬───────────────────────────────────────────────────┘
                       │ result written (winner_clan_id set)
                       ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  BracketAdvancementService                                                │
│      - Resolves winner_participant_id from MatchResult.winner_clan_id     │
│        + tournament_participants.clan_id lookup                           │
│      - Writes tournament_brackets.winner_participant_id                   │
│      - If advances_to_bracket_id is set, propagates winner into next     │
│        bracket's participant_a_id or participant_b_id                     │
│      - Triggers StandingsCalculatorService::recalculate(tournament)       │
│      - If all brackets in stage completed → next stage / tournament done  │
│      - Writes discord_outbound_messages row (kind=bracket_result_announce)│
└──────────────────────┬───────────────────────────────────────────────────┘
                       │
            ┌──────────┴──────────┐
            ▼                     ▼
┌─────────────────────┐   ┌──────────────────────────────────┐
│  StandingsCalculator │   │  TournamentObserver              │
│    SingleElim: rank  │   │    - Polymorphic Event upsert    │
│      from final pos  │   │    - DiscordOutboundMessage write│
│    DoubleElim: same  │   │      (tournament_announce kind)  │
│    RoundRobin: head- │   └──────────────────────────────────┘
│      to-head sort    │
│    Swiss: Buchholz   │
└─────────────────────┘
                       │ writes tournament_standings rows
                       ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                    PUBLIC SURFACE (Vue + Inertia)                         │
│   Route: GET /tournaments/{slug}                                          │
│      → TournamentShowController → Inertia('Tournaments/Show')            │
│      → 5-tab Vue page (Overview / Bracket / Schedule / Standings /        │
│        Participants)                                                      │
│   Route: GET /tournaments/{slug}.json (separate, no Inertia)             │
│      → TournamentPublicJsonController                                     │
│      → returns { tournament, stages, brackets[], standings[], etag }      │
│      → Vue setInterval(30s) → fetch → diff-skip if etag unchanged         │
└──────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| File / class | Responsibility |
|--------------|-----------------|
| `apps/web/database/migrations/2026_05_15_100000_create_tournaments_table.php` | Tournament root entity; FK to games; JSONB title/description; status column with CHECK |
| `apps/web/database/migrations/2026_05_15_100100_create_tournament_participants_table.php` | Tournament↔Clan join with seed, status, placement |
| `apps/web/database/migrations/2026_05_15_100200_create_tournament_stages_table.php` | Stage grouping (group/elim/swiss-round); ordinal; settings JSONB |
| `apps/web/database/migrations/2026_05_15_100300_create_tournament_brackets_table.php` | Bracket node; round/position; nullable participants; advances_to (self-FK); match_id FK to matches |
| `apps/web/database/migrations/2026_05_15_100400_create_tournament_standings_table.php` | Denormalised standings; recomputed by StandingsCalculatorService |
| `apps/web/app/Models/Tournament.php` | LogsActivity + HasTranslations + HasUuidPrimaryKey; `MorphOne<Event, $this>`; `static::observe(TournamentObserver::class)` |
| `apps/web/app/Models/TournamentParticipant.php` | LogsActivity; BelongsTo Tournament, Clan |
| `apps/web/app/Models/TournamentStage.php` | LogsActivity; BelongsTo Tournament; HasMany TournamentBracket |
| `apps/web/app/Models/TournamentBracket.php` | LogsActivity; BelongsTo TournamentStage, TournamentParticipant (a, b, winner), GameMatch; self-FK advances_to |
| `apps/web/app/Models/TournamentStanding.php` | LogsActivity; BelongsTo Tournament, TournamentStage, TournamentParticipant |
| `apps/web/app/Services/TournamentStatusService.php` | State machine (draft/registering/seeded/running/completed/cancelled); mirrors MatchStatusService |
| `apps/web/app/Services/TournamentSeedingService.php` | Seed participants by rank/random/manual; writes `tournament_participants.seed` |
| `apps/web/app/Services/Brackets/BracketGeneratorService.php` (strategy interface) | Front door — dispatches to 4 concrete generators by `tournament.format` |
| `apps/web/app/Services/Brackets/SingleEliminationGenerator.php` | inner_outer seeding + bye distribution; writes brackets w/ advances_to chain |
| `apps/web/app/Services/Brackets/DoubleEliminationGenerator.php` | W-bracket + L-bracket + grand final; loser drop pattern |
| `apps/web/app/Services/Brackets/RoundRobinGenerator.php` | Circle method; (N-1) rounds × (N/2) matches/round; dummy participant for odd N |
| `apps/web/app/Services/Brackets/SwissGenerator.php` | Round-by-round pairing; opponent-points + Buchholz tiebreak; never-paired-before constraint |
| `apps/web/app/Services/BracketAdvancementService.php` | MatchResult → winner_participant_id → advances_to propagation |
| `apps/web/app/Services/BracketMatchMaterialiserService.php` | Bracket pair → GameMatch row + slot materialisation |
| `apps/web/app/Services/StandingsCalculatorService.php` | Format-specific tiebreaker; writes tournament_standings rows |
| `apps/web/app/Observers/TournamentObserver.php` | Event upsert + Discord outbound write (mirror MatchObserver) |
| `apps/web/app/Observers/MatchResultObserver.php` (NEW) | Hook after MatchResult save → BracketAdvancementService if match belongs to a tournament bracket |
| `apps/web/app/Filament/Resources/TournamentResource.php` | Full CRUD + 4 RelationManagers + HeaderActions (start, complete, cancel, recalculate); audit tab |
| `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/ParticipantsRelationManager.php` | Add/forfeit/withdraw participants |
| `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/BracketsRelationManager.php` | Read-only list (view brackets — admin doesn't edit individual brackets) |
| `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` | Stage CRUD |
| `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StandingsRelationManager.php` | Recalculate action + read-only display |
| `apps/web/app/Http/Controllers/TournamentShowController.php` | Public Inertia page (5 tabs) |
| `apps/web/app/Http/Controllers/TournamentPublicJsonController.php` | JSON polling endpoint with etag |
| `apps/web/app/Data/TournamentData.php`, `TournamentParticipantData.php`, `TournamentStageData.php`, `TournamentBracketData.php`, `TournamentStandingData.php`, `PublicTournamentData.php`, `BracketNodeData.php`, `BracketEdgeData.php` | Spatie laravel-data DTOs |
| `apps/web/resources/js/pages/Tournaments/Show.vue` | 5-tab tabbed page (Overview / Bracket / Schedule / Standings / Participants) |
| `apps/web/resources/js/pages/Tournaments/Index.vue` | Tournament directory list |
| `apps/web/resources/js/components/tournaments/BracketCanvas.vue` | SVG renderer (computes layout from BracketNodeData + BracketEdgeData) |
| `apps/web/resources/js/components/tournaments/BracketNode.vue` | Single bracket SVG `<g>` (match card with two participants + score) |
| `apps/web/resources/js/components/tournaments/StandingsTable.vue` | Standings table with format-specific columns (Buchholz column for swiss, etc.) |
| `apps/web/resources/js/composables/useTournamentPolling.ts` | setInterval(30s) + etag/last_modified diff-skip |
| `apps/web/lang/en/tournaments.php` | All i18n keys (D-013 + CLAUDE.md §7) |

### Recommended Project Structure

```
apps/web/
├── app/
│   ├── Data/
│   │   ├── TournamentData.php                # admin DTO (full state)
│   │   ├── PublicTournamentData.php          # public DTO (privacy-filtered)
│   │   ├── TournamentParticipantData.php
│   │   ├── TournamentStageData.php
│   │   ├── TournamentBracketData.php         # full state (for admin)
│   │   ├── TournamentStandingData.php
│   │   ├── BracketNodeData.php               # SVG render-shape (id, round, position, participant_a, participant_b, winner, status)
│   │   └── BracketEdgeData.php               # SVG render-shape (from_bracket_id, to_bracket_id, participant_slot 'a' | 'b')
│   ├── Filament/Resources/
│   │   ├── TournamentResource.php
│   │   ├── TournamentResource/
│   │   │   ├── Pages/
│   │   │   │   ├── ListTournaments.php
│   │   │   │   ├── CreateTournament.php
│   │   │   │   └── EditTournament.php
│   │   │   └── RelationManagers/
│   │   │       ├── ParticipantsRelationManager.php
│   │   │       ├── StagesRelationManager.php
│   │   │       ├── BracketsRelationManager.php
│   │   │       └── StandingsRelationManager.php
│   ├── Http/Controllers/
│   │   ├── TournamentShowController.php       # Inertia
│   │   ├── TournamentIndexController.php      # Inertia (directory)
│   │   └── TournamentPublicJsonController.php # JSON-only polling
│   ├── Models/
│   │   ├── Tournament.php
│   │   ├── TournamentParticipant.php
│   │   ├── TournamentStage.php
│   │   ├── TournamentBracket.php
│   │   └── TournamentStanding.php
│   ├── Observers/
│   │   ├── TournamentObserver.php             # NEW
│   │   └── MatchResultObserver.php            # NEW (or extend MatchResultService)
│   ├── Services/
│   │   ├── TournamentStatusService.php
│   │   ├── TournamentSeedingService.php
│   │   ├── BracketMatchMaterialiserService.php
│   │   ├── BracketAdvancementService.php
│   │   ├── StandingsCalculatorService.php
│   │   └── Brackets/
│   │       ├── BracketGeneratorService.php          # strategy front-door
│   │       ├── BracketGeneratorStrategy.php         # interface
│   │       ├── SingleEliminationGenerator.php
│   │       ├── DoubleEliminationGenerator.php
│   │       ├── RoundRobinGenerator.php
│   │       └── SwissGenerator.php
│   └── Support/
│       └── DiscordOutboundPayloadBuilder.php  # extend with buildTournamentAnnounce()
├── database/
│   ├── factories/
│   │   ├── TournamentFactory.php
│   │   ├── TournamentParticipantFactory.php
│   │   ├── TournamentStageFactory.php
│   │   ├── TournamentBracketFactory.php
│   │   └── TournamentStandingFactory.php
│   └── migrations/
│       ├── 2026_05_15_100000_create_tournaments_table.php
│       ├── 2026_05_15_100100_create_tournament_participants_table.php
│       ├── 2026_05_15_100200_create_tournament_stages_table.php
│       ├── 2026_05_15_100300_create_tournament_brackets_table.php
│       └── 2026_05_15_100400_create_tournament_standings_table.php
├── lang/en/
│   └── tournaments.php                        # NEW
├── resources/js/
│   ├── pages/Tournaments/
│   │   ├── Index.vue
│   │   └── Show.vue
│   ├── components/tournaments/
│   │   ├── BracketCanvas.vue
│   │   ├── BracketNode.vue
│   │   ├── StandingsTable.vue
│   │   ├── ParticipantsList.vue
│   │   └── TournamentScheduleList.vue
│   └── composables/
│       └── useTournamentPolling.ts
└── tests/
    ├── Feature/
    │   ├── Admin/
    │   │   ├── TournamentResourceTest.php
    │   │   ├── TournamentSeedActionTest.php
    │   │   ├── TournamentReseedActionTest.php
    │   │   ├── TournamentForfeitActionTest.php
    │   │   ├── TournamentWithdrawActionTest.php
    │   │   ├── TournamentRecalculateStandingsTest.php
    │   │   └── TournamentAuditLogTest.php
    │   ├── Models/
    │   │   ├── TournamentModelTest.php
    │   │   ├── TournamentParticipantModelTest.php
    │   │   ├── TournamentStageModelTest.php
    │   │   ├── TournamentBracketModelTest.php
    │   │   └── TournamentStandingModelTest.php
    │   ├── Services/
    │   │   ├── TournamentStatusServiceTest.php
    │   │   ├── TournamentSeedingServiceTest.php
    │   │   ├── BracketGeneratorSingleEliminationTest.php
    │   │   ├── BracketGeneratorDoubleEliminationTest.php
    │   │   ├── BracketGeneratorRoundRobinTest.php
    │   │   ├── BracketGeneratorSwissTest.php
    │   │   ├── BracketMatchMaterialiserServiceTest.php
    │   │   ├── BracketAdvancementServiceTest.php
    │   │   └── StandingsCalculatorServiceTest.php
    │   ├── Tournaments/
    │   │   ├── TournamentShowPageTest.php
    │   │   ├── TournamentIndexPageTest.php
    │   │   ├── TournamentPublicJsonControllerTest.php
    │   │   └── TournamentEndToEndTest.php       # SC-1 8-clan single-elim end-to-end
    │   └── Observers/
    │       ├── TournamentObserverTest.php
    │       └── MatchResultObserverTest.php
    └── Unit/
        └── Data/
            ├── TournamentDataTest.php
            ├── PublicTournamentDataTest.php
            ├── BracketNodeDataTest.php
            └── BracketEdgeDataTest.php
```

### Pattern 1: Tournament status state machine

**What:** Strict transitions: `draft → registering → seeded → running → completed`; `cancelled` is reachable from any non-terminal. Mirrors Phase 4 `MatchStatusService` verbatim (D-04-04-A pattern).

**When to use:** Every tournament write that changes `status` MUST flow through `TournamentStatusService::transition($tournament, $to)`. Direct `$tournament->update(['status'=>...])` is forbidden (defended by a `CHECK` constraint at DB layer + grep gate in plan close).

**Example (PHP):**

```php
// Source: app/Services/MatchStatusService.php (Phase 4 plan 04-04) — adapted verbatim
final class TournamentStatusService
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'draft' => ['registering', 'cancelled'],
        'registering' => ['seeded', 'cancelled'],
        'seeded' => ['running', 'registering', 'cancelled'],   // back to registering only if no matches played
        'running' => ['completed', 'cancelled'],
        'completed' => [],   // terminal
        'cancelled' => [],   // terminal
    ];

    public function transition(Tournament $tournament, string $to, ?User $causer = null): Tournament
    {
        $from = $tournament->status;
        if (!in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new TournamentStatusInvalidTransitionException(
                __('tournaments.errors.invalid_transition', ['from' => $from, 'to' => $to])
            );
        }

        return DB::transaction(function () use ($tournament, $from, $to, $causer): Tournament {
            $tournament->update(['status' => $to]);
            activity()
                ->causedBy($causer ?? auth()->user())
                ->performedOn($tournament)
                ->withProperties(['from' => $from, 'to' => $to])  // D-04-12-A pattern
                ->log("Tournament status: {$from} -> {$to}");
            return $tournament;
        });
    }
}
```

### Pattern 2: BracketGeneratorService strategy

**What:** Front-door service dispatches to one of four format-specific generators. Each generator is a pure function: input is `Tournament + ordered Participants` (after seeding), output is `tournament_stages[]` + `tournament_brackets[]` rows written inside a single `DB::transaction`. Idempotency: throws if any bracket has a non-null `match_id` (i.e., advancement already started — reseeding is the appropriate path).

**When to use:** Called once per tournament during `TournamentStatusService::start()` (transitions `seeded → running`). For swiss, the generator is called per round — first round at start; subsequent rounds when an admin clicks "Generate next swiss round" (after all current-round matches have results).

**Example (PHP):**

```php
// Source: hand-rolled; cross-reference brackets-manager.js for output structure
interface BracketGeneratorStrategy
{
    public function generate(Tournament $tournament, Collection $orderedParticipants): void;
}

final class BracketGeneratorService
{
    public function __construct(
        private readonly SingleEliminationGenerator $singleElim,
        private readonly DoubleEliminationGenerator $doubleElim,
        private readonly RoundRobinGenerator $roundRobin,
        private readonly SwissGenerator $swiss,
    ) {}

    public function generate(Tournament $tournament): void
    {
        $participants = $tournament->participants()
            ->where('status', 'active')
            ->orderBy('seed')
            ->get();

        $strategy = match ($tournament->format) {
            'single_elimination' => $this->singleElim,
            'double_elimination' => $this->doubleElim,
            'round_robin' => $this->roundRobin,
            'swiss' => $this->swiss,
        };

        DB::transaction(fn () => $strategy->generate($tournament, $participants));
    }
}
```

### Pattern 3: Single-elim with byes (inner_outer seeding)

**What:** Standard tournament "fold" — Seed 1 vs Seed 2^k (top vs bottom), bracket halves arranged so 1 and 2 meet in final. Byes for non-power-of-2 counts are awarded to top seeds so they advance directly to round 2. `[VERIFIED: Wikipedia + Brackets Ninja docs + brackets-manager.js docs Context7]`

**When to use:** All single-elim and the W-bracket of double-elim. Use `inner_outer` ordering per brackets-manager.js Context7 reference doc.

**Algorithm:**

1. Compute `bracketSize = 2^ceil(log2(N))` and `byeCount = bracketSize - N`.
2. Order participants by seed (already done by upstream `TournamentSeedingService`).
3. Append `byeCount` null sentinels to fill to `bracketSize`.
4. Compute first-round pair ordering using inner_outer: for size = 8, the order is [1,8,4,5,2,7,3,6] — top seeds paired with bottom seeds, halves arranged so 1 & 2 are on opposite halves. For size = 16: [1,16,8,9,4,13,5,12,2,15,7,10,3,14,6,11].
5. For each round `r ∈ [1, ceil(log2(N))]`:
   - For each position `p ∈ [1, bracketSize / 2^r]`:
     - Create a `tournament_brackets` row with `round_number = r`, `position = p`, `participant_a_id`, `participant_b_id` (both nullable — null = bye or TBD).
     - If `r == 1` and `participant_b_id` is null (bye), auto-set `winner_participant_id = participant_a_id`.
     - Link `advances_to_bracket_id` = the bracket at `(round_number = r+1, position = ceil(p/2))`. **Two-pass insert** — insert all rows first with null advances_to, then UPDATE to set advances_to using a position lookup map. Avoids forward-references.

**Inner-outer ordering reference (computed):**

```
size=4:  [1,4,2,3]
size=8:  [1,8,4,5,2,7,3,6]
size=16: [1,16,8,9,4,13,5,12,2,15,7,10,3,14,6,11]
```

`[CITED: brackets-manager.js user-guide/ordering Context7 docs — single_elimination inner_outer]`

### Pattern 4: Round-robin (circle method)

**What:** Every participant plays every other participant exactly once. `(N-1)` rounds × `N/2` matches per round. Odd N adds a dummy participant (= bye round). `[VERIFIED: Wikipedia + Rosetta Code]`

**When to use:** Group stages, league formats, small tournaments where complete fairness matters more than speed.

**Algorithm (N even):**

1. Number participants 0..N-1.
2. Fix participant 0; rotate the other N-1 around it.
3. For round `r ∈ [0, N-2]`:
   - For position `i ∈ [0, N/2 - 1]`:
     - Compute rotated indices: `(i, N-1-i)` for `i=0` means `(fixed=0, rotated[N-2-r])`; otherwise pair `(rotated[(i-1 + r) mod (N-1)], rotated[(N-1-i + r - 1) mod (N-1)])`. Implement via standard "fix index 0, rotate ring" algorithm.

**Algorithm (N odd):** Treat as `N+1` even with one ghost participant; ghost's opponent in each round has the bye.

**Result schema:** Store each round in `tournament_brackets` with `round_number = r+1`, sequential `position`. `advances_to_bracket_id` is NULL for all (round-robin has no advancement chain — all matches are independent). Winner determined per-match; standings computed by `StandingsCalculatorService` reading all match results.

### Pattern 5: Swiss (round-by-round, Buchholz tiebreak)

**What:** Round count = `ceil(log2(N))`. Each round pairs players with similar scores; never re-pairs the same matchup. Tiebreak by Buchholz = sum of opponents' scores. `[VERIFIED: Wikipedia + Brackets Ninja + Striveon docs]`

**Round 1 pairing:** Sort participants by initial seed; pair `(seed[0], seed[N/2])`, `(seed[1], seed[N/2+1])`, ... (top half vs bottom half). For odd N, lowest-seeded gets a bye (1 point).

**Round 2+ pairing:**
1. Sort participants by `(score DESC, buchholz DESC, seed ASC)`.
2. Group by score.
3. Within each score group, pair top half vs bottom half.
4. **Never-paired-before constraint:** if the natural pairing would re-match a previous opponent, swap with the next candidate down. Detect cycles and back-track if necessary (rarely needed for our N≤64 scale).
5. Floats: if a score group has odd count, the bottom participant "floats down" to the next group.

**Tiebreaker (Buchholz):**
```
buchholz(p) = sum over all p's opponents: opponent.current_score
```
Variants: median Buchholz (drop highest + lowest), modified (drop unplayed) — Phase 6 ships **plain Buchholz** for v1; admin can request variants in v2 (logged as Phase 9 polish item).

**Storage:** Generate one stage per round (`tournament_stages.type = 'swiss-round'`, ordinal = round number). Each round's brackets are independent (no `advances_to`); `StandingsCalculatorService` reads cumulative results.

**Open question:** Whether to generate round 2+ automatically (after all round-N matches have results) or require admin to click "Generate next round". **Recommendation: admin clicks** — admin retains override ability; surfaces tiebreak ambiguity if any. Filament action `Generate next Swiss round` on `TournamentResource::HeaderActions`, visible only when current round is complete.

### Pattern 6: Double-elimination (Burton variant)

**What:** Winner bracket + loser bracket + grand final (optional reset). Loser-bracket organisation: each round has a minor + major stage; losers from W-bracket round `r` drop into L-bracket minor stage of round corresponding to `r`. `[VERIFIED: Wikipedia + Brackets Ninja + brackets-manager.js docs Context7]`

**When to use:** 2nd most common format for esports; gives every team a "second life".

**Algorithm:**
1. Generate W-bracket per Pattern 3 (single-elim).
2. Generate L-bracket: for N=8, L-bracket has 5 rounds (`LB1 minor, LB1 major, LB2 minor, LB2 major, LB final`); for N=16, 6 rounds.
3. **Loser drop chain:** Each W-bracket loser drops to a specific L-bracket position. Standard mapping is to spread loser pairs across the L-bracket halves to avoid same-region rematches. Use `brackets-manager.js`'s output as our test oracle.
4. Grand final: W-winner vs L-winner. If W-winner loses, optional reset match (config flag `grand_final_reset` on `tournament_stages.settings`).

**Storage:** Two `tournament_stages` (`type='winners-bracket'`, `type='losers-bracket'`) + 1 grand-final stage. Brackets in W-stage point `advances_to_bracket_id` at next W-bracket; brackets that produce a loser also point `loser_advances_to_bracket_id` at the L-bracket entry (new column). Grand final's W and L participants are filled from W-stage final winner + L-stage final winner.

**Schema note:** add `loser_advances_to_bracket_id` (nullable self-FK) to `tournament_brackets` to support loser drops without a separate table.

### Pattern 7: BracketAdvancementService

**What:** Hooks after `MatchResultService::upsert()` writes `winner_clan_id`. Resolves the corresponding tournament participant + propagates winner forward.

**Trigger options (pick one):**
- **Option A — observer on MatchResult:** `MatchResultObserver::saved($result)` checks if `result->match->bracket()->exists()`. Pro: decoupled from MatchResultService; Con: another observer layer.
- **Option B — explicit call inside MatchResultService:** add a `BracketAdvancementService` call after the auto-status-flip. Pro: explicit, easy to trace; Con: couples Phase 4 service to Phase 6.
- **Recommendation: Option A (observer)** — preserves Phase 4 service purity; matches the existing observer pattern in Phase 4/5 (MatchObserver → events + outbound).

**Example:**

```php
final class BracketAdvancementService
{
    public function advance(MatchResult $result): void
    {
        // 1. Find bracket linked to this match
        $bracket = TournamentBracket::where('match_id', $result->match_id)->first();
        if ($bracket === null) {
            return; // non-tournament match — no advancement
        }

        // 2. Resolve winner participant by clan_id lookup
        $winnerParticipant = TournamentParticipant::where('tournament_id', $bracket->stage->tournament_id)
            ->where('clan_id', $result->winner_clan_id)
            ->first();

        if ($winnerParticipant === null) {
            throw new BracketWinnerNotParticipantException(__('tournaments.errors.winner_not_participant'));
        }

        // 3. Write winner_participant_id
        DB::transaction(function () use ($bracket, $winnerParticipant) {
            $bracket->update(['winner_participant_id' => $winnerParticipant->id]);

            // 4. Propagate to next bracket if linked
            if ($bracket->advances_to_bracket_id !== null) {
                $next = TournamentBracket::lockForUpdate()->find($bracket->advances_to_bracket_id);
                // Decide slot a vs b based on origin position parity (or even/odd round logic)
                $slot = $this->resolveSlot($bracket, $next);   // returns 'a' or 'b'
                $next->update(["participant_{$slot}_id" => $winnerParticipant->id]);
            }

            // 5. If round-robin or swiss — no advancement chain; just recalc standings
            app(StandingsCalculatorService::class)->recalculate($bracket->stage->tournament);

            // 6. Trigger Discord announce (kind=bracket_result_announce)
            DiscordOutboundMessage::create([
                'message_type' => 'bracket_result_announce',
                'status' => 'pending',
                'payload' => app(DiscordOutboundPayloadBuilder::class)->buildBracketResult($bracket),
                // ... other fields
            ]);

            // 7. If this was the last bracket of the stage / tournament, advance tournament status
            if ($bracket->stage->tournament->allBracketsComplete()) {
                app(TournamentStatusService::class)->transition($bracket->stage->tournament, 'completed');
            }
        });
    }

    private function resolveSlot(TournamentBracket $from, TournamentBracket $to): string
    {
        // Even position → 'a'; odd position → 'b'. Standard bracket fold.
        return $from->position % 2 === 1 ? 'a' : 'b';
    }
}
```

### Pattern 8: SVG bracket renderer (server data + Vue layout)

**What:** Server emits a `PublicTournamentData` DTO containing `nodes: BracketNodeData[]` and `edges: BracketEdgeData[]`. Vue's `<BracketCanvas>` component maps nodes to a 2D grid `(x = round_number × column_width, y = position × row_height + round_offset)` and emits `<line>` SVG edges + `<g><rect/><text/></g>` node groups.

**Node DTO shape:**
```typescript
type BracketNodeData = {
  id: string,                                // tournament_brackets.id (UUID)
  round_number: number,
  position: number,
  participant_a: { id: string, clan_name: string, seed: number } | null,
  participant_b: { id: string, clan_name: string, seed: number } | null,
  winner_participant_id: string | null,
  match_id: string | null,                   // link to /matches/{id} page
  status: 'pending' | 'in-progress' | 'completed' | 'bye',
  scheduled_at: string | null,
};
```

**Edge DTO shape:**
```typescript
type BracketEdgeData = {
  from_bracket_id: string,
  to_bracket_id: string,
  to_slot: 'a' | 'b',                        // which slot of the destination this edge feeds
  type: 'winner' | 'loser',                  // 'loser' for double-elim drop edges
};
```

**Layout (Vue side):**

```typescript
// resources/js/components/tournaments/BracketCanvas.vue
const COLUMN_WIDTH = 200;
const ROW_HEIGHT = 80;
const ROUND_OFFSET = 40;        // first round y-offset to center subsequent rounds

const layout = computed(() => {
  const positions = new Map<string, { x: number, y: number }>();
  for (const node of props.nodes) {
    const x = (node.round_number - 1) * COLUMN_WIDTH + 20;
    // y-positioning: each subsequent round centers between its feeder nodes.
    // Simple impl: y = position * ROW_HEIGHT * (2 ^ (round_number - 1))
    //              - ROW_HEIGHT * (2 ^ (round_number - 1) / 2)
    const verticalSpacing = ROW_HEIGHT * Math.pow(2, node.round_number - 1);
    const y = (node.position - 1) * verticalSpacing + verticalSpacing / 2;
    positions.set(node.id, { x, y });
  }
  return positions;
});
```

Edges drawn as 3-segment polylines: horizontal out from source → vertical to destination row → horizontal into destination. Tailwind v4 CSS variables for color (`var(--color-bracket-winner-line)`, `var(--color-bracket-loser-line)` for double-elim).

### Pattern 9: 30s polling JSON endpoint

**What:** `/tournaments/{slug}.json` returns the full public tournament shape with an `etag` field (computed as `sha256(json_body)`). Vue polls every 30s; client compares previous etag, skips update if unchanged.

**Server:**
```php
public function show(Tournament $tournament): JsonResponse
{
    $data = PublicTournamentData::fromModel($tournament);
    $json = json_encode($data->toArray());
    $etag = sha1($json);
    return response()->json([
        'data' => $data,
        'etag' => $etag,
        'last_modified_at' => now()->toIso8601String(),
    ])->setEtag($etag);
}
```

**Client:**
```typescript
// composables/useTournamentPolling.ts
const tournament = ref<PublicTournamentData | null>(null);
const lastEtag = ref<string | null>(null);

const poll = async () => {
  const res = await fetch(`/tournaments/${slug}.json`, {
    headers: lastEtag.value ? { 'If-None-Match': lastEtag.value } : {},
  });
  if (res.status === 304) return;  // unchanged
  const body = await res.json();
  tournament.value = body.data;
  lastEtag.value = body.etag;
};

onMounted(() => {
  poll();
  const interval = setInterval(poll, 30000);
  onUnmounted(() => clearInterval(interval));
});
```

### Pattern 10: Filament admin actions (seed / reseed / forfeit / withdraw / recalculate)

**What:** Each action is a Filament v3 `Action` on `TournamentResource::HeaderActions` (single-tournament page actions) or `Tables\Actions\Action` (table-level). All use `requiresConfirmation()` + a service-layer call. `[VERIFIED: Filament v3 docs Context7]`

**Example (Reseed):**
```php
// In EditTournament::getHeaderActions()
Action::make('reseed')
    ->label(__('admin.tournament.actions.reseed.label'))
    ->icon('heroicon-o-arrow-path')
    ->color('warning')
    ->requiresConfirmation()
    ->modalHeading(__('admin.tournament.actions.reseed.modal_heading'))
    ->modalDescription(__('admin.tournament.actions.reseed.modal_description'))
    ->visible(fn (Tournament $record) => $record->canReseed())  // see Pattern 11
    ->action(function (Tournament $record) {
        app(TournamentSeedingService::class)->reseed($record);
        Notification::make()
            ->success()
            ->title(__('admin.tournament.actions.reseed.success'))
            ->send();
    });
```

**Reseed eligibility (Pattern 11):** `canReseed()` returns true only if status is `seeded` AND no `tournament_brackets` row has a non-null `match_id` (no matches materialised yet) OR matches exist but none have results. **Researcher recommendation: tighten to "no matches played" (i.e., no `MatchResult` rows exist for any bracket-linked match).** Once a result is recorded, reseeding would invalidate completed work — admin should `cancel` and create a new tournament instead.

### Anti-Patterns to Avoid

- **Generating brackets from within the Filament wizard.** Bracket generation is a side effect of `TournamentStatusService::start()` (seeded → running). Do not put generator calls in `CreateTournament::handleRecordCreation` — they belong in the service-layer state transition.
- **Hand-rolling MatchSignupService for tournament matches.** Reuse Phase 4 `MatchSignupService` verbatim. Bracket-matches are just `GameMatch` rows; they sign up the same way.
- **Writing tournament-aware logic into `MatchResultService`.** Use `MatchResultObserver` instead. Phase 4 stays Phase-4-pure.
- **Storing bracket layout pixels in the database.** Layout is presentation; only `round_number` + `position` + `advances_to_bracket_id` + (optional) `loser_advances_to_bracket_id` are persisted. Vue computes pixels.
- **Letting admin manually edit individual `tournament_brackets` rows.** Brackets are write-once by `BracketGeneratorService`. Admin actions (forfeit/withdraw) write to `tournament_participants.status` + recompute via `BracketAdvancementService`-style logic.
- **Using `Tournament::query()->update(...)` for state transitions.** Bypasses observers (Phase 4 Pitfall 12). Always `$tournament->save()` or `$tournament->update(...)` on a model instance.
- **Recomputing standings on every page load.** Recompute on bracket completion (via observer chain) + Filament `Recalculate standings` action. Page reads denormalised `tournament_standings` rows.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Match capacity enforcement under concurrent signup | Custom locking in tournament-match signup controller | `MatchSignupService` (Phase 4 plan 04-06) | Already row-locked, 5-guard, pcntl-tested; bracket-matches are GameMatch rows so it just works |
| Match slot grid creation | Custom slot insertion in BracketMatchMaterialiserService | `MatchSlotMaterialiserService` (Phase 4 plan 04-05) | Snapshot semantics already locked (D-04-05-A); reuse via DI |
| Match result recording | Custom result endpoint for tournament matches | `MatchResultService::upsert()` (Phase 4 plan 04-09) | Terminal-state skip logic already correct; auto status flip to `played` |
| Polymorphic calendar event for Tournament | Custom event table or duplicate columns | `MorphOne<Event, $this>` + `TournamentObserver` mirrors `MatchObserver` (Phase 4 plan 04-08) | `events` table is already polymorphic |
| Discord embed delivery for tournament announce | Custom HTTP call from observer | `discord_outbound_messages` outbox + bot polling worker (Phase 5) | Adds `kind=tournament_announce` to the existing outbox; durability already proven |
| Tournament status state machine | Hand-rolled transitions | `TournamentStatusService` mirroring `MatchStatusService` (Phase 4 plan 04-04 D-04-04-A) | Established Phase 4 idiom — explicit ALLOWED map + audit log via `withProperties()` |
| Tournament audit log | Custom audit table | `LogsActivity` trait on every Tournament* model | D-012 requires it; Phase 4/5 idiom proven across 12 models |
| Tournament i18n | English-only strings | `apps/web/lang/en/tournaments.php` + `__()` / `t()` everywhere | D-013 + CLAUDE.md §7; NoHardcodedStringsTest enforces |
| TS types for frontend | Hand-written `BracketNodeData` types | `spatie/laravel-data` `#[TypeScript]` + `php artisan trenchwars:typescript-generate` | D-020 + Phase 1 plan 01-15 pipeline |
| Player privacy on standings MVPs | Conditional rendering in Vue | `PlayerPrivacyGate` (Phase 2 plan 02-05) on the DTO factory | D-018 + Phase 2 idiom |
| TournamentResource scaffold | Hand-rolled CRUD | Filament `php artisan make:filament-resource Tournament --view` | Standard Filament v3 idiom; covered by Phase 1–5 |
| Bracket SVG rendering | Vendor Vue bracket lib | Hand-rolled SVG `<g><line/><rect/><text/></g>` in `BracketCanvas.vue` | Vue libs are Vue 2 era or React; ~200 LOC for our needs |
| Etag on JSON polling endpoint | Custom hash logic | Laravel `response()->json()->setEtag($sha1)` + `If-None-Match` header | Laravel native; matches HTTP/1.1 spec |

**Key insight:** Phase 6 is overwhelmingly **reuse**, not new build. The Phase 4–5 stack provides every mutation primitive Phase 6 needs; the only genuinely new code is the 4 bracket generators + state machine + SVG renderer + Filament admin glue. Estimate: ~12 plans (per CONTEXT.md), 12–14 commits, no new vendor deps.

---

## Common Pitfalls

### Pitfall 1: PHP 8 `match` reserved keyword (continuation of D-04-03-A)

**What goes wrong:** Writing `App\Models\Match` for a bracket-match relation triggers `PHP Parse error: syntax error, unexpected token "match", expecting identifier`.

**Why it happens:** `match` is a PHP 8.0+ reserved keyword for the `match($x){}` expression. Class names cannot collide.

**How to avoid:** Phase 6 uses `App\Models\GameMatch` everywhere. `TournamentBracket::match()` relation method is fine (PHP allows reserved words as method names), but `belongsTo(GameMatch::class, 'match_id')` MUST pass the explicit FK arg per D-04-03-B.

**Warning signs:** Any plan task referencing `App\Models\Match` → reject at plan-check; substitute `App\Models\GameMatch`. Grep gate in phase close: `grep -r 'App\\Models\\Match[^T]' apps/web/app` returns 0 hits (except in references inside docstrings — those are intentional).

### Pitfall 2: Bracket round_number off-by-one when computing advances_to

**What goes wrong:** New bracket row's `advances_to_bracket_id` points to the wrong target (e.g., off by one position in the next round).

**Why it happens:** The natural formula is `next_position = ceil(current_position / 2)`. Mistake is integer division `current_position / 2` which truncates.

**How to avoid:** In PHP use `(int) ceil($currentPosition / 2)` or the explicit `intval(($currentPosition + 1) / 2)`. Write a PHPUnit/Pest test that asserts the advances_to chain for an 8-participant single-elim: [1→Q1, 2→Q1, 3→Q2, 4→Q2, 5→Q3, 6→Q3, 7→Q4, 8→Q4] → [Q1, Q2 → S1; Q3, Q4 → S2] → [S1, S2 → F]. `BracketGeneratorSingleEliminationTest` cross-references this layout.

**Warning signs:** Vue layout looks "twisted" — first-round winners feeding wrong-side semifinals.

### Pitfall 3: Bracket generator non-idempotency

**What goes wrong:** Admin clicks "Start tournament" twice; second click regenerates brackets and orphans the first set (or duplicates rows on UNIQUE collision).

**Why it happens:** `BracketGeneratorService::generate()` has no idempotency guard.

**How to avoid:** Guard at entry: if `$tournament->stages()->exists()`, throw `BracketsAlreadyGeneratedException`. Combined with `TournamentStatusService::transition()` which only allows `seeded → running` once (status check is the second layer of defense).

**Warning signs:** Tournament has 2× expected brackets in `tournament_brackets`; status is still `running`.

### Pitfall 4: Bracket → GameMatch materialisation race

**What goes wrong:** Two admins click "Materialise next round" simultaneously; both spawn GameMatch rows for the same bracket → orphan GameMatch + duplicate slot grids.

**Why it happens:** No row-lock on `tournament_brackets.match_id` write.

**How to avoid:** Inside `BracketMatchMaterialiserService::materialiseFor($bracket)`, wrap in `DB::transaction(function () use ($bracket) { $b = TournamentBracket::lockForUpdate()->find($bracket->id); if ($b->match_id !== null) return $b->match; /* create */ });`. Mirrors D-04-06-A row-lock idiom verbatim.

**Warning signs:** Two GameMatch rows exist with the same `tournament_brackets.id` reference (DB CHECK or UNIQUE on `match_id` would catch — add it: `UNIQUE INDEX tournament_brackets_match_id_unique ON tournament_brackets(match_id) WHERE match_id IS NOT NULL`).

### Pitfall 5: Swiss never-paired-before constraint creates impossible pairings

**What goes wrong:** Swiss generator backtrack loops infinitely on certain participant constellations (e.g., 4 players, 3 rounds — everyone has played everyone).

**Why it happens:** Round count `ceil(log2(N))` can equal or exceed `N-1` (the round-robin upper bound) for small N. Once exhausted, no valid pairing exists.

**How to avoid:** Add a sanity check in `SwissGenerator::generate()`: refuse to start a swiss tournament with `participants_count < 2 ^ desired_rounds` (i.e., for `ceil(log2(N))` rounds you need at least 2 to-the-power-of-rounds participants). Add a participant lower bound (e.g., minimum 6 for 3-round swiss). Surface as i18n key `tournaments.errors.swiss_too_few_participants`.

**Warning signs:** Swiss generator hangs or throws on round 3+ for small participant counts.

### Pitfall 6: Standings recalculate trampling concurrent writes

**What goes wrong:** Two MatchResult writes complete simultaneously; both trigger StandingsCalculatorService::recalculate; one overwrites the other's intermediate state.

**Why it happens:** No serialisation around the per-tournament recalculate.

**How to avoid:** Wrap `StandingsCalculatorService::recalculate(Tournament $t)` in `DB::transaction(function () use ($t) { Tournament::lockForUpdate()->find($t->id); /* ... */ });`. The parent row lock serialises recalculation.

**Warning signs:** Standings show stale Buchholz scores or rank tie-breaker drift after concurrent match results.

### Pitfall 7: TournamentObserver double-fire on cascade observer chain

**What goes wrong:** When TournamentStatusService transitions status, the observer fires twice (once for the model save, once for the activity-log write of a different model that touches tournament).

**Why it happens:** Eloquent's observer dispatches on every `save()`. If a status transition triggers a related save that itself loops back, the observer fires twice.

**How to avoid:** Gate the observer's outbound-write logic on `wasChanged('status')` (Phase 5 D-05-05 idiom). Test: assert that updating `tournament.title` (no status change) does NOT enqueue a Discord outbound row.

**Warning signs:** Duplicate `discord_outbound_messages` rows with `message_type=tournament_announce`.

### Pitfall 8: Polling endpoint cache staleness

**What goes wrong:** Public viewers see stale brackets after a match result is recorded, despite the 30s poll interval.

**Why it happens:** Etag computed from a snapshot that's cached at the controller level; underlying data changed but the cached snapshot didn't refresh.

**How to avoid:** Phase 6 v1 does NOT use Laravel response cache for the JSON endpoint. Compute the etag from a `tournament.updated_at` + `tournament.brackets.max(updated_at)` SQL aggregate per request (one cheap query). Adding HTTP cache layer is a Phase 9 polish item.

**Warning signs:** Vue's etag never changes despite admin writes; bracket UI doesn't update.

### Pitfall 9: SVG renderer x/y math fails for double-elim L-bracket

**What goes wrong:** Loser bracket nodes overlap the winner bracket or appear off-screen.

**Why it happens:** The simple "x = round × column_width" assumes a single rooted tree. Double-elim has two separate trees (W and L) plus a grand final.

**How to avoid:** Render W-bracket and L-bracket as two separate `<g transform="translate(...)">` groups stacked vertically (W on top, L on bottom). The DTO carries `tournament_stages.type = 'winners-bracket' | 'losers-bracket' | 'grand-final'`; Vue groups nodes by stage and offsets each group's y-coordinate.

**Warning signs:** Visual chaos when rendering 8-team double-elim.

### Pitfall 10: i18n key explosion for format-specific labels

**What goes wrong:** Admin sees "tournament_format_round_robin" raw key in UI because the i18n file has the key but a typo broke the lookup.

**Why it happens:** 4 formats × 5+ surfaces (admin label, public badge, modal headings, error messages, status colors) = 20+ keys; easy to miss one.

**How to avoid:** Centralise format labels in `apps/web/lang/en/tournaments.php` under a `formats` namespace; reference via `__('tournaments.formats.' . $format . '.label')`. `NoHardcodedStringsTest` catches missed `__()` wrapping. Add a Pest test that all 4 formats × 4 i18n keys (label, description, badge_class, badge_label) resolve to non-empty strings.

**Warning signs:** Test failure with "tournaments.formats.swiss.label resolves to itself" (missing key).

### Pitfall 11: tournament_brackets self-FK cycle on advances_to_bracket_id

**What goes wrong:** A buggy generator writes `advances_to_bracket_id = self.id`; advancement loops infinitely.

**Why it happens:** Off-by-one in round index calculation; cycle in bracket-to-bracket linking.

**How to avoid:** Add DB-level CHECK: `CHECK (advances_to_bracket_id != id)`. Add service-level depth limit in `BracketAdvancementService::advance()` — if propagation depth exceeds `ceil(log2(N)) + 2`, throw.

**Warning signs:** Stack overflow or infinite loop in advancement code path.

### Pitfall 12: Forfeit / withdraw timing leaks into played matches

**What goes wrong:** Admin marks a participant as withdrawn AFTER their first match completed; the matches retroactively become invalid; standings shift unexpectedly.

**Why it happens:** Forfeit/withdraw semantics not clearly defined.

**How to avoid:** **Forfeit** (`tournament_participants.status = 'disqualified'`) only affects FUTURE matches — past matches retain their results; the participant just doesn't advance further. **Withdraw** (`tournament_participants.status = 'withdrawn'`) same semantics; admin chooses one. Both write `activity_log` rows with `withProperties(['reason' => '...'])`. Document this in `apps/web/lang/en/tournaments.php` action descriptions.

**Warning signs:** Admin reports "standings are wrong after I forfeited a player".

---

## Code Examples

### Migration: tournaments table

```php
// Source: hand-rolled; pattern verbatim from 2026_05_14_100000_create_matches_table.php
// File: apps/web/database/migrations/2026_05_15_100000_create_tournaments_table.php

Schema::create('tournaments', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('game_id');                                       // D-007 FK to games
    $table->string('slug')->unique();                              // public URL slug
    $table->jsonb('title');                                        // D-013 translatable
    $table->jsonb('description')->nullable();
    $table->text('format');                                        // 'single_elimination' | 'double_elimination' | 'round_robin' | 'swiss'
    $table->text('status')->default('draft');                      // 'draft' | 'registering' | 'seeded' | 'running' | 'completed' | 'cancelled'
    $table->timestampTz('starts_at')->nullable();
    $table->timestampTz('ends_at')->nullable();
    $table->integer('max_participants')->nullable();               // cap; null = unlimited
    $table->jsonb('settings')->nullable();                         // format-specific config (group_count, grand_final_reset, etc.)
    $table->uuid('organiser_user_id');                             // who created/runs it
    $table->uuid('default_game_match_type_id')->nullable();        // GameMatchType to use when materialising bracket-matches
    $table->boolean('is_public')->default(true);
    $table->timestamps();

    $table->foreign('game_id')->references('id')->on('games')->restrictOnDelete();
    $table->foreign('organiser_user_id')->references('id')->on('users')->restrictOnDelete();
    $table->foreign('default_game_match_type_id')->references('id')->on('game_match_types')->nullOnDelete();

    $table->index('slug');
    $table->index(['status', 'starts_at']);
    $table->index('game_id');
    $table->index('is_public');
});

DB::statement('ALTER TABLE tournaments ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_format_check CHECK (format IN ('single_elimination','double_elimination','round_robin','swiss'));");
DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_status_check CHECK (status IN ('draft','registering','seeded','running','completed','cancelled'));");
DB::statement("ALTER TABLE tournaments ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
DB::statement("ALTER TABLE tournaments ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
```

### Migration: tournament_brackets (with self-FK)

```php
// File: apps/web/database/migrations/2026_05_15_100300_create_tournament_brackets_table.php

Schema::create('tournament_brackets', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('tournament_stage_id');
    $table->integer('round_number');
    $table->integer('position');
    $table->uuid('participant_a_id')->nullable();
    $table->uuid('participant_b_id')->nullable();
    $table->uuid('winner_participant_id')->nullable();
    $table->uuid('match_id')->nullable();                           // FK to matches (the GameMatch this bracket spawned)
    $table->uuid('advances_to_bracket_id')->nullable();             // winner advances here (self-FK)
    $table->uuid('loser_advances_to_bracket_id')->nullable();       // for double-elim drop chain (self-FK)
    $table->timestamps();

    $table->foreign('tournament_stage_id')->references('id')->on('tournament_stages')->cascadeOnDelete();
    $table->foreign('participant_a_id')->references('id')->on('tournament_participants')->nullOnDelete();
    $table->foreign('participant_b_id')->references('id')->on('tournament_participants')->nullOnDelete();
    $table->foreign('winner_participant_id')->references('id')->on('tournament_participants')->nullOnDelete();
    $table->foreign('match_id')->references('id')->on('matches')->nullOnDelete();
    $table->foreign('advances_to_bracket_id')->references('id')->on('tournament_brackets')->nullOnDelete();
    $table->foreign('loser_advances_to_bracket_id')->references('id')->on('tournament_brackets')->nullOnDelete();

    $table->index(['tournament_stage_id', 'round_number', 'position']);
    $table->index('match_id');
});

DB::statement('ALTER TABLE tournament_brackets ALTER COLUMN id SET DEFAULT gen_random_uuid();');

// Pitfall 11 — no self-cycle
DB::statement("ALTER TABLE tournament_brackets ADD CONSTRAINT tournament_brackets_no_self_advance CHECK (advances_to_bracket_id != id AND loser_advances_to_bracket_id != id);");

// Pitfall 4 — one GameMatch per bracket (partial unique allowing nulls)
DB::statement("CREATE UNIQUE INDEX tournament_brackets_match_id_unique ON tournament_brackets(match_id) WHERE match_id IS NOT NULL;");

DB::statement("ALTER TABLE tournament_brackets ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
DB::statement("ALTER TABLE tournament_brackets ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
```

### Model: Tournament (mirrors GameMatch idiom)

```php
// File: apps/web/app/Models/Tournament.php
namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\TournamentObserver;
use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;
    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'game_id', 'slug', 'title', 'description', 'format', 'status',
        'starts_at', 'ends_at', 'max_participants', 'settings',
        'organiser_user_id', 'default_game_match_type_id', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_participants' => 'integer',
            'settings' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $event): string => "Tournament {$event}");
    }

    public function getRouteKeyName(): string { return 'slug'; }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo { return $this->belongsTo(Game::class); }

    /** @return BelongsTo<User, $this> */
    public function organiser(): BelongsTo { return $this->belongsTo(User::class, 'organiser_user_id'); }

    /** @return BelongsTo<GameMatchType, $this> */
    public function defaultGameMatchType(): BelongsTo { return $this->belongsTo(GameMatchType::class, 'default_game_match_type_id'); }

    /** @return HasMany<TournamentParticipant, $this> */
    public function participants(): HasMany { return $this->hasMany(TournamentParticipant::class); }

    /** @return HasMany<TournamentStage, $this> */
    public function stages(): HasMany { return $this->hasMany(TournamentStage::class)->orderBy('ordinal'); }

    /** @return HasMany<TournamentStanding, $this> */
    public function standings(): HasMany { return $this->hasMany(TournamentStanding::class); }

    /** @return MorphOne<Event, $this> */
    public function event(): MorphOne { return $this->morphOne(Event::class, 'eventable'); }

    protected static function booted(): void
    {
        static::observe(TournamentObserver::class);
    }
}
```

### TournamentObserver (mirrors MatchObserver)

```php
// File: apps/web/app/Observers/TournamentObserver.php
namespace App\Observers;

use App\Models\Event;
use App\Models\DiscordOutboundMessage;
use App\Models\Tournament;
use App\Support\DiscordOutboundPayloadBuilder;

class TournamentObserver
{
    /**
     * Mirror MatchObserver::saved(): keep Event row coherent with is_public + status.
     */
    public function saved(Tournament $tournament): void
    {
        $shouldHaveEvent = $tournament->is_public && $tournament->status !== 'cancelled';
        if ($shouldHaveEvent) {
            Event::updateOrCreate(
                ['eventable_type' => Tournament::class, 'eventable_id' => $tournament->id],
                [
                    'starts_at' => $tournament->starts_at ?? now(),
                    'ends_at' => $tournament->ends_at,
                    'title' => $tournament->getTranslations('title'),
                    'is_public' => $tournament->is_public,
                ],
            );
            return;
        }
        Event::where('eventable_type', Tournament::class)
            ->where('eventable_id', $tournament->id)
            ->delete();
    }

    /**
     * On create with is_public=true → enqueue tournament_announce outbound.
     * Mirror Phase 5 D-05-05-B/E pattern.
     */
    public function created(Tournament $tournament): void
    {
        if (! $tournament->is_public) return;
        $payload = app(DiscordOutboundPayloadBuilder::class)->buildTournamentAnnounce($tournament);
        DiscordOutboundMessage::create([
            'message_type' => 'tournament_announce',
            'status' => 'pending',
            'channel_id' => null,            // resolved at dispatch time from system announce channel
            'payload' => $payload,
            'causer_user_id' => auth()->id(),
        ]);
    }

    /** Status transition triggers update announce (only on wasChanged('status') — Pitfall 7) */
    public function updated(Tournament $tournament): void
    {
        if (! $tournament->wasChanged('status')) return;
        if (! $tournament->is_public) return;
        // ... build kind=tournament_announce_update payload
    }
}
```

### Filament TournamentResource header actions

```php
// File: apps/web/app/Filament/Resources/TournamentResource/Pages/EditTournament.php

protected function getHeaderActions(): array
{
    return [
        Action::make('open_registration')
            ->label(__('admin.tournament.actions.open_registration.label'))
            ->visible(fn () => $this->record->status === 'draft')
            ->requiresConfirmation()
            ->action(fn () => app(TournamentStatusService::class)->transition($this->record, 'registering')),

        Action::make('seed')
            ->label(__('admin.tournament.actions.seed.label'))
            ->visible(fn () => $this->record->status === 'registering' && $this->record->participants()->count() >= 2)
            ->requiresConfirmation()
            ->form([Forms\Components\Select::make('strategy')->options(['by_rank' => 'By rank', 'random' => 'Random', 'manual' => 'Manual'])->required()])
            ->action(function (array $data) {
                app(TournamentSeedingService::class)->seed($this->record, $data['strategy']);
                app(TournamentStatusService::class)->transition($this->record, 'seeded');
            }),

        Action::make('start')
            ->label(__('admin.tournament.actions.start.label'))
            ->visible(fn () => $this->record->status === 'seeded')
            ->requiresConfirmation()
            ->action(function () {
                app(BracketGeneratorService::class)->generate($this->record);
                app(TournamentStatusService::class)->transition($this->record, 'running');
                // Optional: also materialise round 1 GameMatches immediately
                app(BracketMatchMaterialiserService::class)->materialiseFirstRound($this->record);
            }),

        Action::make('reseed')
            ->label(__('admin.tournament.actions.reseed.label'))
            ->visible(fn () => $this->record->canReseed())   // status=seeded AND no MatchResult written yet
            ->requiresConfirmation()
            ->modalDescription(__('admin.tournament.actions.reseed.modal_description'))
            ->action(fn () => app(TournamentSeedingService::class)->reseed($this->record)),

        Action::make('recalculate_standings')
            ->label(__('admin.tournament.actions.recalculate_standings.label'))
            ->visible(fn () => in_array($this->record->status, ['running', 'completed'], true))
            ->action(fn () => app(StandingsCalculatorService::class)->recalculate($this->record)),

        Action::make('cancel')
            ->label(__('admin.tournament.actions.cancel.label'))
            ->color('danger')
            ->visible(fn () => ! in_array($this->record->status, ['completed', 'cancelled'], true))
            ->requiresConfirmation()
            ->action(fn () => app(TournamentStatusService::class)->transition($this->record, 'cancelled')),
    ];
}
```

`[CITED: Filament v3 docs — Action::requiresConfirmation()]`

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded HLL tournament logic | D-007 generic Game model (Phase 3) | 2026-05-13 | Phase 6 tournaments are game-agnostic; same code runs for any future game |
| Bigint primary keys | UUID primary keys (`HasUuidPrimaryKey` trait) | Phase 1 | Phase 6 tables follow suit; tournament URLs use `slug` not numeric ids |
| WebSocket-first live updates | 30s JSON polling | Round-1 scope decision (REQ-non-goals-round-1) | Phase 6 polls; WebSocket upgrade tracked as TOUR-V2-01 |
| Filament v2 | Filament v3.3 (`v3.3.50` verified 2026-05-13) | Phase 1 D-001 | Phase 6 uses Filament v3 idioms (Tabs, RelationManagers, Action::requiresConfirmation) |
| Hand-rolled JS bundling | Vite ^6 (Phase 1) | Phase 1 D-001 | Phase 6 Vue components hot-reload during dev; built bundle via `vite build` |
| Hand-written TS types | spatie/laravel-data #[TypeScript] → api.d.ts | Phase 1 plan 01-15 | Phase 6 emits ~8 new DTOs; TS types auto-generated via `php artisan trenchwars:typescript-generate` |

**Deprecated/outdated (do not use):**

- Filament v2 documentation (e.g., `/2.x/tables/actions` web-search hit) — we're on v3.3, API is different.
- Laravel Mix SVG plugins — we're on Vite ^6, not Mix. Use Vite's static asset import + inline `<svg>` JSX in Vue.
- `react-tournament-brackets` / `kamilwylegala/vue-tournament-bracket` — Vue 2 era or React-only; not compatible with our Vue 3 + Composition API + TS strict setup.
- Hand-rolled tournament libraries in PHP (e.g., old composer packages from pre-Laravel-9 era). Most are stale or PHP 7 only.

---

## Assumptions Log

Phase 6 has surprisingly few `[ASSUMED]` claims because the patterns are inherited from Phase 4–5 (verified by running tests + 618 passing Pest + 117 passing Vitest). The remaining assumptions concern algorithm details and open admin-UX questions.

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Inner_outer ordering for size=8 → [1,8,4,5,2,7,3,6] | Pattern 3 | Cosmetic: bracket displays "wrong" pairings vs ATP/WTA standard. Cross-check against brackets-manager.js test vectors. `[CITED: brackets-manager.js Context7]` but should be verified against published ATP draw. |
| A2 | Swiss minimum N for ceil(log2(N)) rounds is 2^rounds | Pattern 5 + Pitfall 5 | If wrong, generator hangs on small tournaments. Mitigated by sanity check + admin error message. |
| A3 | Buchholz tiebreak (plain, no median variant) is sufficient for v1 | Pattern 5 | Some tournament cultures require median Buchholz; if users complain, Phase 9 polish item to add variant selector. |
| A4 | Reseed is allowed only before any MatchResult exists (NOT just before any GameMatch exists) | Pattern 11 (Filament reseed eligibility) | Stricter than CONTEXT.md says ("stage with no matches played"). Aligns to "no results recorded yet" which is what CONTEXT.md likely means. **Need user confirmation if this matches intent.** |
| A5 | Forfeit and withdraw have IDENTICAL forward effect (no future matches, past matches retained) | Pitfall 12 | If admins expect forfeit to retroactively zero past matches, current semantics surprise them. Documented in i18n action descriptions. |
| A6 | Swiss next-round generation requires admin click (not auto-fire after all current-round matches complete) | Pattern 5 | If users expect auto-progression, admin clicking is friction. **Researcher recommendation: ship admin-click for v1; add auto-fire option in v2 if requested.** |
| A7 | Double-elim L-bracket layout uses standard "spread loser pairs across L-bracket halves" mapping | Pattern 6 | If our hand-rolled mapping differs from brackets-manager.js, our brackets look "weird" but still correct. Cross-check with brackets-manager.js for 8-team and 16-team layouts. |
| A8 | Tournament organiser can be different from admin (limited-scope role) | C11 / Filament resource gating | If users want admin-only for v1, the `tournament.organize` permission is over-scoped. CONTEXT.md doesn't speak to this. **Recommended: ship `tournament.manage` for admins only in v1; add `tournament.organize` later.** |
| A9 | `default_game_match_type_id` per tournament is sufficient (no per-bracket override) | tournaments table migration | If a tournament needs "groups use 6v6, finals use 50v50", v1 design forces same GameMatchType throughout. Stage-level override is a Phase 9 polish addition. |
| A10 | Bracket-match GameMatch's host_clan is null (not host_clan_id = participant_a_id) | tournaments / GameMatch FK semantics | "Host clan" concept doesn't quite apply to tournament brackets — both participants are guests. nullable host_clan_id is already in the schema (Phase 4 migration). Confirm: bracket GameMatch.host_clan_id stays null. |

**Recommendation:** Have discuss-phase confirm A4, A5, A6, A8 before plan generation. The others (A1, A2, A3, A7, A9, A10) are reasonable defaults that can be flipped in Phase 9 polish without architectural change.

---

## Open Questions

1. **Reseed eligibility threshold (A4)**
   - What we know: CONTEXT.md says "stage with no matches played"; my reading is "no MatchResult rows exist".
   - What's unclear: Does "matches played" mean "MatchResult.recorded_at IS NOT NULL" or "GameMatch.status == 'played'" or "any GameMatch materialised"?
   - Recommendation: lock to "no MatchResult rows exist for any bracket-linked match" as the strictest reasonable reading; surface in discuss-phase.

2. **Swiss next-round trigger (A6)**
   - What we know: round 2+ pairing depends on round-N results.
   - What's unclear: auto-fire after last result, or admin-click?
   - Recommendation: admin-click for v1; surfaces tiebreak surprises before committing pairings.

3. **Forfeit vs withdraw semantic difference (A5)**
   - What we know: both write `tournament_participants.status` to a non-active value.
   - What's unclear: do admins expect different effects?
   - Recommendation: surface as a tooltip on each action; document in i18n description; ship identical forward semantics.

4. **Tournament organiser role (A8)**
   - What we know: D-012 says admins manage everything.
   - What's unclear: does "limited tournament organiser" exist in round 1, or is everything admin-only?
   - Recommendation: ship admin-only for v1 (matches D-012); add organiser tier in v2.

5. **Bot integration kind enums (Phase 5 outbox extension)**
   - What we know: Phase 5 outbox supports `match_announce`, `match_announce_update`, `role_sync`.
   - What's unclear: does Phase 6 want `tournament_announce`, `tournament_status_update`, `bracket_result_announce` as distinct kinds, or one umbrella?
   - Recommendation: distinct kinds — different payload shapes, easier to extend per-kind logic in the bot worker.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.4 in `web` container | All Phase 6 PHP code | ✓ | 8.4.x `[VERIFIED: D-021 + Phase 5 verification]` | — |
| Postgres 16 in `postgres` container | All migrations + queries | ✓ | 16.x `[VERIFIED: Phase 1 verification]` | — |
| Redis 7 in `redis` container | Horizon queue (for SyncDiscordRolesJob; not directly needed by Phase 6 services) | ✓ | 7.x `[VERIFIED: Phase 1 verification]` | — |
| Node 22 in `web` container | Vite build + pnpm | ✓ | 22.x `[VERIFIED: Phase 1 plan 01-06 + Phase 5 plan 05-08]` | — |
| Filament v3.3.50 | TournamentResource + RelationManagers | ✓ | v3.3.50 `[VERIFIED: composer show 2026-05-13]` | — |
| spatie/laravel-data 4.22.1 | DTOs + TS generation | ✓ | 4.22.1 `[VERIFIED: composer show 2026-05-13]` | — |
| pcntl extension | Concurrency Pest tests (BracketMatchMaterialiser race + StandingsCalculator concurrent recalc) | ✓ | present `[VERIFIED: D-04-01-C + Phase 4 plan 04-06 concurrency test]` | Dual-DB-connection fallback (Phase 4 plan 04-06 documented; unused) |
| spatie/laravel-typescript-transformer pipeline | api.d.ts emission | ✓ | ^3.0 `[VERIFIED: Phase 1 plan 01-15]` | — |

**Missing dependencies with no fallback:** None — Phase 6 ships zero new vendor deps.

**Missing dependencies with fallback:** None.

---

## Validation Architecture

Phase 6 honours `workflow.nyquist_validation: true` (config verified). Wave 0 RED stubs precede every implementation wave; per-task command + full-suite Pest gate at every wave boundary.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest ^4.7 (web) + Vitest (bot — minimal use here, only if Phase 5 bot embed builder needs extending for tournament_announce) |
| Config file | `apps/web/tests/Pest.php` (auto-wires `TestCase::class` + `RefreshDatabase::class` to Feature suite) |
| Quick run command | `docker compose exec web ./vendor/bin/pest --filter='<TestClassFragment>' --no-coverage` |
| Full suite command | `docker compose exec web ./vendor/bin/pest --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-success-tournament-end-to-end | 8-clan single-elim end-to-end | feature | `pest --filter='TournamentEndToEnd'` | ❌ Wave 0 (target: `tests/Feature/Tournaments/TournamentEndToEndTest.php`) |
| REQ-success-tournament-end-to-end | Bracket generator: single-elim 8 participants with byes for 5/6/7 | unit/feature | `pest --filter='BracketGeneratorSingleElimination'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Bracket generator: double-elim 8 participants | unit/feature | `pest --filter='BracketGeneratorDoubleElimination'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Bracket generator: round-robin 8 participants | unit/feature | `pest --filter='BracketGeneratorRoundRobin'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Bracket generator: swiss 8 participants × 3 rounds | unit/feature | `pest --filter='BracketGeneratorSwiss'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | BracketAdvancementService propagates winner_participant_id on MatchResult write | feature | `pest --filter='BracketAdvancement'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | StandingsCalculatorService computes Buchholz correctly | feature | `pest --filter='StandingsCalculatorService'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | TournamentStatusService state machine (6 transitions) | feature | `pest --filter='TournamentStatusService'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | TournamentSeedingService (by_rank / random / manual) | feature | `pest --filter='TournamentSeedingService'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Filament admin actions (seed/reseed/start/forfeit/withdraw/recalculate) | feature | `pest --filter='TournamentReseedAction\|TournamentForfeitAction\|TournamentWithdrawAction\|TournamentRecalculateStandings'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | TournamentObserver: Event sync + outbound writer | feature | `pest --filter='TournamentObserver'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | MatchResultObserver triggers BracketAdvancement | feature | `pest --filter='MatchResultObserver'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Public TournamentShow Inertia page (5 tabs) + JSON polling endpoint | feature | `pest --filter='TournamentShowPage\|TournamentPublicJsonController'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Audit log on all 5 new models + actions | feature | `pest --filter='TournamentAuditLog'` | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | i18n key coverage on all 4 formats × surfaces | feature | `pest --filter='TournamentI18nKeyCoverage'` (similar to Phase 5 BotI18nKeyCoverageTest) | ❌ Wave 0 |
| REQ-success-tournament-end-to-end | Vue page renders + bracket SVG layout computes (vue-tsc + headless test) | unit/feature | `vue-tsc --noEmit` (no Pest equivalent — relies on TS strict + integration via Inertia smoke test) | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `pest --filter='<task-relevant-class>' --no-coverage` (typically <5s)
- **Per wave merge:** `pest --filter='Tournament|Bracket|Standings' --no-coverage` (Phase 6 narrow band, ~10s after full suite scales to Phase 6 size)
- **Phase gate:** Full suite green: `pest --no-coverage` + `pint --test` + `phpstan analyse` + `vue-tsc --noEmit` + `shared-types typecheck` before `/gsd-verify-work`.

### Wave 0 Gaps

Phase 6 ships ~20–25 NEW test files. Wave 0 RED stubs needed:

- [ ] `tests/Feature/Models/TournamentModelTest.php`
- [ ] `tests/Feature/Models/TournamentParticipantModelTest.php`
- [ ] `tests/Feature/Models/TournamentStageModelTest.php`
- [ ] `tests/Feature/Models/TournamentBracketModelTest.php`
- [ ] `tests/Feature/Models/TournamentStandingModelTest.php`
- [ ] `tests/Feature/Services/TournamentStatusServiceTest.php`
- [ ] `tests/Feature/Services/TournamentSeedingServiceTest.php`
- [ ] `tests/Feature/Services/BracketGeneratorSingleEliminationTest.php`
- [ ] `tests/Feature/Services/BracketGeneratorDoubleEliminationTest.php`
- [ ] `tests/Feature/Services/BracketGeneratorRoundRobinTest.php`
- [ ] `tests/Feature/Services/BracketGeneratorSwissTest.php`
- [ ] `tests/Feature/Services/BracketMatchMaterialiserServiceTest.php`
- [ ] `tests/Feature/Services/BracketAdvancementServiceTest.php`
- [ ] `tests/Feature/Services/StandingsCalculatorServiceTest.php`
- [ ] `tests/Feature/Observers/TournamentObserverTest.php`
- [ ] `tests/Feature/Observers/MatchResultObserverTest.php`
- [ ] `tests/Feature/Admin/TournamentResourceTest.php`
- [ ] `tests/Feature/Admin/TournamentSeedActionTest.php`
- [ ] `tests/Feature/Admin/TournamentReseedActionTest.php`
- [ ] `tests/Feature/Admin/TournamentForfeitActionTest.php`
- [ ] `tests/Feature/Admin/TournamentWithdrawActionTest.php`
- [ ] `tests/Feature/Admin/TournamentRecalculateStandingsTest.php`
- [ ] `tests/Feature/Admin/TournamentAuditLogTest.php`
- [ ] `tests/Feature/Tournaments/TournamentShowPageTest.php`
- [ ] `tests/Feature/Tournaments/TournamentIndexPageTest.php`
- [ ] `tests/Feature/Tournaments/TournamentPublicJsonControllerTest.php`
- [ ] `tests/Feature/Tournaments/TournamentEndToEndTest.php` (SC capstone)
- [ ] `tests/Feature/I18n/TournamentI18nKeyCoverageTest.php`
- [ ] `tests/Unit/Data/TournamentDataTest.php`
- [ ] `tests/Unit/Data/PublicTournamentDataTest.php`
- [ ] `tests/Unit/Data/BracketNodeDataTest.php`
- [ ] `tests/Unit/Data/BracketEdgeDataTest.php`
- [ ] Factory stubs: `database/factories/{Tournament,TournamentParticipant,TournamentStage,TournamentBracket,TournamentStanding}Factory.php`

Framework install: none — Pest 4 + Filament + spatie all present.

---

## Security Domain

Phase 6 ships under `security_enforcement: true` (config verified). ASVS Level 1 baseline applies.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes (admin actions) | Discord OAuth (Phase 1 D-002 + plan 01-09); session + Filament panel guard |
| V3 Session Management | yes | Laravel default session driver; `SameSite=Lax`, `HttpOnly`, `Secure` in prod (CLAUDE.md §6) |
| V4 Access Control | yes | spatie/laravel-permission (`tournament.view`, `tournament.manage`, `tournament.audit`); Filament policies on `TournamentResource` |
| V5 Input Validation | yes | spatie/laravel-data DTOs + Laravel FormRequest validation; HasTranslations JSONB columns validated per-locale |
| V6 Cryptography | no | Phase 6 does not introduce new secrets; reuses session + Sanctum from Phase 5 |
| V7 Error Handling | yes | Custom DomainException subclasses (e.g., `TournamentStatusInvalidTransitionException`, `BracketsAlreadyGeneratedException`); never leak stack traces; i18n error messages |
| V8 Data Protection | yes (PII in standings via PlayerPrivacyGate) | `PublicTournamentData::fromModel($t)` uses `PlayerPrivacyGate` for MVP displays (D-018) |
| V11 Business Logic | yes | Status state machine + row-locked tournament + bracket-match capacity (inherits D-010) |
| V13 API | yes (JSON polling endpoint) | Rate limiting via Laravel `throttle:60,1` on `/tournaments/{slug}.json`; Etag prevents amplification |

### Known Threat Patterns for Phase 6 (Laravel 12 + Filament v3 + Inertia v2)

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| SQL injection via slug param | Tampering | Laravel route model binding + Eloquent query builder (no raw SQL with user input); `tournaments.slug` validated against `^[a-z0-9-]+$` regex on create |
| Unauthorised tournament state change | Elevation of Privilege | All status transitions through `TournamentStatusService`; spatie permission `tournament.manage` required by Filament policy |
| Mass assignment on Tournament create | Tampering | `protected $fillable` explicit list; `is_public` flag is admin-only via Filament form gating |
| Privacy bypass on standings page | Information Disclosure | `PublicTournamentData::fromModel` applies `PlayerPrivacyGate` for any user-rendered MVP/player display |
| Bracket advancement race producing inconsistent state | Tampering | `DB::transaction` + `lockForUpdate` on parent Tournament row (Pitfall 4 + Pitfall 6 mitigations) |
| CSRF on admin actions | Tampering | Inertia + Laravel CSRF (already wired Phase 1 plan 01-09) |
| JSON polling endpoint DoS | Denial of Service | Laravel `throttle` middleware + Etag (304 on unchanged) reduces server cost on hot polling |
| Discord outbound spoofing | Spoofing | Reuses Phase 5 outbox + bot Sanctum scoped token + `X-Bot-Acts-As-User` (D-002 chain) |
| Tournament settings JSONB injection | Tampering | Validate `settings` JSONB shape via spatie/laravel-data DTO before write; whitelist keys per format |
| Audit log tampering | Repudiation | LogsActivity is append-only; Filament audit page exposes no edit/delete (CLAUDE.md §6) |
| Forfeit/withdraw bypass | Elevation of Privilege | Spatie permission required; participant `status` write goes through service that audits |

**Block-on threshold:** `security_block_on: high`. Phase 6 has no `high`-severity novel surfaces (auth and CSRF are inherited from Phase 1; outbound is inherited from Phase 5). Standard plan-checker review for medium-severity items.

---

## Sources

### Primary (HIGH confidence)

- Context7 `/drarig29/brackets-manager.js` — 80 code snippets, source reputation High, benchmark 89.5. Bracket data model, seed ordering methods (inner_outer, natural, half_shift, etc.), final standings API, swiss support.
- Context7 `/websites/drarig29_github_io_brackets-docs` — 899 code snippets, source reputation High, benchmark 77.1. Glossary, structure of single/double elimination, bye placement.
- `apps/web/composer.json` (read 2026-05-13) — pinned versions for laravel/framework ^12.0, filament/filament ^3.3, spatie/laravel-data ^4.22, etc.
- `docker compose exec web composer show filament/filament` (run 2026-05-13) — exact version v3.3.50.
- `docker compose exec web composer show spatie/laravel-data` (run 2026-05-13) — exact version 4.22.1.
- `apps/web/app/Models/GameMatch.php` + `apps/web/app/Models/Event.php` + `apps/web/app/Services/MatchSlotMaterialiserService.php` + `apps/web/app/Observers/MatchObserver.php` (read 2026-05-13) — Phase 4 + Phase 5 patterns for direct reuse / mirror.
- `.planning/phases/04-matches-manual/04-PHASE-VERIFICATION.md` + `.planning/phases/05-discord-bot-v1/05-PHASE-VERIFICATION.md` (read 2026-05-13) — Phase 4/5 D-### bindings (D-04-03-A, D-04-12-A, etc.).
- `CLAUDE.md` + `.planning/PROJECT.md` (read 2026-05-13) — D-001..D-021 LOCKED decisions.
- `.planning/STATE.md` (read 2026-05-13) — current platform state + canonical binding affirmations.

### Secondary (MEDIUM confidence)

- [Wikipedia — Swiss-system tournament](https://en.wikipedia.org/wiki/Swiss-system_tournament) — Buchholz tiebreak definition, pairing rules, round count formula.
- [Wikipedia — Round-robin tournament](https://en.wikipedia.org/wiki/Round-robin_tournament) — circle method, (N-1) rounds × (N/2) matches/round formula.
- [Wikipedia — Double-elimination tournament](https://en.wikipedia.org/wiki/Double-elimination_tournament) — Burton variant, W/L bracket structure, grand final reset.
- [Brackets Ninja — Single-Elimination Brackets](https://www.bracketsninja.com/types/single-elimination-bracket) — bye distribution rule (never cluster byes).
- [w3tutorials — Tournament Bracket Placement Algorithm](https://www.w3tutorials.net/blog/tournament-bracket-placement-algorithm/) — seeding pseudocode.
- [Striveon — Swiss Tournament Generator](https://joinstriveon.com/blog/swiss-tournament-bracket-generator) — Buchholz tiebreaker, modified Buchholz variants.
- [Brackets Ninja — Understanding Double-Elimination](https://www.bracketsninja.com/blog/understanding-double-elimination-brackets) — L-bracket minor/major stage structure.
- [Abhijeet Krishnan — Double Elimination Bracket Maths](https://abhijeetkrishnan.me/technical/double-elim-bracket-maths/) — round count formula `R = 2*(ceil(lg N) - 1) + 2`.
- [Filament docs — Actions](https://filamentphp.com/docs/2.x/tables/actions) (note: this hit was v2; v3 docs at https://filamentphp.com/docs/3.x/...) — `requiresConfirmation()` pattern.

### Tertiary (LOW confidence — needs validation if relied upon)

- [Medium — Creating Swiss-style Tournament Manager Part 1](https://medium.com/@jsw.tan1991/creating-a-swiss-style-tournament-manager-part-1-match-making-4d01d7cfdeaa) — blog post; algorithm sketch is fine but no library code.
- [Rosetta Code — Round-robin tournament schedule](https://rosettacode.org/wiki/Round-robin_tournament_schedule) — multi-language reference impls; useful for cross-checking PHP impl.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| Standard stack (zero new packages) | HIGH | Versions verified via composer show in-container 2026-05-13; identical to Phase 1–5 stack with 618+117 passing tests |
| Architecture patterns (services, observers, outbox) | HIGH | Direct mirror of Phase 4/5 idioms — `TournamentStatusService` ≅ `MatchStatusService` (D-04-04), `TournamentObserver` ≅ `MatchObserver` (D-04-08-A/B + D-05-05), etc. |
| Schema design (5 tables) | HIGH | Standard tournament data model; cross-referenced against brackets-manager.js Context7 (data layer storage shape) |
| Single-elim algorithm | HIGH | inner_outer ordering verified via Context7 brackets-manager.js docs; Wikipedia confirms mirror/fold |
| Round-robin algorithm | HIGH | Circle method documented in Wikipedia + Rosetta Code |
| Swiss algorithm | MEDIUM | Buchholz tiebreak is documented but the never-paired-before backtrack edge cases (Pitfall 5) need careful implementation; will rely on test vectors |
| Double-elim algorithm | MEDIUM | Burton variant is documented but L-bracket drop chain mapping is non-trivial; cross-check against brackets-manager.js output |
| Filament v3 Action with confirmation | HIGH | Context7 docs (v3 specific) + Phase 4 plan 04-09 already uses this pattern |
| Vue + SVG bracket renderer | MEDIUM | No vendor lib; ~200 LOC hand-roll; layout math is straightforward but visual polish iteration likely needed |
| 30s JSON polling | HIGH | Laravel native `response()->json()->setEtag()`; standard HTTP/1.1 If-None-Match |
| Pitfalls catalogue | HIGH (Pitfalls 1, 2, 3, 7, 8 → Phase 4/5 inherited patterns); MEDIUM (Pitfalls 4, 5, 6, 9, 11, 12 → algorithm specifics) | Inherited pitfalls have proven mitigations; algorithm-specific pitfalls are reasoned from the algorithm definitions |
| i18n + audit (D-013 + D-012) | HIGH | Phase 4–5 idioms transferred verbatim; `NoHardcodedStringsTest` + `LogsActivity` already in place |

**Research date:** 2026-05-13
**Valid until:** 2026-06-12 (30 days — stack is stable; algorithm references are timeless)
