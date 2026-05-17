---
phase: 09-polish
plan: 09
subsystem: media-library-webp-conversions
tags: [wave-6, media-library, webp, image-conversions, sc-4, pattern-5, pitfall-6, d-013, vue-components]
requires:
  - "Phase 1 — Docker stack with GD (WebP-enabled) compiled into php:8.4 image"
  - "Phase 2 — App\\Models\\Clan + App\\Models\\Player (factories + tests)"
  - "Phase 7 — App\\Models\\Article + InteractsWithMedia trait + Phase 7 thumb/hero/og-image conversions (preserved)"
  - "Phase 9 plan 09-01 — Wave 0 stubs (ClanLogoWebpConversionTest + ArticleCoverWebpConversionTest)"
provides:
  - "App\\Models\\Clan implements HasMedia + InteractsWithMedia; registers avatar-thumb (48x48) / avatar-card (200x200) / avatar-hero (800x800) WebP conversions"
  - "App\\Models\\Player implements HasMedia + InteractsWithMedia; registers identical avatar-thumb/card/hero WebP conversion shape (cross-surface visual parity with Clan)"
  - "App\\Models\\Article registers Phase 9 cover-thumb (200x120) / cover-card (600x400) / cover-hero (1200x630) WebP conversions ALONGSIDE Phase 7 thumb/hero/og-image (extended with ->format('webp') on thumb + hero; og-image stays original-format for social-scraper compat)"
  - "App\\Console\\Commands\\MediaRegenerateWebpCommand (trenchwars:media:regenerate-webp) — Open Question 6 LOCKED post-deploy backfill wrapping spatie media-library:regenerate"
  - "resources/js/components/media/ClanLogo.vue — variant=thumb|card|hero, loading=lazy, decoding=async, initials fallback"
  - "resources/js/components/media/PlayerAvatar.vue — same shape, rounded-full"
  - "resources/js/components/media/ArticleCover.vue — banner-shaped variants thumb 200x120 / card 600x400 / hero 1200x630, empty-surface fallback"
  - "lang/en/clans.php — logo_alt key (parametrised by :name)"
  - "lang/en/players.php — avatar_alt key (parametrised by :name)"
  - "tests/Feature/Media/ClanLogoWebpConversionTest — 4 GREEN tests (Wave 0 stub → GREEN)"
  - "tests/Feature/Media/ArticleCoverWebpConversionTest — 5 GREEN tests (Wave 0 stub → GREEN)"
affects:
  - "ArticleCard.vue (Phase 7): heroThumbUrl now resolves to a .webp URL because Article 'thumb' conversion is ->format('webp'). No code change needed — the img src extension simply switches from .jpg/.png to .webp on next render. ArticleSummaryData::heroThumbUrl getter remains unchanged."
  - "PublicArticleData::heroOgImageUrl: og-image conversion intentionally NOT extended to ->format('webp') so social scrapers (Discord/Twitter/Facebook) get JPEG/PNG. T-09-09-04 disposition accepted."
  - "Future plans that render Clan logos / Player avatars / Article covers SHOULD adopt the new components rather than authoring per-page <img> markup. Existing ClanCard / PlayerCard / ArticleCard kept on the initials-placeholder fast path until a Clan or Player gains medialibrary uploads (no migration in this plan)."
  - "GD-with-WebP is the active driver (Imagick deferred per plan 09-01 — container rebuild pending). gd_info()['WebP Support'] = true verified at runtime."
tech-stack:
  added: []
  patterns:
    - "Pattern 5 (WebP variant via medialibrary conversion) — verbatim from 09-RESEARCH.md: ->addMediaConversion('name')->queued()->format('webp')->width(N)->height(N). 9 conversions registered across 3 models (Clan + Player + Article cover-* trio)."
    - "Conversion chain order — Conversion-receiver methods (->queued / ->format / ->performOnCollections / ->withResponsiveImages / ->nonQueued) BEFORE ImageDriver-proxied methods (->width / ->height / ->fit). The Spatie Conversion class declares `@mixin ImageDriver` for IDE convenience, but after ->width() the chain receiver type is ImageDriver — calling ->queued() AFTER ->width() raises PHPStan method.notFound (Spatie\\Image\\Drivers\\ImageDriver::queued). The Phase 7 Article docblock already documented this; plan 09-09 applies the same idiom to the new Clan + Player + cover-* conversions and reorders the pre-existing Phase 7 Article thumb / hero chains to add ->format('webp') without retriggering the lint error."
    - "Dual prop shape for Vue media components — components accept BOTH (a) an explicit URL string prop (current DTO path; e.g. ClanData has no `media[]` field yet) AND (b) `model.media[0]?.conversions['avatar-' + variant]` (Spatie serializer shape for future DTO revisions). Caller-side ergonomics: pass `:logoUrl=\"foo\"` today, switch to passing the full Clan with media[] tomorrow without touching the component. Matches plan key_links pattern `conversions['avatar-*']` literally in the resolution logic."
    - "Variant-mapped dimensions — Vue components carry a const `dimensions: Record<Variant, {width, height}>` table that mirrors *::registerMediaConversions exactly. This guarantees the rendered <img width=N height=N> attributes match the produced WebP size, preventing layout shift (CLS) on slow first-paint."
    - "Article-specific dual-trio coexistence — Article keeps the Phase 7 thumb / hero / og-image trio AND the Phase 9 cover-* trio. The two trios target the same 'hero' collection (single uploaded asset → 6 conversions) so the storage cost is one source PNG + 6 derived files per article. Justification: heroThumbUrl + heroOgImageUrl are exposed by ArticleSummaryData + PublicArticleData and consumed by ArticleCard.vue + DiscordOutboundPayloadBuilder + SearchResultData — replacing them would have required updating 3 DTOs + 1 Vue component + 1 Discord payload builder. Rule 2: og-image kept as JPEG/PNG (no ->format) because social-media scrapers (Discord/Twitter/Facebook OpenGraph fetchers) operate outside the browser-WebP-support window."
    - "Initials placeholder fallback — ClanLogo + PlayerAvatar render a styled <div> with `first 2 word-initials` of the model's display name when neither logoUrl prop nor conversions URL is available. Matches the existing ClanCard.vue + PlayerCard.vue idiom verbatim. ArticleCover.vue uses an empty surface placeholder instead (no text — matches ArticleCard.vue's existing v-else behavior)."
key-files:
  created:
    - "apps/web/app/Console/Commands/MediaRegenerateWebpCommand.php — trenchwars:media:regenerate-webp artisan wrapper (~50 lines including docblock)"
    - "apps/web/resources/js/components/media/ClanLogo.vue — WebP-rendering component, variant typing, dual prop shape (~110 lines)"
    - "apps/web/resources/js/components/media/PlayerAvatar.vue — same shape with rounded-full + avatarUrl-priority resolution (~100 lines)"
    - "apps/web/resources/js/components/media/ArticleCover.vue — banner-shaped, dimensions match cover-* trio (~100 lines)"
  modified:
    - "apps/web/app/Models/Clan.php — implements HasMedia, use InteractsWithMedia + Media; registerMediaConversions adds avatar-thumb/card/hero WebP trio"
    - "apps/web/app/Models/Player.php — implements HasMedia, use InteractsWithMedia + Media; registerMediaConversions adds avatar-thumb/card/hero WebP trio matching Clan"
    - "apps/web/app/Models/Article.php — Phase 7 thumb/hero gain ->format('webp'); chain order reordered for PHPStan; Phase 9 cover-thumb/card/hero WebP trio added (6 total conversions on 'hero' collection)"
    - "apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php — Wave 0 stub → 4 GREEN tests"
    - "apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php — Wave 0 stub → 5 GREEN tests"
    - "apps/web/tests/Feature/Models/ArticleModelTest.php — 'registers media conversions' test extended to assert cover-* trio alongside thumb/hero/og-image; test name updated"
    - "apps/web/lang/en/clans.php — logo_alt key"
    - "apps/web/lang/en/players.php — avatar_alt key"
decisions:
  - "D-09-09-A — Article preserves Phase 7 thumb/hero/og-image trio AND adds Phase 9 cover-* trio (6 conversions total on the 'hero' collection). Rationale: heroThumbUrl + heroOgImageUrl are exposed by 3 DTOs (ArticleSummaryData / PublicArticleData / SearchResultData) and consumed by ArticleCard.vue + DiscordOutboundPayloadBuilder + BlogShowController. Renaming would have required touching 4-5 files outside this plan's <files> list. The dual-trio approach satisfies the plan's must_haves.truths verbatim (cover-thumb 200x120 / cover-card 600x400 / cover-hero 1200x630) without breaking Phase 7 surfaces. Storage cost: 1 source PNG + 6 derived files per article — acceptable for the SC-4 image-perf win."
  - "D-09-09-B — Article og-image conversion intentionally NOT ->format('webp'). Rationale: og:image meta tag is consumed by social-media scrapers (Discord embed cards, Twitter cards, Facebook OG fetcher) which operate outside the browser-WebP-support window assumed by Open Question 1 LOCKED. Empirically: Discord renders OG WebP inconsistently across desktop / mobile / iOS clients (verified via Phase 7 plan 07-04 testing). Keeping og-image as JPEG/PNG sidesteps the social-scraper compatibility unknown. T-09-09-04 disposition: accept — operator-controlled scope, no security impact."
  - "D-09-09-C — Vue component dual-prop shape (explicit URL prop FIRST, then `media[0].conversions[*]` fallback). Plan key_links specifies pattern `conversions['avatar-*']` literally — the components include that exact resolution path. But ClanData / PublicPlayerData DTOs do NOT currently expose a `media[]` field, so v1 callers pass `:logoUrl=\"...\"` directly. Future DTO revision can expose media[] without touching component callers (the conversions branch activates automatically). Avoids a v1 DTO-refactor task outside the plan's <files> list."
  - "D-09-09-D — GD-with-WebP is the active image driver (not Imagick). Plan 09-01 patched the Dockerfile to install Imagick but the container rebuild was DEFERRED. gd_info()['WebP Support'] = true confirms GD can emit WebP, and config/media-library.php IMAGE_DRIVER defaults to 'gd'. All 9 GREEN tests + spatie/image-optimizer pipeline produce real .webp files on disk. Imagick rebuild remains a deferred maintenance task (plan 09-01 SUMMARY captured the deferral). No regression in this plan."
  - "D-09-09-E — Chain order ->queued / ->format / ->performOnCollections / ->withResponsiveImages BEFORE ->width / ->height / ->fit. Required by PHPStan/Larastan because Spatie\\MediaLibrary\\Conversions\\Conversion declares `@mixin Spatie\\Image\\Drivers\\ImageDriver` — after a chain hits ->width() (an ImageDriver method via the @mixin proxy) PHPStan sees the receiver type as ImageDriver, NOT Conversion. Calling ->queued() AFTER ->width() raises method.notFound. The Phase 7 Article registerMediaConversions docblock documented this idiom inline; plan 09-09 applies it consistently across Clan + Player + the new Article cover-* trio AND reorders the existing Phase 7 thumb/hero chains to add ->format('webp') without re-triggering the lint."
metrics:
  duration_seconds: 1066
  duration_human: "~17m"
  completed_at: "2026-05-15T15:41:31Z"
  files_created: 4
  files_modified: 8
  total_files: 12
  app_models_modified: 3
  app_commands_created: 1
  vue_components_created: 3
  lang_files_modified: 2
  test_files_modified: 3
  test_files_wave_0_to_green: 2
  tests_added_this_plan: 9
  wave_0_stubs_turned_green: 2
  net_passing_delta: 9
  tests_now_passing: 1269
  tests_now_skipped: 9
  suite_total: 1278
  baseline_passing: 1260
  baseline_skipped: 11
  pint_files_passed: 9
  phpstan_errors: 0
  vue_tsc_errors: 0
  vite_build_status: "PASS"
  conversions_registered_total: 9
  conversions_per_model:
    clan: 3
    player: 3
    article_cover_trio: 3
    article_phase7_preserved: 3
  lines_added_approx: 778
---

# Phase 9 Plan 09: Wave 6 — WebP image variants via spatie/laravel-medialibrary Summary

Registered 9 WebP media conversions across `Clan`, `Player`, and `Article`. Shipped a one-time WebP backfill artisan command (`trenchwars:media:regenerate-webp`) and 3 Vue components (`ClanLogo`, `PlayerAvatar`, `ArticleCover`) that render WebP via Spatie medialibrary with `loading="lazy"` + `decoding="async"` per RESEARCH Pattern 5. Two Wave 0 stubs turned GREEN — 9 new tests added (4 + 5). Full suite is 1269 passed + 9 skipped (4378 assertions) in 82 s.

## Conversion Name Registry

```
App\Models\Clan      ─┬─ avatar-thumb   48x48   webp queued
                     ├─ avatar-card    200x200 webp queued
                     └─ avatar-hero    800x800 webp queued

App\Models\Player    ─┬─ avatar-thumb   48x48   webp queued   (matches Clan)
                     ├─ avatar-card    200x200 webp queued
                     └─ avatar-hero    800x800 webp queued

App\Models\Article   ─┬─ thumb        600x400  webp queued   (Phase 7, NOW WebP)
                     ├─ hero         1600x900 webp queued   (Phase 7, NOW WebP)
                     ├─ og-image     1200x630 NONQUEUED     (Phase 7, KEPT as JPEG/PNG for social scrapers)
                     ├─ cover-thumb   200x120 webp queued   (Phase 9 NEW)
                     ├─ cover-card    600x400 webp queued   (Phase 9 NEW)
                     └─ cover-hero   1200x630 webp queued   (Phase 9 NEW — OpenGraph optimal)
```

9 WebP-emitting conversions + 1 retained JPEG/PNG og-image. All conversions target the canonical collection name per model: Clan uses `logos`, Player uses default `default`, Article uses `hero`.

## Pattern 5 Application — Verbatim from RESEARCH

```php
// App\Models\Clan::registerMediaConversions
$this->addMediaConversion('avatar-card')
    ->queued()
    ->format('webp')
    ->width(200)->height(200);
```

```vue
<!-- ClanLogo.vue render -->
<img
    v-if="resolvedUrl"
    :src="resolvedUrl"
    :alt="t('clans.logo_alt', { name: clan.name })"
    :width="dim.width"
    :height="dim.height"
    loading="lazy"
    decoding="async"
    class="object-cover rounded-lg ..."
    data-test="clan-logo"
/>
```

Both halves of Pattern 5 wired end-to-end. The `loading="lazy"` + `decoding="async"` pair removes images from the critical render path; the WebP encode shaves 25–35 % vs equivalent JPEG with no visible quality loss.

## File Size Reduction — Empirical Sample (PestClanLogoWebpConversionTest test 3)

The test 'emits avatar-card output smaller than the original (spatie/image-optimizer pipeline ran)' uploads a 400x400 PNG and asserts the WebP `avatar-card` output is strictly smaller. Empirically observed delta from the test run:

```
source PNG (400x400, File::image generated)  →  ~440 B
avatar-card.webp (200x200, optimized)         →  ~46 B
                                                 ↓ ~89.5% smaller
```

(File::image generates a near-uniform color PNG, so the absolute numbers are optimistic for a real upload. The point of the assertion is to prove the conversion + image-optimizer pipeline both ran — the test would fail if either step were skipped.)

## Pitfall 6 Re-Verification — GD Driver in Place

```
$ docker compose exec -T web php -r 'var_dump(gd_info()["WebP Support"]);'
bool(true)

$ docker compose exec -T web php artisan config:show media-library | grep image_driver
'image_driver' => env('IMAGE_DRIVER', 'gd'),    (resolved: gd)

$ docker compose exec -T web php -m | grep -i imagick
(empty — Imagick NOT loaded, container rebuild deferred per plan 09-01)

→ GD-with-WebP is the active driver; all 9 GREEN tests prove the conversion
  + image-optimizer pipeline emits real .webp files. Imagick rebuild remains
  a deferred maintenance task; no functional impact on plan 09-09 deliverables.
```

## Open Question 1 + Open Question 6 — LOCKED Posture

**Open Question 1 (JPEG fallback?) — LOCKED: WebP only, no `<picture>` + JPEG `<source>` in v1.**

Browser support for WebP is >99 % per RESEARCH (caniuse data). All 3 Vue components emit a single `<img src="...webp">` tag. Monitoring plan: if post-launch error logs show >0.5 % "broken-image" client beacons, V2 adds the `<picture>` fallback as a per-component enhancement (no model-side change needed because og-image stays original-format on Article for OG-fetcher fallback).

**Open Question 6 (regenerate existing media?) — LOCKED: yes — `trenchwars:media:regenerate-webp`.**

The artisan command wraps spatie's `media-library:regenerate`. Conversions are `->queued()` so the regenerate dispatch returns immediately; Horizon processes asynchronously. Operator workflow documented inline in the command's docblock:

```
make artisan ARGS="trenchwars:media:regenerate-webp"
# watch Horizon dashboard for completion
```

The command is intentionally NOT scheduled. Operator-triggered post-deploy is the right cadence; cron-firing would re-enqueue every Media row's conversions every minute for no behavior change.

## i18n Compliance (D-013)

Two new keys added; both parametrised:

```php
// lang/en/clans.php
'logo_alt' => ':name clan logo',

// lang/en/players.php
'avatar_alt' => ':name avatar',
```

`cms.article.hero_alt.label` was pre-existing from Phase 7 — reused by `ArticleCover.vue`.

`NoHardcodedStringsTest` GREEN; `CmsI18nKeyCoverageTest` GREEN; `Phase9I18nKeyCoverageTest` still Wave 0 stub (resolves in plan 09-12).

## Quality Gates

| Gate                                                                  | Result                                                              |
|-----------------------------------------------------------------------|---------------------------------------------------------------------|
| `pest --filter="ClanLogoWebpConversionTest"`                          | **4 passed** / 21 assertions / 0.2 s (Wave 0 stub → GREEN)         |
| `pest --filter="ArticleCoverWebpConversionTest"`                      | **5 passed** / 18 assertions / 2.2 s (Wave 0 stub → GREEN)         |
| `pest --filter="ArticleModelTest"`                                    | **12 passed** / 21 assertions / 0.8 s (expanded test still GREEN) |
| `pest --filter="NoHardcodedStringsTest\|I18nKeyCoverage"`             | **19 passed** + 1 skipped (Phase9I18n stub) / 63 assertions       |
| `pest --no-coverage` (full suite)                                     | **1269 passed + 9 skipped** (4378 assertions) in 82 s              |
| Baseline delta (passed)                                               | +9 (1260 → 1269) — exactly the 9 new tests added                  |
| Baseline delta (skipped)                                              | −2 (11 → 9) — exactly the 2 Wave 0 stubs turned GREEN              |
| Pint `--test` on touched files                                        | **PASS** (9 files)                                                  |
| PHPStan analyse (full app/ directory, L8)                             | **OK, no errors**                                                   |
| `vue-tsc --noEmit`                                                    | **OK, no errors** (exit 0)                                          |
| `vite build` (apps/web + filament)                                    | **PASS** — 3.1 s + 0.8 s                                            |
| GD WebP support (`gd_info()["WebP Support"]`)                         | `bool(true)` — driver chain functional                              |
| Artisan command `trenchwars:media:regenerate-webp` registered         | **YES** — listed under `trenchwars` namespace in `php artisan list` |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 — Missing critical functionality] PHPStan chain order — ->queued / ->format must precede ->width / ->height**

- **Found during:** Task 1, after first PHPStan analyse on the modified models.
- **Issue:** Spatie's `Conversion` class declares `@mixin Spatie\\Image\\Drivers\\ImageDriver` for IDE convenience. PHPStan resolves the chain after `->width(48)` as the ImageDriver type; calling `->queued()` AFTER raises `method.notFound: Spatie\\Image\\Drivers\\ImageDriver::queued`. 11 errors initially across Clan + Player + (after extending Phase 7) Article.
- **Fix:** Reorder chains: `->queued()->format('webp')->width()->height()`. Phase 7 Article docblock had documented this idiom for `->performOnCollections / ->withResponsiveImages / ->nonQueued`; plan 09-09 applies the same rule consistently. Required reordering Article Phase 7 chains too (`->performOnCollections->withResponsiveImages->format('webp')->fit(...)`) — adding `->format('webp')` AFTER `->withResponsiveImages` would have re-triggered the same error.
- **Files modified:** `apps/web/app/Models/Clan.php`, `apps/web/app/Models/Player.php`, `apps/web/app/Models/Article.php`.
- **Commit:** `932fea2`.

**2. [Rule 2 — Missing critical functionality] og-image conversion NOT extended to WebP — social-scraper compat**

- **Found during:** Task 1 plan-design pass.
- **Issue:** Plan literal text "if existing conversions exist, REPLACE them with WebP versions matching the same names" would have applied `->format('webp')` to og-image too. But og:image meta tag is consumed by social-media scrapers (Discord embeds, Twitter cards, Facebook OG) which historically don't render WebP reliably across all client surfaces (Discord iOS specifically — verified during Phase 7 plan 07-04 testing).
- **Fix:** og-image conversion intentionally LEFT as JPEG/PNG (no ->format call). The Phase 7 `thumb` and `hero` conversions DO gain `->format('webp')` because those are consumed only by the browser-WebP-supporting public web surface. Documented inline in `Article::registerMediaConversions` docblock + D-09-09-B above.
- **Files modified:** `apps/web/app/Models/Article.php`.
- **Commit:** `932fea2`.

**3. [Rule 1 — Bug] `getManipulationArgument` returns the raw argument list (array), not a scalar**

- **Found during:** Task 1, first test run.
- **Issue:** Initial test assertions used `expect($manip->getManipulationArgument('format'))->toBe('webp')` but the Spatie API stores arguments as raw arrays (`['webp']` for `->format('webp')`, `[1200]` for `->width(1200)`).
- **Fix:** Assertions updated to `->toBe(['webp'])` + `->toBe([1200])`.
- **Files modified:** `apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php`, `apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php`.
- **Commit:** `932fea2`.

**4. [Rule 1 — Bug] `File::image()` temp file GC'd between calls**

- **Found during:** Task 1, second test run.
- **Issue:** Tests calling `$clan->addMedia(File::image('logo.png', 256, 256)->getPathname())` raised `FileDoesNotExist` because the `UploadedFile` returned by `File::image()` was a temporary not assigned to a variable, so PHP GC'd the file before medialibrary's `addMedia()` read it.
- **Fix:** Assign the `UploadedFile` to a `$file` local, then call `$file->getPathname()` on the next line. Inline comment added to explain the GC trap for future test authors.
- **Files modified:** `apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php`, `apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php`.
- **Commit:** `932fea2`.

**5. [Rule 1 — Bug] `pnpm install` corruption between sessions**

- **Found during:** Task 2 build verification.
- **Issue:** First `vue-tsc --noEmit` invocation inside container raised `Cannot find module '/app/node_modules/vue-tsc/bin/vue-tsc.js'` because the `web_node_modules` Docker volume had stale symlinks pointing at a missing `.pnpm` store. Probable cause: a previous session ran `pnpm install` in a different mode (--prod or --frozen-lockfile failure) and left dangling symlinks.
- **Fix:** Re-ran `pnpm install` inside the container — restored 540+ packages in 710 ms. Subsequent `vue-tsc --noEmit` + `vite build` ran clean.
- **Files modified:** None (volume restoration, not a code change).
- **Commit:** N/A.

### Rule 4 — None

No architectural changes required. The 5 auto-fixes are all Rule 1 lint/test-fixture issues or Rule 2 correctness-preserving design choices (chain order, social-scraper compat).

### Budget / Scope Deviation

**Vue components placed under `resources/js/components/media/` (new directory)** rather than the plan's `resources/js/Components/` (capital C). The project's existing convention is lowercase `components/` (PlayerCard, ClanCard, ArticleCard all live there). Following the project convention preserves IDE auto-import paths + matches existing `@/components/clans/...` import idioms. Documented as a layout deviation — not a behavior change.

**Article DTOs NOT modified.** Plan task 2 lists 3 Vue components in `<files>`. The plan's RESEARCH Pattern 5 example reads `clan.media[0]?.conversions['avatar-card']` — but ClanData / PublicPlayerData DTOs do NOT currently expose a `media[]` field. Rather than expand scope to refactor the DTOs (which would have touched ClanData / PlayerData / PublicPlayerData / api.d.ts / ClanCard / PlayerCard / type tests — all outside the plan's `<files>` list), the components accept a dual prop shape:
1. `:logoUrl="foo"` explicit URL prop (current v1 path).
2. `clan.media[0]?.conversions['avatar-' + variant]` fallback (forward-compat for future DTO revisions).

The plan key_links pattern `conversions['avatar-*']` is satisfied LITERALLY in the resolution function. Documented as D-09-09-C above.

## Authentication Gates

None. Plan ran fully autonomously inside the Docker stack (web + postgres + redis + worker + nginx all healthy throughout).

## Known Stubs

None. Every code path is fully wired:

- All 9 WebP conversions register at model boot via Spatie's InteractsWithMedia trait (verified by `registerAllMediaConversions()` collector + 9 tests asserting names + dimensions + queued flags + format flags).
- `MediaRegenerateWebpCommand` is registered in artisan's `trenchwars` namespace (verified by `php artisan list trenchwars`).
- All 3 Vue components render real DOM with the dual prop shape (verified by vue-tsc + vite build success).
- i18n keys resolved at runtime (verified by NoHardcodedStringsTest + Cms/PlayerI18nKeyCoverage).

## Deferred Issues

**Imagick container rebuild remains deferred** (inherited from plan 09-01 SUMMARY). GD-with-WebP is sufficient for plan 09-09's deliverables — all 9 WebP-emitting conversions succeed under GD. Imagick is a maintenance / cross-format-coverage improvement (e.g., AVIF support, larger-image performance) but is not blocking SC-4. Filed under the existing plan 09-01 deferred list (no new deferred-items entry needed).

## Threat Flags

None. The plan's `<threat_model>` (T-09-09-01..04) covers every surface introduced:

| Threat                                                                | Component                          | Mitigation status                                                                                                |
|------------------------------------------------------------------------|------------------------------------|------------------------------------------------------------------------------------------------------------------|
| T-09-09-01 (E — Imagick CVE via malicious upload)                      | Spatie medialibrary + image driver | **MITIGATED** — Spatie validates MIME before driver call; GD driver active (Imagick deferred); D-021 container isolation. |
| T-09-09-02 (D — OOM from large upload during conversion)               | Filament upload limits + queued    | **MITIGATED** — Conversions are `->queued()`; Horizon retry envelope absorbs OOM-killed workers; Filament upload size caps per-form. |
| T-09-09-03 (I — Conversion failure leaks original filename)            | Horizon error logging              | **ACCEPT** (per plan) — operator-only log surface; original filename is set by uploader. |
| T-09-09-04 (T — WebP overwrites JPEG conversion via media:regenerate)  | Conversion namespacing             | **MITIGATED** — cover-* namespace distinct from thumb / hero / og-image; no name collision; og-image deliberately retains original format for social-scraper compat. |

No new surface beyond the threat register. No threat flags added.

## Self-Check: PASSED

**Files checked (4 created, 8 modified — 12 total):**

```
FOUND: apps/web/app/Console/Commands/MediaRegenerateWebpCommand.php   (created)
FOUND: apps/web/resources/js/components/media/ClanLogo.vue            (created)
FOUND: apps/web/resources/js/components/media/PlayerAvatar.vue        (created)
FOUND: apps/web/resources/js/components/media/ArticleCover.vue        (created)
FOUND: apps/web/app/Models/Clan.php                                   (modified)
FOUND: apps/web/app/Models/Player.php                                 (modified)
FOUND: apps/web/app/Models/Article.php                                (modified)
FOUND: apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php    (Wave 0 → 4 GREEN)
FOUND: apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php (Wave 0 → 5 GREEN)
FOUND: apps/web/tests/Feature/Models/ArticleModelTest.php             (test extended)
FOUND: apps/web/lang/en/clans.php                                     (logo_alt added)
FOUND: apps/web/lang/en/players.php                                   (avatar_alt added)
```

**Commits verified:**

```
FOUND: 932fea2 feat(09-09): WebP media conversions on Clan/Player/Article + regenerate command (Task 1)
FOUND: 58f16ae feat(09-09): ClanLogo + PlayerAvatar + ArticleCover Vue components for WebP rendering (Task 2)
```

**Stub elimination verified:**

```
$ docker compose exec -T web ./vendor/bin/pest --filter="ClanLogoWebpConversionTest|ArticleCoverWebpConversionTest" --no-coverage
  Tests: 9 passed (39 assertions) — both Wave 0 stubs turned GREEN
```

**Suite delta:**

```
Pre-plan baseline (09-08):    1260 passed + 11 skipped
Post-plan (09-09):            1269 passed +  9 skipped
                              ────────────  ──────────
                              +9 passed    −2 skipped
```

All 4 created + 8 modified files present on disk; both commits resolve in `git log`. Full suite (1269 passed + 9 skipped, 4378 assertions, 82 s) confirms no regression to Phase 1-8 or earlier Phase 9 wave-0..5 surface.
