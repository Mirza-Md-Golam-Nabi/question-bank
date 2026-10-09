<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Every referral rule in one place: who can refer whom, when a code can
 * still be entered, the newcomer's discount, and the referrer's reward.
 *
 * Referrals are one level deep on purpose — a referrer is rewarded for the
 * people they brought in themselves, never for those people's own
 * referrals. Rewards only follow real money: they are worked out from the
 * cash part of the newcomer's first successful payment, never from the part
 * paid with wallet credit (credit must not be able to breed more credit).
 *
 * The rule is the same for every referrer; only where the reward ends up
 * differs. A Teacher's or Student's becomes wallet credit (WalletService
 * counts it); a Staff member's is paid out with their earnings
 * (StaffPayout). Either way it is one ReferralReward row that matures when
 * the payment's refund period ends.
 */
class ReferralService
{
    /**
     * Without look-alike characters (0/O, 1/I/L), since codes get read out
     * and typed by hand.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    public function __construct(private readonly WalletService $wallet) {}

    public function isEnabled(): bool
    {
        return BillingSetting::current()->referral_enabled;
    }

    /**
     * Who can bring people in: any Teacher or Student, and a Staff member
     * whose account is active (not waiting for approval, not suspended).
     */
    public function canRefer(User $user): bool
    {
        return $user->role->holdsWallet()
            || ($user->role === UserRole::Staff && $user->isActive());
    }

    /**
     * Who can be brought in: the roles that buy a subscription.
     */
    public function canBeReferred(User $user): bool
    {
        return $user->role->subscriptionTargetRole() !== null;
    }

    /**
     * The user's own code to share, made the first time it is asked for.
     */
    public function codeFor(User $user): string
    {
        if (blank($user->referral_code)) {
            do {
                $code = collect(range(1, self::CODE_LENGTH))
                    ->map(fn (): string => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])
                    ->implode('');
            } while (User::where('referral_code', $code)->exists());

            $user->forceFill(['referral_code' => $code])->save();
        }

        return $user->referral_code;
    }

    public function linkFor(User $user): string
    {
        return route('referral.visit', ['code' => $this->codeFor($user)]);
    }

    public function findReferrer(?string $code): ?User
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        $referrer = User::where('referral_code', $code)->first();

        return $referrer && $this->canRefer($referrer) ? $referrer : null;
    }

    /**
     * A code can be entered until the first purchase — after that the
     * "new customer" the reward and discount exist for is no longer new.
     */
    public function canEnterCode(User $user): bool
    {
        return $this->isEnabled()
            && $this->canBeReferred($user)
            && $user->referred_by_id === null
            && ! $user->hasSuccessfulPayment();
    }

    /**
     * Records who referred this user. False when the code is unknown, is
     * the user's own, or the user can no longer be referred.
     */
    public function attach(User $user, ?string $code): bool
    {
        $referrer = $this->findReferrer($code);

        if (! $referrer || $referrer->is($user) || ! $this->canEnterCode($user)) {
            return false;
        }

        $user->forceFill(['referred_by_id' => $referrer->id])->save();

        return true;
    }

    /**
     * The newcomer's discount on their first purchase, as a percentage.
     */
    public function discountPercentFor(User $buyer): float
    {
        if (! $this->isEnabled() || $buyer->referred_by_id === null || $buyer->hasSuccessfulPayment()) {
            return 0.0;
        }

        return BillingSetting::current()->referee_discount_percent;
    }

    /**
     * The share of a referred customer's first payment this user would
     * earn, as a percentage.
     */
    public function rewardPercentFor(User $referrer): float
    {
        return BillingSetting::current()->rewardPercentFor($referrer->role);
    }

    /**
     * What a referrer's rewards add up to, by where they stand: still
     * inside the refund period, or theirs for good (`paid` being the part
     * of that already included in a Staff payout).
     *
     * @return array{maturing: float, matured: float, paid: float}
     */
    public function rewardSummaryFor(User $referrer): array
    {
        $totals = ReferralReward::totalsByState($referrer->referralRewards()->getQuery());

        return [
            'maturing' => $totals['maturing'],
            'matured' => $totals['matured'],
            'paid' => $totals['paid'],
        ];
    }

    /**
     * Records the referrer's reward for a payment that has just been
     * approved — if it is the buyer's first successful one, any cash was
     * paid, and the referrer is (still) allowed to refer at this moment.
     *
     * The amount is fixed at this moment's percentage, and the reward
     * matures when the payment's refund period ends; changing either
     * setting later leaves it as it was. Safe to call twice for the same
     * payment.
     */
    public function rewardFor(Payment $payment): ?ReferralReward
    {
        $buyer = $payment->user;
        $referrer = $buyer->referrer;

        if (! $this->isEnabled() || ! $referrer || ! $this->canRefer($referrer) || $payment->amount <= 0) {
            return null;
        }

        $isFirstPurchase = ! $buyer->payments()
            ->where('status', PaymentStatus::Success)
            ->whereKeyNot($payment->id)
            ->exists();

        $amount = round($payment->amount * $this->rewardPercentFor($referrer) / 100, 2);

        if (! $isFirstPurchase || $amount <= 0 || $payment->referralReward()->exists()) {
            return null;
        }

        $maturesAt = $payment->refundable_until ?? now();

        $reward = new ReferralReward([
            'referrer_id' => $referrer->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'matures_at' => $maturesAt,
            // Only wallet credit expires; a Staff member's reward is cash owed.
            'expires_at' => $referrer->role->holdsWallet() ? $this->wallet->expiryFor($maturesAt) : null,
        ]);
        $reward->forceFill(['created_at' => now()])->save();

        Log::info('Referral reward recorded.', [
            'payment_id' => $payment->id,
            'referrer_id' => $referrer->id,
            'buyer_id' => $buyer->id,
            'amount' => $amount,
            'matures_at' => $maturesAt->toDateTimeString(),
        ]);

        return $reward;
    }

    /**
     * Cancels the reward a payment earned, when that payment is refunded
     * — otherwise a purchase-then-refund would mint free credit. A refund
     * is only possible while the reward is still maturing, so nothing has
     * been spent or paid out yet and there is nothing to claw back.
     */
    public function cancelRewardFor(Payment $payment): void
    {
        $reward = $payment->referralReward;

        if (! $reward || $reward->cancelled_at !== null) {
            return;
        }

        $reward->update(['cancelled_at' => now()]);

        Log::info('Referral reward cancelled after refund.', [
            'payment_id' => $payment->id,
            'referrer_id' => $reward->referrer_id,
            'amount' => $reward->amount,
        ]);
    }

    /**
     * Whether the money came from a number the referrer also uses. The
     * rule itself is Payment's `sharingPayerNumberWithReferrer` scope, so
     * a list of payments can ask it of every row in its own query.
     */
    public function sharesPayerNumberWithReferrer(Payment $payment): bool
    {
        return Payment::query()->whereKey($payment->id)->sharingPayerNumberWithReferrer()->exists();
    }
}
