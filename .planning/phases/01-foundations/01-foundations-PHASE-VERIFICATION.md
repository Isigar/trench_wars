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

## Quality gates — RESULT: _(filled in by Task 2)_

_(populated after `make pest` / `make pint` / `make phpstan` / `pnpm run build` / bot+rcon-worker pipelines run.)_

---

## Manual smoke checklist — RESULT: _(filled in by Task 3)_

_(populated after Filament dual-Tailwind visual check + Discord OAuth real-app happy path.)_
