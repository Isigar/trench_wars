---
phase: 05-discord-bot-v1
plan: 01
subsystem: discord-bot
tags: [wave-0, scaffolding, sanctum, horizon, red-stubs, i18n]
dependency_graph:
  requires: [phase-04-complete]
  provides: [sanctum-installed, horizon-installed, worker-service, bot-deps, wave-0-red-baseline]
  affects: [05-02, 05-03, 05-04, 05-05, 05-06, 05-07, 05-09, 05-10, 05-11]
tech_stack:
  added:
    - "laravel/sanctum v4.3.2 (PAT auth + abilities for bot↔web)"
    - "laravel/horizon v5.46.0 (Redis queue dashboard + retry/backoff)"
    - "discord.js ^14.26 (Discord gateway + REST + slash commands; runtime dep)"
    - "undici ^7 (HTTP client for bot→web Sanctum-authenticated calls; runtime dep)"
    - "ioredis ^5.10 (Redis client for bot; runtime dep; forward-compat reservation)"
  patterns:
    - "Wave 0 RED stub baseline: bare `it()->markTestIncomplete()` form (Phase 4 commit 8435020 canonical idiom — Pest.php autowires TestCase + RefreshDatabase via `uses(...)->in(...)`)"
    - "Factory stub idiom: string FQN $model + per-line @phpstan-ignore (Phase 4 04-01 commit 6e5024c precedent; CLAUDE.md §3 forbids baseline regen)"
    - "docker-compose worker service shares web_vendor volume + reuses docker/web/Dockerfile for D-021 dev-prod parity (Pitfall 8 recommendation A)"
    - "Container healthcheck pattern: /proc/1/cmdline inspection (busybox-portable; pgrep absent in php-fpm alpine image)"
key_files:
  created:
    - "apps/web/routes/api.php (install:api scaffold)"
    - "apps/web/config/horizon.php (horizon:install)"
    - "apps/web/config/sanctum.php (vendor:publish SanctumServiceProvider)"
    - "apps/web/database/migrations/2026_05_13_164841_create_personal_access_tokens_table.php"
    - "apps/web/app/Providers/HorizonServiceProvider.php (horizon:install auto-registered)"
    - "apps/web/database/factories/DiscordOutboundMessageFactory.php (Wave 0 stub)"
    - "apps/web/lang/en/bot.php (14 keys — 9 errors + 5 embeds)"
    - "apps/web/tests/Feature/Bot/*.php (11 Pest RED stubs)"
    - "apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php (1 model stub)"
    - "apps/bot/tests/{commands,components,events,services,lib}/*.test.ts (9 Vitest RED stubs / 22 it.todo entries)"
  modified:
    - "apps/web/composer.json + composer.lock (sanctum + horizon)"
    - "apps/web/bootstrap/app.php (api: parameter wired manually after install:api WARN)"
    - "apps/web/bootstrap/providers.php (HorizonServiceProvider registered + pint auto-fix)"
    - "apps/web/.env.example (WEB_API_TOKEN= empty + SANCTUM_STATEFUL_DOMAINS= empty)"
    - "apps/web/lang/en/admin.php (discord_outbound_message group appended)"
    - "docker-compose.yml (worker service added; healthcheck Rule 1 fix)"
    - "apps/bot/package.json + repo-root pnpm-lock.yaml (discord.js/undici/ioredis)"
    - "CLAUDE.md (§2 stack table: sanctum + horizon + bot row upgrade)"
decisions:
  - "D-05-01-A: laravel/sanctum v4.3.2 (latest 4.x line; Laravel 12 compatible) — pinned via `composer require` semver caret"
  - "D-05-01-B: laravel/horizon v5.46.0 (latest 5.x line) — pinned via `composer require` semver caret"
  - "D-05-01-C: Wave 0 Pest stub idiom = canonical Phase 4 commit 8435020 bare form (no namespace, no per-file uses() call). The plan <interfaces> snippet was wrong — applying its idiom triggered a TestRepository fatal error on this project's Pest.php config. Documented as Rule 3 deviation."
  - "D-05-01-D: apps/bot/pnpm-lock.yaml does NOT exist — workspace lockfile is at repo root (D-015 pnpm-workspaces). Plan's files_modified entry was incorrect; documented as Rule 3 deviation."
  - "D-05-01-E: Worker container healthcheck uses /proc/1/cmdline inspection (`tr '\\0' ' ' < /proc/1/cmdline | grep -q 'artisan horizon'`) instead of pgrep — pgrep is from procps, not installed in php-fpm Alpine images."
  - "D-05-01-F: SANCTUM_STATEFUL_DOMAINS explicitly empty in .env.example (T-05-01-05 Pitfall 4 mitigation defence-in-depth on top of the framework default which already excludes bot/nginx hostnames)."
metrics:
  duration_seconds: ~900
  completed_date: "2026-05-13"
  tasks_total: 2
  tasks_completed: 2
  commits: 3
  files_changed: 39
---

# Phase 5 Plan 01: Wave 0 — Sanctum/Horizon install + worker compose entry + RED stubs Summary

Wave 0 scaffolding for Phase 5 complete. Installed laravel/sanctum (v4.3.2) and laravel/horizon (v5.46.0) into the web app, wired `install:api` (Laravel 12's API scaffold), published Sanctum config + personal_access_tokens migration, added a `worker` service to docker-compose.yml running `php artisan horizon` against the same image as `web` (D-021 dev-prod parity), installed discord.js@^14.26 + undici@^7 + ioredis@^5.10 as bot runtime deps, and committed the Wave 0 RED baseline: 12 web Pest stubs + 9 Vitest stub files (22 it.todo entries) + 1 DiscordOutboundMessageFactory stub + apps/web/lang/en/bot.php (14 keys) + admin.php appendix (discord_outbound_message group).

## Acceptance Criteria

### Task 1 — Sanctum + Horizon + worker compose service (commit e0be595)

- [x] `composer require laravel/sanctum` → v4.3.2 (latest 4.x, Laravel 12 compatible)
- [x] `composer require laravel/horizon` → v5.46.0 (latest 5.x line)
- [x] `php artisan install:api` → `routes/api.php` created (template GET /user endpoint + Sanctum-auth middleware); personal_access_tokens migration shipped + applied
- [x] `bootstrap/app.php` withRouting() now has `api: __DIR__ . '/../routes/api.php'`. **Deviation:** Laravel 12 `install:api` printed `WARN  Unable to automatically add API route definition to [bootstrap/app.php]` — manual edit was required (Rule 3 — blocking).
- [x] `php artisan horizon:install` → `config/horizon.php` published; `App\Providers\HorizonServiceProvider` created + auto-registered in `bootstrap/providers.php`
- [x] `php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"` → `config/sanctum.php` already-existed (auto-published by install:api); a duplicate `*_create_personal_access_tokens_table.php` migration was created by the second publish and removed inline (Rule 3 fix).
- [x] `php artisan migrate` → personal_access_tokens table created
- [x] `docker-compose.yml` worker service added between `web-nginx` and `bot`; reuses `docker/web/Dockerfile`; mounts `./apps/web:/app + web_vendor:/app/vendor`; command `php artisan horizon`; depends_on postgres healthy + redis healthy
- [x] `docker compose up -d worker` → `Up (healthy)` after Rule 1 healthcheck fix (commit 620c82d)
- [x] **Pitfall 4 verified clean:** `php artisan config:show sanctum.stateful` returns `[localhost, localhost:3000, 127.0.0.1, 127.0.0.1:8000, ::1, localhost:8000]` — NO web-nginx, NO bot, NO trenchwars-bot in the list (T-05-01-05 mitigated)
- [x] `.env.example`: empty `WEB_API_TOKEN=` (T-05-01-02 — no token-shaped placeholder) + empty `SANCTUM_STATEFUL_DOMAINS=` (defence-in-depth)
- [x] CLAUDE.md §2 Stack and Versions table updated with `laravel/sanctum ^4.0` and `laravel/horizon ^5` rows; bot row upgraded to "(Phase 5 active) Node 22 + discord.js@^14.26 + undici@^7 + ioredis@^5.10 + TypeScript strict"
- [x] `pint --test bootstrap/app.php` → PASS
- [x] `phpstan analyse` → No errors

### Task 2 — 20 Wave 0 RED stubs + bot deps + i18n (commit 242e78f)

- [x] `apps/bot/package.json`: discord.js ^14.26, undici ^7, ioredis ^5.10 added as runtime dependencies (NOT devDependencies)
- [x] Repo-root `pnpm-lock.yaml` updated with the 31 new packages. **Deviation:** plan's `files_modified` listed `apps/bot/pnpm-lock.yaml` but the project uses workspace-level locking (D-015 pnpm-workspaces, single root lockfile); that file does not exist and never will (Rule 3 — blocking).
- [x] 11 Pest stubs in `tests/Feature/Bot/` + 1 in `tests/Feature/Models/DiscordOutboundMessageModelTest.php` — each contains `<?php declare(strict_types=1);` + header comment citing SC and replacing plan + `it('placeholder — replace in plan 05-NN', fn () => $this->markTestIncomplete(...));`. **Deviation:** plan `<interfaces>` snippet included `namespace Tests\\Feature\\Bot;` + `uses(TestCase::class, RefreshDatabase::class)` — applying that idiom triggered `Tests\\TestCase can not be used. The folder ... already uses the test case` fatal because the project's `apps/web/tests/Pest.php` autowires both via `uses(TestCase::class)->in('Feature', 'Unit')` + `uses(RefreshDatabase::class)->in('Feature')` (Phase 1 setup). Aligned to the canonical Phase 4 commit 8435020 bare form. Rule 3 — blocking.
- [x] 9 Vitest stub files under `apps/bot/tests/{commands,components,events,services,lib}/` — each contains `import { describe, it } from 'vitest'` + describe block with one or more `it.todo` entries naming the behaviour and the replacing plan number (22 todos total)
- [x] `apps/web/database/factories/DiscordOutboundMessageFactory.php` — Phase 4 04-01 idiom: string FQN `'App\\\\Models\\\\DiscordOutboundMessage'`, `@phpstan-ignore-next-line missingType.generics` + `property.defaultValue`, `definition()` throws `RuntimeException('placeholder — replaced by plan 05-02')`
- [x] `apps/web/lang/en/bot.php` exists with 14 keys (9 in errors group + 5 in embeds group) verbatim per plan `<interfaces>` block
- [x] `apps/web/lang/en/admin.php` appended with `discord_outbound_message` group (label, plural_label, 7 fields, 2 actions, 4 statuses) — preserved existing 9 top-level keys
- [x] `make pest --filter='Bot|DiscordOutbound'` → **12 incomplete + 1 passed (5 assertions)** — RED baseline established (the "1 passed" is plan-stage-independent test in MatchSlot covering "discord" in slot relation grep — false positive on filter)
- [x] `vitest run` → 22 todo entries + 2 passed (existing skeleton.test.ts) across 10 test files; exit 0
- [x] `make pest` (full suite) → **493 passed (1459 assertions) + 12 incomplete** — no regressions from Phase 4 close baseline (493/1459 exactly preserved); Wave 0 adds 12 new incomplete markers
- [x] `make phpstan` → No errors (CLAUDE.md §3 baseline kept untouched)
- [x] `make pint --test` → PASS on 314 files (after Pint auto-fix on 3 files: bootstrap/providers.php, config/horizon.php, DiscordOutboundMessageFactory.php — Rule 1)

### Bonus: Task 1 healthcheck Rule 1 fix (commit 620c82d)

- [x] Worker container originally reported `unhealthy` even though Horizon master was running as PID 1 — root cause: `pgrep` is from `procps` package, not installed in php-fpm Alpine images
- [x] Replaced healthcheck with `/proc/1/cmdline` inspection: `tr '\\0' ' ' < /proc/1/cmdline | grep -q 'artisan horizon'` (POSIX-portable, busybox tr+grep both present)
- [x] `docker compose ps worker` → `Up 42 seconds (healthy)`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Laravel 12 `install:api` could not modify bootstrap/app.php**
- **Found during:** Task 1
- **Issue:** `php artisan install:api` printed `WARN  Unable to automatically add API route definition to [bootstrap/app.php]. API route file should be registered manually.` — the auto-modifier failed (likely because of the trailing `])->create();` pattern that the AST mutator didn't recognise).
- **Fix:** Manually edited `bootstrap/app.php`'s `withRouting(...)` call to add `api: __DIR__ . '/../routes/api.php',` immediately after the `web:` parameter (canonical Laravel 12 idiom).
- **Files modified:** `apps/web/bootstrap/app.php`
- **Commit:** e0be595

**2. [Rule 3 - Blocking] Duplicate personal_access_tokens migration after Sanctum vendor:publish**
- **Found during:** Task 1
- **Issue:** `install:api` already shipped one PAT migration; running `vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"` later created a second `*_create_personal_access_tokens_table.php` file 21 seconds later. Both ran at migrate time would clash on `CREATE TABLE`.
- **Fix:** Deleted the duplicate migration before `php artisan migrate`. Single canonical migration retained.
- **Files removed:** `apps/web/database/migrations/2026_05_13_164902_create_personal_access_tokens_table.php`
- **Commit:** e0be595

**3. [Rule 3 - Blocking] Plan `<interfaces>` Pest stub idiom conflicts with project's Pest.php autowiring**
- **Found during:** Task 2 verification
- **Issue:** Plan specified stub idiom `namespace Tests\\Feature\\Bot; uses(TestCase::class, RefreshDatabase::class)` per file. Project's `apps/web/tests/Pest.php` (from Phase 1 plan 01-05) already autowires both via `uses(TestCase::class)->in('Feature', 'Unit')` + `uses(RefreshDatabase::class)->in('Feature')`. Re-applying the `uses()` call duplicates the wiring and triggers a `Tests\\TestCase can not be used. The folder ... already uses the test case` fatal at TestRepository load time.
- **Fix:** Rewrote all 12 web Pest stubs to follow the canonical Phase 4 commit 8435020 idiom — bare `<?php declare(strict_types=1);` + header comment + `it('placeholder — replace in plan 05-NN', function (): void { $this->markTestIncomplete(...); });`. No namespace, no `uses()` call (Pest.php autowires).
- **Files affected:** all 12 Pest stub files
- **Commit:** 242e78f

**4. [Rule 3 - Blocking] `apps/bot/pnpm-lock.yaml` does not exist (workspace lockfile is at repo root)**
- **Found during:** Task 2 staging
- **Issue:** Plan's `files_modified` listed `apps/bot/pnpm-lock.yaml`. The repo uses pnpm-workspaces (D-015) — there is a single root `pnpm-lock.yaml` that covers all packages (apps/web, apps/bot, apps/rcon-worker, packages/shared-types). Per-package lockfiles are never generated.
- **Fix:** Committed the modified root `pnpm-lock.yaml` instead. Recorded as decision D-05-01-D.
- **Files affected:** `pnpm-lock.yaml` (root)
- **Commit:** 242e78f

**5. [Rule 1 - Bug] `*/` token embedded in stub header comment caused PHP parse error**
- **Found during:** Task 2 pest run
- **Issue:** Initial draft of `BotApiMatchSignupAbilitiesTest.php` header read "for /api/bot/matches/*/signups" — the literal `*/` in the URL pattern terminated the surrounding `/* … */` doc comment early, producing `syntax error, unexpected token "|"` at line 9.
- **Fix:** Replaced `/api/bot/matches/*/signups` with `/api/bot/matches/{id}/signups` (functionally identical phrasing).
- **Files affected:** `apps/web/tests/Feature/Bot/BotApiMatchSignupAbilitiesTest.php`
- **Commit:** 242e78f

**6. [Rule 1 - Bug] Pint auto-fixes on three Task 1 files (style ripple)**
- **Found during:** Task 2 `pint --test` run
- **Issue:** After Task 1's installs, three files violated Pint's default Laravel preset:
  - `bootstrap/providers.php` — `fully_qualified_strict_types` + `single_line_after_imports` (HorizonServiceProvider was inserted as fully-qualified `App\\Providers\\HorizonServiceProvider::class` in the array literal but the file had no `use` imports)
  - `config/horizon.php` — `concat_space` (Horizon's published file used `Str::slug(...).'_horizon:'` without spaces)
  - `DiscordOutboundMessageFactory.php` — `phpdoc_separation`
- **Fix:** Ran `./vendor/bin/pint` on the three files; auto-fix produced canonical formatting. Phpstan still clean after the fix; tests still RED.
- **Files modified:** 3 files
- **Commit:** 242e78f

**7. [Rule 1 - Bug] Worker container healthcheck never went healthy — `pgrep` absent**
- **Found during:** SUMMARY verification (post-Task-2)
- **Issue:** Worker container ran Horizon correctly as PID 1, but `docker compose ps worker` reported `unhealthy` because the plan-prescribed healthcheck `pgrep -f 'artisan horizon'` failed with `pgrep: executable file not found in $PATH` — the `procps` package is not installed in the php-fpm Alpine base image.
- **Fix:** Replaced healthcheck with `tr '\\0' ' ' < /proc/1/cmdline | grep -q 'artisan horizon'` (POSIX-portable; busybox provides `tr` and `grep`). Container now reports `Up (healthy)` within `start_period`.
- **Files modified:** `docker-compose.yml`
- **Commit:** 620c82d (Rule 1 follow-up)

### Authentication Gates

None — no live Discord / external auth required for Wave 0.

## Files Created/Modified

```
38 files changed, 1337 insertions(+), 3 deletions(-)
```

### Created (28)

```
apps/web/app/Providers/HorizonServiceProvider.php
apps/web/config/horizon.php
apps/web/config/sanctum.php
apps/web/database/migrations/2026_05_13_164841_create_personal_access_tokens_table.php
apps/web/routes/api.php
apps/web/database/factories/DiscordOutboundMessageFactory.php
apps/web/lang/en/bot.php
apps/web/tests/Feature/Bot/BotApiAuthMatrixTest.php
apps/web/tests/Feature/Bot/BotApiMatchSignupAbilitiesTest.php
apps/web/tests/Feature/Bot/BotApiMatchSignupTest.php
apps/web/tests/Feature/Bot/BotApiOutboundAckTest.php
apps/web/tests/Feature/Bot/BotApiOutboundClaimTest.php
apps/web/tests/Feature/Bot/BotApiUserMeTest.php
apps/web/tests/Feature/Bot/DiscordEventRoleChangeEchoSuppressionTest.php
apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php
apps/web/tests/Feature/Bot/ResolveBotActsAsUserMiddlewareTest.php
apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php
apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php
apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php
apps/bot/tests/commands/clan.test.ts
apps/bot/tests/commands/match.test.ts
apps/bot/tests/commands/profile.test.ts
apps/bot/tests/components/rsvpButton.test.ts
apps/bot/tests/components/signupModal.test.ts
apps/bot/tests/events/guildMemberUpdate.test.ts
apps/bot/tests/lib/customIds.test.ts
apps/bot/tests/lib/embeds.test.ts
apps/bot/tests/services/outbound.test.ts
```

### Modified (10)

```
CLAUDE.md                          (§2 stack table — sanctum + horizon + bot row upgrade)
apps/web/.env.example              (WEB_API_TOKEN=, SANCTUM_STATEFUL_DOMAINS=)
apps/web/bootstrap/app.php         (api: parameter in withRouting)
apps/web/bootstrap/providers.php   (HorizonServiceProvider + pint auto-fix)
apps/web/composer.json             (sanctum ^4.3 + horizon ^5.46)
apps/web/composer.lock             (sanctum + horizon trees)
apps/web/config/horizon.php        (pint concat_space auto-fix)
apps/web/lang/en/admin.php         (discord_outbound_message group appended)
apps/bot/package.json              (discord.js + undici + ioredis runtime deps)
pnpm-lock.yaml                     (workspace lockfile — 31 new entries)
docker-compose.yml                 (worker service + Rule 1 healthcheck fix)
```

## Per-Stub Mapping (for plans 05-02..05-13 cross-reference)

| Stub | File | SC | Replaced by plan |
|------|------|----|------------------|
| BotApiMatchSignupTest | apps/web/tests/Feature/Bot/BotApiMatchSignupTest.php | SC-2 | 05-04 |
| BotApiMatchSignupAbilitiesTest | apps/web/tests/Feature/Bot/BotApiMatchSignupAbilitiesTest.php | SC-5 | 05-04 |
| BotApiOutboundClaimTest | apps/web/tests/Feature/Bot/BotApiOutboundClaimTest.php | SC-3 | 05-04 |
| BotApiOutboundAckTest | apps/web/tests/Feature/Bot/BotApiOutboundAckTest.php | SC-3 | 05-04 |
| BotApiUserMeTest | apps/web/tests/Feature/Bot/BotApiUserMeTest.php | SC-1 | 05-04 |
| BotApiAuthMatrixTest | apps/web/tests/Feature/Bot/BotApiAuthMatrixTest.php | SC-5 | 05-03 |
| DiscordEventRoleChangeEchoSuppressionTest | apps/web/tests/Feature/Bot/DiscordEventRoleChangeEchoSuppressionTest.php | SC-4 | 05-04 |
| ResolveBotActsAsUserMiddlewareTest | apps/web/tests/Feature/Bot/ResolveBotActsAsUserMiddlewareTest.php | SC-5 | 05-03 |
| SyncDiscordRolesJobTest | apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php | SC-4 | 05-06 |
| DiscordOutboundOnMatchCreateTest | apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php | SC-3 | 05-05 |
| SyncDiscordRolesJobDispatchTest | apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php | SC-4 | 05-06 |
| DiscordOutboundMessageModelTest | apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php | SC-3 | 05-02 |
| match.test.ts | apps/bot/tests/commands/match.test.ts | SC-1/SC-2 | 05-09 |
| clan.test.ts | apps/bot/tests/commands/clan.test.ts | SC-1 | 05-09 |
| profile.test.ts | apps/bot/tests/commands/profile.test.ts | SC-1 | 05-09 |
| rsvpButton.test.ts | apps/bot/tests/components/rsvpButton.test.ts | SC-3 | 05-10 |
| signupModal.test.ts | apps/bot/tests/components/signupModal.test.ts | SC-2 | 05-10 |
| outbound.test.ts | apps/bot/tests/services/outbound.test.ts | SC-3 | 05-11 |
| guildMemberUpdate.test.ts | apps/bot/tests/events/guildMemberUpdate.test.ts | SC-4 | 05-11 |
| customIds.test.ts | apps/bot/tests/lib/customIds.test.ts | SC-3 | 05-10 |
| embeds.test.ts | apps/bot/tests/lib/embeds.test.ts | SC-1/SC-3 | 05-10 |

## Commits

| Commit | Type | Description |
|--------|------|-------------|
| e0be595 | feat | Install Sanctum + Horizon + add worker compose service |
| 242e78f | test | 20 Wave 0 RED stubs + discord.js/undici/ioredis bot deps + bot.php + admin.php appendix |
| 620c82d | fix | Worker healthcheck uses /proc/1/cmdline instead of pgrep |

## Verification Snapshot

```text
$ docker compose exec web ./vendor/bin/pest --filter='Bot|DiscordOutbound' --no-coverage
Tests:    12 incomplete, 1 passed (5 assertions)
Duration: 1.18s

$ docker compose exec web ./vendor/bin/pest --no-coverage
Tests:    12 incomplete, 493 passed (1459 assertions)
Duration: 23.30s

$ vitest run  (from apps/bot)
Test Files  1 passed | 9 skipped (10)
     Tests  2 passed | 22 todo (24)

$ docker compose exec web ./vendor/bin/pint --test
PASS  314 files

$ docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress
[OK] No errors

$ docker compose ps worker --format '{{.Status}}'
Up 42 seconds (healthy)

$ docker compose exec web php artisan config:show sanctum.stateful
sanctum.stateful
  0 .......... localhost
  1 .......... localhost:3000
  2 .......... 127.0.0.1
  3 .......... 127.0.0.1:8000
  4 .......... ::1
  5 .......... localhost:8000
(no web-nginx, bot, or trenchwars-bot — Pitfall 4 verified clean)
```

## Self-Check: PASSED

- [x] All 28 created files exist
- [x] All 3 commits exist in `git log --oneline -5`
- [x] Sanctum + Horizon installed and bound to expected versions
- [x] Worker container healthy
- [x] RED baseline established (12 web + 22 bot todos)
- [x] No phpstan errors / pint failures / pest regressions
