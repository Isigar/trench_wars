<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1 i18n catalogue
| + 09-RESEARCH.md § Accessibility (WCAG 2.1 AA).
|
| D-013 — every aria-label, sr-only span, and "Skip to content" link in
| apps/web/resources/js/** resolves to a key here. The file itself IS the `a11y`
| namespace (per plan 09-01 task 1 note — keys at the root level, not nested
| under `a11y.*`). CI gate: Phase9I18nKeyCoverageTest (plan 09-12).
|
| Naming: snake_case, hierarchical groups. Screen-reader-friendly English.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Skip link (first focusable element on every public page)
    |--------------------------------------------------------------------------
    */
    'skip_to_content' => 'Skip to main content',

    /*
    |--------------------------------------------------------------------------
    | Notifications bell aria labels
    |--------------------------------------------------------------------------
    | Consumed by: apps/web/resources/js/Components/NotificationsBell.vue (plan 09-06)
    | Pairs with notifications.bell.* keys; this namespace covers the screen-reader-only spans.
    */
    'notifications' => [
        'bell_label' => 'Notifications (:count unread)',
        'mark_read' => 'Mark notification as read',
        'mark_all_read' => 'Mark all notifications as read',
    ],

    /*
    |--------------------------------------------------------------------------
    | Site navigation (mobile menu + dropdown toggles)
    |--------------------------------------------------------------------------
    */
    'menu' => [
        'toggle_open' => 'Open navigation menu',
        'toggle_close' => 'Close navigation menu',
    ],

    /*
    |--------------------------------------------------------------------------
    | BulkAction row selection (Filament tables + admin lists)
    |--------------------------------------------------------------------------
    */
    'bulk_action' => [
        'select_row' => 'Select this row',
        'select_all' => 'Select all rows',
        'deselect_all' => 'Deselect all rows',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic interactive labels (icon-only buttons across the app)
    |--------------------------------------------------------------------------
    */
    'icon_button' => [
        'close' => 'Close',
        'open' => 'Open',
        'search' => 'Search',
    ],
];
