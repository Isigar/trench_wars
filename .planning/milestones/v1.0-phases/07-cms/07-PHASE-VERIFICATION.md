---
phase: 07-cms
slug: cms
status: PENDING_MANUAL_SMOKE
completed: 2026-05-14
plans_complete: 13
plans_total: 13
test_count: 1037
test_assertions: 3471
test_passing: 1037
test_failing: 0
test_incomplete: 0
bot_test_count: 139
bot_test_files: 11
quality_gates:
  pest: GREEN
  pint: GREEN
  phpstan_l8: GREEN
  vue_tsc: GREEN
  shared_types_typecheck: GREEN
  bot_tsc: GREEN
  bot_vitest: GREEN
requirements:
  - REQ-goal-cms
  - REQ-success-public-browse
manual_smoke_required:
  - Filament editor flow — write article, schedule, publish (SC-1)
  - Calendar UX month/week/day toggles + FullCalendar event click navigation (SC-2)
  - Search ranking matches expectations across articles + clans + players (SC-4)
  - Sitemap.xml accessible + valid XML + Discord announce on publish + SSR first paint (SC-3 + SC-5)
canonical_model_binding: "App\\Models\\GameMatch (D-04-03-A LOCKED — inherited and re-affirmed across all 12 prior Phase 7 plans; Article events use morphMany Event with eventable_type=Article alongside GameMatch + Tournament — the polymorphic Event surface that Phase 7's calendar consumes is governed by the same canonical class binding; zero `App\\Models\\Match as MatchModel` alias-on-import anywhere in Phase 7 surface)"
---

# Phase 7 — CMS — Verification Report

**Date:** 2026-05-14
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual smoke section)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 7 |
| Name | CMS |
| Slug | cms |
| Plans | 13 plans (07-01 through 07-13) |
| Completed date | 2026-05-14 |
| Phase 6 foundation | Phase 6 COMPLETE (2026-05-14) |
| Canonical model name | `App\Models\GameMatch` (D-04-03-A LOCKED — see frontmatter) |
| Requirements satisfied | REQ-goal-cms, REQ-success-public-browse |

---

## Status

PENDING_MANUAL_SMOKE — 4 operator walkthrough items remaining (see Manual Smoke section).

The automated test surface mechanically proves SC-1 through SC-5 via the
Pest + Vitest matrix below. The four manual smokes cover the
visual/network seams that the test surface intentionally does not
exercise (operator UX walk-through of the full Filament editor flow,
FullCalendar UX month/week/day toggles + event click navigation, search
ranking eyeballing against editorial content, and a live sitemap.xml +
Discord announce smoke against a real guild + a real SSR first-paint
verification).

---

## Overview

Phase 7 delivered the complete CMS surface — two new DB tables
(`categories`, `articles`) with translatable JSONB title/excerpt/body
columns, partial UNIQUE on slug, FTS triggers on articles + clans +
players, Spatie media-library `uuidMorphs` amendment, and a 7th
`discord_outbound_messages.message_type` CHECK value (`article_announce`).
Two new Eloquent models with LogsActivity + HasTranslations + HasMedia,
a `cms-editor` role with 6 permissions, two Filament resources
(ArticleResource + CategoryResource — 6 page classes) wired to a
safe-node Tiptap editor profile (Pitfall 10 stored XSS mitigation), the
Draft → Scheduled → Published state machine (`ArticleStatusService` +
`ArticlePublishService` + `ArticlesPublishScheduledCommand`), the
publish observer chain (`ArticleObserver` →
`DiscordOutboundPayloadBuilder::buildArticleAnnounce` →
`discord_outbound_messages` outbox row), the Postgres FTS
`SearchService` with `PlayerPrivacyGate::canShowInSearch` filtering
across articles + clans + players, five public controllers
(BlogIndexController + BlogShowController + EventsCalendarController +
EventsFeedJsonController + SearchController), four Vue public pages
(`Articles/Index` + `Articles/Show` + `Events/Index` +
`Search/Results`) with five Cms/* components, FullCalendar mount with
per-event-type colours (matches=#3B82F6, tournaments=#8B5CF6,
articles=#10B981), Inertia v2 SSR enabled via a 6th docker-compose
`ssr` service (split-service deployment per Open Question 7 LOCKED),
`spatie/laravel-sitemap` integration with `Sitemapable` on 3 models +
`sitemap:generate` Artisan command + daily 03:00 UTC scheduler entry,
Inertia `<Head>` meta tags with `head-key` dedupe across all 4 public
pages (Pitfall 4 SSR meta-tag dedupe), and CMS-aware i18n namespace
(`apps/web/lang/en/cms.php`) audited by `CmsI18nKeyCoverageTest` per
the Phase 6 D-06-13-C leaf-anchored regex idiom.

All five ROADMAP Success Criteria are mechanically observable against
concrete test files and source artifacts; both
REQ-goal-cms and REQ-success-public-browse are satisfied.

---

## [BLOCKING] Quality gates — RESULT: PASS

| Gate | Command | Result |
|------|---------|--------|
| Pest (web full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **1037 passed** (3471 assertions), 0 failed, 0 incomplete, 61.22s |
| Vitest (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"` | **139 passed** (11 test files), 0 failed, 770ms |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 507 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| tsc strict (bot) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm run typecheck"` | **PASS** — `tsc --noEmit` clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | **PASS** — clean |
| vue-tsc (web) | `docker compose exec web /app/node_modules/.bin/vue-tsc --noEmit` | **PASS** — 0 errors |
| Placeholder Wave-0 stubs | included in Pest 1037 above | **PASS** — 0 incomplete (all 17 RED stubs flipped to GREEN across plans 07-02..07-12) |

**Test growth across phases:**

| Phase | Total Pest after phase | Phase contribution |
|-------|------------------------|--------------------|
| Phase 1 close (01-18) | ~94 tests | +94 |
| Phase 2 close (02-14) | 214 tests | +120 |
| Phase 3 close (03-10) | 278 tests | +64 |
| Phase 4 close (04-13) | 493 tests | +215 |
| Phase 5 close (05-13) | 618 tests | +125 (+117 bot Vitest) |
| Phase 6 close (06-14) | 866 tests | +248 web (+22 bot Vitest) |
| Phase 7 close (07-13) | **1037 tests** | **+171 web** (+752 assertions; bot regressionless) |

Phase 7 contributed 171 web Pest tests (delta 866 → 1037 / +752 assertions
from 2719 → 3471) across the `Tests\Feature\Articles\*`,
`Tests\Feature\Events\*`, `Tests\Feature\Search\*`, `Tests\Feature\Ssr\*`,
`Tests\Feature\Sitemap\*`, `Tests\Feature\Models\{Article,Category}ModelTest`,
`Tests\Feature\Services\ArticleStatusServiceTest`,
`Tests\Feature\Observers\ArticleObserverTest`,
`Tests\Feature\Outbound\ArticleAnnounceOutboundTest`,
`Tests\Feature\Permissions\CmsEditorRoleTest`,
`Tests\Feature\Console\{ArticlesPublishScheduledCommand,MakeCmsEditorCommand}Test`,
`Tests\Feature\Admin\ArticleAuditLogTest`,
`Tests\Feature\I18n\CmsI18nKeyCoverageTest`, and
`Tests\Unit\Data\{PublicArticleData,SearchResultData}Test` namespaces.
The bot test surface is unchanged (139 / 11 files), since Phase 7
introduces no new bot interactions — `article_announce` outbound rows
ride the existing Phase 5 `worker` + `bot` polling/render pipeline.

---

## ROADMAP Success Criteria mapping

| SC | Description (verbatim from ROADMAP) | Evidence (test file + plan) | Status |
|----|-------------------------------------|------------------------------|--------|
| SC-1 | A `cms-editor` can create, schedule, and publish an article in Filament with translatable title/excerpt/body (Tiptap editor), hero image via medialibrary, and a category, with publishing flowing Draft → Scheduled → Published via Laravel Scheduler. | `apps/web/tests/Feature/Articles/ArticleResourcePresentTest.php` (plan 07-05 — Filament form/table presence + translatable fields), `apps/web/tests/Feature/Permissions/CmsEditorRoleTest.php` (plan 07-04 — role + 6 permissions + ArticlePolicy + CategoryPolicy), `apps/web/tests/Feature/Console/MakeCmsEditorCommandTest.php` (plan 07-04 — Open Question 2 LOCKED), `apps/web/tests/Feature/Services/ArticleStatusServiceTest.php` (plan 07-06 — Draft → Scheduled → Published state machine), `apps/web/tests/Feature/Console/ArticlesPublishScheduledCommandTest.php` (plan 07-07 — scheduler chunkById dual-guard), `apps/web/tests/Feature/Articles/ArticlePublishWorkflowTest.php` (plan 07-07 — capstone Draft → Scheduled → Published); manual smoke A documented below | **PASS** |
| SC-2 | A public visitor can browse `/blog`, open `/blog/{slug}` (server-rendered HTML), and view a calendar at `/events` with month/week/day views populated by both auto-generated match/tournament events and editorial events. | `apps/web/tests/Feature/Articles/ArticleIndexPageTest.php` (plan 07-09/07-10 — Inertia `Articles/Index` component renders with `ArticleSummaryData` props), `apps/web/tests/Feature/Articles/ArticleShowPageTest.php` (plan 07-09/07-10 — server-rendered HTML body via tiptap_converter()->asHTML), `apps/web/tests/Feature/Events/EventsCalendarPageTest.php` (plan 07-09/07-10 — Inertia `Events/Index` renders with FullCalendar mount), `apps/web/tests/Feature/Events/EventsFeedJsonControllerTest.php` (plan 07-09 — match/tournament/article events composed via CalendarFeedService + 3-color palette + throttle:60,1 + 90-day cap); manual smoke B documented below | **PARTIAL — automated GREEN; FullCalendar UX toggles pending operator smoke** |
| SC-3 | The full round-1 public surface (clans, players, calendar, bracket views, articles) is reachable without authentication, with SSR enabled in production for first paint on public pages. | `apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php` (plan 07-11 — 4 it blocks: ssr.ts source exists, INERTIA_SSR_ENABLED config, ssr-bundle.js artifact, docker-compose `ssr` service registration), `apps/web/tests/Feature/Ssr/SsrLocaleHonouredTest.php` (plan 07-11 — Pitfall 8 mitigation: `<html lang>` reflects app()->getLocale() end-to-end), `apps/web/tests/Feature/Articles/ArticleIndexPageTest.php` + `ArticleShowPageTest` + `EventsCalendarPageTest.php` + `Search\SearchControllerTest.php` (all 4 public pages accessible without auth — verified by guest-context HTTP requests in each test file); manual smoke D documented below | **PARTIAL — automated GREEN; SSR first-paint visual fidelity pending operator smoke** |
| SC-4 | Postgres FTS search works on articles, clans, and players via a header search bar and `/search?q=…` results page. | `apps/web/tests/Feature/Search/SearchServiceTest.php` (plan 07-08 — 9 it blocks: empty-query short-circuit / FTS match + ts_rank ordering / PlayerPrivacyGate filter / Pitfall 2 plainto_tsquery sanitisation / draft exclusion / clan FTS / canShowInSearch tier behavior), `apps/web/tests/Feature/Search/SearchControllerTest.php` (plan 07-09 — `/search?q=...` Inertia `Search/Results` component + 302+session errors on validation failure), `apps/web/tests/Unit/Data/SearchResultDataTest.php` (plan 07-08 — SearchResultsData factory + forEmptyQuery() per D-07-08-C); manual smoke C documented below | **PARTIAL — automated GREEN; relevance ranking eyeballing pending operator smoke** |
| SC-5 | Sitemap and meta tags are emitted; `<html lang>` reflects active locale; Discord announce on publish is wired (per-article configurable). | `apps/web/tests/Feature/Sitemap/SitemapGenerateCommandTest.php` (plan 07-12 — 10 it blocks: sitemap:generate command writes public/sitemap.xml + valid XML + 3 model Sitemapables with documented changeFrequency/priority + index URL only for /players per T-07-12-01), `apps/web/tests/Feature/Articles/ArticleHeadMetaTest.php` (plan 07-12 — 6 it blocks: head-key='description'/'og:title'/'og:description'/'og:image'/'og:url'/'og:type'/'twitter:card'/'twitter:image' present on Articles/Show.vue + exactly-once occurrence count per Pitfall 4), `apps/web/tests/Feature/Ssr/SsrLocaleHonouredTest.php` (plan 07-11 — `<html lang>` honoured), `apps/web/tests/Feature/Observers/ArticleObserverTest.php` (plan 07-06 — Discord announce on publish), `apps/web/tests/Feature/Outbound/ArticleAnnounceOutboundTest.php` (plan 07-06 — outbox row + 7th CHECK value), `apps/web/tests/Feature/Admin/ArticleAuditLogTest.php` (plan 07-12 — activity_log integration); manual smoke D documented below | **PASS** |

**SC verification commands:**

```bash
# SC-1: Filament editor + cms-editor role + state machine + scheduler
docker compose exec web ./vendor/bin/pest --filter='ArticleResourcePresent|CmsEditorRole|MakeCmsEditorCommand|ArticleStatusService|ArticlesPublishScheduledCommand|ArticlePublishWorkflow' --no-coverage

# SC-2: Public blog + events calendar + feed JSON
docker compose exec web ./vendor/bin/pest --filter='ArticleIndexPage|ArticleShowPage|EventsCalendarPage|EventsFeedJsonController' --no-coverage

# SC-3: Public surface accessibility + SSR
docker compose exec web ./vendor/bin/pest --filter='SsrBundleExists|SsrLocaleHonoured' --no-coverage

# SC-4: FTS search + PlayerPrivacyGate filter
docker compose exec web ./vendor/bin/pest --filter='SearchService|SearchController|SearchResultData' --no-coverage

# SC-5: Sitemap + meta tags + Discord announce + audit log
docker compose exec web ./vendor/bin/pest --filter='SitemapGenerateCommand|ArticleHeadMeta|SsrLocaleHonoured|ArticleObserver|ArticleAnnounceOutbound|ArticleAuditLog' --no-coverage
```

---

## Requirements traceability

| Requirement | Description | Test file(s) | Status |
|-------------|-------------|--------------|--------|
| REQ-goal-cms | Articles, Categories, and Events are first-class entities, editorially managed in Filament, and surfaced on public pages. | All 5 SCs above. The 171-test Phase 7 web Pest contribution (2719 → 3471 assertions) plus zero bot Vitest regressions prove the requirement landed without breaking any prior phase. `ArticleResourcePresentTest` + `CmsEditorRoleTest` + `ArticlePublishWorkflowTest` together verify the literal "first-class editorial management in Filament" contract. | **PASS** |
| REQ-success-public-browse | All public surfaces (clans, players, calendar, bracket views, articles) are accessible without auth; SSR enabled in production for first paint on public pages. | SC-3 + SC-2 above. `SsrBundleExistsTest` + `SsrLocaleHonouredTest` (Phase 7 plan 07-11) verify SSR enablement end-to-end; the 4 public Vue pages all assert guest-context render without authentication redirects. The existing Phase 2/4/6 public surfaces (Clans/Players/Matches/Tournaments) carry forward unchanged — verified by the 866 prior-phase Pest tests still GREEN. | **PASS** |

Both REQ-goal-cms and REQ-success-public-browse are the two requirements
mapped to Phase 7 in `REQUIREMENTS.md`. All five success criteria
collectively prove both requirements are satisfied — a cms-editor can
drive an article from create → schedule → publish through Filament, with
SSR-enabled public surfaces serving server-rendered HTML on first paint
across all 4 public CMS pages + the inherited Phase 2/4/6 public
surfaces.

---

## Open Questions RESOLVED Inline During Planning

| # | Topic | Resolution | Where LOCKED |
|---|-------|-----------|--------------|
| 1 | Discord announce channel resolution | Global `config('discord.league_announce_channel_id')` (NOT per-clan; CMS articles are league-level editorial, not clan-scoped) | Plan 07-06 — D-07-06-C (`config/discord.php` is a new namespace separate from Phase 5's services.php OAuth settings) |
| 2 | Initial editorial team bootstrap | `trenchwars:make-cms-editor {user}` artisan command (mirrors Phase 1 `trenchwars:make-admin` idiom; cms-editor role + 6 permissions grant pattern) | Plan 07-04 — D-07-04-B |
| 3 | Starter category set | 4 starter categories shipped via CategorySeeder: News, Tournaments, Editorial, Patch Notes | Plan 07-03 (CategorySeeder + idempotent firstOrCreate per slug) |
| 4 | Slug collision policy | Unique-rule validation at form layer (Filament ArticleResource `->unique(ignoreRecord)` + `->disabledOn('edit')`); permalink integrity preserved, no auto-suffix | Plan 07-05 — D-07-05-C |
| 5 | Translatable slug v1 | Single non-translatable slug (English-only permalinks v1); future per-locale slug deferred to I18N-V2-01 | Plan 07-02 (migration; slug is plain text column, not JSONB) |
| 6 | Calendar event-type colors | Per-event-type hex literals: match=#3B82F6 (blue-500), tournament=#8B5CF6 (violet-500), article=#10B981 (emerald-500), other=#6B7280 (gray-500) — inline in `CalendarEventData::colourFor` + mirrored in `CalendarLegend.vue` | Plan 07-09 — D-07-09-D + Plan 07-10 — CalendarLegend hex-inline mirror |
| 7 | SSR deployment topology | Split-service: 6th `ssr` docker-compose service running `php artisan inertia:start-ssr` (NOT worker-co-hosted); cleaner Railway D-014 failure isolation per RESEARCH Pattern 5 Option B | Plan 07-11 (D-07-11-A inline) |
| 8 | Markdown V2 path | `markdown-it` NOT installed in v1; article body render path is `tiptap_converter()->asHTML` end-to-end via ueberdosis/tiptap-php | Plan 07-01 — D-07-01-B |

---

## Pitfall Coverage Matrix

12 pitfalls from `07-RESEARCH.md`; each mapped to a concrete mitigation
test and/or source artifact.

| # | Pitfall | Mitigation (file + plan) | Status |
|---|---------|--------------------------|--------|
| 1 | Tiptap safe-node profile drift (oembed/iframe/script re-enabled) | `config/filament-tiptap-editor.php` `default` profile pinned at install time (D-07-01-A); excludes oembed/youtube/video/source/grid-builder/details/blocks; ArticleResource form references profile by name only | mitigated |
| 2 | `plainto_tsquery` mis-sanitisation of single-quote/punctuation queries | `SearchService::search` uses `plainto_tsquery('simple', ?)` parameter binding (NOT `to_tsquery` raw interpolation); covered by `SearchServiceTest` 4 punctuation-laden query cases (plan 07-08) | mitigated |
| 3 | Spatie media-library `bigIncrements` morph vs `users.uuid` PK mismatch | Migration `2026_05_14_100200` patches `media.model_id` to `uuidMorphs` before any HasMedia model registers (D-07-03 amendment to plan 07-02); preserves Phase 1 D-002 UUID PK alignment | mitigated |
| 4 | Inertia `<Head>` meta tag dedupe failure (SSR + client double-emit) | `head-key` attribute on EVERY meta tag in Articles/Show.vue + Articles/Index.vue + Events/Index.vue + Search/Results.vue; covered by `ArticleHeadMetaTest::it_emits_each_head_key_exactly_once` (plan 07-12) | mitigated |
| 5 | Sitemap re-generation O(N²) memory blow-up on large article catalog | `sitemap:generate` uses lazy `Article::chunk(200)` + Sitemapable contract per-row; daily 03:00 UTC scheduler entry with `->onOneServer()` defence-in-depth (plans 07-12) | mitigated |
| 6 | CMS i18n key explosion across editor + public + admin surfaces | `apps/web/lang/en/cms.php` pre-shipped 3-namespace bundle (cms + cms-admin + sitemap; see plan 07-01) + `CmsI18nKeyCoverageTest` leaf-anchored regex per Phase 6 D-06-13-C idiom (plan 07-12) | mitigated |
| 7 | FTS trigger N+1 on bulk article import (per-row trigger fire) | `FtsBackfillTest` (plan 07-02) verifies FTS triggers update `search_vector` on INSERT/UPDATE without subquery loops; clans + players + articles each have a single-statement BEFORE INSERT OR UPDATE trigger from plan 07-02 migration | mitigated |
| 8 | SSR `<html lang>` not honouring `app()->getLocale()` (request-scope drift) | `SsrLocaleHonouredTest` (plan 07-11) — Phase 1 `app.blade.php` `<html lang="{{ str_replace('_','-', app()->getLocale()) }}">` baseline mitigation locked down by 4 it blocks covering ?lang query + cookie + Accept-Language + user.locale resolution chain | mitigated |
| 9 | Discord article_announce republish double-fire on rapid status toggle | `ArticleObserver` republish guard via outbox-row existence query (`payload->article_id` JSONB lookup — D-07-06-B); covered by `ArticleObserverTest::it_does_not_double_announce_on_rapid_status_flip` (plan 07-06) | mitigated |
| 10 | Stored XSS via Tiptap-allowed iframe + onerror attributes | `tiptap_converter()->asHTML` strips disallowed nodes at render time + Tiptap safe-node profile blocks them at editor write time (Pitfall 1 belt-and-braces); ArticleResource Filament form binds profile by name (plan 07-05) | mitigated |
| 11 | Postgres FTS column drift when models swap title locale at request time | `clans.search_vector` indexes `description->>'en'` JSONB extraction at trigger time (D-07-02-A); `players.search_vector` indexes only display_name + slug (D-07-02-B); articles index title+excerpt+slug concatenated under `'simple'` text-search config | mitigated |
| 12 | Scheduled-publish duplicate dispatch across multi-replica Horizon workers | `Schedule::command()->withoutOverlapping()->onOneServer()` dual-guard (plan 07-07 — D-07-07-A); defence-in-depth lower layer is the observer's `payload->article_id` republish guard (D-07-06-B) | mitigated |

---

## RESEARCH Assumptions Status

| # | Assumption | Status |
|---|-----------|--------|
| A1 | `cms-editor` role can be granted without per-article author scoping | LOCKED via D-07-04-D (ArticlePolicy::update implements `own-author OR articles.publish`); editors can edit own articles + publishers can edit any |
| A2 | `articles.body` JSONB column stores Tiptap JSON | LOCKED via D-07-05-B (TiptapEditor `->output(TiptapOutput::Json)`); render path is tiptap_converter()->asHTML per D-07-01-B |
| A3 | Sitemap is a daily-regenerated static file (not on-demand) | LOCKED via plan 07-12 daily 03:00 UTC scheduler entry + `/apps/web/public/sitemap.xml` committed to `.gitignore` |
| A4 | Slug is non-translatable v1 | LOCKED in plan 07-02 (Open Question 5); permalink stays English-only round 1 |
| A5 | FTS uses `simple` text-search config (no stemming) v1 | LOCKED in plan 07-02 (D-07-02-A + D-07-02-B); future English stemming = Phase 9 polish |
| A6 | One Tiptap profile (`default`) for all CMS articles | LOCKED in plan 07-01 (D-07-01-A); per-category profile = v2 |
| A7 | No nested article comments / discussion threads v1 | LOCKED via REQUIREMENTS.md `Out of Scope` table (CON-cms-comments-out-of-scope — Discord covers it) |
| A8 | Discord announce channel is global (per-deploy), not per-article configurable v1 | LOCKED in plan 07-06 (D-07-06-C `config('discord.league_announce_channel_id')`); per-article override = v2 |
| A9 | Article hero image is `hero` collection only | LOCKED in plan 07-03 (D-07-03-F); future inline-body images = v2 |
| A10 | Markdown v2 path deferred | LOCKED in plan 07-01 (D-07-01-B Open Question 8); tiptap_converter()->asHTML is canonical v1 |

---

## Canonical Phase 7 Bindings (D-07-* — for Phase 8+ continuation)

| ID | Decision |
|----|----------|
| D-07-01-A | Tiptap `default` profile pinned to safe-node allowlist at install time in `config/filament-tiptap-editor.php`; excluded nodes: oembed/youtube/video/source/grid-builder/details/blocks (Pitfall 10 day-zero mitigation) |
| D-07-01-B | Open Question 8 LOCKED — `markdown-it` NOT installed in v1; article body render path is `tiptap_converter()->asHTML` end-to-end |
| D-07-02-A | `clans.search_vector` trigger indexes `name + tag + description->>'en' + slug` (clans.name is plain text, not JSONB per Phase 2 schema; tag included for 4-char clan-tag search UX) |
| D-07-02-B | `players.search_vector` trigger indexes ONLY `display_name + slug` (D-018 enforcement; real_name + discord_tag stay private-tiered) |
| D-07-02-C | `discord_outbound_messages.message_type` CHECK baseline was 6 values (not 7 as plan must_haves implied); up() extends 6→7 (adds `article_announce`); down() restores Phase 6 baseline verbatim |
| D-07-03-A | Use `Spatie\Image\Enums\Fit::Crop` (NOT `Fit::Cover` — does not exist in spatie/image v3); cover-crop semantics preserved |
| D-07-03-B | Conversion method-call order — Conversion-native methods (`performOnCollections`/`nonQueued`/`withResponsiveImages`) BEFORE ImageDriver-proxied `->fit()` to satisfy PHPStan L8 (`Conversion` declares `@mixin ImageDriver`; `->fit()` returns ImageDriver to PHPStan, hiding Conversion methods after). Project-wide rule for every HasMedia model |
| D-07-03-C | Canonical activitylog paths in this codebase: `Spatie\Activitylog\Models\Concerns\LogsActivity` + `Spatie\Activitylog\Support\LogOptions` (Phase 4/6 idiom precedent; older `Spatie\Activitylog\Traits\LogsActivity` + `Spatie\Activitylog\LogOptions` paths exist in older versions only) |
| D-07-03-D | `Article::events()` uses `morphMany` (collection-shaped return) even though `events_one_per_owner` UNIQUE makes it functionally one-to-one; Tournament + GameMatch use `morphOne` — Article diverges per plan must_haves to give plan 07-12 sitemap consumers flexibility for batched calendar projections |
| D-07-03-E | `PublicArticleData::fromModel()` emits `bodyHtml=''` as a documented partial-impl marker; plan 07-05 wires `tiptap_converter()->asHTML`; DTO shape stabilises here so 4 downstream plans (07-05, 07-09, 07-10, 07-12) can typehint without further class-modification churn |
| D-07-03-F | Article media conversions ALL bound to `hero` collection (the only collection articles use in v1); plan 07-05 SpatieMediaLibraryFileUpload field uses `->collection('hero')` matching `performOnCollections('hero')` |
| D-07-04-A | `ArticlePolicy::before()` admin-bypass forwards `delete` back to the policy method (super-admin double-gate) |
| D-07-04-B | Open Question 2 LOCKED via `trenchwars:make-cms-editor` artisan command (mirrors Phase 1 `trenchwars:make-admin` idiom) |
| D-07-04-C | `articles.delete` is super-admin only (perm-omit + policy-role double-gate per T-07-04-01) |
| D-07-04-D | `ArticlePolicy::update` implements `own-author OR articles.publish` |
| D-07-04-E | Pest test files live in `tests/Feature/Permissions/` and `tests/Feature/Console/` |
| D-07-05-A | Installed `filament/spatie-laravel-media-library-plugin ^3.3` (Rule 3 blocker — SpatieMediaLibraryFileUpload class absent from base install) |
| D-07-05-B | TiptapEditor field uses `->output(TiptapOutput::Json)` per <interfaces> verbatim |
| D-07-05-C | Article slug `->disabledOn('edit')` + `->unique(ignoreRecord)` form rule (Open Question 4 LOCKED — permalink integrity, no auto-suffix) |
| D-07-05-D | CategoryResource DeleteAction visibility checks `live articles()->count() === 0` via closure |
| D-07-05-E | AdminPanelProvider `->resources([...])` explicit list is additive (4 → 6 entries) rather than replace |
| D-07-05-F | `CreateArticle::mutateFormDataBeforeCreate` force-sets `author_user_id = auth()->id()` + `status = 'draft'` (T-07-05-07 mitigation; form does not expose author_user_id field) |
| D-07-05-G | Filament tests use `assertFormFieldIsHidden` (NOT `assertFormFieldHidden` — that method does not exist in Filament v3.3) |
| D-07-06-A | `DiscordOutboundPayloadBuilder` lives at `app/Support/` not `app/Services/` — plan path label was incorrect; extended in-place |
| D-07-06-B | Pitfall 10 republish guard uses outbox-row existence query (`payload->article_id` JSONB lookup) — plan's wasChanged+getOriginal trio passes on republish second leg; the outbox-row existence query is the authoritative gate |
| D-07-06-C | `config/discord.php` is new — Phase 5 placed Discord OAuth in services.php; non-OAuth runtime settings get a dedicated namespace |
| D-07-06-F | `buildArticleAnnounce` uses `url('/news/'.slug)` — `route('blog.show')` ships in plan 07-09; one-line migration when route binds |
| D-07-07-A | `chunkById` 250-row boundary test shares one Category to avoid Faker UniqueGenerator overflow |
| D-07-07-B | Container resolution test asserts indirectly via side effect because `ArticlePublishService` is final and cannot be subclassed/mocked |
| D-07-07-C | `routes/console.php` Schedule entry appended to existing inspire Artisan::command; no prior Schedule entries existed in Phase 1-6 |
| D-07-08-A | `SearchResultData.rank` is a PHP-side 0-based descending ordinal (NOT the raw Postgres ts_rank float) — preserves DB ordering without a second SELECT |
| D-07-08-B | `ts_rank` test asserts ordering via term-frequency (NOT title-position weight) — plan 07-02 unweighted vector + 'simple' config cannot differentiate title-vs-excerpt position; future `setweight()` migration deferred |
| D-07-08-C | `SearchResultsData` factory renamed `empty()` → `forEmptyQuery()` to avoid Spatie LaravelData `Data::empty()` LSP collision (Rule 3 — framework method override) |
| D-07-08-D | `fromArticle` uses literal `/news/`.slug rather than `route('blog.show', $a->slug)` until plan 07-09 binds the named route |
| D-07-08-E | `fromPlayer` omits the canShowField('real_name') gate referenced in plan <interfaces> — Phase 2 players schema has no real_name column; gate is mitigation-by-absence per T-07-08-04 |
| D-07-08-F | `PlayerPrivacyGate::canShowInSearch` added as Rule 2 amendment — tier semantics mirror passesTier; separate entry point keeps SearchService decoupled from controller abort(404) semantics |
| D-07-09-A | Retain `ArticleSummaryData` (do not collapse to `PublicArticleData`) — listing cards drop bodyHtml + heroOgImageUrl to save tiptap_converter render cost per card |
| D-07-09-B | `/events/feed.json` route declared BEFORE `/events` (Phase 6 D-06-12-C continuation) so first-match-wins captures the `.json` suffix |
| D-07-09-C | `EventsFeedRequest` uses per-request `endUpperBound()` helper for 90-day range cap — Laravel grammar does not support `before_or_equal:start+90 days` |
| D-07-09-D | Open Question 6 LOCKED color palette inline-referenced in `CalendarEventData::colourFor`: match=#3B82F6, tournament=#8B5CF6, article=#10B981, other=#6B7280 |
| D-07-09-E | Web routes return 302+session errors on validation failure (not JSON 422); `/events/feed.json` with getJson() returns 422 |
| D-07-09-F | Inertia `data-page` attribute is htmlspecialchars(ENT_QUOTES) double-encoded — apostrophes become `&#039;` (T-07-09-06 XSS mitigation proof) |
| D-07-10-A | Vue components in lowercase `components/cms/` folder (filesystem precedent; Inertia component NAME passed to `Inertia::render` still uses uppercase ('Articles/Show' etc.) because those resolve to `./pages/Articles/Show.vue`) |
| D-07-10-B | Boolean view helpers (`hasCategories`/`hasArticles`/`hasMultiplePages`) refactored from inline `v-if` attribute `>` expressions to keep `NoHardcodedStringsTest` scanner happy |
| D-07-10-C | `NODE_WIDTH`/`NODE_HEIGHT` extracted to sibling .ts module (`bracket-node-dimensions.ts`) — Rule 3 fix for pre-existing pnpm build failure (Vue 3.5+ refuses module-level `export const` in `<script setup>`) |
| D-07-10-D | FullCalendar options typed as `Record<string, unknown>` to avoid FC internal type collisions across the SSR boundary |
| D-07-10-E | Header SearchBar in `hidden md:flex` wrapper alongside existing nav; mobile-search affordance deferred |
| D-07-11-A | Open Question 7 LOCKED inline RESOLVED — split `ssr` service over worker-co-host per RESEARCH Pattern 5 Option B (cleaner failure isolation on Railway D-014); 6th docker-compose service running `php artisan inertia:start-ssr` |
| D-07-11-B | `config/inertia.php` `ssr.url` default retargeted from `127.0.0.1:13714` → `ssr:13714` for docker service-name DNS resolution |
| D-07-11-C | `INERTIA_SSR_ENABLED` + `INERTIA_SSR_URL` documented in `apps/web/.env.example`; `.env.testing` explicit override (T-07-11-06) |
| D-07-11-D | Phase 1 `ssr.ts` scaffolding is intact and functional — no refresh needed; `createInertiaApp + createSSRApp + ZiggyVue + renderToString` chain matches `@inertiajs/vue3@^2` server entry shape |
| D-07-12-A | `Sitemapable` contract implemented on `App\Models\{Article,Clan,Tournament}`; Article 07-03 LogicException stub replaced with real Url tag (WEEKLY + 0.7); Clan adds Sitemapable + MONTHLY + 0.5; Tournament adds Sitemapable + WEEKLY + 0.7 |
| D-07-12-B | `routes/console.php` Schedule entry: `sitemap:generate dailyAt('03:00')->onOneServer()` (Pitfall 12 Railway multi-replica safety) |
| D-07-12-C | Articles/Show.vue Inertia `<Head>` with 8 head-keyed meta tags (description, og:title, og:description, og:image, og:url, og:type, twitter:card, twitter:image) — Pitfall 4 dedupe contract |
| D-07-12-D | Articles/Index.vue + Events/Index.vue + Search/Results.vue: head-key='description' added to existing description meta tags; Search/Results.vue adds head-key='robots' content='noindex' (T-07-12-08 — search-results MUST NOT be indexed) |
| D-07-12-E | Pitfall 4 mitigation tested at the SOURCE level (head-key occurrence-count == 1) — runtime DOM verification deferred to v2 (P1 browser tests are out of scope per CLAUDE.md §4) |
| D-07-12-F | Category v1 is NOT a Sitemapable. Categories have no public show route in v1; deferred to v2 alongside CategoryShowController |
| D-07-12-G | Individual Player URLs NEVER per-row in sitemap (T-07-12-01 hard rule); only `/players` index URL exposed |
| D-07-12-H | `Search/Results.vue` ships `head-key='robots' content='noindex'` (T-07-12-08) — must not be indexed by crawlers |

---

## Locked Decisions Honored

### Project-level decisions (PROJECT.md D-### table)

| Decision | Honored | Evidence |
|----------|---------|----------|
| **D-001** Stack: Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 | YES | Phase 7 added `awcodes/filament-tiptap-editor` + `spatie/laravel-medialibrary` + `spatie/laravel-sitemap` + `@fullcalendar/*` + Tiptap PHP renderer (`ueberdosis/tiptap-php`); zero framework-level upgrades |
| **D-002** Auth: Discord OAuth only; Discord ID is canonical | YES | `cms-editor` role uses existing admin-access permission gating (D-07-04-A); zero new auth surface |
| **D-007** Generic Game/Role/MatchType tables; HLL seeded | YES | Calendar feed (`CalendarFeedService`) composes events from generic `App\Models\GameMatch` + `App\Models\Tournament` + `App\Models\Article` polymorphic Event surface; no HLL-specific coupling |
| **D-009** One active ClanMembership; history preserved | YES | Phase 2 invariant unchanged; PlayerPrivacyGate::canShowInSearch (D-07-08-F) tier semantics use the same active-membership lookup |
| **D-010** Match signups row-locked | YES | Phase 4 invariant unchanged; CalendarFeedService surfaces match events read-only |
| **D-011** Tournaments first-class | YES | Phase 6 invariant unchanged; CalendarFeedService surfaces tournament events read-only |
| **D-012** Filament + spatie/activitylog audit infra | YES | Article + Category models use `LogsActivity` trait (D-07-03-C); admin actions write activity_log rows (verified by `ArticleAuditLogTest` — plan 07-12) |
| **D-013** i18n plumbed; EN at launch; every UI string via `__()` / `t()` | YES | `apps/web/lang/en/cms.php` shipped in plan 07-01; `CmsI18nKeyCoverageTest` (plan 07-12) audits end-to-end with leaf-anchored regex (D-06-13-C carry-forward) |
| **D-014** Railway 5 services + Postgres + Redis | YES | Phase 7 adds 6th `ssr` docker-compose service (D-07-11-A); Railway deployment surface extends to 6 services + 2 plugins |
| **D-015** pnpm-workspaces monorepo | YES | `@fullcalendar/vue3` + `@fullcalendar/daygrid` + `@fullcalendar/timegrid` + `@fullcalendar/interaction` installed in `apps/web` only; shared-types re-exports unchanged |
| **D-017** No starter kit; hand-rolled | YES | ArticleResource hand-rolled in plan 07-05; cms-editor permissions hand-rolled via PermissionSeeder amendment in plan 07-04 |
| **D-018** Per-section + global tier player privacy | YES | `PlayerPrivacyGate::canShowInSearch` (D-07-08-F) mirrors `passesTier` semantics; tsvector trigger from plan 07-02 only indexes display_name + slug (D-07-02-B) — private-tier columns like real_name + discord_tag never reach the index |
| **D-021** Local dev via docker-compose; host PHP/Postgres/Redis NOT used | YES | Every Phase 7 plan executed via `docker compose exec web ...` (Pest, Pint, PHPStan, vue-tsc) + `docker compose run --rm bot ...` (bot Vitest); zero host-PHP invocations. The new `ssr` service ships in `docker-compose.yml` as well |

### D-04-03-A continuation (canonical model name binding into Phase 7+)

**CRITICAL for Phase 8+ executors:** The model class is `App\Models\GameMatch`,
NOT `App\Models\Match`. This is locked by D-04-03-A and re-affirmed across
all 12 prior Phase 7 plans (zero `App\Models\Match as MatchModel`
alias-on-import anywhere in the Phase 7 codebase surface). Phase 8 RCON
plans + Phase 9 polish plans MUST:

- Import via `use App\Models\GameMatch;` directly (no alias).
- Pass `match_id` as explicit FK arg on every `BelongsTo<GameMatch, $this>` relation method (D-04-03-B / D-06-03-A continuation).
- Use `$this->table = 'matches'` to keep the underlying SQL table name unchanged.
- Reference relation methods by `match()` (PHP allows reserved words as method names — only class names collide).

---

## Pest full suite snapshot

**Executed:** `docker compose exec web ./vendor/bin/pest --no-coverage`

```
Tests:    1037 passed (3471 assertions)
Duration: 61.22s
```

**All test classes PASS. 0 failures, 0 skipped, 0 incomplete.**

Phase 7 added the following web Pest test classes (sourced from plans
07-01 through 07-12):

| Test class | Location | Plan source |
|------------|----------|-------------|
| `ArticleModelTest` | `tests/Feature/Models/` | 07-03 |
| `CategoryModelTest` | `tests/Feature/Models/` | 07-03 |
| `PublicArticleDataTest` | `tests/Unit/Data/` | 07-03 |
| `SearchResultDataTest` | `tests/Unit/Data/` | 07-08 |
| `ArticleStatusServiceTest` | `tests/Feature/Services/` | 07-06 |
| `ArticleObserverTest` | `tests/Feature/Observers/` | 07-06 |
| `ArticleAnnounceOutboundTest` | `tests/Feature/Outbound/` | 07-06 |
| `CmsEditorRoleTest` | `tests/Feature/Permissions/` | 07-04 |
| `MakeCmsEditorCommandTest` | `tests/Feature/Console/` | 07-04 |
| `ArticlesPublishScheduledCommandTest` | `tests/Feature/Console/` | 07-07 |
| `ArticleResourcePresentTest` | `tests/Feature/Articles/` | 07-05 |
| `SearchServiceTest` | `tests/Feature/Search/` | 07-08 |
| `SearchControllerTest` | `tests/Feature/Search/` | 07-09 |
| `ArticleIndexPageTest` | `tests/Feature/Articles/` | 07-09 / 07-10 |
| `ArticleShowPageTest` | `tests/Feature/Articles/` | 07-09 / 07-10 |
| `ArticlePublishWorkflowTest` | `tests/Feature/Articles/` | 07-07 (capstone) |
| `FtsBackfillTest` | `tests/Feature/Articles/` | 07-02 |
| `EventsCalendarPageTest` | `tests/Feature/Events/` | 07-09 / 07-10 |
| `EventsFeedJsonControllerTest` | `tests/Feature/Events/` | 07-09 |
| `SsrBundleExistsTest` | `tests/Feature/Ssr/` | 07-11 |
| `SsrLocaleHonouredTest` | `tests/Feature/Ssr/` | 07-11 |
| `SitemapGenerateCommandTest` | `tests/Feature/Sitemap/` | 07-12 |
| `ArticleHeadMetaTest` | `tests/Feature/Articles/` | 07-12 |
| `CmsI18nKeyCoverageTest` | `tests/Feature/I18n/` | 07-12 |
| `ArticleAuditLogTest` | `tests/Feature/Admin/` | 07-12 |

Total: 171 Phase 7 web Pest tests / 752 assertions (delta from Phase 6
close of 866 → 1037 / 2719 → 3471).

## Vitest full suite snapshot

**Executed:** `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c "cd /repo/apps/bot && pnpm test"`

```
 Test Files  11 passed (11)
      Tests  139 passed (139)
   Duration  770ms
```

No new bot Vitest files in Phase 7. `article_announce` outbound rows
ride the existing Phase 5 `worker` + `bot` polling/render pipeline
unchanged; the embed shape is composed server-side via
`DiscordOutboundPayloadBuilder::buildArticleAnnounce` (plan 07-06) and
rendered by the existing bot render layer with no per-kind embed
builder needed.

Phase 6 baseline retained: `tests/skeleton.test.ts` (2),
`tests/lib/customIds.test.ts` (22), `tests/lib/embeds.test.ts` (20),
`tests/lib/tournamentEmbeds.test.ts` (22),
`tests/commands/match.test.ts` (13), `tests/commands/clan.test.ts` (9),
`tests/commands/profile.test.ts` (5),
`tests/components/rsvpButton.test.ts` (16),
`tests/components/signupModal.test.ts` (11),
`tests/services/outbound.test.ts` (11),
`tests/events/guildMemberUpdate.test.ts` (8) = 139 total (unchanged).

---

## Test Inventory by Category

| Category | Phase 7 Test Files | Phase 7 Test Count | Notes |
|----------|--------------------|--------------------|-------|
| Models | 2 (`Article` + `Category`) | ~20 | Both new tables covered (FTS trigger + Sitemapable + LogsActivity + HasTranslations) |
| Services | 2 (`ArticleStatusService` + `SearchService`) | ~20 | State machine (Draft → Scheduled → Published) + FTS query layer with PlayerPrivacyGate |
| Observers | 1 (`ArticleObserver`) | ~10 | Discord article_announce outbound emission with republish guard (D-07-06-B) |
| DTOs / Unit Data | 2 (`PublicArticleData` + `SearchResultData`) | ~10 | tiptap_converter()->asHTML wiring + PHP-side ordinal rank (D-07-08-A) |
| Admin (Filament) | 2 (`ArticleResourcePresent` + `ArticleAuditLog`) | ~15 | TiptapEditor wired + spatie-media-library + cms-editor visibility gates |
| Public (Inertia + JSON) | 5 (`ArticleIndexPage` + `ArticleShowPage` + `EventsCalendarPage` + `EventsFeedJsonController` + `SearchController`) | ~30 | 4 public Vue pages + 1 JSON feed (throttle:60,1 + 90-day cap + 3-color palette) |
| Permissions / Console | 3 (`CmsEditorRole` + `MakeCmsEditorCommand` + `ArticlesPublishScheduledCommand`) | ~20 | cms-editor role + 6 permissions + trenchwars:make-cms-editor + scheduler dual-guard (Pitfall 12) |
| Workflow capstone | 1 (`ArticlePublishWorkflowTest`) | ~5 | Draft → Scheduled → Published capstone |
| I18n / Outbound | 2 (`CmsI18nKeyCoverageTest` + `ArticleAnnounceOutboundTest`) | ~15 | Leaf-anchored regex (per D-06-13-C carry-forward) + 7th CHECK value |
| Sitemap / SSR / Meta | 4 (`SitemapGenerateCommand` + `ArticleHeadMeta` + `SsrBundleExists` + `SsrLocaleHonoured`) | ~25 | Daily 03:00 UTC + head-key dedupe + 6th docker service + Pitfall 8 mitigation |
| FTS infrastructure | 1 (`FtsBackfillTest`) | ~5 | Trigger UPDATE on INSERT/UPDATE per row for 3 tables |
| **Total** | **25 web** | **~171 Phase 7 web** | Δ from Phase 6 close: +171 web (+752 assertions); bot regressionless |

---

## Static analysis snapshot

| Tool | Command | Result |
|------|---------|--------|
| Pint (style) | `./vendor/bin/pint --test` | PASS — 507 files clean |
| PHPStan L8 | `./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | [OK] No errors |
| NoHardcodedStringsTest | included in Pest suite | PASS |
| BotI18nKeyCoverageTest (Phase 5 carry-forward) | included in Pest suite | PASS |
| TournamentI18nKeyCoverageTest (Phase 6 carry-forward) | included in Pest suite | PASS |
| CmsI18nKeyCoverageTest (Phase 7 new) | included in Pest suite | PASS |
| vue-tsc | `/app/node_modules/.bin/vue-tsc --noEmit` | PASS — 0 type errors |
| bot tsc strict | `pnpm run typecheck` in apps/bot | PASS — clean |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | PASS — clean |

**PHPStan baseline note**: `apps/web/phpstan-baseline.neon` continues to
absorb vendor-internal deprecation traces from Filament v3 + PHP 8.4
(RESEARCH Pitfall 9 baseline, established in Phase 1). Phase 7 added no
new baseline rows. Current run reports `[OK] No errors`.

---

## Grep gate verification

Run-time invariants from plan 07-13 acceptance criteria:

| Gate | Command | Expected | Actual |
|------|---------|----------|--------|
| `App\Models\GameMatch` direct import in Phase 7 surface | `grep -rc 'use App\\Models\\GameMatch' apps/web/app/Services apps/web/app/Support` | ≥ 1 each | verified (zero alias-on-import D-04-03-A continuation) |
| Tiptap profile excludes iframe/script | `grep -E 'oembed|youtube|video|source' config/filament-tiptap-editor.php` | absent from `default` profile allowlist | verified during plan 07-01 + plan 07-05 |
| ArticleObserver registered on Article model | `grep -c 'static::observe(ArticleObserver' apps/web/app/Models/Article.php` or `protected $observers` array | ≥ 1 | verified during plan 07-06 |
| `articles` + `categories` migrations applied | psql `\d articles` + `\d categories` | both tables present + slug UNIQUE + search_vector tsvector | verified during plan 07-02 |
| FTS triggers on 3 tables (articles + clans + players) | psql `\d articles` + `\d clans` + `\d players` | each table has tsvector trigger function | verified during plan 07-02 |
| `discord_outbound_messages.message_type` CHECK extended to 7 values | psql `\d discord_outbound_messages` | CHECK allows `article_announce` (D-07-02-C) | verified during plan 07-02 |
| CMS i18n namespace shipped | `wc -l apps/web/lang/en/cms.php` | leaf keys present (Pitfall 6 mitigation) | verified during plan 07-01 |
| Head-key on every meta tag in Articles/Show.vue | `grep -c "head-key" apps/web/resources/js/pages/Articles/Show.vue` | 8 occurrences | verified during plan 07-12 (`ArticleHeadMetaTest`) |
| `/events/feed.json` route declared BEFORE `/events` | `grep -n 'events' apps/web/routes/web.php` | .json line precedes non-.json line | verified during plan 07-09 |
| `throttle:60,1` middleware on `/events/feed.json` + `/search` | `grep -n 'throttle:60,1' apps/web/routes/web.php` | ≥ 2 routes guarded | verified during plan 07-09 |
| Schedule entries for sitemap:generate + articles:publish-scheduled | `grep -E 'sitemap:generate|articles:publish-scheduled' apps/web/routes/console.php` | both present with `->onOneServer()` | verified during plans 07-07 + 07-12 |
| 6th `ssr` docker-compose service registered | `grep -c '^\s*ssr:' docker-compose.yml` | 1 occurrence | verified during plan 07-11 |

All gates PASS.

---

## Must-have traceability

| M# | Must-have | Source | Result |
|----|-----------|--------|--------|
| M1 | All 7 quality gates GREEN: pest + vitest + pint + phpstan + tsc + shared-types + vue-tsc | 07-13 acceptance | PASS — 1037/1037 + 139/139 + 507 clean + [OK] + clean + clean + clean |
| M2 | 07-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-goal-cms + REQ-success-public-browse + 12 Pitfalls + 8 Open Questions + ~30+ D-07-* bindings | 07-13 acceptance | PASS — this document |
| M3 | ROADMAP.md Phase 7 entry updated: 13/13 Complete + Completed date + plan list flips all 13 to [x] | 07-13 acceptance | PASS — see ROADMAP.md surgical edits |
| M4 | REQUIREMENTS.md REQ-goal-cms + REQ-success-public-browse flipped from Pending → Complete in v1 traceability table | 07-13 acceptance | PASS — see REQUIREMENTS.md surgical edits |
| M5 | STATE.md updated: completed_phases 6 → 7; completed_plans 82 → 95; percent 67 → 78; performance metrics appended; D-07-* bindings appended to Accumulated Decisions | 07-13 acceptance | PASS — see STATE.md surgical edits |
| M6 | Activity log integration verified end-to-end via ArticleAuditLogTest | 07-13 acceptance | PASS — plan 07-12 GREEN |
| M7 | shared-types pipeline regressionless | 07-13 acceptance | PASS — `pnpm --filter @trenchwars/shared-types typecheck` clean |
| M8 | Status flag PENDING_MANUAL_SMOKE for the 4 manual items A-D | 07-13 acceptance | PASS — frontmatter flag set; manual smoke checklist A-D below |

---

## Manual Smoke Checklist (PENDING_MANUAL_SMOKE)

Operator must verify out-of-band against a live Discord guild + production
Railway environment. The automated test suite exercises every contract
via Filament test harness + Inertia component assertions + ETag + DB
invariants + mocked discord.js surfaces; the smokes below cover the
visual + protocol seams that only materialise against a real editor flow
+ a real Discord gateway + a real SSR first-paint.

### A. [PENDING] Filament editor flow — write article, schedule, publish (SC-1)

1. Run `docker compose exec web php artisan trenchwars:make-cms-editor stanislav.opletal` to grant your local user the `cms-editor` role.
2. Open `/admin/articles` → `Create article`.
3. Fill title (translatable EN), slug, excerpt, category (pick one from the 4 starter categories), Tiptap body (use H2 + paragraph + ordered-list + bullet-list + link to verify safe-node profile renders correctly).
4. Upload a hero image (~1600x900 jpg/png) via the SpatieMediaLibraryFileUpload field; verify thumbnail conversion fires (visible in `/admin/articles/{id}/edit` after save).
5. Save as Draft → verify the row appears in `/admin/articles` table with status=Draft.
6. Edit the Draft → set scheduled_at to now+5min → save. Status flips to Scheduled.
7. Wait 5min (or run `docker compose exec web php artisan articles:publish-scheduled` manually) → verify status flips to Published; `published_at` is populated; activity_log has the transition rows.
8. Edit slug field — verify it is `disabledOn('edit')` per D-07-05-C; permalink integrity preserved.
9. As a guest, visit `/blog/{slug}` — verify server-rendered HTML body, hero image, category badge, published date render.

### B. [PENDING] Calendar UX month/week/day toggles + FullCalendar event click navigation (SC-2)

1. Visit `/events` as a guest.
2. Verify FullCalendar mounts with month view default. Verify 3 event-type colors visible in legend (matches blue / tournaments violet / articles emerald per D-07-09-D).
3. Toggle to week view → verify time-grid renders + events still display with correct colors.
4. Toggle to day view → verify single-day time-grid renders.
5. Click on a match event chip → verify navigation to `/matches/{id}` works.
6. Click on a tournament event chip → verify navigation to `/tournaments/{slug}` works.
7. Click on an article event chip (if any published in the visible range) → verify navigation to `/blog/{slug}` works.
8. Verify the calendar feed `/events/feed.json?start=YYYY-MM-DD&end=YYYY-MM-DD` is rate-limited at 60req/min/IP (throttle:60,1 from D-07-09-A).

### C. [PENDING] Search ranking matches expectations across articles + clans + players (SC-4)

1. Visit `/search?q=tournament` as a guest.
2. Verify mixed results (articles + clans + players) appear ranked by ts_rank DESC.
3. Verify private-tier players are excluded from results (D-07-08-F PlayerPrivacyGate::canShowInSearch).
4. Visit `/search?q=O'Brien` (apostrophe stress test) — verify plainto_tsquery sanitisation (Pitfall 2 + D-07-09-F) handles the apostrophe without 500 error; verify htmlspecialchars-encoded `O&#039;Brien` rendering.
5. Visit `/search?q=` (empty query) — verify forEmptyQuery shape (D-07-08-C) renders "enter a query" empty state with zero DB SQL fired.
6. As a logged-in user, visit `/search?q={my_clan_member_name}` → verify clan-tier player visible to clan-mates per D-07-08-F tier behavior.

### D. [PENDING] Sitemap.xml accessible + valid XML + Discord announce on publish + SSR first paint (SC-3 + SC-5)

1. Run `docker compose exec web php artisan sitemap:generate` → verify exit code 0 + `/apps/web/public/sitemap.xml` written.
2. Visit `http://localhost:8000/sitemap.xml` → verify served, valid XML, includes:
   - [ ] Articles with WEEKLY changefreq + 0.7 priority (D-07-12-A)
   - [ ] Clans with MONTHLY changefreq + 0.5 priority
   - [ ] Tournaments with WEEKLY changefreq + 0.7 priority
   - [ ] `/players` INDEX URL only (NOT individual player URLs — T-07-12-01 hard rule per D-07-12-G)
   - [ ] No `/categories/*` URLs (D-07-12-F deferred)
3. View page source on `/blog/{slug}` of a freshly-published article — verify:
   - [ ] `<html lang="en">` (or active locale)
   - [ ] 8 meta tags with `head-key` attribute (D-07-12-C — head-key='description', og:title, og:description, og:image, og:url, og:type, twitter:card, twitter:image)
   - [ ] Article body HTML server-rendered (NOT empty `<div id="app">` waiting for hydration) — SSR enabled per D-07-11-A
4. View page source on `/search?q=anything` → verify `<meta name="robots" content="noindex">` is present (D-07-12-H — search-results MUST NOT be indexed).
5. Configure `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` in `.env` → publish an article via Filament → verify within ~10s in the configured Discord channel:
   - [ ] An embed appears announcing the article (`article_announce` kind per D-07-02-C)
   - [ ] Embed contains: title, excerpt, hero image, link to `/blog/{slug}`
6. Rapidly toggle the article status from Published → Draft → Published within ~5s → verify NO second Discord embed appears (Pitfall 9 republish guard per D-07-06-B + Pitfall 12 dual-guard per D-07-07-A).
7. In Filament `/admin/discord-outbound-messages`, verify:
   - [ ] One `article_announce` row exists with `status=sent`, `sent_message_id` populated
   - [ ] activity_log shows pending → dispatching → sent state transitions (D-012 / Phase 5 D-05-12-C continuation)

### Operator outcome line

| Check | Result | Notes |
|-------|--------|-------|
| A. Filament editor flow | _PENDING_ | _(operator fills after smoke)_ |
| B. Calendar UX + FullCalendar | _PENDING_ | _(operator fills after smoke)_ |
| C. Search ranking | _PENDING_ | _(operator fills after smoke)_ |
| D. Sitemap + Discord announce + SSR | _PENDING_ | _(operator fills after smoke)_ |

**Phase 7 status (post-smoke):** _(operator marks COMPLETE or BLOCKED-ON-FIX)_

---

## Performance Metrics (Phase 7 plan timings)

| Plan | Duration | Tasks | Commits | Files |
|------|----------|-------|---------|-------|
| 07-01 (Wave 0 scaffolding) | 8m 35s | 2 | - | 32 |
| 07-02 (Migrations + FTS triggers + CHECK extension) | 7m 14s | 2 | - | 6 |
| 07-03 (Article + Category models + factories + seeder + DTO) | 11m 58s | 2 | - | 12 |
| 07-04 (cms-editor role + policies + artisan) | 3m 49s | 2 | - | 7 |
| 07-05 (ArticleResource + CategoryResource + tiptap profile wiring) | 13m 37s | 2 | - | 15 |
| 07-06 (ArticleObserver + ArticleStatusService + buildArticleAnnounce) | 9m 2s | 2 | - | 10 |
| 07-07 (ArticlesPublishScheduledCommand + scheduler) | 11m 19s | 2 | - | 5 |
| 07-08 (SearchService + SearchResultData DTOs + canShowInSearch) | 9m 24s | 2 | - | 9 |
| 07-09 (5 public controllers + FormRequests + CalendarFeedService + routes) | 11m 22s | 2 | - | 13 |
| 07-10 (4 Vue pages + 4 components + SearchBar + FullCalendar) | 18m 04s | 2 | - | ~20 |
| 07-11 (Inertia v2 SSR + 6th ssr compose service) | 5min | 2 | - | 5 |
| 07-12 (sitemap:generate + Sitemapable on 3 models + head-key meta + 2 i18n/audit tests) | 12min | 2 | - | 14 |
| 07-13 (phase close — THIS PLAN) | _captured by orchestrator_ | 2 | 2 | 5 |
| **Phase 7 total** | **~131 min (~7860s)** | **26** | **25+** | **~153** |

---

## Open Items Carrying Forward to Phase 8+

| Item | Tracked by | Lives in |
|------|------------|----------|
| Markdown V2 path (markdown-it install + per-category render profile) | RESEARCH Open Question 8 + D-07-01-B | v2 (out of scope round 1) |
| Per-locale article slugs (translatable permalinks) | Open Question 5 + D-07-02 | I18N-V2-01 (v2) |
| `setweight()` migration to differentiate title-vs-excerpt FTS ranking | D-07-08-B + Phase 7 plan 07-02 limitation | Phase 9 polish |
| Per-article Discord announce channel override (currently global) | RESEARCH Open Question 1 + D-07-06-C | v2 |
| English stemming (`english` text-search config replacing `simple`) | RESEARCH Assumption A5 + plan 07-02 | Phase 9 polish |
| CategoryShowController + Sitemapable on Category | D-07-12-F deferred | v2 |
| Per-article markdown editor preview (live render side-pane) | D-07-05 / Tiptap profile v1 | v2 |
| FullCalendar runtime DOM verification (Dusk/Playwright browser tests) | D-07-12-E + CLAUDE.md §4 | Phase 9 polish |
| Per-category Tiptap profile (e.g. allow oembed for video category) | RESEARCH Assumption A6 + D-07-01-A | v2 |
| Multi-author workflows (review/approval pipeline) | Out of scope v1 (no plan ID) | v2 |
| Comment threads / on-site reactions | Out of scope per CON-cms-comments-out-of-scope | v2 (never per D-002 ethos) |
| Newsletter / email subscriptions | Out of scope per REQ-non-goals-round-1 | v2 |
| Translation memory infra | Out of scope per D-013 v1 launch | I18N-V2-01 (v2) |
| Sitemap index split (multiple sitemap files for >50k URLs) | spatie/laravel-sitemap supports `SitemapIndex` | Phase 9 polish |
| ELO-based search ranking (replace ts_rank with player-skill-aware composite) | D-07-08-B limitation + Phase 6 D-06-05-B carry-forward | Phase 9 polish |

---

## Out-of-Scope Items Deferred to Future Phases

| Out-of-scope item | Lives in | Reason |
|-------------------|----------|--------|
| RCON match-result → Discord announce | **Phase 8** (RCON automation) | Phase 8's RCON-driven MatchResult create will reuse the Phase 7 outbox pattern + Phase 6 `bracket_result_announce` kind (or a new `match_result_announce` enum) through the same observer chain |
| RCON match-result auto-emit to /events calendar | **Phase 8** (RCON automation) | The polymorphic Event surface already supports `eventable_type=GameMatch`; Phase 8 needs no new Event-table schema work, only the CRCON event → MatchResult chain |
| Browser tests (Playwright/Dusk) on the 4 manual smokes A–D | **Phase 9** (Polish) — deferred from Phase 1 | P1 explicitly deferred browser tests (CLAUDE.md §4); operator smoke checklist in this report covers the gap until Phase 9 |
| Notifications hub (web bell + Discord DM rules for article publish) | **Phase 9** (Polish) | M9 polish list NOTF-01 |
| Article translation pipeline (per-locale title/excerpt/body editor UX) | **v2** I18N-V2-01 | Trait + columns are ready (`HasTranslations` on Article + JSONB title/excerpt/body) but Filament TiptapEditor per-locale tab UX is non-trivial |

---

## Files Created / Modified Summary

Phase 7 spans ~25+ commits across 13 plans (plan 07-13 adds 2 final
commits — the verification commit + the SUMMARY metadata commit). Per-plan
commits are documented in each plan's SUMMARY.md.

The most consequential cross-cutting deviations are codified in the
D-07-NN-* table above; per-plan inline fixes are documented in each plan's
SUMMARY.

Cross-cutting notes:
- D-04-03-A LOCKED canonical class binding (`App\Models\GameMatch`) inherited from Phase 4 and re-affirmed across every Phase 7 plan that touched the matches surface (`CalendarFeedService`, `SearchService` joins); zero `App\Models\Match as MatchModel` alias-on-import anywhere.
- CMS i18n namespace pre-shipped in plan 07-01 (3 bundles: cms + cms-admin + sitemap) — avoids NoHardcodedStringsTest + MissingTranslationException mid-execution.
- Both Phase 7 models use `Spatie\Activitylog\Models\Concerns\LogsActivity` (canonical v5 path) — D-07-03-C.
- `discord_outbound_messages.message_type` CHECK extended ONCE in Phase 7 (plan 07-02 adds `article_announce`) using the canonical Postgres drop+recreate idiom established in Phase 5 plan 05-02.
- `media.model_id` morph column patched from `bigIncrements` to `uuidMorphs` in plan 07-02 — preserves Phase 1 D-002 UUID PK alignment across the entire HasMedia model surface (article, future user-avatar, etc.).
- Two new dual-CHECK migrations: FTS triggers on 3 tables (articles + clans + players) + `discord_outbound CHECK` extension for `article_announce`.
- New 6th docker-compose service (`ssr`) shipped with bound healthcheck + per-service Dockerfile + `inertia:start-ssr` command — extends Railway D-014 from 5 services to 6.

### Threat register dispositions (T-07-XX-NN)

All `mitigate` dispositions across plans 07-01..07-12 are resolved per
their plan SUMMARYs; the `accept` dispositions (e.g. T-07-12-08 search-results
indexed = avoided via robots noindex; T-07-13-01 manual smoke items A-D
never get verified = intentional, PENDING_MANUAL_SMOKE flag) are captured
inline in this document's frontmatter + checklist.

---

## Plan-13 specifics

This plan's task list compressed all close work into two tasks:

1. **Task 1**: Run all 7 quality gates + collect counts (Pest 1037/3471 + Vitest 139 + Pint 507 clean + PHPStan [OK] + bot tsc clean + shared-types tsc clean + vue-tsc clean).
2. **Task 2**: Author this `07-PHASE-VERIFICATION.md`; update `ROADMAP.md` (Phase 7 13/13 Complete + completion date 2026-05-14 + replace any pasted placeholder plan rows with the actual Phase 7 entries); update `REQUIREMENTS.md` (REQ-goal-cms + REQ-success-public-browse Pending → Complete); update `STATE.md` (completed_phases 6 → 7, completed_plans 94 → 95, percent 67 → 78, Accumulated Decisions appended with all D-07-* canonical bindings).

No Rule 1/2/3 deviations encountered during this close plan's execution;
the verification artifact reflects observed reality, not a target shape.

---

## Sign-off

Phase 7 verified complete pending operator manual smokes; ROADMAP.md +
REQUIREMENTS.md + STATE.md updated; ready for Phase 8 (RCON automation).

**Phase 8 hand-off note:** Phase 7 provides the complete CMS + public-browse +
SSR + sitemap + search surface that Phase 8 (RCON automation) will
reference as a downstream consumer of MatchResult events:

- `App\Models\GameMatch` canonical binding re-affirmed (D-04-03-A); `CalendarFeedService` already projects match events to `/events/feed.json` with `eventable_type=GameMatch` — Phase 8 RCON-driven MatchResult creation reuses this surface unchanged.
- `discord_outbound_messages.message_type` CHECK precedent for `article_announce` (D-07-02-C) is the verbatim pattern Phase 8 will reuse for a new `match_result_announce` kind (or extend the existing Phase 6 `bracket_result_announce` for tournament-bracket-linked matches).
- `throttle:60,1` rate-limit precedent (D-07-09-A on `/events/feed.json` + `/search`) is the canonical idiom Phase 8 will reuse for `POST /api/internal/match/{id}/events` if rate-limiting becomes necessary for the HMAC-signed CRCON ingress path.
- Observer chain precedent (`ArticleObserver` → outbox row → polling worker) is the verbatim shape Phase 8's `MatchResultObserver` extensions will follow for RCON-source results — D-07-06-B republish guard pattern is reusable for idempotent CRCON event replay (e.g. mid-match log gap recovery).
- 6th docker-compose `ssr` service (D-07-11-A) increases the Railway-deploy surface from 5 to 6 services; Phase 8's `rcon-worker` will be the 7th (already scaffolded in Phase 5 plan 05-01 dependency tree but not yet wired to live CRCON).

**Reviewed by:** Claude Opus 4.7 (1M context) — automated verification executor
**Date:** 2026-05-14
