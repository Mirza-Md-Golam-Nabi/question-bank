<?php

namespace App\Filament\Resources\BoardQuestionPapers\Tables;

use App\Enums\QuestionStatus;
use App\Filament\Support\PastPaperFilters;
use App\Models\BoardQuestionPaper;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The past papers list — the Admin's, with approve/reject, and a Staff
 * member's own.
 */
class BoardQuestionPapersTable
{
    public static function configure(Table $table, bool $reviewing = true): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withExamUsage()->with(['board', 'classSubject.academicClass', 'classSubject.subject', 'creator:id,name']))
            ->columns([
                TextColumn::make('board.short_name')->label(__('Source'))->badge()
                    ->state(fn (BoardQuestionPaper $record): string => $record->board->display_short_name)
                    ->description(fn (BoardQuestionPaper $record): ?string => $record->board->type->getLabel())
                    ->searchable(query: PastPaperFilters::searchSource(...)),
                TextColumn::make('year')->sortable()
                    ->searchable(query: PastPaperFilters::searchYear(...)),
                TextColumn::make('unit')->label(__('Unit'))->placeholder('—')->searchable(),
                TextColumn::make('classSubject.academicClass.name')->label(__('Class'))->placeholder('—')
                    ->searchable(query: PastPaperFilters::searchClass(...)),
                TextColumn::make('classSubject.subject.name')->label(__('Subject'))
                    ->state(fn (BoardQuestionPaper $record): ?string => $record->classSubject?->subject?->display_name)
                    ->placeholder('—')
                    ->searchable(query: PastPaperFilters::searchSubject(...)),
                TextColumn::make('mcq_questions_count')->label(__('MCQ'))->counts('mcqQuestions')->alignCenter(),
                TextColumn::make('cq_questions_count')->label(__('CQ'))->counts('cqQuestions')->alignCenter(),
                TextColumn::make('status')->badge()->color(fn (QuestionStatus $state) => $state->getColor()),
                TextColumn::make('creator.name')->label(__('Created by'))->visible($reviewing)->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(QuestionStatus::class),
                ...PastPaperFilters::all(),
            ])
            ->filtersFormColumns(2)
            ->recordActions(array_values(array_filter([
                $reviewing ? self::approveAction() : null,
                $reviewing ? self::rejectAction() : null,
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])))
            ->toolbarActions($reviewing ? [
                BulkActionGroup::make([
                    self::approveSelectedAction(),
                    DeleteBulkAction::make(),
                ]),
            ] : []);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (BoardQuestionPaper $record) => $record->canBeApproved() && auth()->user()->can('approve', $record))
            ->requiresConfirmation()
            // The confirm button takes the focus, so Enter confirms.
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['autofocus' => true]))
            ->action(fn (BoardQuestionPaper $record) => $record->approve(auth()->user()));
    }

    /**
     * Approves the selected papers at once, each one whole.
     */
    protected static function approveSelectedAction(): BulkAction
    {
        return BulkAction::make('approveSelected')
            ->label(__('Approve selected'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (): bool => (bool) auth()->user()?->role->isAdmin())
            ->requiresConfirmation()
            ->modalDescription(__('Papers with no questions yet are left out.'))
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['autofocus' => true]))
            ->action(function (Collection $records): void {
                ['approved' => $approved, 'without_questions' => $withoutQuestions] = BoardQuestionPaper::approveMany($records, auth()->user());

                Notification::make()
                    ->title(__(':count approved', ['count' => $approved]))
                    ->body($withoutQuestions > 0 ? __(':count left out: no questions yet.', ['count' => $withoutQuestions]) : null)
                    ->color($withoutQuestions > 0 ? 'warning' : 'success')
                    ->icon($withoutQuestions > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('Reject'))
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->visible(fn (BoardQuestionPaper $record) => $record->status !== QuestionStatus::Rejected && auth()->user()->can('reject', $record))
            ->schema([
                Textarea::make('rejection_reason')->label(__('Reason'))->required(),
            ])
            ->action(fn (BoardQuestionPaper $record, array $data) => $record->reject(auth()->user(), $data['rejection_reason']));
    }
}
