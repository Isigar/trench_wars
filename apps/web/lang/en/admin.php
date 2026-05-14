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
            // Phase 3 additions (03-01-PLAN.md task 2; 03-CONTEXT.md § Phase Boundary).
            'Game' => 'Game',
            'GameRole' => 'Game role',
            'GameMatchType' => 'Match type',
            'GameMatchTypeRoleLimit' => 'Role capacity',
            // Phase 8 additions (08-01-PLAN.md task 2; 08-CONTEXT.md § Specifics).
            'MatchServer' => 'Match server',
            'MatchServerBooking' => 'Server booking',
            'MatchEvent' => 'Match event',
            'MatchPlayerStat' => 'Player stat',
        ],
        // Phase 8 audit-line copy (08-01-PLAN.md task 2) — Filament activity_log
        // descriptions for MatchServerResource actions. Consumed by plan 08-09.
        'match_servers' => [
            'test_queued' => 'Test connection queued',
            'test_ok' => 'Test connection succeeded',
            'test_error' => 'Test connection failed',
            'rotated_password' => 'CRCON password rotated',
        ],
    ],
    'tab' => [
        'profile' => 'Profile',
        'audit' => 'Audit log',
    ],
    'nav' => [
        // Filament navigationGroup() handle for the Phase 3 Game / GameMatchType resources
        // (03-01-PLAN.md task 2 + 03-RESEARCH.md Pitfall 8). Harmless if unused; required
        // so __()'d nav labels in plans 03-06/03-07 do not raise MissingTranslationException.
        'platform' => 'Platform',
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
            'discord_announce_channel_id_help' => 'Discord channel snowflake — copy from Channel Settings → Edit Channel → Advanced → Channel ID. Bot needs send + embed perms.',
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

    // Added by plan 02-13 task 2 (Rule 2 — plan 02-06 missed this key group).
    'discord_guild' => [
        'label' => 'Discord guild',
        'plural_label' => 'Discord guild',
        'fields' => [
            'guild_id' => 'Discord guild ID',
            'name' => 'Guild name',
            'icon_url' => 'Icon URL',
        ],
    ],

    // -------------------------------------------------------------------------
    // Phase 3 game domain resources (03-CONTEXT.md § Phase Boundary, 03-01-PLAN.md task 2)
    // Placeholder English copy — finalised by plan 03-09 i18n audit.
    // Key shapes mirror the existing clan_* groups so Filament v3 resources in
    // plans 03-06/03-07 can call __() without raising MissingTranslationException.
    // -------------------------------------------------------------------------

    'game' => [
        'label' => 'Game',
        'plural_label' => 'Games',
        'section' => [
            'profile' => 'Profile',
            'roles' => 'Roles',
            'match_types' => 'Match types',
        ],
        'fields' => [
            'key' => 'Key',
            'name' => 'Name',
            'name_locale' => 'Locale',
            'name_text' => 'Display name',
            'is_active' => 'Active',
        ],
        'help' => [
            'key_format' => 'Lowercase letters, digits, and underscores only.',
        ],
        'tab' => [
            'profile' => 'Profile',
            'roles' => 'Roles',
            'match_types' => 'Match types',
            'audit' => 'Audit',
        ],
    ],

    'game_role' => [
        'label' => 'Game role',
        'plural_label' => 'Roles',
        'fields' => [
            'key' => 'Key',
            'display_name' => 'Display name',
            'display_name_locale' => 'Locale',
            'display_name_text' => 'Translation',
            'sort_order' => 'Sort order',
            'is_active' => 'Active',
        ],
        'help' => [
            'key_format' => 'Lowercase letters, digits, and underscores only.',
        ],
    ],

    'game_match_type' => [
        'label' => 'Match type',
        'plural_label' => 'Match types',
        'section' => [
            'profile' => 'Profile',
            'role_limits' => 'Role capacity',
        ],
        'fields' => [
            'key' => 'Key',
            'name' => 'Name',
            'name_locale' => 'Locale',
            'name_text' => 'Translation',
            'description' => 'Description',
            'description_locale' => 'Locale',
            'description_text' => 'Translation',
            'is_active' => 'Active',
            'game' => 'Game',
        ],
        'help' => [
            'key_format' => 'Lowercase letters, digits, and underscores only.',
        ],
        'tab' => [
            'profile' => 'Profile',
            'role_limits' => 'Role capacity',
            'audit' => 'Audit',
        ],
    ],

    'game_match_type_role_limit' => [
        'label' => 'Role capacity',
        'plural_label' => 'Role capacities',
        'fields' => [
            'role' => 'Role',
            'capacity' => 'Capacity',
            'sort_order' => 'Sort order',
        ],
        'help' => [
            'capacity_min_zero' => 'Capacity must be zero or greater.',
            'role_scope' => 'Roles are scoped to the parent game.',
        ],
    ],

    // -------------------------------------------------------------------------
    // Phase 4 match + event domain resources (04-RESEARCH.md Pattern 1, 04-01-PLAN.md task 1).
    // Consumed by MatchResource + 4 RelationManagers + EventResource in plans 04-09/04-12.
    // Key shapes mirror the existing game/clan groups so Filament v3 resources can call
    // __() without raising MissingTranslationException; placeholder copy until 04-12 audit.
    // -------------------------------------------------------------------------

    'match' => [
        'label' => 'Match',
        'plural_label' => 'Matches',
        'section' => [
            'profile' => 'Profile',
            'audit' => 'Audit',
        ],
        'wizard' => [
            'step_type' => 'Match type',
            'step_type_desc' => 'Pick the game and match type — this drives slot materialisation.',
            'step_schedule' => 'Schedule',
            'step_schedule_desc' => 'When and where the match runs, plus host clan + visibility.',
            'step_review' => 'Review',
            'step_review_desc' => 'Confirm and create. Slots and the calendar event are generated automatically.',
        ],
        'fields' => [
            'game_match_type' => 'Match type',
            'organiser' => 'Organiser',
            'host_clan' => 'Host clan',
            'server_address' => 'Server address',
            'scheduled_at' => 'Scheduled at',
            'status' => 'Status',
            'is_public' => 'Public',
            'title' => 'Title',
            'description' => 'Description',
        ],
        'actions' => [
            'open_signups' => 'Open signups',
            'lock_signups' => 'Lock signups',
            'cancel' => 'Cancel match',
        ],
    ],

    'match_slot' => [
        'label' => 'Slot',
        'plural_label' => 'Slots',
        'fields' => [
            'game_role' => 'Role',
            'slot_index' => 'Slot #',
            'occupant_user' => 'Occupant',
            'confirmed_at' => 'Confirmed at',
        ],
    ],

    'match_access_rule' => [
        'label' => 'Access rule',
        'plural_label' => 'Access rules',
        'fields' => [
            'clan_tag' => 'Clan tag',
        ],
        // Pattern 5 UX: when no rules are present the match is open to all clans.
        // Surfaced by AccessRulesRelationManager via ->emptyStateHeading() (plan 04-09).
        'empty_heading' => 'No access restrictions — this match is open to all clans.',
    ],

    'match_result' => [
        'label' => 'Result',
        'plural_label' => 'Results',
        'fields' => [
            'winner_clan' => 'Winning clan',
            'allies_score' => 'Allies score',
            'axis_score' => 'Axis score',
            'notes' => 'Notes',
            'recorded_by' => 'Recorded by',
            'recorded_at' => 'Recorded at',
        ],
    ],

    'match_mvp' => [
        'label' => 'MVP',
        'plural_label' => 'MVPs',
        'fields' => [
            'player' => 'Player',
            'category' => 'Category',
            'value' => 'Value',
        ],
    ],

    'event' => [
        'label' => 'Event',
        'plural_label' => 'Events',
        'fields' => [
            'eventable' => 'Source',
            'starts_at' => 'Starts at',
            'ends_at' => 'Ends at',
            'title' => 'Title',
            'is_public' => 'Public',
        ],
    ],

    // -------------------------------------------------------------------------
    // Phase 5 Discord bot v1 (05-01-PLAN.md task 2 Wave 0 RED stub).
    // Consumed by DiscordOutboundMessageResource (Filament) in plan 05-07.
    // Resource is read-only + a Retry table action; no Create/Edit.
    // -------------------------------------------------------------------------
    'discord_outbound_message' => [
        'label' => 'Outbound message',
        'plural_label' => 'Outbound messages',
        'fields' => [
            'message_type' => 'Type',
            'status' => 'Status',
            'channel_id' => 'Channel',
            'attempts' => 'Attempts',
            'last_error' => 'Last error',
            'sent_message_id' => 'Sent message ID',
            'created_at' => 'Created',
            // Added by plan 05-12 task 1 — BotI18nKeyCoverageTest surfaced this
            // gap (DiscordOutboundMessageResource references the key for the
            // `causer.username` table column).
            'causer' => 'Caused by',
        ],
        'actions' => [
            'retry' => 'Retry',
            'retry_success' => 'Message marked pending for redelivery.',
        ],
        'status' => [
            'pending' => 'Pending',
            'dispatching' => 'Dispatching',
            'sent' => 'Sent',
            'failed' => 'Failed',
        ],
    ],

    // -------------------------------------------------------------------------
    // Phase 6 tournament + bracket admin resources (06-01-PLAN.md task 1 Wave 0).
    // Consumed by TournamentResource + nested RelationManagers in plan 06-11.
    // Resource ships with 9 admin actions (open_registration / seed / reseed /
    // start / forfeit / withdraw / recalculate_standings / cancel / generate_next_swiss_round)
    // and a read-only audit feed. Key shapes mirror the existing match/event/clan
    // groups so Filament v3 resources can call __() without raising
    // MissingTranslationException; placeholder copy until 06-13 audit.
    // -------------------------------------------------------------------------

    'tournament' => [
        'label' => 'Tournament',
        'plural_label' => 'Tournaments',
        'navigation_group' => 'Tournaments',
        'section' => [
            'profile' => 'Profile',
            'audit' => 'Audit',
        ],
        'fields' => [
            'slug' => 'Slug',
            'game_id' => 'Game',
            'title' => 'Title',
            'description' => 'Description',
            'format' => 'Format',
            'status' => 'Status',
            'starts_at' => 'Starts at',
            'ends_at' => 'Ends at',
            'max_participants' => 'Max participants',
            'organiser_user_id' => 'Organiser',
            'default_game_match_type_id' => 'Default match type',
            'is_public' => 'Public',
            'settings' => 'Settings',
            'participants_count' => 'Participants',
        ],
        'actions' => [
            'open_registration' => ['label' => 'Open registration'],
            'seed' => ['label' => 'Seed participants'],
            'reseed' => [
                'label' => 'Re-seed',
                'modal_description' => 'Only available while no matches have been played.',
                'success' => 'Participants re-seeded.',
            ],
            'start' => ['label' => 'Start tournament'],
            'forfeit' => ['label' => 'Forfeit participant'],
            'withdraw' => ['label' => 'Withdraw participant'],
            'recalculate_standings' => ['label' => 'Recalculate standings'],
            'cancel' => ['label' => 'Cancel tournament'],
            'generate_next_swiss_round' => ['label' => 'Generate next Swiss round'],
            'materialise_next_round' => [
                'label' => 'Materialise next round',
                'modal_heading' => 'Materialise pending bracket matches?',
                'modal_description' => 'Spawn GameMatch + slot grid for every bracket that has both participants set and no match yet.',
                'success' => 'Pending brackets materialised.',
            ],
        ],
    ],

    'tournament_participant' => [
        'label' => 'Participant',
        'plural_label' => 'Participants',
        'fields' => [
            'clan_id' => 'Clan',
            'seed' => 'Seed',
            'status' => 'Status',
            'placement' => 'Placement',
        ],
    ],

    'tournament_stage' => [
        'label' => 'Stage',
        'plural_label' => 'Stages',
        'fields' => [
            'type' => 'Type',
            'ordinal' => 'Order',
            'name' => 'Name',
            'brackets_count' => 'Brackets',
        ],
    ],

    'tournament_bracket' => [
        'label' => 'Bracket',
        'plural_label' => 'Brackets',
        'fields' => [
            'stage' => 'Stage',
            'round_number' => 'Round',
            'position' => 'Position',
            'participant_a_id' => 'Participant A',
            'participant_b_id' => 'Participant B',
            'winner_participant_id' => 'Winner',
            'match_id' => 'Match',
        ],
    ],

    'tournament_standing' => [
        'label' => 'Standing',
        'plural_label' => 'Standings',
        'fields' => [
            'participant_id' => 'Participant',
            'wins' => 'Wins',
            'losses' => 'Losses',
            'draws' => 'Draws',
            'points' => 'Points',
            'tiebreak_score' => 'Tiebreak',
            'rank' => 'Rank',
        ],
    ],

    // ─── Phase 7 (CMS) ──────────────────────────────────────────────────
    // Filament resource labels for ArticleResource + CategoryResource
    // (plan 07-05). Public-facing copy lives in lang/en/cms.php; this
    // namespace holds admin-chrome strings only (Phase 4 extension idiom).

    'article' => [
        'label' => 'Article',
        'plural_label' => 'Articles',
        'nav' => 'Articles',
        'fields' => [
            'title' => 'Title',
            'slug' => 'Slug',
            'excerpt' => 'Excerpt',
            'body' => 'Body',
            'hero_media_id' => 'Hero image',
            'category_id' => 'Category',
            'status' => 'Status',
            'scheduled_at' => 'Scheduled at',
            'published_at' => 'Published at',
            'author_user_id' => 'Author',
            'allow_discord_announce' => 'Announce on Discord',
        ],
        'publication' => [
            'section' => 'Publication',
            'help' => 'Draft → Scheduled → Published. The Laravel Scheduler flips Scheduled rows automatically when scheduled_at passes.',
        ],
    ],

    'category' => [
        'label' => 'Category',
        'plural_label' => 'Categories',
        'nav' => 'Categories',
        'fields' => [
            'name' => 'Name',
            'slug' => 'Slug',
            'description' => 'Description',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 8 — RCON automation (08-01-PLAN.md task 2)
    |--------------------------------------------------------------------------
    | Consumed by MatchServerResource (plan 08-09) + booking RelationManager.
    | Field keys mirror match_servers / match_server_bookings columns (plan 08-02
    | migration). Action `test` drives the TestConnectionAction button.
    */
    'match_servers' => [
        'label' => 'Match server',
        'plural_label' => 'Match servers',
        'nav' => 'Match servers',
        'fields' => [
            'name' => 'Name',
            'host' => 'Host',
            'port_rcon' => 'RCON port',
            'port_query' => 'Query port',
            'password_encrypted' => 'RCON password',
            'region' => 'Region',
            'is_active' => 'Active',
            'last_test_status' => 'Last test status',
            'last_test_at' => 'Last test at',
            'notes' => 'Notes',
        ],
        'actions' => [
            'test' => 'Test connection',
        ],
        'last_test_status' => [
            'ok' => 'OK',
            'error' => 'Error',
            'none' => 'Not yet tested',
        ],
    ],

    'match_server_bookings' => [
        'label' => 'Server booking',
        'plural_label' => 'Server bookings',
        'nav' => 'Server bookings',
        'fields' => [
            'match_id' => 'Match',
            'server_id' => 'Server',
            'reserved_from' => 'Reserved from',
            'reserved_to' => 'Reserved to',
            'status' => 'Status',
        ],
    ],
];
