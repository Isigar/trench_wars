---
phase: 4
slug: matches-manual
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-13
approved: 2026-05-13
---

# Phase 4 — Validation Strategy

> Per-phase validation contract. Approved by autonomous workflow (workflow.skip_discuss=true).

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) — D-001 stack |
| **Config file** | `apps/web/phpunit.xml` |
| **Quick run command** | `make pest ARGS="--filter=Match"` |
| **Full suite command** | `make pest` |
| **Estimated runtime** | ~60s after Phase 4 (current 278 → ~340+ tests) |

## Sampling Rate

- After every task commit: `make pest ARGS="--filter=Match"` (~15s scope)
- After every plan wave: `make pest` (full suite)
- Before phase verification: full suite + Pint + PHPStan + vue-tsc all GREEN
- Max feedback latency: ~60 seconds

## Per-Task Verification Map

> Planner-populated during plan generation. Each task gets an `<automated>` block.

| Plan | Wave | Requirement | Coverage focus |
|------|------|-------------|----------------|
| 04-01 | 0 | REQ-goal-match-workflows | Test scaffolding (factory stubs, Pest RED stubs, i18n keys) |
| 04-02 | 1 | REQ-goal-match-workflows | Migrations (matches, match_slots, match_access_rules, match_results, match_mvps, events) |
| 04-03 | 2 | REQ-goal-match-workflows | Models + factories + relationship tests |
| 04-04 | 2 | REQ-goal-match-workflows | Match status state machine + transition tests |
| 04-05 | 3 | REQ-goal-match-workflows | MatchSlotMaterialiserService (snapshot at create) |
| 04-06 | 3 | REQ-goal-match-workflows | MatchSignupService (D-010 row-locked transactional capacity + tag access rule) |
| 04-07 | 4 | REQ-goal-match-workflows | DTOs (MatchData, MatchSlotData, MatchResultData, EventData) + TS regen |
| 04-08 | 5 | REQ-goal-match-workflows | MatchObserver auto-Event sync (polymorphic) |
| 04-09 | 5 | REQ-goal-match-workflows | MatchResource Filament wizard + RelationManagers + MatchResultService |
| 04-10 | 6 | REQ-goal-match-workflows | Public controllers (MatchCalendarController + MatchShowController) + routes |
| 04-11 | 6 | REQ-goal-match-workflows | Public Vue pages (Matches/Index.vue calendar + Matches/Show.vue detail) |
| 04-12 | 7 | REQ-goal-match-workflows | i18n keys + audit log integration + Filament presence test |
| 04-13 | 8 | REQ-goal-match-workflows | [BLOCKING] Phase verification + ROADMAP + REQUIREMENTS update + final gates |

## Wave 0 Requirements

- [ ] `apps/web/tests/Feature/Phase04/` test directory
- [ ] Factory stubs for the 6 new models
- [ ] i18n key files extended (admin.matches.*, public.matches.*)

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filament wizard end-to-end: officer creates Match selecting HLL Scrim 50v50 → 50 slots materialise → signups open immediately | SC-1 | Filament wizard UI flow | Admin login → Matches → Create → wizard 3 steps → save → verify 50 slot rows + status=open in DB |
| Two players sign up simultaneously to last slot — exactly one succeeds | SC-2 | Concurrency edge case | Two browser windows or curl: POST /matches/{id}/signups concurrently → one returns 201, one returns 409 |
| Public visitor browses /matches calendar; private matches hidden; tag-restricted matches show but signup forbidden for ineligible players | SC-3, SC-5 | UX walkthrough | Logged-out user opens /matches → only public + tag-eligible matches visible |
| Admin enters/overrides match result; activity_log records causer + before/after | SC-4 | Filament admin flow | Match status=played → edit Result via Filament → check activity_log |
| Cancelling a match deletes the corresponding Event row | SC-5 | Observer chain | Cancel match → SELECT * FROM events WHERE eventable_id=match.id → 0 rows |

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies (verified at plan time)
- [x] Wave 0 covers all RED stubs
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true`

**Approval:** approved 2026-05-13 by autonomous workflow.
