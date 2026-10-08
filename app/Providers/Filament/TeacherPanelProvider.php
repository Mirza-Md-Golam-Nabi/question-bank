<?php

namespace App\Providers\Filament;

use App\Filament\Support\PanelDefaults;
use App\Filament\Support\QuestionEditorAssets;
use App\Filament\Teacher\Pages\Auth\Login;
use App\Http\Controllers\TeacherExamPrintController;
use App\Http\Controllers\TeacherExamResultsPrintController;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Route;

class TeacherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerOn(PanelDefaults::apply($panel, 'teacher')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Teacher/Resources'), for: 'App\Filament\Teacher\Resources')
            ->discoverPages(in: app_path('Filament/Teacher/Pages'), for: 'App\Filament\Teacher\Pages')
            ->discoverWidgets(in: app_path('Filament/Teacher/Widgets'), for: 'App\Filament\Teacher\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            // The printable question paper and result sheet are plain pages
            // (no panel chrome to print around), but still behind the
            // panel's own auth.
            ->authenticatedRoutes(function (): void {
                Route::get('exams/{exam}/print', TeacherExamPrintController::class)->name('exams.print');
                Route::get('exams/{exam}/results/print', TeacherExamResultsPrintController::class)->name('exams.results.print');
            }));
    }
}
