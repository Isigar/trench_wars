---
phase: 01-foundations
plan: 18
subsystem: testing
tags: [migrations, pest, pint, phpstan, vite, tailwind, filament, dual-tailwind, oauth, ci-gate, phase-close]

# Dependency graph
requires:
  - phase: 01-foundations
    provides: All 17 prior plans (04 schema, 09-10 OAuth, 11 RBAC, 12 Filament panel, 13 resources, 14 audit, 15 DTO pipeline, 16 CI, 17 Railway)
provides:
  - Phase 1 verification report (M1..M7 must-have traceability)
  - [BLOCKING] schema-push proof on a fresh database
  - Quality-gate snapshot (Pest 54/54, Pint 91 clean, PHPStan L8 clean, both Vite manifests built, bot/rcon-worker/shared-types pipelines green)
  - PENDING manual smoke checklist with reproduction steps for the operator
affects: [phase-02 (clans), phase-03 (matches), every downstream phase that assumes a working P1 foundation]

# Tech tracking
tech-stack:
  added: ["@types/node@^22 in packages/shared-types (Rule 3 fix; previously only in apps/bot + apps/rcon-worker)"]
  patterns: ["Phase verification doc pattern — single PHASE-VERIFICATION.md captures schema push + quality gates + manual smoke + M-traceability for downstream /gsd-verify-work"]

key-files:
  created:
    - .planning/phases/01-foundations/01-foundations-PHASE-VERIFICATION.md
    - .planning/phases/01-foundations/01-foundations-18-SUMMARY.md
    - pnpm-lock.yaml (root — first committed lockfile)
  modified:
    - packages/shared-types/package.json (added @types/node devDep)

key-decisions:
  - "Phase 1 closes with two manual smokes deferred to operator (autonomous-mode user direction); automated gates fully green"
  - "shared-types missing @types/node was a pre-existing Rule 3 blocker inherited from plan 01-15 — surfaced and fixed during P1 verification rather than reopening 01-15"
  - "Laravel default tables (sessions/password_resets/personal_access_tokens/failed_jobs/cache/jobs) are intentionally absent from the schema (D-002 OAuth-only, plan 01-04 deletion); the M1 acceptance criteria's 9-business-table count remains satisfied"

patterns-established:
  - "PHASE-VERIFICATION.md is the canonical phase-close artifact — every future phase ends with one, mapped 1:1 against the plan's must_haves.truths and ROADMAP SC-N criteria"
  - "When manual smokes are required but autonomous-mode is active, document them as PENDING with full reproduction steps; do not block phase close on them"

requirements-completed: [REQ-constraint-railway-deploy, REQ-constraint-en-launch-i18n-ready]

# Metrics
duration: 6min
completed: 2026-05-04
---

# Phase 01 Plan 18: phase-1-verification Summary

**Final phase-1 close: [BLOCKING] schema push proven on a fresh DB, full quality-gate suite green (Pest 54/54, Pint 91 clean, PHPStan L8 0 new findings, both Vite manifests built, bot/rcon-worker/shared-types pipelines all green), manual smokes documented as PENDING for operator handoff.**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-05-04T18:44:03Z
- **Completed:** 2026-05-04T18:50:09Z (approx — measured at SUMMARY write time)
- **Tasks:** 2 automated tasks executed; Task 3 (manual smoke checkpoint) documented as PENDING per autonomous-mode handoff
- **Files modified:** 4 (`01-foundations-PHASE-VERIFICATION.md`, `01-foundations-18-SUMMARY.md`, `packages/shared-types/package.json`, `pnpm-lock.yaml`)

## Accomplishments

- **[BLOCKING] schema push verified on fresh DB:** dropped + recreated `trenchwars` database, ran `php artisan migrate --force` against the empty database, all 7 migrations from plans 04/10/11/14 applied cleanly with no errors. PermissionSeeder seeded 2 permissions + 2 roles, all `guard_name='web'`. 3 Postgres extensions enabled (uuid-ossp, pgcrypto, citext) + plpgsql default. 9 business tables present, exactly matching plan task 1 acceptance criteria.
- **Full quality-gate suite green:** Pest 54 passed / 161 assertions / 0 failures (parallel ×24, 2.53s); Pint 91 files clean; PHPStan L8 `[OK] No errors`; main Vite bundle built (`apps/web/public/build/manifest.json`, 2492 modules, 2.57s); Filament theme bundle built (`apps/web/public/build/filament/manifest.json`, 110 kB CSS, 994ms — Pitfall 1 dual-Tailwind workaround proven at build time); `@trenchwars/shared-types` typecheck PASS; `@trenchwars/bot` typecheck/lint/test all PASS (2/2 vitest); `@trenchwars/rcon-worker` typecheck/lint/test all PASS (2/2 vitest).
- **PHASE-VERIFICATION.md authored:** captures M1–M7 must-have traceability + ROADMAP SC-1..SC-5 mapping + full reproduction steps for the two manual smokes (Filament dual-Tailwind visual + Discord OAuth real-app happy path) so the operator can complete them on their own time without re-reading the plan.
- **Pre-existing TS-config blocker fixed (Rule 3 deviation):** `packages/shared-types` was missing `@types/node` in devDependencies, which broke its typecheck (`tsconfig.base.json` declares `types:["node"]`) and downstream broke `apps/bot` typecheck (which imports from `@trenchwars/shared-types/dist/index.d.ts`). Strictly additive package.json fix; CI workflows already use `pnpm install --no-frozen-lockfile` so they would have hit the same gap on first green run.

## Task Commits

1. **Task 1: [BLOCKING] migrate --force on fresh DB** — `c4ad754` (docs)
   - Dropped + recreated `trenchwars`, ran migrate, ran seed, verified 9 tables + 3 extensions + 2 permissions + 2 roles. Authored task-1 portion of `01-foundations-PHASE-VERIFICATION.md`.
2. **Task 2: Quality gates + bot/rcon/shared-types pipelines** — `e17a7df` (docs)
   - Pest 54/54, Pint 91 clean, PHPStan L8 zero, both Vite manifests, bot+rcon+shared-types all green. Filled in tasks-2-and-3 portion of PHASE-VERIFICATION.md (manual smoke documented as PENDING per autonomous-mode handoff). Includes Rule 3 deviation: added `@types/node` to `packages/shared-types/package.json` + committed first `pnpm-lock.yaml`.

**Plan metadata commit:** _(this final-commit, hash recorded post-write)_

## Files Created/Modified

| File | Change | Why |
|---|---|---|
| `.planning/phases/01-foundations/01-foundations-PHASE-VERIFICATION.md` | created (272 lines) | Single phase-close artifact — schema push + quality gates + manual smoke checklist + M1–M7 traceability + deviation log |
| `.planning/phases/01-foundations/01-foundations-18-SUMMARY.md` | created (this file) | Plan-level summary per execute-plan protocol |
| `packages/shared-types/package.json` | modified | Added `@types/node@^22.0.0` devDep (Rule 3 — blocking typecheck failure inherited from plan 01-15) |
| `pnpm-lock.yaml` | created | First-time committed root lockfile (4193 lines) — improves reproducibility; CI workflows already tolerated absence via `--no-frozen-lockfile` |

## Decisions Made

- **Manual smokes deferred to operator** rather than blocking phase close — explicit user direction in autonomous-mode handoff. Both smokes (Filament dual-Tailwind theme visual + Discord OAuth real-app happy path) have full reproduction steps captured in PHASE-VERIFICATION.md so the operator can run them at their own cadence without re-reading the plan.
- **Rule 3 fix scope:** kept the fix minimal — only added `@types/node` to one package.json + committed lockfile. Did not touch tsconfig.base.json (the original gap is in shared-types' missing devDep, not in the base config). Did not regenerate the bot/rcon-worker lockfiles (they already worked).
- **PHASE-VERIFICATION.md naming/location:** matched the plan frontmatter spec exactly (`.planning/phases/01-foundations/01-foundations-PHASE-VERIFICATION.md`); did not number it `01-18-VERIFICATION.md` to keep the verifier convention phase-scoped (one verification doc per phase, not per plan).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking issue] Added `@types/node` to `packages/shared-types` devDependencies**

- **Found during:** Task 2 (running `pnpm --filter @trenchwars/shared-types typecheck` for the bot/rcon-worker/shared-types pipeline gates)
- **Issue:** `error TS2688: Cannot find type definition file for 'node'.` — `tsconfig.base.json` declares `"types":["node"]`, and `packages/shared-types/package.json` did not list `@types/node` as a devDependency. Same root cause for `pnpm --filter shared-types build`, which generates `dist/index.d.ts`, in turn required for `@trenchwars/bot` to typecheck (`apps/bot/src/index.ts` line 6: `import ... from '@trenchwars/shared-types'`).
- **Fix:** Added `"@types/node": "^22.0.0"` to `packages/shared-types/package.json` devDependencies, version-aligned to `apps/bot` and `apps/rcon-worker`.
- **Files modified:** `packages/shared-types/package.json`; side-effect: first-time commit of `pnpm-lock.yaml` (4193 lines).
- **Verification:** `pnpm --filter @trenchwars/shared-types typecheck` (after fix) PASS; `pnpm --filter @trenchwars/shared-types build` produces `dist/index.{js,d.ts,js.map}`; downstream `pnpm --filter @trenchwars/bot typecheck` PASS; `pnpm --filter @trenchwars/rcon-worker typecheck` PASS.
- **Why Rule 3 (not Rule 4 architectural):** Pre-existing config gap inherited from plan 01-15. Strictly additive (no architectural decision affected; no config base-rewrite; no semantics change). Caught by CI on first green run regardless.
- **Committed in:** `e17a7df` (Task 2 commit).

### Manual smoke deferral (NOT a deviation — explicit user direction)

The plan declares `autonomous: false` because of two manual checkpoints (Filament dual-Tailwind visual check + Discord OAuth real-world happy path). Per the user's autonomous-mode handoff in the execute prompt, both smokes are documented in PHASE-VERIFICATION.md as `[PENDING — manual smoke required]` with full reproduction steps; the plan completes successfully without blocking on operator action. This is per the user's instruction, not a Rules 1–3 auto-fix.

---

**Total deviations:** 1 auto-fixed (Rule 3 — blocking)
**Impact on plan:** Single additive package.json fix; no scope creep. Without it, three of the seven quality gate rows in PHASE-VERIFICATION.md (`shared-types typecheck`, `bot typecheck`, `rcon-worker typecheck`) would have failed.

## Issues Encountered

- **shared-types `dist/` not committed (gitignored).** First-time encounter during this plan's gate run. The tsconfig outputs `dist/` and `.gitignore` lists `dist/`. The bot package's `tsconfig.json` resolves the workspace dep through its package.json `types: ./dist/index.d.ts`, so `dist/` must be built locally for downstream typecheck to succeed. Worked around by running `pnpm --filter shared-types build` once before bot/rcon-worker typecheck. No code change needed; the local build artifact is regenerated by the in-container `php artisan trenchwars:typescript-generate` command (plan 01-15) which calls the sync-types.sh script. CI runs the typecheck step in the `shared-types` workflow which exercises the same path.
- **`make pint --test` doesn't accept `--colors=never`.** Plan suggested `./vendor/bin/pint --test --colors=never`; the local Pint version errors with `The "--colors" option does not exist.`. Dropped the flag; output is still readable. Cosmetic only.

## User Setup Required

**External services require manual configuration before manual smokes can run.** The two PENDING smokes in PHASE-VERIFICATION.md require:

1. **Discord developer application** at https://discord.com/developers/applications with redirect URI `http://localhost:8000/auth/discord/callback` (exact match — Pitfall 2). Client ID + Client Secret pasted into `apps/web/.env` (`DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`). See PHASE-VERIFICATION.md "Manual smoke checklist — A" for full steps.
2. **Operator's own Discord user account ID** for `make artisan ARGS="trenchwars:make-admin <YOUR_DISCORD_USER_ID>"` — needed to grant admin-access on first login so the Filament theme can be visually verified at /admin.

Both already documented in `.planning/phases/01-foundations/01-VALIDATION.md` (Manual-Only Verifications) and the plan's `<how-to-verify>` block.

## Next Phase Readiness

**Phase 1 closes with:**

- All 18 plans executed (17 prior + this one).
- All automated phase-1 must-haves PASS (M1–M5, M7).
- M6 (manual smoke) PENDING for operator at their cadence — does not block downstream planning per autonomous-mode handoff.
- Schema, OAuth, RBAC, Filament admin, audit log, DTO pipeline, CI, Railway deploy doc all in place.
- Phase 2 (clans) can begin planning; its only hard prerequisite from P1 is `users` + `players` + `player_privacy` schema (M1 PASS) and the Filament admin shell (M5 PASS at build time, M6 PENDING at visual time).

**Open items rolled forward:**

- Operator must complete the two manual smokes (PHASE-VERIFICATION.md Manual smoke checklist A + B + C) before declaring phase 1 100% closed in production. If any item fails, escalate via `/gsd-plan-phase --gaps`.
- `.docs/` directory still untracked (carried from plan 01-04 deferred-items.md) — non-blocking.

---
*Phase: 01-foundations*
*Completed: 2026-05-04*

## Self-Check: PASSED

| Item | Status |
|---|---|
| `01-foundations-PHASE-VERIFICATION.md` exists on disk | FOUND |
| `01-foundations-18-SUMMARY.md` exists on disk | FOUND |
| `packages/shared-types/package.json` exists on disk | FOUND |
| `pnpm-lock.yaml` exists on disk | FOUND |
| Task 1 commit `c4ad754` reachable in `git log --all` | FOUND |
| Task 2 commit `e17a7df` reachable in `git log --all` | FOUND |
