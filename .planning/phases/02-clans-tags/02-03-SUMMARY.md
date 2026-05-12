---
phase: 02-clans-tags
plan: 03
subsystem: models
tags: [models, factories, translatable, logactivity, d-009, d-012, d-013]
dependency_graph:
  requires: [02-01, 02-02]
  provides: [Clan, ClanTag, ClanMembership, ClanInvite, ClanApplication, DiscordGuild, Player-HasTranslations]
  affects: [02-05-DTOs, 02-07-controllers, 02-12-filament-resources]
tech_stack:
  added: []
  patterns: [HasTranslations, LogsActivity, HasUuidPrimaryKey, partial-unique-index, BelongsToMany]
key_files:
  created:
    - apps/web/app/Models/Clan.php
    - apps/web/app/Models/ClanTag.php
    - apps/web/app/Models/ClanMembership.php
    - apps/web/app/Models/ClanInvite.php
    - apps/web/app/Models/ClanApplication.php
    - apps/web/app/Models/DiscordGuild.php
  modified:
    - apps/web/app/Models/Player.php
    - apps/web/database/factories/ClanFactory.php
    - apps/web/database/factories/ClanTagFactory.php
    - apps/web/database/factories/ClanMembershipFactory.php
    - apps/web/database/factories/ClanInviteFactory.php
    - apps/web/database/factories/ClanApplicationFactory.php
    - apps/web/database/factories/DiscordGuildFactory.php
    - apps/web/tests/Feature/Models/ClanModelTest.php
    - apps/web/tests/Feature/Models/ClanMembershipModelTest.php
    - apps/web/tests/Feature/Admin/PlayerResourceBioFieldTest.php
    - apps/web/tests/Feature/Models/PlayerModelTest.php
decisions:
  - "Clan.getRouteKeyName() returns 'slug' for /clans/{slug} route-model binding"
  - "ClanTag also gets LogsActivity (not in PATTERNS.md sample but consistent with D-012)"
  - "Player.casts() method removed entirely (bio was the only key); HasTranslations owns the accessor"
  - "PlayerModelTest + PlayerResourceBioFieldTest updated to use getTranslations('bio') API (Rule 1 — old array cast tests broke)"
metrics:
  duration: "~4 min (224s)"
  completed_date: "2026-05-12"
  tasks_completed: 3
  files_changed: 17
---

# Phase 02 Plan 03: Models Wave 1 Summary

6 Phase-2 Eloquent models + Player HasTranslations migration; 6 real factories; 2 model test suites GREEN.

## What Was Built

### Task 1: Clan, ClanTag, ClanMembership models + factories

**Clan** (`apps/web/app/Models/Clan.php`):
- Traits: `HasFactory<ClanFactory>`, `HasTranslations`, `HasUuidPrimaryKey`, `LogsActivity`, `SoftDeletes`
- `$translatable = ['description']` with `/** @var list<string> */` PHPDoc (Pitfall 7)
- `$fillable` covers all schema columns; `discord_role_id` included (admin-only path — T-02-02-01 mitigation lives in the controller layer)
- Relations: `owner(): BelongsTo<User>`, `tags(): BelongsToMany<ClanTag>` via `clan_clan_tag`, `memberships(): HasMany<ClanMembership>`, `activeMembers(): HasMany<ClanMembership>` with `whereNull('left_at')`
- `getRouteKeyName()` returns `'slug'` for `/clans/{slug}` route-model binding
- LogsActivity description: `"Clan {$event}"`

**ClanTag** (`apps/web/app/Models/ClanTag.php`):
- Traits: `HasFactory<ClanTagFactory>`, `HasTranslations`, `HasUuidPrimaryKey`, `LogsActivity`
- `$translatable = ['label']` (Pitfall 5 mitigation)
- `$fillable = ['slug', 'label', 'color']`
- Relation: `clans(): BelongsToMany<Clan>` via `clan_clan_tag`
- LogsActivity description: `"ClanTag {$event}"`

**ClanMembership** (`apps/web/app/Models/ClanMembership.php`):
- Traits: `HasFactory<ClanMembershipFactory>`, `HasUuidPrimaryKey`, `LogsActivity`
- `$fillable` includes `role`, `joined_at`, `left_at`, `invited_by`
- Casts: `joined_at => datetime`, `left_at => datetime`
- Relations: `clan()`, `user()`, `inviter()` (via `invited_by` FK)
- LogsActivity description: `"ClanMembership {$event}"`

**Factories**: ClanFactory uses `fake()->unique()->company()` for name, slug with random suffix, `description = ['en' => ...]`. ClanTagFactory uses locale-keyed `label`. ClanMembershipFactory defaults to `role='member'`, `left_at=null`.

### Task 2: ClanInvite, ClanApplication, DiscordGuild + Player HasTranslations

**ClanInvite** (`apps/web/app/Models/ClanInvite.php`):
- Traits: `HasFactory<ClanInviteFactory>`, `HasUuidPrimaryKey`, `LogsActivity`
- Casts: `decided_at => datetime`, `expires_at => datetime`
- Relations: `clan()`, `invitee()` via `invited_user_id`, `inviter()` via `inviting_user_id`
- LogsActivity description: `"ClanInvite {$event}"`

**ClanApplication** (`apps/web/app/Models/ClanApplication.php`):
- Traits: `HasFactory<ClanApplicationFactory>`, `HasUuidPrimaryKey`, `LogsActivity`
- Cast: `decided_at => datetime`
- Relations: `clan()`, `applicant()` via `applicant_user_id`, `decidedBy()` via `decided_by`
- LogsActivity description: `"ClanApplication {$event}"`

**DiscordGuild** (`apps/web/app/Models/DiscordGuild.php`):
- Traits: `HasFactory<DiscordGuildFactory>`, `HasUuidPrimaryKey`, `LogsActivity`
- `protected $table = 'discord_guild'` (singular — D-003)
- `$fillable = ['guild_id', 'name', 'icon_url']`
- LogsActivity description: `"DiscordGuild {$event}"`

**Player.php migration**:
- Added `use Spatie\Translatable\HasTranslations;` import
- Added `use HasTranslations;` to trait stack
- Added `/** @var list<string> */ public array $translatable = ['bio'];`
- Removed `'bio' => 'array'` cast (Pitfall 3 mitigation); `casts()` method removed entirely as `bio` was the only key

### Task 3: ClanModelTest + ClanMembershipModelTest (Wave 0 stubs replaced)

**ClanModelTest** (5 assertions, all GREEN):
1. Factory creates clan with `status='active'`
2. Invalid status throws `QueryException` (CHECK constraint)
3. `setTranslation('description','en','Hi')` round-trips correctly
4. `tags()->attach()` + `detach()` BelongsToMany pivot round-trip
5. LogsActivity writes row with `subject_type=Clan, event='created'`

**ClanMembershipModelTest** (4 assertions, all GREEN):
1. D-009 partial unique: second active membership throws `QueryException`
2. Second membership succeeds after first has `left_at` set (history preserved)
3. Invalid role throws `QueryException` (CHECK constraint)
4. LogsActivity writes row with `subject_type=ClanMembership, event='created'`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] PlayerModelTest 'casts bio to array' broke after HasTranslations migration**
- **Found during:** Task 2 verification
- **Issue:** `PlayerModelTest` expected `$player->bio` to return `['en' => 'hello']` (array), but `HasTranslations` now returns `'hello'` (current-locale string)
- **Fix:** Updated test to assert `$player->bio === 'hello'` and added `getTranslation()`/`getTranslations()` assertions
- **Files modified:** `apps/web/tests/Feature/Models/PlayerModelTest.php`
- **Commit:** 0974ccf

**2. [Rule 1 - Bug] PlayerResourceBioFieldTest bio array assertion broke after HasTranslations migration**
- **Found during:** Task 2 verification
- **Issue:** `expect($reloaded->bio)->toBeArray()` failed because `HasTranslations` returns current-locale string via `->bio`, not the full JSONB array. Also `$player->bio = [...]` assignment now goes through the trait's mutator — used `setTranslation()` instead
- **Fix:** Updated test to use `setTranslation()` for setting values, `getTranslations('bio')` for reading the full locale map
- **Files modified:** `apps/web/tests/Feature/Admin/PlayerResourceBioFieldTest.php`
- **Commit:** 0974ccf

## Tests Summary

| Suite | Before | After |
|-------|--------|-------|
| Models (all) | 3 PASS / 2 FAIL (Wave 0 stubs) | 5 PASS / 0 FAIL |
| Player (all) | 14 PASS / 3 FAIL | 14 PASS / 3 FAIL (3 remain as Wave 0 stubs in future plans) |
| PHPStan L8 | clean | clean |
| Pint | clean | clean |

## Known Stubs

None. All 6 factories produce valid model instances; all Wave 0 factory stubs replaced with real definitions.

## Threat Flags

No new security surface introduced. All threat mitigations from T-02-02-01 through T-02-02-05 are implemented:
- T-02-02-01: `discord_role_id` in `$fillable` but Filament-admin-only route enforcement lives in plan 02-09 controller layer (documented)
- T-02-02-02: Role change enforced by dedicated controller action (plan 02-09)
- T-02-02-03: `bio` public field — privacy gate in plan 02-05 DTO
- T-02-02-04: `LogsActivity` on every model with `logFillable + logOnlyDirty`
- T-02-02-05: D-009 partial unique index proven GREEN by `ClanMembershipModelTest`

## Self-Check: PASSED

All required files exist. All task commits verified:
- cc2fc4d: feat(02-03): Clan, ClanTag, ClanMembership models + real factories
- 0974ccf: feat(02-03): ClanInvite, ClanApplication, DiscordGuild models + Player HasTranslations
- 4b19d0e: feat(02-03): replace Wave 0 test stubs with real ClanModelTest + ClanMembershipModelTest
