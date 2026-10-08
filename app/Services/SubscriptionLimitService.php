<?php

namespace App\Services;

use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\Subscription;
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
     * Null means unlimited.
     */
    public function monthlyExamLimitFor(User $user): ?int
    {
        return $this->activeSubscriptionFor($user)?->plan?->monthly_exam_limit;
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
            ->where('created_by', $user->id)
            ->where('exam_type', $exam->exam_type)
            ->whereMonth('created_at', $createdAt->month)
            ->whereYear('created_at', $createdAt->year)
            ->where('id', '<=', $exam->id)
            ->count();

        return $examsCreatedUpToThisOne <= $limit;
    }
}
