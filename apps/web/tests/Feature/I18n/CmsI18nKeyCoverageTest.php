<?php

declare(strict_types=1);

/*
| Source: 07-12-PLAN.md task 2 — i18n key coverage gate for Phase 7 CMS +
| events + search + admin.article*.* / admin.category*.* namespaces (D-013 +
| Pitfall 10 mitigation).
|
| Two complementary checks (verbatim Phase 6 D-06-13-C idiom — see
| tests/Feature/I18n/TournamentI18nKeyCoverageTest.php for the canonical
| pattern):
|
|   (1) Expected-key resolution — a hardcoded list of leaf keys that MUST
|       resolve to a non-empty string via __() / trans(). Catches the case
|       where someone deletes a key from lang/en/{cms,events,search,admin}.php
|       but a Vue / Filament / PHP reference still expects it.
|
|   (2) Source-grep round-trip — preg_match every t() / __() call in the
|       Phase 7 Vue + Filament + controller source surface for keys in the
|       cms.* / events.* / search.* / admin.article*.* / admin.category*.*
|       namespaces, then assert every captured leaf key resolves. Catches the
|       reverse case — a t() call referencing a key that never landed in
|       lang/en/*.php.
|
| Pitfall 10 (i18n key drift across the editor + scheduler + meta-tag + audit
| matrix) is mitigated by THIS test — CI fails on key drift.
*/

use Illuminate\Support\Facades\File;

/**
 * Grep one source file for t('namespace.key') OR __('namespace.key') references
 * whose key starts with one of the supplied prefixes.
 *
 * Accepts BOTH single and double quotes. Accepts BOTH t( and __( call shapes
 * (the bare-`t(` covers Vue's auto-imported useT composable; `__(` covers
 * PHP / Filament code paths).
 *
 * Leaf-anchored regex (must end on a concrete alphanumeric/underscore
 * character) — string-concat dynamic keys like
 * `__('cms.status.' . $record->status . '.label')` end on the trailing dot
 * before the variable and are excluded by construction; their concrete leaves
 * are covered by the expected-key resolution test below.
 *
 * @param  list<string>  $prefixes  e.g. ['cms.', 'events.']
 * @return array<int, string>
 */
function grepCmsI18nKeys(string $filePath, array $prefixes): array
{
    $contents = (string) file_get_contents($filePath);

    $alternatives = implode('|', array_map(
        fn (string $p): string => preg_quote($p, '/'),
        $prefixes,
    ));

    $pattern = '/(?:\bt|__)\(\s*(["\'])((?:' . $alternatives . ')[a-z0-9_.]*[a-z0-9_])\1/i';
    preg_match_all($pattern, $contents, $matches);

    /** @var array<int, string> $keys */
    $keys = $matches[2];

    return array_values(array_unique($keys));
}

/**
 * Resolve the absolute paths of all files this test scans.
 *
 * Phase 7 Vue surface (plan 07-10 + 07-12):
 *   - resources/js/pages/Articles/*.vue (Index, Show)
 *   - resources/js/pages/Events/*.vue   (Index)
 *   - resources/js/pages/Search/*.vue   (Results)
 *   - resources/js/components/cms/*.vue (ArticleCard, CategoryFilterPill,
 *     CalendarLegend, SearchBar, etc.)
 *
 * Phase 7 Filament surface (plan 07-05):
 *   - app/Filament/Resources/ArticleResource.php
 *   - app/Filament/Resources/ArticleResource/Pages/*.php
 *   - app/Filament/Resources/CategoryResource.php
 *   - app/Filament/Resources/CategoryResource/Pages/*.php
 *
 * Phase 7 controller surface (plan 07-09 + 07-10):
 *   - app/Http/Controllers/BlogIndexController.php
 *   - app/Http/Controllers/BlogShowController.php
 *   - app/Http/Controllers/EventsCalendarController.php
 *   - app/Http/Controllers/SearchController.php
 *
 * @return array<int, string>
 */
function cmsI18nScanFiles(): array
{
    return array_values(array_filter(array_merge(
        File::glob(base_path('resources/js/pages/Articles/*.vue')),
        File::glob(base_path('resources/js/pages/Events/*.vue')),
        File::glob(base_path('resources/js/pages/Search/*.vue')),
        File::glob(base_path('resources/js/components/cms/*.vue')),
        [base_path('app/Filament/Resources/ArticleResource.php')],
        File::glob(base_path('app/Filament/Resources/ArticleResource/Pages/*.php')),
        [base_path('app/Filament/Resources/CategoryResource.php')],
        File::glob(base_path('app/Filament/Resources/CategoryResource/Pages/*.php')),
        [
            base_path('app/Http/Controllers/BlogIndexController.php'),
            base_path('app/Http/Controllers/BlogShowController.php'),
            base_path('app/Http/Controllers/EventsCalendarController.php'),
            base_path('app/Http/Controllers/SearchController.php'),
        ],
    ), fn (string $path): bool => is_file($path)));
}

// -----------------------------------------------------------------------------
// 1. EXPECTED-KEY RESOLUTION — every key the Phase 7 surfaces are known to
//    consume MUST resolve to a non-empty string. Deleting any key from
//    lang/en/{cms,events,search,admin}.php without adjusting consumers is a
//    CI failure HERE.
// -----------------------------------------------------------------------------

it('every expected Phase 7 cms.* / events.* / search.* / admin.{article,category}.* key resolves to a non-empty string', function (): void {
    $expected = [];

    // === cms.status.* (3 statuses × 2 leaves = 6) ===
    foreach (['draft', 'scheduled', 'published'] as $status) {
        foreach (['label', 'badge_class'] as $leaf) {
            $expected[] = "cms.status.{$status}.{$leaf}";
        }
    }

    // === cms.actions.* (3 actions × 3 leaves = 9) ===
    foreach (['publish_now', 'schedule', 'unpublish'] as $action) {
        foreach (['label', 'modal_heading', 'success'] as $leaf) {
            $expected[] = "cms.actions.{$action}.{$leaf}";
        }
    }

    // === cms.fields.* (9 fields × 2 leaves = 18) ===
    foreach ([
        'title', 'slug', 'excerpt', 'body', 'hero',
        'category_id', 'scheduled_at', 'published_at', 'allow_discord_announce',
    ] as $field) {
        foreach (['label', 'help'] as $leaf) {
            $expected[] = "cms.fields.{$field}.{$leaf}";
        }
    }

    // === cms.errors.* (5 error strings) ===
    foreach ([
        'slug_taken',
        'invalid_status_transition',
        'scheduled_at_in_past',
        'tiptap_unsafe_node',
        'category_in_use',
    ] as $err) {
        $expected[] = "cms.errors.{$err}";
    }

    // === cms.empty.* (4 empty states × 1 leaf each) ===
    foreach (['articles', 'categories', 'search', 'events'] as $emp) {
        $expected[] = "cms.empty.{$emp}.label";
    }

    // === cms.blog.* (public Vue chrome) ===
    $expected[] = 'cms.blog.empty.label';
    $expected[] = 'cms.blog.pagination.prev';
    $expected[] = 'cms.blog.pagination.next';
    $expected[] = 'cms.blog.read_more.label';
    $expected[] = 'cms.blog.category_filter.label';
    $expected[] = 'cms.blog.category_filter.all';

    // === cms.article.* (show page meta strip + hero alt) ===
    $expected[] = 'cms.article.meta.published_on';
    $expected[] = 'cms.article.meta.author';
    $expected[] = 'cms.article.meta.category';
    $expected[] = 'cms.article.hero_alt.label';

    // === cms.page_meta.* (consumed by Inertia <Head> in plan 07-12) ===
    $expected[] = 'cms.page_meta.blog_index.title';
    $expected[] = 'cms.page_meta.blog_index.description';
    $expected[] = 'cms.page_meta.blog_show.title_template';
    $expected[] = 'cms.page_meta.blog_show.description_fallback';
    $expected[] = 'cms.page_meta.events.title';
    $expected[] = 'cms.page_meta.events.description';
    $expected[] = 'cms.page_meta.search.title';
    $expected[] = 'cms.page_meta.search.description';

    // === events.* (FullCalendar chrome + types + legend + navigation + empty + page) ===
    foreach (['title', 'today', 'month', 'week', 'day'] as $leaf) {
        $expected[] = "events.header.{$leaf}";
    }
    foreach (['match', 'tournament', 'article'] as $type) {
        $expected[] = "events.types.{$type}.label";
        $expected[] = "events.legend.{$type}.label";
    }
    foreach (['prev', 'next', 'today'] as $leaf) {
        $expected[] = "events.navigation.{$leaf}";
    }
    $expected[] = 'events.empty.label';
    $expected[] = 'events.page.title';
    $expected[] = 'events.page.description';

    // === search.* (UI + results + sections) ===
    $expected[] = 'search.placeholder.label';
    $expected[] = 'search.results.none';
    $expected[] = 'search.results.heading';
    $expected[] = 'search.results.total_count';
    $expected[] = 'search.results.section_articles';
    $expected[] = 'search.results.section_clans';
    $expected[] = 'search.results.section_players';
    $expected[] = 'search.results.empty_state';
    $expected[] = 'search.sections.articles.label';
    $expected[] = 'search.sections.clans.label';
    $expected[] = 'search.sections.players.label';
    $expected[] = 'search.header.q_placeholder';
    $expected[] = 'search.header.submit';
    $expected[] = 'search.page.title';

    // === admin.article.* (Filament ArticleResource — plan 07-05) ===
    foreach (['label', 'plural_label', 'nav'] as $leaf) {
        $expected[] = "admin.article.{$leaf}";
    }
    foreach ([
        'title', 'slug', 'excerpt', 'body', 'hero_media_id',
        'category_id', 'status', 'scheduled_at', 'published_at',
        'author_user_id', 'allow_discord_announce',
    ] as $field) {
        $expected[] = "admin.article.fields.{$field}";
    }
    $expected[] = 'admin.article.publication.section';
    $expected[] = 'admin.article.publication.help';

    // === admin.category.* (Filament CategoryResource — plan 07-05) ===
    foreach (['label', 'plural_label', 'nav'] as $leaf) {
        $expected[] = "admin.category.{$leaf}";
    }
    foreach (['name', 'slug', 'description'] as $field) {
        $expected[] = "admin.category.fields.{$field}";
    }

    // Sanity: the test should be auditing AT LEAST 80 keys (the Phase 7 surface
    // is materially larger than Phase 6's per-namespace footprint).
    expect(count($expected))->toBeGreaterThanOrEqual(80);

    $missing = [];
    foreach ($expected as $key) {
        $resolved = trans($key);
        if (! is_string($resolved) || $resolved === $key || trim($resolved) === '') {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing or empty in lang/en/{cms,events,search,admin}.php:\n  - " . implode("\n  - ", $missing),
    );
});

// -----------------------------------------------------------------------------
// 2. SOURCE-GREP ROUND-TRIP — every concrete cms.* / events.* / search.* /
//    admin.{article,category}.* key Phase 7 Vue + Filament + controllers
//    actually reference MUST resolve. Catches t() calls against keys that
//    never landed in lang/en/*.php.
// -----------------------------------------------------------------------------

it('every concrete Phase 7 i18n key used in Vue + Filament + controller source resolves to a real string', function (): void {
    $files = cmsI18nScanFiles();

    expect($files)->not->toBeEmpty(
        'Phase 7 Vue/Filament/controller surface scan returned zero files — globs are broken.'
    );

    $prefixes = [
        'cms.',
        'events.',
        'search.',
        'admin.article.',
        'admin.category.',
    ];

    $referenced = [];
    foreach ($files as $file) {
        foreach (grepCmsI18nKeys($file, $prefixes) as $key) {
            $referenced[$key] = $file;
        }
    }

    expect($referenced)->not->toBeEmpty(
        'No cms.* / events.* / search.* / admin.{article,category}.* keys discovered in Phase 7 source — regex broken or files moved.'
    );

    $missing = [];
    foreach ($referenced as $key => $file) {
        $resolved = trans($key);
        if (! is_string($resolved) || $resolved === $key || trim($resolved) === '') {
            $missing[] = sprintf('%s (referenced by %s)', $key, str_replace(base_path() . '/', '', $file));
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing from lang/en/{cms,events,search,admin}.php:\n  - " . implode("\n  - ", $missing),
    );
});
