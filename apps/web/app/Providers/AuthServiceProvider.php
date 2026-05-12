<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Policies\ClanMembershipPolicy;
use App\Policies\ClanPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Source: 02-09-PLAN.md Task 1.
 *
 * Registers Policy classes with Laravel's Gate. Laravel 12 does not ship
 * AuthServiceProvider by default; this provider is created explicitly for
 * policy discoverability (convention over magic auto-discovery).
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model-to-policy mapping for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Clan::class => ClanPolicy::class,
        ClanMembership::class => ClanMembershipPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
