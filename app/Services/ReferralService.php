<?php

namespace App\Services;

use App\Enums\ExamType;
use App\Enums\PaymentStatus;
use App\Enums\ReferralSource;
use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\StudentReferral;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Every referral rule in one place: who can refer whom, when a code can
 * still be entered, the buyer's discount, and the referrer's reward. How
 * long a student stays under a referrer is StudentReferralService's.
 *
 * Who a buyer's referrer is depends on the buyer:
 *  - a **student** is under one referrer at a time, for a limited number of
 *    days (a code they typed, a link they signed up through, or the exam
 *    link of a teacher they sat an exam with). Every purchase in that time
 *    gets the discount, and earns the referrer a reward — or only the first
 *    one, if the Admin says so;
 *  - a **teacher** is referred once, for good, by the code they signed up
 *    with; only their first purchase gets the discount and the reward.
 *
 * Referrals are one level deep on purpose — a referrer is rewarded for the
 * people they brought in themselves, never for those people's own
 * referrals. Rewards only follow real money: they are worked out from the
 * cash part of a payment, never from the part paid with wallet credit
 * (credit must not be able to breed more credit). A payment remembers who
 * its referrer was when it was placed.
 *
 * Where a reward ends up depends on the referrer: a Teacher's or Student's
 * goes to their wallet, which they can spend on their subscription or take
 * out as cash above a reserve (WithdrawalService), and is only earned while
 * they have a paid subscription themselves; a Staff member's is paid out
 * with their earnings (StaffPayout). Either way it is one ReferralReward
 * row that matures when the payment's refund period ends.
 */
class ReferralService
{
    /**
     * Without look-alike characters (0/O, 1/I/L), since codes get read out
     * and typed by hand.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    public function __construct(private readonly StudentReferralService $studentReferrals) {}

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
     * Whether the user may enter a code now. A student: while they are
     * under no referrer, or the code they typed can still be swapped. A
     * teacher: until their first purchase — after that the "new customer"
     * the reward and discount exist for is no longer new.
     */
    public function canEnterCode(User $user): bool
    {
        if (! $this->isEnabled() || ! $this->canBeReferred($user)) {
            return false;
        }

        if ($user->role === UserRole::Student) {
            return $this->studentReferrals->canChange($user);
        }

        return $user->referred_by_id === null && ! $user->hasSuccessfulPayment();
    }

    /**
     * Records who referred this user. False when the code is unknown, is
     * the user's own, or the user can no longer be referred.
     *
     * @param  ReferralSource  $source  How a student got the code: typed, or through a referral link.
     */
    public function attach(User $user, ?string $code, ReferralSource $source = ReferralSource::Code): bool
    {
        $referrer = $this->findReferrer($code);

        if (! $referrer || $referrer->is($user) || ! $this->canEnterCode($user)) {
            return false;
        }

        if ($user->role === UserRole::Student) {
            return $this->studentReferrals->startFromCode($user, $referrer, $source);
        }

        $user->forceFill(['referred_by_id' => $referrer->id])->save();

        return true;
    }

    /**
     * A logged-in student opening a teacher's exam link comes under that
     * teacher's referral — unless they are already under a fixed one.
     */
    public function attributeFromExam(User $student, Exam $exam): void
    {
        $teacher = $exam->creator;

        if (! $this->isEnabled()
            || $student->role !== UserRole::Student
            || $exam->exam_type !== ExamType::TeacherExam
            || $teacher?->role !== UserRole::Teacher
            || ! $this->canRefer($teacher)) {
            return;
        }

        $this->studentReferrals->startFromExam($student, $teacher, $exam);
    }

    /**
     * Who would be credited with a purchase the buyer makes now, and the
     * student referral it would be made under (none for a teacher buyer).
     * Null when nobody would: no referrer, the programme is off, or a
     * teacher who has bought before.
     *
     * @return array{referrer: User, student_referral: ?StudentReferral}|null
     */
    public function referralFor(User $buyer): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($buyer->role === UserRole::Student) {
            $referral = $this->studentReferrals->current($buyer);

            return $referral ? ['referrer' => $referral->referrer, 'student_referral' => $referral] : null;
        }

        if ($buyer->referred_by_id === null || $buyer->hasSuccessfulPayment()) {
            return null;
        }

        return ['referrer' => $buyer->referrer, 'student_referral' => null];
    }

    /**
     * The buyer's discount, as a percentage: on every purchase of a student
     * while they are under a referrer, and on a referred teacher's first.
     */
    public function discountPercentFor(User $buyer): float
    {
        return $this->referralFor($buyer) ? BillingSetting::current()->referee_discount_percent : 0.0;
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
     * approved, when:
     *  - cash was paid, and the payment's referrer is (still) allowed to
     *    refer at this moment;
     *  - a Teacher or Student referrer has a paid subscription in force
     *    (none during the trial or on the free plan);
     *  - for a student's purchase: every purchase while they are under the
     *    referral, or only the first one under it if the Admin says so; for
     *    a teacher's purchase: only their first.
     *
     * The amount is fixed at this moment's percentage (kept on the row),
     * and the reward matures when the payment's refund period ends;
     * changing either setting later leaves it as it was. Safe to call twice
     * for the same payment.
     */
    public function rewardFor(Payment $payment): ?ReferralReward
    {
        $referrer = $payment->referrer;

        if (! $this->isEnabled() || ! $referrer || ! $this->canRefer($referrer) || $payment->amount <= 0
            || $payment->referralReward()->exists() || ! $this->isRewardedPurchase($payment)) {
            return null;
        }

        if (! $this->earnsCommission($referrer)) {
            Log::info('No reward: the referrer has no paid subscription.', [
                'payment_id' => $payment->id,
                'referrer_id' => $referrer->id,
            ]);

            return null;
        }

        $percent = $this->rewardPercentFor($referrer);
        $amount = round($payment->amount * $percent / 100, 2);

        if ($amount <= 0) {
            return null;
        }

        $maturesAt = $payment->refundable_until ?? now();

        $reward = new ReferralReward([
            'referrer_id' => $referrer->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'percent' => $percent,
            'matures_at' => $maturesAt,
            // Wallet money and a Staff member's reward are both cash owed;
            // neither expires.
            'expires_at' => null,
        ]);
        $reward->forceFill(['created_at' => now()])->save();

        Log::info('Referral reward recorded.', [
            'payment_id' => $payment->id,
            'referrer_id' => $referrer->id,
            'buyer_id' => $payment->user_id,
            'percent' => $percent,
            'amount' => $amount,
            'matures_at' => $maturesAt->toDateTimeString(),
        ]);

        return $reward;
    }

    /**
     * Whether a referrer would earn a reward on a purchase now. A Teacher or
     * Student only with a paid subscription in force — not during the free
     * trial, not on the free plan; a Staff member always (they have no
     * subscription; their reward is paid with their earnings).
     */
    public function earnsCommission(User $referrer): bool
    {
        if (! $referrer->role->holdsWallet()) {
            return true;
        }

        return $referrer->activeSubscriptions()
            ->contains(fn ($subscription): bool => $subscription->plan && ! $subscription->plan->is_trial && ! $subscription->plan->is_default_free);
    }

    /**
     * Whether this purchase is one that earns a reward: a student's, every
     * one under the referral (or only the first, as the Admin sets); a
     * teacher's, only their first.
     */
    private function isRewardedPurchase(Payment $payment): bool
    {
        $earlierSuccessful = Payment::query()
            ->where('status', PaymentStatus::Success)
            ->whereKeyNot($payment->id);

        if ($payment->student_referral_id === null) {
            return ! $earlierSuccessful->where('user_id', $payment->user_id)->exists();
        }

        return ! BillingSetting::current()->commission_first_purchase_only
            || ! $earlierSuccessful->where('student_referral_id', $payment->student_referral_id)->exists();
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
