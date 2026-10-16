<?php

namespace App\Models;

use App\Enums\MobileBankingProvider;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything about billing and referrals that the Admin sets rather than
 * the code: the referral percentages and withdrawal reserves, the refund window, the
 * bonus for giving a phone number, whether spending credit needs an OTP,
 * the numbers customers send their payment to, and — the one setting here
 * that is not about money — how many questions one JSON import may carry.
 * One row, read through current() — no amount or percentage of these lives
 * anywhere in the code.
 */
#[Fillable([
    'referral_enabled', 'student_reward_percent', 'teacher_reward_percent', 'staff_reward_percent', 'referee_discount_percent',
    'commission_first_purchase_only', 'referral_lock_days', 'referral_change_days',
    'teacher_reserve_amount', 'teacher_min_withdrawal', 'student_reserve_amount', 'student_min_withdrawal',
    'group_member_change_days', 'teacher_device_limit', 'student_device_limit',
    'refund_window_days', 'phone_bonus_exams', 'otp_required_for_credit',
    'question_import_max', 'teacher_question_import_enabled',
    'difficulty_min_answers', 'difficulty_hard_max_percent', 'difficulty_medium_max_percent',
    'mistake_clear_streak', 'default_negative_marks',
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
        'student_reward_percent' => 0,
        'staff_reward_percent' => 0,
        'teacher_reward_percent' => 0,
        'commission_first_purchase_only' => false,
        'referral_lock_days' => 90,
        'referral_change_days' => 1,
        'teacher_reserve_amount' => 0,
        'teacher_min_withdrawal' => 0,
        'student_reserve_amount' => 0,
        'student_min_withdrawal' => 0,
        'group_member_change_days' => 30,
        'teacher_device_limit' => 3,
        'student_device_limit' => 2,
        'refund_window_days' => 0,
        'referee_discount_percent' => 0,
        'phone_bonus_exams' => 0,
        'otp_required_for_credit' => false,
        'question_import_max' => 100,
        'teacher_question_import_enabled' => true,
        'difficulty_min_answers' => 30,
        'difficulty_hard_max_percent' => 33,
        'difficulty_medium_max_percent' => 66,
        'mistake_clear_streak' => 2,
        'default_negative_marks' => 0.25,
    ];

    protected function casts(): array
    {
        return [
            'referral_enabled' => 'boolean',
            'student_reward_percent' => 'float',
            'staff_reward_percent' => 'float',
            'teacher_reward_percent' => 'float',
            'commission_first_purchase_only' => 'boolean',
            'referral_lock_days' => 'integer',
            'referral_change_days' => 'integer',
            'teacher_reserve_amount' => 'float',
            'teacher_min_withdrawal' => 'float',
            'student_reserve_amount' => 'float',
            'student_min_withdrawal' => 'float',
            'group_member_change_days' => 'integer',
            'teacher_device_limit' => 'integer',
            'student_device_limit' => 'integer',
            'refund_window_days' => 'integer',
            'referee_discount_percent' => 'float',
            'phone_bonus_exams' => 'integer',
            'otp_required_for_credit' => 'boolean',
            'question_import_max' => 'integer',
            'teacher_question_import_enabled' => 'boolean',
            'difficulty_min_answers' => 'integer',
            'difficulty_hard_max_percent' => 'float',
            'difficulty_medium_max_percent' => 'float',
            'mistake_clear_streak' => 'integer',
            'default_negative_marks' => 'float',
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
     * The referrer's share of the cash paid — a Teacher's, a Student's and a
     * Staff member's each have a percentage of their own.
     */
    public function rewardPercentFor(UserRole $referrerRole): float
    {
        return match ($referrerRole) {
            UserRole::Teacher => $this->teacher_reward_percent,
            UserRole::Staff => $this->staff_reward_percent,
            default => $this->student_reward_percent,
        };
    }

    /**
     * What always stays in a Teacher's or Student's wallet — spendable on
     * their own next subscription, never taken out as cash.
     */
    public function reserveFor(UserRole $role): float
    {
        return $role === UserRole::Teacher ? $this->teacher_reserve_amount : $this->student_reserve_amount;
    }

    /**
     * The smallest amount a Teacher or Student can ask to take out.
     */
    public function minimumWithdrawalFor(UserRole $role): float
    {
        return $role === UserRole::Teacher ? $this->teacher_min_withdrawal : $this->student_min_withdrawal;
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
