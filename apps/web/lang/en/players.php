<?php

declare(strict_types=1);

/*
| Source: 02-UI-SPEC.md § Copywriting Contract — players namespace strings.
|
| Keys for the /players/{slug} public profile page.
| Privacy gate is enforced server-side (D-018); Vue renders only received fields.
*/

return [
    'privacy' => [
        'your_profile_note' => 'Some sections of your profile are hidden from other visitors. Adjust privacy in your account settings.',
    ],

    'section' => [
        'clan_history' => 'Clan history',
        'match_history' => 'Match history',
        'stats' => 'Stats',
    ],

    'match_history' => [
        'placeholder' => 'Match data will be available once this player\'s matches are recorded.',
    ],

    'stats' => [
        'placeholder' => 'Stats will appear once matches are recorded.',
    ],
];
