<?php

namespace App\Models;

use App\Enums\MobileBankingProvider;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything about billing and referrals that the Admin sets rather than
 * the code: the referral percentages, the refund window, how long wallet credit lasts, the
 * bonus for giving a phone number, whether spending credit needs an OTP,
 * the numbers customers send their payment to, and — the one setting here
 * that is not about money — how many questions one JSON import may carry.
 * One row, read through current() — no amount or percentage of these lives
 * anywhere in the code.
 */
#[Fillable([
    'referral_enabled', 'referrer_reward_percent', 'staff_reward_percent', 'referee_discount_percent',
    'credit_expiry_months', 'refund_window_days', 'phone_bonus_exams', 'otp_required_for_credit',
    'question_import_max',
    'bkash_number', 'nagad_number', 'rocket_number', 'payment_instructions',
])]
class BillingSetting extends Model
{
    /**
     * What applies until the Admin has saved the settings once: everything
     * about money switched off.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'referral_enabled' => false,
        'referrer_reward_percent' => 0,
        'staff_reward_percent' => 0,
        'refund_window_days' => 0,
        'referee_discount_percent' => 0,
        'phone_bonus_exams' => 0,
        'otp_required_for_credit' => false,
        'question_import_max' => 100,
    ];

    protected function casts(): array
    {
        return [
            'referral_enabled' => 'boolean',
            'referrer_reward_percent' => 'float',
            'staff_reward_percent' => 'float',
            'refund_window_days' => 'integer',
            'referee_discount_percent' => 'float',
            'credit_expiry_months' => 'integer',
            'phone_bonus_exams' => 'integer',
            'otp_required_for_credit' => 'boolean',
            'question_import_max' => 'integer',
        ];
    }

    /**
     * The settings in force. Not saved until the Admin saves them, so
     * merely reading a setting never writes to the database.
     */
    public static function current(): self
    {
        // Read once per request: a single purchase asks for the settings
        // half a dozen times. Kept on the application container, so it
        // lasts exactly as long as the request (or the test) does.
        if (! app()->bound(self::class)) {
            app()->instance(self::class, self::query()->first() ?? new self);
        }

        return app(self::class);
    }

    /**
     * A change to the settings applies from the very next read.
     */
    protected static function booted(): void
    {
        $forget = fn () => app()->forgetInstance(self::class);

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * The referrer's share of the cash paid, which depends on who they
     * are: Staff are paid in cash, so they have a percentage of their own.
     */
    public function rewardPercentFor(UserRole $referrerRole): float
    {
        return $referrerRole === UserRole::Staff ? $this->staff_reward_percent : $this->referrer_reward_percent;
    }

    /**
     * Until when a payment approved at `$approvedAt` can be refunded — and
     * so also the moment the reward it earned stops being provisional.
     * With no refund window that is straight away.
     */
    public function refundDeadlineFrom(CarbonInterface $approvedAt): CarbonInterface
    {
        return $approvedAt->copy()->addDays($this->refund_window_days);
    }

    /**
     * The numbers a customer can send a manual payment to, keyed by
     * provider — only the ones the Admin has filled in.
     *
     * @return array<string, string>
     */
    public function receivingNumbers(): array
    {
        return array_filter([
            MobileBankingProvider::Bkash->value => $this->bkash_number,
            MobileBankingProvider::Nagad->value => $this->nagad_number,
            MobileBankingProvider::Rocket->value => $this->rocket_number,
        ]);
    }
}
