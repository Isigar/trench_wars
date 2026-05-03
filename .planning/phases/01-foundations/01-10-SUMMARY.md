---
phase: 01-foundations
plan: 10
subsystem: identity-schema-and-models
tags:
  - eloquent-uuid-pk-v4
  - has-uuids-trait-override
  - postgres-citext-email
  - postgres-jsonb-translatable-ready
  - postgres-check-constraints
  - foreign-key-restrict-on-delete
  - foreign-key-cascade-on-delete
  - softdeletes-players
  - players-1to1-users
  - player-privacy-1to1-players
  - laravel-12-fillable-strict
  - phpstan-level-8-clean
  - pest-feature-tests
dependency_graph:
  requires:
    - postgres-extensions-migration        # plan 01-04 — uuid-ossp + pgcrypto + citext enabled in trenchwars + trenchwars_test
    - pest-4-test-framework                # plan 01-05 — RefreshDatabase wired in tests/Pest.php
    - phpstan-level-8-gate                 # plan 01-05 — extends to apps/web/app/Models + database paths
    - pint-laravel-preset                  # plan 01-05 — concat_space rule fired on PlayerFactory and was auto-corrected
  provides:
    - has-uuid-primary-key-trait           # apps/web/app/Concerns/HasUuidPrimaryKey.php — wraps HasUuids; emits UUID v4 (Str::uuid()) for parity with gen_random_uuid()
    - users-table                          # uuid PK gen_random_uuid(); discord_id text UNIQUE NOT NULL (D-002 snowflake-as-text); email citext NULL; locale text NOT NULL DEFAULT 'en'; remember_token; last_login_at + left_community_at + created_at + updated_at all timestamptz
    - players-table                        # uuid PK; user_id uuid UNIQUE FK users (RESTRICT ON DELETE / CASCADE ON UPDATE) — enforces 1:1 user↔player; slug text UNIQUE; bio jsonb (translatable-ready); avatar_source CHECK in (discord,upload); softDeletes
    - player-privacy-table                 # uuid PK; player_id uuid UNIQUE FK players (CASCADE ON DELETE / CASCADE ON UPDATE) — enforces 1:1 + cleanup; show_to text DEFAULT 'community' CHECK in (public,community,clan,private); 5 boolean section toggles per D-018
    - user-eloquent-model                  # apps/web/app/Models/User.php — extends Authenticatable; HasUuidPrimaryKey + Notifiable; fillable: discord_id/username/email/avatar_url/locale/last_login_at/left_community_at; casts datetime; hasOne(Player); password column dropped per D-017
    - player-eloquent-model                # apps/web/app/Models/Player.php — Model + HasUuidPrimaryKey + SoftDeletes; bio cast to array (HasTranslations wraps in Phase 2+); belongsTo(User), hasOne(PlayerPrivacy)
    - player-privacy-eloquent-model        # apps/web/app/Models/PlayerPrivacy.php — Model + HasUuidPrimaryKey; protected $table='player_privacy' (avoids "player_privacies" pluralization); 5 boolean casts; belongsTo(Player)
    - user-factory                         # 18-digit numeric snowflake-shaped discord_id; unique safeEmail; locale=en
    - player-factory                       # cascades through UserFactory; slugged-username + 4-char random suffix; defaults avatar_source=discord
    - player-privacy-factory               # cascades through PlayerFactory; D-018 defaults (show_real_name=false; others true; show_to=community)
    - user-model-test                      # 3 tests — UUID v4 string shape (36 chars), discord_id UNIQUE QueryException, hasOne(player) null default
    - player-model-test                    # 3 tests — factory graph round-trip, soft-delete vs withTrashed, bio array cast
    - player-privacy-model-test            # 3 tests — D-018 defaults, show_to CHECK constraint blocks invalid value, cascade-on-forceDelete
  affects:
    - "01-09 (Discord OAuth + ProvisionFirstLogin) — listener writes to users + players + player_privacy via these factories' $fillable shape; transaction wrap relies on FK semantics provisioned here"
    - "01-11 (spatie/laravel-permission + admin seed) — User model needs HasRoles trait + FilamentUser interface added; HasUuidPrimaryKey already in place"
    - "01-12 (Filament v3 panel) — User and Player resources read directly from these models; Player resource displays inline player_privacy via privacy() relation"
    - "Phase 2 clans — ClanMembership.player_id references players(id); membership unique partial index over (player_id, deleted_at IS NULL) builds atop the soft-delete pattern established here"
    - "Phase 2+ translatable — spatie/laravel-translatable's HasTranslations trait wraps Player::$casts['bio']='array' without a column-type change"
tech_stack:
  added: []
  patterns:
    - "Trait-extracted UUID PK (HasUuidPrimaryKey) — every domain model that needs UUID v4 PKs uses this trait; centralises the v4-vs-v7 decision so we can flip to UUIDv7 in one file when Postgres 17 ships"
    - "DB-default + ORM-default belt-and-braces — id has gen_random_uuid() default at the DB level so seeders using DB::table() also produce valid UUIDs; the trait's newUniqueId() wins for Eloquent::create() paths"
    - "CHECK constraints over Postgres ENUM types — both avatar_source and show_to use TEXT + CHECK; CHECK is cheaper to migrate than ALTER TYPE if the value set grows"
    - "raw-SQL ALTER TABLE for citext + timestamptz — Laravel Schema builder doesn't have native citext or 'change column type to timestamptz' verbs; we ship a Schema::create followed by DB::statement ALTERs in the same migration"
    - "1:1 enforced by UNIQUE FK column — no separate junction; user_id and player_id are both UNIQUE NOT NULL columns whose FKs encode the parent relationship and the cardinality together"
key_files:
  created:
    - apps/web/app/Concerns/HasUuidPrimaryKey.php
    - apps/web/database/migrations/2026_05_03_100000_create_users_table.php
    - apps/web/database/migrations/2026_05_03_100100_create_players_table.php
    - apps/web/database/migrations/2026_05_03_100200_create_player_privacy_table.php
    - apps/web/app/Models/Player.php
    - apps/web/app/Models/PlayerPrivacy.php
    - apps/web/database/factories/PlayerFactory.php
    - apps/web/database/factories/PlayerPrivacyFactory.php
    - apps/web/tests/Feature/Models/UserModelTest.php
    - apps/web/tests/Feature/Models/PlayerModelTest.php
    - apps/web/tests/Feature/Models/PlayerPrivacyModelTest.php
  modified:
    - apps/web/app/Models/User.php       # full rewrite — drops password, adds Discord-OAuth shape + relationships; was Laravel default before this plan
    - apps/web/database/factories/UserFactory.php  # full rewrite — drops password/email_verified_at/unverified-state; adds discord_id snowflake numerify + locale=en
decisions:
  - id: 01-10-DEC-1
    decision: "Override HasUuids::newUniqueId() to return Str::uuid()->toString() (v4) instead of inheriting Laravel 11+'s default ordered UUID v7"
    rationale: ".docs/05-database-schema.md explicitly pegs PKs to v4 from gen_random_uuid() until Postgres 17 ships native UUIDv7 generation. Centralising this in HasUuidPrimaryKey means a one-file change to flip the whole codebase later."
    alternatives_considered: "Use Laravel default (UUIDv7 ordered) and let the DB default (gen_random_uuid v4) win for DB::table seeders only — would mean Eloquent::create() produces v7 IDs while seeders produce v4 IDs in the same database (mixed). Rejected: schema doc says v4 everywhere for now."
  - id: 01-10-DEC-2
    decision: "1:1 cardinality enforced by UNIQUE NOT NULL FK columns (user_id on players, player_id on player_privacy) rather than separate junction tables"
    rationale: "Cleaner reads (no extra join), atomic INSERT semantics, and the UNIQUE index makes `User::with('player')` + `Player::with('privacy')` use a single B-tree lookup. spatie/laravel-permission and Filament both expect direct hasOne/belongsTo on the parent."
    alternatives_considered: "Composite PK (user_id alone as players.id) — rejected: forces Player to share its identity with User, breaking the soft-delete-on-Player-but-keep-User pattern that .docs/05-database-schema.md prescribes."
  - id: 01-10-DEC-3
    decision: "FK player_id → players uses CASCADE ON DELETE; FK user_id → players uses RESTRICT ON DELETE"
    rationale: "Privacy settings are meaningless without their player; cascading them on delete is the obvious intent. But deleting a User out from under a live Player would orphan match history and audit logs — RESTRICT forces an explicit two-step (soft-delete the Player first, then the User if needed). Soft-delete on Player only NULLs deleted_at and does NOT trigger the FK cascade — privacy survives soft-delete and only dies on forceDelete()."
    alternatives_considered: "CASCADE on user_id too — rejected: makes accidental User::forceDelete() catastrophic across the whole Phase 2 clan history. Better to surface a QueryException."
  - id: 01-10-DEC-4
    decision: "Use TEXT + CHECK for avatar_source and show_to enums (instead of native Postgres ENUM types)"
    rationale: "Adding a value to a Postgres ENUM (`ALTER TYPE … ADD VALUE`) requires AccessExclusiveLock and forks per-version migration plumbing. CHECK constraints are dropped and re-added in a single migration with the new value list — cheaper to evolve. Schema doc 'Conventions' block also recommends this."
    alternatives_considered: "Native ENUM — rejected: harder to migrate. Free-text TEXT (no constraint) — rejected: would let Filament admin UI write 'galactic' silently."
  - id: 01-10-DEC-5
    decision: "Cast Player.bio to 'array' in P1; do NOT add HasTranslations trait yet"
    rationale: "spatie/laravel-translatable lands in Phase 2+ when content models (clans, articles) need it. Wrapping a bare jsonb-array field today with HasTranslations adds a hidden requirement (every read must pass a locale) that the rest of P1 isn't ready for. Plain array cast lets P1 store an untyped json blob; Phase 2+ replaces the cast with HasTranslations and the column shape is identical."
    alternatives_considered: "Ship HasTranslations now — rejected: cross-cuts Phase 2 territory and forces every P1 player display to hardcode a locale fallback that plan 08's i18n system isn't authoritative for yet."
metrics:
  duration_minutes: 2.8
  duration_seconds: 169
  tasks_completed: 2
  files_changed: 11
  tests_added: 9
  tests_total_after_plan: 13
  phpstan_errors: 0
  pint_violations_after_autofix: 0
  completed_date: "2026-05-03"
---

# Phase 1 Plan 10: Identity Schema (Users, Players, Player Privacy) Summary

UUID v4 PKs, citext email, jsonb bio, FK + CHECK + soft-deletes — the canonical identity layer for everything Phase 2+ will hang off.

## What was built

### Task 1 — HasUuidPrimaryKey trait + 3 schema migrations (commit `cb51281`)

**`apps/web/app/Concerns/HasUuidPrimaryKey.php`** — extends Laravel's `HasUuids` and overrides `newUniqueId()` to return `Str::uuid()->toString()` (v4) instead of the framework default of `Str::orderedUuid()` (v7). One-file flip when Postgres 17 lands.

**`apps/web/database/migrations/2026_05_03_100000_create_users_table.php`** — `id uuid PK DEFAULT gen_random_uuid()`, `discord_id text UNIQUE NOT NULL` (D-002 snowflake-as-text), `username text NOT NULL`, `email citext NULL` (case-insensitive), `avatar_url text NULL`, `locale text NOT NULL DEFAULT 'en'`, `remember_token`, `last_login_at timestamptz NULL`, `left_community_at timestamptz NULL`, `created_at + updated_at` upgraded to `timestamptz`. citext column added via raw `ALTER TABLE` after `Schema::create` because the Schema builder has no citext verb.

**`apps/web/database/migrations/2026_05_03_100100_create_players_table.php`** — `id uuid PK`, `user_id uuid UNIQUE NOT NULL` FK users (RESTRICT ON DELETE, CASCADE ON UPDATE) enforces 1:1 + safety, `slug text UNIQUE NOT NULL`, `display_name text NULL`, `avatar_source text NOT NULL DEFAULT 'discord'` with `CHECK (avatar_source IN ('discord','upload'))`, `avatar_path text NULL`, `bio jsonb NULL`, `country_code text NULL`, `softDeletes('deleted_at')`, timestamps upgraded to timestamptz.

**`apps/web/database/migrations/2026_05_03_100200_create_player_privacy_table.php`** — `id uuid PK`, `player_id uuid UNIQUE NOT NULL` FK players (CASCADE ON DELETE, CASCADE ON UPDATE) enforces 1:1 + cleanup-on-forceDelete, `show_to text NOT NULL DEFAULT 'community'` with `CHECK (show_to IN ('public','community','clan','private'))`, 5 booleans with D-018 defaults (`show_real_name = false`, others `true`).

Verified via `psql \d users / \d players / \d player_privacy`: every column type, default, NOT NULL, UNIQUE, FK direction, and CHECK constraint matches the schema doc and the plan's must-have list.

### Task 2 — Eloquent models + factories + 9 Pest tests (commit `329d561`)

**`apps/web/app/Models/User.php`** — full rewrite of the Laravel default. Drops `password` (D-017 OAuth-only) and `email_verified_at` (Discord pre-verifies). Uses `HasUuidPrimaryKey + Notifiable + HasFactory<UserFactory>`. Fillable: `discord_id, username, email, avatar_url, locale, last_login_at, left_community_at`. Casts `last_login_at + left_community_at` to `datetime`. Hides `remember_token`. `player()` returns `HasOne<Player, $this>`. (FilamentUser contract + spatie/laravel-permission HasRoles land in plan 11.)

**`apps/web/app/Models/Player.php`** — `Model + HasUuidPrimaryKey + SoftDeletes + HasFactory<PlayerFactory>`. Fillable: `user_id, slug, display_name, avatar_source, avatar_path, bio, country_code`. Casts `bio` to `array` (HasTranslations trait wraps in Phase 2+). `user()` returns `BelongsTo<User, $this>`; `privacy()` returns `HasOne<PlayerPrivacy, $this>`.

**`apps/web/app/Models/PlayerPrivacy.php`** — `Model + HasUuidPrimaryKey + HasFactory<PlayerPrivacyFactory>`. `protected $table = 'player_privacy'` overrides Eloquent's default pluralisation to `player_privacies`. Fillable: `player_id, show_to + 5 booleans`. Boolean casts on all 5 toggles. `player()` returns `BelongsTo<Player, $this>`.

**Factories** — `UserFactory` generates 18-digit numeric snowflake-shaped `discord_id`, unique safeEmail, locale=en. `PlayerFactory` cascades through `User::factory()` for `user_id` and produces a slugified `username + '-' + random(4)`. `PlayerPrivacyFactory` cascades through `PlayerFactory` and ships D-018 defaults verbatim.

**`apps/web/tests/Feature/Models/{UserModelTest,PlayerModelTest,PlayerPrivacyModelTest}.php`** — 9 tests total, 3 per file:

- UserModelTest: UUID v4 string shape (36 chars); discord_id UNIQUE constraint throws QueryException; `$user->player` returns null pre-provisioning.
- PlayerModelTest: factory cascade round-trip (`$player->user_id === $player->user->id`); soft-delete (find returns null, withTrashed find returns the row); bio array cast round-trips `['en' => 'hello']` after refresh.
- PlayerPrivacyModelTest: factory ships D-018 defaults (show_to='community', show_real_name=false, show_discord_tag=true); show_to CHECK constraint blocks 'galactic' with QueryException; cascade-on-forceDelete deletes the privacy row.

## Verification results

### Plan-level must_haves (frontmatter `must_haves.truths`)

| # | Truth | Verified |
|---|-------|----------|
| 1 | users table has UUID PK + discord_id text UNIQUE NOT NULL + email citext NULL | ✓ via `\d users` (id uuid not null DEFAULT gen_random_uuid(), discord_id text unique constraint, email citext nullable) |
| 2 | players table has UUID PK + user_id uuid UNIQUE FK users + slug text UNIQUE + bio jsonb NULL | ✓ via `\d players` (user_id_unique + user_id_foreign, slug_unique, bio jsonb nullable) |
| 3 | player_privacy table has UUID PK + player_id uuid UNIQUE FK players + booleans + show_to CHECK | ✓ via `\d player_privacy` (player_id_unique + player_id_foreign cascade, player_privacy_show_to_check constraint) |
| 4 | User, Player, PlayerPrivacy Eloquent models exist with HasUuids trait + relationships | ✓ via grep: `HasUuidPrimaryKey` in all 3 models; `function player`, `function user`, `function privacy` present |
| 5 | Soft deletes on Player; Users + PlayerPrivacy do NOT soft-delete in P1 | ✓ Player.php uses SoftDeletes; User.php and PlayerPrivacy.php do not |
| 6 | Factories produce valid rows so plan 09's Pest tests can use User::factory()->create() | ✓ 9 tests build valid factory chains; full Pest suite (13 tests) green |
| 7 | `make migrate:fresh` applies extensions + 3 schema migrations cleanly against postgres | ✓ `php artisan migrate:fresh --force` ran clean; 4 migrations DONE |

### Plan-level must_haves (frontmatter `must_haves.artifacts`)

All 6 artifact files exist and contain the required `contains` patterns (`discord_id`, `user_id`, `show_to`, `HasUuids` (via HasUuidPrimaryKey), `SoftDeletes`, `show_to`).

### Plan-level must_haves (frontmatter `must_haves.key_links`)

| Link | Verified |
|------|----------|
| users migration → extensions migration via `gen_random_uuid` / `citext` | ✓ both keywords present in users migration |
| Player → User via `belongsTo` | ✓ `belongsTo(User::class)` in Player.php |
| Player → PlayerPrivacy via `hasOne` | ✓ `hasOne(PlayerPrivacy::class)` in Player.php |

### Task acceptance criteria

| Task | Criterion | Result |
|------|-----------|--------|
| 1 | HasUuidPrimaryKey concern exists with v4 UUID generation | ✓ trait emits Str::uuid() |
| 1 | 3 migrations exist with correct columns + FKs + CHECK constraints | ✓ verified via `\d` output |
| 1 | `make migrate` succeeds; tables present in postgres | ✓ 3 migrations DONE; trenchwars + trenchwars_test both clean |
| 1 | email column is citext; bio is jsonb; soft deletes on players | ✓ via `\d` output |
| 2 | User, Player, PlayerPrivacy models exist with correct relationships and casts | ✓ all 3 models authored; relations + casts verified by Pest |
| 2 | 3 factories produce valid rows | ✓ all 3 factories build cascading graphs |
| 2 | All 9 model tests pass | ✓ `pest tests/Feature/Models` 9 passed |
| 2 | PlayerPrivacy CHECK constraint blocks invalid show_to values | ✓ test `it enforces show_to CHECK constraint` green (QueryException on 'galactic') |

### Quality gates

- **Pest** — `tests/Feature/Models` 9 passed; full suite 13 passed (32 assertions); 0.43s.
- **PHPStan level 8** — `phpstan analyse` reports `No errors` on the expanded model + database tree.
- **Pint (Laravel preset)** — clean across 38 files after one auto-fix on `PlayerFactory.php` (concat_space).

### Requirements progress

`REQ-constraint-railway-deploy` — partial satisfaction: schema migrates cleanly against the same Postgres 16 image Railway uses (alpine-tagged), and gen_random_uuid()/citext are extension-driven so Railway's first deploy will run the same `0001_01_01_000000_enable_postgres_extensions` + this plan's 3 migrations identically.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Pint concat_space auto-fix on PlayerFactory.php**
- **Found during:** Task 2, after authoring PlayerFactory
- **Issue:** Pint Laravel preset's `concat_space` rule expects spaces around `.` operator: `'foo' . 'bar'` not `'foo'.'bar'`. The plan's pasted PlayerFactory snippet had `Str::slug(...) . '-' . Str::lower(...)` which Pint flagged as a violation under the project's preset.
- **Fix:** Ran `./vendor/bin/pint database/factories/PlayerFactory.php` to apply the auto-correction. Final form is identical to the plan's intent — Pint chose to keep the spaces around concat (the plan's own snippet had spaces but my initial draft compacted them).
- **Files modified:** `apps/web/database/factories/PlayerFactory.php`
- **Commit:** included in `329d561` (the pint-corrected form was committed alongside the rest of task 2)

### Process notes (not behavior deviations)

- **Added `rememberToken()` to users migration** — the plan's pasted users migration snippet did not include `rememberToken()` even though the plan's behavior comment said it should be there (and User model's `$hidden = ['remember_token']` references it). Treated this as the plan's prose-vs-snippet mismatch and followed the prose. Adding remember_token is on the .docs/05-database-schema.md trajectory anyway (Laravel's Authenticatable contract assumes it).
- **citext defined as the *last* column in users (not interleaved)** — the plan's snippet wrote the email comment but commented out the actual column, then added it via `DB::statement` AFTER `Schema::create`. Followed that pattern verbatim. Postgres column ordering doesn't affect query semantics; only `\d` output ordering changes.
- **Tests use `Database\\Factories\\*` template-tag PHPDoc** — added `/** @use HasFactory<XFactory> */` blocks on the model `use HasFactory;` lines to satisfy PHPStan level 8's generic class checks. The plan's pasted models did not include these PHPDoc tags but PHPStan would otherwise flag `HasFactory` as a non-generic class usage (the project's existing User model already had this pattern so I followed it).

## Authentication gates

None — this plan is pure schema + Eloquent. No external services touched.

## Threat surface scan

No new surface beyond what the plan's `<threat_model>` already covers:

- **T-1-03** (duplicate users via discord_id race) — mitigated by `UNIQUE` index at DB level; `it enforces unique discord_id` test asserts QueryException on duplicate.
- **T-1-23** (orphan players from User cascade) — mitigated by `restrictOnDelete()` on `players.user_id`; will be covered by Phase 2 clan-membership integration tests when those land.
- **T-1-24** (invalid show_to value) — mitigated by `CHECK (show_to IN ('public','community','clan','private'))`; `it enforces show_to CHECK constraint` test asserts QueryException on 'galactic'.
- **T-1-25** (email PII disclosure) — accepted; email is citext + nullable; not exposed in any P1 surface (no public route reads it; Filament resource lands in plan 12 with admin-access gate).

No new threats discovered.

## Commits

- `cb51281` `feat(01-10): add HasUuidPrimaryKey trait + users/players/player_privacy migrations` — 4 files (+187)
- `329d561` `feat(01-10): add User/Player/PlayerPrivacy models, factories, model tests` — 9 files (+311 −41)

(Final SUMMARY commit follows this file.)

## Next steps (handed to subsequent plans)

- **Plan 01-09 (Discord OAuth + ProvisionFirstLogin)** — ready to write the `Login` event listener that upserts users / creates players / creates player_privacy in a single `DB::transaction`. The factories built here exercise the same shape the listener will use, so the listener's tests can `User::factory()->create()` and assert the listener is idempotent on re-login.
- **Plan 01-11 (spatie/laravel-permission + admin seed)** — needs to add `HasRoles` trait + `FilamentUser` contract + `canAccessPanel()` to `User.php`. The `HasUuidPrimaryKey` trait already returns string keys, which spatie/laravel-permission's polymorphic role pivot handles via `model_morph_key=string-uuid` in `config/permission.php`.
- **Plan 01-12 (Filament v3 panel)** — User and Player resources can read directly from these models. Player resource's "Privacy" inline section reads from `$player->privacy` (the hasOne wired here).
- **Phase 2 (clans + memberships)** — `ClanMembership.player_id` references `players(id)`; the partial unique index over `(player_id) WHERE deleted_at IS NULL` for D-009 (one active membership) builds atop the soft-delete pattern Player established here.
- **Phase 2+ (translatable)** — when `spatie/laravel-translatable` is installed, replace `Player::$casts['bio'] = 'array'` with `use HasTranslations;` + `protected array $translatable = ['bio'];`. Column type is unchanged (already jsonb), so no migration is needed.

## Self-Check: PASSED

- All 13 created/modified files verified present on disk.
- Both task commits (`cb51281`, `329d561`) verified present in `git log --oneline --all`.
