<?php

namespace App\Providers\Filament;

use App\Filament\Staff\Pages\Auth\Login;
use App\Filament\Support\PanelDefaults;
use App\Filament\Support\QuestionEditorAssets;
use App\Http\Middleware\EnsureStaffIsApproved;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerOn(PanelDefaults::apply($panel, 'staff')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Staff/Resources'), for: 'App\Filament\Staff\Resources')
            ->discoverPages(in: app_path('Filament/Staff/Pages'), for: 'App\Filament\Staff\Pages')
            ->discoverWidgets(in: app_path('Filament/Staff/Widgets'), for: 'App\Filament\Staff\Widgets')
            ->widgets([
                // widget list
            ])
            ->authMiddleware([
                EnsureStaffIsApproved::class,
            ]));
    }
}
