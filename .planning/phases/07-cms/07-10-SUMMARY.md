---
phase: 07-cms
plan: 10
subsystem: cms-public-vue-pages
tags:
  - wave-6
  - vue-pages
  - fullcalendar
  - public-cms
  - article-show
  - events-calendar
  - search-results
  - blog-index
  - tiptap-xss-mitigation
  - publiclayout-extension
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-05-SUMMARY.md  # PublicArticleData fully wired with tiptap_converter (bodyHtml v-html source)
    - .planning/phases/07-cms/07-08-SUMMARY.md  # SearchResultsData (FTS section shape)
    - .planning/phases/07-cms/07-09-SUMMARY.md  # 5 public controllers + 2 DTOs (ArticleSummaryData + CalendarEventData) + routes
    - .planning/phases/02-clans-tags/02-08-SUMMARY.md  # PublicLayout primitive + nav slot idiom
    - .planning/phases/06-tournaments-brackets/06-12-SUMMARY.md  # Vue page App.Data.* ambient-namespace idiom precedent
  provides:
    - "resources/js/pages/Articles/Index.vue — paginated card grid + CategoryFilterPill row + pagination footer; consumes BlogIndexController props (articles, pagination, categories, activeCategory, meta); ArticleCard items linked via Inertia router"
    - "resources/js/pages/Articles/Show.vue — single-article detail page; hero image + meta strip + v-html bodyHtml rendering (Pitfall 10 XSS-safe by Tiptap profile pinning chain — proven end-to-end in ArticleShowPageTest)"
    - "resources/js/pages/Events/Index.vue — FullCalendar Vue3 mount with month/week/day toggles + Inertia router.visit eventClick + Pitfall 11 explicit local timezone; CalendarLegend renders Open Question 6 LOCKED palette"
    - "resources/js/pages/Search/Results.vue — 3-section results page (articles + clans + players) with rank chips, thumbnail rendering, empty-state per-section + global empty-state"
    - "resources/js/components/cms/ArticleCard.vue — listing card component; props typed via App.Data.ArticleSummaryData (07-09 DTO)"
    - "resources/js/components/cms/CategoryFilterPill.vue — active-state Inertia Link pill with router param wiring"
    - "resources/js/components/cms/SearchBar.vue — debounced (300ms) router.get('/search') submit on Enter; data-test='search-bar' marker for the integration smoke in EventsCalendarPageTest"
    - "resources/js/components/cms/CalendarLegend.vue — 3-chip legend with hex colors inlined to mirror CalendarEventData::colourFor (match=#3B82F6, tournament=#8B5CF6, article=#10B981)"
    - "resources/js/layouts/PublicLayout.vue — extended in-place: <SearchBar /> appended to header chrome between nav slot and theme/auth toggles (hidden md:flex per UI-SPEC § Responsive Breakpoints alongside the existing nav)"
    - "lang/en/cms.php — +blog.{empty,pagination.{prev,next},read_more,category_filter.{label,all}} + article.{meta.{published_on,author,category},hero_alt}"
    - "lang/en/events.php — +legend.{match,tournament,article}.label + navigation.{prev,next,today}"
    - "lang/en/search.php — +results.{section_articles,section_clans,section_players,empty_state} + header.{q_placeholder,submit}"
    - "tests/Feature/Articles/ArticleShowPageTest — 5 GREEN it() blocks (replaces 07-01 RED stub)"
    - "tests/Feature/Events/EventsCalendarPageTest — 5 GREEN it() blocks (replaces 07-01 RED stub)"
    - "resources/js/components/tournaments/bracket-node-dimensions.ts — extracted NODE_WIDTH/NODE_HEIGHT constants (Rule 3 fix for pre-existing pnpm build failure)"
  affects:
    - apps/web/resources/js/pages/Articles/                       # +2 pages
    - apps/web/resources/js/pages/Events/                         # +1 page
    - apps/web/resources/js/pages/Search/                         # +1 page
    - apps/web/resources/js/components/cms/                       # +4 components (new folder)
    - apps/web/resources/js/components/tournaments/               # +1 file (bracket-node-dimensions.ts) + 2 modified (BracketNode + BracketCanvas)
    - apps/web/resources/js/layouts/PublicLayout.vue              # +SearchBar import + insertion in header
    - apps/web/resources/js/types/api.d.ts                        # auto-regenerated (ArticleSummaryData + CalendarEventData)
    - packages/shared-types/src/api.d.ts                          # same auto-regenerated mirror
    - apps/web/lang/en/cms.php                                    # +blog + article namespaces
    - apps/web/lang/en/events.php                                 # +legend + navigation namespaces
    - apps/web/lang/en/search.php                                 # +results.section_*/empty_state + header.* namespaces
    - apps/web/tests/Feature/Articles/ArticleShowPageTest.php     # RED → 5 GREEN
    - apps/web/tests/Feature/Events/EventsCalendarPageTest.php    # RED → 5 GREEN
tech-stack:
  added: []
  patterns:
    - "Vue page prop typing via App.Data.* ambient namespace (Phase 4 D-04-11-A + Phase 6 06-12 idiom continuation): defineProps<{ article: App.Data.PublicArticleData }>() / { results: App.Data.SearchResultsData } / { article: App.Data.ArticleSummaryData } — types flow from spatie/laravel-typescript-transformer auto-regeneration of resources/js/types/api.d.ts on every vue-tsc run (the transformer hook fires when it detects DTO class changes via the #[TypeScript] attribute)."
    - "FullCalendar Vue3 mount pattern (07-RESEARCH Pattern 7 + Pitfall 11): plugins=[dayGridPlugin, timeGridPlugin, interactionPlugin] (3 plugins; @fullcalendar/vue3 ships the wrapper component), events='/events/feed.json' (FullCalendar fetches per-view with start+end params), timeZone='local' (Pitfall 11 — CalendarEventData emits ISO-8601 with explicit UTC offset so the client converts to local in displayed cells), eventClick handler calls info.jsEvent.preventDefault() + router.visit(info.event.extendedProps?.url ?? info.event.url) to preserve Inertia SPA navigation instead of FC's default browser navigation."
    - "Articles/Show.vue v-html bodyHtml safe-by-construction (Pitfall 10 mitigation chain, 4 layers): (1) Tiptap editor profile in 07-01 config registers no iframe/script/oembed/youtube extensions; (2) ArticleResource form field uses ->profile('default') in 07-05; (3) tiptap_converter()->asHTML server-side render in PublicArticleData::fromModel drops unknown nodes silently at parse time; (4) ArticleShowPageTest asserts at HTTP layer that the persisted Tiptap doc containing iframe/script/evil.example.com nodes produces a bodyHtml without ANY of those substrings. The Vue template's v-html=\"article.bodyHtml\" therefore paints SSR-sanitised HTML — Vue does not re-parse or sanitise (and shouldn't, the chain already mitigated upstream)."
    - "CalendarLegend hex-inline mirror (Open Question 6 LOCKED): the 3 chip colors are hardcoded as hex literals in CalendarLegend.vue (#3B82F6, #8B5CF6, #10B981) AND inline in CalendarEventData::colourFor (07-09 PHP). The legend never drifts because they share the same source-of-truth comment header pointing at 07-09 D-07-09-D. If the palette changes, both files must update in lockstep — 07-VALIDATION snapshot test catches drift in plan 07-13."
    - "PublicLayout SearchBar header chrome extension (Phase 2 plan 02-08 nav slot idiom continuation): SearchBar mounted inside a `<div class=\"hidden md:flex\">` wrapper between the nav slot and the theme/auth toggle div. Existing nav links (Clans + Matches + Tournaments + Players from Phase 2-6) + UserMenu + ThemeToggle preserved verbatim. The data-test=\"search-bar\" marker on the SearchBar.vue <form> element is the smoke contract for EventsCalendarPageTest's PublicLayout integration assertion."
    - "Vue 3.5+ compiler-sfc constraint: `<script setup>` blocks cannot host module-level `export const` declarations (the script-setup RFC enforces component-scope only). The previous BracketNode.vue inlined NODE_WIDTH + NODE_HEIGHT inside <script setup> — Vite's pnpm build failed with `[@vue/compiler-sfc] <script setup> cannot contain ES module exports.` Extracted them to a sibling bracket-node-dimensions.ts module imported by both BracketNode.vue (for the SVG attribute bindings) and BracketCanvas.vue (for viewBox sizing). This was a pre-existing Phase 6 bug surfaced by 07-10's pnpm build verification requirement."
    - "Pest expect()->toContain() single-arg semantics: Pest 3's `toContain($needle)` accepts ONE positional argument (the substring to find); the second positional argument is treated as a SEPARATE needle to find, not as a failure message. To attach a custom failure message, use `expect(bool_result)->toBeTrue('custom message')`. This caught the first EventsCalendarPageTest run with a confusing 'expected string to contain message-text' failure mode."
    - "NoHardcodedStringsTest false-positive avoidance: the test regex `/>([^<]{3,})</` treats `>` inside attribute values as tag terminators, so `v-if=\"articles.length > 0\"` reads as `>articles.length > 0\" class=...<` to the scanner. Refactored all `length > 0` / `lastPage > 1` style attribute expressions to computed boolean refs (hasArticles, hasCategories, hasMultiplePages) in the <script setup> block — keeps the template free of bare `>` inside attributes."
key-files:
  created:
    - apps/web/resources/js/pages/Articles/Index.vue
    - apps/web/resources/js/pages/Articles/Show.vue
    - apps/web/resources/js/pages/Events/Index.vue
    - apps/web/resources/js/pages/Search/Results.vue
    - apps/web/resources/js/components/cms/ArticleCard.vue
    - apps/web/resources/js/components/cms/CategoryFilterPill.vue
    - apps/web/resources/js/components/cms/SearchBar.vue
    - apps/web/resources/js/components/cms/CalendarLegend.vue
    - apps/web/resources/js/components/tournaments/bracket-node-dimensions.ts
  modified:
    - apps/web/resources/js/layouts/PublicLayout.vue                        # +SearchBar import + insertion in header
    - apps/web/resources/js/components/tournaments/BracketNode.vue          # NODE_*: inlined export const → external module import (Rule 3 fix)
    - apps/web/resources/js/components/tournaments/BracketCanvas.vue        # NODE_*: import path swapped to bracket-node-dimensions.ts
    - apps/web/resources/js/types/api.d.ts                                  # auto-regenerated by typescript-transformer (+ArticleSummaryData +CalendarEventData)
    - packages/shared-types/src/api.d.ts                                    # same mirror
    - apps/web/lang/en/cms.php                                              # +blog.* + article.*
    - apps/web/lang/en/events.php                                           # +legend.* + navigation.*
    - apps/web/lang/en/search.php                                           # +results.section_*/empty_state + header.*
    - apps/web/tests/Feature/Articles/ArticleShowPageTest.php               # RED stub → 5 GREEN
    - apps/web/tests/Feature/Events/EventsCalendarPageTest.php              # RED stub → 5 GREEN
decisions:
  - "D-07-10-A — Vue components live in lowercase `components/cms/` (not `Components/Cms/`). Plan must_haves wording referenced `Components/Cms/*` (uppercase) following the original PROJECT.md UI-SPEC casing, but the actual repo already uses lowercase top-folders (components/clans/, components/events/, components/matches/, components/tournaments/) and lowercase top-level layouts/. Following filesystem precedent keeps imports consistent (@/components/cms/SearchBar.vue maps to a real path; @/Components/Cms/SearchBar.vue would 404 at build time). The Inertia component NAME passed to Inertia::render still uses uppercase ('Articles/Show' etc.) because those resolve to `./pages/Articles/Show.vue` — pages folder uses uppercase subdirectories per existing convention (pages/Tournaments/, pages/Matches/, etc.)."
  - "D-07-10-B — Boolean view helpers extracted from inline attribute expressions to keep NoHardcodedStringsTest regex happy. The test scanner `/>([^<]{3,})</` treats `>` inside attribute values (like `v-if=\"x > 0\"`) as tag terminators and false-positive flags the captured text. Refactored to `hasCategories = computed(() => x.length !== 0)` etc. inside <script setup>, then template references the computed ref. This is a defensive idiom — the alternative was to rewrite the test scanner to use a proper Vue parser (out of scope for 07-10) or to suppress the test (regression risk for D-013 enforcement). The computed-ref refactor is forward-compatible and keeps the safety-net intact."
  - "D-07-10-C — NODE_WIDTH/NODE_HEIGHT extracted to sibling .ts module (Rule 3 fix for pre-existing pnpm build failure). Vue 3.5+ `<script setup>` refuses module-level `export const`. The pre-existing Phase 6 BracketNode.vue inlined them, which broke `pnpm build` on the master branch — discovered when 07-10's verification line required both client + ssr bundles green. The fix is minimal: create bracket-node-dimensions.ts holding both constants, import them from BOTH BracketNode.vue (for the SVG bindings) AND BracketCanvas.vue (for viewBox math). No runtime behaviour change; pure module-layout fix. Logged as a deviation because it was a pre-existing bug outside the plan's stated scope, but fixed inline because the plan's verification depends on a clean build."
  - "D-07-10-D — FullCalendar options typed as `Record<string, unknown>` instead of the strict CalendarOptions import from @fullcalendar/core. The strict type drags in FC's internal types across the SSR boundary which causes vue-tsc friction on the Inertia SSR build (the typescript-transformer emits ambient namespaces but FC's types collide with Vue's component-vnode types). Using `Record<string, unknown>` is the pragmatic narrow path for this 5-key config; if Phase 9 adds a deeper FC integration (e.g. resource view, recurring events), revisit with proper FC types under a vite-ssr-noExternal allowlist."
  - "D-07-10-E — Header SearchBar lives inside `hidden md:flex` wrapper alongside the existing nav. Mobile users see the wordmark + theme/auth toggles only (matching Phase 1-6 mobile nav behaviour); the search input surfaces in the md+ breakpoint. A dedicated mobile-search affordance (e.g. icon button → modal) is deferred — out of scope for v1 launch per Phase 7's CMS-public-browse SC."
metrics:
  duration: 18m 04s
  completed: 2026-05-14
  tasks: 2
  files_created: 9
  files_modified: 10
  commits: 2
---

# Phase 7 Plan 10: Wave 6 — Public CMS Vue Pages + FullCalendar + Cms/* Components Summary

Wave 6 lands the 4 public Vue pages that surface the CMS (`/blog`,
`/blog/{slug}`), the calendar (`/events`), and the Postgres FTS search
(`/search`) on the public web. Wires FullCalendar against the JSON feed
shipped in 07-09. Renders article bodies via `v-html` with the 4-layer
Pitfall 10 mitigation chain proven end-to-end (editor profile pinning →
tiptap_converter → server-side render → HTTP-layer XSS assertion).
Surfaces the global search bar in PublicLayout for SC-4 access from
every public page.

This plan completes the client-side half of SC-2 (CMS public browse) +
SC-3 (events calendar) + SC-4 (Postgres FTS search) atop the 07-09
controllers. Plan 07-11 then enables SSR for production. Plan 07-12
adds the meta-tag head + sitemap on top of these pages.

## Surface Delivered

### 4 Public Vue Pages (`apps/web/resources/js/pages/`)

| Page | File | Props (from 07-09 controller) |
|---|---|---|
| `Articles/Index` | `pages/Articles/Index.vue` | articles, pagination, categories, activeCategory, meta |
| `Articles/Show` | `pages/Articles/Show.vue` | article: PublicArticleData (includes bodyHtml for v-html) |
| `Events/Index` | `pages/Events/Index.vue` | categories, meta (NO events — FullCalendar fetches feed.json) |
| `Search/Results` | `pages/Search/Results.vue` | results: SearchResultsData, query, meta |

All 4 pages mount inside `<PublicLayout>` and consume props typed via the
`App.Data.*` ambient namespace (auto-regenerated by
spatie/laravel-typescript-transformer on every vue-tsc run).

### 4 Cms/* Components (`apps/web/resources/js/components/cms/`)

`ArticleCard.vue` — listing card (hero thumb + title + excerpt + meta +
read-more). Props typed via App.Data.ArticleSummaryData (07-09 DTO; 9
fields, no bodyHtml — D-07-09-A).

`CategoryFilterPill.vue` — active-state Inertia Link pill. Slug `null`
represents the "All" reset pill (drops `?category=` from the URL).

`SearchBar.vue` — debounced (300ms) router.get('/search') submit on Enter.
Placeholder via `t('search.header.q_placeholder')`. data-test="search-bar"
marker for the EventsCalendarPageTest integration smoke.

`CalendarLegend.vue` — 3-chip legend with hex colors inlined to mirror
`CalendarEventData::colourFor` (07-09 D-07-09-D):

```ts
const chips: LegendChip[] = [
  { type: 'match', color: '#3B82F6', labelKey: 'events.legend.match.label' },
  { type: 'tournament', color: '#8B5CF6', labelKey: 'events.legend.tournament.label' },
  { type: 'article', color: '#10B981', labelKey: 'events.legend.article.label' },
];
```

### Layout Extension (`apps/web/resources/js/layouts/PublicLayout.vue`)

Existing header preserved verbatim (Wordmark + nav links + theme/auth
toggles). New: `<SearchBar />` mounted inside a `hidden md:flex` wrapper
between the nav slot and the theme/auth div — matches the existing mobile
nav idiom (Phase 1 UI-SPEC § Responsive Breakpoints).

Already had a nav slot from Phase 2 plan 02-08; this plan did NOT need to
introduce a new slot — the SearchBar lives inline alongside existing
nav children rather than via a dedicated `<slot name="search" />` (the
PublicLayout is used by every Inertia page, so always rendering the
SearchBar in the header is the right default).

### i18n Extensions (3 files)

`lang/en/cms.php` — +`blog.{empty, pagination.{prev,next}, read_more,
category_filter.{label,all}}` + `article.{meta.{published_on,author,category},
hero_alt}`.

`lang/en/events.php` — +`legend.{match,tournament,article}.label` +
`navigation.{prev,next,today}`.

`lang/en/search.php` — +`results.{section_articles, section_clans,
section_players, empty_state}` flat keys (alongside legacy 07-01 nested
keys preserved for back-compat) + `header.{q_placeholder, submit}`.

All keys flow through `t()` in Vue templates (D-013 — ZERO hardcoded
strings; NoHardcodedStringsTest gate green).

## Plan Verification Line-by-Line

| Plan verification line | Result |
|---|---|
| `make pest --filter='ArticleShowPageTest\|EventsCalendarPageTest'` GREEN | **PASS** — 10 passed / 87 assertions (5+5) |
| `vue-tsc --noEmit` returns 0 errors | **PASS** — exit 0 |
| `pnpm build` produces apps/web/public/build/* | **PASS** — client bundle (`app-BPoXEkQ2.js` 278kB) + filament bundle |
| `vite build --ssr` produces apps/web/bootstrap/ssr/ssr.js | **PASS** — `ssr.js` (278kB) + `ssr-manifest.json` (6.79kB) |
| NoHardcodedStringsTest still PASS | **PASS** — 1 assertion |
| Full-suite regression-free | **PASS** — net diff vs 07-09 baseline: +10 GREEN, -2 RED (7 failed / 996 passed → 5 failed / 1006 passed; remaining 5 RED are Wave 0 stubs for plans 07-11/07-12/07-13) |

## pnpm build / SSR build Output (per plan output requirement)

```text
$ pnpm build
> vite build && vite build --config vite.filament.config.ts

vite v7.3.2 building client environment for production...
✓ 822 modules transformed.
public/build/manifest.json                                   24.90 kB │ gzip:  1.42 kB
public/build/assets/Index-QYh0MbRf.js                         2.41 kB │ gzip:  1.13 kB   ← Articles/Index
public/build/assets/Show-DkIiAD6O.js                          3.68 kB │ gzip:  1.36 kB   ← Articles/Show
public/build/assets/Results-Bh-Po5RK.js                       2.92 kB │ gzip:  1.22 kB   ← Search/Results
public/build/assets/PublicLayout.vue_vue_type_…-…-CDgHuBxk.js 83.42 kB │ gzip: 27.43 kB   ← PublicLayout chunk
public/build/assets/Index-QyW979Vj.js                       262.20 kB │ gzip: 78.26 kB   ← Events/Index (FullCalendar — heavy)
public/build/assets/app-BPoXEkQ2.js                         278.19 kB │ gzip: 98.74 kB
✓ built in 2.93s

vite v7.3.2 building client environment for production... (filament)
✓ 1 modules transformed.
public/build/filament/assets/theme-BVBGLtkE.css            110.24 kB │ gzip: 15.68 kB

$ vite build --ssr
vite v7.3.2 building ssr environment for production...
✓ 99 modules transformed.
bootstrap/ssr/ssr-manifest.json    6.79 kB
bootstrap/ssr/ssr.js             278.98 kB
✓ built in 418ms
```

Events/Index.vue chunk weighs in at 262kB because FullCalendar pulls in
its 3 plugins (daygrid + timegrid + interaction) plus core. Acceptable for
a calendar-heavy page; lazy-loading the calendar component is deferred to
post-launch performance pass.

## App.Data.* Ambient Namespace Typing (per plan output requirement)

Vue page prop typing follows the Phase 4 D-04-11-A + Phase 6 06-12 idiom
verbatim. Each page uses `defineProps<{...}>()` with type aliases pulled
from the global ambient namespace:

```ts
// Articles/Show.vue
type PublicArticleData = App.Data.PublicArticleData;
defineProps<{ article: PublicArticleData }>();

// Search/Results.vue
type SearchResultsData = App.Data.SearchResultsData;
type SearchResultData = App.Data.SearchResultData;
defineProps<{ results: SearchResultsData; query: string; meta: PageMeta }>();

// Articles/Index.vue (uses ArticleSummaryData — D-07-09-A)
type ArticleSummaryData = App.Data.ArticleSummaryData;
defineProps<{ articles: ArticleSummaryData[]; pagination: PaginationMeta; ... }>();
```

Types flow from `spatie/laravel-typescript-transformer` auto-regeneration
of `resources/js/types/api.d.ts` on every vue-tsc run (the transformer
hook fires when it detects DTO class changes via the `#[TypeScript]`
attribute). The regen during this plan added `ArticleSummaryData` and
`CalendarEventData` to api.d.ts — both shipped server-side in 07-09 but
the TS types weren't regenerated until 07-10's vue-tsc run.

## FullCalendar Plugins (per plan output requirement)

Events/Index.vue imports 4 packages from @fullcalendar/* (3 plugins + 1
Vue wrapper):

| Import | Purpose |
|---|---|
| `@fullcalendar/vue3` (`FullCalendar`) | Vue 3 wrapper component |
| `@fullcalendar/daygrid` (`dayGridPlugin`) | Month view + dayGridMonth toolbar button |
| `@fullcalendar/timegrid` (`timeGridPlugin`) | Week + day views + timeGridWeek/timeGridDay toolbar buttons |
| `@fullcalendar/interaction` (`interactionPlugin`) | eventClick / select / dateClick interactions |

Plus `@fullcalendar/core` is implicitly pulled in by every plugin
(transitive). Total dependency-tree weight is reflected in the
Events/Index chunk size (262kB minified, 78kB gzipped).

## Open Question 6 LOCKED Color Chips (per plan output requirement)

CalendarLegend.vue inlines the hex literals verbatim:

```ts
const chips: LegendChip[] = [
  { type: 'match', color: '#3B82F6', labelKey: 'events.legend.match.label' },        // Tailwind blue-500
  { type: 'tournament', color: '#8B5CF6', labelKey: 'events.legend.tournament.label' }, // Tailwind violet-500
  { type: 'article', color: '#10B981', labelKey: 'events.legend.article.label' },    // Tailwind emerald-500
];
```

Mirrors `App\Data\CalendarEventData::colourFor` (07-09 D-07-09-D). Both
files carry source-of-truth comments pointing at 07-09 D-07-09-D so the
two never silently drift.

## PublicLayout Header/Nav Slot — Pre-existing or Introduced (per plan output requirement)

PublicLayout already had a header chrome shape from Phase 1 plan 01-07
(Wordmark + skip-link + footer) and a nav slot from Phase 2 plan 02-08
(Clans + Players + UserMenu auth action). Plan 07-10 did NOT introduce
a new `<slot name="search" />` — the SearchBar is mounted directly in
the header chrome alongside the existing nav children. This keeps the
SearchBar visible on every public page that uses PublicLayout (Home,
Clans, Matches, Tournaments, Players, Articles, Events, Search) without
requiring every page to pass a slot.

Trade-off: pages that want to suppress the SearchBar (e.g. a future
landing page with a giant centered search hero) would need either a
prop on PublicLayout (`<PublicLayout :show-search="false">`) or a
fork into a SearchlessPublicLayout. Both deferred to when the use case
arrives.

## Pitfall 10 HTTP-layer XSS Chain Verification

ArticleShowPageTest's third `it()` block ("NEVER returns <iframe> or
<script> in bodyHtml...") is the END-TO-END proof of the 4-layer
mitigation chain. The test persists a published Article whose Tiptap
body deliberately encodes:

```php
'body' => ['en' => [
    'type' => 'doc',
    'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'leading safe text']]],
        ['type' => 'iframe', 'attrs' => ['src' => 'https://evil.example.com']],
        ['type' => 'script', 'attrs' => ['src' => 'https://evil.example.com/xss.js']],
    ],
]]
```

Then hits `GET /blog/{slug}` and asserts the response's Inertia
`article.bodyHtml` prop does NOT contain `<iframe`, `<script`, or
`evil.example.com`. This proves the full chain — editor profile pinning
(07-01 config) → tiptap_converter (07-05 PublicArticleData) → controller
(07-09 BlogShowController) → Inertia payload (07-10 Vue v-html bind) —
drops unsafe nodes at parse time. Vue's `v-html` therefore paints
SSR-sanitised HTML and never sees an iframe/script substring.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking Issue] Pre-existing pnpm build failure in BracketNode.vue.**
- **Found during:** Task 1 first `pnpm build` run.
- **Issue:** `<script setup>` in Vue 3.5+ refuses module-level `export
  const` declarations (compiler-sfc enforces script-setup RFC). The
  pre-existing Phase 6 BracketNode.vue inlined `NODE_WIDTH` + `NODE_HEIGHT`
  as `export const` inside `<script setup>` — Vite build failed with
  `[@vue/compiler-sfc] <script setup> cannot contain ES module exports.`
  Verified pre-existing by `git stash`-ing my changes and rerunning
  `pnpm build` on the master baseline — same failure.
- **Fix:** Created sibling `resources/js/components/tournaments/bracket-node-dimensions.ts`
  exporting `NODE_WIDTH = 200` + `NODE_HEIGHT = 60`. Updated BOTH
  BracketNode.vue (for SVG bindings) AND BracketCanvas.vue (for viewBox
  math) to import from the new module. No runtime behaviour change;
  pure module-layout fix.
- **Files modified:** `apps/web/resources/js/components/tournaments/BracketNode.vue`,
  `apps/web/resources/js/components/tournaments/BracketCanvas.vue`,
  `apps/web/resources/js/components/tournaments/bracket-node-dimensions.ts` (new)
- **Commit:** `dc48a5b`
- **Recorded as:** D-07-10-C
- **Why fixed inline despite scope boundary:** Plan 07-10's verification
  requires `pnpm build` to succeed for both client + ssr bundles. Without
  this fix, the verification line "pnpm build produces both client + ssr
  bundles without errors" could not be satisfied. Rule 3 (blocking issue)
  takes precedence — the fix is minimal, surgical, and zero-risk
  (constants only).

**2. [Rule 1 — Bug] NoHardcodedStringsTest false-positive on `v-if="x > 0"` attribute expressions.**
- **Found during:** Task 1 first `pest --filter=NoHardcodedStringsTest` run.
- **Issue:** The test scanner regex `/>([^<]{3,})</` treats `>` inside
  attribute values as tag terminators. Templates like
  `<nav v-if="categories.length > 0" class="..." :aria-label="...">`
  read as `>...categories.length > 0\" class=...<` to the scanner — the
  captured text `0\" class=\"flex flex-wrap items-center gap-2\"...`
  triggers a hardcoded-string flag.
- **Fix:** Extracted the `length > 0` / `lastPage > 1` style attribute
  expressions to computed boolean refs (`hasCategories`, `hasArticles`,
  `hasMultiplePages`) inside `<script setup>`. Templates now reference
  the computed ref name (no bare `>` inside the attribute).
- **Files modified:** `apps/web/resources/js/pages/Articles/Index.vue`,
  `apps/web/resources/js/pages/Events/Index.vue`
- **Commit:** `dc48a5b`
- **Recorded as:** D-07-10-B
- **Note:** This is a defensive idiom — the alternative was to rewrite
  the NoHardcodedStringsTest scanner to use a proper Vue parser (out of
  scope for 07-10) or to suppress the test (regression risk for D-013
  enforcement). The computed-ref refactor is forward-compatible and
  keeps the safety-net intact.

**3. [Rule 1 — Bug] Pest expect()->toContain() with extra positional argument as failure message.**
- **Found during:** Task 2 first run of EventsCalendarPageTest.
- **Issue:** Pest 3's `toContain($needle)` accepts ONE positional
  argument (the substring to find); the second positional argument is
  treated as a SEPARATE needle to find, not as a failure message. My
  first version of the SearchBar smoke `it()` block called
  `expect($layoutSource)->toContain('SearchBar', 'PublicLayout must import...')`
  which tried to find both substrings — the second one obviously failed.
- **Fix:** Rewrote to `expect(str_contains($layoutSource, 'SearchBar'))->toBeTrue('PublicLayout must import...')`
  — Pest's `toBeTrue($message)` DOES accept a custom failure message.
- **Files modified:** `apps/web/tests/Feature/Events/EventsCalendarPageTest.php`
- **Commit:** `b2c558f`

### Architectural Changes (Rule 4)

None.

### Auth Gates Encountered

None — plan 07-10 is fully autonomous (no auth required for any task).

## Threat Model Status

| Threat ID | Status |
|---|---|
| T-07-10-01 (Tampering — XSS via v-html bodyHtml) | **mitigated** — 4-layer defence (editor profile pinning in 07-01 + ->profile('default') in 07-05 + tiptap_converter()->asHTML in PublicArticleData::fromModel + Pest HTTP-layer assertion that persisted iframe/script Tiptap nodes never surface as substrings in article.bodyHtml). End-to-end proof in ArticleShowPageTest. |
| T-07-10-02 (Info Disclosure — CalendarEventData.id UUID in DOM) | **accepted** — UUIDs are non-sequential, non-leak; appearing in DOM is by design for FullCalendar's eventClick handler. |
| T-07-10-03 (Spoofing — Inertia link prefetch loading unauthorised data) | **accepted** — Inertia prefetch follows public routes only; ArticlePolicy::view gates draft access regardless of prefetch. |
| T-07-10-04 (DoS — Vue v-html rendering deeply nested DOM) | **mitigated** — tiptap-php enforces structure at server side; v-html paints sanitised HTML; browser native parser handles depth. |
| T-07-10-05 (Info Disclosure — SearchBar leaking authenticated user query history via referrer) | **accepted** — Search queries are public surface; no referrer-policy beyond Laravel defaults needed in v1. |
| T-07-10-06 (Tampering — router.visit eventClick navigating to attacker URL) | **mitigated** — `url` field in CalendarEventData is server-controlled (route() generation in 07-09 CalendarEventData::fromModel); attacker cannot inject external URLs without compromising the Event row first. |

## Known Stubs

None. The 4 Vue pages + 4 components fully render their props from the
07-09 controller responses. The `categories` prop on Events/Index.vue
seeds the filter sidebar reserved for a future plan once category-style
match-type tags ship — present in the Inertia payload (assertable by
EventsCalendarPageTest) so the prop contract holds even before the
filter UI lands. The hidden `<ul>` rendering categories is a placeholder
data-test marker, NOT a stub (no visible UI claim being made about
filtering).

## Threat Flags

None. No new endpoints introduced (the plan adds Vue layer on top of
existing 07-09 controllers); no new schema changes; no new file-access
patterns. The SearchBar component issues `router.get('/search')` which
hits the existing throttled route from 07-09.

## Commit Trail

| Task | Commit | Files |
|---|---|---|
| 1: 4 Vue pages + 4 Cms/* components + PublicLayout extension + 3 lang/en extensions + Rule 3 pre-existing build fix + Rule 1 NoHardcodedStrings refactor | `dc48a5b` | 17 (9 created + 8 modified including api.d.ts auto-regen, shared-types mirror, BracketNode + BracketCanvas Rule 3 fix) |
| 2: 2 GREEN test files replacing 07-01 RED stubs (5+5 it() blocks) | `b2c558f` | 2 (both modified — ArticleShowPageTest + EventsCalendarPageTest) |

## Self-Check

- [x] `apps/web/resources/js/pages/Articles/Index.vue` — FOUND
- [x] `apps/web/resources/js/pages/Articles/Show.vue` — FOUND
- [x] `apps/web/resources/js/pages/Events/Index.vue` — FOUND
- [x] `apps/web/resources/js/pages/Search/Results.vue` — FOUND
- [x] `apps/web/resources/js/components/cms/ArticleCard.vue` — FOUND
- [x] `apps/web/resources/js/components/cms/CategoryFilterPill.vue` — FOUND
- [x] `apps/web/resources/js/components/cms/SearchBar.vue` — FOUND
- [x] `apps/web/resources/js/components/cms/CalendarLegend.vue` — FOUND
- [x] `apps/web/resources/js/components/tournaments/bracket-node-dimensions.ts` — FOUND
- [x] `apps/web/resources/js/layouts/PublicLayout.vue` — FOUND (modified, +SearchBar import + insertion)
- [x] `apps/web/lang/en/cms.php` — FOUND (modified, +blog.* + article.*)
- [x] `apps/web/lang/en/events.php` — FOUND (modified, +legend.* + navigation.*)
- [x] `apps/web/lang/en/search.php` — FOUND (modified, +results.section_*/empty_state + header.*)
- [x] `apps/web/tests/Feature/Articles/ArticleShowPageTest.php` — FOUND (modified, RED → 5 GREEN)
- [x] `apps/web/tests/Feature/Events/EventsCalendarPageTest.php` — FOUND (modified, RED → 5 GREEN)
- [x] commit `dc48a5b` — FOUND in git log
- [x] commit `b2c558f` — FOUND in git log

## Self-Check: PASSED
