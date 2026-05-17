---
phase: 06-tournaments-brackets
plan: 02
subsystem: schema
tags:
  - wave-1
  - migrations
  - postgres
  - check-constraints
  - partial-unique
  - self-fk
  - tournaments
  - brackets
  - standings
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 factory stubs + RED tests
    - .planning/phases/04-matches-manual/04-02-SUMMARY.md         # Phase 4 GameMatch / matches table FK target
    - .planning/phases/03-games-match-types/03-02-SUMMARY.md      # Phase 3 games + game_match_types FK targets
    - .planning/phases/02-clans-memberships/02-02-SUMMARY.md      # Phase 2 clans FK target
  provides:
    - "Phase 6 schema: 5 tables (tournaments, tournament_participants, tournament_stages, tournament_brackets, tournament_standings)"
    - "5 CHECK constraints (format, tournament status, participant status, stage type, bracket no-self-advance)"
    - "5 UNIQUE / partial UNIQUE indexes (slug, participant unique, stage ordinal unique, bracket match_id partial unique, bracket stage_position unique, standings unique)"
    - "FK cascade matrix: 13 FKs landed (3 in tournaments, 2 in participants, 1 in stages, 7 in brackets — 2 self + 5 outward, 3 in standings)"
    - "uuid('id')->primary() + gen_random_uuid() default on all 5 tables (Phase 4 D-04 idiom verbatim)"
    - "timestamptz post-conversion on every timestamp column (Phase 1+ idiom)"
  affects:
    - apps/web/database/migrations/  # 5 new migrations
tech-stack:
  added: []
  patterns:
    - "Self-FK ordering workaround: declare self-FKs in a SEPARATE Schema::table() block AFTER Schema::create() completes, because Laravel emits ADD PRIMARY KEY ALTER AFTER FK ADD CONSTRAINT ALTERs in Schema::create — Postgres rejects inline self-FK with 'no unique constraint matching given keys for referenced table'."
    - "Partial UNIQUE INDEX WHERE NOT NULL via raw DB::statement (Schema::unique() cannot express WHERE) — Pitfall 4 mitigation, same idiom as Phase 2 D-009 (one active ClanMembership) and Phase 4 match_slots_one_occupancy_per_user."
    - "CHECK constraint via DB::statement ADD CONSTRAINT, naming pattern '{table}_{semantic}_check', identifier length ≤ 63 bytes (Phase 3 Pitfall 1)."
key-files:
  created:
    - apps/web/database/migrations/2026_05_15_100000_create_tournaments_table.php
    - apps/web/database/migrations/2026_05_15_100100_create_tournament_participants_table.php
    - apps/web/database/migrations/2026_05_15_100200_create_tournament_stages_table.php
    - apps/web/database/migrations/2026_05_15_100300_create_tournament_brackets_table.php
    - apps/web/database/migrations/2026_05_15_100400_create_tournament_standings_table.php
  modified: []
decisions:
  - "D-06-02-A: Self-FKs on tournament_brackets (advances_to_bracket_id, loser_advances_to_bracket_id) are declared in a SEPARATE Schema::table() block AFTER Schema::create() completes. Rationale: Laravel 12 Schema::create() emits inline `$table->foreign()` declarations as `ALTER TABLE ... ADD CONSTRAINT FOREIGN KEY` statements BEFORE emitting `ALTER TABLE ... ADD PRIMARY KEY` for `uuid('id')->primary()`. Postgres rejects a self-FK against a table whose primary key has not been established yet (SQLSTATE 42830 'no unique constraint matching given keys for referenced table'). Adding self-FKs in a deferred Schema::table() block lets the PK ALTER run first. Non-self FKs (to other already-created tables) stay inline."
  - "D-06-02-B: tournament_standings UNIQUE composite is (tournament_stage_id, participant_id) NOT (tournament_id, participant_id). Round-robin tournaments can carry multiple stages (groups + playoffs) where the same participant has DIFFERENT standings rows; uniqueness at the stage level — not tournament level — is the correct granularity."
  - "D-06-02-C: Self-FK no-self-advance CHECK covers BOTH advance pointers in one CHECK: `CHECK (advances_to_bracket_id != id AND loser_advances_to_bracket_id != id)`. NULL is allowed because `NULL != id` evaluates to NULL (not FALSE) in Postgres — un-materialised brackets (no advance pointer yet) coexist with the CHECK."
metrics:
  duration: 4m 06s
  completed: 2026-05-13
  tasks: 2
  files_created: 5
  files_modified: 0
  commits: 2
---

# Phase 6 Plan 2: Wave 1 — 5 Migrations Summary

Phase 6 schema landed. 5 tables, 5 CHECK constraints, 5 unique / partial-unique indexes, 13 FKs (with 2 self-FKs deferred for the PK-ordering quirk). `migrate:fresh` and `rollback --step=5 + migrate` round-trip both green.

## What Landed

### 5 Migration Files

| File | Table | Columns | Indexes | FKs | CHECKs |
|------|-------|---------|---------|-----|--------|
| `2026_05_15_100000_create_tournaments_table.php` | `tournaments` | 16 | 6 (incl. slug unique) | 3 | 2 (format + status) |
| `2026_05_15_100100_create_tournament_participants_table.php` | `tournament_participants` | 9 | 3 (incl. unique composite) | 2 | 1 (status) |
| `2026_05_15_100200_create_tournament_stages_table.php` | `tournament_stages` | 8 | 3 (incl. unique ordinal) | 1 | 1 (type) |
| `2026_05_15_100300_create_tournament_brackets_table.php` | `tournament_brackets` | 12 | 5 (incl. partial unique match_id + stage_position) | 7 (5 outward + 2 self) | 1 (no_self_advance) |
| `2026_05_15_100400_create_tournament_standings_table.php` | `tournament_standings` | 12 | 3 (incl. unique stage+participant) | 3 | 0 |
| **Total** | **5 tables** | **57** | **20** | **16 (12 outward + 2 self + 2 unique slug)** | **5** |

> FK total counts only `references…on` constraints; slug `->unique()` is a unique index not a FK.

### CHECK Constraints (verbatim from `pg_constraint`)

```
tournament_brackets     | tournament_brackets_no_self_advance  | CHECK (advances_to_bracket_id != id AND loser_advances_to_bracket_id != id)
tournament_participants | tournament_participants_status_check | CHECK (status IN ('registered','active','withdrawn','disqualified'))
tournament_stages       | tournament_stages_type_check         | CHECK (type IN ('group','elim','swiss-round','winners-bracket','losers-bracket','grand-final'))
tournaments             | tournaments_format_check             | CHECK (format IN ('single_elimination','double_elimination','round_robin','swiss'))
tournaments             | tournaments_status_check             | CHECK (status IN ('draft','registering','seeded','running','completed','cancelled'))
```

All five CHECK names ≤ 36 chars — well under the Postgres 63-byte identifier limit (Phase 3 Pitfall 1).

### UNIQUE / Partial-UNIQUE Indexes

| Table | Index | Columns | Predicate |
|-------|-------|---------|-----------|
| `tournaments` | `tournaments_slug_unique` | `slug` | — (full UNIQUE) |
| `tournament_participants` | `tournament_participants_unique` | `(tournament_id, clan_id)` | — (composite UNIQUE) |
| `tournament_stages` | `tournament_stages_unique_ordinal` | `(tournament_id, ordinal)` | — (composite UNIQUE) |
| `tournament_brackets` | `tournament_brackets_match_id_unique` | `(match_id)` | `WHERE match_id IS NOT NULL` (partial — Pitfall 4 / T-06-02-03) |
| `tournament_brackets` | `tournament_brackets_stage_position` | `(tournament_stage_id, round_number, position)` | — (composite UNIQUE) |
| `tournament_standings` | `tournament_standings_unique` | `(tournament_stage_id, participant_id)` | — (composite UNIQUE) |

### FK Cascade Matrix

| FK | Source → Target | onDelete | Reason |
|----|-----------------|----------|--------|
| `tournaments.game_id` | → `games.id` | `restrict` | Don't orphan tournaments when a Game is deleted |
| `tournaments.organiser_user_id` | → `users.id` | `restrict` | Preserve organiser audit trail |
| `tournaments.default_game_match_type_id` | → `game_match_types.id` | `set null` | Admin can reassign default match type without nuking tournament |
| `tournament_participants.tournament_id` | → `tournaments.id` | `cascade` | Delete tournament → wipe participants |
| `tournament_participants.clan_id` | → `clans.id` | `restrict` | Preserve participant history; admin must withdraw before clan delete |
| `tournament_stages.tournament_id` | → `tournaments.id` | `cascade` | Delete tournament → wipe stages |
| `tournament_brackets.tournament_stage_id` | → `tournament_stages.id` | `cascade` | Delete stage → wipe brackets |
| `tournament_brackets.participant_a_id` | → `tournament_participants.id` | `set null` | Withdraw participant → null the bracket slot |
| `tournament_brackets.participant_b_id` | → `tournament_participants.id` | `set null` | Same as above |
| `tournament_brackets.winner_participant_id` | → `tournament_participants.id` | `set null` | Same as above |
| `tournament_brackets.match_id` | → `matches.id` | `set null` | Match delete (admin-only) → null bracket's match link |
| `tournament_brackets.advances_to_bracket_id` | → `tournament_brackets.id` (self) | `set null` | Bracket delete → null descendants' advance pointer |
| `tournament_brackets.loser_advances_to_bracket_id` | → `tournament_brackets.id` (self) | `set null` | Same (double-elim drop chain) |
| `tournament_standings.tournament_id` | → `tournaments.id` | `cascade` | Delete tournament → wipe standings |
| `tournament_standings.tournament_stage_id` | → `tournament_stages.id` | `cascade` | Delete stage → wipe standings |
| `tournament_standings.participant_id` | → `tournament_participants.id` | `cascade` | Delete participant → wipe their standings (rebuilds on recalc) |

### `tournament_brackets` Full Schema (verbatim `psql \d+` output)

```
                                         Table "public.tournament_brackets"
            Column            |           Type           | Nullable |      Default
------------------------------+--------------------------+----------+-------------------
 id                           | uuid                     | not null | gen_random_uuid()
 tournament_stage_id          | uuid                     | not null |
 round_number                 | integer                  | not null |
 position                     | integer                  | not null |
 participant_a_id             | uuid                     |          |
 participant_b_id             | uuid                     |          |
 winner_participant_id        | uuid                     |          |
 match_id                     | uuid                     |          |
 advances_to_bracket_id       | uuid                     |          |
 loser_advances_to_bracket_id | uuid                     |          |
 created_at                   | timestamp with time zone |          |
 updated_at                   | timestamp with time zone |          |

Indexes:
    "tournament_brackets_pkey" PRIMARY KEY, btree (id)
    "tournament_brackets_match_id_index" btree (match_id)
    "tournament_brackets_match_id_unique" UNIQUE, btree (match_id) WHERE match_id IS NOT NULL
    "tournament_brackets_stage_position" UNIQUE, btree (tournament_stage_id, round_number, "position")
    "tournament_brackets_tournament_stage_id_round_number_position_i" btree (tournament_stage_id, round_number, "position")

Check constraints:
    "tournament_brackets_no_self_advance" CHECK (advances_to_bracket_id <> id AND loser_advances_to_bracket_id <> id)

Foreign-key constraints:
    "tournament_brackets_advances_to_bracket_id_foreign" FK (advances_to_bracket_id) REFERENCES tournament_brackets(id) ON DELETE SET NULL
    "tournament_brackets_loser_advances_to_bracket_id_foreign" FK (loser_advances_to_bracket_id) REFERENCES tournament_brackets(id) ON DELETE SET NULL
    "tournament_brackets_match_id_foreign" FK (match_id) REFERENCES matches(id) ON DELETE SET NULL
    "tournament_brackets_participant_a_id_foreign" FK (participant_a_id) REFERENCES tournament_participants(id) ON DELETE SET NULL
    "tournament_brackets_participant_b_id_foreign" FK (participant_b_id) REFERENCES tournament_participants(id) ON DELETE SET NULL
    "tournament_brackets_tournament_stage_id_foreign" FK (tournament_stage_id) REFERENCES tournament_stages(id) ON DELETE CASCADE
    "tournament_brackets_winner_participant_id_foreign" FK (winner_participant_id) REFERENCES tournament_participants(id) ON DELETE SET NULL
```

## Verification

| Gate | Result |
|------|--------|
| `php artisan migrate:fresh --no-interaction` (Phase 1-6 full schema, 31 migrations) | DONE — every migration applied with no errors |
| `php artisan migrate:rollback --step=5 --no-interaction` | DONE — all 5 Phase 6 migrations reversed cleanly |
| Re-apply `php artisan migrate --no-interaction` after rollback | DONE — round-trip green |
| `./vendor/bin/pint --test` on all 5 migration files | PASS — 5 files clean |
| `./vendor/bin/phpstan analyse` on all 5 migration files | PASS — `[OK] No errors` |
| Full project `./vendor/bin/phpstan analyse` (regression check) | PASS — no new errors |
| All 5 CHECK constraints present (verified via `pg_constraint` query) | PASS |
| Partial UNIQUE INDEX on `tournament_brackets(match_id) WHERE match_id IS NOT NULL` present | PASS (verified via `psql \d+ tournament_brackets`) |
| Both self-FKs on `tournament_brackets` present with `ON DELETE SET NULL` | PASS |
| FK cascade matrix matches plan spec | PASS — 16 FKs, every onDelete rule verified |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Self-FKs on `tournament_brackets` reordered to a deferred `Schema::table()` block**

- **Found during:** Task 2 — first `migrate:fresh` attempt
- **Issue:** Postgres rejected the migration with `SQLSTATE[42830]: Invalid foreign key: 7 ERROR: there is no unique constraint matching given keys for referenced table "tournament_brackets"`. Root cause: Laravel 12 Schema::create() emits inline `$table->foreign()` declarations as `ALTER TABLE ... ADD CONSTRAINT FOREIGN KEY` statements BEFORE emitting the `ALTER TABLE ... ADD PRIMARY KEY` for `uuid('id')->primary()`. A self-FK against a table whose PK has not yet been established fails the FK validation. Plan SQL trace (`migrate --pretend`) confirmed the ordering: 7 FK ALTERs first, then PK ALTER, then the post-create `DB::statement` block. The 5 non-self FKs were fine (their targets are already-created tables); the 2 self-FKs were the only failures.
- **Fix:** Removed the two `$table->foreign('advances_to_bracket_id')` / `$table->foreign('loser_advances_to_bracket_id')` calls from inside `Schema::create()` and re-added them in a fresh `Schema::table('tournament_brackets', fn (Blueprint $table) => …)` block immediately after. By that point Laravel has finished the CREATE TABLE + all inline FKs + PK ADD, so the self-FK ALTERs succeed. No semantic change; the resulting schema is identical (both FKs exist with `ON DELETE SET NULL`, confirmed via `\d+ tournament_brackets`).
- **Files modified:** `apps/web/database/migrations/2026_05_15_100300_create_tournament_brackets_table.php`
- **Commit:** `35a7d4b`

This pattern is **NOT documented in the Phase 6 RESEARCH.md** code example for tournament_brackets, so it earns a new locked decision (D-06-02-A) for downstream plans that might add more self-FK tables (e.g., team brackets, bracket aliases). The decision text is in this SUMMARY frontmatter.

No other deviations. Plan executed as written.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-02-01 (Tampering — invalid format) | mitigate | `tournaments_format_check` CHECK present; only 4 LOCKED values pass |
| T-06-02-02 (Tampering — self-advance cycle) | mitigate | `tournament_brackets_no_self_advance` CHECK present; covers both self-FK pointers; NULL allowed |
| T-06-02-03 (Tampering — two brackets share one GameMatch) | mitigate | `tournament_brackets_match_id_unique` partial UNIQUE INDEX WHERE NOT NULL present |
| T-06-02-04 (Tampering — clan registers twice) | mitigate | `tournament_participants_unique` UNIQUE composite present |
| T-06-02-05 (Repudiation — status string mutation) | mitigate | `tournaments_status_check` CHECK present at DB layer |
| T-06-02-06 (DoS — orphan FK from partial revert) | accept | rollback round-trip green; production rollback procedure documented elsewhere |

## Threat Flags

None — no new security-relevant surface introduced beyond what `<threat_model>` already covers.

## Known Stubs

None. All 5 migrations are fully implemented; no TODO / FIXME / placeholder markers.

## Plan Linkages

This plan provides the schema foundation for the rest of Phase 6:

- **Plan 06-03 (models)** binds Eloquent models to these 5 tables. The Wave 0 factory stubs in plan 06-01 are replaced with typed-generic factories that emit valid rows respecting these CHECKs.
- **Plan 06-04..06-09 (services)** write to / read from these tables.
- **Plan 06-08 (BracketAdvancementService)** walks the self-FK chain (`advances_to_bracket_id`, `loser_advances_to_bracket_id`); the no-self-advance CHECK is its DB-layer safety net.
- **Plan 06-06 (BracketMatchMaterialiserService)** writes `tournament_brackets.match_id`; the partial UNIQUE INDEX is the race defence.
- **Plan 06-09 (StandingsCalculatorService)** writes `tournament_standings.{wins,losses,draws,points,tiebreak_score,rank}` and reads back; the `(tournament_stage_id, participant_id)` UNIQUE allows the upsert pattern.

## Self-Check: PASSED

- All 5 created files exist on disk:
  - `apps/web/database/migrations/2026_05_15_100000_create_tournaments_table.php`
  - `apps/web/database/migrations/2026_05_15_100100_create_tournament_participants_table.php`
  - `apps/web/database/migrations/2026_05_15_100200_create_tournament_stages_table.php`
  - `apps/web/database/migrations/2026_05_15_100300_create_tournament_brackets_table.php`
  - `apps/web/database/migrations/2026_05_15_100400_create_tournament_standings_table.php`
- Both commits exist on `master`:
  - `aa9d430` — feat(06-02): migrations 1+2 — tournaments + tournament_participants
  - `35a7d4b` — feat(06-02): migrations 3+4+5 — stages + brackets + standings
- `migrate:fresh` runs all 31 migrations successfully (verified twice).
- `migrate:rollback --step=5 + migrate` round-trip succeeds (reversibility verified).
- Pint + PHPStan clean on all 5 files; full-project PHPStan clean (no regressions).
