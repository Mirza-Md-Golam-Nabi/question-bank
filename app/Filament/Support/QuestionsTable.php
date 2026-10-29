<?php

namespace App\Filament\Support;

use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Models\Question;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Columns, filters, and row actions shared by every panel's QuestionResource
 * table. The review actions (QuestionReviewActions — approve, reject, many
 * at once, and from the View window) only render when the viewing user's
 * QuestionPolicy allows it (Admin/Super Admin), so the same table
 * definition is safe to reuse in the Teacher and Staff panels without ever
 * exposing them there.
 */
class QuestionsTable
{
    /**
     * Whether the list shows where each question is in review — not for a
     * School, whose own questions are never reviewed (CLAUDE.md rule 12).
     */
    public static function showsReview(): bool
    {
        return CurrentPanel::id() !== 'school';
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('chapter.classSubject.academicClass.name')->label(__('Class'))->toggleable(),
                TextColumn::make('chapter.classSubject.subject.name')->label(__('Subject'))->toggleable(),
                TextColumn::make('chapter.name')->label(__('Chapter'))->searchable(),
                TextColumn::make('topic.name')->label(__('Topic'))->placeholder('—')->searchable(),
                TextColumn::make('question_type')->badge(),
                TextColumn::make('difficulty')->badge(),
                TextColumn::make('marks'),
                TextColumn::make('status')->badge()->color(fn (QuestionStatus $state) => $state->getColor())
                    ->visible(self::showsReview()),
                // Shown only while the question is still on the background
                // duplicate check or waiting for the Admin's word on it.
                TextColumn::make('duplicate_status')->label(__('Duplicate check'))->badge()
                    ->state(fn (Question $record): ?DuplicateCheckStatus => $record->duplicate_status === DuplicateCheckStatus::Clear ? null : $record->duplicate_status)
                    ->color(fn (DuplicateCheckStatus $state): string => $state->getColor())
                    ->placeholder('—')
                    ->visible(self::showsReview())
                    ->toggleable(),
                TextColumn::make('version')->label(__('v'))->toggleable(),
                TextColumn::make('creator.name')->label(__('Created by')),
                TextColumn::make('created_at')->localDateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(QuestionStatus::class)->visible(self::showsReview()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                QuestionReviewActions::approve(),
                QuestionReviewActions::reject(),
                QuestionReviewActions::view(),
                // A question under a shared stimulus is edited with its whole
                // group, so the group always changes as one.
                EditAction::make()->iconButton()->hidden(fn (Question $record): bool => $record->isInStimulusGroup()),
                StimulusGroupActions::edit(),
                DeleteAction::make()->iconButton(),
                RestoreAction::make()->iconButton(),
                ForceDeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    QuestionReviewActions::approveSelected(),
                    QuestionReviewActions::rejectSelected(),
                    // Each selected question is checked on its own, so an
                    // approved one among them is left alone.
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
