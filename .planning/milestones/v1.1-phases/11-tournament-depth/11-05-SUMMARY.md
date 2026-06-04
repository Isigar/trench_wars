---
phase: 11-tournament-depth
plan: 05
subsystem: tournaments
tags: [vue, typescript, shared-types, i18n, phase-close, buchholz, standings]

requires:
  - phase: 11-tournament-depth
    plan: 02
    provides: "TournamentStandingData.median_buchholz DTO field; SwissStandingsCalculator writes the column"
  - phase: 11-tournament-depth
    plan: 03
    provides: "BracketAdvancementService Elo hook + Swiss auto-advance (all GREEN)"
  - phase: 11-tournament-depth
    plan: 04
    provides: "BracketMatchMaterialiserService stage override + StagesRelationManager Select"

provides:
  - "StandingsTable.vue: showMedianBuchholz computed (format === 'swiss') + swiss-only th/td"
  - "tournaments.standings.tiebreak_median_buchholz i18n label"
  - "TournamentStandingData.median_buchholz: number in api.d.ts (typescript:transform regen)"
  - "packages/shared-types/src/api.d.ts synced from apps/web"
  - "11-PHASE-VERIFICATION.md: SC-1..4 → passing test traceability"
  - "REQUIREMENTS.md TOUR-01..04 Complete; ROADMAP Phase 11 5/5 Complete"

affects:
  - phase-12
  - public /tournaments/{slug} page (Swiss standings now shows median column)

tech-stack:
  added: []
  patterns:
    - "showMedianBuchholz computed<boolean> — swiss-only column gate (v-if on th + td)"
    - "row.median_buchholz direct typed access (no as-any cast) after shared-types regen"
    - "typescript:transform + sync-types.sh sync pattern (plan 01-15 idiom reused)"

key-files:
  created:
    - ".planning/phases/11-tournament-depth/11-PHASE-VERIFICATION.md"
  modified:
    - "apps/web/resources/js/components/tournaments/StandingsTable.vue"
    - "apps/web/lang/en/tournaments.php"
    - "apps/web/resources/js/types/api.d.ts"
    - "packages/shared-types/src/api.d.ts"
    - ".planning/ROADMAP.md"
    - ".planning/REQUIREMENTS.md"

key-decisions:
  - "D-11-05-A: row.median_buchholz accessed directly (typed number after regen) — no as-any cast per plan environment instruction"
  - "D-11-05-B: showMedianBuchholz computed<boolean> added alongside tiebreakLabel (swiss-only gate for both <th> and <td>)"

patterns-established:
  - "Phase-close shared-types regen idiom: typescript:transform in-container → sync-types.sh host-side"

requirements-completed: [TOUR-01, TOUR-02, TOUR-03, TOUR-04]

duration: ~18min
completed: 2026-06-04
---

# Phase 11 Plan 05: Public median Buchholz + shared-types regen + phase close Summary

**Median Buchholz column on public Swiss standings (typed field, t() header), shared-types regenerated with median_buchholz, full phase-close gate suite green (1365 tests / 4802 assertions), TOUR-01..04 traced to passing tests**

## Performance

- **Duration:** ~18 min
- **Started:** 2026-06-04T11:46:35Z
- **Completed:** 2026-06-04T12:04Z
- **Tasks:** 2
- **Files modified:** 6 (+ 1 created)

## Accomplishments

### Task 1: Median Buchholz column + shared-types regen

- `StandingsTable.vue`: `showMedianBuchholz = computed<boolean>(() => props.format === 'swiss')` added alongside `tiebreakLabel`; `<th v-if="showMedianBuchholz">` with `t('tournaments.standings.tiebreak_median_buchholz')` header; `<td v-if="showMedianBuchholz">{{ row.median_buchholz }}</td>` — direct typed access (no `as any` cast)
- `lang/en/tournaments.php`: `'tiebreak_median_buchholz' => 'Median Buchholz'` added after `tiebreak_buchholz`
- `typescript:transform` regenerated `apps/web/resources/js/types/api.d.ts` — `TournamentStandingData.median_buchholz: number` now present
- `packages/shared-types/src/api.d.ts` synced via `sync-types.sh`
- `vue-tsc --noEmit` PASS (no output)

### Task 2: Phase-close full gate suite + TOUR traceability

- `make artisan migrate:fresh --seed`: PASS (all migrations + all seeders)
- `make pest` full suite: **1365 passed** (4802 assertions), 0 failed, 87.39s
- `make pint --test`: PASS (674 files clean)
- `make phpstan`: [OK] No errors
- `vue-tsc --noEmit`: PASS
- `cd apps/bot && vitest run`: 190 passed (15 files), 961ms
- `cd apps/bot && tsc --noEmit`: PASS
- `cd apps/bot && eslint .`: PASS
- `11-PHASE-VERIFICATION.md` written: SC-1..4 + TOUR-01..04 mapped to named passing tests; idempotency + no-regression evidence included; 2 PENDING_MANUAL_SMOKE items (pixel rendering)
- `ROADMAP.md` Phase 11 row updated: 4/5 → 5/5 Complete, completion date 2026-06-04
- `REQUIREMENTS.md` last-updated note updated to TOUR-01..04 Phase 11 close

## Task Commits

1. **Task 1: Median Buchholz column + lang key + shared-types regen** - `1d710cc` (feat)
2. **Task 2: Phase-close verification + ROADMAP/REQUIREMENTS updates** - `90ca491` (docs)

## Files Created/Modified

- `apps/web/resources/js/components/tournaments/StandingsTable.vue` - MODIFIED: showMedianBuchholz computed + swiss-only th/td
- `apps/web/lang/en/tournaments.php` - MODIFIED: tiebreak_median_buchholz key added
- `apps/web/resources/js/types/api.d.ts` - MODIFIED: regenerated; median_buchholz: number on TournamentStandingData
- `packages/shared-types/src/api.d.ts` - MODIFIED: synced from apps/web
- `.planning/phases/11-tournament-depth/11-PHASE-VERIFICATION.md` - CREATED: SC-1..4 + TOUR-01..04 traceability
- `.planning/ROADMAP.md` - MODIFIED: Phase 11 5/5 Complete
- `.planning/REQUIREMENTS.md` - MODIFIED: last-updated note

## Decisions Made

- **D-11-05-A:** `row.median_buchholz` accessed directly (typed `number` after shared-types regen) — no `as any` cast per plan environment instruction ("do NOT follow the as any snippet").
- **D-11-05-B:** `showMedianBuchholz = computed<boolean>(() => props.format === 'swiss')` matches the plan spec exactly; gates both the `<th>` and `<td>`.

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written. `median_buchholz` was already present in the PHP DTO (`TournamentStandingData.php` line 36) from plan 11-01 and 11-02; `typescript:transform` correctly surfaced it in the generated types.

## Known Stubs

None — `StandingsTable.vue` reads `row.median_buchholz` from real DTO data written by `SwissStandingsCalculator` (plan 11-02). The column renders real computed values, not placeholders.

## Threat Flags

T-11-05-02 (TS types drift from runtime DTO) and T-11-05-03 (null median crashes Vue render) from the plan's threat model are both mitigated:
- T-11-05-02: `typescript:transform` regen + `vue-tsc --noEmit` gate confirmed the field is typed `number`
- T-11-05-03: `showMedianBuchholz` v-if gate fires only for swiss format where the value is always written (DB default 0; never null for swiss rows)

## Self-Check

Verifying files exist:
- `apps/web/resources/js/components/tournaments/StandingsTable.vue` — FOUND
- `apps/web/lang/en/tournaments.php` — FOUND
- `apps/web/resources/js/types/api.d.ts` — FOUND (median_buchholz at line 374)
- `packages/shared-types/src/api.d.ts` — FOUND
- `.planning/phases/11-tournament-depth/11-PHASE-VERIFICATION.md` — FOUND
- `.planning/ROADMAP.md` — FOUND (Phase 11 → 5/5 Complete)
- `.planning/REQUIREMENTS.md` — FOUND

Commits: `1d710cc` (Task 1), `90ca491` (Task 2) — both present in git log

## Self-Check: PASSED

All files exist. Both commits verified.

---
*Phase: 11-tournament-depth*
*Completed: 2026-06-04*
