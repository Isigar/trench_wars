---
phase: 03-games-match-types
plan: 02
subsystem: database/migrations
tags: [migrations, postgres, schema, games, game_roles, game_match_types, game_match_type_role_limits, d-007, wave-1]
requires:
  - "Phase 2 migrations (clans / clan_tags / clan_memberships) — pattern source"
  - "Postgres 16 with gen_random_uuid()"
  - "spatie/laravel-translatable (jsonb columns target HasTranslations)"
provides:
  - "games table (UUID PK, UNIQUE key, translatable jsonb name)"
  - "game_roles table (composite UNIQUE (game_id, key), cascade FK to games)"
  - "game_match_types table (composite UNIQUE (game_id, key), cascade FK to games, nullable jsonb description)"
  - "game_match_type_role_limits table (dual cascade FKs, composite UNIQUE, capacity CHECK)"
  - "DB-layer slug guard (CHECK key ~ '^[a-z0-9_]+$') on all 3 keyed tables — T-03-02-03 mitigation"
  - "DB-layer capacity floor (CHECK capacity >= 0 on gmtrl) — T-03-02-02 mitigation"
  - "Cascade chain Game → roles, match-types → role-limits per Pitfall 7"
affects:
  - "Plan 03-03 (models + factories) — can now write Eloquent factories against these tables"
  - "Plan 03-04 (validation enums + form requests) — DB CHECKs are second prong of input validation"
  - "Plan 03-05 (HLL seeder) — composite UNIQUEs make firstOrCreate idempotent (Pattern 5)"
  - "Plan 03-06 / 03-07 (Filament resources) — DB constraints enable defense-in-depth"
tech-stack:
  added: []
  patterns:
    - "Anonymous-class migration"
    - "Named composite UNIQUE index (Pitfall 1)"
    - "Raw DB::statement for Postgres-specific CHECK + timestamptz upgrade"
    - "cascadeOnDelete FK chain"
key-files:
  created:
    - apps/web/database/migrations/2026_05_13_100000_create_games_table.php
    - apps/web/database/migrations/2026_05_13_100100_create_game_roles_table.php
    - apps/web/database/migrations/2026_05_13_100200_create_game_match_types_table.php
    - apps/web/database/migrations/2026_05_13_100300_create_game_match_type_role_limits_table.php
  modified: []
decisions:
  - "Composite UNIQUE on game_match_type_role_limits uses short name `gmtrl_match_type_role_unique` because the canonical Laravel auto-name (`game_match_type_role_limits_game_match_type_id_game_role_id_unique`, 71 bytes) exceeds Postgres' 63-byte identifier limit"
  - "Cross-game invariant (matchType.game_id === role.game_id) is NOT enforced at DB layer in plan 03-02 — a SQL CHECK cannot reference another table, and adding a PL/pgSQL trigger function would introduce Postgres-specific maintenance the team has avoided (Pitfall 10 / A6). Defense-in-depth is deferred to plan 03-03's model saving() listener and plan 03-07's Filament Select scoping"
  - "FKs in game_match_type_role_limits use cascadeOnDelete (NOT restrictOnDelete) because RoleLimits are configuration rows, not historical records — admin operations on parent rows must propagate (Pitfall 7). Plan 04 wave 0 must revisit if signed-up slots come to reference RoleLimit rows directly (Assumption A3)"
  - "Down migrations rely on Schema::dropIfExists alone — all named UNIQUE indexes and CHECK constraints live inside the table they're attached to, so they drop with the table (no separate DROP INDEX needed). This differs from clan_memberships, where the partial unique index `clan_memberships_one_active` is dropped first via raw SQL because it was created via raw SQL outside the Schema builder"
metrics:
  duration: "~12 minutes"
  completed: "2026-05-13"
  tasks: "2/2"
  commits: 2
---

# Phase 3 Plan 02: Wave 1 — Migrations Summary

Four migrations land the D-007 relational backbone — `games`, `game_roles`, `game_match_types`, `game_match_type_role_limits` — in the canonical Phase 1/2 raw-SQL idiom: UUID PKs with `gen_random_uuid()` default, `timestamptz` upgrade on `created_at` / `updated_at`, named composite UNIQUE indexes, key-format CHECKs (`key ~ '^[a-z0-9_]+$'`) on every `key` column, and a `capacity >= 0` CHECK on the role-limit table. Full `migrate:fresh` is green across Phase 1+2+3 (18 migrations).

## Migration Files

| Timestamp | File | Purpose |
|-----------|------|---------|
| `2026_05_13_100000` | `create_games_table.php` | Generic Game catalogue (D-007) — UUID PK, UNIQUE `key`, translatable jsonb `name`, `is_active` boolean |
| `2026_05_13_100100` | `create_game_roles_table.php` | Game-scoped role catalogue — composite UNIQUE `(game_id, key)`, FK `game_id` cascadeOnDelete |
| `2026_05_13_100200` | `create_game_match_types_table.php` | Game-scoped match-type catalogue — composite UNIQUE `(game_id, key)`, FK `game_id` cascadeOnDelete, nullable jsonb `description` |
| `2026_05_13_100300` | `create_game_match_type_role_limits_table.php` | Capacity matrix `(MatchType, Role) → capacity` — dual FKs cascadeOnDelete, composite UNIQUE, CHECK `capacity >= 0` |

All four use the anonymous-class migration pattern (`return new class extends Migration`) and place all CHECK / timestamptz / `gen_random_uuid()` statements via raw `DB::statement(...)` (Laravel Schema builder does not natively support these Postgres-specific facilities).

## Constraint Verification (via `\d` in psql)

### `games`
```
Indexes:
    "games_pkey" PRIMARY KEY, btree (id)
    "games_key_unique" UNIQUE CONSTRAINT, btree (key)
Check constraints:
    "games_key_format_check" CHECK (key ~ '^[a-z0-9_]+$'::text)
Referenced by:
    TABLE "game_roles" CONSTRAINT "game_roles_game_id_foreign"
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
```

### `game_roles`
```
Indexes:
    "game_roles_pkey" PRIMARY KEY, btree (id)
    "game_roles_game_id_key_unique" UNIQUE CONSTRAINT, btree (game_id, key)
Check constraints:
    "game_roles_key_format_check" CHECK (key ~ '^[a-z0-9_]+$'::text)
Foreign-key constraints:
    "game_roles_game_id_foreign" FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
```

### `game_match_types`
```
Indexes:
    "game_match_types_pkey" PRIMARY KEY, btree (id)
    "game_match_types_game_id_key_unique" UNIQUE CONSTRAINT, btree (game_id, key)
Check constraints:
    "game_match_types_key_format_check" CHECK (key ~ '^[a-z0-9_]+$'::text)
Foreign-key constraints:
    "game_match_types_game_id_foreign" FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
```

### `game_match_type_role_limits`
```
Indexes:
    "game_match_type_role_limits_pkey" PRIMARY KEY, btree (id)
    "gmtrl_match_type_role_unique" UNIQUE CONSTRAINT, btree (game_match_type_id, game_role_id)
Check constraints:
    "gmtrl_capacity_check" CHECK (capacity >= 0)
Foreign-key constraints:
    "game_match_type_role_limits_game_match_type_id_foreign"
        FOREIGN KEY (game_match_type_id) REFERENCES game_match_types(id) ON DELETE CASCADE
    "game_match_type_role_limits_game_role_id_foreign"
        FOREIGN KEY (game_role_id) REFERENCES game_roles(id) ON DELETE CASCADE
```

All threat-register mitigations from the plan's `<threat_model>` are in place:

| Threat ID | Mitigation | Visible in psql output |
|-----------|------------|------------------------|
| T-03-02-01 | Composite UNIQUE on `(game_id, key)` | `game_roles_game_id_key_unique`, `game_match_types_game_id_key_unique` |
| T-03-02-02 | `capacity >= 0` CHECK | `gmtrl_capacity_check` |
| T-03-02-03 | `key ~ '^[a-z0-9_]+$'` CHECK on all 3 keyed tables | `games_key_format_check`, `game_roles_key_format_check`, `game_match_types_key_format_check` |
| T-03-02-04 | timestamptz upgrade for created_at/updated_at | "timestamp with time zone" column type on all 4 tables |

## Postgres 63-byte Identifier Limit Workaround

The composite UNIQUE on `game_match_type_role_limits(game_match_type_id, game_role_id)` would auto-name to `game_match_type_role_limits_game_match_type_id_game_role_id_unique` — 71 bytes, which exceeds Postgres' 63-byte `NAMEDATALEN` limit. Two failure modes if left auto-named:

1. Postgres silently truncates to 63 bytes, producing a name collision risk with future indexes on the same table.
2. Laravel's auto-name truncation logic may produce a different truncated name than Postgres', resulting in a `\d` index name that doesn't match what Laravel believes it created (Pitfall 1).

The plan and migration explicitly use the short name `gmtrl_match_type_role_unique` (28 bytes). This is the **only** Phase 3 migration where the auto-name would not fit; the other composite UNIQUEs (`game_roles_game_id_key_unique`, `game_match_types_game_id_key_unique`) fit comfortably within 63 bytes but are still explicitly named for Pitfall-1 hygiene.

## Pattern Deviations from Phase 2 Analogs

Plan 03-02 deliberately followed the simpler Phase 2 pattern set; the only intentional deviations are:

- **No `softDeletes()` on any table.** Phase 2's `clans` carries `softDeletes('deleted_at')` because clans are user-facing entities. Phase 3 tables are configuration/catalogue rows — admin-managed, not user-soft-deleted. Deletes cascade through the chain by design (Pitfall 7).
- **No raw-SQL partial UNIQUE index.** Phase 2's `clan_memberships` uses a partial unique index `WHERE left_at IS NULL` because Schema's `unique()` doesn't support `WHERE`. None of the Phase 3 tables need a partial index, so all composite UNIQUEs go through `Schema::unique([...], 'name')` and drop automatically with the table — no raw `DROP INDEX` needed in `down()`.
- **One nullable jsonb column.** `game_match_types.description` is jsonb-nullable (HasTranslations + Filament Pitfall 2). The down-side coercion (`null → ['en' => '']`) is documented and lands in plan 03-07's Filament page mutator, not here.
- **No `restrictOnDelete` FKs.** Phase 2's clan_memberships uses `restrictOnDelete` to preserve history. Phase 3 RoleLimits are configuration rows; cascade is correct (Pitfall 7 / A3).

## Commits

| # | Hash | Subject |
|---|------|---------|
| 1 | `e91b872` | `feat(03-02): add games + game_roles migrations` |
| 2 | `98bff45` | `feat(03-02): add game_match_types + game_match_type_role_limits migrations` |

## Verification Run

- `docker compose exec web php artisan migrate:fresh --no-interaction` → 18/18 migrations green (Phase 1 + Phase 2 + 4 new Phase 3).
- `docker compose exec postgres psql -U trenchwars trenchwars -c "\d <table>"` → all 4 tables report expected indexes, CHECKs, FKs.
- `docker compose exec web ./vendor/bin/pint --test <four new files>` → PASS (2 files per task; 4 files total).
- `docker compose exec web ./vendor/bin/phpstan analyse` → "No errors" (129 files; 127 pre-Phase-3 + 2 new in run 1 — phpstan picks up migration files via `database/migrations` discovery).
- `docker compose exec web ./vendor/bin/pest --filter="Game"` → 7 RED stubs unchanged (Wave 0 placeholder tests expect `App\Models\Game*` classes that plan 03-03 will introduce; expected per CONTEXT).

## Deviations from Plan

None — plan executed exactly as written. Both tasks followed the `<acceptance_criteria>` verbatim. The migration timestamps, column lists, index names, CHECK names, and cascade rules all match the plan's specification character-for-character (cross-referenced against the RESEARCH.md Code Examples section).

## Known Stubs

None introduced by this plan. The 7 RED stubs from plan 03-01 (`Game*ModelTest.php` files) are still RED as expected — plan 03-03 wires the models that turn them GREEN.

## Self-Check: PASSED

- Files exist:
  - `apps/web/database/migrations/2026_05_13_100000_create_games_table.php` — FOUND
  - `apps/web/database/migrations/2026_05_13_100100_create_game_roles_table.php` — FOUND
  - `apps/web/database/migrations/2026_05_13_100200_create_game_match_types_table.php` — FOUND
  - `apps/web/database/migrations/2026_05_13_100300_create_game_match_type_role_limits_table.php` — FOUND
- Commits exist:
  - `e91b872` — FOUND
  - `98bff45` — FOUND
