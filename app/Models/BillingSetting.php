<?php

namespace App\Models;

use App\Enums\AdminNotificationTopic;
use App\Enums\MobileBankingProvider;
use App\Enums\PaperSourceType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything about billing and referrals that the Admin sets rather than
 * the code: the referral percentages and withdrawal reserves, the refund window, the
 * bonus for giving a phone number, whether spending credit needs an OTP,
 * the numbers customers send their payment to, what a Staff member earns
 * per past paper of each kind, and — the one setting here that is not
 * about money — how many questions one JSON import may carry.
 * One row, read through current() — no amount or percentage of these lives
 * anywhere in the code.
 */
#[Fillable([
    'referral_enabled', 'student_reward_percent', 'teacher_reward_percent', 'staff_reward_percent', 'referee_discount_percent',
    'commission_first_purchase_only', 'referral_lock_days', 'referral_change_days',
    'teacher_reserve_amount', 'teacher_min_withdrawal', 'student_reserve_amount', 'student_min_withdrawal',
    'group_member_change_days', 'teacher_device_limit', 'student_device_limit',
    'refund_window_days', 'phone_bonus_exams', 'otp_required_for_credit',
    'admin_notification_recipients', 'question_import_max', 'question_import_image_max_kb', 'question_import_images_max_mb', 'teacher_question_import_enabled', 'staff_paper_import_enabled', 'duplicate_similarity_percent',
    'difficulty_min_answers', 'difficulty_hard_max_percent', 'difficulty_medium_max_percent',
    'mistake_clear_streak', 'practice_minutes_per_question', 'practice_minutes_step', 'default_negative_marks', 'paper_rates',
    'performance_min_questions', 'performance_trend_exams', 'performance_falling_exams', 'performance_inactive_days',
    'school_question_release_days', 'onboarding_checklist_enabled',
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
        'question_import_image_max_kb' => 1024,
        'question_import_images_max_mb' => 20,
        'teacher_question_import_enabled' => true,
        'staff_paper_import_enabled' => false,
        'duplicate_similarity_percent' => 85,
        'difficulty_min_answers' => 30,
        'difficulty_hard_max_percent' => 33,
        'difficulty_medium_max_percent' => 66,
        'mistake_clear_streak' => 2,
        'practice_minutes_per_question' => 1,
        'practice_minutes_step' => 5,
        'default_negative_marks' => 0.25,
        'performance_min_questions' => 5,
        'performance_trend_exams' => 10,
        'performance_falling_exams' => 3,
        'performance_inactive_days' => 14,
        'school_question_release_days' => 60,
        'onboarding_checklist_enabled' => true,
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
            'question_import_image_max_kb' => 'integer',
            'question_import_images_max_mb' => 'integer',
            'teacher_question_import_enabled' => 'boolean',
            'staff_paper_import_enabled' => 'boolean',
            'duplicate_similarity_percent' => 'integer',
            'difficulty_min_answers' => 'integer',
            'difficulty_hard_max_percent' => 'float',
            'difficulty_medium_max_percent' => 'float',
            'mistake_clear_streak' => 'integer',
            'practice_minutes_per_question' => 'float',
            'practice_minutes_step' => 'integer',
            'default_negative_marks' => 'float',
            'performance_min_questions' => 'integer',
            'performance_trend_exams' => 'integer',
            'performance_falling_exams' => 'integer',
            'performance_inactive_days' => 'integer',
            'school_question_release_days' => 'integer',
            'onboarding_checklist_enabled' => 'boolean',
            'paper_rates' => 'array',
            'admin_notification_recipients' => 'array',
        ];
    }

    /**
     * The Admins who get the bell notifications of this kind: the ones
     * chosen in the settings, or every Admin when none is — still active
     * and still an Admin.
     *
     * @return Collection<int, User>
     */
    public function adminsToNotify(AdminNotificationTopic $topic): Collection
    {
        $chosen = $this->admin_notification_recipients[$topic->value] ?? [];

        return User::query()
            ->admins()
            ->where('status', UserStatus::Active)
            ->when($chosen !== [], fn ($query) => $query->whereKey($chosen))
            ->get();
    }

    /**
     * How long a self-practice exam of this many questions runs: the
     * Admin's minutes per question, rounded up to the Admin's step — with a
     * minute per question and a step of 5, 10 questions are 10 minutes and
     * 11–15 are 15. Never less than one step.
     */
    public function practiceMinutesFor(int $questionCount): int
    {
        $step = max(1, $this->practice_minutes_step);

        return max($step, (int) (ceil($questionCount * $this->practice_minutes_per_question / $step) * $step));
    }

    /**
     * What a Staff member earns for one approved past paper of this kind;
     * nothing until the Admin sets an amount for it.
     */
    public function paperRateFor(PaperSourceType $type): float
    {
        return (float) ($this->paper_rates[$type->value] ?? 0);
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
