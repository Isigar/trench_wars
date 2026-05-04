<?php

declare(strict_types=1);

/*
| Source: 01-UI-SPEC.md § Copywriting Contract / § Page: /admin /admin/audit
| + 01-13-PLAN.md task 1 (User/Player resource labels) + task 2 (Role/Permission resource labels).
|
| Admin-namespace English strings — Filament chrome (via __()) + audit-page copy.
*/

return [
    'brand' => [
        'name' => 'Trenchwars',
    ],
    'audit' => [
        'empty' => [
            'heading' => 'No activity yet',
            'body' => 'Admin actions across the panel will appear here as they happen.',
        ],
    ],
    'user' => [
        'label' => 'User',
        'plural_label' => 'Users',
        'fields' => [
            'discord_id' => 'Discord ID',
            'username' => 'Username',
            'email' => 'Email',
            'avatar_url' => 'Avatar URL',
            'locale' => 'Locale',
            'last_login_at' => 'Last login',
        ],
    ],
    'player' => [
        'label' => 'Player',
        'plural_label' => 'Players',
        'section' => [
            'profile' => 'Profile',
            'privacy' => 'Privacy',
        ],
        'fields' => [
            'user' => 'User',
            'slug' => 'Slug',
            'display_name' => 'Display name',
            'avatar_source' => 'Avatar source',
            'avatar_path' => 'Avatar path',
            'country_code' => 'Country code',
            'bio' => 'Bio (JSONB, locale-keyed)',
            'show_to' => 'Show to',
            'show_real_name' => 'Show real name',
            'show_discord_tag' => 'Show Discord tag',
            'show_clan_history' => 'Show clan history',
            'show_match_history' => 'Show match history',
            'show_stats' => 'Show stats',
            'created_at' => 'Created',
        ],
        'help' => [
            'bio_jsonb' => 'Translatable JSON content (locale → text). Phase 2+ adds a structured editor.',
        ],
    ],
    'role' => [
        'label' => 'Role',
        'plural_label' => 'Roles',
        'fields' => [
            'name' => 'Name',
            'guard_name' => 'Guard',
            'permissions' => 'Permissions',
            'permissions_count' => 'Permissions',
        ],
    ],
    'permission' => [
        'label' => 'Permission',
        'plural_label' => 'Permissions',
        'fields' => [
            'name' => 'Name',
            'guard_name' => 'Guard',
        ],
    ],
];
