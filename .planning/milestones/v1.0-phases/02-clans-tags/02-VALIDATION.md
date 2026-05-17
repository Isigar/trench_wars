---
phase: 2
slug: clans-tags
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-12
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Inherits Phase 1 test infrastructure (Pest 4 + Larastan L8 + Pint).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) — Laravel Feature + Unit tests |
| **Config file** | `apps/web/phpunit.xml` (Pest reads from PHPUnit XML) |
| **Quick run command** | `make pest ARGS="--filter=<TestName>"` (single-file, ~3s) |
| **Full suite command** | `make pest` (all tests, ~20-40s incl. Phase 1 + new Phase 2) |
| **Static analysis** | `make phpstan` (Larastan level 8) |
| **Style** | `make pint ARGS="--test"` (CI gate) |
| **Estimated runtime** | quick ~3s, full ~40s, phpstan ~30s |

All commands run **inside the web container** (D-021). Host PHP is broken and forbidden by CLAUDE.md.

---

## Sampling Rate

- **After every task commit:** Run `make pest ARGS="--filter=<TestName>"` (single file matching the task)
- **After every plan wave:** Run `make pest` full suite + `make phpstan` + `make pint ARGS="--test"`
- **Before `/gsd-verify-work`:** Full suite + phpstan + pint must all be green
- **Max feedback latency:** ~3s per task, ~70s per wave

---

## Per-Task Verification Map

| Task | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| W0 install | 02-W0 | 0 | (infra) | — | spatie/laravel-translatable installed | unit | `docker compose exec web composer show spatie/laravel-translatable` | ❌ W0 | ⬜ pending |
| Migrations | TBD | 1 | REQ-tenancy-single-guild, REQ-tenancy-multi-clan | T-02-01 | discord_guild row-count enforced; partial unique index applied | feature | `make pest ARGS="--filter=ClanMigrationsTest"` | ❌ W0 | ⬜ pending |
| Models | TBD | 1 | REQ-tenancy-multi-clan | T-02-02 | LogsActivity on every model | unit | `make pest ARGS="--filter=ClanModelTest"` | ❌ W0 | ⬜ pending |
| Privacy gate | TBD | 2 | REQ-goal-public-profiles | T-02-03 | withheld fields omitted from DTO, not nulled | unit | `make pest ARGS="--filter=PlayerPrivacyGateTest"` | ❌ W0 | ⬜ pending |
| Public routes | TBD | 2 | REQ-goal-public-profiles | T-02-04 | /clans, /clans/{slug}, /players/{slug} reachable without auth; private→404 | feature | `make pest ARGS="--filter=PublicClanRoutesTest"` | ❌ W0 | ⬜ pending |
| My Clan | TBD | 3 | REQ-tenancy-multi-clan | T-02-05 | leader/officer auth gate; audit log entry per mutation | feature | `make pest ARGS="--filter=MyClanManagementTest"` | ❌ W0 | ⬜ pending |
| Invites | TBD | 3 | REQ-tenancy-multi-clan | T-02-06 | state machine: pending→accepted/declined/expired/cancelled | feature | `make pest ARGS="--filter=ClanInviteTest"` | ❌ W0 | ⬜ pending |
| Applications | TBD | 3 | REQ-tenancy-multi-clan | T-02-07 | state machine: pending→accepted/declined/cancelled | feature | `make pest ARGS="--filter=ClanApplicationTest"` | ❌ W0 | ⬜ pending |
| Active membership | TBD | 1 | REQ-tenancy-multi-clan | T-02-08 | partial unique index rejects second active membership | feature | `make pest ARGS="--filter=ClanMembershipUniqueTest"` | ❌ W0 | ⬜ pending |
| Filament admin | TBD | 4 | REQ-tenancy-multi-clan | T-02-09 | ClanResource/ClanTagResource/ClanMembershipResource/ClanInviteResource/ClanApplicationResource pages reachable; activity_log no delete | feature | `make pest ARGS="--filter=ClanFilamentResourceTest"` | ❌ W0 | ⬜ pending |
| Single guild | TBD | 1 | REQ-constraint-single-guild | T-02-10 | discord_guild seeder creates exactly one row; create disabled in Filament | feature | `make pest ARGS="--filter=DiscordGuildSingleRowTest"` | ❌ W0 | ⬜ pending |
| i18n no hardcoded | TBD | 5 | (style) | — | no hardcoded UI strings in new Vue pages | static | `make pest ARGS="--filter=NoHardcodedStringsTest"` (extends Phase 1 test) | ✅ inherited | ⬜ pending |
| TS types | TBD | 5 | REQ-tenancy-multi-clan | — | new clan DTOs regenerate into packages/shared-types | feature | `make artisan ARGS="typescript:transform"` + `pnpm -F shared-types check` | ❌ W0 | ⬜ pending |
| Pint+PHPStan | (gate) | all | — | — | CI gate clean | static | `make pint ARGS="--test" && make phpstan` | ✅ inherited | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

> Task IDs marked "TBD" are filled in by the planner with `{N}-{plan}-{task}` format.

---

## Wave 0 Requirements

- [ ] `composer require spatie/laravel-translatable:^6.14` (inside web container)
- [ ] `apps/web/tests/Feature/Phase02/` directory + namespace skeleton
- [ ] `apps/web/tests/Unit/Phase02/PlayerPrivacyGateTest.php` — stub asserting `expect(true)->toBeFalse()` so Wave 1 has a target
- [ ] Test stubs for each test file above (12 stub files, each with at least one `it('placeholder', fn () => expect(true)->toBeFalse())` so Wave 0 verification shows infrastructure is wired but tests fail until implemented)
- [ ] `apps/web/database/factories/` — ClanFactory, ClanTagFactory, ClanMembershipFactory, ClanInviteFactory, ClanApplicationFactory stubs
- [ ] Inertia HTTP test helper extensions (route helpers for `/clans`, `/clans/{slug}`, `/players/{slug}`)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filament UI visual: ClanResource form renders translatable description correctly | REQ-tenancy-multi-clan | Filament admin UI rendering | Sign in as admin → /admin/clans/create → confirm "Description (en)" Filament field renders + saves to JSONB |
| Public /clans page visual + accessibility | REQ-goal-public-profiles | Vue rendering + axe-core | Visit /clans logged-out → confirm cards render, filter works, empty state visible when no matches |
| Audit log writes for clan mutations | REQ-tenancy-multi-clan | Verifying activity_log row content | After editing a clan via My Clan UI: `make artisan ARGS="tinker"` → `ActivityLog::latest()->first()` — confirm `subject_type=Clan`, `causer_id` = user, `description` set |

---

## Validation Sign-Off

- [ ] All planner tasks have `<automated>` verify command or are listed under Wave 0
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references (test stubs + factories + composer install)
- [ ] No watch-mode flags (Pest one-shot runs only)
- [ ] Feedback latency: ~3s per task, ~70s per wave
- [ ] `nyquist_compliant: true` to be set after planner completes mapping task IDs to test files

**Approval:** pending — planner will finalize task IDs and the wave_0_complete flag after Wave 0 runs.
