---
phase: 02-clans-tags
plan: 13
subsystem: filament-admin
tags: [filament, admin, clan-membership, clan-invite, clan-application, discord-guild, d-003, d-012]
dependency_graph:
  requires: [02-03, 02-06, 02-12]
  provides: [filament-clan-membership-resource, filament-clan-invite-resource, filament-clan-application-resource, filament-discord-guild-resource]
  affects: [filament-admin-panel, phase-02-test-suite]
tech_stack:
  added: []
  patterns: [filament-read-only-resource, filament-no-create-restriction, filament-view-record, audit-tab-integration]
key_files:
  created:
    - apps/web/app/Filament/Resources/ClanMembershipResource.php
    - apps/web/app/Filament/Resources/ClanMembershipResource/Pages/ListClanMemberships.php
    - apps/web/app/Filament/Resources/ClanMembershipResource/Pages/ViewClanMembership.php
    - apps/web/app/Filament/Resources/ClanInviteResource.php
    - apps/web/app/Filament/Resources/ClanInviteResource/Pages/ListClanInvites.php
    - apps/web/app/Filament/Resources/ClanInviteResource/Pages/ViewClanInvite.php
    - apps/web/app/Filament/Resources/ClanApplicationResource.php
    - apps/web/app/Filament/Resources/ClanApplicationResource/Pages/ListClanApplications.php
    - apps/web/app/Filament/Resources/ClanApplicationResource/Pages/ViewClanApplication.php
    - apps/web/app/Filament/Resources/DiscordGuildResource.php
    - apps/web/app/Filament/Resources/DiscordGuildResource/Pages/ListDiscordGuilds.php
    - apps/web/app/Filament/Resources/DiscordGuildResource/Pages/EditDiscordGuild.php
  modified:
    - apps/web/lang/en/admin.php
    - apps/web/tests/Feature/Admin/ClanFilamentResourceTest.php
decisions:
  - DiscordGuildResource.getPages() omits 'create' route — Filament-layer D-003 enforcement; visiting /admin/discord-guilds/create returns 404
  - Read-only resources (Membership/Invite/Application) use disabled+dehydrated(false) form fields for ViewRecord — Filament v3 requires form() even on view-only resources
  - Status badge colors follow plan spec: warning=pending, success=accepted, danger=declined/revoked/cancelled, gray=expired/default
  - admin.discord_guild lang keys added inline (Rule 2 amendment — plan 02-06 omitted this key group)
metrics:
  duration: 270s
  completed_date: "2026-05-12"
  tasks_completed: 3
  files_created: 12
  files_modified: 2
  deviations: 1
---

# Phase 02 Plan 13: Remaining Filament Resources Summary

4 Filament resources (read-only + single-row-gated) completing D-012 for Phase 2 — ClanMembership/ClanInvite/ClanApplication as read-only audit listings, DiscordGuildResource with no-Create restriction enforcing D-003.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | 3 read-only resources + 6 pages | 0c3f6e3 | ClanMembershipResource, ClanInviteResource, ClanApplicationResource + 6 page files |
| 2 | DiscordGuildResource + no-Create restriction | 4842256 | DiscordGuildResource, 2 page files, lang/en/admin.php |
| 3 | ClanFilamentResourceTest comprehensive tests | 5ec9f4b | tests/Feature/Admin/ClanFilamentResourceTest.php |

## Resource Navigation Sort Order

| Resource | navigationSort | navigationIcon |
|----------|---------------|----------------|
| ClanResource (plan 02-12) | 3 | heroicon-o-user-group |
| ClanTagResource (plan 02-12) | 4 | heroicon-o-tag |
| ClanMembershipResource | 5 | heroicon-o-identification |
| ClanInviteResource | 6 | heroicon-o-envelope |
| ClanApplicationResource | 7 | heroicon-o-inbox-arrow-down |
| DiscordGuildResource | 8 | heroicon-o-server-stack |

## DiscordGuildResource No-Create Wiring

`getPages()` returns only `index` and `edit` — the `create` key is absent:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListDiscordGuilds::route('/'),
        // INTENTIONALLY no 'create' route — discord_guild holds exactly one row (D-003).
        'edit'  => Pages\EditDiscordGuild::route('/{record}/edit'),
    ];
}
```

This makes `/admin/discord-guilds/create` return a 404, verified by test `it does NOT register Create for DiscordGuildResource (D-003 single-row)`.

## Test Coverage Summary

10 tests in `ClanFilamentResourceTest.php` — all GREEN:

1. registers ClanMembershipResource at /admin/clan-memberships
2. registers ClanInviteResource at /admin/clan-invites
3. registers ClanApplicationResource at /admin/clan-applications
4. registers DiscordGuildResource at /admin/discord-guilds
5. does NOT register Create for ClanMembershipResource (lifecycle in My Clan)
6. does NOT register Create for ClanInviteResource
7. does NOT register Create for ClanApplicationResource
8. does NOT register Create for DiscordGuildResource (D-003 single-row)
9. does NOT register Delete on activity_log rows (audit page read-only)
10. admin can view a Clan record and the audit_log tab renders after a mutation

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] Added admin.discord_guild lang keys**

- **Found during:** Task 2
- **Issue:** `lang/en/admin.php` was missing the `discord_guild` key group (`label`, `plural_label`, `fields.guild_id`, `fields.name`, `fields.icon_url`). Plan 02-06 added clan domain keys but skipped `discord_guild`. Without these keys, `__('admin.discord_guild.label')` returns the raw key string in all nav/heading labels.
- **Fix:** Added `discord_guild => [...]` block to `lang/en/admin.php` in Task 2.
- **Files modified:** `apps/web/lang/en/admin.php`
- **Commit:** `4842256`

**2. [Rule 1 - Bug] Removed fontFamily('mono') from TextInput form field**

- **Found during:** Task 2 PHPStan run
- **Issue:** `fontFamily()` is a `TextColumn` method (table) not a `TextInput` method (form). PHPStan level 8 caught the undefined method call.
- **Fix:** Removed `->fontFamily('mono')` from the form's `guild_id` TextInput; kept it on the table TextColumn where it belongs.
- **Files modified:** `apps/web/app/Filament/Resources/DiscordGuildResource.php`
- **Commit:** `4842256`

**3. [Rule 3 - Pre-existing] Pint style fix on PlayerPrivacyGateTest.php**

- **Found during:** Final `pint --test` CI gate check
- **Issue:** Pre-existing `fully_qualified_strict_type` Pint style violation in `tests/Unit/Services/PlayerPrivacyGateTest.php` (created in an earlier plan).
- **Fix:** Applied Pint auto-fix. No logic change.
- **Files modified:** `apps/web/tests/Unit/Services/PlayerPrivacyGateTest.php`
- **Commit:** `847eb85`

## Known Stubs

None — all resources render real model data from the database.

## Self-Check

Files exist:
- `apps/web/app/Filament/Resources/ClanMembershipResource.php` — FOUND
- `apps/web/app/Filament/Resources/ClanInviteResource.php` — FOUND
- `apps/web/app/Filament/Resources/ClanApplicationResource.php` — FOUND
- `apps/web/app/Filament/Resources/DiscordGuildResource.php` — FOUND
- `apps/web/tests/Feature/Admin/ClanFilamentResourceTest.php` — FOUND

Commits verified:
- `0c3f6e3` — feat(02-13): 3 read-only Filament resources
- `4842256` — feat(02-13): DiscordGuildResource with no-Create restriction
- `5ec9f4b` — feat(02-13): replace ClanFilamentResourceTest Wave 0 stub
- `847eb85` — style(02-13): pint auto-fix pre-existing issue

## Self-Check: PASSED
