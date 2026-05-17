---
phase: 07-cms
plan: 01
subsystem: scaffold
tags:
  - wave-0
  - composer-deps
  - npm-deps
  - factory-stubs
  - red-stubs
  - i18n-skeleton
  - tiptap-safe-profile
  - pitfall-10
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Factory stub + bare functional Pest idioms (D-06-01-A/B)
    - .planning/phases/06-tournaments-brackets/06-14-SUMMARY.md  # Phase 6 verification gate — Phase 7 starts on a green baseline
  provides:
    - "4 composer deps installed in apps/web at pinned major.minor: spatie/laravel-medialibrary 11.22.1, awcodes/filament-tiptap-editor v3.5.16, ueberdosis/tiptap-php 2.1.0, spatie/laravel-sitemap 8.1.0"
    - "5 npm devDependencies installed in apps/web: @fullcalendar/{core,vue3,daygrid,timegrid,interaction} @ ^6.1.20"
    - "config/media-library.php published with default disk='public', max_file_size=10MB"
    - "config/filament-tiptap-editor.php published + 'default' profile pinned to safe-node allowlist (Pitfall 10 mitigation — iframe/script/oembed/youtube/video/source/grid-builder/details/blocks all EXCLUDED)"
    - "create_media_table migration published (NOT yet migrated — plan 07-02 owns the migrate run)"
    - "2 factory stubs (Article + Category) using Phase 6 D-06-01-A verbatim idiom — string FQN $model + per-line @phpstan-ignore; plan 07-03 swaps in real generics"
    - "17 Pest RED stubs covering Articles / Events / Search / Sitemap / I18n / Observers / Outbound / Ssr / Admin / Unit Data — replaced by plans 07-02..07-12"
    - "apps/web/lang/en/cms.php — full Phase 7 CMS i18n namespace skeleton (status × 3 × 2, actions × 3 × 3, fields × 9 × 2, errors × 4, empty × 4 ≈ 50 leaf keys)"
    - "apps/web/lang/en/events.php — calendar UI namespace (header × 5, types × 3, empty, page meta ≈ 15 leaf keys)"
    - "apps/web/lang/en/search.php — header search + results page namespace (placeholder, results × 3, sections × 3, page meta ≈ 10 leaf keys)"
    - "apps/web/lang/en/admin.php extended with article + category resource blocks (Phase 4 in-place idiom; existing keys untouched)"
    - ".env.example documents INERTIA_SSR_ENABLED=false (flipped true in prod by plans 07-09..07-11)"
    - "Environment prereqs verified: gd extension present (medialibrary OS dep); storage:link created + verified"
  affects:
    - apps/web/composer.json                       # 4 new deps pinned
    - apps/web/composer.lock                       # locked hash chain
    - apps/web/package.json                        # 5 new devDeps
    - apps/web/pnpm-lock.yaml                      # locked
    - apps/web/config/                             # media-library.php + filament-tiptap-editor.php published + pinned
    - apps/web/database/migrations/                # 1 new published migration (NOT yet migrated)
    - apps/web/database/factories/                 # 2 new factory files
    - apps/web/lang/en/                            # 3 new namespaces + admin.php extension
    - apps/web/tests/Feature/Articles/             # 6 new RED stubs (new directory)
    - apps/web/tests/Feature/Events/               # 2 new RED stubs (new directory)
    - apps/web/tests/Feature/Search/               # 2 new RED stubs (new directory)
    - apps/web/tests/Feature/Sitemap/              # 1 new RED stub (new directory)
    - apps/web/tests/Feature/Outbound/             # 1 new RED stub (new directory)
    - apps/web/tests/Feature/Ssr/                  # 1 new RED stub (new directory)
    - apps/web/tests/Feature/I18n/                 # 1 new RED stub (existing dir)
    - apps/web/tests/Feature/Observers/            # 1 new RED stub (existing dir)
    - apps/web/tests/Feature/Admin/                # 1 new RED stub (existing dir)
    - apps/web/tests/Unit/Data/                    # 1 new RED stub (existing dir)
    - apps/web/.env.example                        # INERTIA_SSR_ENABLED documented
    - apps/web/.gitignore                          # /public/{css,js}/awcodes appended (runtime-published assets)
tech-stack:
  added:
    - "spatie/laravel-medialibrary ^11.22 — hero image attachment on Article (D-012 + plan 07-03)"
    - "awcodes/filament-tiptap-editor ^3.5 — Tiptap rich-text editor for ArticleResource.body (plan 07-05)"
    - "ueberdosis/tiptap-php ^2.1 — server-side Tiptap JSON → safe HTML conversion (plan 07-10)"
    - "spatie/laravel-sitemap ^8.1 — sitemap:generate Artisan command (plan 07-12)"
    - "@fullcalendar/* ^6.1.20 (5 packages) — FullCalendar Vue3 component for /events (plan 07-10)"
  patterns:
    - "Container-only composer + pnpm (D-021) — install commands run inside docker compose exec web"
    - "Tiptap safe-node profile pinning (Pitfall 10 mitigation) — config/filament-tiptap-editor.php 'default' profile excludes iframe/script/oembed/youtube/video/source/grid-builder/details/blocks"
    - "Wave 0 factory stub idiom (Phase 6 D-06-01-A verbatim) — string FQN $model + per-line @phpstan-ignore on missingType.generics + property.defaultValue"
    - "Bare functional Pest stub convention (Phase 5/6 D-05-01-C / D-06-01-B verbatim) — no namespace, no per-file uses() call, single it('placeholder for ...')"
    - "i18n namespace pre-shipped in full (instead of incremental per-plan) — prevents NoHardcodedStringsTest CI failures mid-execution (D-013 + Phase 6 D-06-01-C precedent)"
key-files:
  created:
    # Configs published + pinned
    - apps/web/config/media-library.php
    - apps/web/config/filament-tiptap-editor.php
    # Medialibrary migration (NOT yet migrated — plan 07-02 owns migrate)
    - apps/web/database/migrations/2026_05_13_234858_create_media_table.php
    # Factory stubs (2) — Wave 0 replaced by plan 07-03
    - apps/web/database/factories/ArticleFactory.php
    - apps/web/database/factories/CategoryFactory.php
    # i18n (3 new)
    - apps/web/lang/en/cms.php
    - apps/web/lang/en/events.php
    - apps/web/lang/en/search.php
    # Articles RED stubs (6) — replaced by plans 07-02/07-05/07-07/07-09/07-10/07-12
    - apps/web/tests/Feature/Articles/ArticleResourcePresentTest.php
    - apps/web/tests/Feature/Articles/ArticlePublishWorkflowTest.php
    - apps/web/tests/Feature/Articles/ArticleIndexPageTest.php
    - apps/web/tests/Feature/Articles/ArticleShowPageTest.php
    - apps/web/tests/Feature/Articles/ArticleHeadMetaTest.php
    - apps/web/tests/Feature/Articles/FtsBackfillTest.php
    # Events RED stubs (2) — replaced by plans 07-09/07-10
    - apps/web/tests/Feature/Events/EventsCalendarPageTest.php
    - apps/web/tests/Feature/Events/EventsFeedJsonControllerTest.php
    # Search RED stubs (2) — replaced by plans 07-08/07-09
    - apps/web/tests/Feature/Search/SearchServiceTest.php
    - apps/web/tests/Feature/Search/SearchControllerTest.php
    # Sitemap RED stub (1) — replaced by plan 07-12
    - apps/web/tests/Feature/Sitemap/SitemapGenerateCommandTest.php
    # I18n RED stub (1) — replaced by plan 07-12
    - apps/web/tests/Feature/I18n/CmsI18nKeyCoverageTest.php
    # Observers RED stub (1) — replaced by plan 07-06
    - apps/web/tests/Feature/Observers/ArticleObserverTest.php
    # Outbound RED stub (1) — replaced by plan 07-06
    - apps/web/tests/Feature/Outbound/ArticleAnnounceOutboundTest.php
    # Ssr RED stub (1) — replaced by plan 07-11
    - apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php
    # Admin RED stub (1) — replaced by plan 07-12
    - apps/web/tests/Feature/Admin/ArticleAuditLogTest.php
    # Unit Data RED stub (1) — replaced by plans 07-03/07-05
    - apps/web/tests/Unit/Data/PublicArticleDataTest.php
  modified:
    - apps/web/composer.json     # +4 deps
    - apps/web/composer.lock     # locked hash chain (14211 lines)
    - apps/web/package.json      # +5 devDeps
    - apps/web/pnpm-lock.yaml    # locked (2731 lines)
    - apps/web/.env.example      # INERTIA_SSR_ENABLED=false documented
    - apps/web/.gitignore        # /public/{css,js}/awcodes appended
    - apps/web/lang/en/admin.php # Appended article + category resource blocks; existing keys untouched
decisions:
  - "D-07-01-A — Tiptap 'default' profile pinned to safe-node allowlist in config/filament-tiptap-editor.php at install time. EXCLUDED: oembed, youtube, video, source, grid-builder, details, blocks. This is the day-zero Pitfall 10 mitigation (T-07-01-01 stored XSS via author-inserted iframe/script). Plan 07-05 ArticleResource form references this profile by name; any widening MUST be paired with a new threat-model entry."
  - "D-07-01-B — Open Question 8 LOCKED inline: markdown-it is NOT installed in v1. Article body render path is `tiptap_converter()->asHTML` end-to-end (Filament editor → tiptap JSON in articles.body JSONB → server-side asHTML for SSR + Inertia props). markdown v2 is out of scope v1 per RESEARCH.md."
  - "Wave 0 scaffolding reuses Phase 6 D-06-01-A factory-stub idiom verbatim: string FQN $model + per-line @phpstan-ignore. Plan 07-03 swaps in `@extends Factory<Article>` + `protected $model = Article::class` once the model lands."
  - "Wave 0 Pest stubs reuse Phase 6 D-06-01-B bare functional convention: no namespace, no per-file uses() call. Pest.php autowires TestCase + RefreshDatabase via uses(...)->in('Feature')."
  - "Phase 7 i18n namespace pre-shipped in full (3 new lang/en/*.php files + admin.php extension) — prevents NoHardcodedStringsTest CI failures mid-execution (D-013 + Phase 6 D-06-01-C precedent)."
  - "create_media_table migration intentionally published but NOT migrated. Plan 07-02 owns the migrate run alongside articles + categories + fts_doc trigger migrations (single migrate cycle keeps the migrate manifest tidy)."
metrics:
  duration: 8m 35s
  completed: 2026-05-14
  tasks: 2
  files_created: 22  # 2 configs + 1 migration + 2 factories + 3 lang + 17 test stubs - 3 untouched
  files_modified: 7  # composer.json + composer.lock + package.json + pnpm-lock.yaml + .env.example + .gitignore + admin.php
  commits: 2
---

# Phase 7 Plan 1: Wave 0 CMS Scaffolding Summary

Phase 7 (CMS) Wave 0 — 4 composer deps + 5 npm deps installed at pinned versions, Tiptap profile locked to a safe-node allowlist (Pitfall 10 mitigation in place from day 0), 2 factory stubs + 17 Pest RED stubs + 3 new lang/en/*.php files + admin.php extension shipped. Every subsequent Phase 7 plan (07-02..07-12) has an explicit RED landing spot for its GREEN assertions and a complete i18n namespace to wrap UI strings.

## Environment Prereqs (Task 1 step 1)

| Check | Result |
|-------|--------|
| `docker compose exec web php -m \| grep -iE "gd\|imagick"` | **gd present**, imagick absent (one is sufficient per RESEARCH Environment Availability) |
| `ls -la /app/public/storage` | **created via `php artisan storage:link`** — symlink target `/app/storage/app/public` |

## Composer Deps (4 — pinned)

| Package | Pinned | Installed | Released | Purpose |
|---------|--------|-----------|----------|---------|
| `spatie/laravel-medialibrary` | `^11.22` | **11.22.1** | 2026-05-04 | Hero image attachment on Article (plan 07-03) |
| `awcodes/filament-tiptap-editor` | `^3.5` | **v3.5.16** | 2025-11-13 | Tiptap rich-text editor for ArticleResource (plan 07-05) |
| `ueberdosis/tiptap-php` | `^2.1` | **2.1.0** | 2026-01-10 | Server-side Tiptap JSON → HTML conversion (plan 07-10) |
| `spatie/laravel-sitemap` | `^8.1` | **8.1.0** | 2026-03-12 | sitemap:generate Artisan command (plan 07-12) |

`composer.lock` final size: **14211 lines** (locked hash chain captures all transitive deps).

## NPM Deps (5 — devDependencies, ^6.1.20)

```json
"@fullcalendar/core": "^6.1.20",
"@fullcalendar/daygrid": "^6.1.20",
"@fullcalendar/interaction": "^6.1.20",
"@fullcalendar/timegrid": "^6.1.20",
"@fullcalendar/vue3": "^6.1.20"
```

`apps/web/pnpm-lock.yaml` final size: **2731 lines**.

**`markdown-it` is INTENTIONALLY NOT INSTALLED in v1** (D-07-01-B — Open Question 8 LOCKED). Article body render path is `tiptap_converter()->asHTML` end-to-end.

## Tiptap Profile Pinning (Pitfall 10 audit trail)

`apps/web/config/filament-tiptap-editor.php` `'default'` profile tools array (exact):

```php
'default' => [
    'heading', 'bullet-list', 'ordered-list', 'blockquote', 'hr', '|',
    'bold', 'italic', 'strike', 'underline', '|',
    'link', 'media', 'table', '|',
    'code', 'code-block',
],
```

**Explicitly EXCLUDED** (iframe-bearing / raw-HTML / supply-chain risk):

| Excluded tool | Why |
|---------------|-----|
| `oembed` | Iframe-bearing — would allow author to embed arbitrary 3rd-party HTML |
| `youtube` | Iframe wrapper |
| `video` | HTML5 video with external src |
| `source` | Raw HTML edit — would bypass the entire allowlist |
| `grid-builder` | Brings complex node structures whose sanitisation surface is too large for v1 |
| `details` | `<details>` is fine in theory but pulls in more author-controlled HTML structure than v1 wants |
| `blocks` | Tiptap "custom blocks" — would let authors paste arbitrary node JSON |

`bubble_menu_tools` and `floating_menu_tools` mirror the safe-node allowlist (no shortcuts to the excluded tools via floating menus).

Plan 07-05 `ArticleResource` form schema MUST reference this `'default'` profile by name (`TiptapEditor::make('body')->profile('default')`). Any future widening of the allowlist requires a new threat-model entry paired with this file's `git log`.

## Wave 0 Stubs Landed

### 2 Factory Stubs (replaced by plan 07-03)

| File | $model FQN |
|------|------------|
| `apps/web/database/factories/ArticleFactory.php` | `'App\\Models\\Article'` |
| `apps/web/database/factories/CategoryFactory.php` | `'App\\Models\\Category'` |

Both factories use the **Phase 6 D-06-01-A idiom verbatim**: `final class` + string FQN `$model` + per-line `@phpstan-ignore` on `missingType.generics` (class doc) + `property.defaultValue` (`$model` line) + `definition() returns []`. Plan 07-03 swaps in `@extends Factory<Article>` + `protected $model = Article::class` once the models exist.

### 17 Pest RED Stubs

| Suite | Count | Target plan(s) |
|-------|-------|----------------|
| `tests/Feature/Articles/ArticleResourcePresentTest.php` | 1 | 07-05 |
| `tests/Feature/Articles/ArticlePublishWorkflowTest.php` | 1 | 07-07 |
| `tests/Feature/Articles/ArticleIndexPageTest.php` | 1 | 07-09/07-10 |
| `tests/Feature/Articles/ArticleShowPageTest.php` | 1 | 07-10 |
| `tests/Feature/Articles/ArticleHeadMetaTest.php` | 1 | 07-12 |
| `tests/Feature/Articles/FtsBackfillTest.php` | 1 | 07-02 |
| `tests/Feature/Events/EventsCalendarPageTest.php` | 1 | 07-10 |
| `tests/Feature/Events/EventsFeedJsonControllerTest.php` | 1 | 07-09 |
| `tests/Feature/Search/SearchServiceTest.php` | 1 | 07-08 |
| `tests/Feature/Search/SearchControllerTest.php` | 1 | 07-09 |
| `tests/Feature/Sitemap/SitemapGenerateCommandTest.php` | 1 | 07-12 |
| `tests/Feature/I18n/CmsI18nKeyCoverageTest.php` | 1 | 07-12 (Pitfall 10) |
| `tests/Feature/Observers/ArticleObserverTest.php` | 1 | 07-06 |
| `tests/Feature/Outbound/ArticleAnnounceOutboundTest.php` | 1 | 07-06 |
| `tests/Feature/Ssr/SsrBundleExistsTest.php` | 1 | 07-11 |
| `tests/Feature/Admin/ArticleAuditLogTest.php` | 1 | 07-12 |
| `tests/Unit/Data/PublicArticleDataTest.php` | 1 | 07-03/07-05 |
| **Total** | **17** | |

All 17 stubs follow the **Phase 5/6 D-05-01-C / D-06-01-B bare functional convention**:

```php
<?php
declare(strict_types=1);

/* Wave 0 RED stub — replaced by plan 07-XX. */

it('placeholder for <feature> — replace via plan 07-XX', function (): void {
    expect(true)->toBe(false);
});
```

`docker compose exec web ./vendor/bin/pest --filter="placeholder" --no-coverage` reports **17 failed / 17 assertions** — the intended RED baseline. Suite did not crash.

### i18n Namespaces (3 new + 1 extended)

| File | Top-level keys | Leaf-key inventory |
|------|----------------|---------------------|
| `apps/web/lang/en/cms.php` (144 lines) | `status`, `actions`, `fields`, `errors`, `empty` | status × 3 × 2 = 6; actions × 3 × 3 = 9; fields × 9 × 2 = 18; errors × 4 (interpolation: `:slug`, `:from`, `:to`, `:node`); empty × 4 = **≈ 41 leaf keys** |
| `apps/web/lang/en/events.php` (70 lines) | `header`, `types`, `empty`, `page` | header × 5; types × 3 × 1; empty × 1; page × 2 = **≈ 11 leaf keys** |
| `apps/web/lang/en/search.php` (66 lines) | `placeholder`, `results`, `sections`, `page` | placeholder × 1; results × 3 (`:query`, `:count` interpolation); sections × 3 × 1; page × 1 = **≈ 8 leaf keys** |
| `apps/web/lang/en/admin.php` (589 lines, +39) | added `article`, `category` blocks | article × { label, plural_label, nav, fields × 11, publication × 2 } = 16; category × { label, plural_label, nav, fields × 3 } = 6 = **≈ 22 leaf keys** appended |

Existing `admin.php` keys (550 lines through Phase 6) are untouched. CmsI18nKeyCoverageTest (plan 07-12) will assert every `t()` / `__()` call in Phase 7 Vue + PHP resolves against this set.

### `.env.example` extension

```env
# ─── Inertia v2 SSR (Phase 7 plan 07-01) ────────────────────────────────
# Off in dev to keep the Vite hot-reload loop tight. Flipped to true in
# production (Railway env group); plans 07-09..07-11 wire the prod ssr
# Node sidecar in docker-compose and the Inertia ssr.mjs bundle.
INERTIA_SSR_ENABLED=false
```

### `.gitignore` extension

`/public/css/awcodes` and `/public/js/awcodes` appended — these directories are republished at every `composer require filament/*` hook (`filament:upgrade` post-install action) and mirror the existing `/public/{css,js}/filament` gitignored entries.

## Pint + PHPStan Gates

| Gate | Result |
|------|--------|
| `make pint --test` (full suite, 460 files) | **PASS** |
| `make phpstan` (Larastan L8, full suite) | **[OK] No errors** |
| `docker compose exec web ./vendor/bin/phpstan analyse database/factories/ArticleFactory.php database/factories/CategoryFactory.php` | **[OK] No errors** (per-line @phpstan-ignore comments hold) |

## Deviations from Plan

### Auto-fixed / clarified inline

**1. [Rule 3 — blocking issue] Container pnpm cannot update repo-root `pnpm-lock.yaml`**
- **Found during:** Task 1 step 5.
- **Issue:** Plan must_haves line 45 reads "pnpm-lock.yaml at repo root regenerated". In practice, the web container mounts only `apps/web/` at `/app` (per `docker-compose.yml` D-021 layout); the workspace root + `pnpm-workspace.yaml` are NOT visible inside the container, and host has no `pnpm` binary. `docker compose exec web pnpm add` updates `apps/web/pnpm-lock.yaml` only.
- **Fix:** Followed the established Phase 1-06 + Phase 5-01 precedent — `apps/web/pnpm-lock.yaml` is the tracked lockfile for web-app deps. Repo-root `pnpm-lock.yaml` is for bot/rcon-worker workspace packages (Phase 5/8 territory) and is unaffected by web-side installs.
- **Files modified:** `apps/web/package.json`, `apps/web/pnpm-lock.yaml` (repo-root `pnpm-lock.yaml` untouched).
- **Commit:** 841789b.

**2. [Rule 2 — missing critical functionality] `storage:link` symlink absent on entry**
- **Found during:** Task 1 step 1 (Environment Availability check).
- **Issue:** Medialibrary requires `public/storage` → `storage/app/public` symlink for default `disk='public'` to serve uploaded heros. `ls /app/public/storage` returned ENOENT.
- **Fix:** Ran `docker compose exec web php artisan storage:link` — symlink now points to `/app/storage/app/public`. Verified.
- **Files modified:** None (symlink lives at runtime path, not tracked in git).
- **Commit:** 841789b (documented in commit message).

**3. [Rule 2 — missing critical functionality] `public/css/awcodes` + `public/js/awcodes` not in `.gitignore`**
- **Found during:** Task 1 step 2 (after composer require).
- **Issue:** `filament:upgrade` post-install hook republishes Filament + Tiptap static assets to `apps/web/public/{css,js}/awcodes`. These are runtime-published from `vendor/`; tracking them would bloat git and create merge conflicts on every `composer update`. Mirror of existing `/public/{css,js}/filament` entries.
- **Fix:** Appended `/public/css/awcodes` and `/public/js/awcodes` to `apps/web/.gitignore` next to the existing filament entries.
- **Files modified:** `apps/web/.gitignore`.
- **Commit:** 841789b.

**4. [Plan clarification] Stub count: 17 (not 15)**
- **Found during:** Plan task 2 step 6 (the plan body itself reconciles 15→17 inline: "Total = 17 stubs — the count of 15 in CONTEXT was a draft").
- **Action:** Shipped 17 RED stubs per the plan's reconciled list (the must_haves.truths line 51 still reads "15 Pest RED test stub files"; this is a stale truth from the CONTEXT draft). The 17 stubs cover every Phase 7 implementation plan's RED landing spot per 07-VALIDATION.md.

### Auth gates encountered

None.

### Architectural changes (Rule 4)

None.

## Open Question Resolutions

**OQ-08 (markdown v2) — LOCKED inline in this plan:** Out of scope v1. `tiptap_converter()->asHTML` is the sole article-body render path. `markdown-it` NOT installed; `ueberdosis/tiptap-php` carries the entire server-side render duty. Plans 07-10/07-12 reference this lock when wiring `Blog/Show.vue` body rendering.

## Threat Model Status

| Threat ID | Status |
|-----------|--------|
| T-07-01-01 | **mitigated** — Tiptap 'default' profile pinned to safe-node allowlist (see audit trail above) |
| T-07-01-02 | **accepted** — 4 PHP + 5 npm deps all first-party Spatie / Filament-community / FullCalendar MIT; composer.lock + apps/web/pnpm-lock.yaml capture hash chain |
| T-07-01-03 | **accepted** — INERTIA_SSR_ENABLED is a boolean toggle, not a secret; .env.example shape only |
| T-07-01-04 | **mitigated** — media-library default disk='public' (already gitignored); storage:link verified |
| T-07-01-05 | **deferred-to-plan-07-05** — Filament form-layer `->maxSize(5120)->acceptedFileTypes(['image/jpeg','image/png','image/webp'])` will be added when ArticleResource lands |

## Known Stubs

All Wave 0 stubs are intentional and tracked. Each RED Pest stub names the target plan in its placeholder description. No silent stubs remain.

## Commit Trail

| Task | Commit | Files |
|------|--------|-------|
| 1: composer + npm deps + configs + .env | `841789b` | composer.{json,lock}, package.json, pnpm-lock.yaml, config/media-library.php, config/filament-tiptap-editor.php, .env.example, .gitignore, database/migrations/2026_05_13_234858_create_media_table.php |
| 2: factory + RED stubs + i18n | `011c597` | 2 factories + 3 lang files + admin.php extension + 17 RED test stubs |

## Self-Check: PASSED

All files claimed above are present on disk; all commits resolvable via `git log`; pest --filter="placeholder" → 17 failed / 17 assertions (RED baseline); pint + phpstan clean.
