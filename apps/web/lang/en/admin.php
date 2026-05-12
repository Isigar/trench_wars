<?php

declare(strict_types=1);

/*
| Source: 01-UI-SPEC.md § Copywriting Contract / § Page: /admin /admin/audit
| + 01-13-PLAN.md task 1 (User/Player resource labels) + task 2 (Role/Permission resource labels).
| Extended: 02-UI-SPEC.md § Copywriting Contract — clan resource labels, audit subjects, and fields.
|
| Admin-namespace English strings — Filament chrome (via __()) + audit-page copy.
*/

return [
    'brand' => [
        'name' => 'Trenchwars',
    ],
    'audit' => [
        'nav' => 'Audit log',
        'title' => 'Audit log',
        'col' => [
            'created_at' => 'When',
            'causer' => 'Who',
            'event' => 'Event',
            'subject_type' => 'Subject',
            'subject_id' => 'Subject ID',
            'description' => 'Description',
        ],
        'empty' => [
            'heading' => 'No activity yet',
            'body' => 'Admin actions across the panel will appear here as they happen.',
        ],
        'no_activity_yet' => 'No activity logged for this record yet.',
        'event' => [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
        ],
        'filter' => [
            'event' => 'Event',
            'subject_type' => 'Subject type',
            'date_range' => 'Date range',
            'from' => 'From',
            'until' => 'Until',
        ],
        'subject' => [
            'User' => 'User',
            'Player' => 'Player',
            'Role' => 'Role',
            'Permission' => 'Permission',
            // Phase 2 additions (02-UI-SPEC.md § Filament Admin Resources)
            'Clan' => 'Clan',
            'ClanTag' => 'Tag',
            'ClanMembership' => 'Membership',
            'ClanInvite' => 'Invite',
            'ClanApplication' => 'Application',
        ],
    ],
    'tab' => [
        'profile' => 'Profile',
        'audit' => 'Audit log',
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
            'bio_locale' => 'Locale',
            'bio_text' => 'Bio text',
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

    // -------------------------------------------------------------------------
    // Phase 2 clan domain resources (02-UI-SPEC.md § Filament Admin Resources)
    // -------------------------------------------------------------------------

    'clan' => [
        'label' => 'Clan',
        'plural_label' => 'Clans',
        'section' => [
            'profile' => 'Profile',
        ],
        'fields' => [
            'name' => 'Clan name',
            'slug' => 'Slug',
            'tag' => 'Tag',
            'description' => 'Description (JSONB, locale-keyed)',
            'description_locale' => 'Locale',
            'description_text' => 'Description text',
            'country_code' => 'Country code',
            'owner' => 'Owner',
            'status' => 'Status',
            'tags' => 'Tags',
            'discord_role_id' => 'Discord role ID',
            'discord_announce_channel_id' => 'Discord announce channel ID',
        ],
        'help' => [
            'description_jsonb' => 'Translatable JSON content (locale → text). Phase 7 adds a structured locale editor.',
        ],
    ],

    'clan_tag' => [
        'label' => 'Clan tag',
        'plural_label' => 'Clan tags',
        'fields' => [
            'slug' => 'Slug',
            'label' => 'Label (JSONB, locale-keyed)',
            'label_locale' => 'Locale',
            'label_text' => 'Label text',
            'color' => 'Color',
        ],
    ],

    'clan_membership' => [
        'label' => 'Membership',
        'plural_label' => 'Memberships',
        'fields' => [
            'user' => 'User',
            'clan' => 'Clan',
            'role' => 'Role',
            'joined_at' => 'Joined',
            'left_at' => 'Left',
        ],
    ],

    'clan_invite' => [
        'label' => 'Invite',
        'plural_label' => 'Invites',
        'fields' => [
            'clan' => 'Clan',
            'user' => 'Invited user',
            'invited_by' => 'Invited by',
            'status' => 'Status',
            'message' => 'Message',
            'decided_at' => 'Decided at',
        ],
    ],

    'clan_application' => [
        'label' => 'Application',
        'plural_label' => 'Applications',
        'fields' => [
            'clan' => 'Clan',
            'user' => 'Applicant',
            'status' => 'Status',
            'message' => 'Message',
            'decided_at' => 'Decided at',
        ],
    ],
];
