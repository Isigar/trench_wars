<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-01-PLAN.md task 2 — header search bar +
| /search results page i18n namespace (must_haves.truths line 49).
|
| Phase 7 Postgres FTS UNION across articles + clans + players, with D-018
| PlayerPrivacyGate applied to the players section. Keys are referenced by:
|   - Header search input (placeholder)     (plan 07-10) → search.placeholder.label
|   - /search results page                  (plan 07-10) → search.results.*, search.sections.*
|   - <head> meta                           (plan 07-12) → search.page.title
|
| Hardcoded English strings here are authoritative; localisation happens in
| later phases (D-013 — plumbed day one, EN at launch).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Header search input
    |--------------------------------------------------------------------------
    */
    'placeholder' => [
        'label' => 'Search articles, clans, players…',
    ],

    /*
    |--------------------------------------------------------------------------
    | Results page chrome
    |--------------------------------------------------------------------------
    */
    'results' => [
        'none' => 'No results for ":query".',
        'heading' => 'Search results',
        'total_count' => ':count results',
    ],

    /*
    |--------------------------------------------------------------------------
    | Section labels (UNION'd FTS sources)
    |--------------------------------------------------------------------------
    */
    'sections' => [
        'articles' => [
            'label' => 'Articles',
        ],
        'clans' => [
            'label' => 'Clans',
        ],
        'players' => [
            'label' => 'Players',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | <head> meta
    |--------------------------------------------------------------------------
    */
    'page' => [
        'title' => 'Search — Trenchwars',
    ],
];
