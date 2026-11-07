<?php

namespace App\Services;

use App\Enums\MobileBankingProvider;
use App\Models\BillingSetting;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

/**
 * The bKash/Nagad details a customer gives with money they sent — for a
 * purchase and for the difference of a plan change alike.
 */
final class ManualPaymentDetails
{
    /**
     * @throws ValidationException
     */
    public static function ensureUsable(MobileBankingProvider|string|null $provider, ?string $payerNumber, string $transactionId): void
    {
        self::ensureProvider($provider);

        if ($payerNumber === null) {
            throw ValidationException::withMessages([
                'payer_number' => __('Enter the mobile number you sent the payment from.'),
            ]);
        }

        if ($transactionId === '') {
            throw ValidationException::withMessages(['transaction_id' => __('Enter the transaction ID.')]);
        }

        // The same transaction can't pay for two purchases — also what
        // keeps a resubmitted form from creating a second payment.
        if (Payment::where('gateway_transaction_id', $transactionId)->exists()) {
            throw ValidationException::withMessages([
                'transaction_id' => __('This transaction ID has already been used.'),
            ]);
        }
    }

    /**
     * Where money owed back is to be sent: one of the services the Admin
     * takes payments with, and a valid mobile number.
     *
     * @throws ValidationException
     */
    public static function ensureReceivable(MobileBankingProvider|string|null $provider, ?string $number): void
    {
        self::ensureProvider($provider);

        if ($number === null) {
            throw ValidationException::withMessages([
                'payer_number' => __('Enter the mobile number to send the money to.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private static function ensureProvider(MobileBankingProvider|string|null $provider): void
    {
        $provider = $provider instanceof MobileBankingProvider ? $provider->value : $provider;

        if (! array_key_exists((string) $provider, BillingSetting::current()->receivingNumbers())) {
            throw ValidationException::withMessages(['payer_provider' => __('Choose how you sent the payment.')]);
        }
    }
}
