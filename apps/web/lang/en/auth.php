<?php

declare(strict_types=1);

/*
| Source: Laravel 12 default lang/en/auth.php + 01-UI-SPEC.md § Copywriting Contract.
|
| Auth-namespace English strings — Laravel defaults plus Trenchwars Discord OAuth copy.
*/

return [
    // Laravel default auth lines.
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Discord OAuth — UI-SPEC.md § Copywriting Contract.
    'discord' => [
        'button_label' => 'Log in with Discord',
        'success' => 'Signed in as :name.',
        'error' => [
            'cancelled' => 'Discord login was cancelled.',
            'provider' => "Couldn't reach Discord. Try again in a moment.",
        ],
    ],
];
