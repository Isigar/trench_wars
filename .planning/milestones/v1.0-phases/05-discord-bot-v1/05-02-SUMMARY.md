---
phase: 05-discord-bot-v1
plan: 02
subsystem: discord-bot
tags: [wave-1, migrations, outbox, eloquent, logsactivity]
dependency_graph:
  requires: [phase-04-complete, 05-01-complete]
  provides:
    - discord_outbound_messages_table
    - DiscordOutboundMessage_model
    - DiscordOutboundMessage_scopePending_scopeDispatchable
    - DiscordOutboundMessageFactory_real
  affects: [05-04, 05-05, 05-06, 05-07]
tech_stack:
  added: []
  patterns:
    - "Outbox table (RESEARCH Pattern 4): durable pending->dispatching->sent|failed state machine with attempts counter, last_error trace, backoff_until retry deadline"
    - "DB-tier CHECK constraints for enum-like columns (status + message_type) — defence-in-depth on top of Eloquent fillable (T-05-02-01 / T-05-02-02)"
    - "Activity log diff lives in `attribute_changes` (collection) — NOT `properties` — in this version of spatie/laravel-activitylog"
    - "Eloquent compound scope: `scopeDispatchable` filters status=pending AND (backoff_until IS NULL OR <= now())"
    - "foreignUuid->nullOnDelete preserves outbound history when the causer User is deleted (T-05-02-04 partial)"
key_files:
  created:
    - "apps/web/database/migrations/2026_05_13_170625_create_discord_outbound_messages_table.php"
    - "apps/web/app/Models/DiscordOutboundMessage.php"
  modified:
    - "apps/web/database/factories/DiscordOutboundMessageFactory.php (replaces 05-01 RuntimeException stub)"
    - "apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php (replaces 05-01 markTestIncomplete stub — 17 GREEN tests, 38 assertions)"
decisions:
  - "D-05-02-A: `clans.discord_announce_channel_id` is already in the schema and the Clan model — the Phase 2 initial clans migration (2026_05_12_100100_create_clans_table.php, commit 8b7fbfe) added the column AND Filament ClanResource Phase 2 work landed the admin edit UI. The plan's second migration is OMITTED (would have failed with duplicate column). Documented as Rule 1 deviation."
  - "D-05-02-B: spatie/laravel-activitylog Support\\LogOptions has no `dontSubmitEmptyLogs()` method in the installed version — correct method is `dontLogEmptyChanges()`. Plan `<interfaces>` snippet was wrong; aligned to actual installed API."
  - "D-05-02-C: spatie/laravel-activitylog v4+ stores attribute diffs in the `attribute_changes` column (collection cast), NOT in `properties`. Plan-derived assertion path was corrected accordingly. Tests assert via `\$activity->attribute_changes->toArray()['attributes'|'old']`."
metrics:
  duration_seconds: ~405
  completed_date: "2026-05-13"
  tasks_total: 2
  tasks_completed: 2
  commits: 2
  files_changed: 4
---

# Phase 5 Plan 02: Wave 1 — discord_outbound_messages migration + DiscordOutboundMessage model + factory + GREEN test Summary

Phase 5 Wave 1 schema substrate landed: the durable Discord outbox table is in place with CHECK-constrained enums, the `(status, backoff_until)` compound index is wired for the bot poll path, and the DiscordOutboundMessage Eloquent model exposes the canonical `scopePending` + `scopeDispatchable` query API that plan 05-04 (BotApiOutboundController) consumes. The Wave 0 RED stub for the model test flipped GREEN with 17 tests / 38 assertions covering casts, scopes, CHECK constraints, FK nullOnDelete behaviour, and LogsActivity append-only writes.

## Acceptance Criteria

### Task 1 — Migration with CHECK constraints + indexes (commit `56fe94c`)

- [x] Filename: `apps/web/database/migrations/2026_05_13_170625_create_discord_outbound_messages_table.php`
- [x] `up()` builds the table per `<interfaces>` — UUID PK + 10 columns + 2 indexes + timestamps
- [x] `DB::statement` CHECK on `message_type` (allowed: `match_announce`, `role_sync`, `generic`) — verified via `information_schema.check_constraints`
- [x] `DB::statement` CHECK on `status` (allowed: `pending`, `dispatching`, `sent`, `failed`) — verified
- [x] `foreignUuid('causer_user_id')->constrained('users')->nullOnDelete()` — verified by Task 2 test `it nullifies causer_user_id when the causer User is deleted`
- [x] `down()` is `Schema::dropIfExists('discord_outbound_messages')` — CHECKs drop with the table
- [x] `make artisan ARGS="migrate"` applied cleanly
- [x] `make artisan ARGS="migrate:fresh --seed"` round-trip CLEAN (all 23 migrations + 5 seeders ran from scratch)
- [x] `make phpstan` reports **No errors**
- [x] `make pint --test` PASS (316 files clean across the project)

**Plan's prescribed second migration (`*_add_discord_announce_channel_id_to_clans_table.php`) was OMITTED.** Phase 2 already shipped the column in the initial clans migration; adding it again would fail with `column "discord_announce_channel_id" of relation "clans" already exists`. See Deviations § 1.

### Task 2 — Model + factory + GREEN test (commit `56f3f06`)

- [x] `apps/web/app/Models/DiscordOutboundMessage.php`:
  - [x] Namespace `App\Models`; extends `Model`
  - [x] Traits: `HasUuidPrimaryKey` (project's UUIDv4 wrapper) + `HasFactory` + `LogsActivity`
  - [x] `/** @use HasFactory<DiscordOutboundMessageFactory> */` PHPDoc for PHPStan L8
  - [x] `$fillable` lists 9 columns (channel_id, message_type, status, payload, attempts, last_error, sent_message_id, causer_user_id, backoff_until)
  - [x] `casts()` returns `['payload' => 'array', 'attempts' => 'integer', 'backoff_until' => 'datetime']`
  - [x] `causer()` BelongsTo User via `causer_user_id`
  - [x] `scopePending(Builder $q)` filters `status=pending`
  - [x] `scopeDispatchable(Builder $q)` filters `status=pending AND (backoff_until IS NULL OR <= now())`
  - [x] `getActivitylogOptions()` returns `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->setDescriptionForEvent(...)` (plan's `dontSubmitEmptyLogs()` corrected — see Deviations § 2)
- [x] **Clan.php was NOT modified** — `discord_announce_channel_id` is already in the existing `$fillable` from Phase 2 (see Deviations § 1)
- [x] `DiscordOutboundMessageFactory.php` REPLACES plan 05-01's `RuntimeException` stub:
  - [x] `definition()` returns the 9-key array per `<interfaces>`
  - [x] State methods: `pending()`, `dispatching()`, `sent(?string $messageId = null)`, `failed(string $error = ...)`, `roleSync()`
  - [x] No remaining `RuntimeException('placeholder')` — Wave 0 stub fully removed
- [x] `DiscordOutboundMessageModelTest.php` REPLACES Wave 0 stub:
  - [x] **17 `it()` blocks** (38 assertions) — exceeds the plan's 8+ minimum
  - [x] Fillable round-trip
  - [x] Payload cast round-trip (array → JSONB → array, including nested embed)
  - [x] Attempts cast to int
  - [x] backoff_until cast to Carbon
  - [x] scopePending only returns status=pending rows (one out of four)
  - [x] scopeDispatchable excludes dispatching/sent/failed
  - [x] scopeDispatchable excludes pending rows with future backoff_until
  - [x] scopeDispatchable includes pending rows with past backoff_until
  - [x] scopeDispatchable includes pending rows with null backoff_until
  - [x] CHECK constraint rejects unknown message_type (QueryException)
  - [x] CHECK constraint rejects unknown status (QueryException)
  - [x] Happy path accepts each valid message_type enum value
  - [x] Happy path accepts each valid status enum value
  - [x] nullOnDelete: causer User force-deleted → causer_user_id becomes null
  - [x] causer() BelongsTo relation resolves
  - [x] Activity log row written on create (D-012)
  - [x] Activity log row written on status transition (D-012) — verifies `attribute_changes['attributes']['status'] == 'dispatching'` AND `attribute_changes['old']['status'] == 'pending'`
- [x] `make pest --filter=DiscordOutboundMessageModelTest` → **17 passed, 38 assertions, 0 incomplete, 0 failed**
- [x] `make phpstan` → No errors (whole project, not just new files)
- [x] `make pint --test` → 316 files clean

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug / Plan-data conflict] `clans.discord_announce_channel_id` column ALREADY exists in the schema (and Clan model fillable) from Phase 2**

- **Found during:** Task 1 prep (initial read of `apps/web/database/migrations/2026_05_12_100100_create_clans_table.php` and `apps/web/app/Models/Clan.php`)
- **Issue:** The plan asks for a second migration `*_add_discord_announce_channel_id_to_clans_table.php` plus a Clan model amendment to append `discord_announce_channel_id` to `$fillable`. BOTH are already in place:
  - The Phase 2 initial clans migration (commit `8b7fbfe`, line 35) calls `$table->text('discord_announce_channel_id')->nullable();` immediately after `discord_role_id` — exactly the column placement the plan prescribes.
  - The Clan model (`apps/web/app/Models/Clan.php` line 48) already has `'discord_announce_channel_id'` as the last element of `$fillable`.
  - The Filament ClanResource (`apps/web/app/Filament/Resources/ClanResource.php` lines 125–126) already exposes the field for admin edit, with `__('admin.clan.fields.discord_announce_channel_id')` i18n key wired in `apps/web/lang/en/admin.php:155`.
- **Why this happened:** Phase 2 plan 02-02-1 ("clans + clan_tags + clan_clan_tag migrations") landed the column proactively (Phase 2 was authored after Phase 5 RESEARCH had Q1 resolved). The Phase 5 plan was not updated to reflect that the column was already in place.
- **Fix:** Skipped the second migration entirely. Running the plan-prescribed migration verbatim would have failed at `ALTER TABLE clans ADD COLUMN discord_announce_channel_id` with `column already exists`. Skipped the Clan model amendment for the same reason.
- **Verification:** `SELECT column_name FROM information_schema.columns WHERE table_name='clans'` returns `discord_announce_channel_id` as column 9 (positioned after `discord_role_id` at column 8 — exactly the `after('discord_role_id')` ordering the plan requested).
- **Files affected (or rather: NOT affected):** plan-prescribed `*_add_discord_announce_channel_id_to_clans_table.php` (skipped), `apps/web/app/Models/Clan.php` (no edit needed)
- **Commit:** N/A — work was already committed in Phase 2

**2. [Rule 1 — Bug] Plan's `dontSubmitEmptyLogs()` method does not exist in installed spatie/laravel-activitylog**

- **Found during:** Task 2 first `make pest` run
- **Issue:** Plan `<interfaces>` block prescribed `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->...`. Running the test produced `BadMethodCallException: Call to undefined method Spatie\Activitylog\Support\LogOptions::dontSubmitEmptyLogs()`.
- **Root cause:** The actual method name in the installed spatie/laravel-activitylog version is `dontLogEmptyChanges()` (verified by reading `vendor/spatie/laravel-activitylog/src/Support/LogOptions.php` — the available `dont*` methods are `dontLogFillable`, `dontLogIfAttributesChangedOnly`, `dontLogEmptyChanges`). `dontSubmitEmptyLogs` is from a different library / older version.
- **Fix:** Replaced `dontSubmitEmptyLogs()` with `dontLogEmptyChanges()` in `DiscordOutboundMessage::getActivitylogOptions()`. Behaviour is equivalent: empty-diff updates are not logged.
- **Files affected:** `apps/web/app/Models/DiscordOutboundMessage.php`
- **Commit:** `56f3f06`

**3. [Rule 1 — Bug] Activity log diff lives in `attribute_changes`, NOT in `properties` (initial test assertion path)**

- **Found during:** Task 2 second `make pest` run (after fix § 2)
- **Issue:** Initial draft of the status-transition test asserted `$activity->properties->toArray()['attributes']['status']`. Test failed with `Failed asserting that null is identical to 'dispatching'.` because `properties` is the custom-data bag (always empty for vanilla LogsActivity-driven writes); the actual attribute diff lives in a separate `attribute_changes` column on the activity_log table.
- **Root cause:** Read `vendor/spatie/laravel-activitylog/src/Support/ActivityLogger.php` line 103: `withChanges($changes)` sets `$this->getActivity()->attribute_changes = collect($changes);` — properties and attribute_changes are distinct columns. The Activity model casts `attribute_changes` to `collection` (v4+ trait). The plan-derived test path was wrong.
- **Fix:** Updated the status-transition assertion to read `$activity->attribute_changes->toArray()`. Added a second assertion confirming both `attributes.status == 'dispatching'` AND `old.status == 'pending'` (verifying the diff captures BOTH sides).
- **Files affected:** `apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php`
- **Commit:** `56f3f06`

**4. [Rule 1 — Style] Pint `fully_qualified_strict_types` violation on test file**

- **Found during:** Task 2 `make pint --test` after fix § 3
- **Issue:** Initial draft used `expect(...)->toBeInstanceOf(\Illuminate\Support\Carbon::class)` inline FQN. Pint preset requires a `use` import.
- **Fix:** Ran `./vendor/bin/pint` (auto-fix) — Pint hoisted the FQN to a `use Illuminate\Support\Carbon;` import at the top of the test file.
- **Files affected:** `apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php`
- **Commit:** `56f3f06`

### Authentication Gates

None — Wave 1 is schema + Eloquent only; no external auth required.

## Files Created/Modified

```
4 files changed, 434 insertions(+), 20 deletions(-)
```

### Created (2)

```
apps/web/database/migrations/2026_05_13_170625_create_discord_outbound_messages_table.php
apps/web/app/Models/DiscordOutboundMessage.php
```

### Modified (2)

```
apps/web/database/factories/DiscordOutboundMessageFactory.php  (Wave 0 stub -> real definition + state methods)
apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php  (markTestIncomplete -> 17 GREEN tests)
```

### Plan-prescribed but NOT created/modified (1 each — see Deviations § 1)

```
NOT CREATED:  apps/web/database/migrations/*_add_discord_announce_channel_id_to_clans_table.php
NOT MODIFIED: apps/web/app/Models/Clan.php
```

## Schema Verification (information_schema)

### `discord_outbound_messages` columns (in ordinal_position order)

| # | column           | type                      | nullable | default              |
|---|------------------|---------------------------|----------|----------------------|
| 1 | id               | uuid                      | NO       | `gen_random_uuid()`  |
| 2 | channel_id       | text                      | NO       | —                    |
| 3 | message_type     | character varying         | NO       | —                    |
| 4 | status           | character varying         | NO       | `'pending'`          |
| 5 | payload          | jsonb                     | NO       | —                    |
| 6 | attempts         | smallint                  | NO       | `0`                  |
| 7 | last_error       | text                      | YES      | —                    |
| 8 | sent_message_id  | text                      | YES      | —                    |
| 9 | causer_user_id   | uuid                      | YES      | —                    |
|10 | backoff_until    | timestamp with time zone  | YES      | —                    |
|11 | created_at       | timestamp with time zone  | YES      | —                    |
|12 | updated_at       | timestamp with time zone  | YES      | —                    |

### CHECK constraints

| name                       | clause                                                                  |
|----------------------------|-------------------------------------------------------------------------|
| `doutmsg_message_type_chk` | `(message_type)::text = ANY ((ARRAY['match_announce','role_sync','generic'])::text[])` |
| `doutmsg_status_chk`       | `(status)::text = ANY ((ARRAY['pending','dispatching','sent','failed'])::text[])`      |

### Indexes

| index                            | definition                                                              |
|----------------------------------|-------------------------------------------------------------------------|
| `discord_outbound_messages_pkey` | unique btree(id)                                                        |
| `doutmsg_status_backoff_idx`     | btree(status, backoff_until) — supports scopeDispatchable poll query   |
| `doutmsg_type_idx`               | btree(message_type) — supports Filament admin filter by type           |

### `clans` final column order (relevant subset)

…, `status`, `discord_role_id`, **`discord_announce_channel_id`**, `created_at`, … — confirms the column is positioned `after('discord_role_id')` per plan requirement (Phase 2 placement is identical to what the omitted migration would have produced).

## Factory State Methods Catalog

| state             | resulting row                                                       |
|-------------------|---------------------------------------------------------------------|
| (default)         | status=pending, message_type=match_announce, payload `{kind: placeholder}` |
| `pending()`       | (explicit alias of default — for test readability)                  |
| `dispatching()`   | status=dispatching, attempts=1                                      |
| `sent($id?)`      | status=sent, sent_message_id=$id ?? random snowflake                |
| `failed($err?)`   | status=failed, last_error=$err ?? `'Discord 500 — Internal Server Error'` |
| `roleSync()`      | message_type=role_sync, payload `{discord_user_id, discord_role_id, action: add}` |

## Test Outcome

```text
$ docker compose exec web ./vendor/bin/pest tests/Feature/Models/DiscordOutboundMessageModelTest.php --no-coverage
Tests:    17 passed (38 assertions)
Duration: 1.29s

$ docker compose exec web ./vendor/bin/pest --filter='Bot|DiscordOutbound' --no-coverage
Tests:    11 incomplete, 18 passed (43 assertions)
Duration: 1.53s

$ docker compose exec web ./vendor/bin/pest --no-coverage
Tests:    11 incomplete, 510 passed (1497 assertions)
Duration: 23.80s

$ docker compose exec web ./vendor/bin/pint --test
PASS  316 files

$ docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress
[OK] No errors

$ docker compose exec web php artisan migrate:status | grep discord_outbound
  2026_05_13_170625_create_discord_outbound_messages_table ........... [1] Ran
```

### CHECK Constraint Negative-Path Test Outcome

The Pest assertions

```php
expect(fn () => DiscordOutboundMessage::factory()->create(['message_type' => 'foobar']))
    ->toThrow(QueryException::class);
expect(fn () => DiscordOutboundMessage::factory()->create(['status'       => 'banana']))
    ->toThrow(QueryException::class);
```

both PASS — Postgres raises `SQLSTATE[23514]: Check violation` and Laravel wraps it as `Illuminate\Database\QueryException`. Defence-in-depth (T-05-02-01 + T-05-02-02) verified.

## Wave 0 Baseline Movement

| Marker                                                  | Before 05-02       | After 05-02        | Δ            |
|---------------------------------------------------------|--------------------|--------------------|--------------|
| Pest full suite                                         | 12 incomplete / 493 passed (1459 assertions) | 11 incomplete / 510 passed (1497 assertions) | -1 incomplete, +17 passed, +38 assertions |
| `--filter='Bot\|DiscordOutbound'`                       | 12 incomplete / 1 passed (5 assertions)      | 11 incomplete / 18 passed (43 assertions)    | same delta |
| `make pint --test`                                      | PASS 314 files     | PASS 316 files (new model + factory ack)     | +2 files    |
| `make phpstan`                                          | No errors          | No errors          | unchanged    |

## Self-Check: PASSED

- [x] `apps/web/database/migrations/2026_05_13_170625_create_discord_outbound_messages_table.php` exists
- [x] `apps/web/app/Models/DiscordOutboundMessage.php` exists
- [x] `apps/web/database/factories/DiscordOutboundMessageFactory.php` exists (modified)
- [x] `apps/web/tests/Feature/Models/DiscordOutboundMessageModelTest.php` exists (modified)
- [x] Commit `56fe94c` exists in `git log --oneline -5` (Task 1: migration)
- [x] Commit `56f3f06` exists in `git log --oneline -5` (Task 2: model + factory + test)
- [x] `discord_outbound_messages` table queryable; CHECK constraints + indexes verified via information_schema
- [x] `clans.discord_announce_channel_id` column present (from Phase 2)
- [x] `make pest --filter=DiscordOutboundMessageModelTest` GREEN: 17/17 passed
- [x] No Pint failures / PHPStan errors / Pest regressions
