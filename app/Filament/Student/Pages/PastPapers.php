<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\PastPapersPage;
use App\Models\BoardQuestionPaper;
use App\Services\PastPaperExamService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * A student sits a whole past paper as their own practice, timed as the
 * real test was, and sees the answers and explanations straight after.
 */
class PastPapers extends PastPapersPage
{
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Practice;

    protected static ?int $navigationSort = 4;

    protected function showsMcqsInPreview(): bool
    {
        return false;
    }

    protected function paperActions(): array
    {
        return [
            Action::make('sit')
                ->label(__('Sit this paper'))
                ->icon(Heroicon::OutlinedPlayCircle)
                ->visible(fn (BoardQuestionPaper $record): bool => $record->mcq_questions_count > 0)
                ->requiresConfirmation()
                ->modalHeading(fn (BoardQuestionPaper $record): string => $record->title())
                ->modalDescription(fn (BoardQuestionPaper $record): string => collect([
                    trans_choice(':count MCQ question|:count MCQ questions', $record->mcq_questions_count),
                    __(':minutes minutes', ['minutes' => $record->duration_minutes ?: $record->mcq_questions_count]),
                    $record->negative_marks > 0 ? __(':marks marks off for each wrong answer', ['marks' => $record->negative_marks]) : null,
                ])->filter()->join(' · ').'. '.__('The clock starts as soon as you begin.'))
                ->modalSubmitActionLabel(__('Start'))
                ->action(function (BoardQuestionPaper $record, Action $action): void {
                    $attempt = $this->rescueWithNotification(
                        fn () => app(PastPaperExamService::class)->startPractice(Auth::user(), $record),
                        $action,
                    );

                    $this->redirect(TakeExamPage::urlFor($attempt));
                }),
        ];
    }
}
