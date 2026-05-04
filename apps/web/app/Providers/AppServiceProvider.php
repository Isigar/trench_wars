<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ProvisionFirstLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', Provider::class);
        });

        Event::listen(Login::class, ProvisionFirstLogin::class);
    }
}
