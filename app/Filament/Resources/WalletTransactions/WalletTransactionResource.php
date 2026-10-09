<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Enums\WalletTransactionType;
use App\Filament\Resources\WalletTransactions\Pages\ManageWalletTransactions;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The wallet ledger, for the Admin to audit: every credit given and spent,
 * and the payment behind it. Read-only — rows are only ever written by
 * WalletService.
 */
class WalletTransactionResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'payment.user']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Date'))->dateTime(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->money('BDT'),
                TextColumn::make('payment.user.name')->label(__('Payment by'))->placeholder('—'),
                TextColumn::make('payment.gateway_transaction_id')->label(__('Txn ID'))->placeholder('—'),
                TextColumn::make('expires_at')->label(__('Expires'))->date()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->options(WalletTransactionType::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWalletTransactions::route('/'),
        ];
    }
}
