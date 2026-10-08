<?php

namespace App\Filament\Teacher\Resources\Exams\Tables;

use App\Enums\ExamStatus;
use App\Filament\Support\TableActions;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('subject.name'),
                TextColumn::make('delivery_mode')->label(__('Exam mode'))->badge()->color('gray'),
                TextColumn::make('status')->badge(),
                TextColumn::make('total_marks'),
                TextColumn::make('questions_count')->label(__('Questions'))->counts('questions'),
                TextColumn::make('share_token')
                    ->label(__('Share link'))
                    ->formatStateUsing(fn (?string $state) => $state ? url("/exam/{$state}") : '—')
                    ->copyable()
                    ->copyMessage(__('Link copied')),
            ])
            ->filters([
                SelectFilter::make('status')->options(ExamStatus::class),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('Publish'))
                    ->color('success')
                    ->icon('heroicon-o-globe-alt')
                    ->visible(fn (Exam $record) => $record->status === ExamStatus::Draft && $record->delivery_mode->includesOnline())
                    ->requiresConfirmation()
                    ->authorize('publish')
                    ->action(function (Exam $record) {
                        $record->recalculateTotalMarks();
                        $record->publish();

                        Notification::make()->title(__('Exam published'))->success()->send();
                    }),
                // Students only see their score until this is pressed —
                // otherwise a blank paper would reveal the answer key to
                // anyone who has yet to sit the exam.
                Action::make('releaseAnswers')
                    ->label(__('Release answers'))
                    ->color('info')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (Exam $record) => $record->status !== ExamStatus::Draft && $record->delivery_mode->includesOnline() && ! $record->showsAnswersToStudents())
                    ->requiresConfirmation()
                    ->modalDescription(__('Students will be able to see every question, the correct answers and their own answers. Do this only after everyone has finished the exam.'))
                    ->authorize('update')
                    ->action(function (Exam $record) {
                        $record->releaseAnswers();

                        Notification::make()->title(__('Answers released to students'))->success()->send();
                    }),
                Action::make('hideAnswers')
                    ->label(__('Hide answers'))
                    ->color('gray')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (Exam $record) => $record->answers_released_at !== null)
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->action(function (Exam $record) {
                        $record->hideAnswers();

                        Notification::make()->title(__('Answers hidden from students'))->success()->send();
                    }),
                Action::make('print')
                    ->label(__('Print / PDF'))
                    ->color('gray')
                    ->icon('heroicon-o-printer')
                    ->visible(fn (Exam $record) => $record->delivery_mode->includesOffline())
                    ->url(fn (Exam $record): string => route('filament.teacher.exams.print', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions(TableActions::bulkDelete());
    }
}
