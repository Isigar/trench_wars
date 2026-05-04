# Phase 1 — Foundations — Verification Report

**Date:** 2026-05-04
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual smoke section)

---

## [BLOCKING] Schema push — RESULT: PASS

`docker compose exec web php artisan migrate --force` against a freshly-recreated `trenchwars` database.

**Migrations applied (7 total, all from plans 04, 10, 11, 14):**

| # | Migration | Source plan | Time |
|---|-----------|-------------|------|
| 1 | `0001_01_01_000000_enable_postgres_extensions` | 01-04 | 24.98ms |
| 2 | `2026_05_03_100000_create_users_table` | 01-10 | 36.39ms |
| 3 | `2026_05_03_100100_create_players_table` | 01-10 | 45.75ms |
| 4 | `2026_05_03_100200_create_player_privacy_table` | 01-10 | 38.19ms |
| 5 | `2026_05_03_110000_create_permission_tables` | 01-11 | 53.49ms |
| 6 | `2026_05_03_140000_create_activity_log_table` | 01-14 | 18.95ms |
| 7 | `2026_05_03_140100_add_uuid_columns_to_activity_log` | 01-14 | 29.72ms |

**Postgres extensions** (`SELECT extname FROM pg_extension`): `citext`, `pgcrypto`, `plpgsql` (default), `uuid-ossp` — 3/3 required extensions present per `0001_01_01_000000_enable_postgres_extensions`.

**Tables present** (`\dt` in trenchwars DB):

| Table | Owner |
|---|---|
| `activity_log` | trenchwars |
| `migrations` | trenchwars |
| `model_has_permissions` | trenchwars |
| `model_has_roles` | trenchwars |
| `permissions` | trenchwars |
| `player_privacy` | trenchwars |
| `players` | trenchwars |
| `role_has_permissions` | trenchwars |
| `roles` | trenchwars |
| `users` | trenchwars |

**Note:** Laravel-default tables (`sessions`, `password_reset_tokens`, `personal_access_tokens`, `failed_jobs`, `cache`, `jobs`) are intentionally absent — deleted in plan 01-04 Task 2 step 6 (D-002 OAuth-only auth means no password resets; sessions are file-driver in P1; sanctum personal-access-tokens are deferred). All 9 expected business tables from plan task 1 acceptance criteria are present.

**Seeders** (`docker compose exec web php artisan db:seed --force`):

```
INFO  Seeding database.
Database\Seeders\PermissionSeeder ............... DONE (2,033 ms)
```

**Seeded permissions:**

| name | guard_name |
|---|---|
| `admin-access` | web |
| `audit.view` | web |

**Seeded roles:**

| name | guard_name |
|---|---|
| `cms-editor` | web |
| `super-admin` | web |

**Acceptance criteria — all PASS:**

- [x] migrate --force succeeded on fresh DB (7 migrations, 0 failures)
- [x] 9 business tables present in trenchwars DB
- [x] 3 required postgres extensions enabled (uuid-ossp, pgcrypto, citext)
- [x] PermissionSeeder ran; 2 permissions + 2 roles seeded with `guard_name='web'`

---

## Quality gates — RESULT: PASS

| Gate | Command | Result |
|---|---|---|
| Pest (full, parallel) | `docker compose exec web ./vendor/bin/pest --parallel` | **54 passed** (161 assertions), 0 failed, 2.53s @ 24 procs |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 91 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=2G` | **`[OK] No errors`** (baseline + 0 new findings) |
| Vite main build | `pnpm run build` (Tailwind v4 site) | **PASS** — `apps/web/public/build/manifest.json` (7.34 kB), `app-yvPOvLO9.js` 264.82 kB / `app-JkEqP9d4.css` 65.91 kB, 2492 modules transformed in 2.57s |
| Vite filament build | `vite build --config vite.filament.config.ts` (Tailwind v3 theme) | **PASS** — `apps/web/public/build/filament/manifest.json` (0.23 kB), `theme-5RoEH-4l.css` 110.07 kB, 994ms |
| `@trenchwars/shared-types` typecheck | `pnpm --filter @trenchwars/shared-types typecheck` | **PASS** (after Rule 3 fix — see Deviations) |
| `@trenchwars/bot` typecheck | `pnpm --filter @trenchwars/bot typecheck` | **PASS** |
| `@trenchwars/bot` lint | `pnpm --filter @trenchwars/bot lint` | **PASS** (eslint clean) |
| `@trenchwars/bot` test | `pnpm --filter @trenchwars/bot test` | **PASS** — 2/2 vitest tests, 368ms |
| `@trenchwars/rcon-worker` typecheck | `pnpm --filter @trenchwars/rcon-worker typecheck` | **PASS** |
| `@trenchwars/rcon-worker` lint | `pnpm --filter @trenchwars/rcon-worker lint` | **PASS** (eslint clean) |
| `@trenchwars/rcon-worker` test | `pnpm --filter @trenchwars/rcon-worker test` | **PASS** — 2/2 vitest tests, 380ms |

**Pest test composition** (54 tests total, expected ≈30-40 per plan; actual count exceeds because plans 11/13/14/15 added tests too):

| Source plan | Test files (Feature/Unit) |
|---|---|
| 01-05 | bootstrap (`ExampleTest`, `Pest.php` config) |
| 01-06 | Inertia + Vite scaffold (`InertiaResolverTest` etc) |
| 01-07 | Vue/Tailwind front-end smoke |
| 01-08 | i18n (`NoHardcodedStringsTest`, `ValidationMessagesLocalizedTest`, `LocaleResolutionTest`) |
| 01-09 | Discord OAuth happy + invalid-state + error paths |
| 01-10 | Users/Players/PlayerPrivacy schema + first-login mint test |
| 01-11 | Spatie permission + admin-access role gate |
| 01-12 | Filament panel registration + `RedirectFilamentAuthToDiscord` |
| 01-13 | `FilamentResourcesPresentTest` (5 tests covering all 4 resources) |
| 01-14 | Activity-log on User/Player; AuditPage gate |
| 01-15 | DTO generation + cross-package shared-types sync |

**PHPStan baseline note**: per RESEARCH Pitfall 9 (Filament v3 PHP 8.4 deprecation noise), `apps/web/phpstan-baseline.neon` absorbs vendor-internal deprecation traces. Baseline is regenerated only with explicit user request (CLAUDE.md §3); current run reports `[OK] No errors`, meaning **zero new findings** outside baseline.

**Frontend build manifests verified on disk:**

```
-rw-r--r-- root root  228 May  4 20:45 apps/web/public/build/filament/manifest.json
-rw-r--r-- root root 7338 May  4 20:45 apps/web/public/build/manifest.json
```

Dual-Tailwind workaround (Pitfall 1) confirmed: two separate manifests, two distinct asset trees, no Tailwind v3↔v4 cross-bleed at build time. Visual confirmation deferred to manual smoke (Task 3).

---

## Manual smoke checklist — RESULT: PENDING (manual smoke required by user)

The plan declares `autonomous: false` because two smoke checks require human visual + interactive verification. Per the user's direction (autonomous-mode delegation), Phase 1 is **not blocked** on these — they are documented here for the operator to execute after phase 1 closes.

### A. [PENDING — manual smoke required] Filament dual-Tailwind theme visual check (Pitfall 1)

**Why this can't be automated:** automated tests catch broken HTML / missing routes / unstyled assertions, but they cannot regress on visual CSS correctness. Filament v3 + Tailwind v4 (main) + Tailwind v3 (filament theme via `tailwindcss-v3` alias) is the highest-risk pitfall in the phase. Both bundles built cleanly above (manifests on disk, manifest sizes sensible, theme CSS 110 kB) — but only a human eyeball confirms the theme **applies correctly to /admin**.

**Reproduction steps for the operator:**

1. `make up` (stack already up — verified at execution time).
2. Browser: open <http://localhost:8000/admin>. (You will be redirected to Discord OAuth — see step 3 below.)
3. Complete Discord OAuth login (real Discord developer app credentials in `apps/web/.env` — `DISCORD_CLIENT_ID` + `DISCORD_CLIENT_SECRET` + redirect URI registered as `http://localhost:8000/auth/discord/callback`). After landing, run `make artisan ARGS="trenchwars:make-admin <YOUR_DISCORD_USER_ID>"` from the repo root to grant admin-access.
4. Refresh /admin in the browser.
5. Verify each of these pass:
   - [ ] Form labels render (not raw HTML defaults)
   - [ ] Input borders visible
   - [ ] Primary buttons filled in trench-red (#A4262C)
   - [ ] Table row hover shows surface-elevated bg
   - [ ] Sidebar nav lists User, Player, Role, Permission, Audit log
   - [ ] Dark theme is the default (panel chrome matches public site — dark olive bg, off-white text)
   - [ ] Light theme toggle (Filament's built-in) flips cleanly
   - [ ] No completely-unstyled elements (would indicate Tailwind v3 theme bundle didn't load)
6. If any item fails, the dual-Tailwind workaround (Pitfall 1) is broken — open a Phase 1 hotfix plan via `/gsd-plan-phase --gaps`.

### B. [PENDING — manual smoke required] Discord OAuth real-world happy path

**Why this can't be automated:** Pest tests use `Socialite::shouldReceive(...)` mocks. The live path against `https://discord.com/api/oauth2/authorize` (state-CSRF + redirect_uri exact match — Pitfall 2) cannot be exercised from CI without leaking real OAuth credentials.

**Reproduction steps for the operator:**

1. Log out (clear session cookie or POST to `/auth/logout` if a button exists).
2. Browser: open <http://localhost:8000/>. Click **Log in with Discord**.
3. Authorise the OAuth screen with `identify` + `email` scopes.
4. Verify you land back on `/` with a "Signed in as ..." flash + logged-in greeting.
5. Open postgres:
   ```bash
   docker compose exec postgres psql -U trenchwars -d trenchwars \
     -c "SELECT discord_id, username, last_login_at FROM users;"
   ```
   Expect: exactly 1 row.
6. Re-log out, re-log in with the same Discord account. Re-run the query: still **exactly 1 row**, `last_login_at` updated.
7. Confirm `players` and `player_privacy` each have exactly 1 row:
   ```bash
   docker compose exec postgres psql -U trenchwars -d trenchwars \
     -c "SELECT count(*) FROM players; SELECT count(*) FROM player_privacy;"
   ```

### C. [PENDING — manual smoke required] /admin/audit reflects admin mutations

1. As admin (via step A.3 above), navigate to /admin/users → edit any user → change `username` → save.
2. Open /admin/audit. Verify the **`updated`** event row appears with your causer name + the changed-fields preview.

### Operator outcome line (to be filled in by user)

| Check | Result | Notes |
|---|---|---|
| A. Filament dual-Tailwind theme | _PENDING_ | _(operator fills after smoke)_ |
| B. Discord OAuth real-app happy path | _PENDING_ | _(operator fills after smoke)_ |
| C. /admin/audit shows admin mutations | _PENDING_ | _(operator fills after smoke)_ |

**Phase 1 status (post-smoke):** _(operator marks COMPLETE or BLOCKED-ON-FIX)_

---

## M1..M10 must-have traceability

The plan frontmatter `must_haves.truths` enumerates the 7 binding M-criteria for phase close. Mapping to results:

| M# | Must-have | Source | Result |
|---|---|---|---|
| M1 | `[BLOCKING] migrate --force` runs successfully on fresh DB; all migrations from plans 04, 10, 11, 14 applied | Schema push section | **PASS** (7 migrations, all DONE) |
| M2 | Full Pest suite green | Pest gate | **PASS** (54/54) |
| M3 | Pint reports zero formatting issues | Pint gate | **PASS** (91 files) |
| M4 | PHPStan L8 reports zero new findings (baseline-allowed only) | PHPStan gate | **PASS** (`[OK] No errors`) |
| M5 | Frontend `pnpm run build` succeeds for both bundles (main + filament) | Vite gates (rows 4, 5) | **PASS** (both manifests on disk) |
| M6 | Manual smoke — Filament theme renders correctly + Discord OAuth happy path | Manual smoke A + B | **PENDING** (deferred to operator per autonomous-mode handoff; see steps above) |
| M7 | This document captures M1..M10 | — | **PASS** (you're reading it) |

**Bonus M-criteria from ROADMAP SC-1..SC-5** (covered in passing by the above gates):

| ROADMAP SC | Mapping |
|---|---|
| SC-1 (auth happy path) | M6.B PENDING; mocked happy path M2 PASS |
| SC-2 (admin panel works) | M5 PASS (build); M6.A PENDING (visual) |
| SC-3 (audit log writes) | M2 PASS (`AuditPageTest`); M6.C PENDING (live) |
| SC-4 (i18n end-to-end) | M2 PASS (`NoHardcodedStringsTest` enforces no literals; `LocaleResolutionTest` covers resolution order) |
| SC-5 (CI green from day 1) | M2/M3/M4 PASS automated; CI workflows shipped in plan 01-16 |

---

## Deviations from plan

### Auto-fixed issues

**1. [Rule 3 — Blocking issue] Added `@types/node` to `packages/shared-types` devDependencies**

- **Found during:** Task 2 (running `pnpm --filter @trenchwars/shared-types typecheck` for the bot/rcon-worker pipeline gate)
- **Symptom:** `error TS2688: Cannot find type definition file for 'node'.` — `tsconfig.base.json` declares `"types": ["node"]`, but `packages/shared-types/package.json` did not list `@types/node` as a devDependency. Same root cause for `pnpm --filter ... build` (which generates `dist/index.d.ts`, in turn required for `@trenchwars/bot` to typecheck).
- **Fix:** Added `"@types/node": "^22.0.0"` to `packages/shared-types/package.json` devDependencies (matching the version pinned by `apps/bot` and `apps/rcon-worker`).
- **Why Rule 3 (not Rule 4 architectural):** Pre-existing config gap inherited from plan 01-15 — every workspace that extends `tsconfig.base.json` already declares `@types/node` except shared-types. Strictly additive; no architectural decision affected.
- **Files modified:** `packages/shared-types/package.json`
- **Side-effect:** `pnpm-lock.yaml` populated at repo root for the first time (no prior committed lockfile; CI workflows already use `pnpm install --no-frozen-lockfile`, but committing the lockfile improves reproducibility).
- **Commit:** _(this commit — recorded below at Self-Check)_

