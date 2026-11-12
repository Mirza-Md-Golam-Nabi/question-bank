<?php

namespace App\Filament\Support\Pages;

use App\Enums\Locale;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\SendsCreditOtp;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ManualPaymentFields;
use App\Filament\Support\NavigationGroup;
use App\Models\BillingSetting;
use App\Models\InstitutionName;
use App\Models\Payment;
use App\Services\InstitutionNameService;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the Teacher and School panels' "Institution names" page: the
 * names printed at the top of a question paper — the free one, and extra
 * ones paid for by the period. Every rule (when a name can be changed,
 * what is usable, paying) is InstitutionNameService's; this page only
 * collects the forms and shows the result.
 */
abstract class InstitutionNamesPage extends Page
{
    use NotifiesValidationFailures;
    use SendsCreditOtp;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.institution-names';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Institution names');
    }

    public function getTitle(): string
    {
        return __('Institution names');
    }

    /**
     * @return Collection<int, InstitutionName>
     */
    public function names(): Collection
    {
        $names = $this->service()->namesFor(Auth::user());
        $names->load(['payments' => fn ($query) => $query->latest('id')]);

        return $names;
    }

    public function pendingPayment(): ?Payment
    {
        return Auth::user()->pendingPayment()?->load(['plan', 'institutionName']);
    }

    public function isOnSale(): bool
    {
        return $this->service()->isOnSale();
    }

    public function settings(): BillingSetting
    {
        return BillingSetting::current();
    }

    public function service(): InstitutionNameService
    {
        return app(InstitutionNameService::class);
    }

    /**
     * Writes the free name the first time.
     */
    public function addFreeNameAction(): Action
    {
        return Action::make('addFreeName')
            ->label(__('Add your institution name'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalDescription(Locale::current()->writeDigits(__('It is free and printed at the top of your question papers. It can be changed once every :days days.', ['days' => $this->settings()->institution_name_change_days])))
            ->schema([$this->nameField()])
            ->action(function (array $data, Action $action): void {
                $this->rescueWithNotification(fn () => $this->service()->saveFreeName(Auth::user(), $data['name']), $action);

                Notification::make()->title(__('Name saved'))->success()->send();
            });
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->label(__('Change'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->size('sm')
            ->color('gray')
            ->modalHeading(__('Change the name'))
            ->modalDescription(Locale::current()->writeDigits(__('After a change, the name can be changed again only after :days days.', ['days' => $this->settings()->institution_name_change_days])))
            ->fillForm(fn (array $arguments): array => ['name' => $this->ownName($arguments)->name])
            ->schema([$this->nameField()])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->rescueWithNotification(fn () => $this->service()->rename(Auth::user(), $this->ownName($arguments), $data['name']), $action);

                Notification::make()->title(__('Name saved'))->success()->send();
            });
    }

    public function addPaidNameAction(): Action
    {
        return Action::make('addPaidName')
            ->label(__('Add another name'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('Add another name'))
            ->modalDescription(fn (): string => $this->priceDescription())
            ->fillForm(fn (): array => ['use_wallet' => $this->walletBalance() > 0])
            ->schema(fn (): array => [$this->nameField(), ...$this->paymentFields()])
            ->action(function (array $data, Action $action): void {
                $payment = $this->rescueWithNotification(fn () => $this->service()->requestNew(Auth::user(), $data['name'], $data), $action);

                $this->notifyPlaced($payment);
            });
    }

    public function renewAction(): Action
    {
        return Action::make('renew')
            ->label(fn (array $arguments): string => $this->ownName($arguments)->isUsable() ? __('Add more days') : __('Pay'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('Pay for ":name"', ['name' => $this->ownName($arguments)->name]))
            ->modalDescription(fn (): string => $this->priceDescription())
            ->fillForm(fn (): array => ['use_wallet' => $this->walletBalance() > 0])
            ->schema(fn (): array => $this->paymentFields())
            ->action(function (array $data, array $arguments, Action $action): void {
                $payment = $this->rescueWithNotification(fn () => $this->service()->requestRenewal(Auth::user(), $this->ownName($arguments), $data), $action);

                $this->notifyPlaced($payment);
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('Remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->size('sm')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments, Action $action): void {
                $this->rescueWithNotification(function () use ($arguments): bool {
                    $this->service()->delete(Auth::user(), $this->ownName($arguments));

                    return true;
                }, $action);

                Notification::make()->title(__('Name removed'))->success()->send();
            });
    }

    private function notifyPlaced(Payment $payment): void
    {
        Notification::make()
            ->title($payment->isSuccessful()
                ? __('The name can be printed now.')
                : __('Payment submitted. The name can be printed as soon as it is approved.'))
            ->success()
            ->send();
    }

    private function priceDescription(): string
    {
        return Locale::current()->writeDigits(__('৳:price for :days days. When the days run out only your first name can be printed, until you pay again.', [
            'price' => number_format($this->settings()->institution_name_price, 2),
            'days' => $this->settings()->institution_name_days,
        ]));
    }

    private function walletBalance(): float
    {
        return Auth::user()->role->holdsWallet() ? app(WalletService::class)->balanceFor(Auth::user()) : 0.0;
    }

    /**
     * @return array<int, mixed>
     */
    private function paymentFields(): array
    {
        return ManualPaymentFields::components(
            fn (bool $useWallet): array => $this->service()->quote(Auth::user(), $useWallet),
            $this->walletBalance(),
            fn () => $this->sendOtp(),
            __('Price'),
        );
    }

    private function nameField(): TextInput
    {
        return TextInput::make('name')
            ->label(__('Institution name'))
            ->required()
            ->maxLength(InstitutionNameService::MAX_LENGTH);
    }

    /**
     * One of the user's own names, never someone else's.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function ownName(array $arguments): InstitutionName
    {
        return Auth::user()->institutionNames()->findOrFail((int) ($arguments['name'] ?? 0));
    }
}
