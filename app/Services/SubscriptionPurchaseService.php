<?php

namespace App\Services;

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Closure;
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
 *              and the referrer is rewarded (success) — the referrer being
 *              who it was when the purchase was placed
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
        private readonly PurchaseNotifications $notifications,
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
     * @param  array{use_wallet?: bool, otp_code?: ?string, payer_provider?: MobileBankingProvider|string|null, payer_number?: ?string, transaction_id?: ?string, class_ids?: mixed, subject_ids?: mixed}  $details
     *
     * @throws ValidationException
     */
    public function request(User $user, SubscriptionPlan $plan, array $details): Payment
    {
        if (! $this->isAvailableTo($user, $plan)) {
            throw ValidationException::withMessages(['plan' => __('This plan is not available to you.')]);
        }

        $selections = $plan->planConditions()->resolveSelections($details);

        $payment = DB::transaction(function () use ($user, $plan, $details, $selections): Payment {
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

            // Who referred the buyer at this moment stays with the payment:
            // a student's referrer can change before it is approved.
            $referral = $this->referrals->referralFor($user);

            $payment = Payment::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'referrer_id' => $referral['referrer']->id ?? null,
                'student_referral_id' => $referral['student_referral']->id ?? null,
                'selections' => $selections,
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

            // A student's class follows the one they buy for, so their
            // practice and their next purchase start from it.
            if ($user->role === UserRole::Student && count($selections['class_ids']) === 1) {
                $user->update(['academic_class_id' => $selections['class_ids'][0]]);
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

        $payment->refresh();
        $this->notifications->placed($payment);

        return $payment;
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
        return $this->thenNotify($payment, fn (Payment $payment) => $this->notifications->approved($payment), DB::transaction(function () use ($payment, $reviewer): bool {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment?->isPending() || ! $payment->plan) {
                return false;
            }

            $subscription = $this->startSubscription($payment->user, $payment->plan, $payment->selections ?? []);

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
        }));
    }

    /**
     * The money never arrived (wrong or made-up transaction id): the
     * purchase is called off and any wallet credit put towards it returned.
     */
    public function reject(Payment $payment, User $reviewer, ?string $note = null): bool
    {
        return $this->thenNotify($payment, fn (Payment $payment) => $this->notifications->rejected($payment), DB::transaction(function () use ($payment, $reviewer, $note): bool {
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
        }));
    }

    /**
     * Undoes an approved purchase after its money has been given back
     * (which happens outside the system): the subscription it bought ends
     * now, wallet credit put towards it is returned, and the reward it
     * earned the referrer is cancelled.
     *
     * Only possible inside the payment's refund period, and only for a
     * subscription nothing has been done with (`Payment::isRefundable()`).
     * False otherwise: past the period the reward has matured and can't be
     * taken back, and a used subscription has been paid for.
     */
    public function refund(Payment $payment, User $reviewer, ?string $note = null): bool
    {
        return $this->thenNotify($payment, fn (Payment $payment) => $this->notifications->refunded($payment), DB::transaction(function () use ($payment, $reviewer, $note): bool {
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
        }));
    }

    /**
     * Tells whoever should know once a step has really happened (it
     * returned true, its transaction committed) — so a step done twice is
     * told once.
     *
     * @param  Closure(Payment): void  $notify
     */
    private function thenNotify(Payment $payment, Closure $notify, bool $happened): bool
    {
        if ($happened) {
            $notify($payment->refresh());
        }

        return $happened;
    }

    /**
     * Whether the user may buy this plan now: one on sale for their role,
     * and — for the free trial — only if they have never had it.
     */
    public function isAvailableTo(User $user, SubscriptionPlan $plan): bool
    {
        $role = $user->role->subscriptionTargetRole();

        if (! $role || ! SubscriptionPlan::purchasableFor($role)->whereKey($plan->id)->exists()) {
            return false;
        }

        return ! $plan->is_trial || ! $this->hasHadTrial($user);
    }

    public function hasHadTrial(User $user): bool
    {
        return $user->subscriptions()->whereHas('plan', fn ($query) => $query->where('is_trial', true))->exists();
    }

    /**
     * A renewal of the same plan with the same picks is added to the end
     * of the current period instead of overlapping it, so buying early
     * never costs days already paid for. The plan's conditions are copied
     * onto the subscription as they are now.
     *
     * @param  array{class_ids?: array<int, int>, subject_ids?: array<int, int>}  $selections
     */
    private function startSubscription(User $user, SubscriptionPlan $plan, array $selections): Subscription
    {
        $currentPeriodEnd = $user->subscriptions()
            ->active()
            ->where('plan_id', $plan->id)
            ->whereNotNull('ends_at')
            ->get(['ends_at', 'selections'])
            ->filter(fn (Subscription $subscription): bool => ($subscription->selections ?? []) == $selections)
            ->max('ends_at');

        $periodStart = $currentPeriodEnd ? Carbon::parse($currentPeriodEnd) : now();

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'conditions' => $plan->conditions ?? [],
            'selections' => $selections,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'ends_at' => $plan->periodEndFrom($periodStart),
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
