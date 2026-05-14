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
    | Results page chrome (07-01) + section headings + empty state (07-10)
    |--------------------------------------------------------------------------
    | The 07-01 keys (none / heading / total_count) stay as-is. Plan 07-10
    | appends flat section-heading keys (section_articles / section_clans /
    | section_players) and empty_state — kept inside the same `results` block
    | so the i18n surface for the results page is a single namespace lookup.
    */
    'results' => [
        // 07-01 legacy keys (kept verbatim).
        'none' => 'No results for ":query".',
        'heading' => 'Search results',
        'total_count' => ':count results',
        // 07-10 additions consumed by Search/Results.vue.
        'section_articles' => 'Articles',
        'section_clans' => 'Clans',
        'section_players' => 'Players',
        'empty_state' => 'Nothing matched your query — try a different term.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Section labels (UNION'd FTS sources) — legacy nested-label shape (07-01)
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
    | Header search input (plan 07-10 SearchBar.vue chrome)
    |--------------------------------------------------------------------------
    | The 07-01 search.placeholder.label key stays for back-compat; the 07-10
    | SearchBar.vue uses search.header.q_placeholder + search.header.submit.
    */
    'header' => [
        'q_placeholder' => 'Search articles, clans, players…',
        'submit' => 'Search',
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
