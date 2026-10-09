<?php

namespace App\Filament\Support\Pages;

use App\Enums\MobileBankingProvider;
use App\Filament\Support\Concerns\ShowsPlanSummary;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\OtpService;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the Teacher and Student panels' "My Subscription" page — the
 * same "current plan + usage + buy a plan" view for both roles, just backed
 * by whichever user is logged into that panel. Prices, discounts, credit
 * and what happens to a payment are all SubscriptionPurchaseService's; this
 * page only collects the form and shows the result.
 */
abstract class MySubscriptionPage extends Page
{
    use ShowsPlanSummary;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.my-subscription';

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function plans(): Collection
    {
        // Asked for by the price list, the plan cards and the buy action
        // in one render — fetched once for all of them.
        return once(function (): Collection {
            $role = Auth::user()->role->subscriptionTargetRole();

            return $role ? SubscriptionPlan::purchasableFor($role)->get() : new Collection;
        });
    }

    /**
     * What each plan on sale costs this user, keyed by plan id — priced
     * together, so the user's discount is looked up once, not per plan.
     *
     * @return array<int, array{list_price: float, discount: float, wallet: float, payable: float}>
     */
    public function priceList(): array
    {
        return app(SubscriptionPurchaseService::class)->quotes(Auth::user(), $this->plans(), useWallet: false);
    }

    public function walletBalance(): float
    {
        return app(WalletService::class)->balanceFor(Auth::user());
    }

    public function pendingPayment(): ?Payment
    {
        return Auth::user()->pendingPayment()?->load('plan');
    }

    /**
     * @return Collection<int, Payment>
     */
    public function payments(): Collection
    {
        return Auth::user()->payments()->with('plan')->latest('id')->limit(10)->get();
    }

    /**
     * @return array{list_price: float, discount: float, wallet: float, payable: float}
     */
    public function quoteFor(SubscriptionPlan $plan, bool $useWallet = false): array
    {
        return app(SubscriptionPurchaseService::class)->quote(Auth::user(), $plan, $useWallet);
    }

    public function buyAction(): Action
    {
        return Action::make('buy')
            ->label(__('Buy'))
            ->modalHeading(fn (array $arguments): string => __('Buy :plan', ['plan' => $this->planFrom($arguments)->name]))
            ->modalSubmitActionLabel(__('Submit'))
            ->fillForm(fn (): array => ['use_wallet' => $this->walletBalance() > 0])
            ->schema(fn (array $arguments): array => $this->purchaseForm($this->planFrom($arguments)))
            ->action(function (array $data, array $arguments, Action $action): void {
                try {
                    $payment = app(SubscriptionPurchaseService::class)->request(Auth::user(), $this->planFrom($arguments), $data);
                } catch (ValidationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    $action->halt();
                }

                Notification::make()
                    ->title($payment->isSuccessful()
                        ? __('Your subscription is active.')
                        : __('Payment submitted. Your subscription starts as soon as it is approved.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function planFrom(array $arguments): SubscriptionPlan
    {
        return $this->plans()->firstOrFail('id', (int) ($arguments['plan'] ?? 0));
    }

    /**
     * The fields shown depend on what is left to pay: nothing to send (and
     * so nothing to fill in) when wallet credit covers the whole price.
     *
     * @return array<int, mixed>
     */
    private function purchaseForm(SubscriptionPlan $plan): array
    {
        $settings = BillingSetting::current();
        $balance = $this->walletBalance();
        $hasToPay = fn (Get $get): bool => $this->quoteFor($plan, (bool) $get('use_wallet'))['payable'] > 0;
        $needsOtp = fn (Get $get): bool => (bool) $get('use_wallet')
            && $balance > 0
            && app(OtpService::class)->isRequiredForCredit();

        return [
            Toggle::make('use_wallet')
                ->label(__('Use my wallet credit (৳:amount)', ['amount' => number_format($balance, 2)]))
                ->visible($balance > 0)
                ->live(),

            TextEntry::make('summary')
                ->hiddenLabel()
                ->state(fn (Get $get): HtmlString => new HtmlString(view('filament.pages.partials.purchase-summary', [
                    'quote' => $this->quoteFor($plan, (bool) $get('use_wallet')),
                    'receivingNumbers' => $settings->receivingNumbers(),
                    'instructions' => $settings->payment_instructions,
                ])->render())),

            TextInput::make('otp_code')
                ->label(__('Verification code'))
                ->helperText(__('Spending wallet credit needs a code sent to your phone number.'))
                ->visible($needsOtp)
                ->required($needsOtp)
                ->suffixAction(
                    Action::make('sendOtp')
                        ->label(__('Send code'))
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->action(fn () => $this->sendOtp()),
                ),

            Radio::make('payer_provider')
                ->label(__('Sent with'))
                ->options(collect($settings->receivingNumbers())
                    ->map(fn (string $number, string $provider): string => MobileBankingProvider::from($provider)->getLabel())
                    ->all())
                ->inline()
                ->visible($hasToPay)
                ->required($hasToPay),

            TextInput::make('payer_number')
                ->label(__('Number you sent the payment from'))
                ->tel()
                ->visible($hasToPay)
                ->required($hasToPay),

            TextInput::make('transaction_id')
                ->label(__('Transaction ID'))
                ->maxLength(100)
                ->visible($hasToPay)
                ->required($hasToPay),
        ];
    }

    public function sendOtp(): void
    {
        try {
            app(OtpService::class)->send(Auth::user());
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('A verification code has been sent to your phone.'))->success()->send();
    }
}
