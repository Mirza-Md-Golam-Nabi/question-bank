<?php

namespace App\Filament\Teacher\Resources\OmrChecks\Tables;

use App\Enums\Locale;
use App\Filament\Support\TableActions;
use App\Filament\Teacher\Resources\Exams\Tables\ExamsTable;
use App\Filament\Teacher\Resources\OmrChecks\Pages\ListOmrChecks;
use App\Filament\Teacher\Resources\OmrChecks\Schemas\OmrCheckForm;
use App\Models\Exam;
use App\Services\OmrCheckService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Teacher's / School's OMR checks (CLAUDE.md rule 10.12): each with
 * its OMR page (the sheet, scanning) and results — the exams' own buttons
 * (ExamsTable) — and its answer key to change until a sheet is saved.
 */
class OmrChecksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('attempts')->withExists('attempts'))
            ->columns([
                TextColumn::make('title')->label(__('Title'))->searchable(),
                TextColumn::make('questions')
                    ->label(__('Questions'))
                    ->state(fn (Exam $record): string => Locale::current()->writeDigits((string) count($record->answerKey()['answers'])))
                    ->alignCenter(),
                TextColumn::make('total_marks')
                    ->label(__('Total marks'))
                    ->formatStateUsing(fn ($state): string => Locale::current()->writeDigits((string) (float) $state))
                    ->alignCenter(),
                TextColumn::make('omr_identity')->label(__('Saved by'))->badge()->color('gray')->alignCenter(),
                TextColumn::make('attempts_count')
                    ->label(__('Sheets saved'))
                    ->formatStateUsing(fn ($state): string => Locale::current()->writeDigits((string) $state))
                    ->alignCenter(),
                TextColumn::make('created_at')->label(__('Created'))->localDate()->alignCenter(),
            ])
            ->recordUrl(null)
            ->recordActions([
                ExamsTable::omrAction(),
                ExamsTable::resultsAction(),
                ActionGroup::make([
                    Action::make('editKey')
                        ->label(__('Edit answer key'))
                        ->icon('heroicon-o-pencil-square')
                        ->visible(fn (Exam $record): bool => app(OmrCheckService::class)->canChangeKey($record))
                        ->modalHeading(__('Edit answer key'))
                        ->modalWidth(Width::FourExtraLarge)
                        ->fillForm(fn (Exam $record): array => OmrCheckForm::fill($record))
                        ->schema(OmrCheckForm::components())
                        ->modalSubmitActionLabel(__('Save'))
                        ->authorize('update')
                        ->action(fn (Exam $record, array $data, ListOmrChecks $livewire, Action $action) => $livewire->saveCheck($data, $action, $record)),
                    ExamsTable::cancelExamAction()->grouped(),
                    DeleteAction::make(),
                ])
                    ->label(__('More actions'))
                    ->tooltip(__('More actions'))
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray'),
            ])
            ->toolbarActions(TableActions::bulkDelete());
    }
}
