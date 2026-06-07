# Milestones

## v1.2 Reachability completion (Shipped: 2026-06-07)

**Phases completed:** 2 phases (13 MEDIUM, 14 LOW), 7 plans

Closes the 13 reachability gaps found by the 2026-06-06 feature-completeness audit — features whose
backend was tested-green but had no reachable entry point. The 5 HIGH gaps were fixed first on the same
branch; this milestone tracks the 7 MEDIUM+LOW fixes.

**Key accomplishments (full branch — 5 HIGH + 7 MED/LOW):**

- HIGH: RCON capture reachable (MatchServerBooking creation on MatchResource); clan-invite accept/decline
  UI; match-dispute raise entry point; article permalinks fixed (/news→/blog dead links); self-service
  privacy editor (/account/privacy).
- MEDIUM: applicant withdraw-application UI; double-elim N≥8 losers-bracket slot-collision fix
  (a correctness bug — the loser overwrote the LB winner); public /players index page (nav + sitemap 404).
- LOW: ban enforcement at the auth layer (bans were audit-only); Filament form-publish stamps
  published_at; MatchPlayerStat admin correction surface; MatchEvent read-only admin view.

40 new tests. All gates green at close: web Pest 1410, bot Vitest 238, rcon-worker Vitest 40, PHPStan L8
clean, Pint clean (692 files), vue-tsc clean. Multi-agent adversarial code review run on the diff.

---

## v1.1 Completion (Shipped: 2026-06-04)

**Phases completed:** 3 phases, 17 plans, 23 tasks

**Key accomplishments:**

- One-liner:
- One-liner:
- `POST /api/bot/clans/{slug}/applications` (201/422 bot endpoint) + `POST /clans/{slug}/apply` (web redirect) with three typed-exception → error-code mappings, both route registrations, and 13 tests across 3 test files.
- One-liner:
- One-liner:
- `ClanShowController` adds three eligibility props (acceptsApplications / viewerIsActiveMember / viewerHasPendingApplication); `Clans/Show.vue` adds a `showApplyBlock`-gated Apply-to-join form posting to `clans.apply`; 9-case feature test covers the full eligibility matrix.
- One-liner:
- Four additive schema columns (elo_rating, rated_at, median_buchholz, game_match_type_id) with model wiring and four RED Pest scaffolds for Phase 11 tournament depth
- EloRatingService (K=32 standard Elo, DB transaction, activity audit) and Buchholz Cut-1 third tiebreaker in SwissStandingsCalculator — two RED scaffolds turned GREEN
- Elo hook (rated_at-guarded), Swiss auto-advance (idempotent + premature-completion guard), and by_rank seeding repointerd to clan elo_rating DESC — all RED scaffolds GREEN, all Phase-6 tests regression-free
- Stage-level `game_match_type_id` override in `BracketMatchMaterialiserService` using `stage override ?? tournament default` + cross-game-scoped Filament Select on StagesRelationManager
- Median Buchholz column on public Swiss standings (typed field, t() header), shared-types regenerated with median_buchholz, full phase-close gate suite green (1365 tests / 4802 assertions), TOUR-01..04 traced to passing tests
- One-liner:
- One-liner:
- One-liner:
- One-liner:
- One-liner:

---
