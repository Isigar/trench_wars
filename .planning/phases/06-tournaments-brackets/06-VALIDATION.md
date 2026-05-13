---
phase: 6
slug: tournaments-brackets
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-13
approved: 2026-05-13
---

# Phase 6 — Validation Strategy

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (web) + Vitest (bot — minimal) |
| **Quick** | `make pest ARGS="--filter=Tournament"` |
| **Full** | `make pest && make pnpm ARGS="-F @trenchwars/bot test"` |

## Per-Plan Coverage Map (planner-populated)

| Plan | Wave | Focus |
|------|------|-------|
| 06-01 | 0 | Wave 0 — 25+ RED stubs + factories + tournaments.php i18n |
| 06-02 | 1 | Migrations (5 tables: tournaments, participants, stages, brackets, standings) |
| 06-03 | 2 | Models + factories + relationship tests |
| 06-04 | 2 | TournamentStatusService state machine |
| 06-05 | 3 | TournamentSeedingService (rank/random/manual) |
| 06-06 | 3 | BracketGeneratorService single-elim |
| 06-07 | 4 | BracketGeneratorService round-robin + swiss + double-elim |
| 06-08 | 4 | BracketAdvancementService + MatchResultObserver |
| 06-09 | 5 | StandingsCalculatorService (format-specific tiebreakers) |
| 06-10 | 5 | DTOs + TS regen + polymorphic Event sync (TournamentObserver) |
| 06-11 | 6 | TournamentResource Filament + actions (seed/reseed/forfeit/withdraw) |
| 06-12 | 7 | Public controllers + Vue pages + BracketCanvas SVG + 30s polling |
| 06-13 | 8 | i18n + audit + admin presence + bot announce integration |
| 06-14 | 9 | [BLOCKING] Phase verification |

## Wave 0 Requirements

- 5 factory stubs + 25+ RED Pest stubs
- `apps/web/lang/en/tournaments.php` skeleton

## Manual-Only Verifications

| Behavior | Why Manual |
|----------|------------|
| Full single-elim run with 8 clans end-to-end through Filament + public viewing | Operator UX walkthrough |
| Swiss tournament 6-round dry run with Buchholz tiebreaks | Algorithm correctness needs human eyeball |
| Bracket SVG rendering at various counts (4, 7, 8, 16 participants) | Visual layout fidelity |
| Bot announce on bracket creation | Live Discord smoke |

## Validation Sign-Off

- [x] All tasks `<automated>` verify
- [x] Wave 0 RED stubs covers SC matrix
- [x] `nyquist_compliant: true`

**Approval:** 2026-05-13 autonomous workflow.
