<?php

declare(strict_types=1);

namespace App\Providers\Filament;

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
            ->colors([
                'primary' => Color::hex('#A4262C'),
            ])
            ->darkMode()
            ->font('Inter')
            ->viteTheme('resources/css/filament/admin/theme.css', 'build/filament')
            // INTENTIONALLY no ->login() — Discord OAuth is the only auth path (Open Question #4).
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->resources([
                // Plan 13 registers User/Player/Role/Permission resources here.
            ])
            ->pages([
                Dashboard::class,
                // Plan 14 registers the Audit page.
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
