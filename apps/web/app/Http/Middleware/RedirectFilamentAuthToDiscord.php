<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Request;

/**
 * Source: 01-RESEARCH.md Open Question #4 — Filament panel drops the built-in
 * ->login() form so all auth flows funnel through Discord OAuth.
 *
 * Filament\Http\Middleware\Authenticate overrides redirectTo() to return
 * Filament::getLoginUrl(), which is null when ->login() is NOT called on the
 * panel. The parent middleware then falls back to route('login') which is
 * undefined in this app — producing a "Route [login] not defined" exception.
 *
 * This subclass returns the Discord OAuth redirect route instead, while
 * preserving Filament's authenticate() override that performs the
 * canAccessPanel(...) check (Pitfall 4 mitigation).
 *
 * NOTE on signature: Filament's parent override drops the parameter type to
 * stay compatible with both the Filament v3 and Illuminate parent contracts.
 * We mirror that exactly (untyped `$request`) and document the type via the
 * PHPDoc `@param` so PHPStan level 8 has a typed reference (WR-07 fix).
 */
class RedirectFilamentAuthToDiscord extends FilamentAuthenticate
{
    /**
     * @param  Request  $request
     */
    protected function redirectTo($request): string
    {
        return route('auth.discord.redirect');
    }
}
