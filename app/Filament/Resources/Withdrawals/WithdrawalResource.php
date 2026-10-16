<?php

namespace App\Filament\Resources\Withdrawals;

use App\Enums\SubscriptionTargetRole;
use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Withdrawals\Pages\ManageWithdrawals;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Teachers' and Students' requests to take their referral earnings out as
 * cash. The Admin sends the money by bKash/Nagad/Rocket outside the system,
 * then marks the request paid with the transaction id — or rejects it,
 * which puts the amount back in the user's wallet. Both are
 * WithdrawalService's.
 */
class WithdrawalResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Withdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Payroll;

    public static function getNavigationBadge(): ?string
    {
        $waiting = Withdrawal::where('status', WithdrawalStatus::Requested)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'processedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label(__('Name'))->searchable()
                    ->description(fn (Withdrawal $record): ?string => $record->user?->role->getLabel()),
                TextColumn::make('amount')->money('BDT'),
                TextColumn::make('provider')->label(__('Send to'))->badge(),
                // The Admin needs the full number to send the money.
                TextColumn::make('account_number')->label(__('Number'))->copyable(),
                IconColumn::make('number_used_by_referred_buyer')
                    ->label(__('Same number as a referred buyer'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                    ->trueColor('danger')
                    ->falseIcon(null)
                    ->tooltip(fn (Withdrawal $record): ?string => $record->number_used_by_referred_buyer
                        ? __('Someone this user referred paid from this number — check it is not their own second account.')
                        : null),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label(__('Requested'))->dateTime(),
                TextColumn::make('transaction_id')->label(__('Transaction ID'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('processedBy.name')->label(__('Handled by'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(WithdrawalStatus::class)->default(WithdrawalStatus::Requested->value),
                SelectFilter::make('role')
                    ->label(__('Role'))
                    // The roles with a wallet are the ones that buy plans.
                    ->options(SubscriptionTargetRole::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $role) => $query->whereHas('user', fn (Builder $user) => $user->where('role', $role)))),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label(__('Mark paid'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Withdrawal $record): bool => $record->isRequested())
                    ->modalDescription(__('Send the money to the number shown first, then enter the transaction id.'))
                    ->schema([
                        TextInput::make('transaction_id')->label(__('Transaction ID'))->maxLength(100)->required(),
                    ])
                    ->action(function (Withdrawal $record, array $data): void {
                        app(WithdrawalService::class)->markPaid($record, Auth::user(), $data['transaction_id'])
                            ? Notification::make()->title(__('Marked as paid'))->success()->send()
                            : Notification::make()->title(__('This request was already handled.'))->warning()->send();
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Withdrawal $record): bool => $record->isRequested())
                    ->modalDescription(__("The amount goes back into the user's wallet."))
                    ->schema([
                        TextInput::make('note')->label(__('Reason (the user sees it)'))->maxLength(255),
                    ])
                    ->action(function (Withdrawal $record, array $data): void {
                        app(WithdrawalService::class)->reject($record, Auth::user(), $data['note'] ?? null)
                            ? Notification::make()->title(__('Request rejected'))->success()->send()
                            : Notification::make()->title(__('This request was already handled.'))->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWithdrawals::route('/'),
        ];
    }
}
