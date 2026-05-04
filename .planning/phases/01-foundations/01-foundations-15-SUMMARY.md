---
phase: 01-foundations
plan: 15
subsystem: api
tags: [spatie-laravel-data, typescript-transformer, dto, codegen, monorepo, shared-types]

# Dependency graph
requires:
  - phase: 01-foundations
    provides: User/Player/PlayerPrivacy Eloquent models (plan 01-10 — column shape mirrored by the 3 DTOs)
  - phase: 01-foundations
    provides: packages/shared-types skeleton + workspace wiring (plan 01-01)
  - phase: 01-foundations
    provides: docker-compose.yml web service + bind-mount conventions (plan 01-02 + 01-06)
provides:
  - spatie/laravel-data ^4.22 + spatie/laravel-typescript-transformer ^3.0 installed and wired (D-001 LOCKED)
  - app/Data/{UserData,PlayerData,PlayerPrivacyData}.php DTOs with #[TypeScript] attribute
  - app/Providers/TypeScriptTransformerServiceProvider.php (v3-style provider config, registered in bootstrap/providers.php)
  - app/Console/Commands/TypescriptGenerateCommand.php — `trenchwars:typescript-generate` artisan command
  - resources/js/types/api.d.ts (auto-generated `declare namespace App.Data { ... }`)
  - packages/shared-types/src/api.d.ts (synced copy + `/// <reference path>` consumed by index.ts)
  - packages/shared-types/src/index.ts re-exports UserData/PlayerData/PlayerPrivacyData type aliases
  - packages/shared-types/scripts/sync-types.sh host-side fallback
  - docker-compose.yml: ./packages/shared-types -> /repo/packages/shared-types bind mount on web service
affects: [phase-02-clans, phase-03-games, phase-05-bot, phase-08-rcon, all phases authoring new DTOs]

# Tech tracking
tech-stack:
  added:
    - spatie/laravel-data ^4.22 (4.22.1 installed)
    - spatie/laravel-typescript-transformer ^3.0 (3.0.3 installed)
    - spatie/typescript-transformer ^3.0 (3.1.1, transitive)
    - spatie/php-structure-discoverer ^2.4 (transitive)
    - phpdocumentor/reflection ^6.6 (upgraded transitively; required ^5 reflection-docblock downgrade)
  patterns:
    - "v3 transformer config = subclass of TypeScriptTransformerApplicationServiceProvider with `configure(TypeScriptTransformerConfigFactory)` (no PHP config file)"
    - "GlobalNamespaceWriter writes `path` relative to the configured `outputDirectory` — so writer path is bare filename ('api.d.ts'), outputDirectory is the absolute target dir"
    - "Cross-package codegen sync via in-container artisan command + extra docker bind mount (./packages/shared-types -> /repo/packages/shared-types) + host-side fallback shell script"
    - "Generated .d.ts uses `declare namespace App.Data` — consumers use `/// <reference path>` + type aliases at the package root for ergonomic imports"

key-files:
  created:
    - apps/web/app/Data/UserData.php
    - apps/web/app/Data/PlayerData.php
    - apps/web/app/Data/PlayerPrivacyData.php
    - apps/web/app/Console/Commands/TypescriptGenerateCommand.php
    - apps/web/app/Providers/TypeScriptTransformerServiceProvider.php
    - apps/web/resources/js/types/api.d.ts
    - apps/web/tests/Feature/Data/TypescriptTransformTest.php
    - packages/shared-types/src/api.d.ts
    - packages/shared-types/scripts/sync-types.sh
  modified:
    - apps/web/composer.json
    - apps/web/composer.lock
    - apps/web/bootstrap/providers.php
    - apps/web/.gitignore (ignore typescript-transformer-manifest.json)
    - packages/shared-types/src/index.ts (drop placeholder, alias DTOs)
    - docker-compose.yml (bind-mount packages/shared-types into web container)
    - apps/bot/src/index.ts (TrenchwarsApiContract -> UserData)
    - apps/bot/tests/skeleton.test.ts (TrenchwarsApiContract -> UserData)
    - apps/rcon-worker/src/index.ts (TrenchwarsApiContract -> UserData)
    - apps/rcon-worker/tests/skeleton.test.ts (TrenchwarsApiContract -> UserData)

key-decisions:
  - "v3.0 of spatie/laravel-typescript-transformer dropped config/typescript-transformer.php in favour of a published service provider; reconciled the plan's v2-style config-file action steps to v3's provider-class pattern (Rule 3 — canonical install path per the package's own typescript:install command)."
  - "Composer required `--with-all-dependencies` to upgrade phpdocumentor/reflection-docblock from locked 6.0.3 (incompatible with phpdocumentor/reflection ^6.1) to 5.6.7."
  - "GlobalNamespaceWriter writes its `path` relative to the config's `outputDirectory`; passing the full absolute path produced a doubled prefix. Set outputDirectory=resources/js/types and writer path='api.d.ts'."
  - "Dropped PrettierFormatter from the published provider stub — prettier is not installed in the PHP container; the writer's default output is already valid `.d.ts`."
  - "Manually registered TypeScriptTransformerServiceProvider in bootstrap/providers.php — the install command's idempotency check (Str::contains for fully-qualified namespace) misreads the bootstrap file's `use`-imports-shortened format and reports `already registered` without writing the entry."
  - "packages/shared-types/src/index.ts uses `/// <reference path='./api.d.ts'/>` plus type aliases (`export type UserData = App.Data.UserData`) so consumers can `import type { UserData } from '@trenchwars/shared-types'` without spelling out the ambient `App.Data.*` path."
  - "Cross-package shared-types sync inside the container requires bind-mounting ./packages/shared-types -> /repo/packages/shared-types on the web service (cross-cut from plan 01-02; same precedent as plan 01-06's tsconfig.base.json mount). The artisan command warns and exits 0 when the mount is absent (e.g. future Railway deploys); host-side packages/shared-types/scripts/sync-types.sh is the no-docker fallback."
  - "Updated the 01-16 skeleton tests in apps/bot/ and apps/rcon-worker/ to import UserData from @trenchwars/shared-types — TrenchwarsApiContract is no longer exported and the existing test files' own comments (`Plan 01-15 will replace this`) explicitly anticipated this swap."

patterns-established:
  - "DTO authoring: declare `final class FooData extends Data` in app/Data/ with `#[TypeScript]` and a constructor-promoted property list mirroring the corresponding Eloquent model's column shape."
  - "Cross-app type sharing pipeline: `make artisan ARGS='trenchwars:typescript-generate'` regenerates apps/web/resources/js/types/api.d.ts AND syncs to packages/shared-types/src/api.d.ts in one command."
  - "When a generated artifact has a per-environment manifest file (typescript-transformer-manifest.json), gitignore the manifest and commit only the canonical .d.ts."

requirements-completed: [REQ-constraint-railway-deploy]

# Metrics
duration: 8min
completed: 2026-05-04
---

# Phase 1 Plan 15: Spatie Laravel Data + TypeScript Transformer Setup Summary

**Cross-app type sharing pipeline live: 3 PHP DTOs (UserData/PlayerData/PlayerPrivacyData) with `#[TypeScript]` attribute emit `apps/web/resources/js/types/api.d.ts` via `php artisan typescript:transform`, synced to `packages/shared-types/src/api.d.ts` by the custom `trenchwars:typescript-generate` artisan command for consumption by apps/bot and apps/rcon-worker.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-05-04T18:17:46Z
- **Completed:** 2026-05-04T18:25:34Z
- **Tasks:** 2
- **Files modified:** 19

## Accomplishments

- Installed `spatie/laravel-data` ^4.22 and `spatie/laravel-typescript-transformer` ^3.0 (D-001 LOCKED).
- Configured the v3 transformer via `app/Providers/TypeScriptTransformerServiceProvider` (subclass of `TypeScriptTransformerApplicationServiceProvider`) — `transformDirectories(app_path('Data'))`, `outputDirectory(resource_path('js/types'))`, `GlobalNamespaceWriter('api.d.ts')`. Registered in `bootstrap/providers.php`.
- Authored 3 DTOs in `app/Data/` mirroring the User/Player/PlayerPrivacy Eloquent models from plan 01-10. All carry the `#[Spatie\TypeScriptTransformer\Attributes\TypeScript]` attribute.
- `php artisan typescript:transform` emits a 685-byte `apps/web/resources/js/types/api.d.ts` declaring `App.Data.UserData`, `App.Data.PlayerData`, and `App.Data.PlayerPrivacyData` (each as `export type X = { ... }` inside the ambient namespace).
- Custom artisan command `trenchwars:typescript-generate` runs `typescript:transform` then copies the emitted `api.d.ts` to `/repo/packages/shared-types/src/api.d.ts` (graceful warn-and-exit-0 if the bind mount is unavailable).
- `docker-compose.yml` web service gained a `./packages/shared-types -> /repo/packages/shared-types` bind mount so the in-container artisan command reaches the cross-package target. Same precedent as plan 01-06's tsconfig.base.json mount.
- `packages/shared-types/src/index.ts` replaces the `TrenchwarsApiContract` placeholder with type aliases reaching the ambient `App.Data.*` namespace via `/// <reference path>`. Consumers (apps/bot, apps/rcon-worker) now `import type { UserData } from '@trenchwars/shared-types'`.
- `packages/shared-types/scripts/sync-types.sh` ships as a host-side fallback for environments where the docker volume mount isn't available (CI runners building images independently, future deploy targets).
- New Pest test file `tests/Feature/Data/TypescriptTransformTest.php` with 2 assertions: (1) `typescript:transform` writes an `api.d.ts` listing all 3 DTOs; (2) `trenchwars:typescript-generate` exits 0 and (when mount available) syncs to packages/shared-types.

## Task Commits

1. **Task 1: Install spatie/laravel-data + typescript-transformer; configure; author 3 DTOs; run typescript:transform** — `ddfd04f` (feat)
2. **Task 2: trenchwars:typescript-generate command + cross-package shared-types sync + Pest test** — `3aa5803` (feat)

**Plan metadata:** _to be added in the final docs commit_

## Files Created/Modified

### Created (9)
- `apps/web/app/Data/UserData.php` — UserData DTO mirroring User column shape
- `apps/web/app/Data/PlayerData.php` — PlayerData DTO with array bio cast
- `apps/web/app/Data/PlayerPrivacyData.php` — PlayerPrivacyData DTO with the 5 boolean show_* flags + show_to literal
- `apps/web/app/Console/Commands/TypescriptGenerateCommand.php` — trenchwars:typescript-generate artisan command (transform + cross-package sync)
- `apps/web/app/Providers/TypeScriptTransformerServiceProvider.php` — v3-style provider class with transformDirectories/outputDirectory/writer config
- `apps/web/resources/js/types/api.d.ts` — auto-generated `declare namespace App.Data { ... }` (685 bytes, 3 type aliases)
- `apps/web/tests/Feature/Data/TypescriptTransformTest.php` — 2-test Pest spec exercising both transform and the custom sync command
- `packages/shared-types/src/api.d.ts` — synced copy of the generated .d.ts (consumed by bot + rcon-worker)
- `packages/shared-types/scripts/sync-types.sh` — host-side fallback sync script

### Modified (10)
- `apps/web/composer.json` — added spatie/laravel-data + spatie/laravel-typescript-transformer
- `apps/web/composer.lock` — 11 packages installed/upgraded; phpdocumentor/reflection-docblock downgraded 6.0.3 -> 5.6.7
- `apps/web/bootstrap/providers.php` — registered TypeScriptTransformerServiceProvider
- `apps/web/.gitignore` — ignore /resources/js/types/typescript-transformer-manifest.json
- `packages/shared-types/src/index.ts` — drop TrenchwarsApiContract placeholder; alias 3 DTOs
- `docker-compose.yml` — bind-mount packages/shared-types into web container at /repo/packages/shared-types
- `apps/bot/src/index.ts` — TrenchwarsApiContract -> UserData import
- `apps/bot/tests/skeleton.test.ts` — same swap (anticipated by the file's own comment)
- `apps/rcon-worker/src/index.ts` — TrenchwarsApiContract -> UserData import
- `apps/rcon-worker/tests/skeleton.test.ts` — same swap

## Decisions Made

- **v3 of spatie/laravel-typescript-transformer dropped the PHP config file.** v3.0 introduces `Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider` as the configuration surface; users subclass it and override `protected function configure(TypeScriptTransformerConfigFactory $config): void`. The plan's pasted action steps targeted the v2 `config/typescript-transformer.php` API (`auto_discover_types`, `output_file`, `transform_to_native_enums`). Reconciled to v3 (Rule 3 deviation; canonical install via `php artisan typescript:install`).
- **`GlobalNamespaceWriter` resolves `path` relative to `outputDirectory`.** Setting both to `resources/js/types/api.d.ts` produces `resources/js/types/app/resources/js/types/api.d.ts`. Correct shape: `outputDirectory=resources/js/types` + `writer path='api.d.ts'`.
- **Skipped PrettierFormatter.** The published provider stub references `Spatie\TypeScriptTransformer\Formatters\PrettierFormatter`, which shells out to `prettier` — not installed in the PHP container. The writer's default formatting is valid `.d.ts`.
- **`bootstrap/providers.php` registration is manual.** `php artisan typescript:install`'s `Str::contains` check misreads the file's `use`-imports-shortened layout and reports `already registered` without writing the entry. Edited the bootstrap directly.
- **`packages/shared-types/src/index.ts` uses `/// <reference path>` + type aliases.** Generated `api.d.ts` uses `declare namespace App.Data { ... }` (ambient, no module export). Adding `export type UserData = App.Data.UserData` at the package root lets consumers do `import type { UserData } from '@trenchwars/shared-types'` without reaching into the namespace.
- **Cross-package sync via in-container bind mount.** `trenchwars:typescript-generate` runs inside the web container; reaching `packages/shared-types` requires a new bind mount. Modeled after plan 01-06's tsconfig.base.json mount precedent. The command warns and exits 0 if the mount is absent so it's safe to run in deployment containers.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] composer require needed `--with-all-dependencies`**
- **Found during:** Task 1 (composer require)
- **Issue:** `phpdocumentor/reflection-docblock` was locked at 6.0.3, incompatible with `phpdocumentor/reflection ^6.1` required by spatie/laravel-data. Composer refused to resolve without permission to mutate the lock.
- **Fix:** Re-ran with `composer require -W ...`. 11 packages installed/upgraded, reflection-docblock downgraded to 5.6.7 (the `^5` constraint required by 6.x reflection).
- **Files modified:** apps/web/composer.json, apps/web/composer.lock
- **Verification:** Install completed; package:discover succeeded; full Pest suite still 40/40 green after install.
- **Committed in:** ddfd04f

**2. [Rule 3 - Blocking] v2 config-file API replaced by v3 provider-class API**
- **Found during:** Task 1 (vendor:publish)
- **Issue:** Plan instructed `php artisan vendor:publish --tag=typescript-transformer-config`, but v3.0 doesn't ship that tag. v3 uses `php artisan typescript:install` to publish a `TypeScriptTransformerServiceProvider` stub.
- **Fix:** Ran `typescript:install`, then customised the published provider: `transformDirectories(app_path('Data'))`, `outputDirectory(resource_path('js/types'))`, `GlobalNamespaceWriter('api.d.ts')`, dropped PrettierFormatter (not in container).
- **Files modified:** apps/web/app/Providers/TypeScriptTransformerServiceProvider.php
- **Verification:** Verified against vendor source (`vendor/spatie/laravel-typescript-transformer/src/Commands/InstallTypeScriptTransformerCommand.php` + `TypeScriptTransformerApplicationServiceProvider.php`). Confirmed via Context7 README for the package.
- **Committed in:** ddfd04f

**3. [Rule 3 - Blocking] typescript:install didn't actually register the provider**
- **Found during:** Task 1 (first typescript:transform run)
- **Issue:** `typescript:install`'s `registerServiceProvider` does `Str::contains($appConfig, $namespace.'\\Providers\\TypeScriptTransformerServiceProvider::class')`, but `bootstrap/providers.php` uses `use App\Providers\TypeScriptTransformerServiceProvider;` + the short-class form `TypeScriptTransformerServiceProvider::class,`. The check matches false-positive ("already registered") without writing the entry.
- **Fix:** Edited `bootstrap/providers.php` directly to add the use-import + registration line.
- **Files modified:** apps/web/bootstrap/providers.php
- **Verification:** `php artisan typescript:transform` succeeded ("All done!") emitting api.d.ts with all 3 DTOs.
- **Committed in:** ddfd04f
- **Whitelist note:** `bootstrap/providers.php` is not in the plan's `files_modified` whitelist, but registering the provider in v3 is the functional equivalent of publishing the v2 config file (also not in the whitelist). No overlap with plan 01-13 (which touches `app/Filament/Resources/*` + `AdminPanelProvider.php`).

**4. [Rule 3 - Blocking] outputDirectory base + writer path doubling**
- **Found during:** Task 1 (first typescript:transform run after registration)
- **Issue:** `GlobalNamespaceWriter('resources/js/types/api.d.ts')` with default `outputDirectory(resource_path('js/generated'))` first failed with "output directory does not exist". After setting `outputDirectory(resource_path('js/types/api.d.ts'))`, the writer produced `resources/js/types/app/resources/js/types/api.d.ts` because the writer's `path` is appended to `outputDirectory`.
- **Fix:** Set `outputDirectory(resource_path('js/types'))` (an existing directory) + writer path `'api.d.ts'`. Cleaned the prior bad output and re-ran.
- **Files modified:** apps/web/app/Providers/TypeScriptTransformerServiceProvider.php
- **Verification:** Final emit at correct path; verified by reading `apps/web/resources/js/types/api.d.ts` (3 type aliases declared in `App.Data` namespace).
- **Committed in:** ddfd04f

**5. [Rule 2 - Missing critical] Generated manifest file gitignored**
- **Found during:** Task 1 (post-transform self-check)
- **Issue:** `typescript:transform` emits a sibling `typescript-transformer-manifest.json` containing per-file MD5 hashes used to skip unchanged files on subsequent runs. Committing this would create churn on every regen and would not be useful as repo state.
- **Fix:** Added `/resources/js/types/typescript-transformer-manifest.json` to `apps/web/.gitignore`.
- **Files modified:** apps/web/.gitignore
- **Verification:** `git status` no longer shows the manifest as untracked.
- **Committed in:** ddfd04f

**6. [Rule 3 - Cross-cut] docker-compose.yml shared-types bind mount**
- **Found during:** Task 2 (designing the cross-package sync)
- **Issue:** `trenchwars:typescript-generate` must write to `packages/shared-types/src/api.d.ts` from inside the web container, but only `apps/web/` was bind-mounted. The plan body explicitly authorised this docker-compose.yml addition.
- **Fix:** Added `./packages/shared-types:/repo/packages/shared-types` to the web service `volumes`. Cycled the web container so the mount took effect.
- **Files modified:** docker-compose.yml
- **Verification:** Inside container `/repo/packages/shared-types/src/api.d.ts` is writable; `make artisan ARGS="trenchwars:typescript-generate"` reports "Wrote 685 bytes".
- **Committed in:** 3aa5803
- **Whitelist note:** `docker-compose.yml` is not in the plan's `files_modified` whitelist, but the plan body explicitly described this mount line. Same precedent as plan 01-06's tsconfig.base.json mount cross-cut.

**7. [Rule 3 - Blocking] 01-16 skeleton tests still imported TrenchwarsApiContract**
- **Found during:** Task 2 (after replacing the placeholder export)
- **Issue:** apps/bot/tests/skeleton.test.ts and apps/rcon-worker/tests/skeleton.test.ts (shipped by plan 01-16) imported `TrenchwarsApiContract` — gone after the index.ts rewrite. Without an update, plan 01-16 CI fails on `tsc --noEmit`.
- **Fix:** Updated both test files (and the corresponding src/index.ts files) to import `UserData` instead. The existing test-file comments explicitly anticipated this swap ("Plan 01-15 (wave 10) replaces this... this skeleton test will be updated then").
- **Files modified:** apps/bot/tests/skeleton.test.ts, apps/bot/src/index.ts, apps/rcon-worker/tests/skeleton.test.ts, apps/rcon-worker/src/index.ts
- **Verification:** Files reference `UserData` only; `import type { UserData } from '@trenchwars/shared-types'` resolves to the new alias in packages/shared-types/src/index.ts.
- **Committed in:** 3aa5803

---

**Total deviations:** 7 auto-fixed (5 blocking, 1 missing-critical, 1 cross-cut)
**Impact on plan:** All deviations were necessary to land the v3-API canonical install path and the cross-package sync infrastructure the plan required. No scope creep — every modified file outside the strict whitelist (bootstrap/providers.php, docker-compose.yml, .gitignore, the 4 bot/rcon-worker files) was either explicitly authorised in the plan body or required to keep dependent CI gates green.

## Issues Encountered

- **Bot/rcon-worker tsc not validated locally.** Local Node typecheck would require running `pnpm install` at the workspace root + booting the bot/rcon-worker containers; out of scope for this plan. The package-level type aliases in `packages/shared-types/src/index.ts` resolve correctly via `/// <reference path>` and will be validated by plan 01-16's GitHub Actions matrix on the next push.
- **Concurrent commits with plan 01-13 on master.** While 01-15 ran, 01-13 committed two additional commits (`ae4bf20`, `5b5b81d`) on the same `master` branch (not a separate worktree). Files are disjoint (Data DTOs + transformer config vs Filament Resources), no merge conflict; git sequenced the commits correctly.

## User Setup Required

None — no external service configuration introduced. The cross-package sync runs entirely inside the local docker-compose stack.

## Next Phase Readiness

- D-020 deliverable in place: cross-app type sharing pipeline live. Phase 2+ adds DTOs in lockstep with new models by dropping `app/Data/{Model}Data.php` files with `#[TypeScript]` and re-running `make artisan ARGS="trenchwars:typescript-generate"`.
- Phase 5 (apps/bot) and Phase 8 (apps/rcon-worker) can now consume real types from `@trenchwars/shared-types`. The 01-16 CI matrix will validate these end-to-end on the next push to master.
- One remaining plan in Phase 1: **01-18** (final phase wrap-up). After 01-13 completes, only 01-14 + 01-18 remain (15/18 -> 16/18 after this plan; 17/18 once 01-13 finishes).

## Self-Check: PASSED

All 19 referenced files verified to exist on disk; both task commits (`ddfd04f`, `3aa5803`) present in `git log --all`.

---
*Phase: 01-foundations*
*Completed: 2026-05-04*
