<?php

declare(strict_types=1);

/*
| Source: 01-UI-SPEC.md § Copywriting Contract.
| Extended: 02-UI-SPEC.md § Copywriting Contract — nav + role + close action keys.
|
| Common-namespace English strings — brand, actions, errors, theme labels, locale label.
*/

return [
    'brand' => [
        'name' => 'Trenchwars',
    ],
    'actions' => [
        'logout' => 'Log out',
        'logout_confirm' => '',
        'skip_to_content' => 'Skip to content',
        'close' => 'Close',
        'previous' => 'Previous',
        'next' => 'Next',
        'save' => 'Save',
    ],

    /*
    | Nav items added in Phase 2 (02-UI-SPEC.md § Shared layout note).
    */
    'nav' => [
        'clans' => 'Clans',
        'matches' => 'Matches',
        'tournaments' => 'Tournaments',
        'players' => 'Players',
        'my_clan' => 'My Clan',
        'my_profile' => 'My Profile',
    ],

    /*
    | Clan member role labels — used by ClanRoleBadge and any t('common.role.*') reference.
    */
    'role' => [
        'leader' => 'Leader',
        'officer' => 'Officer',
        'member' => 'Member',
        'recruit' => 'Recruit',
    ],
    'errors' => [
        'generic' => 'Something went wrong. Please try again.',
    ],
    'theme' => [
        'light' => 'Light theme',
        'dark' => 'Dark theme',
        'switch_to_light' => 'Switch to light theme',
        'switch_to_dark' => 'Switch to dark theme',
    ],
    'locale' => [
        'label' => 'Language',
    ],

    /*
    | Generic table column labels reused across Filament resources (plan 07-05).
    */
    'updated_at' => 'Updated',
    'created_at' => 'Created',
];
