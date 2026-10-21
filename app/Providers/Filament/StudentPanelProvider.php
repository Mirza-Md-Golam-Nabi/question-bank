<?php

namespace App\Providers\Filament;

use App\Filament\Student\Pages\Auth\Login;
use App\Filament\Student\Pages\Dashboard;
use App\Filament\Support\PanelDefaults;
use App\Filament\Support\QuestionEditorAssets;
use App\Filament\Support\Widgets\Performance\DifficultyChartWidget;
use App\Filament\Support\Widgets\Performance\PerformanceStatsWidget;
use App\Filament\Support\Widgets\Performance\ScoreTrendChartWidget;
use App\Filament\Support\Widgets\Performance\SubjectPerformanceWidget;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerKatexRendererOn(PanelDefaults::apply($panel, 'student', dashboard: Dashboard::class)
            ->login(Login::class)
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Student/Resources'), for: 'App\Filament\Student\Resources')
            ->discoverPages(in: app_path('Filament/Student/Pages'), for: 'App\Filament\Student\Pages')
            ->discoverWidgets(in: app_path('Filament/Student/Widgets'), for: 'App\Filament\Student\Widgets')
            // The performance widgets a teacher's page about a student shows
            // too, so they live outside the panel's own folder.
            ->widgets([
                PerformanceStatsWidget::class,
                ScoreTrendChartWidget::class,
                DifficultyChartWidget::class,
                SubjectPerformanceWidget::class,
            ]));
    }
}
