---
phase: 01-foundations
plan: 01
subsystem: monorepo-skeleton
tags:
  - monorepo
  - pnpm
  - typescript
  - scaffolding
dependency_graph:
  requires: []
  provides:
    - pnpm-workspace-root
    - tsconfig-base
    - apps-bot-skeleton
    - apps-rcon-worker-skeleton
    - packages-shared-types-skeleton
    - apps-web-mount-placeholder
  affects:
    - "01-02 (docker-compose) — bind-mount targets now exist"
    - "01-04 (Laravel scaffold inside web container) — apps/web placeholder reserved"
    - "01-15 (DTO pipeline) — packages/shared-types ready to receive emitted types"
    - "Phase 5 (bot logic) — apps/bot package ready for discord.js"
    - "Phase 8 (rcon-worker) — apps/rcon-worker package ready for CRCON client"
tech_stack:
  added:
    - "pnpm 9.15.0 (corepack-managed; not yet installed on host — runs inside containers per D-021)"
    - "TypeScript 5.6 (declared in devDeps of all three TS packages; not yet installed)"
    - "@types/node 22 (declared in devDeps of bot + rcon-worker)"
  patterns:
    - "All TS packages extend a single root tsconfig.base.json (ES2022/NodeNext/strict)"
    - "Workspace deps via pnpm `workspace:*` protocol"
    - "Package names namespaced under @trenchwars/*"
key_files:
  created:
    - pnpm-workspace.yaml
    - package.json
    - tsconfig.base.json
    - .gitignore
    - .editorconfig
    - apps/bot/package.json
    - apps/bot/tsconfig.json
    - apps/bot/src/index.ts
    - apps/rcon-worker/package.json
    - apps/rcon-worker/tsconfig.json
    - apps/rcon-worker/src/index.ts
    - packages/shared-types/package.json
    - packages/shared-types/tsconfig.json
    - packages/shared-types/src/index.ts
    - apps/web/.gitkeep
  modified: []
decisions:
  - "Used SUMMARY filename `01-01-SUMMARY.md` (matches orchestrator prompt + canonical {phase}-{plan} format), not the plan frontmatter's `01-foundations-01-SUMMARY.md` variant."
metrics:
  tasks_completed: 2
  files_created: 15
  files_modified: 0
  duration_minutes: ~5
  completed: 2026-05-03
---

# Phase 01 Plan 01: pnpm monorepo skeleton — Summary

**One-liner:** Scaffolds the pnpm-workspaces monorepo root with `apps/bot`, `apps/rcon-worker`, `packages/shared-types` TypeScript skeletons (extending a shared `tsconfig.base.json`) plus `.gitignore`/`.editorconfig`, leaving `apps/web/` as an empty placeholder for plan 04's Laravel `composer create-project`.

## What was built

This plan delivers pure file scaffolding — no installs, no Docker, no Laravel. It establishes the directory shape that plan 02 (`docker-compose.yml`) bind-mounts, and the TypeScript package shells that Phases 5 (bot) and 8 (rcon-worker) will fill with actual code.

### Repo-root config (Task 1)

- **`pnpm-workspace.yaml`** — declares `apps/*` and `packages/*` as workspace globs.
- **`package.json`** — root manifest; `private: true`, `packageManager: pnpm@9.15.0`, engines `node>=22, pnpm>=9`, recursive scripts (`build`/`lint`/`test`/`typecheck` use `pnpm -r --if-present`).
- **`tsconfig.base.json`** — single source of TS compiler options consumed via `extends` by every TS package: `target: ES2022`, `lib: ES2022`, `module: NodeNext`, `moduleResolution: NodeNext`, `strict: true`, `noUnusedLocals/Parameters`, `declaration`, `sourceMap`, `types: [node]`.
- **`.gitignore`** — excludes `node_modules/`, `vendor/`, `.env*`, `build/`/`dist/`, Laravel runtime caches under `storage/`, `.phpunit.cache/`, `.phpstan.cache/`, `.idea/`, `.vscode/`, `.DS_Store`, `*.log`, `pnpm-debug.log*`. Mitigates threat **T-1-12** (information disclosure of secrets via git history).
- **`.editorconfig`** — utf-8 / LF / final-newline / no trailing whitespace. PHP files use 4-space indent; TS/Vue/JS/JSON/YAML/CSS/HTML/MD use 2-space; Markdown preserves trailing whitespace (for hard-break syntax).

### TypeScript package skeletons (Task 2)

- **`apps/bot/`** — `@trenchwars/bot` (private, `type: module`); `tsc`/`typecheck` scripts; declares `@trenchwars/shared-types` via `workspace:*`; placeholder dev deps `typescript ^5.6.0` and `@types/node ^22.0.0`. `src/index.ts` imports `TrenchwarsApiContract` (type-only) to prove cross-package wiring and emits a skeleton-boot console log.
- **`apps/rcon-worker/`** — `@trenchwars/rcon-worker`; same shape as bot.
- **`packages/shared-types/`** — `@trenchwars/shared-types` with `main`/`types`/`exports` map pointing at `./dist/index.{js,d.ts}`; `src/index.ts` exports a placeholder `TrenchwarsApiContract` interface that plan 15's spatie/laravel-data DTO pipeline will replace with real types via `export * from "./api"`.
- **`apps/web/.gitkeep`** — empty placeholder so plan 02's compose volume mount target exists; Laravel skeleton lands inside the `web` container in plan 04.

All three TS packages' `tsconfig.json` files use `"extends": "../../tsconfig.base.json"`, emit to `./dist`, and root at `./src`.

## Files created (full list)

```
.editorconfig
.gitignore
package.json
pnpm-workspace.yaml
tsconfig.base.json
apps/bot/package.json
apps/bot/src/index.ts
apps/bot/tsconfig.json
apps/rcon-worker/package.json
apps/rcon-worker/src/index.ts
apps/rcon-worker/tsconfig.json
apps/web/.gitkeep
packages/shared-types/package.json
packages/shared-types/src/index.ts
packages/shared-types/tsconfig.json
```

15 files, 0 modified.

## Verification results

### Task 1 acceptance criteria

| Criterion                                                                | Result | Evidence |
| ------------------------------------------------------------------------ | ------ | -------- |
| All five repo-root files exist                                           | PASS   | `test -f` chain green |
| `package.json` is valid JSON with `"private": true`                      | PASS   | `python3 -c "json.load(...)"` + grep |
| `pnpm-workspace.yaml` lists both `apps/*` and `packages/*` globs         | PASS   | grep both globs |
| `.gitignore` excludes `node_modules`, `vendor/`, `.env`, `build/`, `dist/`, `.idea/` | PASS | grep all six |

### Task 2 acceptance criteria

| Criterion                                                                | Result | Evidence |
| ------------------------------------------------------------------------ | ------ | -------- |
| All nine TS-package files exist                                          | PASS   | `test -f` chain green |
| Each `tsconfig.json` extends `../../tsconfig.base.json`                  | PASS   | grep on all three tsconfigs |
| Bot and rcon-worker import `TrenchwarsApiContract` from `@trenchwars/shared-types` | PASS | grep import statement |
| All three packages have unique `@trenchwars/*` names                     | PASS   | grep each `name` field |
| `apps/web/.gitkeep` exists                                               | PASS   | `test -f apps/web/.gitkeep` |

### Plan-level must_haves

Truth statements:

- "pnpm workspace at repo root resolves all four packages" — workspace globs match `apps/bot`, `apps/rcon-worker`, `packages/shared-types`, plus `apps/web/` directory exists for plan 04. Cannot run `pnpm install` (no install gate yet — `apps/web/package.json` lands in plan 04), but the workspace config is structurally correct.
- "TypeScript projects compile cleanly via `tsc --noEmit`" — **Deferred to post-install verification.** All TS source is syntactically valid, imports resolve via the declared `workspace:*` link, and tsconfig extends paths are correct. Actual `tsc` invocation requires `pnpm install` which is gated until plan 04 (per the plan's explicit `<action>` note: "Do NOT add lockfile yet (no install run here; install happens after plan 04)"). Plan 04 — running `pnpm install` inside the docker `web` container — is the first place this guarantee can be exercised.
- "packages/shared-types exports a placeholder symbol" — `TrenchwarsApiContract` interface exported; bot + rcon-worker both import it.
- ".gitignore excludes node_modules, vendor/, .env, build artifacts, and IDE files" — verified above.

All four `key_links` patterns matched (regex grep passed for each).

## Deviations from Plan

### None

Plan executed exactly as written. Two minor naming notes (not deviations from behavior):

1. **SUMMARY filename:** Plan frontmatter `<output>` proposes `01-foundations-01-SUMMARY.md`; the orchestrator prompt and canonical `{phase}-{plan}-SUMMARY.md` convention call for `01-01-SUMMARY.md`. Used the canonical form.
2. **`tsc --noEmit` not executed:** The plan's must_haves include "compile cleanly via tsc --noEmit", but the plan's `<action>` block also explicitly forbids running `pnpm install` here ("install happens after plan 04"). Without an installed TypeScript binary, `tsc --noEmit` cannot run. This is structural, not a deviation — the verification will happen in plan 04 after `pnpm install` first executes inside the `web` container.

No Rule 1/2/3 auto-fixes were needed.

## Authentication gates

None encountered.

## Threat surface scan

No new attack surface introduced. `T-1-12` (info disclosure via committed secrets) is mitigated by `.gitignore` excluding `.env*`, `node_modules/`, and `vendor/`. No new endpoints, auth paths, file access patterns, or schema changes.

## Commits

- `7e16339` — `chore(01-01): scaffold repo-root workspace files`
- `1e317c5` — `feat(01-01): scaffold bot, rcon-worker, shared-types TS skeletons`

## Next steps (handed to subsequent plans)

- **Plan 02** (`docker-compose.yml`) can now bind-mount `./apps/web`, `./apps/bot`, `./apps/rcon-worker` — all targets exist.
- **Plan 04** runs `composer create-project laravel/laravel apps/web` inside the web container; that creates `apps/web/package.json` and is the first `pnpm install` gate for the workspace.
- **Plan 15** replaces the `TrenchwarsApiContract` placeholder in `packages/shared-types/src/index.ts` with `export * from "./api"` once the spatie/laravel-data DTO generator emits real types.

## Self-Check: PASSED

All 15 files created and verified on disk. Both commit hashes (`7e16339`, `1e317c5`) present in `git log`. All must_haves artifact `contains` patterns and `key_links` regex patterns matched.
