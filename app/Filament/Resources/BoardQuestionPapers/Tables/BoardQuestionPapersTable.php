<?php

namespace App\Filament\Resources\BoardQuestionPapers\Tables;

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

class BoardQuestionPapersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('board.short_name')->label(__('Board'))->badge(),
                TextColumn::make('classSubject.academicClass.name')->label(__('Class')),
                TextColumn::make('classSubject.subject.name')->label(__('Subject')),
                TextColumn::make('year'),
                TextColumn::make('mcq_questions_count')->label(__('MCQ'))->counts('mcqQuestions'),
                TextColumn::make('cq_questions_count')->label(__('CQ'))->counts('cqQuestions'),
                TextColumn::make('status')->badge()->color(fn (QuestionStatus $state) => $state->getColor()),
                TextColumn::make('creator.name')->label(__('Created by')),
            ])
            ->filters([
                SelectFilter::make('status')->options(QuestionStatus::class),
            ])
            ->recordActions([
                self::approveAction(),
                self::rejectAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions(TableActions::bulkDelete());
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (BoardQuestionPaper $record) => $record->status !== QuestionStatus::Approved && auth()->user()->can('approve', $record))
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
