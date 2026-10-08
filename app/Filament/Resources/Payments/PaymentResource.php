<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\TableActions;
use App\Filament\Support\UserSelect;
use App\Models\Payment;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                UserSelect::make(),
                Select::make('subscription_id')
                    ->label(__('Subscription'))
                    ->relationship('subscription', 'id')
                    ->searchable(),
                TextInput::make('amount')->numeric()->minValue(0)->required(),
                Select::make('gateway')->options(PaymentGateway::class)->required(),
                TextInput::make('gateway_transaction_id')->label(__('Transaction ID'))->unique(ignoreRecord: true),
                Select::make('status')->options(PaymentStatus::class)->required(),
                DateTimePicker::make('paid_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('amount')->money('BDT'),
                TextColumn::make('gateway')->badge(),
                TextColumn::make('gateway_transaction_id')->label(__('Txn ID'))->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('paid_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::class),
                SelectFilter::make('gateway')->options(PaymentGateway::class),
            ])
            ->recordActions(TableActions::editAndDelete())
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
