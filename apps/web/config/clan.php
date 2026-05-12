<?php

declare(strict_types=1);

/*
| Source: 02-06-PLAN.md Task 1 + RESEARCH.md Pattern 5 + T-02-06-01 (reserved-slug bypass mitigation).
|
| Clan-domain runtime configuration.
| Reserved slugs are checked by ClanSlugGenerator and re-validated in FormRequest (plan 02-09).
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Reserved Slugs
    |--------------------------------------------------------------------------
    |
    | These slugs must never be used as clan slugs because they conflict with
    | application routes, admin paths, or special user-facing segments.
    | ClanSlugGenerator throws ReservedSlugException for any match.
    |
    */
    'reserved_slugs' => [
        'admin',
        'me',
        'api',
        'clans',
        'players',
        'my-clan',
        'login',
        'logout',
        'health',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clan Tag Constraints
    |--------------------------------------------------------------------------
    |
    | The clan tag (short identifier, e.g. "91st") length bounds.
    | FormRequest (plan 02-09) applies these as validation rules.
    |
    */
    'tag_min_length' => 2,
    'tag_max_length' => 8,

    /*
    |--------------------------------------------------------------------------
    | Description Max Length
    |--------------------------------------------------------------------------
    |
    | Maximum character count for the clan description text (per-locale value).
    | Enforced by FormRequest (plan 02-09). Defensive default here.
    |
    */
    'description_max_length' => 4000,
];
