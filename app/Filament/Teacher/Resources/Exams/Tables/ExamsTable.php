<?php

namespace App\Filament\Teacher\Resources\Exams\Tables;

use App\Enums\ExamStatus;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
                TextColumn::make('status')->badge(),
                TextColumn::make('total_marks'),
                TextColumn::make('questions_count')->label('Questions')->counts('questions'),
                TextColumn::make('share_token')
                    ->label('Share link')
                    ->formatStateUsing(fn (?string $state) => $state ? url("/exam/{$state}") : '—')
                    ->copyable()
                    ->copyMessage('Link copied'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ExamStatus::class),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->color('success')
                    ->icon('heroicon-o-globe-alt')
                    ->visible(fn (Exam $record) => $record->status === ExamStatus::Draft)
                    ->requiresConfirmation()
                    ->authorize('publish')
                    ->action(function (Exam $record) {
                        $record->recalculateTotalMarks();
                        $record->publish();

                        Notification::make()->title('Exam published')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
