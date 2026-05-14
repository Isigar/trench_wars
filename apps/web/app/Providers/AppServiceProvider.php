<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ProvisionFirstLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Source: 01-RESEARCH.md Pattern 1 — register the Discord Socialite provider
     * via Event::listen (Laravel 11+ pattern; no EventServiceProvider in skeleton).
     *
     * Also binds the Login event to ProvisionFirstLogin so first-login user/player/
     * player_privacy creation runs inside DB::transaction (D-018, T-1-03 mitigation).
     *
     * Plan 12: redirect unauthenticated panel hits to Discord OAuth (Open Question #4)
     * by overriding Laravel's `auth` middleware default redirect target. Filament's
     * AdminPanelProvider drops the built-in ->login() form, so the auth middleware
     * stack must redirect to /auth/discord/redirect instead of the (non-existent)
     * `login` named route.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', Provider::class);
        });

        Event::listen(Login::class, ProvisionFirstLogin::class);

        Authenticate::redirectUsing(fn () => route('auth.discord.redirect'));

        // Plan 09-06 — named rate limiters required by routes attached to the
        // notifications hub + public leaderboards endpoint. Plan 09-11 will
        // extend / refine these definitions; RateLimiter::for is idempotent
        // (last registration wins).
        //
        // T-09-06-04 mitigation: notifications-read — 120/min per user for
        //   the mark-read endpoints (per-user limiter to absorb tab-storms
        //   without leaking cross-user counters).
        // T-09-06-05 mitigation: public-api — 30/min by IP for the public
        //   /leaderboards endpoint (and any future JSON polling routes).
        RateLimiter::for('notifications-read', static function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return $userId !== null
                ? Limit::perMinute(120)->by('user:' . (string) $userId)
                : Limit::perMinute(30)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('public-api', static function (Request $request): Limit {
            return Limit::perMinute(30)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });
    }
}
