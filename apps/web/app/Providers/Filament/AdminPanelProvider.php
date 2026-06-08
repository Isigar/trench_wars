<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Audit;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\MatchServerResource;
use App\Filament\Resources\PermissionResource;
use App\Filament\Resources\PlayerResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Http\Middleware\RedirectFilamentAuthToDiscord;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Source: 01-RESEARCH.md Pattern 3 + Open Question #4 (drop ->login())
 * + 01-UI-SPEC.md § Page: /admin (brandName, accent, dark mode, Inter font, viteTheme bundle).
 *
 * P1 ships an empty resource list; plan 13 registers User/Player/Role/Permission resources;
 * plan 14 registers the Audit page.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->brandName(__('admin.brand.name'))
            // Neutral "gunmetal" chrome + brass primary. Red is reserved for
            // danger/destructive actions (so primary buttons like "New match" no
            // longer read as danger). gray => Zinc drives the neutral sidebar/bg;
            // the bespoke audit partials pick up the matching neutral palette from
            // resources/css/filament/admin/theme.css.
            ->colors([
                'primary' => Color::hex('#C7A23A'),   // brass
                'gray' => Color::Zinc,                 // neutral charcoal chrome (no green)
                'danger' => Color::hex('#C03A2B'),     // red — destructive only
                'success' => Color::hex('#6B8E3D'),    // olive — positive badges
                'warning' => Color::hex('#C8932A'),    // amber
                'info' => Color::Blue,
            ])
            ->darkMode()
            ->font('Inter')
            ->viteTheme('resources/css/filament/admin/theme.css', 'build/filament')
            // INTENTIONALLY no ->login() — Discord OAuth is the only auth path (Open Question #4).
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->resources([
                UserResource::class,
                PlayerResource::class,
                RoleResource::class,
                PermissionResource::class,
                // Phase 7 (CMS) — plan 07-05 ArticleResource + CategoryResource.
                ArticleResource::class,
                CategoryResource::class,
                // Phase 8 (RCON automation) — plan 08-09 MatchServerResource
                // (gated behind manage-rcon permission).
                MatchServerResource::class,
            ])
            ->pages([
                Dashboard::class,
                Audit::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                RedirectFilamentAuthToDiscord::class,
            ]);
    }
}
