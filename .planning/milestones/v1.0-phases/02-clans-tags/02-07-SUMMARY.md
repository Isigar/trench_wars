---
phase: 02-clans-tags
plan: "07"
subsystem: public-controllers
tags: [controllers, routes, privacy, inertia, feature-tests]
dependency_graph:
  requires: [02-05, 02-06]
  provides: [public-clan-directory, public-clan-detail, public-player-profile]
  affects: [02-08, 02-09]
tech_stack:
  added: []
  patterns:
    - Single-action invokable controllers (LogoutController analog)
    - PlayerPrivacyGate tier check before DTO construction (T-02-04-02)
    - ClanData/ClanTagData/ClanMembershipData fromModel() static factories for JSONB+denorm fields
    - Route-model binding via slug on both Clan and Player
key_files:
  created:
    - apps/web/app/Http/Controllers/ClanDirectoryController.php
    - apps/web/app/Http/Controllers/ClanShowController.php
    - apps/web/app/Http/Controllers/PlayerProfileController.php
    - apps/web/resources/js/pages/Clans/Index.vue
    - apps/web/resources/js/pages/Clans/Show.vue
    - apps/web/resources/js/pages/Players/Show.vue
  modified:
    - apps/web/app/Models/Player.php
    - apps/web/routes/web.php
    - apps/web/app/Data/ClanData.php
    - apps/web/app/Data/ClanTagData.php
    - apps/web/app/Data/ClanMembershipData.php
    - apps/web/app/Data/PublicPlayerData.php
    - apps/web/tests/Feature/Clans/PublicClanRoutesTest.php
    - apps/web/tests/Feature/Clans/ClanDirectoryTest.php
    - apps/web/tests/Feature/Clans/ClanShowTest.php
    - apps/web/tests/Feature/Clans/PlayerProfilePrivacyTest.php
decisions:
  - "fromModel() static factories added to ClanData, ClanTagData, ClanMembershipData — spatie/laravel-data auto-mapping cannot resolve JSONB translatable fields (getTranslations() vs active-locale string) or denormalised User/Player fields"
  - "Player::memberships relation does not exist (ClanMembership joins via user_id) — PlayerProfileController loads user only; PublicPlayerData::fromPlayer queries ClanMembership directly"
  - "Vue stub pages created for Clans/Index, Clans/Show, Players/Show to satisfy Inertia testing ensure_pages_exist=true constraint; will be replaced by plan 02-08"
  - "ClanTagData::fromModel() uses getTranslations('label') to return full JSONB locale array, not active-locale scalar"
metrics:
  duration: "~12 min"
  completed: "2026-05-12"
  tasks: 2
  files: 16
---

# Phase 2 Plan 07: Public Controllers + Routes Summary

3 public controllers wired to Inertia with full D-018 privacy enforcement and 29 GREEN feature tests covering all privacy tiers, per-section flags, roster filtering, and clan directory behaviour.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | 3 public controllers + Player.getRouteKeyName() + route registrations | d0dd078 | ClanDirectoryController, ClanShowController, PlayerProfileController, Player.php, web.php, ClanData, ClanTagData, ClanMembershipData, PublicPlayerData |
| 2 | Replace 4 Wave 0 stubs (29 tests GREEN) | a612543 | PublicClanRoutesTest, ClanDirectoryTest, ClanShowTest, PlayerProfilePrivacyTest, stub Vue pages |

## Controllers + Inertia Pages

| Controller | Route | Inertia Component |
|------------|-------|-------------------|
| ClanDirectoryController | GET /clans (clans.index) | Clans/Index |
| ClanShowController | GET /clans/{clan:slug} (clans.show) | Clans/Show |
| PlayerProfileController | GET /players/{player:slug} (players.show) | Players/Show |

## Privacy Assertion Mapping

| Tier | Guest | Auth (other clan) | Auth (same clan) | Own profile |
|------|-------|-------------------|------------------|-------------|
| private | 404 | 404 | 404 | 200 |
| community | 404 | 200 | 200 | 200 |
| clan | 404 | 404 | 200 | 200 |
| public | 200 | 200 | 200 | 200 |

Per-section flags (`show_discord_tag`, `show_clan_history`) cause fields to be ABSENT (not null) from the serialised player prop — enforced via `Optional::create()` in `PublicPlayerData::fromPlayer()`. Own-profile viewers bypass all section flags.

## ClanShow Roster Privacy

`ClanShowController` filters `activeMembers` by `member->user->player->privacy->show_clan_history`. Members with `show_clan_history=false` are absent from the `members` array. `hiddenMemberCount` prop drives the UI notice (T-02-04-05).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] fromModel() factories required for JSONB + denormalised DTOs**
- **Found during:** Task 1
- **Issue:** `ClanData::from($clan)` (spatie/laravel-data auto-mapping) cannot resolve `active_member_count` (not a column) or the full JSONB locale array for `description`/`label` (HasTranslations returns active-locale scalar via magic property). `ClanMembershipData::from($membership)` cannot resolve `username`, `avatar_url`, `player_slug` (denormalised from related User/Player).
- **Fix:** Added `fromModel(Clan $clan)` to `ClanData`, `fromModel(ClanTag $tag)` to `ClanTagData`, `fromModel(ClanMembership $membership)` to `ClanMembershipData`. Updated `PublicPlayerData::fromPlayer()` to use `ClanMembershipData::fromModel()`.
- **Files modified:** `app/Data/ClanData.php`, `app/Data/ClanTagData.php`, `app/Data/ClanMembershipData.php`, `app/Data/PublicPlayerData.php`
- **Commit:** d0dd078

**2. [Rule 3 - Blocking] Player model has no memberships relation**
- **Found during:** Task 1 (controller) / Task 2 (tests)
- **Issue:** `PlayerProfileController` referenced `player->load(['memberships.clan'])` but `ClanMembership` is keyed by `user_id` — Player has no `memberships` HasMany. `PublicPlayerData::fromPlayer()` queries `ClanMembership` directly using `$player->user_id`.
- **Fix:** Simplified `PlayerProfileController` to load `['privacy', 'user']` only.
- **Files modified:** `apps/web/app/Http/Controllers/PlayerProfileController.php`
- **Commit:** a612543

**3. [Rule 3 - Blocking] Inertia testing ensure_pages_exist=true blocks tests without Vue pages**
- **Found during:** Task 2
- **Issue:** `config/inertia.php` testing block has `ensure_pages_exist => true`. Vue pages `Clans/Index`, `Clans/Show`, `Players/Show` don't exist (plan 02-08 territory). All `assertInertia` assertions failed with `Inertia page component file does not exist`.
- **Fix:** Created minimal stub Vue pages at the expected paths. Plan 02-08 will replace them with real implementations.
- **Files created:** `resources/js/pages/Clans/Index.vue`, `resources/js/pages/Clans/Show.vue`, `resources/js/pages/Players/Show.vue`
- **Commit:** a612543

## Known Stubs

| Stub | File | Reason |
|------|------|--------|
| Clans/Index.vue stub | resources/js/pages/Clans/Index.vue | Created to satisfy Inertia test constraint; plan 02-08 delivers the real implementation |
| Clans/Show.vue stub | resources/js/pages/Clans/Show.vue | Same |
| Players/Show.vue stub | resources/js/pages/Players/Show.vue | Same |

## Threat Surface Scan

No new trust-boundary surfaces beyond what the plan's threat model covers. All 3 routes are documented in the threat register (T-02-04-01 through T-02-04-07). SQL injection mitigated via Eloquent binding (T-02-04-03, T-02-04-04). Privacy gate fires before any DTO construction (T-02-04-02). Pagination at 20/page (T-02-04-06).

## Self-Check: PASSED

- ClanDirectoryController.php: exists
- ClanShowController.php: exists
- PlayerProfileController.php: exists
- Player.php has getRouteKeyName(): confirmed
- routes/web.php has clans.index, clans.show, players.show: confirmed via route:list
- 29 tests GREEN: confirmed
- PHPStan clean: confirmed
- Pint clean: confirmed
