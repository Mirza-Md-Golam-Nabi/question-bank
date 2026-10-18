<?php

namespace App\Filament\Resources\BoardQuestionPapers\Tables;

use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Filament\Support\TableActions;
use App\Models\BoardQuestionPaper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['board', 'classSubject.academicClass', 'classSubject.subject', 'creator:id,name']))
            ->columns([
                TextColumn::make('board.short_name')->label(__('Source'))->badge()
                    ->state(fn (BoardQuestionPaper $record): string => $record->board->display_short_name)
                    ->description(fn (BoardQuestionPaper $record): ?string => $record->board->type->getLabel()),
                TextColumn::make('year')->sortable(),
                TextColumn::make('unit')->label(__('Unit'))->placeholder('—'),
                TextColumn::make('classSubject.academicClass.name')->label(__('Class'))->placeholder('—'),
                TextColumn::make('classSubject.subject.name')->label(__('Subject'))
                    ->state(fn (BoardQuestionPaper $record): ?string => $record->classSubject?->subject?->display_name)
                    ->placeholder('—'),
                TextColumn::make('mcq_questions_count')->label(__('MCQ'))->counts('mcqQuestions')->alignCenter(),
                TextColumn::make('cq_questions_count')->label(__('CQ'))->counts('cqQuestions')->alignCenter(),
                TextColumn::make('status')->badge()->color(fn (QuestionStatus $state) => $state->getColor()),
                TextColumn::make('creator.name')->label(__('Created by'))->visible($reviewing),
            ])
            ->filters([
                SelectFilter::make('status')->options(QuestionStatus::class),
                SelectFilter::make('source_type')
                    ->label(__('Kind'))
                    ->options(PaperSourceType::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $type) => $query->whereHas('board', fn (Builder $board) => $board->where('type', $type)))),
            ])
            ->recordActions(array_values(array_filter([
                $reviewing ? self::approveAction() : null,
                $reviewing ? self::rejectAction() : null,
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])))
            ->toolbarActions($reviewing ? TableActions::bulkDelete() : []);
    }

    /**
     * Only a paper with questions in it can be approved — a Staff member
     * starts one empty and fills it in.
     */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (BoardQuestionPaper $record) => $record->status !== QuestionStatus::Approved
                && ($record->mcq_questions_count + $record->cq_questions_count) > 0
                && auth()->user()->can('approve', $record))
            ->requiresConfirmation()
            ->action(fn (BoardQuestionPaper $record) => $record->approve(auth()->user()));
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
