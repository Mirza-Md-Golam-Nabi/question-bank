<?php

namespace App\Models;

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One purchase of a subscription plan. `amount` is what was paid in cash;
 * `list_price`, `discount_amount` and `wallet_amount` record how the plan's
 * price came down to it. A payment only ever changes state through
 * SubscriptionPurchaseService (approve / reject / refund).
 */
#[Fillable([
    'user_id', 'subscription_id', 'plan_id', 'referrer_id', 'student_referral_id', 'selections', 'list_price', 'discount_amount', 'wallet_amount',
    'amount', 'gateway', 'gateway_transaction_id', 'payer_provider', 'payer_number', 'status',
    'paid_at', 'reviewed_by', 'reviewed_at', 'refundable_until', 'refunded_at', 'review_note',
])]
class Payment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'payer_provider' => MobileBankingProvider::class,
            'status' => PaymentStatus::class,
            'selections' => 'array',
            'list_price' => 'float',
            'discount_amount' => 'float',
            'wallet_amount' => 'float',
            'amount' => 'float',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'refundable_until' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Who the buyer was referred by when the payment was placed — kept on
     * the payment, since a student's referrer can change later.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * The student's referral the payment was made under, if any.
     */
    public function studentReferral(): BelongsTo
    {
        return $this->belongsTo(StudentReferral::class);
    }

    /**
     * The exams counted against the subscription this payment bought — so
     * a list of payments can count each one's use in its own query.
     */
    public function subscriptionExams(): HasMany
    {
        return $this->hasMany(Exam::class, 'subscription_id', 'subscription_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * The payment itself, as a relation — only so that a rule written as a
     * scope can be asked of every row of a list in the list's own query
     * (`withExists(['itself as … ' => scope])`) instead of once per row.
     */
    public function itself(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'id');
    }

    /**
     * Paid from a number the buyer's referrer also uses — the referrer's
     * own phone number, or a number one of the referrer's own payments
     * came from. A hint for the Admin, not a block: it is what
     * self-referral through a second account looks like, but also what two
     * siblings paying from a parent's number looks like, so a person has
     * to judge it.
     */
    public function scopeSharingPayerNumberWithReferrer(Builder $query): Builder
    {
        $payerNumber = $query->qualifyColumn('payer_number');

        return $query
            ->whereNotNull($payerNumber)
            ->whereExists(fn (QueryBuilder $referrer) => $referrer
                ->from('users as referrer')
                ->whereColumn('referrer.id', $query->qualifyColumn('referrer_id'))
                ->where(fn (QueryBuilder $sameNumber) => $sameNumber
                    ->whereColumn('referrer.phone', $payerNumber)
                    ->orWhereExists(fn (QueryBuilder $theirPayment) => $theirPayment
                        ->from('payments as referrer_payment')
                        ->whereColumn('referrer_payment.user_id', 'referrer.id')
                        ->whereColumn('referrer_payment.payer_number', $payerNumber))));
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Success;
    }

    /**
     * An approved payment can be refunded only until its refund period —
     * fixed when it was approved — runs out. After that the referral
     * reward it earned has matured and may already have been spent or paid
     * out, so there is no undoing it.
     */
    public function isWithinRefundPeriod(): bool
    {
        return $this->isSuccessful() && $this->refundable_until?->isFuture() === true;
    }

    /**
     * Money comes back only for a subscription nothing has been done with —
     * inside the refund period, and with no exam, self-practice or past
     * paper counted against it (by the buyer or a group member).
     */
    public function isRefundable(): bool
    {
        return $this->isWithinRefundPeriod() && ! $this->subscription?->hasBeenUsed();
    }

    public function referralReward(): HasOne
    {
        return $this->hasOne(ReferralReward::class);
    }
}
