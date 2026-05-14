---
phase: 09-polish
plan: 02
subsystem: schema
tags: [wave-1, migrations, schema, notifications, bans, disputes, abuse-reports, discord-outbox, pitfall-7, pitfall-10, pitfall-11, a5-locked, a9-locked]
requires:
  - "09-01 Wave 0 scaffolding (30 Pest RED stubs, 4 factory stubs, 5 i18n skeletons) — schema authored here turns Wave 1+ model/service plans GREEN"
provides:
  - "notifications table (uuid PK, uuidMorphs polymorphic, jsonb data, 3 composite indexes incl. unread + recent + type-dedupe)"
  - "user_notification_preferences table (bigint PK, foreignUuid user_id cascadeOnDelete, composite UNIQUE (user_id, event_type, channel) as unp_unique, supporting unp_user_id_idx)"
  - "bans table (bigint PK, foreignUuid user_id cascadeOnDelete, issued_by + lifted_by user FKs, (user_id, expires_at) index, ban_type as varchar)"
  - "match_disputes table (bigint PK, foreignUuid match_id cascadeOnDelete per A9 LOCKED, raised_by + resolved_by FKs, (status, created_at) + match_id indexes, Pitfall 11 partial UNIQUE one_open_dispute_per_user_per_match)"
  - "abuse_reports table (bigint PK, morph-style target_type/target_id as varchar, foreignUuid reporter cascadeOnDelete, 3 supporting indexes)"
  - "doutmsg_message_type_chk CHECK constraint extended to 9 values incl. user_dm (Pitfall 10 mitigation)"
  - "mps_player_kills_idx composite index (player_id, kills) on match_player_stats for top-N leaderboard (RESEARCH A5)"
affects:
  - "plan 09-03 — Notification model + UserNotificationPreference model + DiscordChannel writer wire over the new tables"
  - "plan 09-04 — NotificationDispatcher idempotency reads notifications_type_idx for dedupe count queries"
  - "plan 09-05 — LeaderboardService::topPlayersByKills uses mps_player_kills_idx"
  - "plan 09-06 — Bell controller reads notifications_unread_idx + notifications_recent_idx"
  - "plan 09-07 — BanService + DisputeService write to bans + match_disputes; enforce ban_type / status enums in application layer"
  - "plan 09-11 — AbuseReport service + report-abuse rate limiter; FormRequest enforces reason_code enum"
tech-stack:
  added: []
  patterns:
    - "Postgres jsonb columns (NOT json) for indexable querying — mirrors articles.title/excerpt/body and match_player_stats.weapons_used (Phase 4/7 idiom)"
    - "uuidMorphs (NOT morphs()) for polymorphic columns referencing UUID PK tables (users) — Phase 7 media table set this precedent; Pitfall 7 LOCKED"
    - "Partial UNIQUE index via raw SQL (CREATE UNIQUE INDEX ... WHERE ...) because Laravel Blueprint cannot express WHERE clause — Phase 2 clan_memberships precedent (one active membership per player); Pitfall 11"
    - "varchar enum columns with application-layer validation (Pest + FormRequest) rather than DB CHECK — keeps schema portable when enum values evolve (used for ban_type, dispute.status, abuse_reports.reason_code/status, user_notification_preferences.event_type/channel)"
    - "DROP+ADD CHECK idiom for discord_outbound_messages.message_type — Postgres has no ALTER CONSTRAINT ... MODIFY for CHECK predicates; mirrors Phase 5/6/7/8 successor migrations"
    - "Composite ASC index supporting DESC ordering via planner backward walk — plain (player_id, kills) covers ORDER BY kills DESC without an explicit DESC definition (RESEARCH A5)"
    - "ALTER COLUMN ... TYPE timestamptz USING ... AT TIME ZONE 'UTC' after Schema::create — project idiom for elevating $table->timestamps() defaults to timestamp-with-tz (matches clans, matches, articles)"
key-files:
  created:
    - "apps/web/database/migrations/2026_05_18_100000_create_notifications_table.php — UUID PK + uuidMorphs + jsonb data + 3 composite indexes"
    - "apps/web/database/migrations/2026_05_18_100100_create_user_notification_preferences_table.php — bigint PK + composite UNIQUE unp_unique + supporting unp_user_id_idx"
    - "apps/web/database/migrations/2026_05_18_100200_create_bans_table.php — site-wide v1 (Open Question 4 LOCKED), no clan_id column"
    - "apps/web/database/migrations/2026_05_18_100300_create_match_disputes_table.php — A9 LOCKED cascadeOnDelete + Pitfall 11 partial UNIQUE"
    - "apps/web/database/migrations/2026_05_18_100400_create_abuse_reports_table.php — morph-style target columns + 3 supporting indexes"
    - "apps/web/database/migrations/2026_05_18_100500_extend_discord_outbound_message_types_for_user_dm.php — DROP+ADD CHECK appending user_dm"
    - "apps/web/database/migrations/2026_05_18_100600_add_match_player_stats_kills_index.php — Schema::table composite index for RESEARCH A5 top-N leaderboard"
  modified: []
decisions:
  - "D-09-02-A — Plan's <interfaces> block referenced constraint name `discord_outbound_messages_message_type_check` (Laravel default), but actual canonical baseline name is `doutmsg_message_type_chk` (set by Phase 5 migration 2026_05_13_170625_create_discord_outbound_messages_table.php). Same Rule 1 deviation pattern as Phase 7 plan 07-02 and Phase 8 plan 08-02 — aligned with on-disk reality, NOT with plan text. Verified live via `SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname='doutmsg_message_type_chk'` before authoring. down() reverts to the 8-value Phase 8 baseline verbatim."
  - "D-09-02-B — ban_type, match_disputes.status, abuse_reports.reason_code/status, user_notification_preferences.event_type/channel intentionally NOT enforced via DB CHECK. Plan task notes explicitly call for application-layer Pest enforcement instead (plans 09-03, 09-07, 09-11). Rationale: enum values evolve across plans (e.g. new ban_type 'shadow' might be needed in v1.5), and varchar+app-validation avoids schema churn. CHECK is reserved for the discord_outbound_messages.message_type column where bot+web tightly couple on the value list."
  - "D-09-02-C — Partial UNIQUE index `one_open_dispute_per_user_per_match` created via raw SQL `CREATE UNIQUE INDEX ... WHERE status='open'` (Pitfall 11). Laravel Blueprint cannot express WHERE on UNIQUE. The down() drops the index explicitly even though dropIfExists('match_disputes') would cascade-drop it — explicit symmetry makes the reversal self-documenting."
  - "D-09-02-D — mps_player_kills_idx created as plain ASC composite (player_id, kills) rather than DESC. Postgres B-tree planner walks the index backwards for ORDER BY kills DESC without a separate index definition. If seq-scan profiling in plan 09-05 LeaderboardService proves the planner is doing a Bitmap Heap Scan + Sort instead of an Index Scan Backward, the index can be re-authored with explicit DESC via raw SQL (RESEARCH A5 fallback). Plain index ships first."
  - "D-09-02-E — abuse_reports.target_id stored as varchar (not uuid) to admit BOTH UUID PK targets (Clan, Player, Article, GameMatch) AND any future bigint PK targets (e.g. future Comment, Forum thread). Trade-off: looser type discipline than a polymorphic uuidMorphs would give, but uuidMorphs forces target_id to uuid which excludes bigint-keyed surfaces. Application code in plan 09-11 must cast/validate target_id appropriately per target_type."
metrics:
  duration_seconds: 353
  duration_human: "~5m 53s"
  completed_at: "2026-05-14T07:21:38Z"
  files_created: 7
  files_modified: 0
  total_files: 7
  migrations_added: 7
  tests_now_passing: 1134  # baseline preserved
  tests_now_skipped: 30    # unchanged from 09-01
  suite_total: 1164
  pint_files_passed: 7      # all 7 new files PASS on pint --test
  phpstan_errors: 0
  lines_added: 503
---

# Phase 9 Plan 02: Wave 1 — Notifications + Moderation Schema Summary

Authored 7 Laravel migrations creating the full Phase 9 schema in one deterministic step: `notifications`, `user_notification_preferences`, `bans`, `match_disputes`, `abuse_reports` tables; extended `doutmsg_message_type_chk` to admit `user_dm`; added `(player_id, kills)` composite index on `match_player_stats` for top-N leaderboard aggregation. `migrate:fresh` applies all 7 cleanly; `migrate:rollback --step=7` reverses cleanly; full Pest baseline (1134 passed + 30 skipped) preserved.

## What Shipped

**Migration files (7, all at 2026_05_18_100[0-6]00 timestamps):**

| # | Timestamp | Migration | Schema Surface |
|---|-----------|-----------|---------------|
| 1 | `100000` | `create_notifications_table` | Laravel polymorphic notifications backing store |
| 2 | `100100` | `create_user_notification_preferences_table` | Per-user × event_type × channel toggle matrix |
| 3 | `100200` | `create_bans_table` | Site-wide bans (Open Question 4 LOCKED — no clan_id) |
| 4 | `100300` | `create_match_disputes_table` | Match dispute workflow + Pitfall 11 partial UNIQUE |
| 5 | `100400` | `create_abuse_reports_table` | Report-abuse review queue |
| 6 | `100500` | `extend_discord_outbound_message_types_for_user_dm` | doutmsg CHECK 8→9 values |
| 7 | `100600` | `add_match_player_stats_kills_index` | mps_player_kills_idx for RESEARCH A5 |

**Schema verification (post `migrate:fresh`):**

`notifications`:
```
 id              | uuid                        | not null | gen_random_uuid()
 notifiable_type | character varying(255)      | not null
 notifiable_id   | uuid                        | not null
 type            | character varying(255)      | not null
 data            | jsonb                       | not null
 read_at         | timestamp(0) with time zone |
 created_at      | timestamp with time zone    |
 updated_at      | timestamp with time zone    |
Indexes:
    "notifications_pkey" PRIMARY KEY, btree (id)
    "notifications_notifiable_type_notifiable_id_index" btree (notifiable_type, notifiable_id)  -- from uuidMorphs
    "notifications_recent_idx" btree (notifiable_type, notifiable_id, created_at)
    "notifications_type_idx" btree (type)
    "notifications_unread_idx" btree (notifiable_type, notifiable_id, read_at)
```

`user_notification_preferences`:
```
Indexes:
    "user_notification_preferences_pkey" PRIMARY KEY, btree (id)
    "unp_unique" UNIQUE CONSTRAINT, btree (user_id, event_type, channel)
    "unp_user_id_idx" btree (user_id)
Foreign-key constraints:
    "user_notification_preferences_user_id_foreign" FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
```

`bans`:
```
Indexes:
    "bans_pkey" PRIMARY KEY, btree (id)
    "bans_issued_by_idx" btree (issued_by_user_id)
    "bans_user_expires_idx" btree (user_id, expires_at)
Foreign-key constraints:
    "bans_issued_by_user_id_foreign" FOREIGN KEY (issued_by_user_id) REFERENCES users(id)
    "bans_lifted_by_user_id_foreign" FOREIGN KEY (lifted_by_user_id) REFERENCES users(id)
    "bans_user_id_foreign" FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
```

`match_disputes`:
```
Indexes:
    "match_disputes_pkey" PRIMARY KEY, btree (id)
    "md_match_idx" btree (match_id)
    "md_status_created_idx" btree (status, created_at)
    "one_open_dispute_per_user_per_match" UNIQUE, btree (match_id, raised_by_user_id) WHERE status::text = 'open'::text
Foreign-key constraints:
    "match_disputes_match_id_foreign" FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE  -- A9 LOCKED
    "match_disputes_raised_by_user_id_foreign" FOREIGN KEY (raised_by_user_id) REFERENCES users(id)
    "match_disputes_resolved_by_user_id_foreign" FOREIGN KEY (resolved_by_user_id) REFERENCES users(id)
```

`abuse_reports`:
```
Indexes:
    "abuse_reports_pkey" PRIMARY KEY, btree (id)
    "ar_reporter_idx" btree (reporter_user_id)
    "ar_status_created_idx" btree (status, created_at)
    "ar_target_idx" btree (target_type, target_id)
Foreign-key constraints:
    "abuse_reports_reporter_user_id_foreign" FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE
    "abuse_reports_reviewed_by_user_id_foreign" FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
```

**`doutmsg_message_type_chk` final IN-list (9 values after migration 6 applies):**

```
CHECK (((message_type)::text = ANY ((ARRAY[
    'match_announce',
    'role_sync',
    'generic',
    'bracket_result_announce',
    'tournament_announce',
    'tournament_announce_update',
    'article_announce',
    'match_result_announce',
    'user_dm'                          -- ← added by 2026_05_18_100500
]::character varying)::text[])))
```

**`mps_player_kills_idx`:**

```
CREATE INDEX mps_player_kills_idx ON public.match_player_stats USING btree (player_id, kills)
```

## Quality Gates

| Gate | Result |
|------|--------|
| `migrate:fresh --force` | ✅ 7/7 DONE — all migrations apply cleanly on a fresh DB |
| `migrate:rollback --step=7 --force` | ✅ 7/7 DONE — partial UNIQUE drops cleanly; CHECK constraint reverts to 8-value Phase 8 baseline verbatim |
| `migrate --force` (re-apply after rollback) | ✅ 7/7 DONE |
| `pest --no-coverage` (full suite) | ✅ **1134 passed + 30 skipped (3783 assertions) in 71.32s** — exact 09-01 baseline preserved |
| `pint --test` on 7 new files | ✅ **PASS** on 7 files (3 from Task 1 + 4 from Task 2) |
| `phpstan analyse --no-progress` (level 8) | ✅ **OK, no errors** |

## Rollback Verification Output

```text
$ docker compose exec web php artisan migrate:rollback --step=7 --force

   INFO  Rolling back migrations.

  2026_05_18_100600_add_match_player_stats_kills_index ........... 4.91ms DONE
  2026_05_18_100500_extend_discord_outbound_message_types_for_user_dm  2.73ms DONE
  2026_05_18_100400_create_abuse_reports_table ................... 5.10ms DONE
  2026_05_18_100300_create_match_disputes_table .................. 5.31ms DONE
  2026_05_18_100200_create_bans_table ............................ 4.86ms DONE
  2026_05_18_100100_create_user_notification_preferences_table ... 4.00ms DONE
  2026_05_18_100000_create_notifications_table ................... 3.14ms DONE
```

Post-rollback inspection confirmed:
- `to_regclass('notifications' | 'user_notification_preferences' | 'bans' | 'match_disputes' | 'abuse_reports')` all returned NULL — every new table dropped.
- `doutmsg_message_type_chk` reverted to the exact 8-value Phase 8 baseline (no `user_dm`).
- `mps_player_kills_idx` no longer present on `match_player_stats`.

Re-apply (`migrate --force`) ran 7/7 DONE without errors — schema is bidirectionally reversible.

## A5 + A9 Notes (LOCKED inline)

**A5 LOCKED — leaderboard index strategy.** RESEARCH (`09-RESEARCH.md`) called for a composite index supporting top-N `ORDER BY kills DESC` on `match_player_stats`. We ship a plain ASC composite (`player_id`, `kills`) — Postgres B-tree planner walks the index backwards for descending ordering without a separate definition. This avoids the maintenance overhead of an explicit DESC index unless seq-scan profiling in plan 09-05 proves the planner is choosing a Bitmap Heap Scan + Sort. Fallback path documented in the migration docblock; plan 09-05 EXPLAIN ANALYZE will verify.

**A9 LOCKED — match_disputes.match_id ON DELETE behaviour.** RESEARCH `<assumptions>` block answered Open Question A9 as `cascadeOnDelete` (when a match row is removed, its dispute log goes with it). Migration 4 implements this verbatim: `$table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete()`. Verified post-migration in `\d match_disputes`: `match_disputes_match_id_foreign … REFERENCES matches(id) ON DELETE CASCADE`. The alternative (`restrictOnDelete`) was explicitly rejected by A9 LOCKED — moderators may need to purge bad matches without first untangling dispute history.

## Deviations from Plan

### Rule 1 — Bug fix (constraint name correction)

**1. [Rule 1 — Bug] Plan referenced non-existent CHECK constraint name `discord_outbound_messages_message_type_check`**
- **Found during:** Task 2 authoring of `extend_discord_outbound_message_types_for_user_dm.php`.
- **Issue:** The plan's `<interfaces>` block prescribed `ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS discord_outbound_messages_message_type_check` — the Laravel-default constraint name. The actual canonical name (set by the Phase 5 migration `2026_05_13_170625_create_discord_outbound_messages_table.php`) is `doutmsg_message_type_chk`. Running the plan's prescribed DROP would no-op (IF EXISTS swallows the miss), then ADD would create a second CHECK constraint with the wrong name — leaving the original `doutmsg_message_type_chk` intact and creating `discord_outbound_messages_message_type_check` alongside it. Both would be enforced simultaneously; the new `user_dm` value would technically pass the new one but the old one (still without `user_dm`) would still reject inserts.
- **Fix applied:** Aligned with the actual on-disk baseline. Used `doutmsg_message_type_chk` in both DROP and ADD statements, matching the exact pattern used by Phase 7 plan 07-02 and Phase 8 plan 08-02 (both of which encountered the same plan-vs-reality drift and resolved it identically). Verified live via `SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname='doutmsg_message_type_chk'` BEFORE authoring the migration.
- **Files modified:** `apps/web/database/migrations/2026_05_18_100500_extend_discord_outbound_message_types_for_user_dm.php`.
- **Commit:** `07d1d8c`.

### No other deviations

- Plan executed exactly as written for all other 6 migrations.
- Rules 2/3/4: N/A — no missing critical functionality found; no blocking issues; no architectural changes needed.

## Authentication Gates

None. Plan ran fully autonomously inside the existing Docker stack (`make up` already running, web + postgres healthy).

## Known Stubs

None introduced by this plan. The 4 factory stubs from plan 09-01 (`BanFactory`, `AbuseReportFactory`, `MatchDisputeFactory`, `UserNotificationPreferenceFactory`) remain `RuntimeException`-throwing — they are intentionally completed by plans 09-03 / 09-07 / 09-11 alongside their respective models. The schema is now ready; models + factories are the next handoff.

## Threat Flags

None. The threat model (T-09-02-01..06) covers all introduced surface:
- jsonb `notifications.data` tampering — admin Filament UI is read-only (mirrors activity_log; enforced in plan 09-07).
- `bans.reason` text disclosure — accepted; moderator-only Filament UI.
- `abuse_reports` DoS via flood — mitigated in plan 09-11 (5/hour rate limiter, auth required).
- `match_disputes.resolution` privilege escalation — plan 09-07 DisputeService enforces moderator permission + writes activity_log row per transition.
- `discord_outbound_messages` CHECK tampering — DB-level CHECK is strict defence-in-depth; new types require auditable migration.
- `notifications` polymorphic id enumeration — accepted; notifiable_id is UUID (non-enumerable); bell controller scopes by `auth()->id()`.

No NEW surface introduced beyond the threat register — every new table maps to a documented threat row.

## Self-Check: PASSED

**Files checked (7 created, 0 modified):**

```
FOUND: apps/web/database/migrations/2026_05_18_100000_create_notifications_table.php
FOUND: apps/web/database/migrations/2026_05_18_100100_create_user_notification_preferences_table.php
FOUND: apps/web/database/migrations/2026_05_18_100200_create_bans_table.php
FOUND: apps/web/database/migrations/2026_05_18_100300_create_match_disputes_table.php
FOUND: apps/web/database/migrations/2026_05_18_100400_create_abuse_reports_table.php
FOUND: apps/web/database/migrations/2026_05_18_100500_extend_discord_outbound_message_types_for_user_dm.php
FOUND: apps/web/database/migrations/2026_05_18_100600_add_match_player_stats_kills_index.php
```

**Commits verified:**

```
FOUND: 30876e2 feat(09-02): create notifications + user_notification_preferences + bans tables
FOUND: 07d1d8c feat(09-02): create match_disputes + abuse_reports + extend discord CHECK + add mps kills index
```

**Schema state verified:**

```
SELECT migration FROM migrations WHERE migration LIKE '2026_05_18%' ORDER BY migration;
→ 7 rows: all 7 Phase 9 Wave 1 migrations recorded.
```

All 7 created files present on disk; both commits resolve in git log with the expected diff (Task 1: 3 files, 216 insertions; Task 2: 4 files, 287 insertions = 7 files, 503 insertions total).
