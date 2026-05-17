---
phase: 07-cms
plan: 02
subsystem: cms-schema
tags:
  - wave-1
  - migrations
  - postgres-fts
  - tsvector-triggers
  - gin-indexes
  - medialibrary-uuid-morphs
  - discord-outbound-check-extension
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-01-SUMMARY.md          # medialibrary published migration to rename + amend
    - .planning/phases/02-clans/                        # clans table exists for FTS trigger backfill
    - .planning/phases/06-tournaments-brackets/06-10-SUMMARY.md  # doutmsg CHECK baseline (6 values)
  provides:
    - "categories table — uuid PK + slug UNIQUE + jsonb name + softDeletes + gen_random_uuid() DB-side default"
    - "articles table — uuid PK + slug UNIQUE + (title,excerpt,body) jsonb + status CHECK + FK chain (category restrict + author nullOnDelete) + (status, published_at) composite index"
    - "articles.search_vector + clans.search_vector + players.search_vector tsvector columns + GIN indexes + plpgsql trigger functions + BEFORE INSERT OR UPDATE triggers maintaining them"
    - "media table — medialibrary vendor-published migration RENAMED to Wave 1 timestamp + morphs swapped to uuidMorphs (Phase 5 D-05-03-B precedent)"
    - "discord_outbound_messages.message_type CHECK extended with article_announce (7 values total post-migration)"
    - "FtsBackfillTest GREEN — 4 it() blocks asserting trigger sync on articles + clans + players via DB::table raw inserts"
  affects:
    - apps/web/database/migrations/                     # +4 new + 1 renamed file
    - apps/web/tests/Feature/Articles/FtsBackfillTest.php  # RED stub -> 4 GREEN cases
    - Postgres schema (articles, categories, media, clans, players, discord_outbound_messages)
tech-stack:
  added: []
  patterns:
    - "Postgres tsvector + GIN + BEFORE INSERT OR UPDATE plpgsql trigger (RESEARCH Pattern 3 verbatim) — survives raw-SQL writes (Pitfall 3 mitigation)"
    - "DROP+ADD CHECK constraint idiom for enum-extension migration (Phase 5/6 canonical idiom — plans 06-08 + 06-10 precedent)"
    - "Vendor-migration RENAME pattern — medialibrary published migration renamed in-place to Wave 1 timestamp so it runs AFTER articles (Pitfall 9 ordering)"
    - "Vendor-migration AMENDMENT pattern — morphs('model') → uuidMorphs('model') so the polymorphic FK is type-compatible with the project's uuid PKs (Phase 5 D-05-03-B canonical fix)"
    - "PHP nowdoc heredoc (<<<'SQL') for plpgsql trigger function bodies — escapes Postgres $$ delimiters without PHP variable interpolation noise"
    - "ALTER TABLE … ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC' — Phase 2 idiom carried forward for new tables"
    - "DB-side gen_random_uuid() column default for uuid PKs — defence-in-depth for seeders that bypass Eloquent ID generation"
key-files:
  created:
    - apps/web/database/migrations/2026_05_15_120000_create_categories_table.php
    - apps/web/database/migrations/2026_05_15_120100_create_articles_table.php
    - apps/web/database/migrations/2026_05_15_120300_add_fts_to_articles_clans_players.php
    - apps/web/database/migrations/2026_05_15_120400_extend_discord_outbound_message_types_for_article_announce.php
  modified:
    - apps/web/database/migrations/2026_05_15_120200_create_media_table.php  # renamed from 2026_05_13_234858_… + morphs→uuidMorphs
    - apps/web/tests/Feature/Articles/FtsBackfillTest.php                    # RED stub → 4 GREEN cases
decisions:
  - "D-07-02-A — clans.search_vector trigger indexes (name + tag + description->>'en' + slug). clans.name is plain text (not JSONB) per Phase 2 schema; clans.tag is INCLUDED because the league directory is searched by 4-char clan tags (e.g. '91st') as often as by full name. This is a one-line widening over the plan's must_haves which mentioned only name + description + slug."
  - "D-07-02-B — players.search_vector trigger indexes ONLY display_name + slug (D-018 enforcement). The plan's <interfaces> mentioned 'username' which is on the users table (not players); since the trigger fires per-row on players, it cannot read username without a subquery (CPU cost on every player INSERT/UPDATE). display_name is the public-by-default Phase 2 player field and is the correct FTS surface. real_name + discord_tag stay private-tiered (PlayerPrivacyGate at query time, plan 07-08)."
  - "D-07-02-C — discord_outbound_messages CHECK baseline was 6 values (not 7 as plan 07-02 must_haves line 24 asserted). The plan's reference to match_announce_update was documentation drift originating in plan 06-10's comment block; no migration ever added that value. The up() migration extends 6→7 (adds article_announce); down() restores Phase 6's 6-value baseline. Verified via pg_get_constraintdef on baseline DB before authoring the migration."
  - "Open Question 4 LOCKED inline (per plan): articles.slug is author-provided with FormRequest unique-rule (plan 07-05); NO auto-suffix on collision — permalink integrity > UX convenience."
  - "Open Question 5 LOCKED inline (per plan): categories.slug + articles.slug are non-translatable (single-slug v1). Per-locale slugs deferred to D-013 future work."
  - "Open Question 1 LOCKED inline (per plan): articles.allow_discord_announce defaults true; routes to global config('discord.league_announce_channel_id') in v1 (no per-article channel override)."
metrics:
  duration: 7m 14s
  completed: 2026-05-14
  tasks: 2
  files_created: 4   # 4 new migrations
  files_modified: 2  # 1 renamed migration + 1 test (RED → GREEN)
  commits: 2
---

# Phase 7 Plan 2: Wave 1 Migrations Summary

Phase 7 Wave 1 — 5 migrations land the CMS schema substrate (categories + articles + medialibrary media + FTS triggers + Discord outbound CHECK extension). All schema invariants (uuid PKs, FK chains, CHECK constraints, partial UNIQUE on slug, tsvector triggers, GIN indexes) are now observable directly in psql, independent of any Eloquent model. Plan 07-03 lands Article + Category models + factories on top of this known-good DB shape.

## Migration Manifest

| # | Timestamp | File | Purpose |
|---|-----------|------|---------|
| 1 | `2026_05_15_120000` | `create_categories_table.php` | Categories taxonomy (uuid PK + slug UNIQUE + jsonb name + softDeletes) |
| 2 | `2026_05_15_120100` | `create_articles_table.php` | Articles entity (11 columns + status CHECK + 2 FKs + composite index) |
| 3 | `2026_05_15_120200` | `create_media_table.php` | medialibrary vendor migration **renamed + uuidMorphs amendment** |
| 4 | `2026_05_15_120300` | `add_fts_to_articles_clans_players.php` | 3 tsvector cols + 3 trigger fns + 3 GIN indexes + 3 backfills |
| 5 | `2026_05_15_120400` | `extend_discord_outbound_message_types_for_article_announce.php` | DROP+ADD CHECK to add `article_announce` |

`docker compose exec web php artisan migrate:fresh --seed --force` exits 0; all 21 migrations apply in sequence; seed steps (PermissionSeeder, DiscordGuildSeeder, BotServiceUserSeeder, ClanTagSeeder, GameSeeder) all complete.

## Schema Invariants Verified in psql

### `articles` table (`\d articles`)

```text
                                         Table "public.articles"
         Column         |            Type             | Collation | Nullable |          Default           
------------------------+-----------------------------+-----------+----------+----------------------------
 id                     | uuid                        |           | not null | gen_random_uuid()
 slug                   | character varying(200)      |           | not null | 
 category_id            | uuid                        |           | not null | 
 title                  | jsonb                       |           | not null | 
 excerpt                | jsonb                       |           |          | 
 body                   | jsonb                       |           | not null | 
 status                 | character varying(20)       |           | not null | 'draft'::character varying
 scheduled_at           | timestamp(0) with time zone |           |          | 
 published_at           | timestamp(0) with time zone |           |          | 
 author_user_id         | uuid                        |           |          | 
 allow_discord_announce | boolean                     |           | not null | true
 created_at             | timestamp with time zone    |           |          | 
 updated_at             | timestamp with time zone    |           |          | 
 deleted_at             | timestamp with time zone    |           |          | 
 search_vector          | tsvector                    |           |          | 
Indexes:
    "articles_pkey" PRIMARY KEY, btree (id)
    "articles_search_vector_idx" gin (search_vector)
    "articles_slug_unique" UNIQUE CONSTRAINT, btree (slug)
    "articles_status_published_at_idx" btree (status, published_at)
Check constraints:
    "articles_status_chk" CHECK (status::text = ANY (ARRAY['draft'::character varying, 'scheduled'::character varying, 'published'::character varying]::text[]))
Foreign-key constraints:
    "articles_author_user_id_foreign" FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
    "articles_category_id_foreign" FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
Triggers:
    articles_search_vector_update BEFORE INSERT OR UPDATE ON articles FOR EACH ROW EXECUTE FUNCTION articles_search_vector_trigger()
```

Confirms: uuid PK + gen_random_uuid() default, slug UNIQUE, status CHECK, both FKs (category restrict, author nullOnDelete), composite (status, published_at) index, GIN search_vector index, BEFORE INSERT OR UPDATE trigger.

### `categories` table (`\d categories`)

```text
                            Table "public.categories"
   Column   |           Type           | Collation | Nullable |      Default      
------------+--------------------------+-----------+----------+-------------------
 id         | uuid                     |           | not null | gen_random_uuid()
 slug       | character varying(200)   |           | not null | 
 name       | jsonb                    |           | not null | 
 created_at | timestamp with time zone |           |          | 
 updated_at | timestamp with time zone |           |          | 
 deleted_at | timestamp with time zone |           |          | 
Indexes:
    "categories_pkey" PRIMARY KEY, btree (id)
    "categories_slug_unique" UNIQUE CONSTRAINT, btree (slug)
Referenced by:
    TABLE "articles" CONSTRAINT "articles_category_id_foreign" FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
```

Confirms: uuid PK + default, slug UNIQUE, name JSONB, articles FK reverse reference visible.

### `media` table (`\d media`) — uuidMorphs amendment verified

```text
 id                    | bigint                         | not null | nextval('media_id_seq'::regclass)
 model_type            | character varying(255)         | not null | 
 model_id              | uuid                           | not null |     ← uuidMorphs (NOT bigInteger)
 uuid                  | uuid                           |          | 
 collection_name       | character varying(255)         | not null | 
 ...
Indexes:
    "media_pkey" PRIMARY KEY, btree (id)
    "media_model_type_model_id_index" btree (model_type, model_id)
    "media_order_column_index" btree (order_column)
    "media_uuid_unique" UNIQUE CONSTRAINT, btree (uuid)
```

`model_id` is `uuid` — confirms `$table->uuidMorphs('model')` worked. Plan 07-03 can attach hero images to `Article` (whose `id` is uuid) without polymorphic FK type drift.

### CHECK constraint extension (Discord outbound)

```sql
SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname='doutmsg_message_type_chk';

 CHECK (((message_type)::text = ANY (
   (ARRAY['match_announce'::character varying,
          'role_sync'::character varying,
          'generic'::character varying,
          'bracket_result_announce'::character varying,
          'tournament_announce'::character varying,
          'tournament_announce_update'::character varying,
          'article_announce'::character varying]::text[]))))
```

**7 permitted values post-migration** (`article_announce` added; baseline was 6). See D-07-02-C deviation note below.

### FTS triggers (`SELECT trigger_name … FROM information_schema.triggers`)

```text
         trigger_name          | event_object_table | action_timing 
-------------------------------+--------------------+---------------
 articles_search_vector_update | articles           | BEFORE
 clans_search_vector_update    | clans              | BEFORE
 players_search_vector_update  | players            | BEFORE
```

All 3 BEFORE INSERT OR UPDATE FOR EACH ROW triggers present and bound to their respective tables.

## FtsBackfillTest — 4 GREEN cases (RED stub replaced)

| Case | Assertion |
|------|-----------|
| `populates articles.search_vector via trigger on raw insert` | DB::table insert with title='Rifleman Tactics Guide' → search_vector non-null + matches `plainto_tsquery('simple', 'rifleman')` |
| `refires trigger on update to articles.title` | Insert with title='One alpha' (matches 'alpha'), UPDATE to title='Two bravo' → 'alpha' no longer matches, 'bravo' matches |
| `populates clans.search_vector for raw-inserted clan` | DB::table insert with name='Phoenix Battalion' + fresh owner user → search_vector non-null + matches `'phoenix'` |
| `populates players.search_vector via trigger on raw insert` | DB::table insert with display_name='Sergeant Tango' → search_vector non-null + matches `'tango'` |

`docker compose exec web ./vendor/bin/pest --filter=FtsBackfillTest --no-coverage` → **4 passed / 9 assertions / 1.47s**.

Full pest suite: **870 passed / 16 expected Wave 0 RED stubs** (for plans 07-03..07-12). FtsBackfillTest is no longer in the failure list — confirmed transitioned from RED to GREEN. No regression on Phase 1-6 tests.

## Pint + PHPStan Gates

| Gate | Files | Result |
|------|-------|--------|
| `make pint --test` | 5 new migrations + FtsBackfillTest | **PASS** (Pint auto-fixed concat_space + class_definition + braces_position issues during authoring; final --test exits clean) |
| `make phpstan` | 5 new migrations + FtsBackfillTest | **[OK] No errors** (Larastan L8) |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] discord_outbound_messages CHECK baseline was 6 values, not 7 as plan asserted**
- **Found during:** Task 1 step (e), pre-authoring inspection of existing constraint via `SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname='doutmsg_message_type_chk';`
- **Issue:** Plan 07-02 must_haves line 24 reads: *"the existing list of 7 permitted values (match_announce, match_announce_update, role_sync, generic, bracket_result_announce, tournament_announce, tournament_announce_update) — total 8 values after this migration"*. The actual DB baseline contains 6 values (no `match_announce_update`). That value was referenced in plan 06-10's comment block (line 31 of `2026_05_15_100600_extend_discord_outbound_message_types_for_tournament_announce.php`) as "existing convention" but no migration ever added it to the constraint.
- **Fix:** up() extends 6→7 (adds `article_announce`); down() restores Phase 6's actual 6-value baseline. Diverged from plan's "7 → 8" framing but aligned with actual DB state. Plan 07-06 ArticleObserver will need to enqueue `article_announce` against a 7-value (not 8-value) accepted set.
- **Files modified:** `apps/web/database/migrations/2026_05_15_120400_extend_discord_outbound_message_types_for_article_announce.php`
- **Commit:** `6a72a86`

**2. [Rule 2 — Missing critical functionality, clarification] players FTS source field: display_name (not username)**
- **Found during:** Task 1 step (d), authoring the players trigger.
- **Issue:** Plan `<interfaces>` line 117 reads: *"players: username + (any public-facing display fields ...)"*. There is no `players.username` column — username lives on `users` (Phase 1 schema). Indexing across the JOIN would require either a denormalised column on `players` or a subquery in the trigger (CPU cost on every player INSERT/UPDATE — unacceptable for trigger fire path).
- **Fix:** Players trigger indexes `display_name + slug` (both public-by-default per D-018). users.username is gated by login-flow privacy already; if it must be FTS-searchable, plan 07-08 SearchService can add a separate users-table query.
- **Files modified:** `apps/web/database/migrations/2026_05_15_120300_add_fts_to_articles_clans_players.php`
- **Commit:** `6a72a86`

**3. [Rule 2 — Missing critical functionality, widening] clans FTS source field: include tag**
- **Found during:** Task 1 step (d), authoring the clans trigger.
- **Issue:** Plan `<interfaces>` line 116 enumerates clans indexed columns as "name->>'en' + description->>'en' + slug". `clans.name` is plain text (not JSONB) per Phase 2 schema — `name->>'en'` would yield NULL (no JSONB unwrap). Additionally, the league directory is searched by 4-char clan tag (e.g. '91st') as often as by full name; tag is canonical clan identity in the Discord guild.
- **Fix:** clans trigger indexes `name (text) + tag (text) + description->>'en' (jsonb unwrap) + slug`. One-line widening over plan; defensible as "must_haves widening to honour actual schema + D-018 public surface".
- **Files modified:** `apps/web/database/migrations/2026_05_15_120300_add_fts_to_articles_clans_players.php`
- **Commit:** `6a72a86`

**4. [Rule 1 — Bug] FtsBackfillTest clans case: ownerUserId was null after RefreshDatabase**
- **Found during:** Task 2 first pest run; clans case failed `expect($ownerUserId)->not->toBeNull()`.
- **Issue:** Pest's `RefreshDatabase` trait rolls back the seeded `bot-service-user` between tests. The test attempted to reuse that seeded user as clan owner but the row was gone by the time the test ran.
- **Fix:** Create a fresh user inline via `DB::table('users')->insert(...)` before the clan insert. Matches Phase 6 test convention (factory-bypass via raw DB writes when models aren't yet present).
- **Files modified:** `apps/web/tests/Feature/Articles/FtsBackfillTest.php`
- **Commit:** `7f4009e`

### Auth gates encountered

None.

### Architectural changes (Rule 4)

None.

## Threat Model Status

| Threat ID | Status |
|-----------|--------|
| T-07-02-01 (slug uniqueness race) | **mitigated** — `articles_slug_unique` btree UNIQUE constraint enforced at DB layer; FormRequest unique-rule (plan 07-05) is UX layer |
| T-07-02-02 (status CHECK bypass) | **mitigated** — `articles_status_chk` enforced; observer (plan 07-06) adds only-forward state machine |
| T-07-02-03 (private fields in FTS index) | **mitigated** — players trigger indexes only `display_name + slug`; `real_name` and `discord_tag` intentionally absent |
| T-07-02-04 (DOS via unbounded body) | **accepted** — body NOT indexed in v1 (only title + excerpt + slug for articles); revisit on Pitfall 7 horizon |
| T-07-02-05 (CHECK downgrade safety) | **mitigated** — down() restores Phase 6's actual 6-value baseline verbatim (corrected for D-07-02-C) |
| T-07-02-06 (mass-insert bypass) | **mitigated** — Postgres BEFORE INSERT OR UPDATE trigger fires regardless of writer; FtsBackfillTest case "populates players.search_vector via trigger on raw insert" exercises this path explicitly |

## Open Question Resolutions

| OQ | Resolution |
|----|------------|
| OQ-1 (Discord article-announce channel routing) | **LOCKED inline (per plan):** `allow_discord_announce` defaults true on articles; v1 routes via `config('discord.league_announce_channel_id')` (global league channel). Per-article channel override deferred. |
| OQ-4 (slug auto-suffix on collision) | **LOCKED inline (per plan):** No auto-suffix. FormRequest unique-rule (plan 07-05) surfaces violation as Filament validation error. Permalink integrity > UX convenience. |
| OQ-5 (per-locale slugs) | **LOCKED inline (per plan):** Single-slug v1; both `categories.slug` and `articles.slug` are non-translatable. Per-locale slugs deferred (D-013 future work). |

## Known Stubs

None — Task 2 GREEN-ed the only Phase-7 plan-07-02-owned RED stub (FtsBackfillTest). 16 Wave 0 RED stubs remain for plans 07-03..07-12, each name-tagged in placeholder text.

## Commit Trail

| Task | Commit | Files |
|------|--------|-------|
| 1: 5 migrations + migrate:fresh --seed exit 0 | `6a72a86` | 4 new + 1 renamed migration |
| 2: FtsBackfillTest RED → 4 GREEN | `7f4009e` | tests/Feature/Articles/FtsBackfillTest.php |

## Self-Check

- [x] `apps/web/database/migrations/2026_05_15_120000_create_categories_table.php` — FOUND
- [x] `apps/web/database/migrations/2026_05_15_120100_create_articles_table.php` — FOUND
- [x] `apps/web/database/migrations/2026_05_15_120200_create_media_table.php` — FOUND (renamed)
- [x] `apps/web/database/migrations/2026_05_15_120300_add_fts_to_articles_clans_players.php` — FOUND
- [x] `apps/web/database/migrations/2026_05_15_120400_extend_discord_outbound_message_types_for_article_announce.php` — FOUND
- [x] `apps/web/tests/Feature/Articles/FtsBackfillTest.php` — FOUND (replaced)
- [x] commit `6a72a86` — FOUND in git log
- [x] commit `7f4009e` — FOUND in git log

## Self-Check: PASSED
