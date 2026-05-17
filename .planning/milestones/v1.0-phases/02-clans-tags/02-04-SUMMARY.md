---
phase: 02-clans-tags
plan: "04"
subsystem: seeders
tags: [seeder, discord-guild, clan-tags, idempotent, d-003]
dependency_graph:
  requires: [02-01, 02-02, 02-03]
  provides: [discord-guild-stub-row, starter-clan-tags]
  affects: [clan-directory-filter-chips, filament-discord-guild-resource-02-13]
tech_stack:
  added: []
  patterns:
    - "firstOrCreate([]) singleton trick for single-row operational enforcement"
    - "firstOrCreate(['slug' => $slug], [...]) for idempotent tag creation"
key_files:
  created:
    - apps/web/database/seeders/DiscordGuildSeeder.php
    - apps/web/database/seeders/ClanTagSeeder.php
  modified:
    - apps/web/database/seeders/DatabaseSeeder.php
    - apps/web/tests/Feature/Clans/DiscordGuildSeederTest.php
    - apps/web/tests/Feature/Clans/DiscordGuildSingleRowTest.php
decisions:
  - "02-04: DiscordGuildSeeder uses firstOrCreate([]) with empty $attributes — singleton trick that matches any existing row without filtering by column value, ensuring re-runs are no-ops"
  - "02-04: ClanTag seeder lookups keyed by slug (UNIQUE column) so firstOrCreate is safe across re-runs"
  - "02-04: DiscordGuildSingleRowTest includes a passing second create() test with RESEARCH.md Pattern 4 citation — documents that DB-level enforcement is intentionally absent; operational gate is seeder + Filament no-Create (plan 02-13)"
metrics:
  duration: "2m 6s"
  completed_date: "2026-05-12"
  tasks_completed: 2
  files_modified: 5
---

# Phase 02 Plan 04: Seeders (Wave 1) Summary

Single-sentence summary: DiscordGuildSeeder seeds one idempotent singleton row (D-003) and ClanTagSeeder seeds EU/NA/Tier-1 starter tags with translatable JSONB labels; both wired into DatabaseSeeder; two Wave 0 stubs replaced with 5 GREEN tests.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | DiscordGuildSeeder + ClanTagSeeder + DatabaseSeeder wiring | 28f8feb | DiscordGuildSeeder.php, ClanTagSeeder.php, DatabaseSeeder.php |
| 2 | Replace Wave 0 test stubs with real DiscordGuild assertions | a139b7e | DiscordGuildSeederTest.php, DiscordGuildSingleRowTest.php |

## Seeder Strategies

### DiscordGuildSeeder — Singleton Pattern

```php
DiscordGuild::firstOrCreate([], [
    'guild_id' => null,
    'name'     => null,
    'icon_url' => null,
]);
```

The empty `$attributes` array is the singleton trick. `firstOrCreate([])` matches the first existing row of any shape — no column conditions mean "give me any row". If none exists, the second array provides the values to insert. On re-run, the first row is found and returned; no INSERT is attempted. Admin fills `guild_id`, `name`, and `icon_url` via the Filament edit page after bot setup (plan 02-13).

### ClanTagSeeder — Unique-Slug Idempotency

```php
ClanTag::firstOrCreate(
    ['slug' => $attrs['slug']],
    ['label' => $attrs['label'], 'color' => $attrs['color']]
);
```

Slug is a UNIQUE column (migration constraint from plan 02-03). The lookup by slug is therefore safe — no duplicate rows can be created. Three starter tags shipped:

| Slug | Label (en) | Color |
|------|-----------|-------|
| eu | EU | #2C5282 (blue) |
| na | NA | #742A2A (red) |
| tier-1 | Tier 1 | #A4262C (accent) |

Labels are stored as JSONB via `spatie/laravel-translatable` with the `en` key, satisfying D-013 from day one.

### DatabaseSeeder Call Order

```
PermissionSeeder -> DiscordGuildSeeder -> ClanTagSeeder
```

Order is intentional: permissions exist before guild or tags in case any future trigger or observer references them. Phase 3+ will add GameSeeder after ClanTagSeeder.

## DB-Level vs Operational Single-Row Enforcement (D-003)

RESEARCH.md Pattern 4 explicitly chose **operational enforcement** (seeder + Filament no-Create page) over a DB-level `CHECK (COUNT(*) <= 1)` constraint for the `discord_guild` table. Reasons:

1. Postgres `CHECK` constraints cannot reference aggregate counts across rows — a proper trigger would be needed, which adds complexity.
2. The operational gate (seeder idempotency + Filament resource with no Create page) is simpler and sufficient for a single-operator admin environment (D-003 states exactly one guild for the league).
3. Direct DB insertion (e.g., via migrations, artisan tinker) is permitted by design — it is an escape hatch, not a bug.

`DiscordGuildSingleRowTest` includes a test that asserts `create()` succeeds at the DB layer, with an inline comment citing Pattern 4. This is a **current contract** assertion. If policy changes to DB-level enforcement, that test should be flipped to `->toThrow(QueryException::class)`.

## Test Coverage

| Test file | Tests | Assertions | Status |
|-----------|-------|-----------|--------|
| DiscordGuildSeederTest.php | 3 | 4 | GREEN |
| DiscordGuildSingleRowTest.php | 2 | 3 | GREEN |

Both files: `declare(strict_types=1)`, no Wave 0 placeholder `it()` remaining.

## Verification

- `migrate:fresh --seed`: 1 discord_guild row + 3 clan_tags rows — PASS
- Re-seed (twice): still 1 discord_guild row + 3 clan_tags rows — PASS (idempotency)
- `--filter=DiscordGuild`: 5/5 GREEN
- `pint --test database/seeders`: PASS (0 style issues)
- `phpstan`: 0 errors

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all seeded data is wired to real models and real DB tables.

## Threat Surface Scan

No new network endpoints, auth paths, file access patterns, or schema changes introduced. Seeders run under console kernel (causer=null in activity log, per T-02-04-03 threat register — accepted disposition).

## Self-Check: PASSED

- apps/web/database/seeders/DiscordGuildSeeder.php: FOUND
- apps/web/database/seeders/ClanTagSeeder.php: FOUND
- apps/web/database/seeders/DatabaseSeeder.php: FOUND (modified)
- apps/web/tests/Feature/Clans/DiscordGuildSeederTest.php: FOUND (replaced)
- apps/web/tests/Feature/Clans/DiscordGuildSingleRowTest.php: FOUND (replaced)
- Commit 28f8feb: FOUND
- Commit a139b7e: FOUND
