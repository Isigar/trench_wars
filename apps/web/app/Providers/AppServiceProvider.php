<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ProvisionFirstLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // Plan 09-08 (SC-4) — flip Eloquent strict mode in non-production so
        // lazy loads, accessing non-existent attributes, and missing-attribute
        // access throw at test/dev time. Production stays relaxed: a runtime
        // surprise on a public page is worse than the same surprise in CI.
        //
        // References:
        //   - 09-RESEARCH.md Pattern 6 — Model::shouldBeStrict in non-prod.
        //   - 09-08-PLAN.md (T-09-08-01 mitigation).
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', Provider::class);
        });

        Event::listen(Login::class, ProvisionFirstLogin::class);

        Authenticate::redirectUsing(fn () => route('auth.discord.redirect'));

        // Plan 09-06 / 09-11 — named rate limiters required by SC-5 layered
        // defenses (public JSON endpoints, auth flow, notifications hub,
        // report-abuse flow). Plan 09-11 extends the 09-06 set with `auth`
        // and `report-abuse`; the existing `public-api` + `notifications-read`
        // definitions are preserved verbatim (their semantics are still
        // correct — IP keyed at 30/min and user keyed at 120/min respectively).
        // RateLimiter::for is idempotent (last registration wins).
        //
        // Threat mitigations:
        //   - T-09-06-04 / T-09-11-* notifications-read — 120/min per user for
        //       the mark-read endpoints (absorb tab-storms; per-user key so
        //       one tab-storm does not leak cross-user counters).
        //   - T-09-06-05 / T-09-11-01 public-api — 30/min by IP for the public
        //       JSON polling endpoints (/leaderboards, /clans.json, /players.json,
        //       /events/feed.json, /search). T-09-11-02 mitigation: Laravel's
        //       TrustProxies middleware sets $request->ip() from the trusted
        //       upstream (Railway terminates TLS), so X-Forwarded-For spoofing
        //       does not bypass the limiter.
        //   - T-09-11-07 auth — 10/min by IP on /auth/discord/callback to slow
        //       OAuth-state-replay storms. Generous enough that a typo-retry
        //       does not lock a legitimate user out.
        //   - T-09-11-03 report-abuse — 5/hour per authenticated user on
        //       POST /reports. Stops report-storms against a single victim
        //       while leaving a generous budget for genuine bad-actor flagging.
        RateLimiter::for('notifications-read', static function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return $userId !== null
                ? Limit::perMinute(120)->by('user:' . (string) $userId)
                : Limit::perMinute(30)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('public-api', static function (Request $request): Limit {
            return Limit::perMinute(30)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });

        // Plan 09-11 — auth limiter (T-09-11-07 mitigation). IP-keyed because
        // the relevant routes are guest-only (/auth/discord/redirect +
        // /auth/discord/callback): there is no authenticated user to key by
        // until AFTER the callback succeeds.
        RateLimiter::for('auth', static function (Request $request): Limit {
            return Limit::perMinute(10)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });

        // Plan 09-11 — report-abuse limiter (T-09-11-03 mitigation). Per-user
        // because the routes sit under the `auth` middleware group — every
        // request has a resolvable User::id. Defence-in-depth: if a future
        // refactor surfaces the endpoint to anonymous users, fall back to an
        // IP key so the limiter still applies.
        RateLimiter::for('report-abuse', static function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return $userId !== null
                ? Limit::perHour(5)->by('user:' . (string) $userId)
                : Limit::perHour(5)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });

        // WR-03 — clan-apply limiter. Per-user to prevent submission storms on
        // POST /clans/{clan:slug}/apply. 5 attempts per minute is generous enough
        // for legitimate use (multiple clan browsing) while bounding bot floods.
        // Route sits under the `auth` middleware group so $request->user() is
        // always resolvable; the IP fallback is defence-in-depth only.
        RateLimiter::for('clan-apply', static function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return $userId !== null
                ? Limit::perMinute(5)->by('user:' . (string) $userId)
                : Limit::perMinute(5)->by('ip:' . (string) ($request->ip() ?? 'unknown'));
        });
    }
}
