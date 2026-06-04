<?php

declare(strict_types=1);

/*
| Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
| Bot-facing English copy + service-layer error messages. Keys consumed by:
|   - /api/bot/* controller JSON error responses (plan 05-04) → bot.errors.*
|   - ResolveBotActsAsUser middleware 422 response (plan 05-03) → bot.errors.acts_as_unknown
|   - Outbound transition guards (plan 05-04) → bot.errors.outbound_not_pending / not_dispatching
|   - Echo-suppression diagnostic (plan 05-04) → bot.errors.echo_suppressed
|   - Embed builder titles + labels (plan 05-10) → bot.embeds.*
|
| Parameter interpolation: `:title`, `:status`, `:time`, `:name`, `:slug`.
| D-013 — i18n plumbed day one, EN at launch. Phase 5 ships English; multi-locale
| comes later per CONTEXT.md "i18n for bot responses (v1 ships English)".
*/

return [
    'errors' => [
        'acts_as_unknown' => 'Discord user has never logged in to the website.',
        'match_not_open' => 'This match is not open for signups.',
        'capacity_full' => 'This role is full.',
        'tag_restricted' => 'Your clan tags are not permitted on this match.',
        'already_signed_up' => 'You are already signed up to this match.',
        'no_active_clan' => 'You have no active clan membership.',
        'outbound_not_pending' => 'This outbound message has already been claimed or completed.',
        'outbound_not_dispatching' => 'This outbound message is not currently being dispatched.',
        'echo_suppressed' => 'Discord-side change suppressed as bot-originated echo (within 60s window).',
        'clan_not_recruiting' => 'This clan is not accepting applications.',
        'already_in_clan' => 'You are already a member of a clan.',
        'duplicate_application' => 'You already have a pending application to this clan.',
    ],

    'embeds' => [
        'match_title' => 'Match :title',
        'match_status' => 'Status: :status',
        'match_scheduled' => 'Scheduled: :time',
        'clan_card_title' => ':name',
        'profile_card_title' => 'Player: :slug',
    ],
];
