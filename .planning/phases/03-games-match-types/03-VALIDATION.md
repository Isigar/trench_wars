---
phase: 3
slug: games-match-types
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-13
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) — D-001 stack |
| **Config file** | `apps/web/phpunit.xml` (Pest reads PHPUnit config) |
| **Quick run command** | `make pest ARGS="--filter=Phase03"` |
| **Full suite command** | `make pest` |
| **Estimated runtime** | ~30s (full suite ~45s after Phase 3) |

Container note: D-021 requires all commands inside containers. Use `make` aliases or `docker compose exec web ./vendor/bin/pest ...`.

---

## Sampling Rate

- **After every task commit:** Run `make pest ARGS="--filter=Phase03 or Game"` (~10s)
- **After every plan wave:** Run `make pest` (full suite, ~45s)
- **Before phase verification:** Full suite must be green; Pint/PHPStan L8 clean
- **Max feedback latency:** ~45 seconds

---

## Per-Task Verification Map

> **Filled by planner during plan generation.** The planner agent expands this table when emitting each plan, mapping every task to its automated verification command (or marking Wave 0 dependencies).

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | — | — | REQ-platform-vision | — | — | — | — | — | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `apps/web/tests/Feature/Phase03/*` test directory created with skeleton tests
- [ ] `apps/web/database/factories/Game{Role,MatchType,...}Factory.php` factory stubs (TBD by planner)
- [ ] Existing Pest infrastructure covers Phase 3 (no new framework install)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filament UI smoke — admin creates Game + GameRoles + GameMatchTypes with capacity matrix end-to-end | SC-1, SC-2 | UI flow inside Filament panel; no headless browser tests in P1 (per Phase 1/2 handoff pattern) | Log in via Discord → /admin → Games resource → Create HLL + verify 15 roles + create Scrim 50v50 + set capacities → save → re-open and confirm persistence |
| HLL seeder produces 15 roles + 5 match types on fresh DB | SC-3 | Seeder idempotency is best smoked manually by `make artisan ARGS="migrate:fresh --seed"` and inspecting Filament | After fresh migrate+seed, open Filament → Games → HLL → expect 15 roles + 5 match types |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
