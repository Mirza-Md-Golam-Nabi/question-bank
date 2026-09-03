<?php

namespace App\Filament\Support;

use App\Enums\QuestionStatus;
use App\Models\Question;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Columns, filters, and row actions shared by every panel's QuestionResource
 * table. Approve/Reject only render when the viewing user's QuestionPolicy
 * allows it (Admin/Super Admin), so the same table definition is safe to
 * reuse in the Teacher and Staff panels without ever exposing those actions
 * there.
 */
class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('chapter.classSubject.academicClass.name')->label('Class')->toggleable(),
                TextColumn::make('chapter.classSubject.subject.name')->label('Subject')->toggleable(),
                TextColumn::make('chapter.name')->label('Chapter')->searchable(),
                TextColumn::make('question_type')->badge(),
                TextColumn::make('difficulty')->badge(),
                TextColumn::make('marks'),
                TextColumn::make('status')->badge()->color(fn (QuestionStatus $state) => $state->getColor()),
                TextColumn::make('version')->label('v')->toggleable(),
                TextColumn::make('creator.name')->label('Created by'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(QuestionStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                self::approveAction(),
                self::rejectAction(),
                ViewAction::make()
                    ->schema(QuestionInfolist::components())
                    ->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
                RestoreAction::make()->iconButton(),
                ForceDeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (Question $record) => $record->status !== QuestionStatus::Approved && auth()->user()->can('approve', $record))
            ->requiresConfirmation()
            ->action(fn (Question $record) => $record->approve(auth()->user()));
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->visible(fn (Question $record) => $record->status !== QuestionStatus::Rejected && auth()->user()->can('reject', $record))
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Reason')
                    ->required(),
            ])
            ->action(fn (Question $record, array $data) => $record->reject(auth()->user(), $data['rejection_reason']));
    }
}
