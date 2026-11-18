<?php

namespace App\Providers\Filament;

use App\Filament\Support\PanelDefaults;
use App\Filament\Support\QuestionEditorAssets;
use App\Filament\Teacher\Pages\Auth\Login;
use App\Filament\Teacher\Pages\Dashboard;
use App\Http\Controllers\OmrScanController;
use App\Http\Controllers\PrintSettingsController;
use App\Http\Controllers\TeacherExamPrintController;
use App\Http\Controllers\TeacherExamResultsCsvController;
use App\Http\Controllers\TeacherExamResultsPrintController;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Route;

class TeacherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerOn(PanelDefaults::apply($panel, 'teacher', dashboard: Dashboard::class)
            ->login(Login::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Teacher/Resources'), for: 'App\Filament\Teacher\Resources')
            ->discoverPages(in: app_path('Filament/Teacher/Pages'), for: 'App\Filament\Teacher\Pages')
            ->discoverWidgets(in: app_path('Filament/Teacher/Widgets'), for: 'App\Filament\Teacher\Widgets')
            ->widgets([
                // widget list
            ])
            // The printable question paper and result sheet are plain pages
            // (no panel chrome to print around), but still behind the
            // panel's own auth.
            ->authenticatedRoutes(fn () => self::examPaperRoutes()));
    }

    /**
     * The exam's printable paper and result sheet (and its CSV), its print
     * settings and the OMR page — plain pages behind the panel's auth, in
     * the Teacher and School panels alike.
     */
    public static function examPaperRoutes(): void
    {
        Route::get('exams/{exam}/print', TeacherExamPrintController::class)->name('exams.print');
        Route::get('exams/{exam}/results/print', TeacherExamResultsPrintController::class)->name('exams.results.print');
        Route::get('exams/{exam}/results/csv', TeacherExamResultsCsvController::class)->name('exams.results.csv');
        Route::post('exams/print-settings', PrintSettingsController::class)->name('exams.print-settings');
        Route::get('exams/{exam}/omr-scan', [OmrScanController::class, 'show'])->name('exams.omr-scan');
        Route::post('exams/{exam}/omr-scan', [OmrScanController::class, 'store'])->name('exams.omr-scan.store');
        Route::post('exams/{exam}/omr-questions', [OmrScanController::class, 'saveQuestionCount'])->name('exams.omr-questions');
        Route::post('exams/{exam}/omr-identity', [OmrScanController::class, 'saveIdentity'])->name('exams.omr-identity');
    }
}
