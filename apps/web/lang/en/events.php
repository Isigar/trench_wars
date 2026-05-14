<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-01-PLAN.md task 2 — events calendar i18n
| namespace skeleton (must_haves.truths line 48).
|
| Phase 7 calendar at GET /events (FullCalendar Vue3 component). Keys are
| referenced by:
|   - EventsCalendarPage.vue header chrome   (plan 07-10) → events.header.*
|   - JSON feed legend / tooltips            (plan 07-09/07-10) → events.types.*
|   - Empty-state fallback                   (plan 07-10) → events.empty.label
|   - <head> meta (page title/description)   (plan 07-12) → events.page.*
|
| Hardcoded English strings here are authoritative; localisation happens in
| later phases (D-013 — plumbed day one, EN at launch).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Calendar chrome (FullCalendar view toggles)
    |--------------------------------------------------------------------------
    */
    'header' => [
        'title' => 'Events calendar',
        'today' => 'Today',
        'month' => 'Month',
        'week' => 'Week',
        'day' => 'Day',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event source labels (badge + legend + tooltip)
    |--------------------------------------------------------------------------
    */
    'types' => [
        'match' => [
            'label' => 'Match',
        ],
        'tournament' => [
            'label' => 'Tournament',
        ],
        'article' => [
            'label' => 'Article',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendar legend chips (plan 07-10 CalendarLegend.vue — Open Question 6 LOCKED)
    |--------------------------------------------------------------------------
    | match=#3B82F6 blue, tournament=#8B5CF6 purple, article=#10B981 green
    | (matches CalendarEventData::colourFor in 07-09; same hex values inline in
    | the Vue component so the legend never drifts from the calendar painting).
    */
    'legend' => [
        'match' => [
            'label' => 'Match',
        ],
        'tournament' => [
            'label' => 'Tournament',
        ],
        'article' => [
            'label' => 'Article',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FullCalendar toolbar buttons (plan 07-10 Events/Index.vue)
    |--------------------------------------------------------------------------
    | Wired into the FullCalendar options.buttonText slot so the prev/next/today
    | buttons render localised labels instead of FullCalendar's English defaults.
    */
    'navigation' => [
        'prev' => 'Previous',
        'next' => 'Next',
        'today' => 'Today',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty state
    |--------------------------------------------------------------------------
    */
    'empty' => [
        'label' => 'No events scheduled in this range.',
    ],

    /*
    |--------------------------------------------------------------------------
    | <head> meta (page title + description)
    |--------------------------------------------------------------------------
    */
    'page' => [
        'title' => 'Events calendar — Trenchwars',
        'description' => 'Upcoming league matches, tournament stages, and editorial events.',
    ],
];
