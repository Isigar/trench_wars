<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Source: 01-RESEARCH.md Pattern 1 + § Pitfall 2 (redirect_uri exact match) +
 * Open Question #4 (Filament built-in login dropped — every panel access starts here).
 *
 * Per CONTEXT.md "Login UI surface" — single Discord OAuth flow, no /login page.
 *
 * Threat mitigations:
 *  - T-1-01: We do NOT call ->stateless(); Socialite enforces the state CSRF check.
 *    InvalidStateException is caught and surfaces as auth.discord.error.cancelled.
 *  - T-1-06: $request->session()->regenerate() runs after Auth::login (anti-fixation).
 */
class DiscordController extends Controller
{
    public function redirect(): RedirectResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('discord');

        $response = $driver->scopes(['identify', 'email'])->redirect();

        // Socialite redirect() returns Symfony RedirectResponse; narrow for static analysis.
        /** @var RedirectResponse $response */
        return $response;
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (InvalidStateException) {
            return redirect()->route('home')->with('error', __('auth.discord.error.cancelled'));
        } catch (Throwable) {
            return redirect()->route('home')->with('error', __('auth.discord.error.provider'));
        }

        // Reject Discord identities with no usable username — both nickname and
        // global name can be null on edge accounts (pre-username-migration users
        // or stripped OAuth scopes). Persisting `''` would corrupt downstream
        // slug generation (`-a3k9`), Filament's getFilamentName(), and the
        // success flash. CR-04 in 01-REVIEW.md.
        $rawUsername = trim((string) ($discordUser->getNickname() ?: $discordUser->getName() ?: ''));
        if ($rawUsername === '') {
            return redirect()->route('home')->with(
                'error',
                __('auth.discord.error.provider'),
            );
        }

        // Upsert by discord_id (D-002 — canonical identity, UNIQUE at DB level — T-1-03).
        // The Login event fires after Auth::login() — ProvisionFirstLogin listener
        // creates the player + privacy row inside DB::transaction (idempotent on re-login).
        $user = User::updateOrCreate(
            ['discord_id' => (string) $discordUser->getId()],
            [
                'username' => $rawUsername,
                'email' => $discordUser->getEmail(),
                'avatar_url' => $discordUser->getAvatar(),
                'locale' => $discordUser->user['locale'] ?? config('app.locale', 'en'),
            ],
        );

        // Banned users are denied a session entirely — no point logging them in
        // just for the ban-check middleware to tear it down on the next request.
        if ($user->activeBan() !== null) {
            return redirect()->route('home')->with('error', __('auth.banned'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('home')->with(
            'success',
            __('auth.discord.success', ['name' => $user->username]),
        );
    }
}
