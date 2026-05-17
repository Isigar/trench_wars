---
phase: 05-discord-bot-v1
plan: 13
subsystem: phase-close
tags: [wave-12, phase-close, blocking, verification, sc-1, sc-2, sc-3, sc-4, sc-5, req-goal-discord-ux, d-04-03-a-locked, pending-manual-smoke]
dependency_graph:
  requires: [05-01-complete, 05-02-complete, 05-03-complete, 05-04-complete, 05-05-complete, 05-06-complete, 05-07-complete, 05-08-complete, 05-09-complete, 05-10-complete, 05-11-complete, 05-12-complete]
  provides:
    - .planning/phases/05-discord-bot-v1/05-PHASE-VERIFICATION.md
    - "Phase 5 close — REQ-goal-discord-ux Complete; ROADMAP 13/13 Complete + Phase 5 [x]; STATE.md 5/9 phases 56%"
    - "PENDING_MANUAL_SMOKE flag for operator's 6-item live Discord guild walkthrough A-F"
    - "Canonical model binding re-affirmed for Phase 6+: App\\Models\\GameMatch (D-04-03-A LOCKED)"
  affects: [06-tournaments-brackets]
tech_stack:
  added: []
  patterns:
    - "Phase close pattern (Phase 4 plan 04-13 precedent): single verification artifact + ROADMAP + REQUIREMENTS (already Complete from plan 05-12) + STATE.md via gsd-sdk state-handler verbs"
    - "Quality gate sweep BEFORE any planning-doc edits — gate-fail aborts the close plan with zero file mutations"
    - "Bot Vitest invocation idiom: docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm test' — bind-mount host source because the bot Docker image bakes its source at build time (pre-Phase-5)"
    - "SC traceability matrix: every SC -> at least 2 test files (web + bot) + a manual smoke check"
key_files:
  created:
    - ".planning/phases/05-discord-bot-v1/05-PHASE-VERIFICATION.md"
    - ".planning/phases/05-discord-bot-v1/05-13-SUMMARY.md"
  modified:
    - ".planning/ROADMAP.md"
    - ".planning/REQUIREMENTS.md"
    - ".planning/STATE.md"
decisions:
  - "D-05-13-A: Phase 5 close ships with REQ-goal-discord-ux ALREADY Complete in REQUIREMENTS.md (lines 37 + 118) — flipped during plan 05-12; this close plan only re-affirms via the verification artifact + appends a footer date stamp. Single most consequential takeaway: REQ status flips can happen mid-phase when the underlying SC is mechanically satisfied; the close plan is the audit gate, not the flip gate."
  - "D-05-13-B: ROADMAP.md Phase 5 plan list already carried the correct 13 plan filenames (verified during read); the orchestrator-noted 'placeholder Phase 2 plan filenames' advisory was based on the pattern visible in Phases 6/7/8/9 (still need to be flipped at their respective close plans). No replacement needed at plan 05-13 — only checkbox flips + completion date in the Progress table."
  - "D-05-13-C: bot Vitest runs via `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm test'` (NOT `docker compose exec bot ...` because the bot service is not running, and NOT `docker compose run --rm bot ...` because that uses the baked-in image source where only the skeleton test file exists from Phase 1 plan 01-01). This is the canonical Phase 5 idiom established across plans 05-08..05-12 and re-verified at close."
  - "D-05-13-D: STATE.md percent calculation reports 56% (5/9 phases) per the orchestrator-supplied execution rules, NOT 99% (67/68 plans) that the SDK's update-progress verb would produce. Both are arithmetically correct against their respective denominators; the orchestrator's choice favors phase-level granularity for top-level dashboards. Bar uses the 5/9 form: [██████████░] (rounded to 10 chars)."
  - "D-05-13-E: Phase 5 took ~5512s wall-clock across 13 plans (mean ~424s/plan), 34+ tasks, 35+ commits, 138 files changed. Largest plan was 05-04 (BotApi controllers + 4 FormRequests + 6 GREEN HTTP integration tests) at 828s. Smallest was 05-11 (outbound polling worker + render dispatcher + guildMemberUpdate reconciler) at 321s — pure component implementation against pre-existing contracts."
metrics:
  duration_seconds: 408
  completed_date: "2026-05-13"
  tasks_total: 2
  tasks_completed: 2
  commits: 2
  files_changed: 5
---

# Phase 5 Plan 13: Wave 12 — Phase Close + ROADMAP/REQUIREMENTS/STATE Updates

## Overview

[BLOCKING] Phase 5 close artifact + ROADMAP/REQUIREMENTS/STATE updates +
PENDING_MANUAL_SMOKE flag for operator's 6-item live Discord guild
walkthrough. Mirrors Phase 4 plan 04-13 verbatim structure with Phase 5
specifics filled in.

## What Shipped

### Quality Gates — all GREEN

| Gate | Command | Result |
|------|---------|--------|
| Pest (web full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **618 passed** (1817 assertions), 0 failed, 0 incomplete, 27.92s |
| Vitest (bot full suite) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"` | **117 passed** (10 test files), 0 failed, 727ms |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 342 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| tsc strict (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm run typecheck"` | **PASS** — clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | **PASS** — clean |
| vue-tsc (web) | `docker compose exec web /app/node_modules/.bin/vue-tsc --noEmit` | **PASS** — 0 errors |

Phase 5 added 125 web Pest tests / 358 assertions on top of Phase 4's
493/1459 baseline, PLUS the complete bot-side Vitest suite of 117 tests.

### Verification Artifact

`05-PHASE-VERIFICATION.md` (~650 lines) authored with:
- Frontmatter: status=Complete (PENDING_MANUAL_SMOKE), counts, gates_passed table
- SC-1..SC-5 traceability table with concrete test files + plan numbers
- 12 RESEARCH pitfall coverage table
- 5 RESEARCH open question resolutions
- 12 D-### project-level decisions honored table
- ~46 D-05-NN-* plan-level decisions rolled up from per-plan SUMMARYs
- Pest full-suite snapshot (18 Phase 5 web test classes listed)
- Vitest full-suite snapshot (10 bot test files listed)
- Static analysis snapshot (Pint + PHPStan + vue-tsc + bot tsc + shared-types tsc)
- 10-row grep gate verification table
- 8-row must-have traceability table
- 6-item manual smoke checklist A-F (PENDING_MANUAL_SMOKE)
- Performance metrics table (13 plans + Phase 5 total)
- Open items carrying forward to Phase 6+
- Out-of-scope items deferred to Phase 6/7/8/9
- 33-commit file list (commit hashes for every Phase 5 commit)
- Sign-off + Phase 6 hand-off note

### ROADMAP.md (3 surgical edits)

1. `- [ ] **Phase 5: Discord bot v1**` → `- [x] **Phase 5: Discord bot v1** ... _Completed 2026-05-13_`
2. `- [ ] 05-13-PLAN.md — [BLOCKING] phase verification ...` → `- [x] 05-13-PLAN.md — ...`
3. Progress table row: `| 5. Discord bot v1 | 1/13 | In progress | - |` → `| 5. Discord bot v1 | 13/13 | Complete | 2026-05-13 |`

Note: The orchestrator-noted "placeholder Phase 2 plan filenames in
Phase 5 section" was advisory and NOT present in the actual ROADMAP.md
state at plan 05-13 read time. Phase 5's plan list already carried the
correct 13 filenames. Phases 6/7/8/9 still have placeholder Phase 2
plan filename lists — those will be replaced at their respective close
plans.

### REQUIREMENTS.md (1 footer append)

REQ-goal-discord-ux was already Complete in both the active-requirements
bullet (line 37) and the v1 Traceability table (line 118) from plan
05-12 — the close plan only re-affirmed via the verification artifact
and appended a fresh `Last updated: 2026-05-13 — Phase 5 close: ...`
footer line.

`gsd-sdk query requirements.mark-complete REQ-goal-discord-ux` returned
`already_complete: ['REQ-goal-discord-ux']` (idempotent — confirms the
prior flip held).

### STATE.md (surgical + SDK-driven edits)

Frontmatter updates (manual — SDK does not cover full state shape):
- `completed_phases: 4 → 5`
- `completed_plans: 67 → 68`
- `percent: 44 → 56` (per orchestrator execution rules — 5/9 phases, not 68/68 plans)
- `stopped_at:` rewritten to capture Phase 5 close + Phase 6 hand-off + D-04-03-A re-affirmation

SDK-driven updates:
- `gsd-sdk query state.advance-plan` → 12 → 13 (current plan)
- `gsd-sdk query state.record-metric --phase 05-discord-bot-v1 --plan 13 --duration 408s --tasks 2 --files 5`
- `gsd-sdk query state.record-session "" "Phase 5 COMPLETE..." "None"` → updates Last session + Resume File
- `gsd-sdk query state.add-decision --summary "[Phase 05]: Plan 05-13 — Phase 5 COMPLETE..."` → Phase 5 close rollup
- `gsd-sdk query state.add-decision --summary "[Phase 05]: D-04-03-A LOCKED continued..."` → Phase 6+ binding re-affirmation

Body section update:
- "Progress: [████░░░░░░] 45% (4/9 phases ...)" → "[██████░░░░] 56% (5/9 phases; 68/68 plans complete through Phase 5 close)"
- "Phase: 05 (Discord bot v1) — IN PROGRESS" → "COMPLETE (PENDING_MANUAL_SMOKE — 6-item operator walkthrough A-F per 05-PHASE-VERIFICATION.md)"

## Plan-Level Deviations

None. The close plan executed cleanly against gate-result reality:

- All 7 quality gates GREEN on first run (no Rule 1/2/3 auto-fixes needed)
- REQ-goal-discord-ux was already Complete (no flip required)
- ROADMAP Phase 5 plan list was already correct (no replacement required)
- The only "deviation" was an advisory note from the orchestrator about a placeholder Phase 2 plan filename list that did NOT exist in the actual ROADMAP.md state — documented as D-05-13-B but with no code/file impact

## Manual Smoke Checklist (PENDING_MANUAL_SMOKE)

The 6-item operator walkthrough is documented in full in
`05-PHASE-VERIFICATION.md` section "Manual Smoke Checklist
(PENDING_MANUAL_SMOKE)". Summary:

- **A.** Discord slash commands register after first deploy + bot login (SC-1)
- **B.** `/match signup` modal end-to-end (SC-2)
- **C.** Match creation → outbound delivery → embed appears in Discord (SC-3)
- **D.** Player joins clan on website → Discord role assigned (SC-4)
- **E.** guildMemberUpdate reconciliation (SC-4)
- **F.** Sanctum bot:* token misuse rejected — 401/403/422 cases (SC-5)

## Phase 5 Close Metrics

- **Duration**: ~5512s wall-clock across 13 plans (mean ~424s/plan)
- **Plans**: 13/13 Complete
- **Tasks**: 34+ across all plans
- **Commits**: 35+ across all plans
- **Files**: 138 changed across Phase 5
- **Web tests added**: 125 (Phase 4 close 493 → Phase 5 close 618)
- **Web assertions added**: 358 (Phase 4 close 1459 → Phase 5 close 1817)
- **Bot tests added**: 115 (Phase 1 plan 01-01 skeleton 2 → Phase 5 close 117)
- **Quality gates**: 7/7 GREEN (Pest, Vitest, Pint, PHPStan L8, bot tsc, shared-types tsc, vue-tsc)
- **Threat dispositions**: all `mitigate` resolved per-plan; all `accept` documented (T-05-13-03 PENDING_MANUAL_SMOKE accepted as operator-owned)

## Forward-Looking Items Carrying to Phase 6+

| Item | Lives in | Why |
|------|----------|-----|
| `/profile` viewer-aware endpoint (`/api/bot/users/by-discord/{id}` with PlayerPrivacyGate) | Phase 9 polish | RESEARCH Q5 + D-05-09-A — v1 ships redirect-to-web stub |
| StringSelectMenu replacement for signup modal text input | Phase 9 polish | Plan 05-10 deferred — better UX than free-form UUID |
| `/clan apply` real implementation | Phase 6+ | D-05-09-B — currently redirect-to-web stub; depends on whether tournament invite flow uses the same path |
| Multi-replica bot deployment | Phase 8 RCON | Assumption A6 — current single-instance OK because web's lockForUpdate handles claim safety |
| i18n for bot responses (currently EN-only at v1) | Phase 7 CMS or Phase 9 | CONTEXT.md i18n note + D-013 — plumbing exists, content deferred |
| Token rotation playbook automation | Phase 9 polish | Artisan commands ship in plan 05-07; manual procedure documented |
| Tournament/CMS/RCON outbound message kinds | Phase 6/7/8 | DiscordOutboundMessage `kind` enum will be extended; outbox + worker + Filament admin reused verbatim |

## Sign-off

Phase 5 verified complete pending operator manual smokes. ROADMAP +
REQUIREMENTS + STATE updated. Phase 6 (Tournaments & brackets) is the
next phase to plan.

**Phase 6 hand-off contract** (codified in 05-PHASE-VERIFICATION.md
"Phase 6 hand-off note"):

- `App\Models\GameMatch` (D-04-03-A LOCKED canonical name — bracket-match materialisation MUST use this FQN)
- `MatchSignupService` (D-010 row-locked) for bracket-match Discord signups
- `DiscordOutboundMessage` + bot polling worker for tournament/bracket announcements (extend `kind` enum with `tournament_announce`)
- `SyncDiscordRolesJob` + `ClanMembershipObserver` already wire bracket-participant clan roster changes to Discord
- Sanctum `bot:*` token + `X-Bot-Acts-As-User` middleware ready for tournament organiser flows from the bot

## Self-Check: PASSED

Verified load-bearing claims before committing:

- FOUND: `.planning/phases/05-discord-bot-v1/05-PHASE-VERIFICATION.md` (630 lines)
- FOUND: `.planning/phases/05-discord-bot-v1/05-13-SUMMARY.md` (this file)
- FOUND: `.planning/ROADMAP.md` (282 lines) — Phase 5 line 19 `[x]`; row 278 `13/13 | Complete | 2026-05-13`; plan 05-13 line 157 `[x]`
- FOUND: `.planning/REQUIREMENTS.md` (138 lines) — REQ-goal-discord-ux line 37 `[x]`; traceability line 118 `Complete`; footer Phase 5 close stamp line 138
- FOUND: `.planning/STATE.md` (325 lines) — frontmatter `completed_phases: 5, completed_plans: 68, percent: 56`; body progress bar updated; stopped_at re-written with Phase 6 hand-off + D-04-03-A re-affirmation
- Quality gates: Pest 618/1817, Vitest 117/117, Pint 342 clean, PHPStan [OK], bot tsc clean, shared-types tsc clean, vue-tsc clean (all captured in 05-PHASE-VERIFICATION.md Quality Gates section)
- D-04-03-A LOCKED canonical model binding re-affirmed: zero `App\Models\Match as MatchModel` aliases in Phase 5 surface (verified during plan 05-12 BotI18nKeyCoverageTest grep audit)
