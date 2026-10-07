<?php

namespace App\Filament\Resources\StaffPayouts;

use App\Filament\Resources\StaffPayouts\Pages\ManageStaffPayouts;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\StaffPayout;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffPayoutResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = StaffPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Payroll;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference_note')
                    ->label(__('Reference note (e.g. bKash TrxID)')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('staff.name')->label(__('Staff'))->searchable(),
                TextColumn::make('total_amount')->money('BDT'),
                TextColumn::make('status')->badge(),
                TextColumn::make('reference_note'),
                TextColumn::make('paidBy.name')->label(__('Paid by')),
                TextColumn::make('paid_at')->dateTime(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaffPayouts::route('/'),
        ];
    }
}
