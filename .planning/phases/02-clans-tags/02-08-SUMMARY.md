---
phase: 02-clans-tags
plan: "08"
subsystem: frontend
tags: [vue, inertia, public-pages, components, layout]
dependency_graph:
  requires: [02-06, 02-07]
  provides: [clans-index-page, clans-show-page, players-show-page, clan-card, member-row, player-card, user-menu, public-layout-nav]
  affects: [02-09]
tech_stack:
  added: []
  patterns:
    - absent-ne-null privacy rendering (v-if field !== undefined)
    - per-clan accent CSS override wrapper hook
    - Inertia router.get for filter navigation with replace+preserveScroll
    - Reka UI DropdownMenuRoot for UserMenu
key_files:
  created:
    - apps/web/resources/js/components/clans/ClanCard.vue
    - apps/web/resources/js/components/clans/MemberRow.vue
    - apps/web/resources/js/components/players/PlayerCard.vue
    - apps/web/resources/js/components/UserMenu.vue
  modified:
    - apps/web/resources/js/pages/Clans/Index.vue
    - apps/web/resources/js/pages/Clans/Show.vue
    - apps/web/resources/js/pages/Players/Show.vue
    - apps/web/resources/js/layouts/PublicLayout.vue
decisions:
  - "Players nav link renders as <a href='/players'> (404 in P2) per UI-SPEC explicit nav structure — TODO Phase 9"
  - "UserMenu My Profile renders as href='#' (disabled forward-compat) — player_slug not in auth shared prop until Phase 9 settings"
  - "ClanCard uses initials fallback (no img tag) — avatar_url not yet in ClanData DTO"
  - "v-if uses .length truthy check (not > 0) to avoid test regex false-positive on > character in template attributes"
metrics:
  duration: "273s (~4.5 min)"
  completed: "2026-05-12"
  tasks: 3
  files_changed: 8
requirements_satisfied:
  - REQ-tenancy-multi-clan
  - REQ-goal-public-profiles
---

# Phase 02 Plan 08: Inertia Vue Pages + Components Summary

3 Inertia pages replacing Wave 3 stubs, 4 new composite components, and PublicLayout nav slot population.

## What Was Built

### Task 1 + 2: 3 Inertia Pages + 4 Composite Components

**Clans/Index.vue** — Public clan directory page
- `defineProps<{ clans: ClanData[]; tags: ClanTagData[]; pagination: Pagination; activeTagSlug?: string; activeSearch?: string }>()`
- Filter bar: TextInput search + ClanTagBadge filter pills (horizontal scroll, `router.get` on change with `replace: true`)
- Two empty states: `clans.directory.empty_results` (filtered) + `clans.directory.empty_default` (none)
- Clan grid: responsive `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`

**Clans/Show.vue** — Public clan detail page
- `defineProps<{ clan: ClanData; members: ClanMembershipData[]; hiddenMemberCount: number }>()`
- Clan hero: 64×64 avatar initials, H1 Display name, ClanTagBadge v-for, member count, StatusBadge (non-active only)
- Description: plain `{{ clan.description.en }}` — NO v-html (T-02-08-01 mitigated)
- Members section: MemberRow v-for + privacy notices (partial/all-hidden)
- Per-clan accent override wrapper present (forward-compat hook, always `{}` in P2 since DTO lacks accent_color)
- Recent activity placeholder

**Players/Show.vue** — Public player profile with privacy-aware rendering
- `defineProps<{ player: PublicPlayerData }>()`
- All sections use `v-if="field !== undefined && field !== null"` — absent = withheld (T-02-08-02 mitigated)
- Own-profile privacy notice via `v-if="player.isOwnProfile"`
- Bio, clan history, match history, stats — each only rendered if field present in DTO
- PlayerCard component for hero block

**ClanCard.vue** — Directory grid item
- Props: `clan: ClanData`
- Entire card is `<a :href="'/clans/' + clan.slug">` link
- 48×48 initials avatar, 20px/600 name, ClanTagBadge v-for, 80-char description excerpt (computed), member count plural
- Hover: `hover:bg-[var(--color-surface-elevated)] hover:border-[var(--color-accent)]` + transition-colors

**MemberRow.vue** — Reusable member row
- Props: `member: ClanMembershipData`, `showActions?: boolean` (false on public pages, true for plan 02-09)
- 32×32 rounded-full avatar initials, player name link (if slug present), ClanRoleBadge
- `emit('change-role')` + `emit('remove')` available for plan 02-09 My Clan management
- Named slot `#actions` exposed for plan 02-09

**PlayerCard.vue** — Player hero composite
- Props: `player: PublicPlayerData`
- 64×64 rounded-full (circular — contrast with clan rounded-lg), Display name, @discordTag (if `!== undefined`), country code, current clan link
- Used by Players/Show.vue as the hero block

**UserMenu.vue** — Auth header dropdown
- Props: `user: AuthUser`
- Reka UI `DropdownMenuRoot/DropdownMenuTrigger/DropdownMenuContent/DropdownMenuItem`
- Trigger: avatar img (or initials) + username
- Items: My Clan (`/my-clan`), My Profile (`href="#"` — forward-compat placeholder), Log out (`router.post('/auth/logout')`)
- All labels via `t()` — `common.nav.my_clan`, `common.nav.my_profile`, `common.actions.logout`

### Task 3: PublicLayout Nav + Auth Slots

**PublicLayout.vue** updated:
- Removed empty `<slot name="nav" />` — replaced with inline `<nav class="hidden md:flex">` containing:
  - `<Link href="/clans">` with active-link 3px accent left-border detection via `page.url.startsWith('/clans')`
  - `<a href="/players">` (forward-compat — TODO Phase 9 comment, 404 in P2 per plan decision)
- Auth slot populated inline: `<UserMenu v-if="user" :user="user" />` else `<LoginButton />`
- Imports: `UserMenu`, `LoginButton`, `AuthUser` from `@/types/inertia`, `usePage`

## Verification Results

| Check | Result |
|-------|--------|
| `npx tsc --noEmit` | CLEAN — 0 errors |
| `NoHardcodedStringsTest` | GREEN — 1 passed |
| `ClanDirectoryTest` | GREEN — 5 passed |
| `ClanShowTest` | GREEN — 3 passed |
| `PlayerProfilePrivacyTest` | GREEN — 14 passed |
| `PublicClanRoutesTest` | GREEN — 6 passed |
| Wave 0 stub tests (ClanApplication/Invite/MyClanManagement) | Pre-existing `expect(true)->toBeFalse()` stubs — Wave 4 scope, not this plan |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] NoHardcodedStringsTest false positives from `>` in v-if expressions**
- **Found during:** Task 1 verification
- **Issue:** The test regex `/>([^<]{3,})</` captures text after `>` characters in attribute values like `v-if="arr.length > 0"`. The `> 0"` is captured as a "text node" containing letters from the following attribute on the next line.
- **Fix:** Changed `v-if="x.length > 0"` to `v-if="x.length"` (truthy check) across Index.vue, Show.vue, and ClanCard.vue. Identical semantics.
- **Files modified:** `Clans/Index.vue`, `Clans/Show.vue`, `components/clans/ClanCard.vue`
- **Commit:** 4349789

**2. [Rule 1 - Bug] Duplicate `defineProps` call in MemberRow.vue**
- **Found during:** Task 2 authoring
- **Issue:** Accidentally had both a plain `defineProps` and a `withDefaults(defineProps(...))` call — TypeScript/Vue SFC compiler disallows two `defineProps` calls in `<script setup>`.
- **Fix:** Consolidated into single `withDefaults(defineProps<{...}>(), {...})` call.
- **Files modified:** `components/clans/MemberRow.vue`
- **Commit:** 4349789 (fixed before commit)

### Deferred Items

- **My Profile link in UserMenu** — Renders as `href="#"` with forward-compat comment. The player slug is not available in the shared `auth` prop (which only has `id`, `discord_id`, `username`, `avatar_url`). Wiring requires either adding `player_slug` to `HandleInertiaRequests::share()` or a `/me` redirect controller. Deferred to Phase 9 (settings/polish).
- **Pint pre-existing issue** — `tests/Unit/Services/PlayerPrivacyGateTest.php fully_qualified_strict_type` style violation was pre-existing before this plan. Out of scope per deviation scope boundary rule.

## Known Stubs

- **UserMenu My Profile link** — `href="#"` renders but navigates nowhere. Intentional forward-compat placeholder. Tracked in deferred-items above.
- **PlayerCard current clan link** — Uses `clan_id` as the href path segment (`/clans/{clan_id}`) since `ClanMembershipData` has `clan_id` but not a clan slug. Plan 02-09 may improve this — for now it's functional but may 404 if slugs differ from IDs. Tracked as forward-compat concern.
- **Clan history entries in Players/Show.vue** — Rendered as `JSON.stringify(entry)` since `clanHistory` is typed as `Record<string, any>[] | null` with no defined shape. Deferred to Phase 3+ when clan history data shape is finalized.

## Threat Surface Scan

No new network endpoints, auth paths, or file access patterns were introduced. All files are frontend-only Vue components and pages. The per-threat mitigations declared in the plan's `<threat_model>` are implemented:

| Threat | Status |
|--------|--------|
| T-02-08-01: XSS via clan description | Mitigated — `{{ clan.description.en }}` (no v-html) |
| T-02-08-02: Privacy logic in Vue | Mitigated — all privacy gates use `v-if="field !== undefined"` |
| T-02-08-03: Inertia router.get with attacker-controlled query | Mitigated — backend re-validates; Vue just passes values |
| T-02-08-04: Hardcoded EN strings | Mitigated — NoHardcodedStringsTest GREEN |

## Self-Check: PASSED

Files created/verified:
- apps/web/resources/js/components/clans/ClanCard.vue — EXISTS
- apps/web/resources/js/components/clans/MemberRow.vue — EXISTS
- apps/web/resources/js/components/players/PlayerCard.vue — EXISTS
- apps/web/resources/js/components/UserMenu.vue — EXISTS
- apps/web/resources/js/pages/Clans/Index.vue — modified (>50 lines)
- apps/web/resources/js/pages/Clans/Show.vue — modified (>50 lines)
- apps/web/resources/js/pages/Players/Show.vue — modified (>50 lines)
- apps/web/resources/js/layouts/PublicLayout.vue — modified

Commit 4349789 exists: FOUND
