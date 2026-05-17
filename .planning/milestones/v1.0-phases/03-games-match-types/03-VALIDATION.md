---
phase: 3
slug: games-match-types
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-13
approved: 2026-05-13
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Approved 2026-05-13 by autonomous workflow (workflow.skip_discuss=true) after gsd-plan-checker verified every task has an `<automated>` verify and Wave 0 covers all stubs.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) — D-001 stack |
| **Config file** | `apps/web/phpunit.xml` (Pest reads PHPUnit config) |
| **Quick run command** | `make pest ARGS="--filter=Game"` |
| **Full suite command** | `make pest` |
| **Estimated runtime** | ~30s (full suite ~45s after Phase 3) |

Container note: D-021 requires all commands inside containers. Use `make` aliases or `docker compose exec web ./vendor/bin/pest ...`.

---

## Sampling Rate

- **After every task commit:** Run `make pest ARGS="--filter=Game"` (~10s scope)
- **After every plan wave:** Run `make pest` (full suite, ~45s)
- **Before phase verification:** Full suite must be green; Pint/PHPStan L8 clean
- **Max feedback latency:** ~45 seconds

---

## Per-Task Verification Map

> Per-plan tasks map to automated verify commands. Each Wave 0 stub is filled in by its owning plan (RED → GREEN transition).

| Plan | Task | Wave | Requirement | Test Type | Automated Command | Status |
|------|------|------|-------------|-----------|-------------------|--------|
| 03-01 | T1 (factory + i18n stubs) | 0 | REQ-platform-vision | scaffold | `make pest ARGS="--filter=Phase03"` (RED stubs exist) | ⬜ pending |
| 03-01 | T2 (Pest stub set) | 0 | REQ-platform-vision | scaffold | `make pest ARGS="--filter=Phase03"` (RED expected) | ⬜ pending |
| 03-02 | T1 (4 migrations) | 1 | REQ-platform-vision | migration | `make artisan ARGS="migrate:fresh"` exits 0 | ⬜ pending |
| 03-02 | T2 (UNIQUE + CHECK + cascade tests) | 1 | REQ-platform-vision | unit | `make pest ARGS="--filter=GameMigration"` | ⬜ pending |
| 03-03 | T1 (4 models) | 2 | REQ-platform-vision | unit | `make pest ARGS="--filter=GameModel"` | ⬜ pending |
| 03-03 | T2 (factories + relationship tests) | 2 | REQ-platform-vision | unit | `make pest ARGS="--filter=GameModel"` | ⬜ pending |
| 03-03 | T3 (cross-game saving listener) | 2 | REQ-platform-vision | unit | `make pest ARGS="--filter=CrossGameGuard"` | ⬜ pending |
| 03-04 | T1 (4 DTOs) | 2 | REQ-platform-vision | unit | `make phpstan` clean | ⬜ pending |
| 03-04 | T2 (TS regen + GameDataTest GREEN) | 2 | REQ-platform-vision | unit | `make pest ARGS="--filter=GameDataTest"` GREEN | ⬜ pending |
| 03-05 | T1 (GameSeeder + DatabaseSeeder) | 3 | REQ-platform-vision | seeder | `make artisan ARGS="migrate:fresh --seed"` + count check | ⬜ pending |
| 03-05 | T2 (idempotency test) | 3 | REQ-platform-vision | feature | `make pest ARGS="--filter=GameSeederIdempotent"` | ⬜ pending |
| 03-06 | T1 (GameResource + 4 Pages) | 4 | REQ-platform-vision | feature | `make pest ARGS="--filter=GameResourcesPresent"` partial GREEN | ⬜ pending |
| 03-06 | T2 (Roles + MatchTypes RMs) | 4 | REQ-platform-vision | feature | `make pest ARGS="--filter=GameResourceRM"` | ⬜ pending |
| 03-07 | T1 (GameMatchTypeResource + Pages) | 5 | REQ-platform-vision | feature | `make pest ARGS="--filter=GameMatchTypeResource"` | ⬜ pending |
| 03-07 | T2 (RoleLimitsRelationManager) | 5 | REQ-platform-vision | feature | `make pest ARGS="--filter=RoleLimitsRM"` | ⬜ pending |
| 03-07 | T3 (Rule-2 amendment to MatchTypesRM) | 5 | REQ-platform-vision | feature | `make pest ARGS="--filter=MatchTypesRMNavigation"` | ⬜ pending |
| 03-08 | T1 (admin presence + 403 test) | 6 | REQ-platform-vision | feature | `make pest ARGS="--filter=GameResourcesPresent"` GREEN | ⬜ pending |
| 03-09 | T1 (admin.php i18n keys) | 6 | REQ-platform-vision | static | `make pest ARGS="--filter=NoHardcodedStrings"` | ⬜ pending |
| 03-09 | T2 (audit log integration test) | 6 | REQ-platform-vision | feature | `make pest ARGS="--filter=GameAuditLog"` | ⬜ pending |
| 03-10 | T1 (PHASE-VERIFICATION.md) | 7 | REQ-platform-vision | docs | `test -f .planning/phases/03-games-match-types/03-PHASE-VERIFICATION.md` | ⬜ pending |
| 03-10 | T2 (ROADMAP + REQUIREMENTS update) | 7 | REQ-platform-vision | docs | `make pest && make pint --test && make phpstan` all GREEN | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `apps/web/tests/Feature/Phase03/` directory created with skeleton RED tests
- [ ] `apps/web/database/factories/GameFactory.php` stub
- [ ] `apps/web/database/factories/GameRoleFactory.php` stub
- [ ] `apps/web/database/factories/GameMatchTypeFactory.php` stub
- [ ] `apps/web/database/factories/GameMatchTypeRoleLimitFactory.php` stub
- [ ] `apps/web/lang/en/admin.php` extended with `admin.games.*` + `admin.game_match_types.*` placeholder keys
- [ ] Existing Pest infrastructure covers Phase 3 (no new framework install needed — Pest 4 + PHPUnit shipped Phase 1)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filament UI end-to-end smoke — admin creates Game → adds GameRoles → creates GameMatchType → sets capacity matrix via Filament Relation Managers | SC-1, SC-2 | UI flow inside Filament panel; no headless browser tests in P1 (same handoff pattern as Phase 1/2) | Log in as admin → /admin/games → Create HLL or verify seeded row → click 15 roles → /admin/game-match-types → create Scrim 50v50 → set capacities per role → save → re-open and confirm persistence |
| HLL seeder produces 15 roles + 5 match types on fresh DB; admin edits are preserved on second seed run (idempotency) | SC-3 | Seeder idempotency best smoked manually via `make artisan ARGS="migrate:fresh --seed"` then a second `make artisan ARGS="db:seed"` after manual edit | After fresh migrate+seed, open Filament → Games → HLL → expect 15 roles + 5 match types. Edit one role's display_name. Re-run `db:seed`. Verify the edit is preserved (firstOrCreate did not overwrite). |
| Adding a new game requires zero code changes (D-007 / SC-4) | SC-4 | Operator-facing verification of the platform's core value proposition | Admin clicks "Create Game" → fills key="cs2" + name="CS2" + adds 3 roles + 1 match type with capacity → save → no migration, no deploy required |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies (verified by gsd-plan-checker 2026-05-13)
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (7 stubs in plan 03-01)
- [x] No watch-mode flags
- [x] Feedback latency < 60s (full suite ~45s)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-05-13 by autonomous workflow (gsd-plan-checker verified all 21 tasks have `<automated>` blocks).
