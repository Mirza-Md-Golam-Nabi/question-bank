<?php

namespace App\Providers\Filament;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\QuestionEditorAssets;
use App\Filament\Teacher\Pages\Auth\Login;
use App\Http\Controllers\TeacherExamPrintController;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TeacherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerOn($panel
            ->id('teacher')
            ->path('teacher')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups(NavigationGroup::class)
            ->discoverResources(in: app_path('Filament/Teacher/Resources'), for: 'App\Filament\Teacher\Resources')
            ->discoverPages(in: app_path('Filament/Teacher/Pages'), for: 'App\Filament\Teacher\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Teacher/Widgets'), for: 'App\Filament\Teacher\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            // The printable question paper is a plain page (no panel chrome
            // to print around), but still behind the panel's own auth.
            ->authenticatedRoutes(fn () => Route::get('exams/{exam}/print', TeacherExamPrintController::class)->name('exams.print'))
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
            ->authMiddleware([
                Authenticate::class,
            ]));
    }
}
