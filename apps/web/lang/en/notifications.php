<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1 i18n catalogue
| + 09-RESEARCH.md § Notifications.
|
| D-013 — every t()/__() consumed by Phase 9 (NotificationsBell.vue,
| MatchStartingSoon::toDatabase, NotificationDispatcher, DiscordChannel payload
| body) resolves to a key here from day one. CI gate: Phase9I18nKeyCoverageTest
| (plan 09-12).
|
| Naming: snake_case, hierarchical groups, parameter interpolation via :param.
| Placeholder English copy — refine in translation pass.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Notifications bell (web UI — top-right unread indicator)
    |--------------------------------------------------------------------------
    | Consumed by: apps/web/resources/js/Components/NotificationsBell.vue (plan 09-06)
    */
    'bell' => [
        'unread_count' => 'Notifications (:count unread)',
        'empty_state' => 'You have no notifications yet.',
        'aria_open' => 'Open notifications',
        'aria_close' => 'Close notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-notification copy (subject + body + CTA strings)
    |--------------------------------------------------------------------------
    | Each notification class (App\Notifications\*) calls __('notifications.<key>')
    | for both database channel rendering and discord_outbound_messages.body.
    */
    'match_starting_soon' => [
        'title' => 'Match starting in :min minutes',
        'body' => ':opponent on :map — sign in to the server now.',
    ],

    'match_cancelled' => [
        'title' => 'Match cancelled',
        'body' => 'Your match against :opponent on :date was cancelled. Reason: :reason',
    ],

    'match_result_published' => [
        'title' => 'Match result published',
        'body' => ':winner won against :loser (:score). View the full breakdown.',
    ],

    'clan_application_decided' => [
        'approved' => [
            'title' => 'Your clan application was approved',
            'body' => 'Welcome to :clan! You are now a member.',
        ],
        'rejected' => [
            'title' => 'Your clan application was rejected',
            'body' => ':clan declined your application. Reason: :reason',
        ],
    ],

    'clan_invite_received' => [
        'title' => 'You have been invited to :clan',
        'body' => ':inviter invited you to join :clan. Open the invite to accept or decline.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Call-to-action labels (notification body buttons + bell-list actions)
    |--------------------------------------------------------------------------
    */
    'cta' => [
        'view_match' => 'View match',
        'view_clan' => 'View clan',
        'view_invite' => 'View invite',
        'mark_read' => 'Mark as read',
        'mark_all_read' => 'Mark all as read',
    ],
];
