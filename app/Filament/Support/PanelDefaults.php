<?php

namespace App\Filament\Support;

use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Everything the Admin, Teacher, Staff and Student panels configure
 * identically — theme, navigation, the dashboard and the middleware stack —
 * so a panel provider only states what makes its own panel different, and a
 * change such as a new middleware lands in all four at once.
 */
class PanelDefaults
{
    /**
     * @param  string  $id  The panel's id, which is also its URL path (`admin` → `/admin`).
     */
    public static function apply(Panel $panel, string $id): Panel
    {
        return $panel
            // The id has to come first: Filament looks up the panel's
            // component cache by id as soon as pages are registered — but
            // only on a real web request, so setting it later passes every
            // test and Artisan command and then breaks the whole site.
            ->id($id)
            ->path($id)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(NavigationGroup::class)
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // A panel may append its own (Staff adds its approval check);
            // Filament merges repeated authMiddleware() calls in order.
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
