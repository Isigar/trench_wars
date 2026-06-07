<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies authenticated access to a currently-banned user.
 *
 * The ban primitives (User::activeBan / BanService::isCurrentlyBanned) existed
 * since plan 09-03 but nothing in the request lifecycle ever invoked them, so a
 * banned user kept full authenticated access — the ban was an audit record, not
 * an access control. This is the "ban-check middleware" the User::activeBan
 * docblock referenced; it is mounted on the authenticated web route groups.
 *
 * On a banned hit: tear the session down (log out + invalidate + regenerate the
 * CSRF token) and bounce to home with an error flash. The user becomes a guest
 * (public pages stay reachable, as a guest) and cannot reach authenticated
 * surfaces. Re-login is blocked at the OAuth callback (DiscordController) so this
 * does not produce a login↔logout loop.
 */
final class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->activeBan() !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home')->with('error', __('auth.banned'));
        }

        return $next($request);
    }
}
