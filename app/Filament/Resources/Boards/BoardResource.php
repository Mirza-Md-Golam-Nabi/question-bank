<?php

namespace App\Filament\Resources\Boards;

use App\Enums\PaperSourceType;
use App\Filament\Resources\Boards\Pages\ManageBoards;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\TableActions;
use App\Models\Board;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoardResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Board::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $modelLabel = 'paper source';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // A question shows where it came from by the short name in
                // the reader's language ("ঢাবি 2023" / "DU 2023").
                TextInput::make('name_bn')
                    ->label(__('Name (Bangla)'))
                    ->required(),
                TextInput::make('name')
                    ->label(__('Name (English)'))
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('short_name_bn')
                    ->label(__('Short name (Bangla)'))
                    ->required()
                    ->maxLength(20),
                TextInput::make('short_name')
                    ->label(__('Short name (English)'))
                    ->required()
                    ->maxLength(20),
                Select::make('type')
                    ->label(__('Kind'))
                    ->options(PaperSourceType::class)
                    ->default(PaperSourceType::Board)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('short_name')->label(__('Short'))->badge()
                    ->state(fn (Board $record): string => $record->display_short_name),
                TextColumn::make('name')->searchable(['name', 'name_bn'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderedByDisplayName($direction))
                    ->state(fn (Board $record): string => $record->display_name),
                TextColumn::make('type')->label(__('Kind'))->badge(),
                TextColumn::make('question_papers_count')->label(__('Papers'))->counts('questionPapers'),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('Kind'))->options(PaperSourceType::class),
            ])
            ->recordActions(TableActions::editAndDelete(iconButtons: true))
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBoards::route('/'),
        ];
    }
}
