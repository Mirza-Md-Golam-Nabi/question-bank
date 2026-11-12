<?php

namespace App\Filament\Support;

use App\Enums\MobileBankingProvider;
use App\Models\BillingSetting;
use App\Services\OtpService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * The fields of paying for something by bKash/Nagad — wallet credit, what
 * it comes to and where to send it, the OTP for spending credit, and the
 * sender's number and transaction id — shared by buying a plan and buying
 * an extra institution name. What they mean is
 * SubscriptionPurchaseService::placeManual()'s; this only collects them.
 * Nothing to send (and so nothing to fill in) when credit covers it all.
 */
final class ManualPaymentFields
{
    /**
     * @param  Closure(bool): array{list_price: float, discount: float, wallet: float, payable: float}  $quote  What it costs, with or without wallet credit.
     * @param  float  $walletBalance  The user's credit; no wallet toggle when there is none.
     * @param  Closure(): void  $sendOtp  Sends the OTP for spending credit.
     * @param  string  $priceLabel  What the first line of the summary calls the price.
     * @return array<int, mixed>
     */
    public static function components(Closure $quote, float $walletBalance, Closure $sendOtp, string $priceLabel): array
    {
        $settings = BillingSetting::current();
        $hasToPay = fn (Get $get): bool => $quote((bool) $get('use_wallet'))['payable'] > 0;
        $needsOtp = fn (Get $get): bool => (bool) $get('use_wallet')
            && $walletBalance > 0
            && app(OtpService::class)->isRequiredForCredit();

        return [
            Toggle::make('use_wallet')
                ->label(__('Use my wallet credit (৳:amount)', ['amount' => number_format($walletBalance, 2)]))
                ->visible($walletBalance > 0)
                ->live(),

            TextEntry::make('summary')
                ->hiddenLabel()
                ->state(fn (Get $get): HtmlString => new HtmlString(view('filament.pages.partials.purchase-summary', [
                    'quote' => $quote((bool) $get('use_wallet')),
                    'priceLabel' => $priceLabel,
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
                        ->action($sendOtp),
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
}
