<?php

declare(strict_types=1);

/*
| Source: 01-CONTEXT.md "i18n end-to-end wiring" + D-013 (no URL prefix at launch).
|
| Locale resolution order: user.locale (DB column, plan 10) → ?lang= → cookie →
| Accept-Language → fallback 'en'.
|
| Plan 08 ships the config + middleware-side share; per-request resolution is performed
| by Laravel's default `app.locale` set from session/cookie. Phase 2+ may add a
| LocaleMiddleware to fold the user.locale + ?lang= sources into the chain.
*/

return [
    'default' => env('APP_LOCALE', 'en'),
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    | Locales the app can serve. Adding a new locale at launch is config-only:
    |   1) drop a `lang/<locale>/*.php` directory mirroring `lang/en/`,
    |   2) add the locale code to this array.
    */
    'available_locales' => ['en'],

    /*
    | Order in which the active locale is resolved. The first non-null source wins.
    | Phase 1 ships the config; Phase 2+ may introduce a LocaleMiddleware that walks
    | this list. The Inertia middleware always reflects `app()->getLocale()` to the
    | client, regardless of how that value was set.
    */
    'resolution_order' => ['user', 'query', 'cookie', 'accept-language', 'default'],

    /*
    | Namespaces flat-merged into the Inertia `translations` shared prop, e.g.
    | `auth.discord.button_label`, `home.tagline`. Add a namespace here when a new
    | `lang/en/<namespace>.php` file is authored.
    */
    'shared_namespaces' => ['auth', 'common', 'admin', 'home', 'validation', 'matches', 'tournaments', 'a11y', 'leaderboards', 'notifications', 'clans', 'cms', 'events', 'players', 'reports', 'search'],
];
