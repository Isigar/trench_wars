# Milestones

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
