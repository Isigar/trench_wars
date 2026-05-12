---
phase: 02-clans-tags
plan: "02"
subsystem: database-migrations
tags: [migrations, schema, postgres, partial-index, uuid, timestamptz, clans, clan-tags, clan-memberships]
dependency_graph:
  requires: [01-foundations]
  provides: [discord_guild-table, clans-table, clan_tags-table, clan_clan_tag-pivot, clan_memberships-table, clan_invites-table, clan_applications-table]
  affects: [02-03-models, 02-04-seeders, 02-05-filament-resources]
tech_stack:
  added: []
  patterns: [uuid-pk-gen-random-uuid, timestamptz-upgrade, check-constraints-raw-sql, partial-unique-index-where, soft-deletes, restrictOnDelete-fks, composite-pk-pivot]
key_files:
  created:
    - apps/web/database/migrations/2026_05_12_100000_create_discord_guild_table.php
    - apps/web/database/migrations/2026_05_12_100100_create_clans_table.php
    - apps/web/database/migrations/2026_05_12_100200_create_clan_tags_table.php
    - apps/web/database/migrations/2026_05_12_100300_create_clan_clan_tag_table.php
    - apps/web/database/migrations/2026_05_12_100400_create_clan_memberships_table.php
    - apps/web/database/migrations/2026_05_12_100500_create_clan_invites_table.php
    - apps/web/database/migrations/2026_05_12_100600_create_clan_applications_table.php
  modified: []
decisions:
  - "D-003 single-guild enforcement: operational via seeder + no-Create Filament resource only (no DB CHECK constraint); per planner decision A2 in RESEARCH.md — seeder firstOrCreate + DiscordGuildResource no-Create is sufficient for operational safety"
  - "discord_guild table: guild_id is nullable at migration time; admin fills after Discord bot setup; guild_id has UNIQUE constraint so it remains unique once populated"
  - "clan_clan_tag pivot: created_at only (no updated_at); both FKs restrictOnDelete so tags cannot be silently removed while clans are attached"
  - "clans.tag UNIQUE: clan short tags compete globally (e.g. '91st'), matching schema doc"
  - "decided_by FK in clan_applications uses nullOnDelete (not restrictOnDelete): decision record preserved if deciding admin leaves the league"
  - "accent_color column NOT added per RESEARCH.md Deferred Ideas — deferred to future UI phase"
metrics:
  duration: "~2 min 20 sec"
  completed: "2026-05-12"
  tasks_completed: 2
  files_created: 7
---

# Phase 02 Plan 02: Migrations Summary

**One-liner:** 7 Phase-2 migrations land the full Clans relational backbone — UUID PKs with `gen_random_uuid()`, `timestamptz` columns, CHECK enum constraints, and a Postgres partial unique index `WHERE left_at IS NULL` enforcing D-009.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | discord_guild + clans + clan_tags + clan_clan_tag pivot migrations | `8b7fbfe` | 4 migration files |
| 2 | clan_memberships (D-009 partial index) + clan_invites + clan_applications | `68f714f` | 3 migration files |

## Migrations Delivered

| Migration | Timestamp | Key Feature |
|-----------|-----------|-------------|
| `create_discord_guild_table` | `2026_05_12_100000` | Singular table name (D-003); guild_id nullable unique; timestamptz |
| `create_clans_table` | `2026_05_12_100100` | slug/tag UNIQUE; description jsonb; status CHECK; softDeletes; owner FK restrictOnDelete |
| `create_clan_tags_table` | `2026_05_12_100200` | label jsonb (translatable); color hex nullable |
| `create_clan_clan_tag_table` | `2026_05_12_100300` | Composite PK [clan_id, clan_tag_id]; created_at only; both FKs restrictOnDelete |
| `create_clan_memberships_table` | `2026_05_12_100400` | **D-009 partial unique index** `clan_memberships_one_active WHERE left_at IS NULL`; role CHECK; invited_by nullOnDelete |
| `create_clan_invites_table` | `2026_05_12_100500` | status CHECK pending/accepted/declined/revoked/expired; decided_at/expires_at timestampTz |
| `create_clan_applications_table` | `2026_05_12_100600` | status CHECK pending/accepted/declined/cancelled; decided_by nullOnDelete |

## Verification Results

- `migrate:fresh` (14 migrations total — 7 P1 + 7 P2): **GREEN** — all 14 completed without error
- Partial unique index confirmed via psql `\d clan_memberships`: `"clan_memberships_one_active" UNIQUE, btree (user_id) WHERE left_at IS NULL`
- `clans_status_check` confirmed via psql `\d clans`: `CHECK (status = ANY (ARRAY['active'::text, 'suspended'::text, 'disbanded'::text]))`
- `make pint --test database/migrations/` (14 files): **PASS**
- `make phpstan` (62 files analysed): **0 errors**

## Deviations from Plan

None — plan executed exactly as written.

The Phase-1 raw-SQL idiom (uuid default, timestamptz upgrade, CHECK constraints, partial unique index via DB::statement) was applied verbatim to all 7 migrations. The RESEARCH.md Pattern 1 code example for `clan_memberships` was used as the direct template.

## Known Stubs

None. Migrations are pure schema — no data stubs or placeholder values.

## Threat Flags

None. All threat mitigations from the plan's STRIDE register are implemented:

| Threat | Mitigation Applied |
|--------|--------------------|
| T-02-01-01 | `clan_memberships_one_active` partial unique index via `DB::statement` |
| T-02-01-02 | `restrictOnDelete` on all clan/user FKs (except `invited_by`/`decided_by` which are `nullOnDelete`) |
| T-02-01-03 | CHECK constraints on `clans.status`, `clan_memberships.role`, `clan_invites.status`, `clan_applications.status` |
| T-02-01-04 | `timestamptz` upgrade applied to all `created_at`/`updated_at`/`deleted_at` columns |
| T-02-01-05 | `discord_guild` singular table + no public route (admin-only in plan 02-13) |

## Self-Check: PASSED

- All 7 migration files exist on disk: confirmed
- Commit `8b7fbfe` (Task 1) and `68f714f` (Task 2) exist in git log: confirmed
- `clan_memberships_one_active` partial unique index visible in Postgres: confirmed
- `clans_status_check` CHECK constraint visible in Postgres: confirmed
- PHPStan 0 errors: confirmed
- Pint 14/14 files pass: confirmed
