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

        // Self-service privacy editor (/account/privacy) — D-018 user-controllable tiers.
        'editor' => [
            'title' => 'Profile privacy',
            'description' => 'Control who can see your profile and which sections are visible. Changes apply immediately.',
            'show_to' => [
                'label' => 'Who can see your profile',
                'help' => 'Sets the baseline audience. Individual sections below can be hidden further.',
                'options' => [
                    'public' => 'Everyone (public)',
                    'community' => 'Logged-in league members',
                    'clan' => 'My clan only',
                    'private' => 'Only me',
                ],
            ],
            'sections' => [
                'label' => 'Profile sections',
                'help' => 'Hide individual sections regardless of the audience above.',
                'show_real_name' => 'Show my real name',
                'show_discord_tag' => 'Show my Discord tag',
                'show_clan_history' => 'Show my clan history',
                'show_match_history' => 'Show my match history',
                'show_stats' => 'Show my stats',
            ],
            'save' => 'Save privacy settings',
            'saved' => 'Privacy settings saved.',
        ],
    ],

    // Phase 9 plan 09-09 — PlayerAvatar.vue alt text (WebP avatar conversions).
    // Parameter: :name (player display name).
    'avatar_alt' => ':name avatar',

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
