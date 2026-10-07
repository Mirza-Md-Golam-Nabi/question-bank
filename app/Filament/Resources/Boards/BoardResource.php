<?php

namespace App\Filament\Resources\Boards;

use App\Filament\Resources\Boards\Pages\ManageBoards;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\Board;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BoardResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Board::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('short_name')
                    ->label(__('Short name'))
                    ->required()
                    ->maxLength(10),
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order_index')
            ->columns([
                TextColumn::make('short_name')->label(__('Short'))->badge(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('question_papers_count')->label(__('Papers'))->counts('questionPapers'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBoards::route('/'),
        ];
    }
}
