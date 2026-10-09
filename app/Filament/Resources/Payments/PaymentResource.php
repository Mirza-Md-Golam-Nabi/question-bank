<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\Payment;
use App\Services\SubscriptionPurchaseService;
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
use Illuminate\Database\Eloquent\Model;

/**
 * The Admin's side of a purchase: match the customer's transaction id
 * against the money that arrived, then approve, reject, or — later —
 * refund. A payment is a financial record, so it can't be typed in, edited
 * or deleted here; it only moves through SubscriptionPurchaseService.
 */
class PaymentResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.referrer', 'plan'])
            // The "same number as referrer" flag for every row, in this
            // one query rather than a lookup per row.
            ->withExists(['itself as same_number_as_referrer' => fn (Builder $query) => $query->sharingPayerNumberWithReferrer()]);
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Date'))->dateTime()->sortable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('plan.name')->label(__('Plan'))->placeholder('—'),
                TextColumn::make('amount')->label(__('Paid'))->money('BDT'),
                TextColumn::make('discount_amount')->label(__('Discount'))->money('BDT')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('wallet_amount')->label(__('Wallet credit'))->money('BDT')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payer_provider')->label(__('Sent with'))->badge()->placeholder('—'),
                TextColumn::make('payer_number')->label(__('Sent from'))->searchable()->placeholder('—'),
                TextColumn::make('gateway_transaction_id')->label(__('Txn ID'))->searchable()->copyable()->placeholder('—'),
                TextColumn::make('user.referrer.name')->label(__('Referred by'))->placeholder('—'),
                IconColumn::make('same_number_as_referrer')
                    ->label(__('Same number as referrer'))
                    ->icon(fn (mixed $state): ?Heroicon => $state ? Heroicon::OutlinedExclamationTriangle : null)
                    ->color('warning')
                    ->tooltip(__('Paid from a number the referrer also uses — check that this is a real new customer.')),
                TextColumn::make('status')->badge(),
                TextColumn::make('refundable_until')->label(__('Refundable until'))->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('review_note')->label(__('Note'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Payment $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalDescription(__('Only approve after checking that this transaction really arrived. The subscription starts immediately.'))
                    ->action(fn (Payment $record) => self::notifyOutcome(
                        app(SubscriptionPurchaseService::class)->approve($record, auth()->user()),
                        __('Payment approved — subscription started.'),
                    )),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->visible(fn (Payment $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->schema([self::noteField(__('Reason (shown to the customer)'))])
                    ->action(fn (Payment $record, array $data) => self::notifyOutcome(
                        app(SubscriptionPurchaseService::class)->reject($record, auth()->user(), $data['review_note'] ?? null),
                        __('Payment rejected.'),
                    )),
                Action::make('refund')
                    ->label(__('Refund'))
                    ->color('warning')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    // Gone once the refund period has passed.
                    ->visible(fn (Payment $record): bool => $record->isRefundable())
                    ->requiresConfirmation()
                    ->modalDescription(__("Send the money back yourself first. This ends the subscription, returns any wallet credit used, and takes back the referrer's reward. It cannot be undone."))
                    ->schema([self::noteField(__('Reason / refund reference'))])
                    ->action(fn (Payment $record, array $data) => self::notifyOutcome(
                        app(SubscriptionPurchaseService::class)->refund($record, auth()->user(), $data['review_note'] ?? null),
                        __('Payment refunded.'),
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    private static function noteField(string $label): TextInput
    {
        return TextInput::make('review_note')->label($label)->maxLength(255);
    }

    /**
     * The service refuses a payment that is no longer in the state the
     * action expects (someone else got there first) — say so rather than
     * report a success that didn't happen.
     */
    private static function notifyOutcome(bool $done, string $successMessage): void
    {
        $done
            ? Notification::make()->title($successMessage)->success()->send()
            : Notification::make()->title(__('This payment has already been handled.'))->warning()->send();
    }
}
