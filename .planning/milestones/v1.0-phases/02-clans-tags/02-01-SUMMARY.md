---
phase: 02-clans-tags
plan: "01"
subsystem: wave-0-scaffold
tags: [composer, pest, factories, wave-0, spatie-translatable]
dependency_graph:
  requires: [phase-01-foundations]
  provides: [translatable-package, wave-0-red-stubs, factory-stubs]
  affects: [apps/web/composer.json, apps/web/tests/Feature/Clans/, apps/web/database/factories/]
tech_stack:
  added: [spatie/laravel-translatable@6.14.1]
  patterns: [Wave-0-RED-stub-pattern, phpstan-ignore-next-line-for-forward-compat-stubs]
key_files:
  created:
    - apps/web/tests/Feature/Clans/.gitkeep
    - apps/web/tests/Feature/Clans/ClanDirectoryTest.php
    - apps/web/tests/Feature/Clans/ClanShowTest.php
    - apps/web/tests/Feature/Clans/PlayerProfilePrivacyTest.php
    - apps/web/tests/Feature/Clans/PublicClanRoutesTest.php
    - apps/web/tests/Feature/Clans/DiscordGuildSeederTest.php
    - apps/web/tests/Feature/Clans/DiscordGuildSingleRowTest.php
    - apps/web/tests/Feature/Clans/MyClanManagementTest.php
    - apps/web/tests/Feature/Clans/ClanInviteTest.php
    - apps/web/tests/Feature/Clans/ClanApplicationTest.php
    - apps/web/tests/Feature/Models/ClanModelTest.php
    - apps/web/tests/Feature/Models/ClanMembershipModelTest.php
    - apps/web/tests/Feature/Admin/ClanResourcesPresentTest.php
    - apps/web/tests/Feature/Admin/ClanFilamentResourceTest.php
    - apps/web/tests/Unit/Data/PlayerProfileDataTest.php
    - apps/web/tests/Unit/Services/PlayerPrivacyGateTest.php
    - apps/web/database/factories/ClanFactory.php
    - apps/web/database/factories/ClanTagFactory.php
    - apps/web/database/factories/ClanMembershipFactory.php
    - apps/web/database/factories/ClanInviteFactory.php
    - apps/web/database/factories/ClanApplicationFactory.php
    - apps/web/database/factories/DiscordGuildFactory.php
  modified:
    - apps/web/composer.json
    - apps/web/composer.lock
decisions:
  - "Factory $model uses string literal (not ::class) + @phpstan-ignore-next-line to avoid eager-loading non-existent models while satisfying PHPStan L8"
  - "Wave 0 factories use @extends Factory<Model> (base Model class) as the generic type since concrete models don't exist yet"
  - "Docker Desktop was not running at agent start; agent started it via powershell.exe then ran composer inside container per D-021"
metrics:
  duration: "13 minutes"
  completed: "2026-05-12"
  tasks_completed: 3
  tasks_total: 3
  files_created: 22
  files_modified: 2
---

# Phase 2 Plan 01: Wave 0 Scaffold Summary

**One-liner:** spatie/laravel-translatable 6.14.1 installed, 15 RED Pest stubs created across Feature/Clans + Feature/Models + Feature/Admin + Unit/Data + Unit/Services, 6 factory stubs guard Wave 0 with RuntimeException.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Install spatie/laravel-translatable | ff97ef9 | apps/web/composer.json, apps/web/composer.lock |
| 2 | Create 15 Wave 0 RED Pest stubs | 3b0a8ff | 16 files in tests/ (15 PHP + .gitkeep) |
| 3 | Create 6 factory stubs | 89fe80a | 6 files in database/factories/ |

## Verification Results

| Check | Result |
|-------|--------|
| `composer show spatie/laravel-translatable` | 6.14.1 |
| `pest --filter='placeholder Wave 0 stub'` | 15 failed (RED as designed) |
| `pint --test` (all 21 new files) | PASS |
| `phpstan analyse` (full suite) | OK — No errors |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocked] Docker Desktop not running at agent start**
- **Found during:** Task 1 (composer install)
- **Issue:** `/usr/bin/docker` is a broken symlink to `/mnt/wsl/docker-desktop/cli-tools/usr/bin/docker`; Docker Desktop was not running
- **Fix:** Started Docker Desktop via `powershell.exe -Command "Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe'"`, waited for socket availability, then ran `docker compose up -d`
- **Files modified:** None (operational fix)
- **Commit:** N/A (infra fix before Task 1)

**2. [Rule 1 - Bug] Incorrect commit on master branch**
- **Found during:** Task 1 — initial `git commit` ran from the main repo directory instead of the worktree
- **Issue:** CWD was `/home/rtx/projects/trench-wars` (main repo) — `git commit` landed on `master` instead of `worktree-agent-a10a088cf0d35f1c2`
- **Fix:** `git reset --hard 900c39ff` on master to revert the bad commit; re-ran composer from worktree directory; copied composer files to worktree; committed correctly on `worktree-agent-a10a088cf0d35f1c2`
- **Files modified:** apps/web/composer.json, apps/web/composer.lock (correct worktree copies)
- **Commit:** ff97ef9

**3. [Rule 1 - Bug] PHPStan L8 failures on factory @phpstan-ignore syntax**
- **Found during:** Task 3 (factory stubs)
- **Issue 1:** Pint added `use App\Models\X;` imports which caused PHPStan to fail on non-existent classes
- **Issue 2:** `@phpstan-ignore property.defaultValue` in property docblock caused parse error when followed by `@var`
- **Fix:** Used `/** @phpstan-ignore-next-line */` on its own line immediately before the property declaration; used `@extends Factory<Model>` (existing base class) to satisfy the generic type requirement
- **Files modified:** All 6 factory stubs
- **Commit:** 89fe80a

### Worktree File Sync Pattern (Infrastructure Note)

The Docker container mounts `/home/rtx/projects/trench-wars/apps/web` (main repo), not the worktree's `apps/web`. For this plan's workflow:
1. Composer ran in the container, modifying main repo files
2. Files were copied from main repo to worktree
3. All git commits were made from the worktree (correct branch)
4. This pattern applies to all plans until Docker compose is reconfigured to mount the worktree

## Factory Stub Design Decision

The plan specified `$model` as a string (`'App\\Models\\Clan'`) for forward-compat. PHPStan L8 with Larastan requires the `@extends Factory<TModel>` generic type. Since concrete models don't exist yet in Wave 0, the solution:
- `@extends Factory<Model>` (references the base `Illuminate\Database\Eloquent\Model` class which exists)
- `/** @phpstan-ignore-next-line */` suppresses the `property.defaultValue` error for the string assignment
- Wave 1 plan 02-03 replaces: adds `use App\Models\X;`, updates `@extends Factory<X>`, sets `$model = X::class`

## Known Stubs

All 15 test stubs are intentional Wave 0 RED stubs. Each asserts `expect(true)->toBeFalse()`. These are tracked and will be replaced by subsequent wave plans:
- Wave 1 (plan 02-03/04): ClanModelTest, ClanMembershipModelTest, DiscordGuildSeederTest, DiscordGuildSingleRowTest
- Wave 2 (plan 02-05/06): PlayerPrivacyGateTest, PlayerProfileDataTest
- Wave 3 (plan 02-07/08): ClanDirectoryTest, ClanShowTest, PublicClanRoutesTest, PlayerProfilePrivacyTest
- Wave 4 (plan 02-09/10/11): MyClanManagementTest, ClanInviteTest, ClanApplicationTest
- Wave 5 (plan 02-12/13): ClanResourcesPresentTest, ClanFilamentResourceTest

All 6 factory stubs throw RuntimeException — intentional Wave 0 guard per T-02-00-03.

## Self-Check: PASSED
