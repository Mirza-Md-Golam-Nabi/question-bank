<?php

namespace App\Filament\Support;

use App\Enums\GuidePage;
use App\Enums\Locale;
use App\Filament\Support\Pages\HelpCenter;
use App\Http\Middleware\SetLocale;
use App\Models\Guide;
use App\Models\Offer;
use App\Services\OnboardingChecklist;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
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
     * @param  Locale|null  $defaultLocale  The language the panel opens in until the visitor picks one; null for the application's own default (Bangla).
     * @param  class-string<Dashboard>  $dashboard  The panel's own dashboard, when it has filters of its own.
     */
    public static function apply(Panel $panel, string $id, ?Locale $defaultLocale = null, string $dashboard = Dashboard::class): Panel
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
            // The bell: what BellNotifier sends each user, looked for once
            // a minute — every open page asks, so not more often.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            // Handing Filament the enum itself would fix each heading in the
            // language that was active when the panel was set up; a closure
            // reads it again in the visitor's language on every page.
            ->navigationGroups(collect(NavigationGroup::cases())
                ->mapWithKeys(fn (NavigationGroup $group): array => [
                    $group->name => FilamentNavigationGroup::make()->label(fn (): ?string => $group->getLabel()),
                ])
                ->all())
            ->pages([
                $dashboard,
                // "Help" — the guides of every panel but the Admin's, who writes them.
                ...($id === 'admin' ? [] : [HelpCenter::class]),
            ])
            // The installable app (config/pwa.php) opens from every panel.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('components.pwa-head')->render())
            // Above the dashboard: "Get started", the offers for this user
            // and the button to switch push notifications on — not for
            // Admins, who write the offers.
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): ?View => auth()->user() && ! auth()->user()->role->isAdmin()
                    ? view('filament.support.offer-banner', [
                        'offers' => Offer::query()->inBannerOf(auth()->user())->get(),
                        'onboarding' => app(OnboardingChecklist::class)->for(auth()->user()),
                    ])
                    : null,
                scopes: $dashboard,
            )
            // "How do I…?" beside a page's own buttons, when it has guides
            // for this user's role (GuidePage).
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                function (array $scopes): ?View {
                    $page = auth()->user() && ! auth()->user()->role->isAdmin() ? GuidePage::forScopes($scopes) : null;
                    $guides = $page ? Guide::query()->onPage(auth()->user(), $page)->get() : collect();

                    return $guides->isNotEmpty() ? view('components.guides.page-help', ['guides' => $guides]) : null;
                },
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                $defaultLocale ? SetLocale::class.':'.$defaultLocale->value : SetLocale::class,
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
