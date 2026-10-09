<?php

namespace App\Services;

use App\Enums\ExamType;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

/**
 * The single place that answers "what's this user's active plan, and what
 * does it let them do" — used by both Teacher exam creation (ExamPolicy) and
 * Student self-practice generation (Auto-Generate and Manual alike), per
 * CLAUDE.md rule 7, so the limit logic is never duplicated per caller.
 */
class SubscriptionLimitService
{
    public function activeSubscriptionFor(User $user): ?Subscription
    {
        return $user->activeSubscription();
    }

    /**
     * The plan whose limits apply: the one the user is subscribed to, or —
     * for someone who never bought anything, or whose paid period ran out
     * — their role's default free plan. Null only when the Admin hasn't
     * set up a free plan for the role.
     */
    public function effectivePlanFor(User $user): ?SubscriptionPlan
    {
        return $this->planSummaryFor($user)['plan'];
    }

    /**
     * Null means unlimited. A user who has given their phone number gets
     * the Admin's bonus exams on top of a limited plan every month.
     */
    public function monthlyExamLimitFor(User $user): ?int
    {
        return $this->planSummaryFor($user)['monthly_limit'];
    }

    /**
     * Everything a page showing "your plan" needs — the subscription, the
     * plan whose limits apply, and the monthly limit — from one lookup,
     * for a caller that would otherwise ask for each separately.
     *
     * @return array{subscription: ?Subscription, plan: ?SubscriptionPlan, monthly_limit: ?int}
     */
    public function planSummaryFor(User $user): array
    {
        $subscription = $this->activeSubscriptionFor($user);
        $plan = $subscription?->plan;

        if (! $plan && ($role = $user->role->subscriptionTargetRole())) {
            $plan = SubscriptionPlan::defaultFreeFor($role)->first();
        }

        $limit = $plan?->monthly_exam_limit;

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'monthly_limit' => $limit === null
                ? null
                : $limit + (filled($user->phone) ? BillingSetting::current()->phone_bonus_exams : 0),
        ];
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $this->activeSubscriptionFor($user) !== null;
    }

    /**
     * The actual gate used before creating/publishing a Teacher exam and
     * before generating a Student self-practice exam (Auto-Generate or
     * Manual alike — both count together, per CLAUDE.md rule 9).
     */
    public function hasReachedMonthlyLimit(User $user, ExamType $examType): bool
    {
        $limit = $this->monthlyExamLimitFor($user);

        if ($limit === null) {
            return false;
        }

        return Exam::createdThisMonthBy($user, $examType)->count() >= $limit;
    }

    /**
     * Whether an exam that already exists was created inside its month's
     * allowance. Saving an exam is the moment it counts against the limit
     * (CLAUDE.md rule 10), so publishing it later must not be blocked just
     * because that same exam used up the last free slot.
     */
    public function isWithinMonthlyAllowance(User $user, Exam $exam): bool
    {
        $limit = $this->monthlyExamLimitFor($user);

        if ($limit === null) {
            return true;
        }

        $createdAt = $exam->created_at ?? now();

        $examsCreatedUpToThisOne = Exam::query()
            ->createdInMonthBy($user, $exam->exam_type, $createdAt)
            ->where('id', '<=', $exam->id)
            ->count();

        return $examsCreatedUpToThisOne <= $limit;
    }
}
