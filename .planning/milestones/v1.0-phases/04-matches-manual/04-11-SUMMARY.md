---
phase: 04-matches-manual
plan: 11
subsystem: public-match-vue-pages
tags: [phase-4, wave-6, public-vue, inertia-pages, privacy-rendering, sc-3, pattern-7]
dependency_graph:
  requires:
    - phase-4-public-match-controllers
    - phase-4-public-match-dtos
    - phase-2-public-clan-pages
    - phase-1-base-ui-primitives
  provides:
    - public-match-calendar-page-vue
    - public-match-show-page-vue
    - match-card-component
    - role-slot-group-component
    - slot-occupant-pill-privacy-renderer
    - signup-button-inertia-post
    - match-status-badge
    - event-date-badge-reusable-primitive
    - public-layout-matches-nav-link
    - sc-3-end-to-end-rendered
  affects:
    - apps/web/resources/js/pages/Matches/Index.vue (NEW)
    - apps/web/resources/js/pages/Matches/Show.vue (NEW)
    - apps/web/resources/js/components/matches/MatchCard.vue (NEW)
    - apps/web/resources/js/components/matches/RoleSlotGroup.vue (NEW)
    - apps/web/resources/js/components/matches/SlotOccupantPill.vue (NEW)
    - apps/web/resources/js/components/matches/SignupButton.vue (NEW)
    - apps/web/resources/js/components/matches/MatchStatusBadge.vue (NEW)
    - apps/web/resources/js/components/events/EventDateBadge.vue (NEW — reusable for P6/P7)
    - apps/web/resources/js/layouts/PublicLayout.vue (modified — added /matches nav link)
    - apps/web/resources/js/components/ui/TextInput.vue (modified — Rule 2 type=date)
    - apps/web/lang/en/matches.php (modified — directory + status.label + show keys)
    - apps/web/lang/en/common.php (modified — common.nav.matches)
tech_stack:
  added: []
  patterns:
    - inertia-page-with-typed-props
    - privacy-aware-3-branch-render
    - inertia-router-post-with-preserveScroll
    - inertia-router-delete-cancel-signup
    - native-intl-datetime-no-runtime-dep
    - color-mix-opacity-variant-pattern
    - calendar-date-pill-reusable-primitive
    - active-nav-link-via-page-url-startsWith
key_files:
  created:
    - apps/web/resources/js/pages/Matches/Index.vue
    - apps/web/resources/js/pages/Matches/Show.vue
    - apps/web/resources/js/components/matches/MatchCard.vue
    - apps/web/resources/js/components/matches/RoleSlotGroup.vue
    - apps/web/resources/js/components/matches/SlotOccupantPill.vue
    - apps/web/resources/js/components/matches/SignupButton.vue
    - apps/web/resources/js/components/matches/MatchStatusBadge.vue
    - apps/web/resources/js/components/events/EventDateBadge.vue
  modified:
    - apps/web/resources/js/layouts/PublicLayout.vue
    - apps/web/resources/js/components/ui/TextInput.vue
    - apps/web/lang/en/matches.php
    - apps/web/lang/en/common.php
  deleted: []
decisions:
  - id: D-04-11-A
    decision: |
      **Privacy branches use `!== null` (matching the actual spatie/laravel-data
      generated TS contract), NOT `!== undefined` as the plan acceptance criteria
      suggested.**

      The plan `<must_haves>` text says: `Templates use v-if="slot.displayName !== undefined"`.
      The actual TS output from spatie/laravel-typescript-transformer for
      `PublicMatchOccupantData` is:

      ```ts
      displayName: string | null;
      playerSlug: string | null;
      clanTag: string | null;
      clanSlug: string | null;
      ```

      i.e., these are nullable, NOT optional. Spatie's transformer emits
      `string | null` for `?string` (nullable native PHP type) and would
      emit `undefined | string | null` only when the property was wrapped
      in `Optional<>`. Since `PublicMatchOccupantData::fromMatchSlot` and
      `forEmptySlot` both produce a null in the withheld/empty case, the
      Vue branch test is `!== null`. Phase 2's PublicPlayerData uses the
      `Optional<>` pattern (visible in api.d.ts line 185: `bio: undefined | Record<string, string> | null`),
      hence Phase 2's `!== undefined` idiom — but PublicMatchOccupantData
      consistently uses nullable.

      Net effect on T-04-11-01 mitigation: unchanged. The server-side
      privacy gate runs in plan 04-10's controller and produces a null
      displayName when the gate denies; the Vue layer never re-derives
      privacy. The branch sees null and falls through to the "Anonymous
      + clan tag" rendering — the D-008 carve-out works exactly as
      specified.

  - id: D-04-11-B
    decision: |
      **MatchStatusBadge does NOT wrap StatusBadge primitive — it implements
      the 5 match-status variants inline.**

      The plan said "Wraps the Phase 1 StatusBadge primitive with 5 variants:
      muted, success, warning, info, error". Phase 1's StatusBadge variants
      are domain-named (`active`, `pending`, `public`, `private`, `leader`,
      `officer`, etc. — see resources/js/components/ui/StatusBadge.vue) and
      use a fixed Map. Adding 5 new variants (`draft`, `open`, `locked`,
      `played`, `cancelled`) to StatusBadge would expand the type union
      from 12 → 17 entries, dilute its domain-status semantics, and
      mix match-domain concerns into a Phase 1 primitive.

      Cleaner alternative used here: MatchStatusBadge is a standalone
      Phase 4 composite that consumes the same Tailwind color tokens
      (var(--color-success) / var(--color-warning) / etc.) and renders
      the 5 match-domain statuses with the color-mix opacity pattern
      that StatusBadge uses internally for its `pending` / `public` /
      `private` cases. Visual parity preserved; type boundaries kept clean.

      Rule 2 amendment for StatusBadge was NOT applied — MatchStatusBadge
      is self-contained.

  - id: D-04-11-C
    decision: |
      **No `dayjs` runtime dependency added — EventDateBadge + Matches/Show.vue
      use the native `Intl.DateTimeFormat` API.**

      The plan said "Computed: month = dayjs(startsAt).format('MMM').toUpperCase();".
      `dayjs` is not in `apps/web/package.json`. Adding it costs ~7kB
      gzipped for a one-method use case (`format('MMM')` + `format('D')`).
      The native `Intl.DateTimeFormat('en-US', { month: 'short' })` API
      produces "Jan", "Feb", … which we uppercase. For Matches/Show.vue's
      richer scheduled_at format, the same API produces "Tue, May 14, 2026,
      08:00 PM" with one Intl call.

      Locale-aware future state: when D-013 second locale lands (Phase 8+),
      the Intl format string will already respond to the locale switcher
      via the `i18n.locale` shared prop — no library swap needed.

  - id: D-04-11-D
    decision: |
      **TextInput primitive extended (Rule 2) to support `type="date"`.**

      The filter bar needs two date inputs (date_from + date_to). Phase 2's
      TextInput only allows `'text' | 'email' | 'search'`. Two options:
      (a) build a new DateInput primitive, (b) extend TextInput's `type`
      prop union.

      Chose (b): minimal one-character change to the type union, zero
      visual or behavioural divergence (browser-native date picker is
      universally accessible), no new component to maintain. A dedicated
      DateInput primitive would be the right choice if we needed custom
      styling on the calendar popover or non-native masking — neither is
      required in P1 (D-013 plumbed-not-styled idiom).

  - id: D-04-11-E
    decision: |
      **Template avoids `>` literal comparison operators — they trip the
      NoHardcodedStringsTest regex.**

      `tests/Feature/I18n/NoHardcodedStringsTest.php` greps with
      `/>([^<]{3,})</` to find text nodes between tags. When a Vue
      template attribute uses `v-if="lastPage > 1"`, the regex sees
      the `>` inside `"... > 1"` as a tag close and captures the
      next chunk of newline-indented attribute names as a "hardcoded
      English string". Two affected literals in Index.vue + one in
      Show.vue were refactored into computed booleans (`hasMultiplePages`,
      `isOnFirstPage`, `isOnLastPage`, `hasRoleGroups`) so the template
      uses identifier names instead of comparisons.

      Long-term cleanup: the NoHardcodedStringsTest regex should use a
      proper Vue template parser. Tracked in deferred-items but
      out-of-scope for this plan (Rule SCOPE BOUNDARY — pre-existing
      test idiom, not caused by our changes).

metrics:
  duration_minutes: 11
  completed: 2026-05-13
---

# Phase 4 Plan 11: Public Match Vue Pages + 5 Composite Components + EventDateBadge + PublicLayout Update Summary

**One-liner:** 2 Inertia pages (Matches/Index calendar with date_from/date_to/tag/status filter bar + paginated list, Matches/Show detail with role-grouped slot grid + Cancel-signup affordance) + 5 match-domain composite components (MatchCard, RoleSlotGroup, SlotOccupantPill 3-branch privacy renderer, SignupButton w/ Inertia router.post, MatchStatusBadge 5-variant) + 1 reusable EventDateBadge primitive (Phase 4 + future Phase 6/7) + PublicLayout 3rd nav link + TextInput Rule 2 type=date amendment — SC-3 (public visitor browses /matches calendar and views /matches/{id} detail with privacy-stripped occupants + Inertia signup CTA) end-to-end rendered; 880 net additions across 12 files (8 created + 4 modified) in 3 task commits; all gates clean (vue-tsc, Pint, PHPStan L8, NoHardcodedStringsTest, full Pest suite at 474 passed / 1 incomplete — same as 04-10 baseline).

## Performance

- **Duration:** ~11 min
- **Started:** 2026-05-13T15:27:10Z
- **Completed:** 2026-05-13T15:38:52Z
- **Tasks:** 3 / 3
- **Files created:** 8 (5 Vue components + 2 pages + 1 reusable primitive)
- **Files modified:** 4 (PublicLayout.vue + TextInput.vue + matches.php + common.php)
- **Net additions:** +880 lines / −6 lines

## Accomplishments

1. **`Matches/Index.vue` — public match calendar.** Inertia page with `<script setup lang="ts">` and explicit `defineProps<{ matches, pagination, activeFilters }>()`. Consumes the 3 props emitted by MatchCalendarController (plan 04-10). Filter bar has 4 inputs: date_from + date_to (TextInput type=date), tag (TextInput text), status (Select with `Any status | Open | Locked | Played`). State seeded from `activeFilters`; filter submission via `router.get('/matches', params, { preserveScroll: true, replace: true })`. Empty states: "empty + filtered" (with Clear filters button) and "empty default". Prev/Next pagination buttons read pagination.{currentPage,lastPage}. Container `max-w-3xl mx-auto px-4 md:px-6 py-8` (Phase 2 idiom).

2. **`Matches/Show.vue` — public match detail page.** Inertia page consuming the 4 props from MatchShowController (`match`, `roleGroups`, `signupAllowed`, `viewerSlotId`). Hero block: H1 title (escaped via Vue mustaches — T-04-11-02), MatchStatusBadge, formatted scheduled_at `<time>` element. Optional description block (plain text, NO v-html — T-04-11-02 reuse of Phase 2 Pitfall 3). Role-grouped slot grid iterates `roleGroups` array → RoleSlotGroup component per role. Cancel-signup Button rendered when `viewerSlotId !== null`, hitting `router.delete(route('matches.signups.destroy', {match, slot}))`. Defensive empty state when `roleGroups.length === 0`.

3. **`MatchCard.vue` — calendar list card.** Entire card is an anchor to `/matches/{id}`. Layout: EventDateBadge (date pill) + title (PublicMatchData.title.en with fallback) + MatchStatusBadge + optional description excerpt (truncated at 80 chars) + optional signup-count summary "X / Y signed up" (only when controller eager-loaded slots). Hover surface uses `var(--color-surface-elevated)` per Phase 2 idiom.

4. **`MatchStatusBadge.vue` — 5-variant status pill.** Renders one of 5 match-domain statuses (draft / open / locked / played / cancelled) with color-mix opacity background + matching text color (consistent with Phase 1/2 StatusBadge `pending/public/private` pattern). Labels via `t('matches.status.label.{status}')`. Standalone (not a StatusBadge wrap) — see D-04-11-B for the rationale.

5. **`SlotOccupantPill.vue` — privacy-aware 3-branch renderer (T-04-11-01).** This is the security-critical Vue piece for Phase 4 — it consumes the privacy-stripped PublicMatchOccupantData from the controller and renders one of three states based on the nullable fields:
   - **Branch 1:** `slot.displayName !== null` → link to `/players/{slug}` (or plain text if no slug) + ClanTagBadge if `clanTag !== null` + "(you)" marker if `isViewer`.
   - **Branch 2:** `displayName === null && clanTag !== null` → "Anonymous" text + ClanTagBadge (D-008 carve-out — clan tag stays public even when name is withheld).
   - **Branch 3:** `displayName === null && clanTag === null` → "Open" dashed-border pill (empty slot).

   **Privacy contract reminder:** The Vue layer NEVER re-derives privacy. The server-side `PlayerPrivacyGate::passesTier` + `allowsSection('show_match_history')` runs in `PublicMatchOccupantData::fromMatchSlot` and produces null for withheld names. See D-04-11-A for the `!== null` vs `!== undefined` clarification.

6. **`SignupButton.vue` — Inertia router.post integration.** Renders a primary-variant Button (Phase 1 primitive); on click invokes:
   ```ts
   router.post(
       route('matches.signups.store', { match: matchId }),
       { game_role_id: gameRoleId },
       { preserveScroll: true },
   );
   ```
   The `:enabled` prop is the UI-only gate; the MatchSignupService remains the canonical truth (plan 04-06's 4 typed exceptions + plan 04-10's 4→422 conversion). Inertia attaches the XSRF header automatically (Phase 1 plan 01-05 wiring). The 422 response flows back via Inertia's `usePage().props.errors` — `game_role_id` errors surface inline; `general` errors land on the form-level banner (future enhancement — plan didn't require an explicit error display surface beyond Inertia's default flash).

7. **`RoleSlotGroup.vue` — per-role section with signup CTA gating.** Header (role.display_name.en with locale fallback chain) + grid of SlotOccupantPills + SignupButton when `signupAllowed && hasEmptySlot && viewerSlotId === null`. The `hasEmptySlot` computed evaluates whether at least one slot has both displayName=null AND clanTag=null. Exports the `RoleGroup` TypeScript interface that Matches/Show.vue imports for its `defineProps`.

8. **`EventDateBadge.vue` — reusable calendar-date pill (Phase 4 + 6 + 7).** Renders a vertical pill with 3-letter month abbreviation + day number ("MAY 14"), used by MatchCard in Phase 4 and earmarked for TournamentCard (Phase 6) + ArticleCard (Phase 7). Uses native `Intl.DateTimeFormat` (no `dayjs` dep — D-04-11-C). Output is a semantic `<time datetime="ISO-8601">` element.

9. **`PublicLayout.vue` — added 3rd nav link.** Inserted `/matches` Inertia Link between `/clans` and `/players` (alphabetical ordering). Active-link detection via `isActive('/matches')` (the existing `page.url.startsWith` helper from Phase 2). `common.nav.matches` i18n key added to common.php.

10. **`TextInput.vue` Rule 2 amendment — `type="date"` support.** Extended the `type` prop union from `'text' | 'email' | 'search'` to `'text' | 'email' | 'search' | 'date'`. Required by Matches/Index.vue's date_from/date_to filters. Minimal diff (1 char added to the union); browser-native date picker is universally accessible — no styling regression. See D-04-11-D for the build-vs-extend rationale.

11. **`lang/en/matches.php` expanded.** Added `matches.directory.*` (10 keys for filter labels + pagination + signup summary), `matches.status.label.*` (5 keys for the badge variants), and `matches.show.*` (8 keys for title fallback / signup / cancel / slot states / role section / scheduled_at / description / login prompt / no-roles defensive state). Net +49 lines vs the plan 04-01 scaffold.

12. **`lang/en/common.php` extended.** Added `common.nav.matches = 'Matches'` key for the new nav link.

## Task Commits

1. **Task 1 — 2 Inertia pages + MatchCard + i18n + TextInput Rule 2** — `993a670` (feat) — 5 files; 535 lines added / 6 lines removed; Matches/Index calendar page (filter bar + pagination + empty states), Matches/Show detail page (hero + description + role-grouped grid + cancel-signup), MatchCard composite, expanded matches.php lang file, TextInput type=date amendment.

2. **Task 2 — 4 match composite components** — `afdcac8` (feat) — 4 files; 280 lines added; MatchStatusBadge (5-variant color-mix pill), SlotOccupantPill (3-branch privacy renderer), SignupButton (Inertia router.post + XSRF), RoleSlotGroup (per-role section with signup CTA gating).

3. **Task 3 — EventDateBadge primitive + PublicLayout nav update + common.nav.matches** — `28e0177` (feat) — 3 files; 65 lines added; EventDateBadge (reusable for Phase 6/7), PublicLayout 3rd nav link (between Clans/Players), common.php key.

## Files Created/Modified

### Created (8)

| File | LOC | Notes |
|---|---|---|
| `apps/web/resources/js/pages/Matches/Index.vue` | 265 | Public calendar — defineProps<{ matches, pagination, activeFilters }>(), 4-input filter bar, prev/next pagination, 3 empty states |
| `apps/web/resources/js/pages/Matches/Show.vue` | 136 | Public detail — defineProps<{ match, roleGroups, signupAllowed, viewerSlotId }>(), hero + description + role grid + cancel-signup affordance |
| `apps/web/resources/js/components/matches/MatchCard.vue` | 89 | Calendar card — anchor to /matches/{id}, EventDateBadge + title + status + summary; permissive overlay type for eager-loaded slots |
| `apps/web/resources/js/components/matches/MatchStatusBadge.vue` | 60 | 5-variant pill (draft/open/locked/played/cancelled), color-mix opacity, t() label |
| `apps/web/resources/js/components/matches/SlotOccupantPill.vue` | 103 | 3-branch privacy render — identified / anonymous+tag / open; D-008 carve-out |
| `apps/web/resources/js/components/matches/SignupButton.vue` | 44 | Inertia router.post(matches.signups.store) + preserveScroll; primary Button variant |
| `apps/web/resources/js/components/matches/RoleSlotGroup.vue` | 73 | Per-role section: header + slot grid + signup CTA gating; exports RoleGroup interface |
| `apps/web/resources/js/components/events/EventDateBadge.vue` | 47 | Reusable date-pill — native Intl.DateTimeFormat (no dayjs dep) |

### Modified (4)

| File | Change |
|---|---|
| `apps/web/resources/js/layouts/PublicLayout.vue` | +17 lines — 3rd Inertia Link nav item for /matches between Clans and Players; isActive('/matches') styling |
| `apps/web/resources/js/components/ui/TextInput.vue` | +1 line — type prop union extended to include 'date' (Rule 2) |
| `apps/web/lang/en/matches.php` | +49 / -6 lines — added directory.* (10 keys), status.label.* (5), show.* (8 new keys) |
| `apps/web/lang/en/common.php` | +1 line — common.nav.matches added |

## Page Component Prop Signatures

```ts
// Matches/Index.vue
type CalendarMatchEntry = App.Data.PublicMatchData & {
    slots?: Array<{ occupant_user_id?: string | null }>;
};
interface Pagination {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
}
interface ActiveFilters {
    dateFrom: string | null;
    dateTo: string | null;
    tag: string | null;
    status: string | null;
}
defineProps<{
    matches: CalendarMatchEntry[];
    pagination: Pagination;
    activeFilters: ActiveFilters;
}>();

// Matches/Show.vue
defineProps<{
    match: App.Data.PublicMatchData;
    roleGroups: RoleGroup[];   // exported from RoleSlotGroup.vue
    signupAllowed: boolean;
    viewerSlotId: string | null;
}>();

// RoleGroup interface (exported from RoleSlotGroup.vue)
interface RoleGroup {
    gameRoleId: string;
    roleKey: string;
    roleDisplayName: Record<string, string> | null;
    sortOrder: number;
    slots: App.Data.PublicMatchOccupantData[];
}
```

## 3-Branch Privacy Rendering in SlotOccupantPill

```ts
const hasIdentifiedOccupant = computed(() => props.slot.displayName !== null);
const hasAnonymousOccupant = computed(() => props.slot.displayName === null && props.slot.clanTag !== null);
// Empty slot (Branch 3) — when both checks are false.
```

```html
<div v-if="hasIdentifiedOccupant">
    <a v-if="slot.playerSlug !== null" :href="`/players/${slot.playerSlug}`">{{ slot.displayName }}</a>
    <span v-else>{{ slot.displayName }}</span>
    <ClanTagBadge v-if="syntheticClanTag !== null" :tag="syntheticClanTag" />
    <span v-if="slot.isViewer">{{ t('matches.show.you_marker') }}</span>
</div>

<div v-else-if="hasAnonymousOccupant">
    <span>{{ t('matches.show.slot_taken_anonymous') }}</span>
    <ClanTagBadge :tag="syntheticClanTag" />
</div>

<div v-else>
    <span>{{ t('matches.show.slot_open') }}</span>
</div>
```

**Privacy contract** (D-04-11-A): The DTO is `displayName: string | null` (NOT `undefined`). The plan said `!== undefined` but the actual TS contract from spatie/laravel-data is nullable, not optional. The branches test `!== null`. Behavior is identical — server still emits null in withheld/empty cases; Vue renders accordingly.

## SignupButton onError Shape

```ts
function signUp(): void {
    if (!props.enabled) return;
    router.post(
        route('matches.signups.store', { match: props.matchId }),
        { game_role_id: props.gameRoleId },
        { preserveScroll: true },
    );
}
```

The 422 response from plan 04-10's MatchSignupController converts each typed service exception:
- `MatchNotOpenException`, `TagRestrictedException`, `AlreadySignedUpException` → `general` field error.
- `CapacityExceededException` → `game_role_id` field error.

Inertia surfaces these via `usePage().props.errors`. The current SignupButton implementation does NOT include an inline error display — it relies on Inertia's flash session forwarded via `share()` (or the Show page's future banner). A future enhancement (out of scope for plan 04-11) would wire `usePage().props.errors.general` into a per-role inline error.

## MatchStatusBadge 5 Variants

| status | bg | fg |
|---|---|---|
| `draft` | color-mix(srgb, var(--color-text-muted) 20%, transparent) | var(--color-text-muted) |
| `open` | color-mix(srgb, var(--color-success) 20%, transparent) | var(--color-success) |
| `locked` | color-mix(srgb, var(--color-warning) 20%, transparent) | var(--color-warning) |
| `played` | color-mix(srgb, var(--color-accent) 20%, transparent) | var(--color-accent) |
| `cancelled` | color-mix(srgb, var(--color-danger) 20%, transparent) | var(--color-danger) |

**No StatusBadge Rule 2 amendment** — see D-04-11-B (MatchStatusBadge is standalone rather than extending StatusBadge's domain-status variants).

## PublicLayout Amendment

```html
<!-- 3rd nav link inserted alphabetically between Clans (line 43) and Players (line 60) -->
<Link
    href="/matches"
    :class="[
        'px-3 py-1 text-sm font-semibold rounded-md',
        'transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
        'focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]',
        isActive('/matches')
            ? 'text-[var(--color-text)] border-l-[3px] border-[var(--color-accent)] pl-2'
            : 'text-[var(--color-text-muted)] hover:text-[var(--color-text)]',
    ]"
    :aria-current="isActive('/matches') ? 'page' : undefined"
>
    {{ t('common.nav.matches') }}
</Link>
```

`common.nav.matches = 'Matches'` was added to `apps/web/lang/en/common.php`. The Inertia `<Link>` component triggers SPA-style navigation without a full page load (Phase 1 wiring); active-link detection reuses the existing `page.url.startsWith` helper.

## EventDateBadge as a Reusable Primitive

```html
<time :datetime="isoLabel"
      class="inline-flex flex-col items-center justify-center
             min-w-8 min-h-8 px-2 py-1 rounded-md
             bg-[var(--color-accent)] text-[var(--color-accent-fg)]
             font-mono text-xs font-semibold leading-tight select-none">
    <span class="text-[10px] tracking-wide">{{ monthLabel }}</span>
    <span class="text-base leading-none">{{ dayLabel }}</span>
</time>
```

- **Phase 4 (this plan):** consumed by MatchCard for the calendar list's date pill.
- **Phase 6 (planned):** TournamentCard for tournament cards.
- **Phase 7 (planned):** ArticleCard for news/article cards.

`startsAt` accepts any ISO-8601 datetime string (e.g., GameMatch.scheduled_at, Event.starts_at). Native `Intl.DateTimeFormat('en-US', { month: 'short' })` produces the locale-appropriate 3-letter month — no `dayjs` runtime dep (D-04-11-C).

## Verification

| Gate | Command | Result |
|---|---|---|
| TypeScript / vue-tsc | `docker compose exec web pnpm exec vue-tsc --noEmit` | **0 errors** |
| Pint | `pint --test` | **clean, 295 files** |
| PHPStan L8 | `phpstan analyse` | **0 errors** |
| NoHardcodedStrings test | `pest tests/Feature/I18n/NoHardcodedStringsTest.php` | **1 passed / 1 assertion** |
| Matches namespace tests | `pest tests/Feature/Matches/` | **49 passed / 298 assertions** |
| Full Pest suite | `pest` | **474 passed / 1 incomplete / 1405 assertions** (same as 04-10 baseline) |
| Vite production build | `pnpm exec vite build` | **built in 2.91s, manifest.json emitted, Matches/Index + Matches/Show chunks present** |

The 1 incomplete is `Admin/MatchAuditLogTest` (deferred to plan 04-12 by design — Wave 0 stub still flagged). Full suite parity with 04-10 baseline confirms no regression.

## Decisions Made

- **D-04-11-A:** Privacy rendering uses `!== null`, not `!== undefined`. The PublicMatchOccupantData TS type emitted by spatie/laravel-data is nullable (`string | null`), not optional (`undefined | string | null`). Behavior is identical to the plan's intent — the server emits null in withheld/empty cases; Vue branches on null.
- **D-04-11-B:** MatchStatusBadge is standalone (does NOT extend StatusBadge with 5 new variants). StatusBadge's domain-status variant set stays focused; adding 5 match-domain variants would dilute its meaning. MatchStatusBadge reuses the same color-mix pattern internally.
- **D-04-11-C:** No `dayjs` runtime dep. Native `Intl.DateTimeFormat` handles all date formatting needs in this plan (3-letter month for EventDateBadge, full datetime for Matches/Show scheduled_at). Saves ~7kB gzip.
- **D-04-11-D:** TextInput type prop extended (Rule 2) to support `'date'` — minimal one-character union expansion vs. building a new DateInput component for the calendar filter bar.
- **D-04-11-E:** Templates avoid `>` literal comparison operators because the NoHardcodedStringsTest regex `>([^<]{3,})<` misreads them. 4 affected v-if expressions refactored to computed booleans (`hasMultiplePages`, `isOnFirstPage`, `isOnLastPage`, `hasRoleGroups`).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 — Missing primitive support] `TextInput.vue` type union extended to include 'date'**

- **Found during:** Task 1, writing Matches/Index.vue filter bar.
- **Issue:** Plan acceptance criteria require date_from + date_to date pickers in the filter bar. The Phase 2 TextInput primitive only allows `'text' | 'email' | 'search'` — vue-tsc reported 2 errors (`Type "date" is not assignable to type ...`) when I passed `type="date"`.
- **Fix:** Extended the `type` prop union to `'text' | 'email' | 'search' | 'date'`. Browser-native date picker requires no additional styling.
- **Files modified:** `apps/web/resources/js/components/ui/TextInput.vue`
- **Commit:** `993a670`
- **Codified as:** D-04-11-D.

**2. [Rule 1 — Test regex misread] `>` literal in v-if expressions tripped NoHardcodedStringsTest**

- **Found during:** Task 1, first NoHardcodedStringsTest run.
- **Issue:** The test's regex `/>([^<]{3,})</` interprets `>` inside an attribute value as a tag close, then captures the following indented attribute names as a "hardcoded English string". Affected expressions: `v-if="pagination.lastPage > 1"`, `:disabled="pagination.currentPage >= pagination.lastPage"`, `v-if="roleGroups.length > 0"`.
- **Fix:** Refactored 3 v-if/disabled expressions into computed boolean identifiers (`hasMultiplePages`, `isOnFirstPage`, `isOnLastPage`, `hasRoleGroups`) defined in `<script setup>`. The test now sees only identifier names in attribute values — no `>` literals leak through.
- **Files modified:** `apps/web/resources/js/pages/Matches/Index.vue`, `apps/web/resources/js/pages/Matches/Show.vue`
- **Commit:** `993a670`
- **Codified as:** D-04-11-E.

**3. [Rule 2 — Missing critical i18n keys] `matches.directory.*` + `matches.status.label.*` + `matches.show.*` expansion**

- **Found during:** Task 1, while wiring page copy through `t()`.
- **Issue:** Plan 04-01 scaffolded only `matches.signup.*`, `matches.status.error.*`, `matches.calendar.*`, and 5 `matches.show.*` keys. The Index.vue page needed 10 directory keys (filter labels, pagination, empty states, signup summary); Show.vue needed 8 additional show keys (description heading, no-roles state, role-section header, you-marker, etc.); MatchStatusBadge needed 5 status-label keys.
- **Fix:** Added 23 new keys to `apps/web/lang/en/matches.php` covering `directory.*`, `status.label.*`, and `show.*` namespaces. All page literals flow through `t()`.
- **Files modified:** `apps/web/lang/en/matches.php`
- **Commit:** `993a670`

**4. [Privacy contract clarification] `!== null` vs `!== undefined` for displayName**

- **Found during:** Task 2, writing SlotOccupantPill.
- **Issue:** Plan `<must_haves>` text says `v-if="slot.displayName !== undefined"`. The actual generated TS type for PublicMatchOccupantData uses `displayName: string | null` (nullable, not optional). spatie/laravel-data emits `undefined | T | null` only when the property is wrapped in `Optional<>` — which PublicMatchOccupantData does not do.
- **Fix:** Used `!== null` (matches the actual TS contract). Server-side privacy gate still emits null in withheld/empty cases; Vue branches accordingly. No security-effect change.
- **Files affected:** `apps/web/resources/js/components/matches/SlotOccupantPill.vue`
- **Commit:** `afdcac8`
- **Codified as:** D-04-11-A.

**5. [Rule 3 — Build artifact prerequisite] Filament theme manifest rebuilt before full test run**

- **Found during:** Full Pest run after Task 3 commit.
- **Issue:** `tests/Feature/Admin/*` (FilamentBootTest, ClanFilamentResourceTest, etc.) failed with "Vite manifest not found at: /app/public/build/filament/manifest.json" because the Filament theme is built via a separate Vite config (`vite.filament.config.ts`) and the working directory's `public/build/filament/` was stale or missing.
- **Fix:** Ran `docker compose exec web pnpm exec vite build --config vite.filament.config.ts` to regenerate the Filament theme manifest. 27 Admin/Filament tests now pass; total suite parity (474 passed) restored.
- **Files modified:** None (build artifact only — emits to `apps/web/public/build/filament/`).
- **Note:** Not committed (build outputs are gitignored). Documented here as a recovery step for the verification gate.

### Non-deviations (planned ambiguities resolved)

- **`>` comparison in v-if** — the plan's verbatim Vue skeleton uses `v-for` everywhere but didn't explicitly forbid `>` comparisons. The NoHardcodedStringsTest regex (Phase 1 plan 01-08) is the constraint; D-04-11-E is the resolution.
- **Standalone vs wrapping MatchStatusBadge** — plan said "wraps StatusBadge primitive". Implementing as standalone (D-04-11-B) preserves StatusBadge's domain semantics; no Rule 2 amendment to StatusBadge was needed.
- **dayjs as runtime dep** — plan suggested dayjs syntax; native Intl is the lighter equivalent. D-04-11-C captures the trade-off.

## Auth Gates

None. Discord OAuth was wired in Phase 1; this plan only consumes the existing auth state via `usePage().props.auth` (in PublicLayout) and `router.post` (with automatic XSRF in SignupButton).

## Known Stubs

- **No NEW stubs introduced by this plan.** All components render real props from real controllers (plan 04-10's MatchCalendarController + MatchShowController).
- **1 carry-over Wave 0 stub:** `Admin/MatchAuditLogTest` (Wave 0 stub from plan 04-02, flipped GREEN by plan 04-12).

## Threat Surface Notes

Threat register T-04-11-01..05 dispositions:

| Threat ID | Disposition | Mitigation status |
|---|---|---|
| T-04-11-01 (Privacy logic re-derived client-side) | mitigate | **MITIGATED** — SlotOccupantPill branches on `slot.displayName !== null` (the privacy-stripped DTO field). Server-side PlayerPrivacyGate runs in PublicMatchOccupantData::fromMatchSlot (plan 04-10). No client-side privacy re-derivation. |
| T-04-11-02 (XSS via match title) | mitigate | **MITIGATED** — All user content rendered via `{{ }}` mustache interpolation (Vue auto-escape). No `v-html` anywhere in this plan. Description block renders as plain text with `whitespace-pre-wrap`. |
| T-04-11-03 (Inertia router.post forged game_role_id) | mitigate | **MITIGATED** (upstream) — MatchSignupRequest FormRequest validates game_role_id as UUID + exists; MatchSignupService runs the canonical signup transaction. |
| T-04-11-04 (Hardcoded EN strings escape into UI) | mitigate | **MITIGATED** — NoHardcodedStringsTest scans `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/` recursively; new `pages/Matches/` + `components/matches/` + `components/events/` subdirs auto-covered. Test GREEN (1 passed / 1 assertion). |
| T-04-11-05 (Empty-slot UI tricks user into signing up to filled role) | mitigate | **MITIGATED** — SignupButton :enabled derived from `signupAllowed && hasEmptySlot && viewerSlotId === null`. Backend MatchSignupService is canonical truth — lockForUpdate + capacity guard runs at POST time. |

No new threat-flag surface introduced. All new code is Inertia/Vue rendering of server-emitted DTOs; no new network endpoints, auth paths, file access, or schema changes.

## Commits

| Hash | Task | Files | Highlights |
|---|---|---|---|
| `993a670` | Task 1 — 2 pages + MatchCard + i18n + TextInput Rule 2 | 5 | Matches/Index calendar (filter bar + pagination), Matches/Show detail (hero + role grid + cancel-signup), MatchCard composite, +49 i18n keys, TextInput type=date |
| `afdcac8` | Task 2 — 4 match composite components | 4 | MatchStatusBadge (5 variants), SlotOccupantPill (3-branch privacy renderer), SignupButton (Inertia router.post), RoleSlotGroup (per-role section + CTA gating) |
| `28e0177` | Task 3 — EventDateBadge + PublicLayout + common.nav.matches | 3 | EventDateBadge reusable primitive (Phase 4/6/7), PublicLayout 3rd nav link, common.php key |

## Self-Check: PASSED

- `apps/web/resources/js/pages/Matches/Index.vue` exists — 265 lines (verified via git log diff stat).
- `apps/web/resources/js/pages/Matches/Show.vue` exists — 136 lines.
- `apps/web/resources/js/components/matches/MatchCard.vue` exists — 89 lines.
- `apps/web/resources/js/components/matches/MatchStatusBadge.vue` exists — 60 lines.
- `apps/web/resources/js/components/matches/SlotOccupantPill.vue` exists — 103 lines (3-branch privacy render).
- `apps/web/resources/js/components/matches/SignupButton.vue` exists — 44 lines (router.post + preserveScroll).
- `apps/web/resources/js/components/matches/RoleSlotGroup.vue` exists — 73 lines (exports RoleGroup interface).
- `apps/web/resources/js/components/events/EventDateBadge.vue` exists — 47 lines (native Intl.DateTimeFormat).
- `apps/web/resources/js/layouts/PublicLayout.vue` modified — `common.nav.matches` link present (`grep -c "common.nav.matches"` → 1).
- `apps/web/resources/js/components/ui/TextInput.vue` modified — `'date'` in type union (Rule 2).
- `apps/web/lang/en/matches.php` modified — directory.* + status.label.* + show.* keys present (`grep -c "directory\|status.label"` → 5+).
- `apps/web/lang/en/common.php` modified — `'matches' => 'Matches'` key present (`grep -c "'matches' =>"` → 1).
- All 3 commits (`993a670`, `afdcac8`, `28e0177`) present in `git log --oneline -5`.
- vue-tsc: 0 errors.
- Pint: 295 files clean.
- PHPStan L8: 0 errors.
- NoHardcodedStringsTest: 1 passed / 1 assertion.
- Matches namespace Pest: 49 passed / 298 assertions.
- Full Pest: 474 passed / 1 incomplete / 1405 assertions (parity with 04-10 baseline).
- Vite production build: clean, Matches/Index + Matches/Show chunks emitted.
