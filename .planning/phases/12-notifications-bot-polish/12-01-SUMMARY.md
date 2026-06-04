---
phase: 12-notifications-bot-polish
plan: "01"
subsystem: notifications
tags: [notifications, nav, ux, test, tdd]
dependency_graph:
  requires: [09-06]
  provides: [NOTF-01-SC1-discoverability, NOTF-01-SC1-honor-proof]
  affects: [UserMenu.vue, common.php, NotificationPreferencesHonorTest]
tech_stack:
  added: []
  patterns: [TDD RED/GREEN, Pest Feature test, enabledNotificationChannels honor]
key_files:
  created:
    - apps/web/tests/Feature/NotificationPreferencesHonorTest.php
  modified:
    - apps/web/resources/js/components/UserMenu.vue
    - apps/web/lang/en/common.php
decisions:
  - "Link already absent from UserMenu — added DropdownMenuItem anchor mirroring My Clan pattern exactly"
  - "TDD RED commit (49c308e) precedes GREEN — tests exercise real HTTP route + fresh() model + via() assertion"
metrics:
  duration: "~6 min"
  completed: "2026-06-04"
---

# Phase 12 Plan 01: Notification Preferences Nav Link + Honor Test Summary

**One-liner:** Discoverable nav link for notification-preferences in user dropdown (i18n) plus end-to-end Pest test proving HTTP POST honors `enabledNotificationChannels()` and `MatchStartingSoon::via()`.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Add notification-preferences link to user dropdown | 47b187f | UserMenu.vue, lang/en/common.php |
| 2 | End-to-end notification-preference honor test (TDD) | 49c308e | NotificationPreferencesHonorTest.php |

## Deviations from Plan

None — plan executed exactly as written.

- Task 1: link was absent; added DropdownMenuItem mirroring My Clan pattern with `href="/account/notification-preferences"` and `t('common.nav.notification_preferences')`.
- Task 2: TDD RED commit (test file) then GREEN (tests passed immediately — implementation was pre-built in Phase 9 plan 09-06 as expected).

## Gates

- `grep account/notification-preferences UserMenu.vue` — PASS
- `grep notification_preferences lang/en/common.php` — PASS
- `vue-tsc --noEmit` — PASS (no output)
- `pest --filter=NotificationPreferencesHonorTest` — PASS (2 tests, 7 assertions, 1.93s)

## Known Stubs

None.

## Threat Flags

None — no new network endpoints, auth paths, or schema changes introduced. Existing T-12-01-E (updateOrCreate keyed on auth()->id()) preserved unmodified.

## Self-Check: PASSED

- `apps/web/resources/js/components/UserMenu.vue` — FOUND, contains `account/notification-preferences`
- `apps/web/lang/en/common.php` — FOUND, contains `notification_preferences`
- `apps/web/tests/Feature/NotificationPreferencesHonorTest.php` — FOUND
- Commits 47b187f, 49c308e — verified in git log
