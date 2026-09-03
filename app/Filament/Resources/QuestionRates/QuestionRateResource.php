<?php

namespace App\Filament\Resources\QuestionRates;

use App\Filament\Resources\QuestionRates\Pages\ManageQuestionRates;
use App\Filament\Support\NavigationGroup;
use App\Models\QuestionRate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionRateResource extends Resource
{
    protected static ?string $model = QuestionRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Payroll;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label('Subject (blank = default rate)')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('rate_amount')
                    ->label('Rate per question')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                DatePicker::make('effective_from')
                    ->default(now())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('subject.name')->label('Subject')->placeholder('Default'),
                TextColumn::make('rate_amount')->money('BDT'),
                TextColumn::make('effective_from')->date(),
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
            'index' => ManageQuestionRates::route('/'),
        ];
    }
}
