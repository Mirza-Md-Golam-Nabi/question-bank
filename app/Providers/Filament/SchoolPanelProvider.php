<?php

namespace App\Providers\Filament;

use App\Filament\School\Pages\Auth\Login;
use App\Filament\Support\PanelDefaults;
use App\Filament\Support\QuestionEditorAssets;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\Questions\QuestionResource;
use App\Http\Controllers\TeacherExamPrintController;
use App\Http\Controllers\TeacherExamResultsPrintController;
use App\Http\Middleware\EnsureAccountIsApproved;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Route;

/**
 * The School panel: one account per school, run by the school's own
 * computer operator — it sets the school's exams, writes its own questions,
 * and is the only account connected to the school's management software.
 * It writes questions and builds and runs exams with the Teacher's own
 * pages ("My questions", the question picker, the exams list and results,
 * the printable paper), which follow whichever panel they are open in.
 * What it writes is its own — never reviewed, never in the shared pool.
 */
class SchoolPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return QuestionEditorAssets::registerOn(PanelDefaults::apply($panel, 'school')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverPages(in: app_path('Filament/School/Pages'), for: 'App\Filament\School\Pages')
            ->resources([
                ExamResource::class,
                QuestionResource::class,
            ])
            ->pages([
                SelectQuestions::class,
            ])
            // Nothing until an Admin has approved the account.
            ->authMiddleware([
                EnsureAccountIsApproved::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('exams/{exam}/print', TeacherExamPrintController::class)->name('exams.print');
                Route::get('exams/{exam}/results/print', TeacherExamResultsPrintController::class)->name('exams.results.print');
            }));
    }
}
