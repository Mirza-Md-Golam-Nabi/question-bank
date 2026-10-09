<?php

namespace App\Services;

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletTransactionType;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The whole life of a subscription purchase, and the only code that moves
 * a payment from one state to another:
 *
 *   request  → the customer picks a plan, has any discount and wallet
 *              credit taken off, sends the rest by bKash/Nagad and submits
 *              the transaction id (pending)
 *   approve  → the Admin has matched the money: the subscription starts
 *              and the referrer is rewarded (success)
 *   reject   → the money never arrived: wallet credit goes back (rejected)
 *   refund   → the money was given back: subscription ends, wallet credit
 *              goes back, the referrer's reward is cancelled (refunded) —
 *              only inside the refund period the Admin has set
 *
 * A payment gateway, once chosen, replaces only how `approve` gets called
 * — its callback calls it instead of an Admin. Every step is one database
 * transaction that first locks the payment and re-checks its state, so a
 * double click or a callback arriving twice does nothing the second time.
 */
class SubscriptionPurchaseService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly ReferralService $referrals,
        private readonly OtpService $otp,
    ) {}

    /**
     * What buying this plan would cost this user right now.
     *
     * @return array{list_price: float, discount: float, wallet: float, payable: float}
     */
    public function quote(User $user, SubscriptionPlan $plan, bool $useWallet): array
    {
        return $this->quotes($user, [$plan], $useWallet)[$plan->id];
    }

    /**
     * The same, for several plans at once (a price list): the user's
     * discount and wallet balance are the same whichever plan they pick,
     * so they are looked up once rather than once per plan.
     *
     * @param  iterable<SubscriptionPlan>  $plans
     * @return array<int, array{list_price: float, discount: float, wallet: float, payable: float}> Keyed by plan id.
     */
    public function quotes(User $user, iterable $plans, bool $useWallet): array
    {
        $discountPercent = $this->referrals->discountPercentFor($user);
        $balance = $useWallet ? max($this->wallet->balanceFor($user), 0) : 0.0;

        $quotes = [];

        foreach ($plans as $plan) {
            $listPrice = (float) $plan->price;
            $discount = round($listPrice * $discountPercent / 100, 2);
            $wallet = round(min($balance, $listPrice - $discount), 2);

            $quotes[$plan->id] = [
                'list_price' => $listPrice,
                'discount' => $discount,
                'wallet' => $wallet,
                'payable' => round($listPrice - $discount - $wallet, 2),
            ];
        }

        return $quotes;
    }

    /**
     * Places the purchase. Wallet credit is taken straight away, so it
     * can't be spent twice while the Admin is still checking the payment.
     * When credit covers the whole price there is nothing to check and the
     * subscription starts immediately.
     *
     * @param  array{use_wallet?: bool, otp_code?: ?string, payer_provider?: MobileBankingProvider|string|null, payer_number?: ?string, transaction_id?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function request(User $user, SubscriptionPlan $plan, array $details): Payment
    {
        if ($plan->target_role !== $user->role->subscriptionTargetRole() || (float) $plan->price <= 0) {
            throw ValidationException::withMessages(['plan' => __('This plan is not available to you.')]);
        }

        $payment = DB::transaction(function () use ($user, $plan, $details): Payment {
            // Serialises this user's purchases: the pending check and the
            // wallet balance below are read under the lock.
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($user->pendingPayment()) {
                throw ValidationException::withMessages([
                    'plan' => __('You already have a payment waiting for approval.'),
                ]);
            }

            $quote = $this->quote($user, $plan, (bool) ($details['use_wallet'] ?? false));

            $payerNumber = User::normalizePhone($details['payer_number'] ?? null);
            $transactionId = trim((string) ($details['transaction_id'] ?? ''));

            if ($quote['payable'] > 0) {
                $this->ensurePaymentDetailsAreUsable($details['payer_provider'] ?? null, $payerNumber, $transactionId);
            }

            // Checked last: a code works once, so it mustn't be used up by a
            // request that then fails on something else.
            if ($quote['wallet'] > 0 && $this->otp->isRequiredForCredit() && ! $this->otp->verify($user, $details['otp_code'] ?? null)) {
                throw ValidationException::withMessages([
                    'otp_code' => __('The verification code is wrong or has expired.'),
                ]);
            }

            $payment = Payment::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'list_price' => $quote['list_price'],
                'discount_amount' => $quote['discount'],
                'wallet_amount' => $quote['wallet'],
                'amount' => $quote['payable'],
                'gateway' => PaymentGateway::Manual,
                'gateway_transaction_id' => $quote['payable'] > 0 ? $transactionId : null,
                'payer_provider' => $quote['payable'] > 0 ? $details['payer_provider'] : null,
                'payer_number' => $quote['payable'] > 0 ? $payerNumber : null,
                'status' => PaymentStatus::Pending,
            ]);

            if ($quote['wallet'] > 0) {
                $this->wallet->debit($user, $quote['wallet'], WalletTransactionType::Purchase, $payment);
            }

            Log::info('Subscription purchase requested.', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                ...$quote,
            ]);

            return $payment;
        });

        if ($payment->amount <= 0) {
            $this->approve($payment);
        }

        return $payment->refresh();
    }

    /**
     * Confirms the money arrived: starts the subscription, starts the
     * refund period, and records the referrer's reward (which matures when
     * that period ends). False when the payment was no longer pending — already
     * approved or rejected — in which case nothing happens.
     *
     * @param  ?User  $reviewer  The Admin approving it; null when no person was involved.
     */
    public function approve(Payment $payment, ?User $reviewer = null): bool
    {
        return DB::transaction(function () use ($payment, $reviewer): bool {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment?->isPending() || ! $payment->plan) {
                return false;
            }

            $subscription = $this->startSubscription($payment->user, $payment->plan);

            $payment->update([
                'status' => PaymentStatus::Success,
                'subscription_id' => $subscription->id,
                'paid_at' => now(),
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'refundable_until' => BillingSetting::current()->refundDeadlineFrom(now()),
            ]);

            $this->referrals->rewardFor($payment);

            Log::info('Payment approved.', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'reviewed_by' => $reviewer?->id,
            ]);

            return true;
        });
    }

    /**
     * The money never arrived (wrong or made-up transaction id): the
     * purchase is called off and any wallet credit put towards it returned.
     */
    public function reject(Payment $payment, User $reviewer, ?string $note = null): bool
    {
        return DB::transaction(function () use ($payment, $reviewer, $note): bool {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment?->isPending()) {
                return false;
            }

            $payment->update([
                'status' => PaymentStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->returnWalletCredit($payment);

            Log::info('Payment rejected.', ['payment_id' => $payment->id, 'reviewed_by' => $reviewer->id]);

            return true;
        });
    }

    /**
     * Undoes an approved purchase after its money has been given back
     * (which happens outside the system): the subscription it bought ends
     * now, wallet credit put towards it is returned, and the reward it
     * earned the referrer is cancelled.
     *
     * Only possible inside the payment's refund period. False once that
     * has passed: the reward has matured by then and can't be taken back.
     */
    public function refund(Payment $payment, User $reviewer, ?string $note = null): bool
    {
        return DB::transaction(function () use ($payment, $reviewer, $note): bool {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment?->isRefundable()) {
                return false;
            }

            $payment->update([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
                'reviewed_by' => $reviewer->id,
                'review_note' => $note,
            ]);

            $payment->subscription?->update([
                'status' => SubscriptionStatus::Cancelled,
                'ends_at' => now(),
            ]);

            $this->returnWalletCredit($payment);
            $this->referrals->cancelRewardFor($payment);

            Log::info('Payment refunded.', ['payment_id' => $payment->id, 'refunded_by' => $reviewer->id]);

            return true;
        });
    }

    /**
     * A renewal of the plan the user is already on is added to the end of
     * their current period instead of overlapping it, so buying early
     * never costs them days they already paid for.
     */
    private function startSubscription(User $user, SubscriptionPlan $plan): Subscription
    {
        $currentPeriodEnd = $user->subscriptions()
            ->active()
            ->where('plan_id', $plan->id)
            ->whereNotNull('ends_at')
            ->max('ends_at');

        $periodStart = $currentPeriodEnd ? Carbon::parse($currentPeriodEnd) : now();

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'ends_at' => $plan->billing_cycle->periodEndFrom($periodStart),
        ]);
    }

    private function returnWalletCredit(Payment $payment): void
    {
        if ($payment->wallet_amount > 0) {
            $this->wallet->credit($payment->user, $payment->wallet_amount, WalletTransactionType::PurchaseReturn, $payment);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensurePaymentDetailsAreUsable(MobileBankingProvider|string|null $provider, ?string $payerNumber, string $transactionId): void
    {
        $provider = $provider instanceof MobileBankingProvider ? $provider->value : $provider;

        if (! array_key_exists((string) $provider, BillingSetting::current()->receivingNumbers())) {
            throw ValidationException::withMessages(['payer_provider' => __('Choose how you sent the payment.')]);
        }

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
}
