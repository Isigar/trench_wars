---
phase: 04-matches-manual
plan: 02
subsystem: matches
tags: [phase-4, wave-1, migrations, schema, raw-sql, partial-unique, polymorphic, check-constraints]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-3-summary
  provides:
    - phase-4-relational-backbone
    - matches-status-enum-db-defence
    - match-slots-one-occupancy-per-user-invariant
    - events-polymorphic-hook-for-phase-6
    - match-results-1-to-1-cardinality
    - match-mvps-category-enum-db-defence
  affects:
    - apps/web/database/migrations/ (6 new Phase 4 migrations)
tech_stack:
  added: []
  patterns:
    - raw-sql-check-constraint
    - raw-sql-partial-unique-index
    - timestamptz-upgrade-via-alter
    - native-timestamptz-application-managed-columns
    - polymorphic-table-no-fk
    - named-composite-unique-postgres-63-byte-fit
key_files:
  created:
    - apps/web/database/migrations/2026_05_14_100000_create_matches_table.php
    - apps/web/database/migrations/2026_05_14_100100_create_match_slots_table.php
    - apps/web/database/migrations/2026_05_14_100200_create_match_access_rules_table.php
    - apps/web/database/migrations/2026_05_14_100300_create_match_results_table.php
    - apps/web/database/migrations/2026_05_14_100400_create_match_mvps_table.php
    - apps/web/database/migrations/2026_05_14_100500_create_events_table.php
  modified: []
decisions:
  - id: D-04-02-A
    decision: |
      `events` polymorphic columns deliberately carry no FK. Two owner classes will
      live there before Phase 6 ships (Match in 04-08; Tournament in 06-XX), and
      Postgres has no native polymorphic-FK option. Integrity is guaranteed by the
      MatchObserver upsert/delete pair (plan 04-08) plus the composite UNIQUE
      `events_one_per_owner`. The named index `events_morphable_index` exists
      alongside the UNIQUE so query plans reference a documented index name even
      though both cover the same columns.
  - id: D-04-02-B
    decision: |
      `match_slots_one_occupancy_per_user` partial UNIQUE follows the verbatim
      Phase 2 D-009 idiom from `clan_memberships_one_active` — raw
      `CREATE UNIQUE INDEX ... WHERE occupant_user_id IS NOT NULL;` via DB::statement
      because Schema::unique() cannot express a WHERE predicate (Pitfall 1).
      down() runs `DROP INDEX IF EXISTS` before Schema::dropIfExists to mirror the
      Phase 2 migration's pair-symmetry exactly.
  - id: D-04-02-C
    decision: |
      All `match_*` parent FKs cascadeOnDelete (matches→slots, matches→rules,
      matches→results, results→mvps); all User/Player/GameRole/ClanTag references
      use restrictOnDelete to preserve audit/historical integrity; host_clan and
      winner_clan and occupant_user use nullOnDelete because the parent entity
      may legitimately disband/leave while the match record continues to exist.
      Mirrors RESEARCH Pattern 1 verbatim.
metrics:
  duration_minutes: 3
  completed: 2026-05-13
---

# Phase 4 Plan 02: Wave 1 Migrations Summary

**One-liner:** Six raw-SQL Phase 4 migrations land cleanly — matches/match_slots/match_access_rules/match_results/match_mvps/events — with the status + category CHECK constraints, the one-slot-per-user-per-match partial UNIQUE index (D-009 analog), the events polymorphic table (Phase 6 hook), and verbatim Phase 1/2/3 timestamptz/gen_random_uuid idiom.

## What Shipped

### 6 new migrations

| File | Table | Key constraints |
|------|-------|-----------------|
| `2026_05_14_100000_create_matches_table.php` | `matches` | status CHECK enum (5 values); 3 FKs (restrict/restrict/null); scheduled_at native timestamptz; 3 indexes |
| `2026_05_14_100100_create_match_slots_table.php` | `match_slots` | composite UNIQUE `match_slots_unique_slot`; partial UNIQUE `match_slots_one_occupancy_per_user`; 3 FKs (cascade/restrict/null) |
| `2026_05_14_100200_create_match_access_rules_table.php` | `match_access_rules` | composite UNIQUE `match_access_rules_unique`; 2 FKs (cascade/restrict) |
| `2026_05_14_100300_create_match_results_table.php` | `match_results` | 1:1 via match_id UNIQUE; CHECK `match_results_scores_nonneg_check`; recorded_at native timestamptz; 3 FKs (cascade/null/restrict) |
| `2026_05_14_100400_create_match_mvps_table.php` | `match_mvps` | composite UNIQUE `match_mvps_unique`; CHECK `match_mvps_category_check`; 2 FKs (cascade/restrict) |
| `2026_05_14_100500_create_events_table.php` | `events` | composite UNIQUE `events_one_per_owner`; named `events_morphable_index`; 2 read indexes; **NO FKs** (polymorphic) |

### Named indexes + CHECK constraints visible via psql \d

| Table | Index / Constraint | Type |
|-------|--------------------|------|
| `matches` | `matches_status_check` | CHECK (status ∈ 5-element enum) |
| `matches` | `matches_scheduled_at_index` | btree (scheduled_at) |
| `matches` | `matches_status_scheduled_at_index` | btree (status, scheduled_at) |
| `matches` | `matches_is_public_index` | btree (is_public) |
| `match_slots` | `match_slots_unique_slot` | UNIQUE (match_id, game_role_id, slot_index) |
| `match_slots` | `match_slots_one_occupancy_per_user` | partial UNIQUE (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL |
| `match_slots` | `match_slots_match_id_occupant_user_id_index` | btree (match_id, occupant_user_id) |
| `match_access_rules` | `match_access_rules_unique` | UNIQUE (match_id, clan_tag_id) |
| `match_results` | `match_results_match_id_unique` | UNIQUE (match_id) — 1:1 with matches |
| `match_results` | `match_results_scores_nonneg_check` | CHECK ((allies_score IS NULL OR ≥ 0) AND (axis_score IS NULL OR ≥ 0)) |
| `match_mvps` | `match_mvps_unique` | UNIQUE (match_result_id, category, player_id) |
| `match_mvps` | `match_mvps_category_check` | CHECK (category ∈ 4-element enum) |
| `events` | `events_one_per_owner` | UNIQUE (eventable_type, eventable_id) |
| `events` | `events_morphable_index` | btree (eventable_type, eventable_id) |
| `events` | `events_starts_at_index` | btree (starts_at) |
| `events` | `events_is_public_starts_at_index` | btree (is_public, starts_at) |

Every named index fits inside Postgres' 63-byte identifier limit (longest is
`match_slots_match_id_occupant_user_id_index` at 43 bytes — well under).

### Cascade matrix (T-04-02-05 + T-04-02-06 mitigation)

| Parent delete | Child action | Why |
|---------------|--------------|-----|
| matches → match_slots | CASCADE | match deletion removes slot rows wholesale |
| matches → match_access_rules | CASCADE | rules are owned-config, not history |
| matches → match_results | CASCADE | 1:1 ownership |
| match_results → match_mvps | CASCADE | MVPs are owned-data, not history |
| game_roles → match_slots | RESTRICT | historical slot rows block role deletion |
| clan_tags → match_access_rules | RESTRICT | tag system stays consistent |
| users → matches.organiser | RESTRICT | preserve match-organiser audit trail |
| users → match_slots.occupant | NULL | slot vacates, match continues |
| users → match_results.recorded_by | RESTRICT | preserve audit trail |
| clans → matches.host_clan | NULL | host disband ⇒ match continues hostless |
| clans → match_results.winner_clan | NULL | winner-clan disband ⇒ result retained |
| players → match_mvps | RESTRICT | preserve historical MVP record |
| (none) → events | n/a | polymorphic — observer-managed (plan 04-08) |

### Partial UNIQUE pattern replication of D-009

The migration body for `match_slots_one_occupancy_per_user` is a verbatim
translation of the Phase 2 `clan_memberships_one_active` idiom:

```php
// Phase 2 (Wave 0):
DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');

// Phase 4 (Wave 1 — this plan):
DB::statement('CREATE UNIQUE INDEX match_slots_one_occupancy_per_user ON match_slots (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL;');
```

Both index names are listed in the `down()` method's `DROP INDEX IF EXISTS` for
roll-back symmetry. Schema::unique() can NOT express a WHERE predicate — this is
the only viable pattern (Pitfall 1).

### Pattern deviations from Phase 2/3 analogs

- **None substantive.** The Phase 2 D-009 partial-unique idiom maps 1:1 onto Phase 4's
  one-slot-per-user invariant; the Phase 3 CHECK-constraint idiom (e.g.
  `games_key_format_check`, `gmtrl_capacity_check`) maps 1:1 onto Phase 4's status
  + category + scores constraints. The only structural novelty is the polymorphic
  `events` table (no precedent in Phase 1-3) — handled per RESEARCH Pattern 8
  (string `eventable_type` + uuid `eventable_id` + no FK + composite UNIQUE +
  named morphable_index).
- **Native timestamptz vs. ALTER pattern:** Application-managed datetime columns
  (`matches.scheduled_at`, `match_slots.confirmed_at`, `match_results.recorded_at`,
  `events.starts_at`, `events.ends_at`) use `$table->timestampTz(...)` directly;
  Eloquent-managed `created_at`/`updated_at` get the `ALTER TABLE ... TYPE timestamptz`
  treatment because `$table->timestamps()` emits plain timestamp. Same convention
  as Phase 1/2/3.

### Postgres 63-byte identifier limit

All named indexes verified under 63 bytes:
- `match_slots_match_id_occupant_user_id_index` (43)
- `match_results_scores_nonneg_check` (33)
- `match_slots_one_occupancy_per_user` (35)
- `match_access_rules_unique` (25)
- `events_is_public_starts_at_index` (32)
- `match_mvps_category_check` (25)
- `matches_status_scheduled_at_index` (33)

No truncation collisions; no auto-generated name overflow risk.

## Verification

| Gate | Command | Result |
|------|---------|--------|
| Full migrate:fresh | `docker compose exec web php artisan migrate:fresh --no-interaction` | **24 migrations green** (Phase 1+2+3+6 new Phase 4) |
| matches table inspection | `psql \d matches` | matches_status_check + 3 FKs + 3 indexes confirmed |
| match_slots table inspection | `psql \d match_slots` | match_slots_unique_slot + match_slots_one_occupancy_per_user (partial) + 3 FKs confirmed |
| match_access_rules inspection | `psql \d match_access_rules` | match_access_rules_unique + 2 FKs confirmed |
| match_results inspection | `psql \d match_results` | match_id UNIQUE (1:1) + match_results_scores_nonneg_check + 3 FKs confirmed |
| match_mvps inspection | `psql \d match_mvps` | match_mvps_unique + match_mvps_category_check + 2 FKs confirmed |
| events inspection | `psql \d events` | events_one_per_owner + events_morphable_index + starts_at + (is_public, starts_at) indexes, NO FKs confirmed |
| `./vendor/bin/pint --test database/migrations` | container | **PASS** (24 files) |
| `./vendor/bin/phpstan analyse` | container | **0 errors** (162 files) |
| `./vendor/bin/pest` (full suite) | container | **22 incomplete + 278 passed**, 822 assertions — identical to Wave 0 baseline (no regressions; Phase 4 RED stubs intentionally still incomplete) |

## Decisions Made

- **D-04-02-A:** `events` table is polymorphic by design with no FK; observer + UNIQUE pair guarantees integrity (Pattern 8). See decision block above.
- **D-04-02-B:** `match_slots_one_occupancy_per_user` partial UNIQUE follows the verbatim D-009 idiom from clan_memberships. See decision block above.
- **D-04-02-C:** Cascade direction follows RESEARCH Pattern 1 verbatim — matches cascade down to all owned children; user/player/role/tag use restrict; clan/user nullable refs use nullOnDelete. See decision block above.

## Deviations from Plan

None — plan executed exactly as written. Both tasks landed in a single execute-and-verify cycle; every constraint name, FK direction, and index spec in the plan's `<interfaces>` block matched the canonical RESEARCH Pattern 1 / Pattern 8 verbatim. The two NOTEs in the plan body (native timestampTz for application columns; ALTER for created_at/updated_at) were observed.

## Auth Gates

None — pure schema migrations, no auth-bearing operations.

## Known Stubs

None. Plan 04-02 lands six concrete migration files with full constraint coverage. The 22 RED Pest stubs from plan 04-01 remain incomplete-by-design until later waves (Plan 04-03 flips the 6 model stubs; subsequent plans flip the rest). They are tracked in 04-01-SUMMARY.md and the phase VALIDATION map — not regressions, not new stubs.

## Threat Flags

No new security surface introduced.

T-04-02-01 through T-04-02-08 from the plan's threat register are all addressed by the constraints that landed:
- **T-04-02-01** (capacity-bypass): `match_slots_unique_slot` + `match_slots_one_occupancy_per_user` partial UNIQUE
- **T-04-02-02** (invalid status): `matches_status_check`
- **T-04-02-03** (invalid MVP category): `match_mvps_category_check`
- **T-04-02-04** (negative scores): `match_results_scores_nonneg_check`
- **T-04-02-05** (orphan slots/rules/results): cascade FKs on all match_* parents
- **T-04-02-06** (orphan mvps): cascade FK match_result_id
- **T-04-02-07** (events drift): `events_one_per_owner` UNIQUE (MatchObserver completes the loop in 04-08)
- **T-04-02-08** (timestamptz drift): every datetime column is timestamptz (native or via ALTER)

## Commits

| Hash | Task | Files | Lines |
|------|------|-------|-------|
| `bf924dd` | Task 1 — matches + match_slots | 2 | +137 |
| `94f060e` | Task 2 — match_access_rules + match_results + match_mvps + events | 4 | +227 |

## Self-Check: PASSED

- 6 migration files exist: 6/6 found at `apps/web/database/migrations/2026_05_14_*`
- migrate:fresh runs cleanly end-to-end (Phase 1+2+3+ all 6 new Phase 4 migrations green)
- All named constraints visible via `psql \d`: matches_status_check, match_slots_unique_slot, match_slots_one_occupancy_per_user, match_access_rules_unique, match_results_match_id_unique, match_results_scores_nonneg_check, match_mvps_unique, match_mvps_category_check, events_one_per_owner, events_morphable_index
- Commit hashes bf924dd and 94f060e present in `git log --oneline -3`
- Pint clean across all 24 migration files
- PHPStan clean across 162 analyzed files
- Pest baseline preserved: 22 incomplete + 278 passed (no regressions, no new RED)
