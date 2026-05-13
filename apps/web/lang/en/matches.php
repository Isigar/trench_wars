<?php

declare(strict_types=1);

/*
| Source: 04-01-PLAN.md Task 1 + 04-RESEARCH.md Validation Architecture.
|
| Phase 4 public match copy + service-layer error messages. Keys are referenced by:
|   - MatchSignupService exceptions       (plan 04-06) → matches.signup.error.*
|   - MatchStatusService transition guard (plan 04-04) → matches.status.error.invalid_transition
|   - MatchCalendarController + Index.vue (plan 04-10/04-11) → matches.calendar.*
|   - MatchShowController + Show.vue      (plan 04-10/04-11) → matches.show.*
|
| Parameter interpolation: `:role`, `:from`, `:to`. Hardcoded English strings here are
| authoritative; localisation happens in later phases (D-013 — plumbed day one, EN at launch).
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
    ],

    'status' => [
        'error' => [
            'invalid_transition' => 'Cannot transition match status from :from to :to.',
        ],
    ],

    'calendar' => [
        'title' => 'Match calendar',
        'empty' => 'No upcoming matches yet — check back soon.',
    ],

    'show' => [
        'title_fallback' => 'Match',
        'signup_button' => 'Sign up',
        'cancel_signup_button' => 'Cancel signup',
        'slot_open' => 'Open',
        'slot_taken_anonymous' => 'Taken',
    ],
];
