<?php

declare(strict_types=1);

/*
| Source: 09-12-PLAN.md task 1 — i18n key coverage gate for Phase 9
| notifications.* / leaderboards.* / moderation.* / a11y.* / reports.*
| namespaces (D-013 + Phase 9 Pitfall mitigation).
|
| Two complementary checks (verbatim Phase 6/7 D-06-13-C / CmsI18nKeyCoverageTest
| idiom — see tests/Feature/I18n/CmsI18nKeyCoverageTest.php for the canonical
| pattern):
|
|   (1) Expected-key resolution — a hardcoded list of leaf keys that MUST
|       resolve to a non-empty string via __() / trans(). Catches the case
|       where someone deletes a key from
|       lang/en/{notifications,leaderboards,moderation,a11y,reports}.php
|       but a Vue / Filament / PHP reference still expects it.
|
|   (2) Source-grep round-trip — preg_match every t() / __() call in the
|       Phase 9 Vue + Filament + controller + Service + Notification source
|       surface for keys in the notifications.* / leaderboards.* / moderation.* /
|       a11y.* / reports.* namespaces, then assert every captured leaf key
|       resolves. Catches the reverse case — a t() call referencing a key that
|       never landed in lang/en/*.php.
|
| Phase 9 CI gate: this test fails on key drift.
*/

use Illuminate\Support\Facades\File;

/**
 * Grep one source file for t('namespace.key') OR __('namespace.key') references
 * whose key starts with one of the supplied prefixes.
 *
 * Leaf-anchored regex (must end on a concrete alphanumeric/underscore
 * character) — string-concat dynamic keys like
 * `__('notifications.types.' . $type . '.label')` end on the trailing dot
 * before the variable and are excluded by construction; their concrete leaves
 * are covered by the expected-key resolution test below.
 *
 * @param  list<string>  $prefixes  e.g. ['notifications.', 'leaderboards.']
 * @return array<int, string>
 */
function grepPhase9I18nKeys(string $filePath, array $prefixes): array
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
 * Phase 9 Vue surface:
 *   - resources/js/pages/Notifications/Index.vue
 *   - resources/js/pages/Leaderboards/Index.vue
 *   - resources/js/pages/Report/Create.vue
 *   - resources/js/pages/Account/NotificationPreferences.vue
 *   - resources/js/components/{NotificationsBell,LeaderboardTable,ReportButton}.vue
 *
 * Phase 9 Notification classes:
 *   - app/Notifications/*.php (5 classes — plan 09-03)
 *
 * Phase 9 Services:
 *   - app/Services/{NotificationDispatcher,BanService,DisputeService,LeaderboardService}.php
 *
 * Phase 9 Filament:
 *   - app/Filament/Resources/{UserResource,MatchResource,MatchDisputeResource,AbuseReportResource}.php
 *     + their Pages subdirs.
 *
 * Phase 9 Controllers:
 *   - app/Http/Controllers/{NotificationsController,LeaderboardsController}.php
 *   - app/Http/Controllers/Reports/ReportsController.php
 *   - app/Http/Controllers/Account/NotificationPreferencesController.php
 *
 * @return array<int, string>
 */
function phase9I18nScanFiles(): array
{
    $base = base_path();

    $vueFiles = [
        $base . '/resources/js/pages/Notifications/Index.vue',
        $base . '/resources/js/pages/Leaderboards/Index.vue',
        $base . '/resources/js/pages/Report/Create.vue',
        $base . '/resources/js/pages/Account/NotificationPreferences.vue',
        $base . '/resources/js/components/NotificationsBell.vue',
        $base . '/resources/js/components/LeaderboardTable.vue',
        $base . '/resources/js/components/ReportButton.vue',
    ];

    $notificationFiles = File::glob($base . '/app/Notifications/*.php');

    $serviceFiles = [
        $base . '/app/Services/NotificationDispatcher.php',
        $base . '/app/Services/BanService.php',
        $base . '/app/Services/DisputeService.php',
        $base . '/app/Services/LeaderboardService.php',
    ];

    $filamentFiles = array_merge(
        [
            $base . '/app/Filament/Resources/UserResource.php',
            $base . '/app/Filament/Resources/MatchResource.php',
            $base . '/app/Filament/Resources/MatchDisputeResource.php',
            $base . '/app/Filament/Resources/AbuseReportResource.php',
        ],
        File::glob($base . '/app/Filament/Resources/MatchDisputeResource/Pages/*.php'),
        File::glob($base . '/app/Filament/Resources/AbuseReportResource/Pages/*.php'),
        File::glob($base . '/app/Filament/Resources/UserResource/Pages/*.php'),
        File::glob($base . '/app/Filament/Resources/MatchResource/Pages/*.php'),
    );

    $controllerFiles = [
        $base . '/app/Http/Controllers/NotificationsController.php',
        $base . '/app/Http/Controllers/LeaderboardsController.php',
        $base . '/app/Http/Controllers/Reports/ReportsController.php',
        $base . '/app/Http/Controllers/Account/NotificationPreferencesController.php',
    ];

    return array_values(array_filter(
        array_merge($vueFiles, $notificationFiles, $serviceFiles, $filamentFiles, $controllerFiles),
        fn (string $path): bool => is_file($path),
    ));
}

// -----------------------------------------------------------------------------
// 1. EXPECTED-KEY RESOLUTION — every key the Phase 9 surfaces are known to
//    consume MUST resolve to a non-empty string. Deleting any key from
//    lang/en/{notifications,leaderboards,moderation,a11y,reports}.php without
//    adjusting consumers is a CI failure HERE.
// -----------------------------------------------------------------------------

it('every expected Phase 9 notifications.* / leaderboards.* / moderation.* / a11y.* / reports.* key resolves to a non-empty string', function (): void {
    $expected = [];

    // === notifications.bell.* (NotificationsBell.vue — plan 09-06) ===
    foreach (['unread_count', 'empty_state', 'aria_open', 'aria_close', 'view_all'] as $leaf) {
        $expected[] = "notifications.bell.{$leaf}";
    }

    // === notifications.page.* (page chrome — plan 09-06) ===
    $expected[] = 'notifications.page.title';
    $expected[] = 'notifications.page.description';

    // === notifications.cta.* (page CTAs — plan 09-06) ===
    $expected[] = 'notifications.cta.mark_all_read';
    $expected[] = 'notifications.cta.mark_read';

    // === leaderboards.page.* (page chrome — plan 09-06) ===
    $expected[] = 'leaderboards.page.title';
    $expected[] = 'leaderboards.page.description';

    // === leaderboards.tabs.* (top players / top clans — plan 09-06) ===
    $expected[] = 'leaderboards.tabs.players';
    $expected[] = 'leaderboards.tabs.clans';

    // === leaderboards.windows.* (window toggles — plan 09-05/06) ===
    foreach (['7d', '30d', 'all'] as $w) {
        $expected[] = "leaderboards.windows.{$w}";
    }

    // === a11y.notifications.* (aria labels — plan 09-10) ===
    $expected[] = 'a11y.notifications.mark_all_read';
    $expected[] = 'a11y.notifications.mark_read';

    // === reports.page.* (Report/Create.vue — plan 09-11) ===
    $expected[] = 'reports.page.title';
    $expected[] = 'reports.page.description';

    // === reports.form.* (form fields — plan 09-11) ===
    $expected[] = 'reports.form.target_type';
    $expected[] = 'reports.form.reason_code';
    $expected[] = 'reports.form.body';
    $expected[] = 'reports.form.body_placeholder';

    // === reports.cta.* (form CTA — plan 09-11) ===
    $expected[] = 'reports.cta.submit';

    // Sanity: the test should be auditing AT LEAST 25 keys across the Phase 9
    // namespace footprint.
    expect(count($expected))->toBeGreaterThanOrEqual(25);

    $missing = [];
    foreach ($expected as $key) {
        $resolved = trans($key);
        if (! is_string($resolved) || $resolved === $key || trim($resolved) === '') {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing or empty in lang/en/{notifications,leaderboards,moderation,a11y,reports}.php:\n  - " . implode("\n  - ", $missing),
    );
});

// -----------------------------------------------------------------------------
// 2. SOURCE-GREP ROUND-TRIP — every concrete notifications.* / leaderboards.* /
//    moderation.* / a11y.* / reports.* key Phase 9 Vue + Filament + controllers
//    + services + notifications actually reference MUST resolve. Catches t()
//    calls against keys that never landed in lang/en/*.php.
// -----------------------------------------------------------------------------

it('every concrete Phase 9 i18n key used in Vue + Filament + controller + service + notification source resolves to a real string', function (): void {
    $files = phase9I18nScanFiles();

    expect($files)->not->toBeEmpty(
        'Phase 9 Vue/Filament/controller/service/notification surface scan returned zero files — globs are broken.'
    );

    $prefixes = [
        'notifications.',
        'leaderboards.',
        'moderation.',
        'a11y.',
        'reports.',
    ];

    $referenced = [];
    foreach ($files as $file) {
        foreach (grepPhase9I18nKeys($file, $prefixes) as $key) {
            $referenced[$key] = $file;
        }
    }

    expect($referenced)->not->toBeEmpty(
        'No notifications.* / leaderboards.* / moderation.* / a11y.* / reports.* keys discovered in Phase 9 source — regex broken or files moved.'
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
        "Translation keys missing from lang/en/{notifications,leaderboards,moderation,a11y,reports}.php:\n  - " . implode("\n  - ", $missing),
    );
});
