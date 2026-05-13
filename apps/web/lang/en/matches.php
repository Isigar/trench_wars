<?php

declare(strict_types=1);

/*
| Source: 04-01-PLAN.md Task 1 + 04-RESEARCH.md Validation Architecture
|        + 04-11-PLAN.md <interfaces> (status.label.* + directory.* + show.* expansion).
|
| Phase 4 public match copy + service-layer error messages. Keys are referenced by:
|   - MatchSignupService exceptions       (plan 04-06) → matches.signup.error.*
|   - MatchStatusService transition guard (plan 04-04) → matches.status.error.invalid_transition
|   - MatchCalendarController + Index.vue (plan 04-10/04-11) → matches.calendar.* + matches.directory.*
|   - MatchShowController + Show.vue      (plan 04-10/04-11) → matches.show.*
|   - MatchStatusBadge                    (plan 04-11)       → matches.status.label.*
|
| Parameter interpolation: `:role`, `:from`, `:to`, `:occupied`, `:total`, `:count`.
| Hardcoded English strings here are authoritative; localisation happens in later phases
| (D-013 — plumbed day one, EN at launch).
*/

return [
    'signup' => [
        'error' => [
            'capacity_full' => 'Sorry — that role is full.',
            'tag_restricted' => 'This match is restricted to clans with specific tags. Your clan does not qualify.',
            'already_signed_up' => 'You are already signed up to this match.',
            'not_open' => 'Signups for this match are not open.',
            'no_active_clan' => 'You must be an active member of a clan to sign up for a match.',
        ],
        'success' => 'Signed up to :role.',
        'cancelled' => 'Your signup has been cancelled.',
    ],

    'status' => [
        'error' => [
            'invalid_transition' => 'Cannot transition match status from :from to :to.',
        ],
        // Labels rendered by MatchStatusBadge.vue (plan 04-11).
        'label' => [
            'draft' => 'Draft',
            'open' => 'Open',
            'locked' => 'Locked',
            'played' => 'Played',
            'cancelled' => 'Cancelled',
        ],
    ],

    'calendar' => [
        'title' => 'Match calendar',
        'empty' => 'No upcoming matches yet — check back soon.',
    ],

    // Directory page (Matches/Index.vue — plan 04-11).
    'directory' => [
        'title' => 'Match calendar',
        'subtitle' => 'Upcoming and recent matches across the league.',
        'empty_default' => 'No upcoming matches yet — check back soon.',
        'empty_results' => 'No matches match your filters.',
        'filter_clear' => 'Clear filters',
        'filter_date_from_label' => 'From date',
        'filter_date_to_label' => 'To date',
        'filter_status_label' => 'Status',
        'filter_status_any' => 'Any status',
        'filter_tag_label' => 'Filter by tag',
        'pagination_prev' => 'Previous',
        'pagination_next' => 'Next',
        'pagination_page_indicator' => 'Page :current of :last',
        'signup_summary' => ':occupied / :total signed up',
    ],

    // Detail page (Matches/Show.vue — plan 04-11).
    'show' => [
        'title_fallback' => 'Match',
        'signup_button' => 'Sign up',
        'cancel_signup_button' => 'Cancel signup',
        'slot_open' => 'Open',
        'slot_taken_anonymous' => 'Anonymous',
        'you_marker' => '(you)',
        'role_unknown' => 'Unknown role',
        'role_section_header' => ':role',
        'tag_restricted_notice' => 'This match is restricted to clans with specific tags.',
        'scheduled_at_label' => 'Scheduled',
        'host_clan_label' => 'Hosted by',
        'description_heading' => 'About this match',
        'no_roles_yet' => 'Slots have not been created for this match yet.',
        'login_to_signup' => 'Log in to sign up.',
    ],
];
