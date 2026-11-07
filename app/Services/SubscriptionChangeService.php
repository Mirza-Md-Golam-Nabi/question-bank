<?php

namespace App\Services;

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PlanChangeRefundMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletTransactionType;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Changing a running subscription after it was bought — the only place
 * that does, and the only place its rules are written:
 *
 *  - **Another plan.** The plan it holds counts as its list price when it
 *    was bought (`subscriptions.plan_price`; 0 for a gift). A dearer plan
 *    costs the difference (no referral discount, no commission on it); a
 *    cheaper one gives the difference back less the Admin's fee
 *    (`BillingSetting.plan_downgrade_fee_percent`). No pro-rating: the new
 *    plan's length is counted from the subscription's start, so time used
 *    is used. It stays the same subscription — what was counted against it
 *    stays counted, group members stay on it — with the new plan's
 *    conditions copied and the classes/subjects picked again. The owner
 *    asks for it (a pending payment the Admin approves, sending back what
 *    a downgrade owes into the wallet or by bKash/Nagad); the Super Admin
 *    makes it at once.
 *  - **The classes/subjects picked.** The owner, as long as nothing has
 *    been done with it; the Super Admin, always (with a reason).
 *
 * The user is told in the bell whenever someone else changed it.
 */
class SubscriptionChangeService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly PurchaseNotifications $notifications,
    ) {}

    /**
     * Whether `$actor` may move this subscription to another plan: a
     * running, bought subscription (not the trial), changed by its owner or
     * the Super Admin.
     */
    public function canChangePlan(User $actor, Subscription $subscription): bool
    {
        return $this->isRunning($subscription)
            && ! $subscription->plan->is_trial
            && ! $subscription->plan->is_default_free
            && ($this->isOwner($actor, $subscription) || $this->isSuperAdmin($actor));
    }

    /**
     * The plans it can be moved to: for the owner, the plans on sale to
     * them; for the Super Admin, any plan of the role (as when giving one).
     * Never the trial, never the plan it holds.
     *
     * @return Builder<SubscriptionPlan>
     */
    public function plansToChangeTo(User $actor, Subscription $subscription): Builder
    {
        $owner = $subscription->user;

        $plans = $this->isSuperAdmin($actor)
            ? app(SubscriptionPurchaseService::class)->grantablePlansFor($owner)
            : SubscriptionPlan::purchasableFor($owner->role->subscriptionTargetRole())->orderBy('price');

        return $plans->where('is_trial', false)->whereKeyNot($subscription->plan_id);
    }

    /**
     * What moving to `$plan` comes to: the plan held counted as `credit`,
     * the new `price`, what is to be paid (`pay`) or given back (`refund`,
     * after the Admin's fee), and when it would end then.
     *
     * @return array{credit: float, price: float, pay: float, refund: float, ends_at: ?CarbonInterface}
     */
    public function quote(Subscription $subscription, SubscriptionPlan $plan): array
    {
        $settings = BillingSetting::current();
        $credit = (float) $subscription->plan_price;
        $price = (float) $plan->price;
        $difference = round($price - $credit, 2);

        return [
            'credit' => $credit,
            'price' => $price,
            'pay' => max(0.0, $difference),
            'refund' => $difference < 0 ? $settings->downgradeRefundOf(-$difference) : 0.0,
            'ends_at' => $plan->periodEndFrom($subscription->starts_at),
        ];
    }

    /**
     * The owner asks to move to `$plan`. With money to pay they send it and
     * give the transaction id; with money owed back they say where to send
     * it — either way the Admin approves it like a purchase. With nothing
     * either way it is made at once.
     *
     * @param  array{payer_provider?: MobileBankingProvider|string|null, payer_number?: ?string, transaction_id?: ?string, class_ids?: mixed, subject_ids?: mixed}  $details
     *
     * @throws ValidationException
     */
    public function request(User $owner, Subscription $subscription, SubscriptionPlan $plan, array $details): Payment
    {
        if (! $this->isOwner($owner, $subscription) || ! $this->canChangePlan($owner, $subscription)
            || ! $this->plansToChangeTo($owner, $subscription)->whereKey($plan->id)->exists()) {
            throw ValidationException::withMessages(['plan' => __('Your subscription cannot be changed to this plan.')]);
        }

        $selections = $plan->planConditions()->resolveSelections($details);
        $this->ensureFits($subscription, $plan);

        $payment = DB::transaction(function () use ($owner, $subscription, $plan, $details, $selections): Payment {
            // Serialises this user's payments, as a purchase does.
            User::whereKey($owner->id)->lockForUpdate()->first();

            if ($owner->pendingPayment()) {
                throw ValidationException::withMessages([
                    'plan' => __('You already have a payment waiting for approval.'),
                ]);
            }

            $quote = $this->quote($subscription, $plan);
            $number = User::normalizePhone($details['payer_number'] ?? null);
            $transactionId = trim((string) ($details['transaction_id'] ?? ''));
            $provider = $details['payer_provider'] ?? null;

            if ($quote['pay'] > 0) {
                ManualPaymentDetails::ensureUsable($provider, $number, $transactionId);
            } elseif ($quote['refund'] > 0) {
                ManualPaymentDetails::ensureReceivable($provider, $number);
            }

            $hasMoney = $quote['pay'] > 0 || $quote['refund'] > 0;

            $payment = $this->createChangePayment($subscription, $plan, $selections, [
                'amount' => $quote['pay'],
                'refund_amount' => $quote['refund'] > 0 ? $quote['refund'] : null,
                'gateway' => PaymentGateway::Manual,
                'gateway_transaction_id' => $quote['pay'] > 0 ? $transactionId : null,
                'payer_provider' => $hasMoney ? $provider : null,
                'payer_number' => $hasMoney ? $number : null,
            ]);

            Log::info('Plan change requested.', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'from_plan_id' => $subscription->plan_id,
                'to_plan_id' => $plan->id,
                ...collect($quote)->except('ends_at')->all(),
            ]);

            if (! $hasMoney) {
                $this->apply($payment, null, null);
            }

            return $payment;
        });

        $payment->refresh();
        $payment->isPending()
            ? $this->notifications->planChangeRequested($payment)
            : $this->notifications->planChanged($payment);

        return $payment;
    }

    /**
     * The Super Admin moves it to `$plan` straight away. The difference is
     * worked out as for the owner, but they write what was actually taken
     * (`amount`, 0 to waive it) or given back (`refund_amount`, with
     * `refund_method`), and why (`note`).
     *
     * @param  array{amount?: mixed, refund_amount?: mixed, refund_method?: PlanChangeRefundMethod|string|null, payer_provider?: MobileBankingProvider|string|null, transaction_id?: ?string, note?: ?string, class_ids?: mixed, subject_ids?: mixed}  $details
     *
     * @throws ValidationException
     */
    public function changeAsSuperAdmin(User $admin, Subscription $subscription, SubscriptionPlan $plan, array $details): Payment
    {
        if (! $this->isSuperAdmin($admin) || ! $this->canChangePlan($admin, $subscription)
            || ! $this->plansToChangeTo($admin, $subscription)->whereKey($plan->id)->exists()) {
            throw ValidationException::withMessages(['plan' => __('This subscription cannot be changed to this plan.')]);
        }

        $note = trim((string) ($details['note'] ?? ''));

        if ($note === '') {
            throw ValidationException::withMessages(['note' => __('Write why the plan is changed.')]);
        }

        $selections = $plan->planConditions()->resolveSelections($details);
        $this->ensureFits($subscription, $plan);

        $isDowngrade = $this->quote($subscription, $plan)['price'] < (float) $subscription->plan_price;
        $amount = $isDowngrade ? 0.0 : round((float) ($details['amount'] ?? 0), 2);
        $refund = $isDowngrade ? round((float) ($details['refund_amount'] ?? 0), 2) : 0.0;
        $method = $this->refundMethodFrom($details['refund_method'] ?? null);
        $transactionId = trim((string) ($details['transaction_id'] ?? '')) ?: null;

        if ($amount < 0 || $refund < 0) {
            throw ValidationException::withMessages(['amount' => __('The amount cannot be negative.')]);
        }

        if ($transactionId !== null && Payment::where('gateway_transaction_id', $transactionId)->exists()) {
            throw ValidationException::withMessages(['transaction_id' => __('This transaction ID has already been used.')]);
        }

        $payment = DB::transaction(function () use ($admin, $subscription, $plan, $details, $selections, $amount, $refund, $method, $transactionId, $note): Payment {
            $payment = $this->createChangePayment($subscription, $plan, $selections, [
                'amount' => $amount,
                'refund_amount' => $refund > 0 ? $refund : null,
                'gateway' => PaymentGateway::Admin,
                'gateway_transaction_id' => $transactionId,
                'payer_provider' => ($amount > 0 || $refund > 0) ? (($details['payer_provider'] ?? null) ?: null) : null,
                'review_note' => $note,
            ]);

            $this->apply($payment, $admin, $method);

            return $payment;
        });

        $payment->refresh();
        $this->notifications->planChanged($payment);

        return $payment;
    }

    /**
     * The Admin has checked the money (and sent back what a downgrade
     * owes): the subscription moves to its new plan. False when the request
     * was no longer pending.
     *
     * @throws ValidationException When the change can no longer be made — the request should be rejected.
     */
    public function approve(Payment $payment, ?User $reviewer, ?PlanChangeRefundMethod $refundMethod): bool
    {
        $approved = DB::transaction(function () use ($payment, $reviewer, $refundMethod): bool {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $payment?->isPending() || ! $payment->isPlanChange()) {
                return false;
            }

            $this->apply($payment, $reviewer, $refundMethod);

            return true;
        });

        if ($approved) {
            $this->notifications->planChanged($payment->refresh());
        }

        return $approved;
    }

    /**
     * Whether `$actor` may correct the classes/subjects picked on it: the
     * owner while nothing has been done with it, the Super Admin always —
     * on a running subscription whose plan asks for a pick.
     */
    public function canChangeSelections(User $actor, Subscription $subscription): bool
    {
        $conditions = PlanConditions::fromArray($subscription->conditions);

        if (! $this->isRunning($subscription) || (! $conditions->needsClassPick() && ! $conditions->needsSubjectPick())) {
            return false;
        }

        return $this->isSuperAdmin($actor)
            || ($this->isOwner($actor, $subscription) && ! $subscription->hasBeenUsed());
    }

    /**
     * Picks the classes/subjects again — from the conditions it was bought
     * with, not the plan's as they are now.
     *
     * @param  array{class_ids?: mixed, subject_ids?: mixed, note?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function changeSelections(User $actor, Subscription $subscription, array $details): void
    {
        if (! $this->canChangeSelections($actor, $subscription)) {
            throw ValidationException::withMessages(['class_ids' => $subscription->hasBeenUsed()
                ? __('This subscription has been used, so only the Super Admin can change its classes and subjects.')
                : __('The classes and subjects of this subscription cannot be changed.')]);
        }

        $byOwner = $this->isOwner($actor, $subscription);
        $note = trim((string) ($details['note'] ?? ''));

        if (! $byOwner && $note === '') {
            throw ValidationException::withMessages(['note' => __('Write why the classes or subjects are changed.')]);
        }

        $selections = PlanConditions::fromArray($subscription->conditions)->resolveSelections($details);
        $before = $subscription->selections;

        DB::transaction(function () use ($subscription, $selections): void {
            $subscription->update(['selections' => $selections]);
            $subscription->user->followPickedClass($selections);
        });

        Log::info('Subscription classes/subjects changed.', [
            'subscription_id' => $subscription->id,
            'before' => $before,
            'after' => $selections,
            'changed_by' => $actor->id,
            'note' => $note ?: null,
        ]);

        if (! $byOwner) {
            $this->notifications->selectionsChanged($subscription, $note);
        }
    }

    /**
     * Moves the subscription to the payment's plan and makes the payment
     * successful. Called inside the caller's transaction, with the payment
     * locked or just created.
     *
     * @throws ValidationException
     */
    private function apply(Payment $payment, ?User $reviewer, ?PlanChangeRefundMethod $refundMethod): void
    {
        $subscription = Subscription::whereKey($payment->subscription_id)->lockForUpdate()->first();
        $plan = $payment->plan;

        if (! $subscription || ! $plan || $subscription->plan_id !== $payment->previous_plan_id || ! $this->isRunning($subscription)) {
            throw ValidationException::withMessages(['plan' => __('The subscription has changed or ended since this was asked for — reject it.')]);
        }

        $this->ensureFits($subscription, $plan);

        $refund = (float) $payment->refund_amount;

        if ($refund > 0) {
            if (! $refundMethod) {
                throw ValidationException::withMessages(['refund_method' => __('Choose how the money was given back.')]);
            }

            if ($refundMethod === PlanChangeRefundMethod::Wallet) {
                if (! $subscription->user->role->holdsWallet()) {
                    throw ValidationException::withMessages(['refund_method' => __('This user has no wallet; send the money by bKash/Nagad.')]);
                }

                $this->wallet->credit($subscription->user, $refund, WalletTransactionType::PlanChangeRefund, $payment);
            }
        }

        $paid = (float) $payment->amount;

        $subscription->update([
            'plan_id' => $plan->id,
            'conditions' => $plan->conditions ?? [],
            'selections' => $payment->selections ?? [],
            'ends_at' => $plan->periodEndFrom($subscription->starts_at),
            // What it counts as at the next change: what has been paid
            // towards it, never more than the plan it holds now.
            'plan_price' => min((float) $plan->price, (float) $subscription->plan_price + $paid),
            'is_gift' => $subscription->is_gift && $paid <= 0,
        ]);

        $payment->update([
            'status' => PaymentStatus::Success,
            'refund_method' => $refund > 0 ? $refundMethod : null,
            'paid_at' => now(),
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ]);

        $subscription->user->followPickedClass($payment->selections ?? ['class_ids' => [], 'subject_ids' => []]);

        Log::info('Plan changed.', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'from_plan_id' => $payment->previous_plan_id,
            'to_plan_id' => $plan->id,
            'paid' => $paid,
            'refund' => $refund,
            'refund_method' => $refundMethod?->value,
            'reviewed_by' => $reviewer?->id,
        ]);
    }

    /**
     * @param  array{class_ids: array<int, int>, subject_ids: array<int, int>}  $selections
     * @param  array<string, mixed>  $money
     */
    private function createChangePayment(Subscription $subscription, SubscriptionPlan $plan, array $selections, array $money): Payment
    {
        return Payment::create([
            'user_id' => $subscription->user_id,
            'type' => PaymentType::PlanChange,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'previous_plan_id' => $subscription->plan_id,
            // No referrer: the difference earns nobody a commission.
            'referrer_id' => null,
            'selections' => $selections,
            'list_price' => (float) $plan->price,
            'discount_amount' => 0,
            'wallet_amount' => 0,
            'status' => PaymentStatus::Pending,
            ...$money,
        ]);
    }

    /**
     * The new plan must still leave time — its length from the start has
     * to reach past today — and room for everyone the owner shares it with.
     *
     * @throws ValidationException
     */
    private function ensureFits(Subscription $subscription, SubscriptionPlan $plan): void
    {
        $endsAt = $plan->periodEndFrom($subscription->starts_at);

        if ($endsAt !== null && $endsAt->isPast()) {
            throw ValidationException::withMessages(['plan' => __('This plan is shorter than the time already used, so it would have ended.')]);
        }

        $seats = $plan->planConditions()->seats ?? 1;
        $people = $subscription->members()->current()->count() + 1;

        if ($people > $seats) {
            throw ValidationException::withMessages(['plan' => __(':count people use this plan now and the new plan has room for :seats — take someone off first.', [
                'count' => $people,
                'seats' => $seats,
            ])]);
        }
    }

    private function refundMethodFrom(PlanChangeRefundMethod|string|null $method): ?PlanChangeRefundMethod
    {
        return $method instanceof PlanChangeRefundMethod ? $method : PlanChangeRefundMethod::tryFrom((string) $method);
    }

    private function isRunning(Subscription $subscription): bool
    {
        // `Subscription::scopeActive()`, asked of the loaded row — no query
        // per row of a list.
        return $subscription->plan !== null
            && $subscription->status === SubscriptionStatus::Active
            && ($subscription->ends_at === null || $subscription->ends_at->isFuture());
    }

    private function isOwner(User $actor, Subscription $subscription): bool
    {
        return $subscription->user_id === $actor->id;
    }

    private function isSuperAdmin(User $actor): bool
    {
        return app(SubscriptionPurchaseService::class)->canGrant($actor);
    }
}
