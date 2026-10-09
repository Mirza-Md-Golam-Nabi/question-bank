<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Auth\Login;
use App\Filament\Support\PanelDefaults;
use App\Filament\Support\QuestionEditorAssets;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerOn(PanelDefaults::apply($panel, 'admin')
            ->default()
            ->login(Login::class)
            // No ->registration() call: public sign-up stays disabled for
            // Admin/Super Admin. The first Super Admin comes from
            // SuperAdminSeeder; further admins are created from inside the
            // panel itself (AdminResource).
            ->passwordReset()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // widget list
            ]));
    }
}
