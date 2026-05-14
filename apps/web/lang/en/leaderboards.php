<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1 i18n catalogue
| + 09-RESEARCH.md § Leaderboards.
|
| D-013 — every t()/__() consumed by Phase 9 (Pages/Leaderboards.vue, tab toggles,
| anonymous-render fallback) resolves to a key here from day one. CI gate:
| Phase9I18nKeyCoverageTest (plan 09-12).
|
| Naming: snake_case, hierarchical groups. Placeholder English copy.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Page chrome (titles, tabs, time windows)
    |--------------------------------------------------------------------------
    | Consumed by: apps/web/resources/js/Pages/Leaderboards.vue (plan 09-06)
    */
    'page' => [
        'title' => 'Leaderboards',
        'description' => 'Top performers across the league.',
    ],

    'tabs' => [
        'players' => 'Top Players',
        'clans' => 'Top Clans',
    ],

    'windows' => [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'all' => 'All time',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table column headers (player + clan leaderboards)
    |--------------------------------------------------------------------------
    */
    'columns' => [
        'rank' => 'Rank',
        'player' => 'Player',
        'clan' => 'Clan',
        'kills' => 'Kills',
        'deaths' => 'Deaths',
        'kdr' => 'K/D ratio',
        'matches' => 'Matches',
        'wins' => 'Wins',
        'losses' => 'Losses',
        'win_rate' => 'Win rate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Render fallbacks (privacy + empty state)
    |--------------------------------------------------------------------------
    | D-018 — player with PlayerPrivacy::show_stats=false renders as anonymous_player
    | label and obscured numbers.
    */
    'empty_state' => 'No leaderboard data yet — check back after the next match.',
    'anonymous_player' => 'Anonymous player',
];
