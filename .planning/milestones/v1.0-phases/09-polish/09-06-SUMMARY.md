---
phase: 09-polish
plan: 06
subsystem: notifications-and-leaderboards-ui
tags: [wave-4, notifications, leaderboards, inertia, public-ui, d-018, d-04-03-a-locked, sc-1, sc-2]
requires:
  - "09-03 Wave 2 — App\\Models\\Notification + Notifiable User + UserNotificationPreference"
  - "09-04 Wave 3 — App\\Notifications\\* + NotificationDispatcher (toArray/toDatabase payload contract)"
  - "09-05 Wave 3 — LeaderboardService::topPlayers/topClans (Cache::flexible SWR) + LeaderboardEntryData + LeaderboardClanEntryData"
  - "Phase 1 — HandleInertiaRequests::share + useT() composable + PublicLayout slot conventions"
  - "Phase 2 — PlayerPrivacyGate::allowsSection + ::passesTier; PlayerPrivacy.show_to + show_stats"
provides:
  - "App\\Http\\Controllers\\NotificationsController — index/markAsRead/markAllAsRead (auth-scoped via $request->user()->notifications())"
  - "App\\Http\\Controllers\\Account\\NotificationPreferencesController — edit/update 5×2 (event_type × channel) preference matrix"
  - "App\\Http\\Controllers\\LeaderboardsController — index (public; window+game query params; DTO hydration with tier+section gate)"
  - "HandleInertiaRequests::share extended with unread_notifications_count closure (Inertia shared prop)"
  - "AppServiceProvider boots RateLimiter::for('notifications-read') + RateLimiter::for('public-api') named limiters"
  - "resources/js/components/NotificationsBell.vue — Reka Popover bell with accent badge, view-all link, mark-all-read CTA"
  - "resources/js/components/LeaderboardTable.vue — stateless table renderer (players/clans modes; D-018 anonymisation at render)"
  - "resources/js/pages/Notifications/Index.vue — full inbox (paginated, per-row mark-read CTA)"
  - "resources/js/pages/Account/NotificationPreferences.vue — Reka SwitchRoot 5×2 grid + bulk POST"
  - "resources/js/pages/Leaderboards/Index.vue — Reka Tabs (Players/Clans) + window toggle + game filter <select>"
  - "5 routes: GET /notifications, POST /notifications/{id}/read, POST /notifications/read-all, GET+POST /account/notification-preferences, GET /leaderboards"
  - "i18n: notifications + leaderboards + a11y added to shared_namespaces; notifications.page.* + .preferences.* + common.actions.{previous,next,save}"
  - "Tightened App\\Data\\LeaderboardEntryData::fromQueryResult to AND ::passesTier with ::allowsSection (D-018 trust-boundary alignment)"
affects:
  - "plan 09-07 search-mode + private indices: bell badge is now the standard auth-only signal in the header — search dropdown can mount alongside it via the same locale-switcher slot pattern"
  - "plan 09-09 medialibrary WebP: LeaderboardClanEntryData.logo_url still null pending plan 09-09; the renderer reserves the column position without a placeholder image"
  - "plan 09-11 advanced rate-limit definitions: AppServiceProvider already registers notifications-read + public-api named limiters; plan 09-11 may refine the per-user vs per-IP fallback logic. Re-registration is idempotent (last RateLimiter::for wins)."
  - "plan 09-12 i18n key coverage: 14 new keys land in lang/en/notifications.php (preferences.* + page.* + bell.view_all) — covered by Phase9I18nKeyCoverageTest once it turns GREEN"
tech-stack:
  added: []
  patterns:
    - "Inertia shared closure prop for badge counts — `unread_notifications_count` is a `fn (): int => $request->user()?->unreadNotifications()->count() ?? 0` so guests resolve to 0 without an unnecessary DB query and every Inertia navigation re-evaluates without any client-side polling (SC-1 no-WebSocket requirement)"
    - "Eager-loaded DTO hydration in O(2) extra queries — controller pre-loads Players (with privacy) + ClanMembership (with clan.name) keyed by id/user_id before iterating raw aggregate rows; the DTO factory never lazy-fetches (Pattern 6 strict-mode protection)"
    - "Privacy gate composition — `allowsSection('show_stats') AND passesTier()` covers both the section flag AND the show_to tier on the leaderboard surface, aligning the public leaderboard with the /players/{slug} trust boundary (D-018)"
    - "Reka UI without a Drawer primitive — reka-ui ships Dialog + Popover + DropdownMenu; the bell uses Popover (lightweight summary + view-all link) rather than a heavy Drawer/Dialog. NotificationsBell renders entirely from props (no fetch on open) because the badge count is already shared via Inertia."
    - "throttle:public-api named limiter — registered at AppServiceProvider boot so plan 09-06 routes can attach the named middleware before plan 09-11 lands the formal definition. Plan 09-11 will overwrite the registration (idempotent)."
    - "T-09-06-02 auth-scoped notification mutation — every mark-read endpoint queries through the Notifiable trait's notifications() relation, never directly on Notification::find($id); cross-user access yields null → abort(404), no Gate/Policy indirection."
key-files:
  created:
    - "apps/web/app/Http/Controllers/NotificationsController.php — 70 lines, 3 actions"
    - "apps/web/app/Http/Controllers/Account/NotificationPreferencesController.php — 138 lines, edit+update; pre-loads existing rows in O(1) query"
    - "apps/web/app/Http/Controllers/LeaderboardsController.php — 161 lines; eager-loads Players+Clans+Memberships in 4 queries"
    - "apps/web/resources/js/components/NotificationsBell.vue — Reka Popover + Bell icon + accent badge"
    - "apps/web/resources/js/components/LeaderboardTable.vue — mode discriminator; D-018 anonymisation branch"
    - "apps/web/resources/js/pages/Notifications/Index.vue — paginated inbox"
    - "apps/web/resources/js/pages/Account/NotificationPreferences.vue — Reka SwitchRoot 5×2 grid"
    - "apps/web/resources/js/pages/Leaderboards/Index.vue — Tabs + window toggle + game filter"
  modified:
    - "apps/web/app/Http/Middleware/HandleInertiaRequests.php — appended unread_notifications_count closure to share()"
    - "apps/web/app/Providers/AppServiceProvider.php — registered notifications-read (120/min user) + public-api (30/min IP) named limiters"
    - "apps/web/app/Data/LeaderboardEntryData.php — `fromQueryResult` AND-combines allowsSection+passesTier (Rule 1 — D-018 alignment)"
    - "apps/web/routes/web.php — 5 new routes (notifications hub + preferences + leaderboards)"
    - "apps/web/resources/js/layouts/PublicLayout.vue — auth-conditional <NotificationsBell> mount"
    - "apps/web/resources/js/types/inertia.d.ts — PageProps.unread_notifications_count: number"
    - "apps/web/lang/en/notifications.php — added bell.view_all + page.* + preferences.* keys"
    - "apps/web/lang/en/common.php — added actions.previous + .next + .save"
    - "apps/web/config/i18n.php — added a11y + leaderboards + notifications to shared_namespaces"
    - "apps/web/tests/Feature/Notifications/NotificationsBellTest.php — Wave 0 stub → 14 GREEN tests"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardPrivacyTest.php — Wave 0 stub → 10 GREEN tests"
decisions:
  - "D-09-06-A — Inertia shared prop carries the unread count, not a polling endpoint. The closure form (`fn (): int => ...`) ensures guests pay nothing (no DB call) and every navigation re-evaluates the count freshly without client-side polling or WebSocket setup. SC-1 'web bell re-renders on every Inertia navigation via shared props' is hereby honoured at the framework boundary."
  - "D-09-06-B — Named rate limiters registered at AppServiceProvider boot, not at route definition. Plan 09-11 will refine them; this plan registers them so the throttle:notifications-read and throttle:public-api middleware references in routes/web.php resolve immediately (otherwise Laravel throws MissingRateLimiterException). RateLimiter::for is idempotent — the latest registration wins."
  - "D-09-06-C — LeaderboardEntryData privacy guard tightened to AND both ::allowsSection('show_stats') AND ::passesTier(). Plan 09-05 originally checked only the section flag; that is correct for the per-section idiom (e.g., show_clan_history) but insufficient for the leaderboard surface where the tier semantics also apply. A clan-tier player with show_stats=true must still render anonymously to a cross-clan viewer — otherwise the leaderboard becomes a privacy backdoor. Rule 1 alignment with D-018 trust-boundary definition. Tests 3 + 4 (LeaderboardPrivacyTest) lock in the contract."
  - "D-09-06-D — Bell drawer is a Reka Popover (not Dialog) because reka-ui does not ship a Drawer primitive. The popover renders a summary + view-all link without fetching additional data — the full list lives at /notifications. Avoids a per-toggle Inertia round-trip and keeps the bell instant."
  - "D-09-06-E — Account/NotificationPreferences POST sends the FULL snapshot (every event_type × channel tuple), not a diff. Server runs updateOrCreate per row in DB::transaction. This is robust against partial-form drift (a user toggling one switch ends up with all rows aligned to current UI state) and survives concurrent edits without merge logic. Composite UNIQUE `unp_unique` index makes updateOrCreate race-free."
metrics:
  duration_seconds: 1251
  duration_human: "~20m 51s"
  completed_at: "2026-05-14T08:34:34Z"
  files_created: 8
  files_modified: 11
  total_files: 19
  controllers_added: 3
  vue_pages_added: 3
  vue_components_added: 2
  routes_added: 5
  inertia_shared_props_added: 1
  rate_limiters_registered: 2
  tests_added_this_plan: 24
  tests_now_passing: 1211
  tests_now_skipped: 19
  suite_total: 1230
  baseline_passing: 1187
  baseline_skipped: 21
  wave_0_stubs_turned_green: 2
  pint_files_passed: 19
  phpstan_errors: 0
  lines_added_approx: 1750
---

# Phase 9 Plan 06: Wave 4 — NotificationsController + LeaderboardsController + Bell + Public UI Summary

Shipped the public-facing UI surface for SC-1 (notification bell + preferences hub) and SC-2 (public leaderboards) with D-018 PlayerPrivacyGate enforcement at the page boundary. The bell badge re-renders on every Inertia navigation via the new `unread_notifications_count` shared prop — no WebSocket, no client-side polling. The leaderboard renders Cache::flexible-served SWR aggregates with privacy gating applied row-by-row at DTO hydration time, and the renderer respects `is_anonymous` to strip both the player link and the clan name.

Two Wave 0 Pest stubs turned GREEN (24 new tests / 228 assertions); full suite holds at 1211 passed / 19 skipped (4132 assertions / 82s).

## Route Table (5 routes added)

| Method | URI                                       | Name                                            | Middleware                                      |
|--------|-------------------------------------------|-------------------------------------------------|-------------------------------------------------|
| GET    | `/leaderboards`                           | `leaderboards.index`                            | `web, throttle:public-api`                      |
| GET    | `/notifications`                          | `notifications.index`                           | `web, auth`                                     |
| POST   | `/notifications/{id}/read`                | `notifications.markRead`                        | `web, auth, throttle:notifications-read`        |
| POST   | `/notifications/read-all`                 | `notifications.markAllRead`                     | `web, auth, throttle:notifications-read`        |
| GET    | `/account/notification-preferences`       | `account.notification-preferences.edit`         | `web, auth`                                     |
| POST   | `/account/notification-preferences`       | `account.notification-preferences.update`       | `web, auth`                                     |

`throttle:public-api` and `throttle:notifications-read` resolve via the named limiters registered in `AppServiceProvider::boot()` (D-09-06-B). Plan 09-11 will refine these definitions; until then, the registrations are owned by this plan.

## Inertia Shared-Prop Diff

```diff
 // HandleInertiaRequests::share()
 'auth'         => fn () => $request->user()?->only([...]),
 'locale'       => fn () => app()->getLocale(),
 'translations' => fn () => $this->translations(app()->getLocale()),
 'flash'        => [...],
 'ziggy'        => fn () => [...],
+'unread_notifications_count' => fn (): int =>
+    $request->user()?->unreadNotifications()->count() ?? 0,
```

- **Lazy eval**: closure form means guests pay no DB cost (`$request->user()` is null → short-circuit to 0).
- **Always present**: prop is `0` even for guests so the Vue layer can do `Number(props.unread_notifications_count)` without an undefined branch.
- **Type**: extended `apps/web/resources/js/types/inertia.d.ts` `PageProps` interface so the prop is type-checked across pages.
- **Auth-only mount**: PublicLayout renders `<NotificationsBell :count="unreadCount" />` only when `page.props.auth` is non-null.

## D-018 Enforcement Points

| Point                                         | Mechanism                                                                                                                                       | Test                                                  |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------|
| **Service** (`LeaderboardService`)            | Returns raw aggregate rows — no privacy logic at this layer (privacy is per-viewer, the cache is per-window).                                  | n/a                                                   |
| **DTO factory** (`LeaderboardEntryData::fromQueryResult`) | `allowsSection('show_stats')` AND `passesTier()` — both must pass to expose identity. Otherwise: `is_anonymous=true`, `player_id=''`, `player_name = __('leaderboards.anonymous_player')`, `clan_name = null`. | LeaderboardPrivacyTest tests 2 + 3 + 4               |
| **Controller** (`LeaderboardsController`)     | Passes `auth()->user()` (the viewer) into every DTO factory call.                                                                              | LeaderboardPrivacyTest test 3 (same vs cross-clan)    |
| **Renderer** (`LeaderboardTable.vue`)         | When `row.is_anonymous === true`: render the i18n label in italic-muted text and DO NOT wrap in `<Link>` (no clickable identity). Render `clan_name` as `—`. | LeaderboardPrivacyTest test 4 (guest sees masked row) |
| **Route**                                     | Public (no auth middleware). The privacy gate handles guest viewers correctly because `passesTier()` requires a non-null viewer for non-public tiers. | LeaderboardPrivacyTest test 5                         |

## Bell UX Flow

```
┌───────────────────────────────────────────────────────────┐
│ PublicLayout header                                      │
│                                                          │
│  [Wordmark]  Clans  Matches  Tournaments  Players  Search│
│                                                          │
│                              ╔══════╗                     │
│             [Theme] [🔔 (3)]│UserMenu│                    │
│                              ╚══════╝                     │
└────────────────────────┬─────────────────────────────────┘
                         │ click
                         ▼
                ┌────────────────────────────────┐
                │  Notifications (3 unread)      │
                │                                │
                │            Mark all as read    │
                │                                │
                │  ▸ View all notifications      │
                └────────────────────────────────┘
                                        │
                                        │ click "View all"
                                        ▼
                       ┌──────────────────────────────────┐
                       │ /notifications (full inbox)      │
                       │                                  │
                       │ ┌──────────────────────────────┐ │
                       │ │ • Match starting in 60 min...│ │
                       │ │ [Mark as read]               │ │
                       │ └──────────────────────────────┘ │
                       │ ┌──────────────────────────────┐ │
                       │ │ • Match starting in 15 min...│ │
                       │ │ [Mark as read]               │ │
                       │ └──────────────────────────────┘ │
                       │ ...                              │
                       │ Page 1 / 3   [Previous] [Next]   │
                       └──────────────────────────────────┘
```

**State propagation**:
1. `NotificationDispatcher` (plan 09-04) writes a DatabaseNotification row.
2. Next Inertia navigation re-evaluates `unread_notifications_count` closure → fresh count.
3. Badge re-renders with the new count.
4. User clicks the bell → Popover opens (no fetch — the badge already has the count).
5. User clicks "Mark all as read" → POST /notifications/read-all → Inertia partial reload → badge becomes 0.

**No polling**: The closure runs on every Inertia render (SPA navigation) — the only way to miss a new notification is to not navigate. That's acceptable for v1 per SC-1 ("no real-time"). A future ticker can opt in via `usePoll` without changing the shared-prop contract.

## Quality Gates

| Gate                                                                                | Result                                                                |
|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------|
| `pest --filter="NotificationsBellTest"`                                             | **14 passed** / 70 assertions / 2.30s                                 |
| `pest --filter="LeaderboardPrivacyTest"`                                            | **10 passed** / 158 assertions / 2.55s                                |
| `pest --filter="LeaderboardServiceTopPlayers\|TopClans\|Cache\|CacheTagFlush"`      | **24 passed** / 47 assertions (Phase 9-05 regression — no breakage)   |
| `pest --filter="InertiaSmokeTest\|TranslationsSharedTest\|PlayerProfilePrivacy"`    | **36 passed** (regression for shared-prop + privacy idioms)           |
| `pest --filter="NoHardcodedStringsTest"`                                            | **1 passed** (after fixing `>= 2` inline comparison in Notifications/Index.vue) |
| `pest --no-coverage` (full suite)                                                   | **1211 passed + 19 skipped** (4132 assertions) in 82.1s               |
| Baseline delta (passed)                                                             | +24 (1187 → 1211) — exactly the 24 new GREEN tests this plan added    |
| Baseline delta (skipped)                                                            | −2 (21 → 19) — exactly the 2 Wave 0 stubs turned GREEN                |
| Pint `--test` on 19 touched files                                                   | **PASS** (after one auto-fix pass: fully_qualified_strict_types + braces_position) |
| PHPStan analyse level 8 on touched app/ files                                       | **OK, no errors**                                                     |
| vue-tsc `--noEmit -p apps/web/tsconfig.json` (full project)                         | **EXIT 0** (no type errors)                                           |

## Wave 0 Stubs → GREEN

```text
NotificationsBellTest                       Wave 0 (1 skipped) → 14 passed
LeaderboardPrivacyTest                      Wave 0 (1 skipped) → 10 passed
                                                                 ─────────
                                                                24 new GREEN tests
```

Skip-list count check:
- Pre-plan (09-05): 21 skipped.
- Post-plan (09-06): 19 skipped (21 − 2 = 19 ✓).

## Deviations from Plan

### Rule 1 — Bug: tier check missing on the leaderboard surface

**1. [Rule 1 — Bug] `LeaderboardEntryData::fromQueryResult` only ran `allowsSection('show_stats')`; ignored `passesTier()`**

- **Found during:** Task 2 — running LeaderboardPrivacyTest test 3 ("clan tier: cross-clan viewer should see anonymous row") and test 4 ("guest sees public+show_stats=true rows, clan-tier rows anonymised").
- **Issue:** Plan 09-05's DTO factory delegated only to `PlayerPrivacyGate::allowsSection($player, $viewer, 'show_stats')`. That checks the per-section boolean, not the show_to tier. A clan-tier player with `show_stats=true` would have rendered with full identity on the public leaderboard to a guest visitor — a D-018 privacy bypass. The leaderboard surface IS the same trust boundary as `/players/{slug}` (both expose identity on a public web page), so both must honour the tier gate.
- **Fix:** Tightened the factory to AND both checks: `allowsSection('show_stats') AND passesTier()`. Docblock updated to call out the D-018 rationale and the plan 09-05 origin. Locked as **D-09-06-C**.
- **Files modified:** `apps/web/app/Data/LeaderboardEntryData.php` (factory).
- **Commit:** `05903d4`.

### Rule 2 — Additive correctness: rate-limiter definitions

**2. [Rule 2 — Missing functionality] Named limiters `notifications-read` + `public-api` not yet registered**

- **Found during:** Task 1 — wiring `->middleware('throttle:notifications-read')` to the mark-read endpoints. Plan 09-11 owns the formal definitions but is not yet executed; without registration, Laravel throws `MissingRateLimiterException` at request time.
- **Fix:** Registered both limiters in `AppServiceProvider::boot()`:
  - `notifications-read` → 120/min per user (auth user keyed by `user:<id>`; falls back to 30/min by IP for unauthenticated edge cases though the routes also have the `auth` middleware).
  - `public-api` → 30/min keyed by `ip:<addr>`.
  - Both match the threat-register figures referenced in `<threat_model>` T-09-06-04 + T-09-06-05.
- **Idempotence:** `RateLimiter::for($name, $callback)` overwrites a prior registration of the same name (last wins). Plan 09-11 can re-register without conflict. Locked as **D-09-06-B**.
- **Files modified:** `apps/web/app/Providers/AppServiceProvider.php`.
- **Commit:** `4a04bdb`.

### Rule 2 — Additive correctness: i18n shared namespaces

**3. [Rule 2 — Missing functionality] `notifications`, `leaderboards`, `a11y` not in `config/i18n.shared_namespaces`**

- **Found during:** Task 1 — wiring `t('notifications.bell.unread_count', { count })` in NotificationsBell.vue. The lang file `lang/en/notifications.php` already existed (plan 09-01 seeded it), but the namespace was not in `shared_namespaces`, so the keys would not flow into `page.props.translations` and `useT()` would log a missing-key warning in dev / return the raw key in prod.
- **Fix:** Added `a11y`, `leaderboards`, `notifications` to `config/i18n.shared_namespaces`. Other phases (e.g., `clans` is similarly absent) are pre-existing and out of scope; plan 09-12 Phase9I18nKeyCoverageTest will surface those.
- **Files modified:** `apps/web/config/i18n.php`.
- **Commit:** `4a04bdb`.

### Rule 3 — Blocking: PHPStan match.alwaysTrue

**4. [Rule 3 — Blocker] PHPStan match.alwaysTrue on the channel default-policy match in NotificationPreferencesController**

- **Found during:** Task 1 PHPStan verification — `Match arm comparison between 'discord' and 'discord' is always true`.
- **Fix:** Removed the `default => false` arm. The validator constrains `channel` to `self::CHANNELS = ['database', 'discord']`, so the match is exhaustive and PHPStan correctly flags the dead arm.
- **Files modified:** `apps/web/app/Http/Controllers/Account/NotificationPreferencesController.php`.
- **Commit:** `4a04bdb`.

### Rule 3 — Blocking: NoHardcodedStringsTest regex false positive

**5. [Rule 3 — Blocker] Inline `v-if="x >= 2"` confused the NoHardcodedStringsTest regex**

- **Found during:** Full-suite regression after Task 2 — NoHardcodedStringsTest failed pointing at `Notifications/Index.vue` line `v-if="notifications.last_page >= 2"`. The regex `/>([^<]{3,})</` interprets the `>` in the attribute value as a tag terminator and captures the following characters as a "text node".
- **Fix:** Extracted `notifications.last_page >= 2` to a `hasMultiplePages` computed ref. Mirrors the existing Articles/Index.vue idiom (it already documents this exact workaround in a comment). Inline comment added so future plans don't reintroduce the pattern.
- **Files modified:** `apps/web/resources/js/pages/Notifications/Index.vue`.
- **Commit:** `83e298d`.

### Rule 4 — None

No architectural decisions required. Every adjustment was a Rule 1 alignment with D-018, a Rule 2 additive correctness extension (rate limiters + i18n namespaces), or a Rule 3 mechanical fix.

## Authentication Gates

None. Plan ran fully autonomously inside the existing Docker stack (web + postgres + redis healthy throughout). No external API, no human action required.

## Known Stubs

None. Every code path is fully wired:

- NotificationsController writes through the production Notifiable trait — `markAsRead()` and `unreadNotifications->markAsRead()` are Laravel's own methods.
- NotificationPreferencesController writes real `UserNotificationPreference` rows in DB::transaction.
- LeaderboardsController hydrates real DTO rows from the SWR-cached service.
- NotificationsBell.vue renders the live shared prop (no mock data).
- LeaderboardClanEntryData.logo_url is intentionally always null pending plan 09-09 (medialibrary WebP) — documented in DTO docblock (D-09-05-B from prior plan), not a stub.

## Threat Flags

None. The plan's `<threat_model>` (T-09-06-01..06) covers every introduced surface:

| Threat                                         | Component                                         | Mitigation status                                                                                                                                                       |
|-------------------------------------------------|--------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| T-09-06-01 (Information Disclosure — privacy)  | `LeaderboardEntryData::fromQueryResult`          | **PASS** — `allowsSection('show_stats') AND passesTier()` (Rule 1 fix). LeaderboardTable.vue refuses to wrap anonymous rows in `<Link>`. LeaderboardPrivacyTest 2-4 GREEN. |
| T-09-06-02 (Elevation — mark another user's notif) | `NotificationsController::markAsRead`        | **PASS** — query routes through `$user->notifications()->find($id)`; cross-user returns null → abort(404). NotificationsBellTest 6 GREEN.                              |
| T-09-06-03 (Tampering — update another user's prefs) | `NotificationPreferencesController::update` | **PASS** — `updateOrCreate` keyed by `auth()->id() + event_type + channel`. NotificationsBellTest 13 GREEN.                                                            |
| T-09-06-04 (DoS — mass mark-read)              | `throttle:notifications-read` middleware         | **PASS** — 120/min per user via the named limiter registered by AppServiceProvider; routes attach the middleware (verified via Route::getRoutes() in tests).            |
| T-09-06-05 (DoS — anonymous /leaderboards scraping) | `throttle:public-api` middleware             | **PASS** — 30/min by IP; LeaderboardPrivacyTest 8 asserts via Route::getRoutes()->getByName('leaderboards.index')->gatherMiddleware().                                  |
| T-09-06-06 (Information Disclosure — timing leak from count) | `unread_notifications_count` closure | **ACCEPT** (per plan) — count is per-user via auth; no cross-user enumeration.                                                                                          |

No new surface beyond the threat register. No threat flags added.

## Self-Check: PASSED

**Files checked (8 created, 11 modified — 19 total):**

```
FOUND: apps/web/app/Http/Controllers/NotificationsController.php
FOUND: apps/web/app/Http/Controllers/Account/NotificationPreferencesController.php
FOUND: apps/web/app/Http/Controllers/LeaderboardsController.php
FOUND: apps/web/app/Http/Middleware/HandleInertiaRequests.php  (modified)
FOUND: apps/web/app/Providers/AppServiceProvider.php           (modified)
FOUND: apps/web/app/Data/LeaderboardEntryData.php              (modified)
FOUND: apps/web/routes/web.php                                 (modified)
FOUND: apps/web/resources/js/components/NotificationsBell.vue
FOUND: apps/web/resources/js/components/LeaderboardTable.vue
FOUND: apps/web/resources/js/pages/Notifications/Index.vue
FOUND: apps/web/resources/js/pages/Account/NotificationPreferences.vue
FOUND: apps/web/resources/js/pages/Leaderboards/Index.vue
FOUND: apps/web/resources/js/layouts/PublicLayout.vue          (modified)
FOUND: apps/web/resources/js/types/inertia.d.ts                (modified)
FOUND: apps/web/lang/en/common.php                             (modified)
FOUND: apps/web/lang/en/notifications.php                      (modified)
FOUND: apps/web/config/i18n.php                                (modified)
FOUND: apps/web/tests/Feature/Notifications/NotificationsBellTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Leaderboards/LeaderboardPrivacyTest.php (Wave 0 → GREEN)
```

**Commits verified:**

```
FOUND: 4a04bdb feat(09-06): NotificationsController + preferences + bell + shared prop (Task 1)
FOUND: 05903d4 feat(09-06): LeaderboardsController + Vue page + table + tier-aware privacy (Task 2)
FOUND: 83e298d fix(09-06): extract hasMultiplePages computed to satisfy NoHardcodedStringsTest
```

**Stub elimination verified:**

```
$ docker compose exec -T web ./vendor/bin/pest --filter="NotificationsBellTest|LeaderboardPrivacyTest" --no-coverage
  Tests: 24 passed (228 assertions) — both Wave 0 stubs turned GREEN
```

**Suite delta:**

```
Pre-plan baseline (09-05):    1187 passed + 21 skipped
Post-plan (09-06):            1211 passed + 19 skipped
                              ────────────  ──────────
                              +24 passed    −2 skipped
```

All 8 created + 11 modified files present on disk; all three commits resolve in `git log`.
